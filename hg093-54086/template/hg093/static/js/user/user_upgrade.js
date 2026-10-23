/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    var tipEl = document.querySelector('.js-vip-tip');

    function showTip(msg, type) {
        if (tipEl) {
            tipEl.textContent = msg || '';
            tipEl.className = 'hl-form-tip' + (type === 'ok' ? ' ok' : '') + (type === 'error' ? ' error' : '');
        }
    }

    document.querySelectorAll('.js-upgrade').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var groupId = this.getAttribute('data-group');
            var long = this.getAttribute('data-long');

            showTip('正在处理...', '');

            fetch(maccms.path + 'index.php/user/upgrade.html', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'group_id=' + encodeURIComponent(groupId) + '&long=' + encodeURIComponent(long),
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res && parseInt(res.code, 10) === 1) {
                        showTip('✅ ' + (res.msg || '升级成功！'), 'ok');
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showTip('❌ ' + (res && res.msg ? res.msg : '升级失败，请重试'), 'error');
                    }
                })
                .catch(function () {
                    showTip('❌ 网络异常，请稍后再试', 'error');
                });
        });
    });
})();
