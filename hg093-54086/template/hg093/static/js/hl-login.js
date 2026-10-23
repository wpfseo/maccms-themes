/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

function openLogin() {
    document.getElementById('loginModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLogin() {
    document.getElementById('loginModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeLogin();
});

document.querySelector('.login_form_submit')?.addEventListener('click', function () {
    var form = document.querySelector('.mac_login_form');
    var tip = document.querySelector('.hl-login-tip') || document.createElement('div');
    if (!document.querySelector('.hl-login-tip')) {
        tip.className = 'hl-login-tip';
        form.appendChild(tip);
    }

    var btn = this;
    var oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '登录中...';
    tip.textContent = '';
    tip.className = 'hl-login-tip';

    var data = new FormData(form);

    fetch(form.action || maccms.path + 'index.php/user/login.html', {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (r) {
            return r.json();
        })
        .then(function (res) {
            if (res && parseInt(res.code, 10) === 1) {
                tip.textContent = res.msg || '登录成功！';
                tip.className = 'hl-login-tip ok';
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                tip.textContent = res.msg || '登录失败，请重试';
                tip.className = 'hl-login-tip';

                var img = document.querySelector('.mac_verify_img');
                if (img) img.src = img.src.replace(/\?.*$/, '') + '?' + Date.now();
            }
        })
        .catch(function () {
            tip.textContent = '网络异常，请稍后再试';
            tip.className = 'hl-login-tip';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = oldText;
        });
});
document.querySelectorAll('[data-close-login]').forEach(function (el) {
    el.addEventListener('click', function () {
        closeLogin();
    });
});
document.querySelectorAll('.mac_verify_img[data-captcha]').forEach(function (img) {
    img.addEventListener('click', function () {
        this.src = this.src.split('?')[0] + '?' + Date.now();
    });
});

document.querySelectorAll('[data-open-login]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        if (window.MAC && MAC.User && MAC.User.IsLogin == 1) return;
        openLogin();
    });
});

(function () {
    var overlay = document.getElementById('globalModal');
    var iconEl = document.getElementById('globalModalIcon');
    var titleEl = document.getElementById('globalModalTitle');
    var msgEl = document.getElementById('globalModalMessage');
    var btnsEl = document.getElementById('globalModalButtons');
    var confirmBtn = document.getElementById('globalModalConfirm');
    var cancelBtn = document.getElementById('globalModalCancel');

    var _resolve = null;
    var _isOpen = false;

    function showModal(options) {
        return new Promise(function (resolve) {
            _resolve = resolve;
            _isOpen = true;

            iconEl.textContent = options.icon || '🔔';
            titleEl.textContent = options.title || '提示';
            msgEl.textContent = options.message || '';

            var btns = options.buttons || 'confirm';

            if (btns === 'confirm') {
                confirmBtn.style.display = '';
                cancelBtn.style.display = '';
                confirmBtn.textContent = options.confirmText || '确定';
                cancelBtn.textContent = options.cancelText || '取消';
                btnsEl.className = 'global-modal-buttons';

                if (options.danger) {
                    confirmBtn.className = 'global-modal-btn global-modal-btn-danger';
                } else {
                    confirmBtn.className = 'global-modal-btn global-modal-btn-primary';
                }
                cancelBtn.className = 'global-modal-btn global-modal-btn-secondary';

                cancelBtn.onclick = function () {
                    hideModal();
                    if (_resolve) _resolve(false);
                };
            } else {
                confirmBtn.style.display = '';
                cancelBtn.style.display = 'none';
                confirmBtn.textContent = options.confirmText || '知道了';
                confirmBtn.className = 'global-modal-btn global-modal-btn-primary';
                btnsEl.className = 'global-modal-buttons single';
            }

            confirmBtn.onclick = function () {
                hideModal();
                if (_resolve) _resolve(true);
            };

            overlay.onclick = function (e) {
                if (e.target === overlay && btns === 'alert') {
                    hideModal();
                    if (_resolve) _resolve(true);
                }
            };

            overlay.classList.add('show');
        });
    }

    function hideModal() {
        _isOpen = false;
        overlay.classList.remove('show');
    }

    window.alert = function (message, title) {
        showModal({
            icon: '🔔',
            title: title || '提示',
            message: message,
            buttons: 'alert',
            confirmText: '知道了'
        });
        return undefined;
    };

    window.confirm = function (message, title, danger) {
        return showModal({
            icon: '⚠️',
            title: title || '确认',
            message: message,
            buttons: 'confirm',
            confirmText: '确定',
            cancelText: '取消',
            danger: danger || false
        });
    };
})();
document.addEventListener(
    'error',
    function (e) {
        var img = e.target;
        if (img && img.tagName === 'IMG' && img.hasAttribute('data-fallback')) {
            var fb = img.getAttribute('data-fallback');
            if (fb && img.src !== fb) {
                img.src = fb;
                img.removeAttribute('data-fallback');
            }
        }
    },
    true
);

if (window.ClipboardJS) {
    new ClipboardJS('.app-download-btn');
    $('.app-download-btn').click(function () {
        layer.tips('复制成功，么么哒', '.app-download-btn', {
            tips: [3, 'rgb(38,111,250)'],
            time: 500
        });
    });
}

(function () {
    var wait = document.getElementById('wait'),
        hrefEl = document.getElementById('href');
    if (!wait || !hrefEl) return;
    var href = hrefEl.href;
    var interval = setInterval(function () {
        var time = --wait.innerHTML;
        if (time <= 0) {
            location.href = href;
            clearInterval(interval);
        }
    }, 1000);
})();
