/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

document.querySelectorAll('.hl-form-captcha-img').forEach(function(img) {
  img.addEventListener('click', function() {
    this.src = this.src.replace(/\?.*$/, '') + '?t=' + Date.now();
  });
});

document.querySelectorAll('.hl-form-code-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var that = this;
    var seconds = 60;
    that.disabled = true;
    that.textContent = seconds + 's';
    var timer = setInterval(function() {
      seconds--;
      if (seconds <= 0) {
        clearInterval(timer);
        that.disabled = false;
        that.textContent = '获取验证码';
      } else {
        that.textContent = seconds + 's';
      }
    }, 1000);
  });
});

document.querySelector('.hl-auth-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var form = this;
    var tip = document.getElementById('regTip');
    var btn = form.querySelector('.hl-auth-btn');
    var oldText = btn.textContent;
    
    
    tip.textContent = '提交中...';
    tip.className = 'hl-form-tip loading';
    btn.disabled = true;
    btn.textContent = '注册中...';
    
    var data = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res && parseInt(res.code, 10) === 1) {
            tip.textContent = '' + (res.msg || '注册成功！正在跳转...');
            tip.className = 'hl-form-tip success';
            
            btn.disabled = false;
            btn.textContent = oldText;
            setTimeout(function() {
                location.href = form.getAttribute('data-redirect') || '/index.php/user/index.html';
            }, 1500);
        } else {
            tip.textContent = '' + (res.msg || '注册失败，请重试');
            tip.className = 'hl-form-tip error';
            btn.disabled = false;
            btn.textContent = oldText;
            
            var img = document.querySelector('.hl-form-captcha-img');
            if (img) img.src = img.src.replace(/\?.*$/, '') + '?t=' + Date.now();
        }
    })
    .catch(function() {
        tip.textContent = '网络异常，请稍后再试';
        tip.className = 'hl-form-tip error';
        btn.disabled = false;
        btn.textContent = oldText;
    });
});
