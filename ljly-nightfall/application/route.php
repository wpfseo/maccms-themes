<?php
return array (
  '__pattern__' => 
  array (
    'id' => '[\\s\\S]*?',
    'ids' => '[\\s\\S]*?',
    'wd' => '[\\s\\S]*',
    'en' => '[\\s\\S]*?',
    'state' => '[\\s\\S]*?',
    'area' => '[\\s\\S]*',
    'year' => '[\\s\\S]*?',
    'lang' => '[\\s\\S]*?',
    'letter' => '[\\s\\S]*?',
    'actor' => '[\\s\\S]*?',
    'director' => '[\\s\\S]*?',
    'tag' => '[\\s\\S]*?',
    'class' => '[\\s\\S]*?',
    'order' => '[\\s\\S]*?',
    'by' => '[\\s\\S]*?',
    'file' => '[\\s\\S]*?',
    'name' => '[\\s\\S]*?',
    'url' => '[\\s\\S]*?',
    'type' => '[\\s\\S]*?',
    'sex' => '[\\s\\S]*?',
    'version' => '[\\s\\S]*?',
    'blood' => '[\\s\\S]*?',
    'starsign' => '[\\s\\S]*?',
    'page' => '\\d+',
    'key' => '[a-zA-Z0-9\\-]+',
    'ajax' => '\\d+',
    'tid' => '\\d+',
    'mid' => '\\d+',
    'rid' => '\\d+',
    'pid' => '\\d+',
    'sid' => '\\d+',
    'nid' => '\\d+',
    'uid' => '\\d+',
    'level' => '\\d+',
    'score' => '\\d+',
    'limit' => '\\d+',
    'a' => '[\\s\\S]+?',
    'b' => '[\\s\\S]+?',
    't' => '[\\s\\S]+?',
    'k' => '[\\s\\S]+?',
  ),
  'cast$' => 
  array (
    0 => 'nightactor/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'cast/page/<page?>' => 
  array (
    0 => 'nightactor/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'cast/<name?>-<page?>' => 
  array (
    0 => 'nightactor/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'cast/<name?>' => 
  array (
    0 => 'nightactor/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'genre$' => 
  array (
    0 => 'nighttag/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'rank$' => 
  array (
    0 => 'nightrank/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'rank/<key>' => 
  array (
    0 => 'nightrank/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'genre/page/<page?>' => 
  array (
    0 => 'nighttag/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'genre/<name?>-<page?>' => 
  array (
    0 => 'nighttag/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'genre/<name?>' => 
  array (
    0 => 'nighttag/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'director$' => 
  array (
    0 => 'nightdirector/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'director/page/<page?>' => 
  array (
    0 => 'nightdirector/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'director/<name?>-<page?>' => 
  array (
    0 => 'nightdirector/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'director/<name?>' => 
  array (
    0 => 'nightdirector/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'duanju$' => 
  array (
    0 => 'nightchannel/duanju',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'duanju/page/<page?>' => 
  array (
    0 => 'nightchannel/duanju',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'sports$' => 
  array (
    0 => 'nightchannel/sports',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'sports/page/<page?>' => 
  array (
    0 => 'nightchannel/sports',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'jieshuo$' => 
  array (
    0 => 'nightchannel/jieshuo',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'jieshuo/page/<page?>' => 
  array (
    0 => 'nightchannel/jieshuo',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'combo$' => 
  array (
    0 => 'nightcombo/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'combo/page/<page?>' => 
  array (
    0 => 'nightcombo/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'combo/<k?>-<page?>' => 
  array (
    0 => 'nightcombo/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'combo/<k?>' => 
  array (
    0 => 'nightcombo/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'year$' => 
  array (
    0 => 'nightyear/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'year/page/<page?>' => 
  array (
    0 => 'nightyear/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'year/<k?>-<page?>' => 
  array (
    0 => 'nightyear/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'year/<k?>' => 
  array (
    0 => 'nightyear/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'series$' => 
  array (
    0 => 'nightseries/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'series/page/<page?>' => 
  array (
    0 => 'nightseries/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'series/<name?>-<page?>' => 
  array (
    0 => 'nightseries/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'series/<name?>' => 
  array (
    0 => 'nightseries/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'sitehome' =>
  array (
    0 => 'index/home',
    1 =>
    array (
    ),
    2 =>
    array (
    ),
  ),
  'publish-<id>' =>
  array (
    0 => 'index/publish_group',
    1 =>
    array (
    ),
    2 =>
    array (
    ),
  ),
  'map' => 
  array (
    0 => 'map/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'rss' => 
  array (
    0 => 'rss/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'index-<page?>' => 
  array (
    0 => 'index/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'gbook-<page?>' => 
  array (
    0 => 'gbook/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'gbook$' => 
  array (
    0 => 'gbook/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'topic-<page?>' => 
  array (
    0 => 'topic/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'topic$' => 
  array (
    0 => 'nighttopic/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'topic/<key>' => 
  array (
    0 => 'nighttopic/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'topicdetail-<id>' => 
  array (
    0 => 'topic/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'actor-<page?>' => 
  array (
    0 => 'actor/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'actor$' => 
  array (
    0 => 'actor/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'actordetail-<id>' => 
  array (
    0 => 'actor/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'actorshow/<area?>-<blood?>-<by?>-<letter?>-<level?>-<order?>-<page?>-<sex?>-<starsign?>' => 
  array (
    0 => 'actor/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'role-<page?>' => 
  array (
    0 => 'role/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'role$' => 
  array (
    0 => 'role/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'roledetail-<id>' => 
  array (
    0 => 'role/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'roleshow/<by?>-<letter?>-<level?>-<order?>-<page?>-<rid?>' => 
  array (
    0 => 'role/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodtype/<id>-<page?>' => 
  array (
    0 => 'vod/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodtype/<id>' => 
  array (
    0 => 'vod/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'voddetail/<id>' => 
  array (
    0 => 'vod/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodrss-<id>' => 
  array (
    0 => 'vod/rss',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodplay/<id>-<sid>-<nid>' => 
  array (
    0 => 'vod/play',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'voddown/<id>-<sid>-<nid>' => 
  array (
    0 => 'vod/down',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodshow/<id>-<area?>-<by?>-<class?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>' => 
  array (
    0 => 'vod/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodsearch/<wd?>-<actor?>-<area?>-<by?>-<class?>-<director?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>' => 
  array (
    0 => 'vod/search',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodplot/<id>-<page?>' => 
  array (
    0 => 'vod/plot',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'vodplot/<id>' => 
  array (
    0 => 'vod/plot',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'arttype/<id>-<page?>' => 
  array (
    0 => 'art/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'arttype/<id>' => 
  array (
    0 => 'art/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artshow-<id>' => 
  array (
    0 => 'art/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artdetail-<id>-<page?>' => 
  array (
    0 => 'art/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artdetail-<id>' => 
  array (
    0 => 'art/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artrss-<id>-<page>' => 
  array (
    0 => 'art/rss',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artshow/<id>-<by?>-<class?>-<level?>-<letter?>-<order?>-<page?>-<tag?>' => 
  array (
    0 => 'art/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artsearch/<wd?>-<by?>-<class?>-<level?>-<letter?>-<order?>-<page?>-<tag?>' => 
  array (
    0 => 'art/search',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'artread/<id>-<page?>' =>
  array(
    0 => 'art/read',
    1 =>
    array(
    ),
    2 =>
    array(
    ),
  ),
    'manga-<page?>' =>
    array (
        0 => 'manga/index',
        1 =>
            array (
            ),
        2 =>
            array (
            ),
    ),
    'manga$' =>
        array (
            0 => 'manga/index',
            1 =>
                array (
                ),
            2 =>
                array (
                ),
        ),
  'mangatype/<id>-<page?>' => 
  array (
    0 => 'manga/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangatype/<id>' => 
  array (
    0 => 'manga/type',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangadetail-<id>' => 
  array (
    0 => 'manga/detail',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangaplay/<id>-<sid>-<nid>' => 
  array (
    0 => 'manga/play',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangadown/<id>-<sid>-<nid>' => 
  array (
    0 => 'manga/down',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangashow/<id>-<area?>-<by?>-<class?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>' => 
  array (
    0 => 'manga/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'mangasearch/<wd?>-<actor?>-<area?>-<by?>-<class?>-<director?>-<lang?>-<letter?>-<level?>-<order?>-<page?>-<state?>-<tag?>-<year?>' => 
  array (
    0 => 'manga/search',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'label-<file>' => 
  array (
    0 => 'label/index',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'liveshow' => 
  array (
    0 => 'live/show',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
  'liveplay/<id>' => 
  array (
    0 => 'live/play',
    1 => 
    array (
    ),
    2 => 
    array (
    ),
  ),
);
