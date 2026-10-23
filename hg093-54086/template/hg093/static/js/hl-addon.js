/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    'use strict';

    function start() {
        if (typeof jQuery === 'undefined') {
            setTimeout(start, 30);
            return;
        }
        (function ($) {
            var CONFIG = {
                apiBase: '',

                flySpeed: 8,

                flyInterval: 3000,

                suggestLimit: 10
            };

            var maccms = window.maccms || { path: '', tpl: '', mid: '1', url: '' };
            var sitePath = (maccms.path || '').replace(/\/+$/, '');
            function setCookie(name, value, days) {
                var d = new Date();
                d.setTime(d.getTime() + (days || 365) * 86400000);
                document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;expires=' + d.toUTCString();
            }
            function getCookie(name) {
                var m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]*)(;|$)'));
                return m ? decodeURIComponent(m[2]) : null;
            }
            function siteUrl(rel) {
                return sitePath + '/' + String(rel).replace(/^\/+/, '');
            }
            function tplUrl(rel) {
                return (maccms.tpl || '').replace(/\/+$/, '') + '/' + String(rel).replace(/^\/+/, '');
            }
            var EMOJI_PATH = tplUrl('static/emoji/');
            var EMOJI_NAMES = [
                '呆问',
                '注意',
                '沉思',
                '震撼',
                '呆滞',
                '欸嘿',
                '喝茶',
                '怒气',
                '哼',
                '玫瑰狗',
                '羞',
                '哈欠',
                '鼓掌',
                '吃瓜',
                '挖鼻',
                '奋斗',
                '吐',
                '打call',
                '给心心',
                '支持',
                '墨镜',
                '白眼',
                'doge',
                '坏笑',
                '难过',
                '生气',
                '委屈',
                '斜眼笑',
                '呆',
                '发怒',
                '惊吓',
                '呕吐',
                '思考',
                '微笑',
                '疑问',
                '大哭',
                '鼓励',
                '抠鼻',
                '亲亲',
                '调皮',
                '笑哭',
                '晕',
                '点赞',
                '害羞',
                '睡着',
                '色',
                '吐血',
                '无奈',
                '再见',
                '流汗',
                '偷笑',
                '抓狂',
                '问号',
                '困',
                '打脸',
                '闭嘴',
                '鄙视',
                '腼腆',
                '馋',
                '可爱',
                '发财',
                '生病',
                '流鼻血',
                '尴尬',
                '大佬'
            ];
            EMOJI_NAMES.sort(function (a, b) {
                return b.length - a.length;
            });
            var EMOJI_RE = new RegExp(
                EMOJI_NAMES.map(function (n) {
                    return n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                }).join('|'),
                'g'
            );
            function danmuEmojiHtml(text) {
                var s = String(text || '');
                var imgs = [];
                s = s.replace(/<img[^>]*>/gi, function (m) {
                    imgs.push(m);
                    return '\u0001' + (imgs.length - 1) + '\u0001';
                });
                s = s
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
                s = s.replace(EMOJI_RE, function (name) {
                    return (
                        '<img src="' +
                        EMOJI_PATH +
                        encodeURIComponent(name) +
                        '.gif" class="danmu-emoji" alt="' +
                        name +
                        '">'
                    );
                });
                return s.replace(/\u0001(\d+)\u0001/g, function (_, i) {
                    return imgs[Number(i)];
                });
            }
            function apiUrl(id) {
                return CONFIG.apiBase + '?id=' + id;
            }

            function getVodId() {
                if (window.player_aaaa && player_aaaa.id) return player_aaaa.id;

                var m = location.pathname.match(/\/id\/(\d+)/);
                return m ? m[1] : '';
            }

            $.fn.danmuFly = function (opt) {
                opt = $.extend(
                    {
                        info: '',
                        color: '#fff',
                        speed: CONFIG.flySpeed
                    },
                    opt || {}
                );
                if (!opt.info) return this;

                return this.each(function () {
                    var $container = $(this);
                    if (!$container.length) return;

                    var cw = $container.width();
                    var ch = $container.height();
                    if (cw < 50 || ch < 50) return;

                    var top = Math.floor(Math.random() * (ch - 60)) + 10;

                    var $item = $('<div class="danmu-item"></div>')
                        .html(danmuEmojiHtml(opt.info))
                        .css({
                            top: top + 'px',
                            left: cw + 'px',
                            color: opt.color
                        });

                    $item.append('<span class="danmu-close">×</span>');
                    $item.find('.danmu-close').on('click', function () {
                        $item.stop(true).remove();
                    });

                    $container.append($item);

                    var itemW = $item.width();
                    $item.animate({ left: -itemW - 20 + 'px' }, opt.speed * 1000, 'linear', function () {
                        $(this).remove();
                    });

                    $item.on('mouseenter', function () {
                        $(this).stop(true);
                    });
                    $item.on('mouseleave', function () {
                        var remaining = cw + itemW + 20 - (cw - parseInt($(this).css('left')));
                        $(this).animate(
                            { left: -itemW - 20 + 'px' },
                            (remaining / cw) * opt.speed * 1000,
                            'linear',
                            function () {
                                $(this).remove();
                            }
                        );
                    });
                });
            };

            var Danmu = {
                timer: null,
                rows: [],
                idx: 0,
                userOn: null,

                effectiveOn: function () {
                    if (this.userOn !== null) return this.userOn;
                    return parseInt(window.conch_theme && window.conch_theme.dumu, 10) === 1;
                },

                init: function () {
                    var $playerVideo = $('.player-video');
                    if (!$playerVideo.length) return;

                    if (parseInt(window.conch_theme && window.conch_theme.comment, 10) !== 1) return;

                    this.injectButtons();

                    if (this.effectiveOn()) {
                        this.fetch();
                    }
                },

                injectButtons: function () {
                    if ($('.danmu-nav-btns').length) return;

                    var isOn = this.effectiveOn();
                    var html =
                        '' +
                        '<div class="danmu-nav-btns">' +
                        '<button class="ep-nav-btn danmu-send-btn" type="button">发弹幕</button>' +
                        '<button class="ep-nav-btn danmu-toggle-btn ' +
                        (isOn ? 'on' : 'off') +
                        '" type="button">' +
                        (isOn
                            ? '弹幕<i class="icon-kaiguan3 dm-open iconfont"></i>'
                            : '弹幕<i class="icon-kaiguan4 dm-open iconfont"></i>') +
                        '</button>' +
                        '</div>';

                    $('.episode-nav').prepend(html);

                    var self = this;

                    $('.danmu-send-btn').on('click', function () {
                        self.gotoComment();
                    });

                    $('.danmu-toggle-btn').on('click', function () {
                        self.toggle();
                    });
                },

                gotoComment: function () {
                    $('.tab-btn').removeClass('active');
                    $('.tab-btn[data-tab="discuss"]').addClass('active');
                    $('.side-card.tab-panel').removeClass('active');
                    $('#panel-discuss').addClass('active');

                    setTimeout(function () {
                        var $input = $(
                            '.mac_comment textarea[name="comment_content"], .mac_comment textarea.comment-content, .mac_comment textarea'
                        );
                        if (!$input.length) {
                            $input = $('.mac_comment').find('textarea').first();
                        }
                        if ($input.length) {
                            $input.focus();

                            var val = $input.val();
                            $input.val('').val(val);
                        }
                    }, 400);
                },

                fetch: function () {
                    var vid = getVodId();
                    if (!vid) return;
                    if (!this.effectiveOn()) return;

                    var self = this;
                    $.post(
                        apiUrl('dan'),
                        { id: vid, mid: maccms.mid || '1' },
                        function (rows) {
                            if (!rows || !rows.length) return;
                            self.rows = rows;
                            self.idx = 0;
                            self.startFly();
                        },
                        'json'
                    );
                },

                startFly: function () {
                    var self = this;
                    this.stopFly();

                    var flyOne = function () {
                        if (!self.effectiveOn()) {
                            self.stopFly();
                            return;
                        }
                        if (self.idx >= self.rows.length) {
                            self.stopFly();
                            return;
                        }
                        var row = self.rows[self.idx++];
                        $('.player-video').danmuFly({ info: row.info || row.comment_content || '' });
                    };

                    flyOne();
                    this.timer = setInterval(flyOne, CONFIG.flyInterval);
                },

                stopFly: function () {
                    if (this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                },

                toggle: function () {
                    var newOn = !this.effectiveOn();
                    this.userOn = newOn;
                    setCookie('conch_danmu', newOn ? '1' : '0', 365);

                    var $btn = $('.danmu-toggle-btn');
                    $btn.html(
                        newOn
                            ? '弹幕<i class="icon-kaiguan3 dm-open iconfont"></i>'
                            : '弹幕<i class="icon-kaiguan4 dm-open iconfont"></i>'
                    )
                        .toggleClass('on', newOn)
                        .toggleClass('off', !newOn);

                    if (newOn) {
                        this.fetch();
                    } else {
                        this.stopFly();
                        $('.player-video').find('.danmu-item').remove();
                    }
                }
            };

            var SearchSuggest = {
                currentXhr: null,

                init: function () {
                    var self = this;
                    var $box = $('<div class="search-suggest"></div>').appendTo(document.body);

                    $('.sidebar-search-input, .nav-search-input, .search-input').each(function () {
                        var $input = $(this);
                        $input.data('ss-box', $box);

                        $input.on('input keyup', function (e) {
                            if (e.keyCode === 38 || e.keyCode === 40) {
                                self.navigate(e.keyCode, $input);
                                return;
                            }

                            if (e.keyCode === 13) {
                                self.hide($input);
                                return;
                            }
                            var wd = $.trim($input.val());
                            if (wd) self.fetch(wd, $input);
                            else self.hide($input);
                        });

                        $input.on('blur', function () {
                            setTimeout(function () {
                                self.hide($input);
                            }, 200);
                        });
                        $input.on('focus', function () {
                            var wd = $.trim($input.val());
                            if (wd) self.fetch(wd, $input);
                        });
                    });

                    $(document).on('click', function (e) {
                        if (!$(e.target).closest('.sidebar-search, .nav-search, .search-box, .search-suggest').length) {
                            $box.removeClass('show');
                        }
                    });

                    $(window).on('scroll resize', function () {
                        $box.removeClass('show');
                    });
                },

                fetch: function (wd, $input) {
                    var self = this;
                    if (this.currentXhr) this.currentXhr.abort();

                    this.currentXhr = $.post(
                        apiUrl('key'),
                        { name: wd },
                        function (rows) {
                            if (rows && rows.length) {
                                self.render(rows, wd, $input);
                            } else {
                                self.fetchNative(wd, $input);
                            }
                        },
                        'json'
                    ).fail(function () {
                        self.fetchNative(wd, $input);
                    });
                },

                fetchNative: function (wd, $input) {
                    var self = this;
                    $.getJSON(
                        siteUrl('/ajax/suggest.html?mid=') + (maccms.mid || '1') + '&wd=' + encodeURIComponent(wd),
                        function (res) {
                            if (res && res.list && res.list.length) {
                                var rows = [];
                                for (var i = 0; i < Math.min(res.list.length, CONFIG.suggestLimit); i++) {
                                    rows.push({ vod_name: res.list[i].name, type_name: '视频' });
                                }
                                self.render(rows, wd, $input);
                            } else {
                                self.hide($input);
                            }
                        }
                    ).fail(function () {
                        self.hide($input);
                    });
                },

                render: function (rows, wd, $input) {
                    var $box = $input.data('ss-box');
                    if (!$box || !$box.length) return;

                    var $wrap = $input.closest('.sidebar-search').length
                        ? $input.closest('.sidebar-search')
                        : $input.closest('.nav-search').length
                          ? $input.closest('.nav-search')
                          : $input.closest('.search-box');
                    var rect = ($wrap && $wrap.length ? $wrap[0] : $input[0]).getBoundingClientRect();
                    var type = $input.closest('.sidebar-search').length
                        ? 'sidebar'
                        : $input.closest('.nav-search').length
                          ? 'nav'
                          : 'searchbox';

                    var vw = window.innerWidth || document.documentElement.clientWidth;
                    var width = rect.width;
                    var maxW = vw - 24;
                    if (width > maxW) width = maxW;
                    var left = rect.left;
                    if (left + width > vw - 8) left = Math.max(8, vw - width - 8);

                    var top = rect.bottom + 8;
                    if (top > window.innerHeight - 320) top = Math.max(8, rect.top - 340);

                    $box.data('owner', $input[0]);
                    $box.attr('data-ss-type', type);
                    $box.css({ left: left, top: top, width: width });

                    var hideType = type === 'sidebar' || type === 'nav';
                    var html = '';
                    var limit = Math.min(rows.length, CONFIG.suggestLimit);
                    for (var i = 0; i < limit; i++) {
                        var name = rows[i].vod_name || rows[i].name || '';
                        var tname = rows[i].type_name || '';
                        var highlighted = name.replace(new RegExp(this.escapeReg(wd), 'gi'), '<em>$&</em>');
                        html +=
                            '<a class="ss-item" href="' +
                            siteUrl('/vod/search.html?wd=') +
                            encodeURIComponent(name) +
                            '">' +
                            '<i class="ss-icon icon-search-line iconfont"></i>' +
                            '<span class="ss-name">' +
                            highlighted +
                            '</span>' +
                            (!hideType && tname ? '<span class="ss-type">' + this.escHtml(tname) + '</span>' : '') +
                            '</a>';
                    }
                    $box.html(html).addClass('show');
                },

                escHtml: function (str) {
                    return String(str == null ? '' : str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                },

                navigate: function (keyCode, $input) {
                    var $box = $input.data('ss-box');
                    var $items = $box.find('.ss-item');
                    if (!$items.length) return;
                    var current = $items.index($items.filter('.active'));
                    $items.removeClass('active').css('background', '');
                    if (keyCode === 40) current = (current + 1) % $items.length;
                    else current = (current - 1 + $items.length) % $items.length;
                    $items.eq(current).addClass('active');

                    var name = $items.eq(current).find('.ss-name').text();
                    $input.val(name);
                },

                hide: function ($input) {
                    var $box = $input.data('ss-box');
                    if ($box && $box.length && $box.data('owner') === $input[0]) $box.removeClass('show');
                },

                escapeReg: function (str) {
                    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                }
            };

            $(function () {
                CONFIG.apiBase = tplUrl('/static/php/conch.php');
                Danmu.init();
                if (parseInt(window.conch_theme && window.conch_theme.suggest, 10) === 1) {
                    SearchSuggest.init();
                }
                NavFloat.init();
                
                const backtop = document.querySelector('.hl-backtop');
                if(backtop){
                    function scrollCheck(){
                        const st = window.scrollY || document.documentElement.scrollTop;
                        if(st > 300){backtop.classList.add('show');}else{backtop.classList.remove('show');}
                    }
                    backtop.onclick = function(){
                    window.scrollTo({top:0,behavior:'smooth'});
                    }
                    window.addEventListener('scroll',scrollCheck);
                    scrollCheck();
                }
            });

            var NavFloat = {
                init: function () {
                    var nav = document.querySelector('.navbar');
                    if (!nav) return;
                    var hero = document.querySelector('.hero-slider');
                    var ticking = false;

                    function update() {
                        ticking = false;
                        var y = window.scrollY || document.documentElement.scrollTop;
                        var threshold = hero ? hero.offsetHeight - 120 : 10;
                        nav.classList.toggle('navbar-fixed', y > threshold);
                    }

                    function onScroll() {
                        if (!ticking) {
                            ticking = true;
                            requestAnimationFrame(update);
                        }
                    }

                    window.addEventListener('scroll', onScroll, { passive: true });
                    window.addEventListener('resize', onScroll, { passive: true });
                    update();
                }
            };
        })(jQuery);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
