/*
**************************************************
* 作者:          海螺主题
* 海螺主题官网:    https://www.hailuo.cc
* 版本:          HL093 极光 2.1.5
* 最后更新:    2026-09-18
**************************************************
*/

(function () {
    'use strict';

    var basePath = typeof maccms !== 'undefined' && maccms.path ? maccms.path : '';
    var vodId = $('.mac_comment').data('id') || '0';

    function getEmojiBasePath() {
        try {
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.indexOf('comment-emoji.js') !== -1) {
                    var pathParts = scripts[i].src.split('/');
                    pathParts.pop();
                    pathParts.pop();
                    pathParts.push('emoji');
                    return pathParts.join('/') + '/';
                }
            }
        } catch (e) {}
        return '/static/emoji/';
    }
    var EMOJI_BASE_PATH = getEmojiBasePath();

    var validEmojis = [
        '呆问',
        '注意',
        '沉思',
        '震撼',
        '呆滞',
        '欸嘿',
        '喝茶',
        '怒气',
        '哼',
        '玫瑰狗',
        '羞',
        '哈欠',
        '鼓掌',
        '吃瓜',
        '挖鼻',
        '奋斗',
        '吐',
        '打call',
        '给心心',
        '支持',
        '墨镜',
        '白眼',
        'doge',
        '坏笑',
        '难过',
        '生气',
        '委屈',
        '斜眼笑',
        '呆',
        '发怒',
        '惊吓',
        '呕吐',
        '思考',
        '微笑',
        '疑问',
        '大哭',
        '鼓励',
        '抠鼻',
        '亲亲',
        '调皮',
        '笑哭',
        '晕',
        '点赞',
        '害羞',
        '睡着',
        '色',
        '吐血',
        '无奈',
        '再见',
        '流汗',
        '偷笑',
        '抓狂',
        '问号',
        '困',
        '打脸',
        '闭嘴',
        '鄙视',
        '腼腆',
        '馋',
        '可爱',
        '发财',
        '生病',
        '流鼻血',
        '尴尬',
        '大佬'
    ];

    var isMobileDevice =
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
        window.innerWidth <= 768;
    var cursorManager = {
        editor: null,
        startPos: 0,
        endPos: 0,
        text: ''
    };

    function saveCursorPosition(textarea) {
        if (!textarea) return;

        cursorManager.editor = textarea;
        cursorManager.startPos = textarea.selectionStart || 0;
        cursorManager.endPos = textarea.selectionEnd || 0;
        cursorManager.text = textarea.value || '';
    }

    function restoreCursorPosition(textarea) {
        if (!textarea) return false;

        textarea.focus();

        var textLen = (textarea.value || '').length;
        var startPos = Math.min(cursorManager.startPos, textLen);
        var endPos = Math.min(cursorManager.endPos, textLen);

        try {
            textarea.setSelectionRange(startPos, endPos);
            return true;
        } catch (e) {
            try {
                textarea.setSelectionRange(textLen, textLen);
                return true;
            } catch (e2) {
                return false;
            }
        }
    }

    function insertEmoji(textarea, emojiText) {
        if (!textarea) return false;

        textarea.focus();

        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        var text = textarea.value || '';

        if (startPos === undefined || startPos === null) {
            startPos = cursorManager.startPos || 0;
            endPos = cursorManager.endPos || startPos;
        }

        var before = text.substring(0, startPos);
        var after = text.substring(endPos);
        var newText = before + emojiText + after;
        var newPos = startPos + emojiText.length;

        textarea.value = newText;

        try {
            textarea.setSelectionRange(newPos, newPos);
        } catch (e) {}

        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        cursorManager.editor = textarea;
        cursorManager.startPos = newPos;
        cursorManager.endPos = newPos;
        cursorManager.text = newText;

        autoResizeTextarea(textarea);

        return true;
    }

    function autoResizeTextarea(textarea) {
        if (!textarea) return;
        var scrollHeight = textarea.scrollHeight;
        var minHeight = 38;
        var maxHeight = 200;
        var finalHeight = Math.min(Math.max(scrollHeight, minHeight), maxHeight);
        if (parseInt(textarea.style.height) !== finalHeight) {
            textarea.style.height = finalHeight + 'px';
        }
    }

    function setTextareaStyle(textarea) {
        if (!textarea) return;
        textarea.style.width = 'calc(100% - 60px)';
        textarea.style.minHeight = '38px';
        textarea.style.maxHeight = '200px';
        textarea.style.height = '38px';
        textarea.style.resize = 'vertical';
        textarea.style.outline = 'none';
        textarea.style.boxSizing = 'border-box';
        textarea.style.fontFamily = 'inherit';
        textarea.style.fontSize = '14px';
        textarea.style.lineHeight = '1.7';
        textarea.style.padding = '6px 10px';
        textarea.style.border = '1px solid var(--border-light)';
        textarea.style.borderRadius = '20px';
        textarea.style.backgroundColor = 'var(--bg-card)';
        textarea.style.color = 'var(--text-primary)';
        textarea.style.float = 'right';
        textarea.style.display = 'inline-block';
        textarea.style.verticalAlign = 'middle';
        textarea.style.overflow = 'hidden';
    }

    function checkVerifyWrapper(editor) {
        if (!editor) return;
        var wrapper = editor.closest('.comment-input-row');
        if (!wrapper) return;
        var verifyWrapper = wrapper.querySelector('.verify-wrapper, #verifyWrapper, .reply-verify-wrapper');
        if (!verifyWrapper) return;

        var isVisible =
            verifyWrapper.style.display !== 'none' &&
            verifyWrapper.style.display !== '' &&
            window.getComputedStyle(verifyWrapper).display !== 'none';

        if (isVisible) {
            editor.classList.add('yzm-textarea');
        } else {
            editor.classList.remove('yzm-textarea');
        }
    }

    function convertToTextarea(editor) {
        if (!editor) return null;

        if (editor.tagName === 'TEXTAREA') {
            return editor;
        }

        var textarea = document.createElement('textarea');

        textarea.className = editor.className;
        textarea.id = editor.id;

        textarea.placeholder =
            editor.getAttribute('data-placeholder') || editor.getAttribute('placeholder') || '输入内容...';

        setTextareaStyle(textarea);

        var content = editor.innerText || editor.textContent || '';
        textarea.value = content;

        editor.parentNode.replaceChild(textarea, editor);

        var parent = textarea.parentNode;
        if (parent) {
            parent.style.display = 'flex';
            parent.style.alignItems = 'center';
            parent.style.gap = '8px';
            parent.style.flexWrap = 'wrap';
        }

        textarea.addEventListener('focus', function () {
            setTimeout(function () {
                saveCursorPosition(textarea);
                checkVerifyWrapper(textarea);
            }, 50);
        });

        textarea.addEventListener('click', function () {
            setTimeout(function () {
                saveCursorPosition(textarea);
                checkVerifyWrapper(textarea);
            }, 50);
        });

        textarea.addEventListener('keyup', function () {
            setTimeout(function () {
                saveCursorPosition(textarea);
                checkVerifyWrapper(textarea);
            }, 50);
        });

        textarea.addEventListener('blur', function () {
            saveCursorPosition(textarea);
        });

        textarea.addEventListener('input', function () {
            autoResizeTextarea(this);
            saveCursorPosition(this);
        });

        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var submitBtn = this.closest('.comment-input-row').querySelector(
                    '.submit-btn, .reply-submit-btn, #submitBtn'
                );
                if (submitBtn) {
                    submitBtn.click();
                }
            }
        });

        var wrapper = textarea.closest('.comment-input-row');
        if (wrapper) {
            var verifyWrapper = wrapper.querySelector('.verify-wrapper, #verifyWrapper, .reply-verify-wrapper');
            if (verifyWrapper) {
                var observer = new MutationObserver(function () {
                    checkVerifyWrapper(textarea);
                });
                observer.observe(verifyWrapper, {
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }
        }

        setTimeout(function () {
            autoResizeTextarea(textarea);
            saveCursorPosition(textarea);
            checkVerifyWrapper(textarea);
        }, 100);

        return textarea;
    }

    function generateEmojiHTML() {
        var html = '';
        validEmojis.forEach(function (num) {
            html += '<span data-emoji="' + num + '">';
            html += '<img src="' + EMOJI_BASE_PATH + num + '.gif" alt="' + num + '" loading="lazy">';
            html += '</span>';
        });
        return html;
    }

    function initAllEmojiBoxes() {
        document.querySelectorAll('.emoji-box, .reply-emoji-box').forEach(function (box) {
            if (!box.dataset.initialized) {
                box.innerHTML = generateEmojiHTML();
                box.dataset.initialized = 'true';
            }
        });
    }

    function closeAllPanels() {
        document.querySelectorAll('.emoji-box, .reply-emoji-box').forEach(function (box) {
            box.classList.remove('active');
            box.style.display = 'none';
        });
    }

    function getEditorForBox(box) {
        var wrapper = box ? box.closest('.comment-reply-wrapper') : null;
        if (wrapper) {
            return wrapper.querySelector('.reply-editor, .reply-textarea');
        }
        return document.querySelector('.comment-editor, .comment-textarea');
    }

    function getMainEmojiBox() {
        var picker = document.querySelector('.comment-input-row .emoji-picker');
        if (picker) {
            var box = picker.querySelector('.emoji-box:not(.reply-emoji-box)');
            if (box) return box;
        }
        var box = document.querySelector('.comment-input-row .emoji-box');
        if (box) return box;
        return document.querySelector('.emoji-box:not(.reply-emoji-box)');
    }

    function getReplyEmojiBox(btn) {
        var picker = btn ? btn.closest('.emoji-picker') : null;
        if (picker) {
            var box = picker.querySelector('.reply-emoji-box');
            if (box) return box;
        }
        var wrapper = btn ? btn.closest('.comment-reply-wrapper') : null;
        if (wrapper) {
            var box = wrapper.querySelector('.reply-emoji-box');
            if (box) return box;
        }
        return document.querySelector('.reply-emoji-box');
    }

    function showEmojiPanel(btn, box, editor) {
        if (!btn || !box) return;
        closeAllPanels();

        if (editor) {
            saveCursorPosition(editor);
        }

        var delay = isMobileDevice ? 600 : 50;

        if (isMobileDevice && editor) {
            editor.blur();
        }

        setTimeout(function () {
            var rect = btn.getBoundingClientRect();
            var windowHeight = window.innerHeight;
            var panelHeight = 210;
            var top = rect.top - panelHeight;
            var left = rect.left;

            if (top < 10) {
                top = rect.bottom + 10;
            }
            if (top + panelHeight > windowHeight - 10) {
                top = rect.top - panelHeight;
                if (top < 10) top = 10;
            }
            if (left + 300 > window.innerWidth) {
                left = window.innerWidth - 310;
            }
            if (left < 10) left = 10;

            box.style.cssText =
                'position:fixed;top:' +
                top +
                'px;left:' +
                left +
                'px;z-index:999999;max-height:' +
                panelHeight +
                'px;overflow-y:auto;';
            box.classList.add('active');
            box.style.display = 'grid';
        }, delay);
    }

    window.toggleEmoji = function (btn) {
        if (window.event) {
            window.event.preventDefault();
            window.event.stopPropagation();
        }

        var editor = document.querySelector('.comment-editor, .comment-textarea');
        var box = getMainEmojiBox();
        if (!box) return;

        if (!box.dataset.initialized) {
            box.innerHTML = generateEmojiHTML();
            box.dataset.initialized = 'true';
        }

        if (box.classList.contains('active')) {
            box.classList.remove('active');
            box.style.display = 'none';
            if (editor) {
                setTimeout(function () {
                    editor.focus();
                    restoreCursorPosition(editor);
                    checkVerifyWrapper(editor);
                }, 300);
            }
            return;
        }

        showEmojiPanel(btn, box, editor);
    };

    window.toggleReplyEmoji = function (btn, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        if (window.event) {
            window.event.preventDefault();
            window.event.stopPropagation();
        }

        var wrapper = btn.closest('.comment-reply-wrapper');
        var editor = wrapper ? wrapper.querySelector('.reply-editor, .reply-textarea') : null;
        var box = getReplyEmojiBox(btn);
        if (!box) return;

        if (!box.dataset.initialized) {
            box.innerHTML = generateEmojiHTML();
            box.dataset.initialized = 'true';
        }

        if (box.classList.contains('active')) {
            box.classList.remove('active');
            box.style.display = 'none';
            if (editor) {
                setTimeout(function () {
                    editor.focus();
                    restoreCursorPosition(editor);
                    checkVerifyWrapper(editor);
                }, 300);
            }
            return;
        }

        showEmojiPanel(btn, box, editor);
    };

    function setupEmojiClickHandler() {
        document.addEventListener(
            'click',
            function (e) {
                var span = e.target.closest('.emoji-box span[data-emoji], .reply-emoji-box span[data-emoji]');
                if (!span) return;

                e.preventDefault();
                e.stopPropagation();

                var num = span.dataset.emoji;
                var box = span.closest('.emoji-box, .reply-emoji-box');
                var editor = getEditorForBox(box);

                if (!editor) {
                    return;
                }

                var emojiText = '{' + num + '}';

                saveCursorPosition(editor);

                if (isMobileDevice) {
                    editor.focus();
                    setTimeout(function () {
                        restoreCursorPosition(editor);
                        setTimeout(function () {
                            insertEmoji(editor, emojiText);
                        }, 150);
                    }, 200);
                } else {
                    insertEmoji(editor, emojiText);
                }

                if (box) {
                    setTimeout(
                        function () {
                            box.classList.remove('active');
                            box.style.display = 'none';
                            if (editor) {
                                setTimeout(function () {
                                    editor.focus();
                                    restoreCursorPosition(editor);
                                    checkVerifyWrapper(editor);
                                }, 100);
                            }
                        },
                        isMobileDevice ? 500 : 100
                    );
                }
            },
            true
        );
    }

    function setupOutsideClickHandler() {
        document.addEventListener('click', function (e) {
            if (
                e.target.closest('.emoji-btn') ||
                e.target.closest('.emoji-box') ||
                e.target.closest('.reply-emoji-box')
            ) {
                return;
            }
            closeAllPanels();
        });
    }

    function setupEscHandler() {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAllPanels();
        });
    }

    function setupResizeHandler() {
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(closeAllPanels, 300);
        });
    }

    function renderCommentEmojis(root) {
        if (!root) root = document;
        root.querySelectorAll('.comment-body').forEach(function (el) {
            if (el.dataset.emojiRendered) return;
            var html = el.innerHTML;
            html = html.replace(/\{([^}]+)\}/g, function (match, num) {
                return (
                    '<img src="' +
                    EMOJI_BASE_PATH +
                    num +
                    '.gif" alt="' +
                    num +
                    '" class="emoji-img" style="width:28px;height:28px;vertical-align:middle;display:inline-block;margin:0 3px;">'
                );
            });
            el.innerHTML = html;
            el.dataset.emojiRendered = 'true';
        });
    }

    function setupMutationObserver() {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            node.querySelectorAll('.comment-editor, .reply-editor').forEach(function (editor) {
                                var textarea = convertToTextarea(editor);
                                if (textarea) {
                                    textarea.className = textarea.className.replace(
                                        'comment-editor',
                                        'comment-textarea'
                                    );
                                    textarea.className = textarea.className.replace('reply-editor', 'reply-textarea');
                                }
                            });
                            node.querySelectorAll('.emoji-box, .reply-emoji-box').forEach(function (box) {
                                if (!box.dataset.initialized) {
                                    box.innerHTML = generateEmojiHTML();
                                    box.dataset.initialized = 'true';
                                }
                            });
                            renderCommentEmojis(node);
                            if (node.querySelector('#commentEditor, #submitBtn')) {
                                initMainComment();
                            }
                        }
                    });
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function refreshVerify(verifyImg) {
        if (verifyImg) {
            verifyImg.src = verifyImg.src.split('?')[0] + '?' + Date.now();
        }
    }

    function submitComment(pid, content, verify, callback) {
        var postData = {
            comment_rid: vodId,
            comment_mid: '1',
            comment_content: content,
            comment_pid: pid || 0,
            verify: verify || ''
        };

        $.ajax({
            url: basePath + '/index.php/comment/saveData',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                if (callback) callback(res);
            },
            error: function () {
                if (callback) callback(null);
            }
        });
    }

    function getEditorContent(editor) {
        if (!editor) return '';
        return (editor.value || '').trim();
    }

    var mainCommentInited = false;

    function initMainComment() {
        if (mainCommentInited) return;
        var editor = document.getElementById('commentEditor');
        var verifyWrapper = document.getElementById('verifyWrapper');
        var verifyCodeEl = document.getElementById('verifyCode');
        var verifyImg = document.getElementById('verifyImg');
        var submitBtn = document.getElementById('submitBtn');

        if (!editor || !submitBtn) return;
        mainCommentInited = true;

        var textarea = convertToTextarea(editor);
        if (textarea) {
            textarea.id = 'commentEditor';
            textarea.className = 'comment-textarea';
        }

        var finalEditor = textarea || editor;

        finalEditor.addEventListener('focus', function () {
            if (verifyWrapper) {
                verifyWrapper.style.display = 'flex';

                setTimeout(function () {
                    checkVerifyWrapper(finalEditor);
                }, 50);
            }
            saveCursorPosition(this);
        });

        function submit() {
            var content = getEditorContent(finalEditor);
            if (!content) {
                alert('请输入评论内容');
                finalEditor.focus();
                return;
            }

            var verify = '';
            if (verifyWrapper && verifyWrapper.style.display !== 'none') {
                verify = verifyCodeEl ? verifyCodeEl.value.trim() : '';
                if (!verify) {
                    alert('请输入验证码');
                    return;
                }
            }

            submitBtn.textContent = '提交中...';
            submitBtn.style.opacity = '0.6';

            submitComment(0, content, verify, function (res) {
                submitBtn.textContent = '发送';
                submitBtn.style.opacity = '1';
                if (res === null) {
                    alert('评论失败，请检查网络');
                    refreshVerify(verifyImg);
                    return;
                }
                if (res.code === 1) {
                    alert(res.data && res.data.status === 0 ? '评论提交成功，等待管理员审核后显示' : '评论发布成功！');
                    finalEditor.value = '';
                    if (verifyCodeEl) verifyCodeEl.value = '';
                    if (verifyWrapper) verifyWrapper.style.display = 'none';

                    checkVerifyWrapper(finalEditor);
                    location.reload();
                } else {
                    alert(res.msg || '评论失败');
                    refreshVerify(verifyImg);
                }
            });
        }

        submitBtn.onclick = function (e) {
            e.preventDefault();
            submit();
        };

        finalEditor.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submit();
            }
        });

        if (verifyCodeEl) {
            verifyCodeEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submit();
                }
            });
        }

        if (verifyWrapper) {
            var observer = new MutationObserver(function () {
                checkVerifyWrapper(finalEditor);
            });
            observer.observe(verifyWrapper, {
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }
    }

    function initDigg() {
        $('body').on('click', '.digg_link', function (e) {
            e.preventDefault();
            var $that = $(this);
            var id = $that.data('id');
            var mid = $that.data('mid');
            var type = $that.data('type');
            if (!id || $that.hasClass('disabled')) return;

            $.ajax({
                url: basePath + '/index.php/ajax/digg.html',
                type: 'GET',
                data: { mid: mid, id: id, type: type },
                dataType: 'json',
                success: function (r) {
                    if (r.code == 1) {
                        var numEl = $that.find('.digg_num');
                        numEl.text(type == 'up' ? r.data.up : r.data.down);
                        $that.addClass('disabled');
                    } else {
                        alert(r.msg || '操作失败');
                    }
                }
            });
        });
    }

    function initReport() {
        $('body').on('click', '.comment_report', function (e) {
            e.preventDefault();
            var $that = $(this);
            var id = $that.data('id');
            if (!id || $that.hasClass('disabled')) return;
            if (!confirm('确定要举报该评论吗？')) return;

            $.ajax({
                url: basePath + '/index.php/comment/report.html',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function (r) {
                    if (r.code == 1) {
                        alert('举报成功，感谢您的反馈！');
                        $that.addClass('disabled').text('已举报');
                    } else {
                        alert(r.msg || '举报失败');
                    }
                }
            });
        });
    }

    function initReply() {
        $('body').on('click', '.comment_reply', function (e) {
            e.preventDefault();
            var $that = $(this);
            var pid = $that.data('id');
            if (!pid) return;

            var $gwAction = $that.closest('.gw_action');
            if (!$gwAction.length) return;

            var $existingReply = $gwAction.next('.comment-reply-wrapper');
            if ($existingReply.length) {
                $existingReply.remove();
                $that.text('回复').removeClass('qxhf').addClass('hf');
                $('.comment-input-row:first').show();
                var commentList = document.querySelector('#panel-discuss .comment-tl');
                if (commentList) commentList.style.maxHeight = '';
                return;
            }

            $('.comment-reply-wrapper').remove();
            $('.comment_reply').text('回复');
            $('.comment-input-row:first').hide();

            var commentList = document.querySelector('#panel-discuss .comment-tl');
            if (commentList) {
                commentList.style.setProperty('max-height', window.innerWidth <= 768 ? '347px' : '475px', 'important');
            }

            var replyHtml =
                '<div class="comment-reply-wrapper">' +
                '<div class="comment-input-row">' +
                '<div class="emoji-picker">' +
                '<span class="emoji-btn" onclick="window.toggleReplyEmoji(this,event)">😊</span>' +
                '<div class="emoji-box reply-emoji-box"></div>' +
                '</div>' +
                '<textarea class="reply-editor reply-textarea" placeholder="输入回复内容..."></textarea>' +
                '<div class="reply-verify-wrapper">' +
                '<input type="text" class="reply-verify-input yanzheng-1" placeholder="  验证码">' +
                '<img class="reply-verify-img yanzhengtu-1" src="' +
                basePath +
                '/index.php/verify/index" onclick="this.src=\'' +
                basePath +
                '/index.php/verify/index?\'+Date.now()">' +
                '</div>' +
                '<a href="javascript:;" class="reply-submit-btn btn-primarys" data-pid="' +
                pid +
                '">回复</a>' +
                '</div>' +
                '</div>';

            $gwAction.after(replyHtml);
            $that.text('取消回复').removeClass('hf').addClass('qxhf');

            var $newReply = $gwAction.next('.comment-reply-wrapper');
            var replyEditor = $newReply.find('.reply-editor')[0];

            if (replyEditor) {
                setTextareaStyle(replyEditor);
                replyEditor.style.overflow = 'hidden';

                var parent = replyEditor.parentNode;
                if (parent) {
                    parent.style.display = 'flex';
                    parent.style.alignItems = 'center';
                    parent.style.gap = '8px';
                    parent.style.flexWrap = 'wrap';
                }

                replyEditor.addEventListener('focus', function () {
                    var wrapper = $(this).closest('.comment-input-row').find('.reply-verify-wrapper');
                    wrapper.css('display', 'flex');
                    saveCursorPosition(this);
                    setTimeout(function () {
                        checkVerifyWrapper(replyEditor);
                    }, 50);
                });
                replyEditor.addEventListener('click', function () {
                    saveCursorPosition(this);
                    checkVerifyWrapper(this);
                });
                replyEditor.addEventListener('keyup', function () {
                    saveCursorPosition(this);
                    checkVerifyWrapper(this);
                });
                replyEditor.addEventListener('blur', function () {
                    saveCursorPosition(this);
                });
                replyEditor.addEventListener('input', function () {
                    autoResizeTextarea(this);
                    saveCursorPosition(this);
                });
                replyEditor.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        $(this).closest('.comment-input-row').find('.reply-submit-btn').click();
                    }
                });

                var wrapper = replyEditor.closest('.comment-input-row');
                if (wrapper) {
                    var verifyWrapper = wrapper.querySelector('.reply-verify-wrapper');
                    if (verifyWrapper) {
                        var observer = new MutationObserver(function () {
                            checkVerifyWrapper(replyEditor);
                        });
                        observer.observe(verifyWrapper, {
                            attributes: true,
                            attributeFilter: ['style', 'class']
                        });
                    }
                }

                setTimeout(function () {
                    saveCursorPosition(replyEditor);
                    var scrollHeight = replyEditor.scrollHeight;
                    var minHeight = 38;
                    var maxHeight = 200;
                    var finalHeight = Math.min(Math.max(scrollHeight, minHeight), maxHeight);
                    replyEditor.style.height = finalHeight + 'px';
                    checkVerifyWrapper(replyEditor);
                }, 50);

                var box = $newReply.find('.reply-emoji-box')[0];
                if (box && !box.dataset.initialized) {
                    box.innerHTML = generateEmojiHTML();
                    box.dataset.initialized = 'true';
                }
            }

            setTimeout(function () {
                if (replyEditor) {
                    replyEditor.focus();
                    saveCursorPosition(replyEditor);
                    checkVerifyWrapper(replyEditor);
                }
            }, 100);
        });
    }

    function initReplySubmit() {
        $('body').on('click', '.reply-submit-btn', function (e) {
            e.preventDefault();
            var $that = $(this);
            var pid = $that.data('pid');
            var $wrapper = $that.closest('.comment-reply-wrapper');
            var editor = $wrapper.find('.reply-editor, .reply-textarea')[0];
            if (!editor) return;

            var content = (editor.value || '').trim();
            if (!content) {
                alert('请输入回复内容');
                editor.focus();
                return;
            }

            var $verifyWrapper = $wrapper.find('.reply-verify-wrapper');
            var verify = '';
            if ($verifyWrapper.length && $verifyWrapper.css('display') !== 'none') {
                verify = $wrapper.find('.reply-verify-input').val().trim();
                if (!verify) {
                    alert('请输入验证码');
                    $wrapper.find('.reply-verify-input').focus();
                    return;
                }
            }

            $that.text('提交中...').css('opacity', '0.6');

            submitComment(pid, content, verify, function (res) {
                $that.text('回复').css('opacity', '1');
                if (res === null) {
                    alert('回复失败，请检查网络');
                    $wrapper.find('.reply-verify-img').attr('src', function (i, src) {
                        return src.split('?')[0] + '?' + Date.now();
                    });
                    return;
                }
                if (res.code == 1) {
                    alert('回复成功' + (res.data && res.data.status === 0 ? '，等待审核' : ''));
                    $('.comment-input-row:first').show();
                    var commentList = document.querySelector('#panel-discuss .comment-tl');
                    if (commentList) commentList.style.maxHeight = '';
                    location.reload();
                } else {
                    alert(res.msg || '回复失败');
                    $wrapper.find('.reply-verify-img').attr('src', function (i, src) {
                        return src.split('?')[0] + '?' + Date.now();
                    });
                    $wrapper.find('.reply-verify-input').val('');
                }
            });
        });

        $('body').on('keydown', '.reply-verify-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('.comment-input-row').find('.reply-submit-btn').click();
            }
        });
    }

    function init() {
        document.querySelectorAll('.comment-editor, .reply-editor').forEach(function (editor) {
            var textarea = convertToTextarea(editor);
            if (textarea) {
                textarea.className = textarea.className.replace('comment-editor', 'comment-textarea');
                textarea.className = textarea.className.replace('reply-editor', 'reply-textarea');
            }
        });

        initAllEmojiBoxes();
        renderCommentEmojis(document);

        setupEmojiClickHandler();
        setupOutsideClickHandler();
        setupEscHandler();
        setupResizeHandler();
        setupMutationObserver();

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.emoji-btn[data-emoji-btn]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                window.toggleEmoji(btn);
                return;
            }
            var pageBtn = e.target.closest('.page-btns[data-page]');
            if (pageBtn && window.MAC && MAC.Comment) {
                e.preventDefault();
                MAC.Comment.Show(pageBtn.getAttribute('data-page'));
                return;
            }
            var verifyImg = e.target.closest('#verifyImg[data-captcha], .reply-verify-img');
            if (verifyImg) {
                verifyImg.src = verifyImg.src.split('?')[0] + '?' + Date.now();
            }
        });

        initMainComment();
        initReport();
        initReply();
        initReplySubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
