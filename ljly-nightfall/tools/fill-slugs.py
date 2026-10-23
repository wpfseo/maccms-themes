#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""为 nf_actor / nf_tag 补齐拼音 slug（幂等，只补 slug 为空且达到建档门槛的行）

用法：
    /opt/ljly_slug/venv/bin/python fill-slugs.py /www/wwwroot/www.ljly.net [--seed /opt/ljly_slug/seed]

说明：
    - 演员门槛 10 部、标签门槛 100 部（与控制器 MIN 一致），避免生成海量薄页
    - 已有 slug 的行不动，保证老 URL 稳定
    - --seed 目录下可放 slug_actor.tsv / slug_tag.tsv（name<TAB>slug）优先复用历史映射
"""
import hashlib
import json
import os
import re
import subprocess
import sys

from pypinyin import lazy_pinyin

ROOT = sys.argv[1] if len(sys.argv) > 1 else '/www/wwwroot/www.ljly.net'
SEED = None
for i, a in enumerate(sys.argv):
    if a == '--seed' and i + 1 < len(sys.argv):
        SEED = sys.argv[i + 1]

PHP = '/www/server/php/73/bin/php'
MIN_ACTOR = 10
MIN_TAG = 100
MIN_DIRECTOR = 10
MIN_SERIES = 3


def db_config():
    code = 'echo json_encode(include "%s/application/database.php");' % ROOT
    out = subprocess.run([PHP, '-r', code], capture_output=True, text=True)
    if out.returncode != 0:
        raise SystemExit('读取 database.php 失败: ' + out.stderr.strip())
    return json.loads(out.stdout)


def mysql_args(cfg):
    return [
        'mysql',
        '-h', str(cfg.get('hostname') or '127.0.0.1'),
        '-P', str(cfg.get('hostport') or 3306),
        '-u', str(cfg['username']),
        '-p%s' % cfg['password'],
        '--default-character-set=utf8mb4',
        str(cfg['database']),
    ]


def my_query(cfg, sql):
    r = subprocess.run(mysql_args(cfg) + ['-N', '-B', '-e', sql],
                       capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit('查询失败: ' + r.stderr.strip())
    return [line.split('\t') for line in r.stdout.splitlines() if line]


def my_exec_file(cfg, path):
    with open(path, 'rb') as f:
        r = subprocess.run(mysql_args(cfg), stdin=f, capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit('写库失败: ' + r.stderr.strip())


def to_slug(name):
    parts = lazy_pinyin(name, errors='default')
    s = ''.join(parts).lower()
    s = re.sub(r'[^a-z0-9]', '', s)
    if not s:
        s = 'p' + hashlib.md5(name.encode('utf-8')).hexdigest()[:6]
    return s[:60]


def sql_str(v):
    return "'" + v.replace('\\', '\\\\').replace("'", "''") + "'"


def load_seed(kind):
    """历史映射 name -> slug"""
    if not SEED:
        return {}
    path = os.path.join(SEED, 'slug_%s.tsv' % kind)
    if not os.path.isfile(path):
        return {}
    out = {}
    with open(path, encoding='utf-8') as f:
        for line in f:
            line = line.rstrip('\n')
            if '\t' in line:
                n, s = line.split('\t', 1)
                if n and s:
                    out[n] = s
    return out


def process(cfg, table, min_count, kind):
    taken = set(r[0] for r in my_query(
        cfg, "SELECT slug FROM %s WHERE slug<>''" % table) if r and r[0])
    rows = my_query(cfg, "SELECT name FROM %s WHERE vod_count>=%d AND slug='' "
                         "ORDER BY vod_count DESC, name" % (table, min_count))
    names = [r[0] for r in rows if r]
    if not names:
        print('%-10s 无需补齐（已有 slug %d 个）' % (table, len(taken)))
        return 0

    seed = load_seed(kind)
    assigns = []
    for n in names:
        slug = seed.get(n)
        if slug and slug in taken:
            slug = None
        if not slug:
            base = to_slug(n)
            slug = base
            i = 2
            while slug in taken:
                slug = base + str(i)
                i += 1
        taken.add(slug)
        assigns.append((n, slug))

    tmp = '/tmp/_fill_slug_%s.sql' % table
    with open(tmp, 'w', encoding='utf-8') as f:
        f.write('SET NAMES utf8mb4;\n')
        for n, s in assigns:
            f.write('UPDATE %s SET slug=%s WHERE name=%s AND slug=\'\';\n'
                    % (table, sql_str(s), sql_str(n)))
    my_exec_file(cfg, tmp)
    os.unlink(tmp)
    print('%-10s 补齐 %d 个（seed 命中 %d）' % (table, len(assigns),
                                            sum(1 for n, _ in assigns if n in seed)))
    return len(assigns)


if __name__ == '__main__':
    cfg = db_config()
    a = process(cfg, 'nf_actor', MIN_ACTOR, 'actor')
    b = process(cfg, 'nf_tag', MIN_TAG, 'tag')
    try:
        c = process(cfg, 'nf_director', MIN_DIRECTOR, 'director')
    except SystemExit as e:
        c = 0
        print('nf_director 跳过（%s）' % e)
    try:
        d = process(cfg, 'nf_series', MIN_SERIES, 'series')
    except SystemExit as e:
        d = 0
        print('nf_series 跳过（%s）' % e)
    total = my_query(cfg, "SELECT COUNT(*) FROM nf_actor WHERE slug<>''")[0][0]
    print('done: 本次写入 %d + %d + %d + %d，库内有效 slug 演员 %s 个' % (a, b, c, d, total))
