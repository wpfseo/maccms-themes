/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    var form = document.querySelector('.hl-member-form');
    var tip = document.getElementById('formTip');

    if (!form) return;

    function msg(text, ok) {
        if (tip) {
            tip.textContent = text || '';
            tip.className = 'hl-form-tip' + (ok ? ' ok' : '');
        }
    }

    form.addEventListener('submit', function (e) {
        var pwd1 = form.querySelector('[name="user_pwd1"]');
        var pwd2 = form.querySelector('[name="user_pwd2"]');

        if (pwd1 && pwd2 && pwd1.value !== pwd2.value) {
            e.preventDefault();
            msg('两次输入的密码不一致');
            return;
        }

        if (pwd1 && pwd1.value.length > 0 && pwd1.value.length < 6) {
            e.preventDefault();
            msg('密码至少 6 位');
            return;
        }

        msg('保存中...');
    });
})();
