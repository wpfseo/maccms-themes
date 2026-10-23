/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    var checkCount = 0;
    var maxChecks = 60;

    function checkPayStatus() {
        var orderCodeEl = document.querySelector('.hl-order-confirm-value');
        var orderCode = orderCodeEl ? orderCodeEl.textContent.trim() : '';
        if (!orderCode) return;

        checkCount++;
        if (checkCount > maxChecks) {
            document.querySelector('.hl-pay-status-text').textContent = '支付超时，请重新下单';
            document.querySelector('.hl-pay-status-dot').style.background = '#ff4d6a';
            return;
        }

        fetch(maccms.path + 'index.php/user/order_status.html?order_code=' + orderCode, {
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                if (res && res.code == 1 && res.status == 1) {
                    var statusEl = document.getElementById('payStatus');
                    statusEl.className = 'hl-pay-status success';
                    document.querySelector('.hl-pay-status-text').textContent = '✅ 支付成功！积分已到账';
                    clearInterval(timer);
                }
            })
            .catch(function () {});
    }

    var timer = setInterval(checkPayStatus, 5000);

    window.addEventListener('beforeunload', function () {
        clearInterval(timer);
    });
})();
