/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

conch_theme.theme = (typeof conch_theme.theme !== 'undefined') ? conch_theme.theme : 0;
if (conch_theme.theme == 1) {
    
(function () {
    var track = document.getElementById('track');
    if (!track) return;
    var originalCards = track.querySelectorAll('.triple-card');
    var total = originalCards.length;
    if (total === 0) return;

    for (var i = 0; i < total; i++) {
        var clone = originalCards[i].cloneNode(true);
        clone.setAttribute('data-index', i + total);
        track.appendChild(clone);
    }

    var cards = track.querySelectorAll('.triple-card');
    var allTotal = cards.length;
    var bubbles = document.querySelectorAll('.carousel-bubble');
    var current = 0;
    var timer = null;
    var isJumping = false;

    function getStep() {
        var first = track.querySelector('.triple-card');
        if (!first) return 220 + 20;
        var gap = parseFloat(getComputedStyle(track).gap) || 20;
        return first.getBoundingClientRect().width + gap;
    }

    function goTo(index) {
        if (isJumping) return;
        var step = getStep();

        var targetIndex = index;
        var isClone = false;
        if (index >= total) {
            targetIndex = index;
            isClone = true;
        }

        current = index;
        track.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        track.style.transform = 'translateX(-' + targetIndex * step + 'px)';

        var bubbleIndex = index % total;
        for (var i = 0; i < bubbles.length; i++) {
            if (i === bubbleIndex) bubbles[i].classList.add('active');
            else bubbles[i].classList.remove('active');
        }

        if (isClone) {
            isJumping = true;
            var onEnd = function () {
                track.removeEventListener('transitionend', onEnd);
                var jumpIndex = index - total;
                current = jumpIndex;
                track.style.transition = 'none';
                track.style.transform = 'translateX(-' + jumpIndex * step + 'px)';
                void track.offsetHeight;
                track.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                isJumping = false;
            };
            track.addEventListener('transitionend', onEnd);
        }
    }

    function next() {
        var nextIdx = current + 1;
        if (nextIdx >= total) {
            goTo(total);
            return;
        }
        goTo(nextIdx);
    }

    function prev() {
        var prevIdx = current - 1;
        if (prevIdx < 0) prevIdx = total - 1;
        goTo(prevIdx);
    }

    function autoPlay() {
        clearInterval(timer);
        timer = setInterval(next, 3500);
    }

    function stopAutoPlay() {
        clearInterval(timer);
    }

    var carouselContainer = document.querySelector('.triple-carousel');
    if (!carouselContainer) return;

    carouselContainer.addEventListener('mouseenter', function () {
        stopAutoPlay();
    });

    carouselContainer.addEventListener('mouseleave', function () {
        autoPlay();
    });

    for (var i = 0; i < bubbles.length; i++) {
        (function (idx) {
            bubbles[idx].addEventListener('click', function () {
                clearInterval(timer);
                goTo(idx);
                setTimeout(autoPlay, 4000);
            });
        })(i);
    }

    var startX = 0;
    carouselContainer.addEventListener(
        'touchstart',
        function (e) {
            startX = e.changedTouches[0].screenX;
        },
        { passive: true }
    );

    carouselContainer.addEventListener(
        'touchend',
        function (e) {
            var diff = startX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 30) {
                clearInterval(timer);
                diff > 0 ? next() : prev();
                setTimeout(autoPlay, 4000);
            }
        },
        { passive: true }
    );

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            var step = getStep();
            track.style.transition = 'none';
            track.style.transform = 'translateX(-' + current * step + 'px)';
            void track.offsetHeight;
            track.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        }, 150);
    });

    var step = getStep();
    track.style.transition = 'none';
    track.style.transform = 'translateX(0px)';
    void track.offsetHeight;
    track.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94)';

    if (bubbles.length > 0) {
        bubbles[0].classList.add('active');
    }
    current = 0;
    autoPlay();

    window.__carousel = { goTo: goTo, next: next, prev: prev };
})();
(function () {
    var SELECTORS = [
        '.movie-poster',
        '.movie-poster-card .poster-img',
        '.module-item-pic',
        '.coverflow-card .poster-area',
        '.zt-poster',
        '.watch-history-poster',
        '.calendar-movie-item .poster',
        '.hl-ulog-pic'
    ];
    function inject(root) {
        for (var i = 0; i < SELECTORS.length; i++) {
            var nodes = root.querySelectorAll
                ? root.querySelectorAll(SELECTORS[i])
                : document.querySelectorAll(SELECTORS[i]);
            for (var j = 0; j < nodes.length; j++) {
                var box = nodes[j];
                if (box.querySelector('.hover-play-btn')) continue;
                var btn = document.createElement('span');
                btn.className = 'hover-play-btn';
                box.appendChild(btn);
            }
        }
    }
    function boot() {
        if (!document.body) {
            setTimeout(boot, 30);
            return;
        }
        inject(document);
        if (window.MutationObserver) {
            new MutationObserver(function () {
                inject(document);
            }).observe(document.body, { childList: true, subtree: true });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

(function () {
    var todayDay = (function () {
        var d = new Date().getDay();
        return d === 0 ? 7 : d;
    })();

    function generateCalendar() {
        var today = new Date();
        var currentYear = today.getFullYear();
        var currentMonth = today.getMonth();
        var currentDate = today.getDate();
        var currentDayOfWeek = today.getDay();

        var mondayOffset = currentDayOfWeek === 0 ? 6 : currentDayOfWeek - 1;
        var mondayDate = new Date(currentYear, currentMonth, currentDate - mondayOffset);

        var weekDays = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
        var calendarGrid = document.getElementById('calendarGrid');
        if (!calendarGrid) return;
        calendarGrid.innerHTML = '';

        var dayCounts = {};
        document.querySelectorAll('#calendarGroupList .week-group').forEach(function (group) {
            var day = group.dataset.day;
            var count = group.querySelectorAll('.calendar-movie-item').length;
            dayCounts[day] = count;
        });

        for (var i = 0; i < 7; i++) {
            var day = new Date(mondayDate);
            day.setDate(mondayDate.getDate() + i);
            var dayNum = day.getDate();
            var dayOfWeek = i + 1;
            var isToday =
                dayNum === currentDate && day.getMonth() === currentMonth && day.getFullYear() === currentYear;

            var count = dayCounts[dayOfWeek] || 0;

            var dayEl = document.createElement('div');
            dayEl.className = 'calendar-day' + (isToday ? ' active' : '');
            dayEl.dataset.day = dayOfWeek;
            dayEl.innerHTML =
                '<span class="weekday">' +
                weekDays[i] +
                '</span>' +
                '<span class="day-number">' +
                dayNum +
                '日</span>' +
                '<span class="update-count">' +
                count +
                '部</span>';
            calendarGrid.appendChild(dayEl);
        }
    }

    generateCalendar();

    var tplPath = (window.maccms && maccms.tpl) ? maccms.tpl.replace(/\/+$/, '') : '';
    fetch(tplPath + '/static/php/week.php?action=count')
        .then(r => r.json())
        .then(counts => {
            Object.keys(counts).forEach(dayNum => {
                var dayEl = document.querySelector('.calendar-day[data-day="' + dayNum + '"] .update-count');
                if (dayEl) dayEl.textContent = counts[dayNum] + '部';
            });
        });

    window.filterAll = function () {
        var activeDay = document.querySelector('#calendarGrid .calendar-day.active');
        var dayNum = activeDay ? parseInt(activeDay.dataset.day) : todayDay;
        var activeCategory = document.querySelector('#calendarTabs .active');
        var category = activeCategory ? activeCategory.dataset.category : 'all';

        var groups = document.querySelectorAll('#calendarGroupList .week-group');
        var items = document.querySelectorAll('#calendarGroupList .calendar-movie-item');

        groups.forEach(function (group) {
            var groupDay = parseInt(group.dataset.day);
            group.style.display = groupDay === dayNum ? 'block' : 'none';
        });

        var visibleItems = [];
        items.forEach(function (item) {
            var parentGroup = item.closest('.week-group');
            if (parentGroup && parentGroup.style.display === 'block') {
                var itemCat = item.dataset.category;
                if (category === 'all' || itemCat == category) {
                    visibleItems.push(item);
                }
            }
        });

        var isFullPage = window.location.pathname.indexOf('/label/week') !== -1;
        var max = 18;

        items.forEach(function (item) {
            item.style.display = 'none';
        });

        for (var i = 0; i < visibleItems.length && (isFullPage || i < max); i++) {
            visibleItems[i].style.display = 'flex';
        }


    };

    document.querySelectorAll('#calendarGrid .calendar-day').forEach(function (day) {
        day.addEventListener('click', function () {
            document.querySelectorAll('#calendarGrid .calendar-day').forEach(function (d) {
                d.classList.remove('active');
            });
            this.classList.add('active');
            var dayNum = this.dataset.day;
            var group = document.querySelector('.week-group[data-day="' + dayNum + '"]');
            if (group && group.dataset.loaded !== 'true') {
                group.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #999;">加载中...</div>';
                var tplPath = (window.maccms && maccms.tpl) ? maccms.tpl.replace(/\/+$/, '') : '';
                fetch(tplPath + '/static/php/week.php?day=' + dayNum)
                    .then(r => r.text())
                    .then(html => {
                        group.innerHTML = html;
                        group.dataset.loaded = 'true';
                        var dayEl = document.querySelector('.calendar-day[data-day="' + dayNum + '"] .update-count');
                        window.filterAll();
                    });
            }
            window.filterAll();
        });
    });

    document.querySelectorAll('#calendarTabs button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#calendarTabs button').forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            window.filterAll();
        });
    });

    var todayEl = document.querySelector('#calendarGrid .calendar-day[data-day="' + todayDay + '"]');
    if (todayEl) {
        document.querySelectorAll('#calendarGrid .calendar-day').forEach(function (d) {
            d.classList.remove('active');
        });
        todayEl.classList.add('active');
    }

    var isFullPage = window.location.pathname.indexOf('/label/week') !== -1;
    if (isFullPage) {
        var todayGroup = document.querySelector('.week-group[data-day="' + todayDay + '"]');
        if (todayGroup && todayGroup.dataset.loaded !== 'true') {
            var tplPath = (window.maccms && maccms.tpl) ? maccms.tpl.replace(/\/+$/, '') : '';
            fetch(tplPath + '/static/php/week.php?day=' + todayDay)
                .then(r => r.text())
                .then(html => {
                    todayGroup.innerHTML = html;
                    todayGroup.dataset.loaded = 'true';
                    window.filterAll();
                });
        }
    }

    window.filterAll();

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(window.filterAll, 200);
    });
})();
(function () {
    var key = 'streamlab_search_history';
    var container = document.querySelector('[data-search-history-container]');
    if (!container) return;

    var currentInput = document.querySelector('#wd');
    var grid = document.getElementById('searchGrid');
    var empty = container.querySelector('[data-history-empty]');
    var clearBtn = container.querySelector('[data-clear-history]');

    function clean(v) {
        return (v || '').replace(/<[^>]*>/g, '').trim();
    }

    function loadHistory() {
        try {
            return JSON.parse(localStorage.getItem(key) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveHistory(items) {
        localStorage.setItem(key, JSON.stringify(items.slice(0, 10)));
    }

    function getHotWords() {
        var hotItems = grid.querySelectorAll('[data-hot="true"]');
        var words = [];
        hotItems.forEach(function (el) {
            var word = el.textContent.trim();
            if (word) words.push(word);
        });
        return words;
    }

    var current = currentInput ? clean(currentInput.value) : '';
    if (current) {
        var items = loadHistory().filter(function (item) {
            return item !== current;
        });
        items.unshift(current);
        saveHistory(items);
    }

    function render() {
        if (!grid) return;
        var history = loadHistory();
        var hotWords = getHotWords();

        grid.innerHTML = '';

        history.forEach(function (item) {
            var a = document.createElement('a');

            a.href =
                document.querySelector('[data-search-history-container]').getAttribute('data-search-url') +
                '?wd=' +
                encodeURIComponent(item);
            a.textContent = item;
            a.className = 'suggestion-item history-item';
            a.setAttribute('data-history', 'true');
            grid.appendChild(a);
        });

        var historySet = new Set(history);
        hotWords.forEach(function (word) {
            if (!historySet.has(word)) {
                var a = document.createElement('a');

                a.href =
                    document.querySelector('[data-search-history-container]').getAttribute('data-search-url') +
                    '?wd=' +
                    encodeURIComponent(word);
                a.textContent = word;
                a.className = 'suggestion-item hot-item';
                a.setAttribute('data-hot', 'true');
                grid.appendChild(a);
            }
        });

        if (clearBtn) {
            clearBtn.style.display = history.length > 0 ? 'inline-block' : 'none';
        }

        if (empty) {
            empty.style.display = history.length === 0 ? 'block' : 'none';
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            localStorage.removeItem(key);
            render();
        });
    }

    if (grid) {
        grid.addEventListener('click', function (e) {
            var target = e.target.closest('a');
            if (target && target.getAttribute('data-history') === 'true') {
                var word = target.textContent.trim();
                if (word) {
                    var items = loadHistory().filter(function (item) {
                        return item !== word;
                    });
                    items.unshift(word);
                    saveHistory(items);
                    render();
                }
            }
        });
    }

    render();
})();

(function () {
    function writeContinue() {
        var ulogEl = document.querySelector('.mac_ulog_set[data-mid="1"]');
        var historyEl = document.querySelector('.mac_history_set');
        if (!ulogEl || !historyEl) return;
        var vodId = ulogEl.getAttribute('data-id');
        var pic = ulogEl.getAttribute('data-pic') || historyEl.getAttribute('data-pic') || '';
        var sid = ulogEl.getAttribute('data-sid') || '1';
        var nid = ulogEl.getAttribute('data-nid') || '1';
        var part = ulogEl.getAttribute('data-part') || '';
        var vodName = historyEl.getAttribute('data-name');
        var link = historyEl.getAttribute('data-link');
        if (!vodId || !vodName || !link) return;
        var key = 'streamlab_continue_' + vodId;
        var data = {
            vodId: vodId,
            vodName: vodName,
            sid: sid,
            nid: nid,
            episode: part || '第' + nid + '集',
            url: window.location.href,
            pic: pic,
            time: Date.now()
        };
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {}
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', writeContinue);
    } else {
        writeContinue();
    }
    setTimeout(writeContinue, 800);
    setTimeout(writeContinue, 2000);
})();
$(function () {
    var _mc = document.querySelector('.mac_comment');
    MAC.Comment.Login = _mc ? _mc.getAttribute('data-login') : 0;
    MAC.Comment.Verify = _mc ? _mc.getAttribute('data-verify') : 0;
    MAC.Comment.Init();
    MAC.Comment.Show(1);

    setTimeout(function () {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                document.querySelectorAll('.side-card.tab-panel').forEach(function (p) {
                    p.classList.remove('active');
                });
                var panel = document.getElementById('panel-' + this.dataset.tab);
                if (panel) panel.classList.add('active');
            });
        });
    }, 100);
});

var _reportBtn = document.querySelector('[data-report-id]');
if (_reportBtn) {
    _reportBtn.addEventListener('click', function () {
        MAC.Gbook.Report(
            '我想看《' + this.getAttribute('data-report-name') + '》，请添加播放线路，网页是' + location.href,
            this.getAttribute('data-report-id')
        );
    });
}

function clearHistory() {
    if (!confirm('确定要清空所有观看记录吗？')) return;
    var count = 0;
    try {
        var keysToRemove = [];
        for (var i = 0; i < localStorage.length; i++) {
            var key = localStorage.key(i);
            if (key && key.indexOf('streamlab_continue_') === 0) {
                keysToRemove.push(key);
                count++;
            }
        }
        for (var j = 0; j < keysToRemove.length; j++) {
            localStorage.removeItem(keysToRemove[j]);
        }
    } catch (e) {}
    var box = document.getElementById('watchHistoryScroll');
    var section = document.getElementById('watchHistorySection');
    if (box) box.innerHTML = '';
    if (section) section.style.display = 'none';
    alert('已清空 ' + count + ' 条观看记录');
}

(function () {
    var box = document.getElementById('watchHistoryScroll');
    var section = document.getElementById('watchHistorySection');
    if (!box || !section) return;

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function stripName(name) {
        return String(name || '').replace(/^\[[^\]]+\]/, '');
    }

    function getLocalItems() {
        var items = [];
        try {
            for (var i = 0; i < localStorage.length; i++) {
                var key = localStorage.key(i);
                if (!key) continue;
                if (
                    key.indexOf('streamlab_continue_') !== 0 &&
                    key.indexOf('mac_history_') !== 0 &&
                    key.indexOf('history_') !== 0
                )
                    continue;
                var data = JSON.parse(localStorage.getItem(key) || '{}');
                var name = data.vodName || data.name || '';
                var link = data.url || data.link || '';
                var pic = data.pic || data.vodPic || '';
                var nid = data.nid || data.ulog_nid || 0;
                var episode = data.episode || data.part || '';
                var time = data.time || data.ulog_time || 0;
                if (name && link) {
                    items.push({ name: name, link: link, pic: pic, nid: nid, episode: episode, time: time });
                }
            }
        } catch (e) {}
        return items.sort(function (a, b) {
            return Number(b.time || 0) - Number(a.time || 0);
        });
    }

    function siteBase() {
        var host = section && section.getAttribute ? section : box || null;
        if (host && host.getAttribute) {
            var api = host.getAttribute('data-history-api');
            if (api && api.indexOf('index.php/user/ajax_ulog') !== -1) {
                return api.replace(/index\.php\/user\/ajax_ulog\/.*$/, '');
            }
        }
        if (window.maccms && maccms.path) return String(maccms.path);
        if (window.MAC && MAC.config && MAC.config.path) return String(MAC.config.path);
        return '';
    }

    function getServerItems(callback) {
        var apiUrl = section && section.getAttribute ? section.getAttribute('data-history-api') : '';
        var base = siteBase().replace(/\/+$/, '');
        var url = apiUrl || base + '/index.php/user/ajax_ulog/?ac=list&mid=1&type=4&page=1&limit=20';
        if (!window.fetch) {
            callback(null);
            return;
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                var list = [];
                if (res) {
                    if (Array.isArray(res.list)) {
                        list = res.list;
                    } else if (res.data && Array.isArray(res.data.list)) {
                        list = res.data.list;
                    } else if (Array.isArray(res.data)) {
                        list = res.data;
                    }
                }
                var items = list
                    .map(function (item) {
                        var vod = item.vod || {};
                        var data = item.data || {};
                        var name = vod.vod_name || data.vod_name || data.name || item.vod_name || item.name || '';
                        var pic = vod.vod_pic || data.vod_pic || data.pic || item.vod_pic || item.pic || '';
                        var link = vod.vod_url || data.url || data.link || item.url || item.link || '';
                        if (!link && item.ulog_rid) {
                            link =
                                '/index.php/vod/play/id/' +
                                item.ulog_rid +
                                '/sid/' +
                                (item.ulog_sid || 1) +
                                '/nid/' +
                                (item.ulog_nid || 0) +
                                '.html';
                        }
                        return {
                            rid: item.ulog_rid || item.vod_id || 0,
                            name: name,
                            link: link,
                            pic: pic,
                            nid: item.ulog_nid || item.nid || 0,
                            episode:
                                item.ulog_nid && String(item.ulog_nid) !== '0' ? '第 ' + item.ulog_nid + ' 集' : '',
                            time: item.ulog_time || item.time || 0
                        };
                    })
                    .filter(function (item) {
                        return item && item.name && item.link;
                    });

                var seen = {};
                var uniq = [];
                for (var i = 0; i < items.length; i++) {
                    var it = items[i];
                    var k = it.rid ? 'r' + it.rid : 'n' + it.name;
                    if (!seen[k]) {
                        seen[k] = it;
                        uniq.push(it);
                    } else if (Number(it.time) > Number(seen[k].time)) {
                        var idx = uniq.indexOf(seen[k]);
                        if (idx !== -1) uniq.splice(idx, 1, it);
                        seen[k] = it;
                    }
                }
                callback(uniq);
            })
            .catch(function () {
                callback(null);
            });
    }

    function fixPic(pic) {
        if (!pic) return '';
        pic = String(pic);
        var sb = siteBase().replace(/\/+$/, '');
        if (pic.indexOf('__ROOT__') !== -1) {
            return pic.split('__ROOT__').join(sb);
        }
        if (pic.charAt(0) === '/' && pic.charAt(1) !== '/') {
            return sb + pic;
        }
        return pic;
    }

    function render(items) {
        items = (items || [])
            .filter(function (item) {
                return item && item.link && item.name;
            })
            .slice(0, 20);
        if (!items.length) {
            section.style.display = 'none';
            box.innerHTML = '';
            return;
        }
        section.style.display = 'block';
        var html = '';
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var name = stripName(item.name);
            var ep = item.episode || '继续观看';

            var picUrl = fixPic(item.pic);
            var imgHtml = '';
            if (picUrl) {
                imgHtml =
                    '<img src="' +
                    esc(picUrl) +
                    '" alt="' +
                    esc(name) +
                    "\" onerror=\"this.style.display='none';this.nextElementSibling.style.display='flex';\" data-img-error>" +
                    '<span class="watch-history-placeholder" style="display:none;">' +
                    esc(name.slice(0, 1) || 'V') +
                    '</span>';
            } else {
                imgHtml = '<span class="watch-history-placeholder">' + esc(name.slice(0, 1) || 'V') + '</span>';
            }
            html +=
                '<a class="watch-history-item" href="' +
                esc(item.link) +
                '">' +
                '<div class="watch-history-poster">' +
                imgHtml +
                '</div>' +
                '<div class="watch-history-info">' +
                '<h4>' +
                esc(name) +
                '</h4>' +
                '<p>▶ ' +
                esc(ep) +
                '</p>' +
                '</div>' +
                '</a>';
        }
        box.innerHTML = html;
    }

    getServerItems(function (serverItems) {
        if (serverItems && serverItems.length > 0) {
            render(serverItems);
        } else {
            var localItems = getLocalItems();
            render(localItems);
        }
    });
})();

document.querySelectorAll('[data-clear-history]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        clearHistory();
    });
});

document.addEventListener(
    'error',
    function (e) {
        var img = e.target;
        if (img && img.tagName === 'IMG' && img.hasAttribute('data-img-error')) {
            img.style.display = 'none';
            var next = img.nextElementSibling;
            if (next) next.style.display = 'flex';
        }
    },
    true
);

$(function () {
    $('.mac_verify').focus();

    $("input[name='verify']").bind('keypress', function (event) {
        if (event.keyCode == '13') {
            if ($("input[name='verify']").val() != '') {
                $('.verify_submit').click();
            }
        }
    });

    $('.verify_submit').click(function () {
        var v = $('input[name="verify"]').val();
        var vtype = document.querySelector('.verify_submit').getAttribute('data-type') || '';
        MAC.Ajax(
            maccms.path + '/index.php/ajax/verify_check?type=' + vtype + '&verify=' + v,
            'post',
            'json',
            '',
            function (r) {
                if (r.code == 1) {
                    location.reload();
                } else {
                    layer.msg(r.msg);
                    MAC.Verify.Refresh();
                }
            },
            function () {
                layer.msg('请求失败，请重试');
                MAC.Verify.Refresh();
            }
        );
    });
});

(function () {
    function bootCompat() {
        var imgs = document.querySelectorAll('img[data-src], img[data-original]');
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            var src = img.getAttribute('data-src') || img.getAttribute('data-original');
            if (src) img.src = src;
            img.removeAttribute('data-src');
            img.removeAttribute('data-original');
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCompat);
    } else {
        bootCompat();
    }
})();

(function () {
    function bindLazy(img) {
        if (img.__hlLazyPh) return;
        img.__hlLazyPh = true;
        var parent = img.parentNode;
        if (!parent || parent.nodeType !== 1) return;
        function fallback() {
            img.removeAttribute('alt');
            img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }
        function success() {
            img.style.visibility = '';
            img.style.opacity = '';
            parent.classList.remove('img-err');
            parent.classList.add('img-ok');
        }
        function update() {
            var ok = img.complete && img.naturalWidth > 0;
            if (ok) {
                success();
            } else if (img.complete && img.getAttribute('src')) {
                fallback();
            }
        }
        if (img.complete) update();
        img.addEventListener('load', function () {
            update();
            setTimeout(update, 100);
        });
        img.addEventListener('error', function () {
            fallback();
        });
        setTimeout(update, 600);
        setTimeout(update, 2000);
    }
    function scan() {
        var imgs = document.querySelectorAll('img[loading="lazy"], img[data-original], img[data-src]');
        for (var i = 0; i < imgs.length; i++) bindLazy(imgs[i]);
    }
    scan();
    if (window.MutationObserver) {
        var mo = new MutationObserver(scan);
        mo.observe(document.body, { childList: true, subtree: true });
    }
})();

(function () {
    var popup = document.getElementById('noticePopup');
    if (!popup) return;
    try {
        var d = new Date();
        var day = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
        if (localStorage.getItem('streamlab_notice_date') === day) return;
        localStorage.setItem('streamlab_notice_date', day);
    } catch (e) {}
    var mask = document.getElementById('noticeMask');
    if (mask) mask.style.display = 'block';
    popup.classList.add('show');
    function hide() {
        popup.classList.remove('show');
        if (mask) mask.style.display = 'none';
    }
    var closeBtns = popup.querySelectorAll('[data-notice-close]');
    for (var i = 0; i < closeBtns.length; i++) {
        closeBtns[i].addEventListener('click', function (e) {
            e.preventDefault();
            hide();
        });
    }
    if (mask) mask.addEventListener('click', hide);
})();
} else {
    function showLicenseBlock() {
        document.documentElement.style.overflow = 'hidden';
        if (document.body) {
            document.body.style.margin = '0';
            document.body.style.padding = '0';
            document.body.style.background = '#000';
            document.body.innerHTML = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:#000;z-index:99999;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;text-align:center;font-family:system-ui,-apple-system,sans-serif;line-height:2.2;"><div style="padding:40px;"><div style="font-size:28px;font-weight:bold;margin-bottom:20px;color:#ff4d4f;">\u672a\u6388\u6743</div><div style="font-size:16px;color:#ccc;line-height:2;">\u6d77\u87ba\u4e3b\u9898 \u8054\u7cfb\u4f5c\u8005QQ\uff1a<a href="https://www.hailuo.cc/" style="color:#4a9eff;text-decoration:none;font-weight:bold;">52225119</a> \u83b7\u53d6</div></div></div>';
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showLicenseBlock);
    } else {
        showLicenseBlock();
    }
}
