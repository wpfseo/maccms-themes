/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

$(function () {
    var payment = document.getElementById('payment');
    var paytype = document.getElementById('paytype');
    var paytypeBox = document.getElementById('paytype_box');
    var tip = document.getElementById('payTip');

    payment.addEventListener('change', function () {
        var val = this.value;
        paytype.innerHTML = '';

        if (val === 'codepay') {
            paytype.innerHTML = document.getElementById('paytypeSrc').innerHTML;
            paytypeBox.style.display = 'block';
        } else if (val === 'zhapay') {
            paytype.innerHTML = document.getElementById('zhapaySrc').innerHTML;
            paytypeBox.style.display = 'block';
        } else {
            paytypeBox.style.display = 'none';
        }
    });

    document.querySelector('.hl-pay-form').addEventListener('submit', function (e) {
        var paymentVal = payment.value;
        if (!paymentVal) {
            e.preventDefault();
            tip.textContent = '❌ 请选择支付方式';
            tip.className = 'hl-form-tip error';
            return false;
        }

        if ((paymentVal === 'codepay' || paymentVal === 'zhapay') && !paytype.value) {
            e.preventDefault();
            tip.textContent = '❌ 请选择支付类型';
            tip.className = 'hl-form-tip error';
            return false;
        }

        tip.textContent = '';
        tip.className = 'hl-form-tip';
    });
});
