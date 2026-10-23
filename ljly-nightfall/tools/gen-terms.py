#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""生成筛选维度的拼音映射表（area / class / lang）

用法：
    /opt/ljly_slug/venv/bin/python gen-terms.py /www/wwwroot/www.ljly.net

产出：
    application/data/nightfall/terms.php   给 PHP 读的映射表（含 slug / 显示名 / 查询值 / 数量）

规则：
    1. 取值拆逗号/顿号等分隔符后统计，量 < MIN_COUNT 的丢弃（多为采集截断的脏值：美、意大、中国香）
    2. class 里量大的截断值（现代都市短 22501 部）按 mac_type.type_name 前缀匹配修正显示名，
       但查询仍用库里的原值，避免改数据
    3. slug 用 pypinyin 生成纯 a-z0-9，冲突加 -2 / -3 后缀
"""
import json
import re
import subprocess
import sys

from pypinyin import lazy_pinyin

ROOT = sys.argv[1] if len(sys.argv) > 1 else '/www/wwwroot/www.ljly.net'
PHP = '/www/server/php/73/bin/php'
MIN_COUNT = 100

DIMS = [
    ('area', 'vod_area'),
    ('class', 'vod_class'),
    ('lang', 'vod_lang'),
]


def db_config():
    code = 'echo json_encode(include "%s/application/database.php");' % ROOT
    out = subprocess.run([PHP, '-r', code], capture_output=True, text=True)
    if out.returncode != 0:
        raise SystemExit('读取 database.php 失败: ' + out.stderr.strip())
    return json.loads(out.stdout)


def mysql_args(cfg):
    return [
        'mysql', '-h', str(cfg.get('hostname') or '127.0.0.1'),
        '-P', str(cfg.get('hostport') or 3306),
        '-u', str(cfg['username']), '-p%s' % cfg['password'],
        '--default-character-set=utf8mb4', str(cfg['database']),
    ]


def my_query(cfg, sql):
    r = subprocess.run(mysql_args(cfg) + ['-N', '-B', '-e', sql], capture_output=True, text=True)
    if r.returncode != 0:
        raise SystemExit('查询失败: ' + r.stderr.strip())
    return [line.split('\t') for line in r.stdout.splitlines() if line]


def slugify(text):
    out = []
    for py in lazy_pinyin(text):
        out.append(re.sub(r'[^a-z0-9]', '', py.lower()))
    s = ''.join(out)
    s = re.sub(r'[^a-z0-9]', '', s)
    return s[:48]


def main():
    cfg = db_config()

    types = [(r[0], r[1]) for r in my_query(cfg, "SELECT type_name, type_en FROM mac_type WHERE type_status=1")]

    result = {}
    for dim, field in DIMS:
        rows = my_query(cfg, "SELECT %s FROM mac_vod WHERE vod_status=1 AND %s<>''" % (field, field))
        cnt = {}
        for (v,) in rows:
            for one in re.split(r'[,，/、]+', v or ''):
                one = one.strip()
                if not one or len(one) > 12:
                    continue
                cnt[one] = cnt.get(one, 0) + 1

        kept = {k: v for k, v in cnt.items() if v >= MIN_COUNT}
        dropped = len(cnt) - len(kept)

        # 截断值修正：仅当「原值 + 剧」正好等于某个分类名时才改显示名
        # （采源把「现代都市短剧」截成「现代都市短」；而「剧情」→「剧情片」属于误判，不能动）
        type_names = {t[0] for t in types}
        items = []
        for val, num in kept.items():
            display = val
            if dim == 'class' and (val + '剧') in type_names:
                display = val + '剧'
            items.append({'v': val, 'n': display, 'c': num})

        # 同显示名合并（截断值与完整值并存时，保留量大的）
        merged = {}
        for it in items:
            key = it['n']
            if key in merged:
                if it['c'] > merged[key]['c']:
                    merged[key] = it
            else:
                merged[key] = it
        items = sorted(merged.values(), key=lambda x: -x['c'])

        used = set()
        for it in items:
            base = slugify(it['n']) or 'x'
            slug, i = base, 2
            while slug in used:
                slug = '%s-%d' % (base, i)
                i += 1
            used.add(slug)
            it['s'] = slug

        result[dim] = items
        print('%-6s 去重 %3d 个，保留 %3d 个（量>=%d），丢弃脏值 %d 个' %
              (dim, len(cnt), len(items), MIN_COUNT, dropped))
        for it in items[:6]:
            print('    %-10s -> %-14s %-14s %d' % (it['v'], it['n'], it['s'], it['c']))

    # 写 PHP 文件
    out_path = ROOT + '/application/data/nightfall/terms.php'
    lines = ["<?php", "/* 筛选维度拼音映射表（自动生成，勿手改）",
             "   生成命令：/opt/ljly_slug/venv/bin/python tools/gen-terms.py " + ROOT,
             "   字段：v=库内查询值 n=显示名 s=URL slug c=作品数 */", "return ["]
    for dim, _ in DIMS:
        lines.append("    '%s' => [" % dim)
        for it in result[dim]:
            lines.append("        ['v' => %s, 'n' => %s, 's' => %s, 'c' => %d]," % (
                php_str(it['v']), php_str(it['n']), php_str(it['s']), it['c']))
        lines.append("    ],")
    lines.append("];")
    content = "\n".join(lines) + "\n"
    with open(out_path, 'w', encoding='utf-8', newline='\n') as f:
        f.write(content)
    print('written: %s (%d bytes)' % (out_path, len(content.encode('utf-8'))))

    # 备份 tsv，便于人工校对
    tsv = ROOT + '/tools/terms.tsv'
    with open(tsv, 'w', encoding='utf-8', newline='\n') as f:
        for dim, _ in DIMS:
            for it in result[dim]:
                f.write('%s\t%s\t%s\t%s\t%d\n' % (dim, it['v'], it['n'], it['s'], it['c']))
    print('written: %s' % tsv)


def php_str(s):
    return "'" + str(s).replace('\\', '\\\\').replace("'", "\\'") + "'"


if __name__ == '__main__':
    main()
