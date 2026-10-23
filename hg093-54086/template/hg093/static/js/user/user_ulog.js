/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

var ulogWrap = document.querySelector('.hl-ulog-wrap');
var delUrl = ulogWrap ? ulogWrap.getAttribute('data-del-url') : '';
if (!delUrl) delUrl = maccms.path + '/index.php/user/ulog_del.html';
document.querySelectorAll('[data-delete-ulog]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var type = this.getAttribute('data-type');

        if (!confirm('确定删除这条记录吗？')) return;

        $.post(
            delUrl,
            { ids: id, type: type, all: 0 },
            function (data) {
                if (data.code == '1') {
                    toastAlert('删除成功');
                    var item = btn.closest('[data-ulog-item]');
                    if (item) item.remove();
                    if (!document.querySelector('[data-ulog-item]')) {
                        location.reload();
                    }
                } else {
                    errorAlert('删除失败：' + data.msg);
                }
            },
            'json'
        );
    });
});

var clearBtn = document.querySelector('[data-clear-ulog]');
if (clearBtn) {
    clearBtn.addEventListener('click', function () {
        if (!confirm('确定清空所有观看历史吗？')) return;

        $.post(
            delUrl,
            { ids: '', type: 4, all: 1 },
            function (data) {
                if (data.code == '1') {
                    toastAlert('清空成功');
                    location.reload();
                } else {
                    errorAlert('清空失败：' + data.msg);
                }
            },
            'json'
        );
    });
}
