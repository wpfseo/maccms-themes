/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    document.querySelectorAll('.js-auth-form').forEach(function (form) {
        var tip = form.querySelector('.hl-auth-tip');

        function message(text, ok) {
            if (tip) {
                tip.innerHTML = text || '';
                tip.style.color = ok ? '#72f0a8' : '#ffb0c7';
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            message('');

            var btn = form.querySelector('button[type="submit"]');
            var old = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '提交中...';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res && parseInt(res.code, 10) === 1) {
                        message((res && res.msg) || '登录成功', true);
                        var redirect = form.getAttribute('data-redirect') || maccms.path + 'index.php/user/index.html';
                        setTimeout(function () {
                            location.href = redirect;
                        }, 500);
                        return;
                    }
                    message((res && res.msg) || '登录失败，请重试');
                    var cap = form.querySelector('.js-captcha');
                    if (cap) {
                        cap.src = maccms.path + 'index.php/verify/index.html?' + Date.now();
                    }
                })
                .catch(function () {
                    message('网络异常，请稍后再试');
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = old;
                    }
                });
        });
    });

    document.querySelectorAll('.js-captcha').forEach(function (img) {
        img.addEventListener('click', function () {
            this.src = maccms.path + 'index.php/verify/index.html?' + Date.now();
        });
    });
})();

document.querySelectorAll('.js-captcha[data-captcha]').forEach(function (img) {
    img.addEventListener('click', function () {
        this.src = maccms.path + 'index.php/verify/index.html?t=' + Date.now();
    });
});
