/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    var tabs = document.querySelectorAll('#payTabs .hl-member-tab');
    var tabOnline = document.getElementById('tabOnline');
    var tabCard = document.getElementById('tabCard');

    function switchTab(target) {
        tabs.forEach(function (t) {
            t.classList.remove('active');
            if (t.getAttribute('data-tab') === target) {
                t.classList.add('active');
            }
        });

        if (target === 'online') {
            tabOnline.style.display = 'block';
            tabOnline.classList.add('active');
            tabCard.style.display = 'none';
            tabCard.classList.remove('active');
        } else {
            tabCard.style.display = 'block';
            tabCard.classList.add('active');
            tabOnline.style.display = 'none';
            tabOnline.classList.remove('active');
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');
            switchTab(target);
        });
    });

    var payForm = document.getElementById('payForm');
    var payBtn = document.getElementById('btnPay');
    var payTip = document.getElementById('payTip');
    var amountInput = payForm.querySelector('input[name="price"]');
    var previewEl = document.getElementById('previewPoints');
    var quickBtns = payForm.querySelectorAll('.hl-form-quick-btn');
    var scaleEl = document.getElementById('scaleRate');
    var scale = scaleEl ? parseFloat(scaleEl.textContent) || 1 : 1;

    function updatePreview() {
        var val = parseFloat(amountInput.value) || 0;
        var points = Math.floor(val * scale);
        if (previewEl) {
            previewEl.textContent = points;
        }
    }

    quickBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var amount = this.getAttribute('data-amount');
            amountInput.value = amount;
            quickBtns.forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            updatePreview();
            amountInput.dispatchEvent(new Event('input'));
        });
    });

    amountInput.addEventListener('input', function () {
        quickBtns.forEach(function (b) {
            b.classList.remove('active');
        });
        updatePreview();
    });

    payForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var val = parseFloat(amountInput.value) || 0;
        var min = parseFloat(amountInput.getAttribute('min')) || 1;

        if (val < min) {
            payTip.textContent = '❌ 充值金额不能低于 ' + min + ' 元';
            payTip.className = 'hl-form-tip error';
            return;
        }

        if (!confirm('确定要在线充值 ' + val + ' 元吗？')) return;

        payTip.textContent = '⏳ 正在创建订单...';
        payTip.className = 'hl-form-tip loading';
        payBtn.disabled = true;
        payBtn.textContent = '提交中...';

        $.ajax({
            url: document.getElementById('payForm').getAttribute('data-url'),
            type: 'post',
            dataType: 'json',
            data: { price: val, flag: 'pay' },
            success: function (r) {
                if (r.code == 1) {
                    payTip.textContent = '✅ 订单创建成功，正在跳转...';
                    payTip.className = 'hl-form-tip success';
                    payBtn.disabled = false;
                    payBtn.textContent = '立即充值';
                    setTimeout(function () {
                        location.href = maccms.path + 'index.php/user/pay.html?order_code=' + r.data.order_code;
                    }, 1000);
                } else {
                    payTip.textContent = '❌ ' + (r.msg || '订单创建失败');
                    payTip.className = 'hl-form-tip error';
                    payBtn.disabled = false;
                    payBtn.textContent = '立即充值';
                }
            },
            error: function () {
                payTip.textContent = '❌ 网络异常，请稍后再试';
                payTip.className = 'hl-form-tip error';
                payBtn.disabled = false;
                payBtn.textContent = '立即充值';
            }
        });
    });

    var cardForm = document.getElementById('cardForm');
    var cardBtn = document.getElementById('btnCard');
    var cardTip = document.getElementById('cardTip');

    cardForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var no = cardForm.querySelector('input[name="card_no"]').value.trim();
        var pwd = cardForm.querySelector('input[name="card_pwd"]').value.trim();

        if (no === '' || pwd === '') {
            cardTip.textContent = '❌ 请输入充值卡号和密码';
            cardTip.className = 'hl-form-tip error';
            return;
        }

        if (!confirm('确定要使用充值卡充值吗？')) return;

        cardTip.textContent = '⏳ 正在验证...';
        cardTip.className = 'hl-form-tip loading';
        cardBtn.disabled = true;
        cardBtn.textContent = '提交中...';

        $.ajax({
            url: document.getElementById('payForm').getAttribute('data-url'),
            type: 'post',
            dataType: 'json',
            data: { card_no: no, card_pwd: pwd, flag: 'card' },
            success: function (r) {
                if (r.code == 1) {
                    cardTip.textContent = '✅ ' + (r.msg || '充值成功！');
                    cardTip.className = 'hl-form-tip success';
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    cardTip.textContent = '❌ ' + (r.msg || '充值失败');
                    cardTip.className = 'hl-form-tip error';
                }
                cardBtn.disabled = false;
                cardBtn.textContent = '确认充值';
            },
            error: function () {
                cardTip.textContent = '❌ 网络异常，请稍后再试';
                cardTip.className = 'hl-form-tip error';
                cardBtn.disabled = false;
                cardBtn.textContent = '确认充值';
            }
        });
    });

    updatePreview();
})();
