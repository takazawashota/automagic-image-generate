jQuery(document).ready(function ($) {
    // 背景画像アップロード
    var amigMediaUploader;

    $('#amig-upload-bg-image').on('click', function (e) {
        e.preventDefault();

        if (amigMediaUploader) {
            amigMediaUploader.open();
            return;
        }

        amigMediaUploader = wp.media({
            title: '背景画像を選択',
            button: {
                text: '画像を使用'
            },
            multiple: false
        });

        amigMediaUploader.on('select', function () {
            var attachment = amigMediaUploader.state().get('selection').first().toJSON();
            $('#amig-bg-image-id').val(attachment.id);
            $('#amig-bg-image-preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;" />').show();

            if ($('#amig-remove-bg-image').length === 0) {
                $('#amig-upload-bg-image').after(' <button type="button" class="button" id="amig-remove-bg-image">画像を削除</button>');
            }
        });

        amigMediaUploader.open();
    });

    // 背景画像削除
    $(document).on('click', '#amig-remove-bg-image', function (e) {
        e.preventDefault();
        $('#amig-bg-image-id').val('');
        $('#amig-bg-image-preview').html('').hide();
        $(this).remove();
    });

    // 透明度スライダーの値表示
    $('#amig-bg-image-opacity').on('input', function () {
        $('#amig-opacity-value').text($(this).val() + '%');
    });

    // プレビュー生成
    $('#amig-preview-btn').on('click', function () {
        var previewText = $('#amig-preview-text').val();
        var $btn = $(this);
        var $loading = $('#amig-preview-loading');
        var $container = $('#amig-preview-container');

        if (!previewText) {
            alert('プレビューテキストを入力してください');
            return;
        }

        $btn.prop('disabled', true);
        $loading.show();
        $container.html('');

        $.ajax({
            url: amigAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'amig_preview_generate',
                preview_text: previewText,
                nonce: amigAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    $container.html('<img src="' + response.data.url + '?t=' + new Date().getTime() + '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;" alt="プレビュー画像" />');
                    $container.append('<p style="margin-top: 10px; color: #666;">画像サイズ: 1200 x 630 px</p>');
                } else {
                    $container.html('<p style="color: red;">エラー: ' + response.data + '</p>');
                }
                $btn.prop('disabled', false);
                $loading.hide();
            },
            error: function () {
                $container.html('<p style="color: red;">通信エラーが発生しました</p>');
                $btn.prop('disabled', false);
                $loading.hide();
            }
        });
    });

    // 手動生成
    $('#amig-generate-btn').on('click', function () {
        var postId = $('#amig-post-id').val();
        var $btn = $(this);
        var $status = $('#amig-status');

        if (!postId || postId < 1) {
            $status.text('有効な投稿IDを入力してください').css('color', 'red');
            return;
        }

        $btn.prop('disabled', true);
        $status.text('生成中...').css('color', 'blue');

        $.ajax({
            url: amigAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'amig_manual_generate',
                post_id: postId,
                nonce: amigAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    $status.text(response.data).css('color', 'green');
                } else {
                    $status.text('エラー: ' + response.data).css('color', 'red');
                }
                $btn.prop('disabled', false);
            },
            error: function () {
                $status.text('通信エラーが発生しました').css('color', 'red');
                $btn.prop('disabled', false);
            }
        });
    });
});
