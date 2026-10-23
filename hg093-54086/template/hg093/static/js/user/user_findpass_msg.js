/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    var form = document.querySelector('.hl-code-form');
    if (!form) return;

    var tip = form.querySelector('[data-code-tip]');
    var sendBtn = form.querySelector('[data-send-code]');

    function msg(text, ok) {
        if (tip) {
            tip.textContent = text || '';
            tip.className = 'hl-form-tip' + (ok ? ' ok' : '');
        }
    }

    function getEmail() {
        return (form.querySelector('[name="user_email"]').value || '').trim();
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            var btn = this;
            var to = getEmail();
            if (!to) {
                msg('请先输入邮箱');
                return;
            }
            btn.disabled = true;
            msg('正在发送...');

            var url = form.getAttribute('data-send-url');
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ac=email&to=' + encodeURIComponent(to),
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    msg(res && res.msg ? res.msg : '已发送', res && res.code == 1);
                })
                .catch(function () {
                    msg('发送失败，请重试');
                })
                .finally(function () {
                    setTimeout(function () {
                        btn.disabled = false;
                    }, 3000);
                });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var pwd = form.querySelector('[name="user_pwd"]');
        var pwd2 = form.querySelector('[name="user_pwd2"]');

        if (pwd && pwd2 && pwd.value !== pwd2.value) {
            msg('两次输入的密码不一致');
            return;
        }

        if (pwd && pwd.value.length < 6) {
            msg('密码至少 6 位');
            return;
        }

        msg('正在保存...');

        var url = form.getAttribute('data-save-url');
        var data = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                msg(res && res.msg ? res.msg : '保存成功', res && res.code == 1);
                if (res && res.code == 1) {
                    setTimeout(function () {
                        location.href = form.getAttribute('data-success-url');
                    }, 1000);
                }
            })
            .catch(function () {
                msg('保存失败，请重试');
            });
    });
})();
