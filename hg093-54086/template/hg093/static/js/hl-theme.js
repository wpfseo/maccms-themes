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
    
var toastTimer = null;

function showToast(msg) {
    var el = document.getElementById('toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast';
        el.className = 'toast-message';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
        el.classList.remove('show');
    }, 2200);
}
window.showToast = showToast;
window.toastAlert = showToast;

(function () {
    var themeBtn = document.getElementById('themeToggle');
    if (!themeBtn) return;
    var savedTheme = localStorage.getItem('hl_theme');
    var defaultTheme = (window.conch_theme && window.conch_theme.theme_style) || 'dark';
    if (defaultTheme !== 'dark' && defaultTheme !== 'light') defaultTheme = 'dark';
    var theme = (savedTheme === 'dark' || savedTheme === 'light') ? savedTheme : defaultTheme;

    document.documentElement.setAttribute('data-theme', theme);

    themeBtn.innerHTML =
        theme === 'dark' ? '<i class="icon-moon-line iconfont"></i>' : '<i class="icon-sun-line iconfont"></i>';
    if (conch_theme.theme == 1) {
    themeBtn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('hl_theme', next);
        themeBtn.innerHTML =
            next === 'dark' ? '<i class="icon-moon-line iconfont"></i>' : '<i class="icon-sun-line iconfont"></i>';
    });}
})();

(function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var menuToggle = document.getElementById('menuToggle');
    var sidebarClose = document.getElementById('sidebarClose');
    if (!sidebar || !overlay || !menuToggle || !sidebarClose) return;

    menuToggle.addEventListener('click', function () {
        sidebar.classList.add('open');
        overlay.classList.add('open');
    });
    sidebarClose.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
    });
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
    });
})();

function getFavs() {
    return JSON.parse(localStorage.getItem('mv_favs') || '[]');
}

function toggleFav(id) {
    var favs = getFavs();
    var index = favs.indexOf(id);
    if (index > -1) {
        favs.splice(index, 1);
    } else {
        favs.push(id);
    }
    localStorage.setItem('mv_favs', JSON.stringify(favs));
    updateAllFavoriteIcons();
}

function isFav(id) {
    return getFavs().indexOf(id) > -1;
}

function updateAllFavoriteIcons() {
    document.querySelectorAll('.favorite-btn').forEach(function (btn) {
        var fav = isFav(btn.dataset.id);
        btn.classList.toggle('active', fav);
        btn.innerHTML =
            (fav ? '<i class="icon-heart-2-fill iconfont"></i>' : '<i class="icon-heart-line iconfont"></i>') + ' 收藏';
    });
}

(function () {
    var coverflowCards = document.querySelectorAll('#coverflowStage .coverflow-card');
    if (!coverflowCards.length) return;
    var coverflowDots = document.querySelectorAll('#coverflowDots .coverflow-dot');
    var totalCards = coverflowCards.length;
    var coverIndex = 2;
    var autoPlayTimer = null;

    function updateCoverflowLayers() {
        coverflowCards.forEach(function (card) {
            var index = parseInt(card.dataset.index);
            var layer = index - coverIndex;
            if (layer > totalCards / 2) layer -= totalCards;
            if (layer < -totalCards / 2) layer += totalCards;
            layer = Math.max(-2, Math.min(2, layer));
            card.dataset.layer = layer;
            card.style.display = layer >= -2 && layer <= 2 ? '' : 'none';
        });
        coverflowDots.forEach(function (dot, i) {
            dot.classList.toggle('active', i === coverIndex);
        });
    }

    function goToCover(idx) {
        coverIndex = ((idx % totalCards) + totalCards) % totalCards;
        updateCoverflowLayers();
    }

    var prevBtn = document.getElementById('coverflowPrev');
    var nextBtn = document.getElementById('coverflowNext');
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            goToCover(coverIndex - 1);
            resetAutoPlay();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            goToCover(coverIndex + 1);
            resetAutoPlay();
        });
    }
    coverflowDots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            goToCover(parseInt(this.dataset.index));
            resetAutoPlay();
        });
    });

    coverflowCards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('button') || e.target.closest('.favorite-icon') || e.target.closest('a')) {
                return;
            }
            goToCover(parseInt(this.dataset.index));
            resetAutoPlay();
        });
    });

    var coverflowWrapper = document.getElementById('coverflowWrapper');
    if (coverflowWrapper) {
        var touchStartX = 0;
        coverflowWrapper.addEventListener(
            'touchstart',
            function (e) {
                touchStartX = e.changedTouches[0].screenX;
            },
            { passive: true }
        );
        coverflowWrapper.addEventListener('touchend', function (e) {
            var diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                goToCover(coverIndex + (diff > 0 ? 1 : -1));
                resetAutoPlay();
            }
        });
    }

    function startAutoPlay() {
        stopAutoPlay();
        autoPlayTimer = setInterval(function () {
            goToCover(coverIndex + 1);
        }, 3000);
    }

    function stopAutoPlay() {
        if (autoPlayTimer) {
            clearInterval(autoPlayTimer);
            autoPlayTimer = null;
        }
    }

    function resetAutoPlay() {
        stopAutoPlay();
        startAutoPlay();
    }

    if (coverflowWrapper) {
        coverflowWrapper.addEventListener('mouseenter', stopAutoPlay);
        coverflowWrapper.addEventListener('mouseleave', startAutoPlay);
    }

    document.querySelectorAll('#coverflowStage .favorite-icon, #coverflowStage .btn-save').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleFav(this.dataset.id);
        });
    });

    document.querySelectorAll('.movie-poster-card .favorite-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleFav(this.dataset.id);
        });
    });

    updateCoverflowLayers();
    updateAllFavoriteIcons();
    startAutoPlay();
})();

(function () {
    document.querySelectorAll('.rail-header-arrow').forEach(function (arrow) {
        arrow.addEventListener('click', function () {
            var railId = this.dataset.rail;
            var scrollContainer = document.getElementById(railId);
            if (scrollContainer && typeof conch_theme.hlname !== 'undefined' && conch_theme.hlname !== '') {
                var direction = this.classList.contains('prev') ? -1 : 1;
                scrollContainer.scrollBy({ left: direction * 300, behavior: 'smooth' });
            }
        });
    });
})();

function sortEpisodeList(wrapDom, itemSelector, sortAttr, reversed, scrollSelector, groupActiveTab) {
    if (!wrapDom) return;
    var box = wrapDom.querySelector(scrollSelector);
    if (!box) return;

    var allItems = Array.from(box.querySelectorAll(itemSelector));
    allItems.forEach(function (item) {
        item.style.display = '';
    });

    allItems.sort(function (a, b) {
        return parseInt(a.dataset[sortAttr]) - parseInt(b.dataset[sortAttr]);
    });
    if (reversed) {
        allItems.reverse();
    }

    box.innerHTML = '';
    allItems.forEach(function (el) {
        box.appendChild(el);
    });

    if (groupActiveTab) {
        var g = groupActiveTab.dataset.group;
        wrapDom.querySelectorAll(itemSelector).forEach(function (ep) {
            ep.style.display = ep.dataset.group === g ? '' : 'none';
        });
    }
}

function initEpisodeModule(opts) {
    var isDetail = opts.isDetail;
    var rootSel = opts.rootSel;
    var lineTabSel = opts.lineTabSel;
    var lineContentSel = opts.lineContentSel;
    var groupTabSel = opts.groupTabSel;
    var itemSel = opts.itemSel;
    var sortAttr = opts.sortAttr;
    var containerSel = opts.containerSel;
    var scrollBoxSel = opts.scrollBoxSel;
    var sortToggleId = opts.sortToggleId;
    var nameTargetId = opts.nameTargetId;
    var lineLabelId = opts.lineLabelId;

    var currentGroup = '0';
    var isReversed = false;

    document.querySelectorAll(lineTabSel).forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            if (!isDetail) e.preventDefault();

            var line = this.dataset.line;
            var target = this.dataset.target;
            var lineName =
                this.dataset.name ||
                this.textContent
                    .trim()
                    .replace(/\(\d+\)/, '')
                    .trim();
            document.querySelectorAll(lineTabSel).forEach(function (t) {
                t.classList.remove('active');
            });
            this.classList.add('active');

            document.querySelectorAll(lineContentSel).forEach(function (c) {
                c.style.display = 'none';
            });

            var targetEl;
            if (isDetail) {
                targetEl = document.querySelector(target);
            } else {
                targetEl = document.getElementById(containerSel.replace('{line}', line));
            }
            if (targetEl) targetEl.style.display = 'block';

            if (nameTargetId) {
                var nameEl = document.getElementById(nameTargetId);
                if (nameEl) nameEl.textContent = lineName;
            }
            if (!isDetail && lineLabelId) {
                var labelDom = document.getElementById(lineLabelId);
                if (labelDom) labelDom.textContent = lineName + ' · 自动';
            }

            var firstGroup = targetEl ? targetEl.querySelector(groupTabSel) : null;
            if (firstGroup) {
                var g = firstGroup.dataset.group;
                switchGroup(targetEl, g);
            }
        });
    });

    document.querySelectorAll(groupTabSel).forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            if (!isDetail) e.preventDefault();
            var g = this.dataset.group;
            var container = this.closest(rootSel);
            switchGroup(container, g);
        });
    });

    function switchGroup(container, group) {
        if (!container) return;
        container.querySelectorAll(groupTabSel).forEach(function (t) {
            t.classList.remove('active');
        });
        var activeTab = container.querySelector(groupTabSel + '[data-group="' + group + '"]');
        if (activeTab) activeTab.classList.add('active');

        container.querySelectorAll(itemSel).forEach(function (chip) {
            chip.style.display = chip.dataset.group === group ? (isDetail ? 'inline-block' : '') : 'none';
        });
        currentGroup = group;
    }

    var sortBtn = document.getElementById(sortToggleId);
    if (sortBtn) {
        sortBtn.addEventListener('click', function () {
            isReversed = !isReversed;
            this.textContent = isReversed ? '正序' : '倒序';

            var activeTab = document.querySelector(lineTabSel + '.active');
            if (!activeTab) return;
            var line = activeTab.dataset.line;
            var container;
            if (isDetail) {
                container = document.querySelector(containerSel.replace('{line}', line));
            } else {
                container = document.querySelector(lineContentSel + '[style*="block"]');
            }
            if (!container) return;

            var activeGTab = container.querySelector(groupTabSel + '.active');
            sortEpisodeList(container, itemSel, sortAttr, isReversed, scrollBoxSel, activeGTab);
            showToast(isReversed ? '已切换为倒序' : '已切换为正序');
        });
    }

    document.addEventListener('click', function (e) {
        var item = e.target.closest(itemSel);
        if (!item) return;
        var line = item.dataset.line;
        var container;
        if (isDetail) {
            container = document.querySelector(containerSel.replace('{line}', line));
        } else {
            container = item.closest(rootSel);
        }
        if (!container) return;
        container.querySelectorAll(itemSel).forEach(function (c) {
            c.classList.remove('active');
        });
        item.classList.add('active');
    });

    function initFirstLine() {
        var defaultTab = document.querySelector(lineTabSel + '.active');
        if (!defaultTab) {
            defaultTab = document.querySelector(lineTabSel);
            if (defaultTab) defaultTab.classList.add('active');
        }
        if (defaultTab) defaultTab.click();
    }

    return { initFirstLine: initFirstLine };
}

(function () {
    var root = document.querySelector('[data-continue-write]');
    if (!root) return;

    var vodId = root.getAttribute('data-vod-id');
    var vodName = root.getAttribute('data-vod-name');
    var sid = root.getAttribute('data-sid');
    var nid = root.getAttribute('data-nid');
    var episode = root.getAttribute('data-episode') || '第' + nid + '集';
    var url = location.href;

    var pic = '';
    var historyEl = document.querySelector('.mac_history_set');
    if (historyEl) {
        pic = historyEl.getAttribute('data-pic') || '';
    }

    var key = 'streamlab_continue_' + vodId;
    var data = {
        vodId: vodId,
        vodName: vodName,
        sid: sid,
        nid: nid,
        episode: episode,
        url: url,
        pic: pic,
        time: Date.now()
    };

    try {
        localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {}
})();

(function () {
    var root = document.querySelector('[data-continue-detail]');
    if (!root) return;

    var vodId = root.getAttribute('data-vod-id');
    var key = 'streamlab_continue_' + vodId;
    var data = null;

    try {
        data = JSON.parse(localStorage.getItem(key) || 'null');
    } catch (e) {
        data = null;
    }

    if (!data || !data.url || data.url.indexOf('__SELF__') !== -1) {
        try {
            localStorage.removeItem(key);
        } catch (e) {}
        return;
    }

    function isMobile() {
        return (
            window.innerWidth <= 767 ||
            /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        );
    }

    var epNum = data.nid || '';
    var mobile = isMobile();

    var continueBtn = document.getElementById('continueBtn');
    if (continueBtn) {
        continueBtn.href = data.url;
        continueBtn.setAttribute('data-url', data.url);
        continueBtn.style.display = 'inline-flex';
        var epSpan = document.getElementById('continueEpisode');
        if (epSpan && epNum) {
            epSpan.textContent = '第' + epNum + '集';
        }
        if (mobile && continueBtn) {
            var icon = continueBtn.querySelector('i');
            if (icon) {
                continueBtn.innerHTML = '';
                continueBtn.appendChild(icon);
                var textNode = document.createTextNode(' 继续 第' + epNum + '集');
                continueBtn.appendChild(textNode);
            } else {
                continueBtn.textContent = '继续 第' + epNum + '集';
            }
        }
    }

    var statCard = document.getElementById('continueStatCard');
    if (statCard) {
        statCard.href = data.url;
        statCard.setAttribute('data-url', data.url);
        var statText = document.getElementById('continueStatText');
        if (statText && epNum) {
            statText.textContent = mobile ? '继续 第' + epNum + '集' : '继续播放 第' + epNum + '集';
        }
    }

    document.querySelectorAll('.ep-chip').forEach(function (el) {
        var sid = el.getAttribute('data-sid');
        var nid = el.getAttribute('data-nid');
        if (sid == data.sid && nid == data.nid) {
            el.classList.add('last-watched');
            var oldTip = el.querySelector('small');
            if (oldTip) oldTip.remove();
            var tip = document.createElement('small');
            tip.textContent = '上次观看';
            el.appendChild(tip);
        }
    });
})();

document.addEventListener('focusin', function (e) {
    var tag = e.target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.contentEditable === 'true') {
        var nav = document.querySelector('.bottom-nav');
        if (nav) nav.style.display = 'none';
    }
});

document.addEventListener('focusout', function (e) {
    var tag = e.target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.contentEditable === 'true') {
        var nav = document.querySelector('.bottom-nav');
        if (nav) nav.style.display = 'flex';
    }
});

(function () {
    var picker = document.querySelector('.emoji-picker');
    if (!picker) return;
    var box = picker.querySelector('.emoji-box');
    var btn = picker.querySelector('.emoji-btn');
    if (!box || !btn) return;

    document.body.appendChild(box);
    box.style.position = 'fixed';
    box.style.zIndex = '1000';
    box.style.display = 'none';

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var rect = this.getBoundingClientRect();

        if (box.style.display === 'grid') {
            box.style.display = 'none';
        } else {
            box.style.display = 'grid';
            box.style.left = rect.left + 'px';
            if (rect.bottom + 200 > window.innerHeight) {
                box.style.top = rect.top - 195 + 'px';
            } else {
                box.style.top = rect.bottom + 5 + 'px';
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && !btn.contains(e.target)) {
            box.style.display = 'none';
        }
    });
})();

(function () {
    var form = document.getElementById('feedbackForm');
    if (!form) return;

    var content = document.getElementById('fbContent');
    var submitBtn = form.querySelector('.gbook_submit');
    var verifyInput = form.querySelector('.verify');
    var msgDiv = document.getElementById('fbMessage');
    var isSubmitting = false;

    var vodName = document.getElementById('fbVodName') ? document.getElementById('fbVodName').value : '';
    var episode = document.getElementById('fbEpisode') ? document.getElementById('fbEpisode').value : '';
    var line = document.getElementById('fbLine') ? document.getElementById('fbLine').value : '';

    var vodInfo = '';
    if (vodName) {
        vodInfo = '[ ' + vodName;
        if (episode) vodInfo += ' ' + episode;
        if (line) vodInfo += ' ' + line;
        vodInfo += ' ]\n\n';
    }

    if (content) {
        content.value = vodInfo + '功能建议：';
    }

    function showMsg(msg, type) {
        if (!msgDiv) return;
        msgDiv.style.display = 'block';
        msgDiv.textContent = msg;
        msgDiv.style.background = type === 'success' ? '#f6ffed' : '#fff2f0';
        msgDiv.style.border = '1px solid ' + (type === 'success' ? '#b7eb8f' : '#ffccc7');
        msgDiv.style.color = type === 'success' ? '#52c41a' : '#ff4d4f';
        clearTimeout(window.fbMsgTimer);
        window.fbMsgTimer = setTimeout(function () {
            msgDiv.style.display = 'none';
        }, 5000);
    }

    document.querySelectorAll('.fb-type').forEach(function (type) {
        type.addEventListener('click', function (e) {
            e.preventDefault();
            if (msgDiv) msgDiv.style.display = 'none';
            document.querySelectorAll('.fb-type').forEach(function (t) {
                t.classList.remove('active');
            });
            this.classList.add('active');

            var text = this.dataset.text;
            if (!content) return;
            var val = content.value;
            var lines = val.split('\n');

            var typeLineIndex = -1;
            if (lines.length >= 3) {
                document.querySelectorAll('.fb-type').forEach(function (t) {
                    if (lines[2] && lines[2].indexOf(t.dataset.text + '：') === 0) {
                        typeLineIndex = 2;
                    }
                });
            }

            if (typeLineIndex !== -1) {
                lines[2] = text + '：';
                content.value = lines.join('\n');
            } else {
                content.value = val + '\n' + text + '：';
            }

            content.focus();
            content.setSelectionRange(content.value.length, content.value.length);
        });
    });

    if (content) {
        content.addEventListener('input', function () {
            var remaining = document.querySelector('.gbook_remaining');
            if (remaining) {
                var len = this.value.length;
                var left = Math.max(0, 200 - len);
                remaining.textContent = left;
                if (left === 0) {
                    showMsg('内容已超过200字限制', 'error');
                } else if (msgDiv) {
                    msgDiv.style.display = 'none';
                }
            }
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isSubmitting || !content) return;

            var currentContent = content.value.trim();
            if (!currentContent || currentContent === (vodInfo + '功能建议：').trim()) {
                showMsg('请描述您的问题', 'error');
                return;
            }
            if (currentContent.length > 200) {
                showMsg('内容超过200字限制，请精简', 'error');
                return;
            }

            var verify = verifyInput ? verifyInput.value.trim() : '';
            if (!verify) {
                showMsg('请输入验证码', 'error');
                return;
            }

            isSubmitting = true;
            submitBtn.value = '提交中...';
            submitBtn.style.opacity = '0.6';

            var formData = new FormData(form);
            formData.append('gbook_content', currentContent);

            if (typeof $ !== 'undefined' && $.ajax) {
                var gbookUrl = (window.maccms && maccms.path ? maccms.path : (window.MAC && MAC.config && MAC.config.path ? MAC.config.path : '')) + '/index.php/gbook/saveData';
                $.ajax({
                    url: gbookUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        var code = parseInt(res && res.code, 10);
                        if (code === 1 || code === 1001) {
                            showMsg('✅ 反馈提交成功，感谢您的支持！', 'success');
                            setTimeout(function () {
                                document.querySelectorAll('.fb-type').forEach(function (t) {
                                    t.classList.remove('active');
                                });
                                var firstType = document.querySelector('.fb-type:first-child');
                                if (firstType) firstType.classList.add('active');
                                if (content) content.value = vodInfo + '功能建议：';
                                if (verifyInput) verifyInput.value = '';
                                var verifyImg = document.querySelector('.comm-code');
                                if (verifyImg) verifyImg.src = verifyImg.src.split('?')[0] + '?v=' + Math.random();
                            }, 1000);
                        } else {
                            showMsg('❌ ' + (res.msg || '提交失败，请重试'), 'error');
                            var verifyImg = document.querySelector('.comm-code');
                            if (verifyImg) verifyImg.src = verifyImg.src.split('?')[0] + '?v=' + Math.random();
                            if (verifyInput) verifyInput.value = '';
                        }
                    },
                    error: function () {
                        showMsg('❌ 网络异常，请稍后重试', 'error');
                        var verifyImg = document.querySelector('.comm-code');
                        if (verifyImg) verifyImg.src = verifyImg.src.split('?')[0] + '?v=' + Math.random();
                        if (verifyInput) verifyInput.value = '';
                    },
                    complete: function () {
                        isSubmitting = false;
                        submitBtn.value = '提交';
                        submitBtn.style.opacity = '1';
                    }
                });
            } else {
                showMsg('jQuery未加载，请刷新重试', 'error');
                isSubmitting = false;
                submitBtn.value = '提交';
                submitBtn.style.opacity = '1';
            }
        });
    }

    if (verifyInput) {
        verifyInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && submitBtn) submitBtn.click();
        });
    }

    if (typeof MAC !== 'undefined' && MAC.Gbook) {
        MAC.Gbook.Login = 1;
        MAC.Gbook.Verify = 1;
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.line-tab')) {
        var detailEp = initEpisodeModule({
            isDetail: true,
            rootSel: '.episode-line-content',
            lineTabSel: '.line-tab',
            lineContentSel: '.episode-line-content',
            groupTabSel: '.group-tab',
            itemSel: '.ep-chip',
            sortAttr: 'nid',
            containerSel: '#epList{line}',
            scrollBoxSel: '.episode-scroll',
            sortToggleId: 'sortToggle',
            nameTargetId: 'currentLineName',
            lineLabelId: null
        });
        detailEp.initFirstLine();
    }

    if (document.querySelector('.line-btn')) {
        var playEp = initEpisodeModule({
            isDetail: false,
            rootSel: '.play-episode-content',
            lineTabSel: '.line-btn',
            lineContentSel: '.play-episode-content',
            groupTabSel: '.play-group-tab',
            itemSel: '.ep-btn',
            sortAttr: 'ep',
            containerSel: 'playEpList{line}',
            scrollBoxSel: '.episode-grid',
            sortToggleId: 'epSortToggle',
            nameTargetId: 'currentLineTitle',
            lineLabelId: 'lineLabel'
        });
        playEp.initFirstLine();
    }

    var descText = document.getElementById('descText');
    var descToggle = document.getElementById('descToggle');
    if (descText && descToggle) {
        var descExpanded = false;
        descToggle.addEventListener('click', function () {
            descExpanded = !descExpanded;
            if (descExpanded) {
                descText.style.maxHeight = descText.scrollHeight + 'px';
                descToggle.textContent = '收起 ▲';
            } else {
                descText.style.maxHeight = '3.5em';
                descToggle.textContent = '展开全部 ▼';
            }
        });
    }

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

    document.querySelectorAll('.play-group-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var wrap = this.closest('.fenzu');
            if (wrap) {
                this.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });
    });
});

(function () {
    var stage = document.getElementById('heroSliderStage');
    var slider = document.getElementById('heroSlider');
    var dotsBox = document.getElementById('heroSliderDots');
    if (!stage || !slider) return;

    var slides = stage.querySelectorAll('.hero-slide');
    var total = slides.length;
    if (!total) return;

    var index = 0;
    var timer = null;
    var interval = parseInt(slider.dataset.autoplay || '5000', 10);

    var dots = [];
    if (dotsBox) {
        for (var i = 0; i < total; i++) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'hero-slider-dot';
            dot.setAttribute('aria-label', '第 ' + (i + 1) + ' 张');
            dot.addEventListener('click', function () {
                goTo(parseInt(this.dataset.i, 10));
                resetAuto();
            });
            dot.dataset.i = i;
            dotsBox.appendChild(dot);
            dots.push(dot);
        }
    }

    function goTo(n) {
        index = ((n % total) + total) % total;
        slides.forEach(function (slide, i) {
            slide.classList.toggle('active', i === index);
        });
        if (dots.length) {
            dots.forEach(function (d, i) {
                d.classList.toggle('active', i === index);
            });
        }
    }

    function next() {
        goTo(index + 1);
    }

    function prev() {
        goTo(index - 1);
    }

    function startAuto() {
        stopAuto();
        if (interval > 0) {
            timer = setInterval(next, interval);
        }
    }

    function stopAuto() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function resetAuto() {
        stopAuto();
        startAuto();
    }

    var prevBtn = document.getElementById('heroSliderPrev');
    var nextBtn = document.getElementById('heroSliderNext');
    if (prevBtn)
        prevBtn.addEventListener('click', function () {
            prev();
            resetAuto();
        });
    if (nextBtn)
        nextBtn.addEventListener('click', function () {
            next();
            resetAuto();
        });

    var touchStartX = 0;
    slider.addEventListener(
        'touchstart',
        function (e) {
            touchStartX = e.changedTouches[0].screenX;
        },
        { passive: true }
    );
    slider.addEventListener('touchend', function (e) {
        var diff = touchStartX - e.changedTouches[0].screenX;
        if (Math.abs(diff) > 40) {
            if (diff > 0) {
                next();
            } else {
                prev();
            }
            resetAuto();
        }
    });

    slider.addEventListener('mouseenter', stopAuto);
    slider.addEventListener('mouseleave', startAuto);

    goTo(0);
    startAuto();
})();

(function () {
    var favTouched = false;
    var curUlogId = '';
    function getCookie(name) {
        var m = document.cookie.match(
            new RegExp('(?:^|;\\s*)' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)')
        );
        return m ? decodeURIComponent(m[1]) : '';
    }
    function isLoginUser() {
        return !!(getCookie('user_check') || getCookie('user_id') || getCookie('user_name'));
    }
    function favKey(id) {
        return 'hl_fav_' + id;
    }
    function markFavLocal(id) {
        try {
            localStorage.setItem(favKey(id), '1');
        } catch (e) {}
    }
    function getFavLocal(id) {
        try {
            return localStorage.getItem(favKey(id)) === '1';
        } catch (e) {
            return false;
        }
    }
    function setFavBtn(btn) {
        if (!btn) return;
        btn.setAttribute('data-fav', '1');
        btn.classList.remove('disabled');
        btn.innerHTML = '<i class="icon-hearts-fill iconfont" style="color: #e45757;"></i>已收藏';
    }
    function resetFavBtn(btn) {
        if (!btn) return;
        btn.setAttribute('data-fav', '0');
        btn.innerHTML = '<i class="icon-hearts-line iconfont"></i>收藏';
    }
    function isFavNow(btn, id) {
        if (btn && btn.getAttribute('data-fav') === '1') return true;
        if (getFavLocal(id)) return true;
        if (btn && btn.classList.contains('active')) return true;
        return false;
    }
    function apiBase() {
        return window.MAC && MAC.config && MAC.config.path ? MAC.config.path : (window.maccms && maccms.path) || '';
    }
    function parseRes(res) {
        var obj = res;
        if (typeof res === 'string') {
            try {
                obj = JSON.parse(res);
            } catch (e) {
                obj = null;
            }
        }
        return obj;
    }
    function getJson(url, onDone) {
        if (window.fetch) {
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .then(onDone)
                .catch(function () {
                    if (window.toastAlert) toastAlert('操作失败，请稍后');
                });
        } else if (window.$) {
            $.get(url, onDone, 'json');
        }
    }

    function doFav(btn) {
        var id = btn.getAttribute('data-id');
        if (!id) return;
        var mid = btn.getAttribute('data-mid') || (window.maccms && maccms.mid) || '1';
        var type = btn.getAttribute('data-type') || '2';
        var base = apiBase();
        var url =
            base +
            '/index.php/user/ajax_ulog/?ac=set&mid=' +
            encodeURIComponent(mid) +
            '&id=' +
            encodeURIComponent(id) +
            '&type=' +
            encodeURIComponent(type);
        getJson(url, function (res) {
            var obj = parseRes(res);
            if (obj && (parseInt(obj.code, 10) === 1 || parseInt(obj.code, 10) === 1001)) {
                favTouched = true;
                markFavLocal(id);
                setFavBtn(btn);
                if (window.toastAlert) toastAlert('收藏成功');
            } else {
                if (window.toastAlert) toastAlert((obj && obj.msg) || '操作失败，请稍后');
            }
        });
    }

    function doUnfav(btn, id) {
        var base = apiBase();
        function del(ulogId) {
            if (!ulogId) {
                if (window.toastAlert) toastAlert('暂无法取消，请到会员中心-我的收藏操作');
                return;
            }
            getJson(base + '/index.php/user/ulog_del?ids=' + encodeURIComponent(ulogId) + '&type=2', function (res) {
                var obj = parseRes(res);
                if (obj && parseInt(obj.code, 10) === 1) {
                    favTouched = true;
                    try {
                        localStorage.removeItem(favKey(id));
                    } catch (e) {}
                    curUlogId = '';
                    resetFavBtn(btn);
                    if (window.toastAlert) toastAlert('已取消收藏');
                } else {
                    if (window.toastAlert) toastAlert((obj && obj.msg) || '操作失败，请稍后');
                }
            });
        }
        if (curUlogId) {
            del(curUlogId);
        } else {
            var mid = btn.getAttribute('data-mid') || (window.maccms && maccms.mid) || '1';
            var url =
                base +
                '/index.php/user/ajax_ulog/?ac=list&mid=' +
                encodeURIComponent(mid) +
                '&id=' +
                encodeURIComponent(id) +
                '&type=2&page=1&limit=10';
            getJson(url, function (res) {
                var obj = parseRes(res);
                var uid = '';
                if (obj && obj.list) {
                    for (var i = 0; i < obj.list.length; i++) {
                        var it = obj.list[i];
                        var rid =
                            it.ulog_rid !== undefined ? it.ulog_rid : it.data_id !== undefined ? it.data_id : it.id;
                        if (String(rid) === String(id)) {
                            uid = it.ulog_id || '';
                            break;
                        }
                    }
                }
                if (uid) {
                    curUlogId = uid;
                }
                del(uid);
            });
        }
    }

    document.addEventListener(
        'click',
        function (e) {
            var t = e.target;
            var btn = t && t.closest ? t.closest('.hl-detail-fav') : null;
            if (!btn) return;
            var id = btn.getAttribute('data-id');
            if (!id) return;
            if (!isLoginUser()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (window.openLogin) window.openLogin();
                else if (window.MAC && MAC.User && MAC.User.directLogin) MAC.User.directLogin();
                else if (window.MAC && MAC.User && MAC.User.Login) MAC.User.Login();
                else if (window.toastAlert) toastAlert('请先登录');
                return;
            }
            if (isFavNow(btn, id)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                doUnfav(btn, id);
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            doFav(btn);
        },
        true
    );

    (function checkFavState() {
        var btns = document.querySelectorAll('.hl-detail-fav[data-id]');
        if (!btns.length) return;
        if (!isLoginUser()) return;
        var base = apiBase();
        for (var i = 0; i < btns.length; i++) {
            (function (btn) {
                var id = btn.getAttribute('data-id');
                if (!id) return;
                var mid = btn.getAttribute('data-mid') || (window.maccms && maccms.mid) || '1';
                var url =
                    base +
                    '/index.php/user/ajax_ulog/?ac=list&mid=' +
                    encodeURIComponent(mid) +
                    '&id=' +
                    encodeURIComponent(id) +
                    '&type=2&page=1&limit=10';
                function done(res) {
                    if (favTouched) return;
                    var obj = parseRes(res);
                    if (!obj || parseInt(obj.code, 10) !== 1) return;
                    var found = false;
                    var list = obj.list;
                    if (list && Object.prototype.toString.call(list) === '[object Array]') {
                        for (var i = 0; i < list.length; i++) {
                            var it = list[i];
                            if (!it) continue;
                            var rid =
                                it.ulog_rid !== undefined ? it.ulog_rid : it.data_id !== undefined ? it.data_id : it.id;
                            if (String(rid) === String(id)) {
                                found = true;
                                curUlogId = it.ulog_id || '';
                                break;
                            }
                        }
                    }
                    if (found) {
                        markFavLocal(id);
                        setFavBtn(btn);
                    } else if (getFavLocal(id)) {
                        try {
                            localStorage.removeItem(favKey(id));
                        } catch (e) {}
                        resetFavBtn(btn);
                    }
                }
                getJson(url, done);
            })(btns[i]);
        }
    })();

    var shareBtn = document.querySelector('.hl-detail-share');
    if (shareBtn) {
        function copyFallback(url) {
            var ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                if (window.toastAlert) toastAlert('链接已复制，快去分享吧');
            } catch (e) {
                if (window.toastAlert) toastAlert('复制失败，请手动复制地址栏链接');
            }
            document.body.removeChild(ta);
        }
        shareBtn.addEventListener('click', function () {
            var url = window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard
                    .writeText(url)
                    .then(function () {
                        if (window.toastAlert) toastAlert('链接已复制，快去分享吧');
                    })
                    .catch(function () {
                        copyFallback(url);
                    });
            } else {
                copyFallback(url);
            }
        });
    }
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