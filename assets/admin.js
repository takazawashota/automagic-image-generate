/* global jQuery, wp, amigAjax */
jQuery(document).ready(function ($) {

    /* ============================================================
       タブ切り替え
    ============================================================ */
    $('.amig-tab').on('click', function () {
        var target = $(this).data('tab');
        $('.amig-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.amig-tab-panel').removeClass('is-active');
        $('#amig-tab-' + target).addClass('is-active');
    });

    /* ============================================================
       カラーピッカー ↔ HEX テキスト入力 双方向同期
    ============================================================ */
    // color input → hex text
    $(document).on('input change', 'input[type="color"][id]', function () {
        var id = $(this).attr('id');
        var hex = $(this).val();
        $('[data-for="' + id + '"]').val(hex);
        updateStyleCanvases();
        schedulePreview();
    });

    // hex text → color input
    $(document).on('input', '.amig-color-hex', function () {
        var id = $(this).data('for');
        var hex = $(this).val();
        if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
            $('#' + id).val(hex);
            updateStyleCanvases();
            schedulePreview();
        }
    });

    /* ============================================================
       レンジスライダー → 表示値同期
    ============================================================ */
    $('#amig-font-size-range').on('input', function () {
        var v = $(this).val();
        $('#amig-font-size-val').text(v + 'px');
        $('#amig-font-size').val(v);
        schedulePreview();
    });

    $('#amig-opacity-range').on('input', function () {
        var v = $(this).val();
        $('#amig-opacity-val').text(v + '%');
        schedulePreview();
    });

    $('#amig-line-height-range').on('input', function () {
        var v = $(this).val();
        $('#amig-line-height-val').text(v);
        $('#amig-line-height').val(v);
        schedulePreview();
    });

    $('#amig-letter-spacing-range').on('input', function () {
        var v = $(this).val();
        $('#amig-letter-spacing-val').text(v + 'px');
        $('#amig-letter-spacing').val(v);
        schedulePreview();
    });

    $('#amig-max-chars-range').on('input', function () {
        var v = $(this).val();
        $('#amig-max-chars-val').text(v == '0' ? '制限なし' : v + '文字');
        $('#amig-max-chars').val(v);
        schedulePreview();
    });

    $('#amig-text-bg-opacity-range').on('input', function () {
        var v = $(this).val();
        $('#amig-text-bg-opacity-val').text(v == '0' ? 'なし' : v + '%');
        schedulePreview();
    });

    /* ============================================================
       フォントウェイトピルボタン
    ============================================================ */
    $(document).on('click', '.amig-weight-btn', function () {
        $(this).closest('.amig-weight-group').find('.amig-weight-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $(this).closest('.amig-weight-group').find('input[type="radio"]').prop('checked', false);
        $(this).find('input[type="radio"]').prop('checked', true);
        schedulePreview();
    });

    /* ============================================================
       テキスト配置ボタン
    ============================================================ */
    $(document).on('click', '.amig-align-btn', function () {
        $(this).closest('.amig-align-group').find('.amig-align-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $(this).closest('.amig-align-group').find('input[type="radio"]').prop('checked', false);
        $(this).find('input[type="radio"]').prop('checked', true);
        schedulePreview();
    });

    /* ============================================================
       スタイルカード選択
    ============================================================ */
    $(document).on('change', '.amig-style-card input[type="radio"]', function () {
        $('.amig-style-card').removeClass('is-selected');
        $(this).closest('.amig-style-card').addClass('is-selected');
        schedulePreview();
    });

    /* ============================================================
       スタイルカード Canvas ミニプレビュー
    ============================================================ */
    function updateStyleCanvases() {
        var c1 = $('#amig-bg-color').val() || '#1a1a2e';
        var c2 = $('#amig-text-color').val() || '#ffffff';
        var c3 = $('#amig-accent-color').val() || '#0f3460';

        $('.amig-style-preview-canvas').each(function () {
            var canvas = this;
            if (!canvas.getContext) { return; }
            var ctx = canvas.getContext('2d');
            if (!ctx) { return; }
            var style = $(canvas).data('style') || 'modern';
            var w = canvas.width || 320;
            var h = canvas.height || 168;
            ctx.clearRect(0, 0, w, h);
            drawStylePreview(ctx, style, w, h, c1, c2, c3);
        });
    }

    function drawStylePreview(ctx, style, w, h, c1, c2, c3) {
        var grad, split, bandH, m;
        switch (style) {

            // フレーム：単色＋内側二重枠線
            case 'frame':
                ctx.fillStyle = c1;
                ctx.fillRect(0, 0, w, h);
                m = Math.round(w * 0.06);
                ctx.strokeStyle = c3;
                ctx.lineWidth = 3;
                ctx.strokeRect(m, m, w - m * 2, h - m * 2);
                ctx.lineWidth = 1;
                ctx.strokeRect(m + 6, m + 6, w - (m + 6) * 2, h - (m + 6) * 2);
                // テキストプレースホルダー
                ctx.fillStyle = hexToRgba(c2, 0.30);
                ctx.fillRect(Math.round(w * 0.2), Math.round(h * 0.38), Math.round(w * 0.6), Math.round(h * 0.09));
                ctx.fillRect(Math.round(w * 0.25), Math.round(h * 0.54), Math.round(w * 0.5), Math.round(h * 0.07));
                break;

            // 二分割：左38%アクセント＋右62%ベース
            case 'split':
                split = Math.round(w * 0.38);
                ctx.fillStyle = c3;
                ctx.fillRect(0, 0, split, h);
                ctx.fillStyle = c1;
                ctx.fillRect(split, 0, w - split, h);
                // 境界ライン
                ctx.fillStyle = 'rgba(255,255,255,0.6)';
                ctx.fillRect(split, 0, 2, h);
                // 右側テキストプレースホルダー
                ctx.fillStyle = hexToRgba(c2, 0.28);
                ctx.fillRect(split + Math.round(w * 0.05), Math.round(h * 0.3), Math.round(w * 0.48), Math.round(h * 0.1));
                ctx.fillRect(split + Math.round(w * 0.05), Math.round(h * 0.47), Math.round(w * 0.38), Math.round(h * 0.08));
                break;

            // バッジ帯：上部グラデ＋下部横帯
            case 'badge':
                grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, c1);
                grad.addColorStop(1, shadeColor(c1, -30));
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);
                bandH = Math.round(h * 0.22);
                ctx.fillStyle = c3;
                ctx.fillRect(0, h - bandH, w, bandH);
                ctx.fillStyle = 'rgba(255,255,255,0.25)';
                ctx.fillRect(0, h - bandH, w, 2);
                // テキストプレースホルダー（帯の上）
                ctx.fillStyle = hexToRgba(c2, 0.28);
                ctx.fillRect(Math.round(w * 0.1), Math.round(h * 0.3), Math.round(w * 0.8), Math.round(h * 0.1));
                ctx.fillRect(Math.round(w * 0.15), Math.round(h * 0.46), Math.round(w * 0.7), Math.round(h * 0.08));
                break;

            // 斜め分割：左上アクセント三角＋右下ベース
            case 'diagonal':
                ctx.fillStyle = c1;
                ctx.fillRect(0, 0, w, h);
                ctx.fillStyle = c3;
                ctx.beginPath();
                ctx.moveTo(0, 0);
                ctx.lineTo(w, 0);
                ctx.lineTo(0, h);
                ctx.closePath();
                ctx.fill();
                // 境界ライン
                ctx.strokeStyle = 'rgba(255,255,255,0.5)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(0, h);
                ctx.lineTo(w, 0);
                ctx.stroke();
                // テキストプレースホルダー
                ctx.fillStyle = hexToRgba(c2, 0.28);
                ctx.fillRect(Math.round(w * 0.38), Math.round(h * 0.38), Math.round(w * 0.52), Math.round(h * 0.1));
                ctx.fillRect(Math.round(w * 0.42), Math.round(h * 0.55), Math.round(w * 0.44), Math.round(h * 0.08));
                break;

            // グラデーション：上から下へ bg_color → accent_color
            case 'gradient':
                grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, c1);
                grad.addColorStop(1, c3);
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);
                break;

            // モダン：グラデーション＋半透明の装飾円
            case 'modern':
                grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, c1);
                grad.addColorStop(1, c3);
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);
                ctx.fillStyle = hexToRgba(c3, 0.35);
                ctx.beginPath();
                ctx.arc(w * 0.8, h * 0.3, w * 0.22, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(w * 0.2, h * 0.7, w * 0.17, 0, Math.PI * 2);
                ctx.fill();
                break;

            // ミニマル：白背景＋左端アクセントライン
            case 'minimal':
                ctx.fillStyle = '#f8f9fa';
                ctx.fillRect(0, 0, w, h);
                ctx.fillStyle = c3;
                ctx.fillRect(0, 0, Math.round(w * 0.035), h);
                ctx.fillStyle = 'rgba(0,0,0,0.12)';
                ctx.fillRect(Math.round(w * 0.08), Math.round(h * 0.35), Math.round(w * 0.7), Math.round(h * 0.08));
                ctx.fillRect(Math.round(w * 0.08), Math.round(h * 0.52), Math.round(w * 0.5), Math.round(h * 0.06));
                break;

            // シンプル：単色塗りつぶし
            case 'simple':
                ctx.fillStyle = c1;
                ctx.fillRect(0, 0, w, h);
                break;

            default:
                grad = ctx.createLinearGradient(0, 0, 0, h);
                grad.addColorStop(0, c1);
                grad.addColorStop(1, c3);
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);
        }
    }

    // HEX色を明暗調整するユーティリティ（amount: 正=明るく 負=暗く）
    function shadeColor(hex, amount) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        if (!result) return hex;
        var r = Math.min(255, Math.max(0, parseInt(result[1], 16) + amount));
        var g = Math.min(255, Math.max(0, parseInt(result[2], 16) + amount));
        var b = Math.min(255, Math.max(0, parseInt(result[3], 16) + amount));
        return '#' + ('0' + r.toString(16)).slice(-2) + ('0' + g.toString(16)).slice(-2) + ('0' + b.toString(16)).slice(-2);
    }

    // カラー文字列を rgba() に変換するユーティリティ
    function hexToRgba(hex, alpha) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        if (!result) return 'rgba(0,0,0,' + alpha + ')';
        return 'rgba(' + parseInt(result[1], 16) + ',' + parseInt(result[2], 16) + ',' + parseInt(result[3], 16) + ',' + alpha + ')';
    }

    // 初期化
    updateStyleCanvases();

    /* ============================================================
       背景画像アップロード（新 UI）
    ============================================================ */
    var amigMediaUploader;

    $('#amig-upload-bg-image').on('click', function () {
        openMediaUploader();
    });

    function openMediaUploader() {
        if (amigMediaUploader) {
            amigMediaUploader.open();
            return;
        }
        amigMediaUploader = wp.media({
            title: '背景画像を選択',
            button: { text: '画像を使用' },
            multiple: false
        });
        amigMediaUploader.on('select', function () {
            var att = amigMediaUploader.state().get('selection').first().toJSON();
            $('#amig-bg-image-id').val(att.id);
            $('#amig-upload-bg-image').hide();
            $('#amig-bg-preview-wrap').show().find('img').attr('src', att.url).attr('alt', att.title || '');
            schedulePreview();
        });
        amigMediaUploader.open();
    }

    $(document).on('click', '#amig-remove-bg-image', function (e) {
        e.preventDefault();
        $('#amig-bg-image-id').val('');
        $('#amig-bg-preview-wrap').hide();
        $('#amig-upload-bg-image').show();
        schedulePreview();
    });

    /* ============================================================
       リアルタイムプレビュー（デバウンス 600ms）
    ============================================================ */
    var previewTimer = null;

    function schedulePreview() {
        if (typeof amigAjax === 'undefined') return;
        clearTimeout(previewTimer);
        $('#amig-preview-status')
            .removeClass('is-loading')
            .html('<span class="dashicons dashicons-clock"></span> 更新待機中…');
        previewTimer = setTimeout(triggerPreview, 600);
    }

    function triggerPreview() {
        if (!$('#amig-design-form').length) { return; }

        var text = $('#amig-preview-text').val() || '日本語タイトルのサンプル';

        var $panel = $('.amig-preview-panel');
        var $canvasWrap = $panel.find('.amig-preview-canvas-wrap');
        var $ph = $panel.find('#amig-preview-placeholder');
        $canvasWrap.addClass('is-loading');

        $('#amig-preview-status')
            .addClass('is-loading')
            .html('<span class="dashicons dashicons-update"></span> 生成中…');

        // プレースホルダーをリセット
        $ph.css('color', '').find('.dashicons')
            .removeClass('dashicons-warning').addClass('dashicons-format-image');
        $ph.find('span:not(.dashicons)').text('生成中...');
        $ph.show();

        // フォーム内のすべての設定を収集してから、WP管理フォーム用フィールドを削除し
        // プレビュー専用パラメータで上書きすることで action が確実に正しい値になる
        var data = {};
        $('#amig-design-form').find('input, select, textarea').each(function () {
            var name = $(this).attr('name');
            if (!name) return;
            if ($(this).is(':radio') || $(this).is(':checkbox')) {
                if (!$(this).is(':checked')) return;
            }
            data[name] = $(this).val();
        });
        // settings_fields() が出力する WP 管理用フィールドを削除
        delete data['action'];
        delete data['_wpnonce'];
        delete data['_wp_http_referer'];
        delete data['option_page'];
        // プレビュー専用パラメータを最後に設定（確実に上書き）
        data.action = 'amig_preview_generate';
        data.nonce = amigAjax.nonce;
        data.preview_text = text;

        $.ajax({
            url: amigAjax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (r) {
                if (r.success && r.data && r.data.url) {
                    var $img = $panel.find('.amig-preview-image');
                    var url = r.data.url + '?t=' + Date.now();
                    $img.attr('src', url).show();
                    $ph.hide();
                    // ダウンロードボタンを有効化
                    $('#amig-download-btn').attr('href', url).show();
                    // ステータス更新
                    var now = new Date();
                    var ts = now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    $('#amig-preview-status')
                        .removeClass('is-loading')
                        .html('<span class="dashicons dashicons-yes"></span> ' + ts + ' 更新');
                } else {
                    var msg = (r && r.data) ? r.data : '画像の生成に失敗しました';
                    $ph.css('color', '#dc2626')
                        .find('.dashicons').removeClass('dashicons-format-image').addClass('dashicons-warning');
                    if (msg.indexOf('nonce') !== -1) {
                        $ph.find('span:not(.dashicons)').html('セッション切れ - <a href="" onclick="location.reload();return false;">ページを再読み込み</a>');
                    } else {
                        $ph.find('span:not(.dashicons)').text(msg);
                    }
                    $ph.show();
                    $panel.find('.amig-preview-image').hide();
                    $('#amig-preview-status')
                        .removeClass('is-loading')
                        .html('<span class="dashicons dashicons-warning"></span> 生成失敗');
                }
            },
            error: function (xhr, textStatus) {
                var msg;
                if (textStatus === 'parseerror') {
                    msg = 'サーバー応答エラー(parseerror) - WP_DEBUG_DISPLAYを無効化してください';
                } else if (xhr.status === 0) {
                    msg = 'ネットワークエラー - サーバーに接続できません';
                } else {
                    msg = 'AJAXエラー (HTTP ' + xhr.status + ') - ページを再読み込みしてください';
                }
                $ph.css('color', '#dc2626')
                    .find('.dashicons').removeClass('dashicons-format-image').addClass('dashicons-warning');
                $ph.find('span:not(.dashicons)').text(msg);
                $ph.show();
                $panel.find('.amig-preview-image').hide();
                $('#amig-preview-status')
                    .removeClass('is-loading')
                    .html('<span class="dashicons dashicons-warning"></span> エラー');
            },
            complete: function () {
                $canvasWrap.removeClass('is-loading');
            }
        });
    }

    // 手動更新ボタン
    $(document).on('click', '#amig-refresh-preview', function () {
        triggerPreview();
    });

    // フォームの変更でプレビューをスケジュール
    $(document).on('change input', '#amig-design-form input, #amig-design-form select, #amig-design-form textarea', function () {
        // カラーピッカーは既に上記でハンドル済み、重複トリガーを避ける
        if ($(this).is('input[type="color"]') || $(this).hasClass('amig-color-hex')) return;
        schedulePreview();
    });

    // プレビューテキスト変更
    $('#amig-preview-text').on('input', function () {
        schedulePreview();
    });

    // 初回プレビュー
    schedulePreview();

    /* ============================================================
       保存バー / 保存完了メッセージ
    ============================================================ */
    $('#amig-design-form').on('submit', function () {
        var $msg = $('.amig-save-msg');
        if ($msg.length) {
            setTimeout(function () {
                $msg.addClass('is-visible');
                setTimeout(function () { $msg.removeClass('is-visible'); }, 3000);
            }, 300);
        }
    });

});

/* ============================================================
   投稿編集画面 メタボックス JS（投稿編集ページでのみ動作）
============================================================ */
jQuery(document).ready(function ($) {

    if (!$('.amig-metabox').length) return;

    function mbRequest(action, postId, nonce, onSuccess, onError) {
        var $box = $('#amig-metabox-' + postId);
        var $wrap = $box.find('.amig-mb-preview-wrap');
        var $msg = $box.find('.amig-mb-msg');

        $wrap.addClass('is-loading');
        $msg.hide().removeClass('is-success is-error');

        $.ajax({
            url: (typeof amigAjax !== 'undefined' ? amigAjax.ajax_url : ajaxurl),
            type: 'POST',
            data: { action: action, post_id: postId, nonce: nonce },
            success: function (r) {
                if (r.success) {
                    onSuccess(r.data, $box, $wrap, $msg);
                } else {
                    showMsg($msg, r.data || 'エラーが発生しました', 'error');
                    if (onError) onError($box, $wrap, $msg);
                }
            },
            error: function () {
                showMsg($msg, '通信エラーが発生しました', 'error');
                if (onError) onError($box, $wrap, $msg);
            },
            complete: function () {
                $wrap.removeClass('is-loading');
            }
        });
    }

    function showMsg($msg, text, type) {
        $msg.text(text).removeClass('is-success is-error').addClass('is-' + type).show();
        setTimeout(function () { $msg.fadeOut(400); }, 3500);
    }

    // ── 生成 / 再生成 ──
    $(document).on('click', '.amig-mb-btn-generate', function () {
        var postId = $(this).data('post-id');
        var nonce = $('#amig-metabox-' + postId).data('nonce');

        if ($(this).find('.dashicons-update').length) {
            if (!confirm('既存のサムネイルを削除して再生成しますか？')) return;
        }

        mbRequest('amig_post_generate', postId, nonce,
            function (data, $box, $wrap, $msg) {
                var $img = $wrap.find('.amig-mb-preview-img');
                if (!$img.length) {
                    $wrap.find('.amig-mb-empty').remove();
                    $img = $('<img class="amig-mb-preview-img" alt="">').prependTo($wrap);
                }
                $img.attr('src', data.thumb_url + '?t=' + Date.now());
                $wrap.removeClass('no-image').addClass('has-image');

                $box.find('.amig-mb-status').html(
                    '<span class="amig-badge amig-badge-success">このプラグインで生成済み</span>'
                );
                var $genBtn = $box.find('.amig-mb-btn-generate');
                $genBtn.removeClass('amig-btn-primary').addClass('amig-btn-secondary')
                    .html('<span class="dashicons dashicons-update"></span> 再生成');

                if (!$box.find('.amig-mb-btn-delete').length) {
                    $box.find('.amig-mb-actions').append(
                        '<button type="button" class="amig-btn amig-btn-danger amig-mb-btn-delete"' +
                        ' data-post-id="' + postId + '">' +
                        '<span class="dashicons dashicons-trash"></span> 削除</button>'
                    );
                }
                showMsg($msg, '\u2713 ' + data.message, 'success');
            }
        );
    });

    // ── 削除 ──
    $(document).on('click', '.amig-mb-btn-delete', function () {
        var postId = $(this).data('post-id');
        var nonce = $('#amig-metabox-' + postId).data('nonce');

        if (!confirm('このプラグインで生成したサムネイルを削除しますか？')) return;

        mbRequest('amig_post_delete', postId, nonce,
            function (data, $box, $wrap, $msg) {
                $wrap.find('.amig-mb-preview-img').remove();
                if (!$wrap.find('.amig-mb-empty').length) {
                    $wrap.append(
                        '<div class="amig-mb-empty">' +
                        '<span class="dashicons dashicons-format-image"></span>' +
                        '<span>\u672a\u8a2d\u5b9a</span></div>'
                    );
                }
                $wrap.removeClass('has-image').addClass('no-image');

                $box.find('.amig-mb-status').html(
                    '<span class="amig-badge amig-badge-warning">\u30b5\u30e0\u30cd\u30a4\u30eb\u672a\u8a2d\u5b9a</span>'
                );
                var $genBtn = $box.find('.amig-mb-btn-generate');
                $genBtn.removeClass('amig-btn-secondary').addClass('amig-btn-primary')
                    .html('<span class="dashicons dashicons-images-alt2"></span> \u751f\u6210\u3059\u308b');
                $box.find('.amig-mb-btn-delete').remove();

                showMsg($msg, '\u2713 ' + data.message, 'success');
            }
        );
    });

});

/* ============================================================
   生成済み画像管理ページ JS
============================================================ */
jQuery(document).ready(function ($) {

    if (!$('.amig-mg-table').length) return;

    var ajaxBase = (typeof amigAjax !== 'undefined') ? amigAjax.ajax_url : ajaxurl;

    /* ── トースト通知 ── */
    function toast(msg, duration) {
        var $t = $('#amig-toast');
        if (!$t.length) {
            $t = $('<div id="amig-toast" class="amig-toast">').appendTo('body');
        }
        $t.text(msg).addClass('is-visible');
        clearTimeout($t.data('timer'));
        $t.data('timer', setTimeout(function () { $t.removeClass('is-visible'); }, duration || 2500));
    }

    /* ── ヘッダー全選択チェックボックス ── */
    $(document).on('change', '.amig-mg-all-cb', function () {
        var pt = $(this).data('pt');
        var checked = $(this).prop('checked');
        $('.amig-mg-row-cb[data-pt="' + pt + '"]').prop('checked', checked);
    });

    // 行チェック変化でヘッダーCB を同期
    $(document).on('change', '.amig-mg-row-cb', function () {
        var pt = $(this).data('pt');
        var total = $('.amig-mg-row-cb[data-pt="' + pt + '"]').length;
        var chk = $('.amig-mg-row-cb[data-pt="' + pt + '"]:checked').length;
        $('.amig-mg-all-cb[data-pt="' + pt + '"]').prop('indeterminate', chk > 0 && chk < total).prop('checked', chk === total);
    });

    /* ── すべて選択ボタン ── */
    $(document).on('click', '.amig-mg-select-all', function () {
        var pt = $(this).data('pt');
        var $cbs = $('.amig-mg-row-cb[data-pt="' + pt + '"]');
        var allChecked = $cbs.length === $cbs.filter(':checked').length;
        $cbs.prop('checked', !allChecked);
        $('.amig-mg-all-cb[data-pt="' + pt + '"]').prop('checked', !allChecked).prop('indeterminate', false);
        $(this).text(allChecked ? 'すべて選択' : '選択解除');
    });

    /* ── 個別削除 ── */
    $(document).on('click', '.amig-mg-delete-single', function () {
        var btn = $(this);
        var postId = btn.data('post-id');
        var postTitle = btn.data('post-title') || 'この投稿';
        var nonce = btn.data('nonce');

        if (!confirm('\u300c' + postTitle + '\u300d\u306e\u30b5\u30e0\u30cd\u30a4\u30eb\u3092\u524a\u9664\u3057\u307e\u3059\u304b\uff1f')) return;

        var $row = btn.closest('tr');
        $row.addClass('is-deleting');

        $.ajax({
            url: ajaxBase,
            type: 'POST',
            data: { action: 'amig_delete_single', post_id: postId, nonce: nonce },
            success: function (r) {
                if (r.success) {
                    $row.fadeOut(250, function () {
                        $(this).remove();
                        updateCount($row.data('pt'));
                    });
                    toast('\u2713 \u524a\u9664\u3057\u307e\u3057\u305f');
                } else {
                    $row.removeClass('is-deleting');
                    toast('\u274c ' + (r.data || '\u524a\u9664\u306b\u5931\u6557\u3057\u307e\u3057\u305f'));
                }
            },
            error: function () {
                $row.removeClass('is-deleting');
                toast('\u274c \u901a\u4fe1\u30a8\u30e9\u30fc\u304c\u767a\u751f\u3057\u307e\u3057\u305f');
            }
        });
    });

    /* ── 選択削除 ── */
    $(document).on('click', '.amig-mg-delete-selected', function () {
        var btn = $(this);
        var pt = btn.data('pt');
        var nonce = btn.data('nonce');
        var $checked = $('.amig-mg-row-cb[data-pt="' + pt + '"]:checked');
        var ids = $checked.map(function () { return $(this).val(); }).get();

        if (!ids.length) {
            toast('\u524a\u9664\u3059\u308b\u6295\u7a3f\u3092\u9078\u629e\u3057\u3066\u304f\u3060\u3055\u3044');
            return;
        }
        if (!confirm(ids.length + ' \u4ef6\u306e\u30b5\u30e0\u30cd\u30a4\u30eb\u3092\u524a\u9664\u3057\u307e\u3059\u304b\uff1f\n\u3053\u306e\u64cd\u4f5c\u306f\u53d6\u308a\u6d88\u305b\u307e\u305b\u3093\u3002')) return;

        btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation:amig-spin .7s linear infinite;display:inline-block;"></span>');

        var done = 0;

        function next(i) {
            if (i >= ids.length) {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> \u9078\u629e\u524a\u9664');
                toast('\u2713 ' + done + ' \u4ef6\u524a\u9664\u3057\u307e\u3057\u305f');
                return;
            }
            var postId = ids[i];
            $.ajax({
                url: ajaxBase,
                type: 'POST',
                data: { action: 'amig_delete_single', post_id: postId, nonce: nonce },
                success: function (r) {
                    if (r.success) {
                        done++;
                        var $row = $('tr[data-post-id="' + postId + '"][data-pt="' + pt + '"]');
                        $row.fadeOut(150, function () {
                            $(this).remove();
                            updateCount(pt);
                        });
                    }
                    next(i + 1);
                },
                error: function () { next(i + 1); }
            });
        }
        next(0);
    });

    /* ── バッジカウント更新 ── */
    function updateCount(pt) {
        var remaining = $('tr[data-pt="' + pt + '"]').length;
        var $card = $('#amig-mgcard-' + pt);
        $card.find('.amig-badge-primary').text(remaining + ' \u4ef6');
        if (remaining === 0) {
            $card.find('.amig-card-body').html(
                '<p style="padding:20px;color:var(--amig-muted);">\u3053\u306e\u6295\u7a3f\u30bf\u30a4\u30d7\u306e\u751f\u6210\u753b\u50cf\u306f\u3042\u308a\u307e\u305b\u3093\u3002</p>'
            );
        }
    }

});
