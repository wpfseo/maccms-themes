/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    function setText(sel, val) {
        document.querySelectorAll(sel).forEach(function (el) {
            el.textContent = String(val);
        });
    }

    function countFrom(res) {
        if (!res) return 0;
        if (res.total != null) return res.total;
        if (res.count != null) return res.count;
        if (res.data && res.data.total != null) return res.data.total;
        if (res.data && res.data.count != null) return res.data.count;
        if (Array.isArray(res.list)) return res.list.length;
        if (res.data && Array.isArray(res.data.list)) return res.data.list.length;
        if (Array.isArray(res.data)) return res.data.length;
        return 0;
    }

    function fetchCount(url, sel) {
        if (!window.fetch) {
            setText(sel, '0');
            return;
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                setText(sel, countFrom(res));
            })
            .catch(function () {
                setText(sel, '0');
            });
    }

    var base = typeof maccms !== 'undefined' && maccms.path ? maccms.path : '';
    fetchCount(base + 'index.php/user/ajax_ulog/?ac=list&mid=1&type=2&page=1&limit=1', '[data-fav-count]');
    fetchCount(base + 'index.php/user/ajax_ulog/?ac=list&mid=1&type=4&page=1&limit=1', '[data-history-count]');
})();
