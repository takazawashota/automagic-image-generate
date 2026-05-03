<?php
/**
 * Automagic Image Generate - Font Manager
 * 
 * フォントファイルの管理とダウンロード機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセスを防止
}

if (!class_exists('Automagic_Font_Manager')) {
class Automagic_Font_Manager {
    
    private static $instance = null;
    
    /**
     * 必要なフォントファイルのリスト
     * プラグインで実際に使用するウェイトのみ（5種類）
     */
    private $required_fonts = array(
        'NotoSansJP-Light.ttf',    // light
        'NotoSansJP-Regular.ttf',  // normal
        'NotoSansJP-Medium.ttf',   // medium
        'NotoSansJP-Bold.ttf',     // bold
        'NotoSansJP-Black.ttf'     // black
    );
    
    /**
     * フォントダウンロードベースURL
     */
    private $font_base_url = 'https://sokulabo.com/products/plugins/automagic-image-generate/fonts/';
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        // 管理画面でのフォント確認とダウンロード機能
        add_action('admin_init', array($this, 'check_fonts_and_download'));
        add_action('admin_notices', array($this, 'font_download_notice'));
        add_action('wp_ajax_amig_download_fonts', array($this, 'ajax_download_fonts'));
        add_action('wp_ajax_amig_download_single_font', array($this, 'ajax_download_single_font'));
        
        // ライセンス画面にフォント状態を表示
        add_action('amig_license_page_content', array($this, 'display_font_status'));
    }
    
    /**
     * フォントディレクトリのパスを取得
     */
    private function get_fonts_dir() {
        return AMIG_PLUGIN_DIR . 'fonts/';
    }
    
    /**
     * フォントファイルの存在確認と不足している場合の記録
     */
    public function check_fonts_and_download() {
        $fonts_dir = $this->get_fonts_dir();
        
        // 不足しているフォントファイルをチェック
        $missing_fonts = array();
        foreach ($this->required_fonts as $font) {
            if (!file_exists($fonts_dir . $font)) {
                $missing_fonts[] = $font;
            }
        }
        
        // 不足しているフォントがある場合、オプションに保存
        if (!empty($missing_fonts)) {
            update_option('amig_missing_fonts', $missing_fonts);
        } else {
            delete_option('amig_missing_fonts');
        }
    }
    
    /**
     * フォントダウンロードの管理通知
     */
    public function font_download_notice() {
        $missing_fonts = get_option('amig_missing_fonts', array());
        
        if (empty($missing_fonts)) {
            return;
        }
        
        // プラグインのライセンス画面でのみ表示
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'amig-license') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-warning">
            <h3>📥 フォントファイルのダウンロードが必要です</h3>
            <p>
                Automagic Image Generateを正常に動作させるには、日本語フォントファイルが必要です。<br>
                不足しているフォント: <?php echo count($missing_fonts); ?>個
            </p>
            <p>
                <button type="button" class="button button-primary" id="amig-download-fonts-notice">
                    フォントファイルをダウンロード
                </button>
                <span id="amig-download-progress-notice" style="display: none;">
                    ダウンロード中... <span id="amig-download-count-notice">0</span>/<?php echo count($missing_fonts); ?>
                </span>
            </p>
            
            <script>
            document.getElementById('amig-download-fonts-notice').addEventListener('click', function() {
                var button = this;
                var progress = document.getElementById('amig-download-progress-notice');
                var countSpan = document.getElementById('amig-download-count-notice');
                
                button.style.display = 'none';
                progress.style.display = 'inline';
                
                // AJAXでフォントダウンロード開始
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                progress.innerHTML = '✅ ダウンロード完了！ページを再読み込みしています...';
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                progress.innerHTML = '❌ エラー: ' + (response.data || 'ダウンロードに失敗しました');
                                button.style.display = 'inline';
                            }
                        } catch (e) {
                            progress.innerHTML = '❌ エラー: 不正な応答';
                            button.style.display = 'inline';
                        }
                    }
                };
                
                xhr.send('action=amig_download_fonts&_ajax_nonce=' + '<?php echo wp_create_nonce('amig_download_fonts'); ?>');
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAXフォントダウンロード処理
     */
    public function ajax_download_fonts() {
        // Nonce確認
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'amig_download_fonts')) {
            wp_die(json_encode(array('success' => false, 'data' => 'セキュリティチェックに失敗しました')));
        }
        
        // 権限確認
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'data' => '権限がありません')));
        }
        
        $missing_fonts = get_option('amig_missing_fonts', array());
        
        if (empty($missing_fonts)) {
            wp_die(json_encode(array('success' => true, 'data' => 'ダウンロードするフォントがありません')));
        }
        
        $fonts_dir = $this->get_fonts_dir();
        
        // フォントディレクトリが存在しない場合は作成
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // ライセンスキーを取得
        $license_key = get_option('amig_license_key', '');
        
        $downloaded_count = 0;
        $errors = array();
        
        foreach ($missing_fonts as $font) {
            $font_url = $this->font_base_url . $font;
            $font_path = $fonts_dir . $font;
            
            // フォントファイルをダウンロード
            $response = wp_remote_get($font_url, array(
                'timeout' => 60, // フォントファイルは大きいのでタイムアウトを長く
                'headers' => array(
                    'X-License-Key' => $license_key,
                    'X-Site-URL' => get_site_url()
                )
            ));
            
            if (is_wp_error($response)) {
                $errors[] = $font . ': ' . $response->get_error_message();
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $errors[] = $font . ': HTTP ' . $response_code;
                continue;
            }
            
            $font_data = wp_remote_retrieve_body($response);
            
            // ファイルに保存
            $result = file_put_contents($font_path, $font_data);
            
            if ($result === false) {
                $errors[] = $font . ': ファイル書き込み失敗';
                continue;
            }
            
            $downloaded_count++;
        }
        
        // 結果をチェック
        if ($downloaded_count === count($missing_fonts)) {
            // 全て成功
            delete_option('amig_missing_fonts');
            wp_die(json_encode(array('success' => true, 'data' => $downloaded_count . '個のフォントをダウンロードしました')));
        } else {
            // 一部または全部失敗
            $this->check_fonts_and_download(); // 不足フォントを再チェック
            wp_die(json_encode(array('success' => false, 'data' => implode(', ', $errors))));
        }
    }
    
    /**
     * AJAX: 単一フォントをダウンロード（逐次処理用）
     */
    public function ajax_download_single_font() {
        // Nonce確認
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'amig_download_fonts')) {
            wp_send_json_error('セキュリティチェックに失敗しました');
        }
        
        // 権限確認
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        // タイムアウトとメモリ制限を緩和
        set_time_limit(120);
        ini_set('memory_limit', '256M');
        
        $missing_fonts = get_option('amig_missing_fonts', array());
        
        if (empty($missing_fonts)) {
            wp_send_json_success('ダウンロード完了');
        }
        
        // 最初のフォントを取得
        $font = array_shift($missing_fonts);
        
        $fonts_dir = $this->get_fonts_dir();
        
        // フォントディレクトリが存在しない場合は作成
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // ライセンスキーを取得
        $license_key = get_option('amig_license_key', '');
        
        $font_url = $this->font_base_url . $font;
        $font_path = $fonts_dir . $font;
        
        // フォントファイルをダウンロード
        $response = wp_remote_get($font_url, array(
            'timeout' => 120,
            'headers' => array(
                'X-License-Key' => $license_key,
                'X-Site-URL' => get_site_url()
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wp_send_json_error($font . ': ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            wp_send_json_error($font . ': HTTP ' . $response_code);
        }
        
        $font_data = wp_remote_retrieve_body($response);
        
        // ファイルに保存
        $result = file_put_contents($font_path, $font_data);
        
        if ($result === false) {
            wp_send_json_error($font . ': ファイル書き込み失敗');
        }
        
        // 残りのフォントリストを更新
        update_option('amig_missing_fonts', $missing_fonts);
        
        // 残りのフォント数を返す
        $remaining = count($missing_fonts);
        
        if ($remaining === 0) {
            delete_option('amig_missing_fonts');
            wp_send_json_success(array('completed' => true, 'remaining' => 0));
        } else {
            wp_send_json_success(array('completed' => false, 'remaining' => $remaining));
        }
    }
    
    /**
     * ライセンス画面でフォント状態を表示
     */
    public function display_font_status() {
        $missing_fonts = get_option('amig_missing_fonts', array());
        
        ?>
        <!-- フォント状態セクション -->
        <div class="postbox" style="border: 1px solid <?php echo empty($missing_fonts) ? '#c6e1c6' : '#f0d9b5'; ?>; border-left: 4px solid <?php echo empty($missing_fonts) ? '#46b450' : '#f0b849'; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #f0f0f1;">
                <div style="width: 26px; height: 26px; border-radius: 50%; background: <?php echo empty($missing_fonts) ? 'linear-gradient(135deg, #46b450 0%, #2c7d2f 100%)' : 'linear-gradient(135deg, #f0b849 0%, #d97706 100%)'; ?>; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                    <span style="font-size: 12px; color: white;">
                        <?php echo empty($missing_fonts) ? '✓' : '📥'; ?>
                    </span>
                </div>
                <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;">
                    フォントファイル状態
                </h2>
            </div>
            <div class="inside" style="padding: 20px;margin: 0;">
                <?php if (empty($missing_fonts)): ?>
                    <!-- 成功状態 -->
                    <div style="background: #f0f6f0; border: 1px solid #c6e1c6; border-radius: 4px; padding: 16px; margin-bottom: 12px;">
                        <p style="margin: 0 0 8px 0; color: #2c7d2f; font-size: 15px; font-weight: 600;">
                            すべてのフォントファイルが正常にインストールされています
                        </p>
                        <p style="margin: 0; color: #50575e; font-size: 13px;">
                            インストール済み: <strong><?php echo count($this->required_fonts); ?>個</strong>のフォントファイル
                        </p>
                    </div>
                    
                    <div style="background: #f6f7f7; border-radius: 3px; padding: 12px 16px;">
                        <p style="margin: 0; color: #50575e; font-size: 13px; line-height: 1.6;">
                            <span class="dashicons dashicons-info" style="color: #2271b1; vertical-align: middle;"></span>
                            日本語フォントが利用可能です。画像生成の準備が整っています。
                        </p>
                    </div>
                <?php else: ?>
                    <!-- 警告状態 -->
                    <div style="background: #fff3cd; border: 1px solid #f0b849; border-radius: 4px; padding: 16px; margin-bottom: 16px;">
                        <p style="margin: 0 0 8px 0; color: #856404; font-size: 15px; font-weight: 600;">
                            <?php echo count($missing_fonts); ?>個のフォントファイルが不足しています
                        </p>
                        <p style="margin: 0; color: #856404; font-size: 13px; line-height: 1.6;">
                            プラグインを正常に動作させるには、日本語フォントファイルが必要です。
                        </p>
                    </div>
                    
                    <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #1d2327; font-weight: 600;">
                        不足しているフォント
                    </h4>
                    <ul style="margin: 0 0 20px 0; padding-left: 24px; list-style: none;">
                        <?php foreach ($missing_fonts as $font): ?>
                            <li style="padding: 8px 12px; margin-bottom: 6px; background: #f6f7f7; border-left: 3px solid #d63638; border-radius: 3px; font-size: 13px; color: #50575e;">
                                <span class="dashicons dashicons-warning" style="color: #d63638; font-size: 16px; vertical-align: middle; margin-right: 6px;"></span>
                                <code style="background: white; padding: 2px 6px; border-radius: 2px; font-size: 12px;"><?php echo esc_html($font); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div style="background: #f6f7f7; border-radius: 4px; padding: 16px; margin-bottom: 16px;">
                        <p style="margin: 0 0 12px 0; color: #1d2327; font-size: 13px; line-height: 1.6;">
                            <span class="dashicons dashicons-info" style="color: #2271b1; vertical-align: middle;"></span>
                            下のボタンをクリックすると、不足しているフォントファイルを自動的にダウンロードしてインストールします。
                        </p>
                        <button type="button" class="button button-primary" id="amig-download-fonts" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            フォントファイルをダウンロード (<?php echo count($missing_fonts); ?>個)
                        </button>
                        <span id="amig-download-progress" style="display: none; margin-left: 12px; color: #2271b1; font-size: 13px; font-weight: 500;">
                            <span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; vertical-align: middle;"></span>
                            ダウンロード中... <span id="amig-download-count">0</span>/<?php echo count($missing_fonts); ?>
                        </span>
                    </div>
                    
                    <style>
                        @keyframes rotation {
                            from { transform: rotate(0deg); }
                            to { transform: rotate(359deg); }
                        }
                    </style>
                    
                    <script>
                    document.getElementById('amig-download-fonts').addEventListener('click', function() {
                        var button = this;
                        var progress = document.getElementById('amig-download-progress');
                        var countSpan = document.getElementById('amig-download-count');
                        var totalFonts = <?php echo count($missing_fonts); ?>;
                        
                        button.style.display = 'none';
                        progress.style.display = 'inline';
                        
                        var downloadedCount = 0;
                        var errors = [];
                        
                        function downloadNextFont() {
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', ajaxurl);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.timeout = 120000; // 2分
                            
                            xhr.ontimeout = function() {
                                errors.push('タイムアウト');
                                finishDownload();
                            };
                            
                            xhr.onerror = function() {
                                errors.push('ネットワークエラー');
                                finishDownload();
                            };
                            
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4) {
                                    if (xhr.status === 200) {
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            
                                            if (response.success) {
                                                downloadedCount++;
                                                countSpan.textContent = downloadedCount;
                                                
                                                // 次のフォントをダウンロード
                                                if (downloadedCount < totalFonts) {
                                                    setTimeout(downloadNextFont, 100); // 100ms待機してから次へ
                                                } else {
                                                    // 全て完了
                                                    finishDownload(true);
                                                }
                                            } else {
                                                errors.push(response.data || 'ダウンロード失敗');
                                                finishDownload();
                                            }
                                        } catch (e) {
                                            errors.push('不正な応答');
                                            finishDownload();
                                        }
                                    } else {
                                        errors.push('HTTP ' + xhr.status);
                                        finishDownload();
                                    }
                                }
                            };
                            
                            xhr.send('action=amig_download_single_font&_ajax_nonce=' + '<?php echo wp_create_nonce('amig_download_fonts'); ?>');
                        }
                        
                        function finishDownload(success) {
                            if (success) {
                                progress.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle;"></span> <span style="color: #2c7d2f;">ダウンロード完了！（' + downloadedCount + '個）ページを再読み込みしています...</span>';
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                var errorMsg = errors.length > 0 ? errors.join(', ') : 'エラーが発生しました';
                                progress.innerHTML = '<span class="dashicons dashicons-dismiss" style="color: #d63638; vertical-align: middle;"></span> <span style="color: #d63638;">エラー: ' + errorMsg + ' (' + downloadedCount + '/' + totalFonts + '個完了)</span>';
                                button.style.display = 'inline-flex';
                            }
                        }
                        
                        // 最初のフォントをダウンロード開始
                        downloadNextFont();
                    });
                    </script>
                <?php endif; ?>
                
                <h4>フォントについて</h4>
                <p>
                    このプラグインでは、美しい日本語テキスト画像を生成するために、
                    Google Fontsの「Noto Sans JP」フォントファミリーを使用しています。
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * プラグイン有効化時のフォントディレクトリ作成とチェック
     */
    public static function on_plugin_activation() {
        $fonts_dir = AMIG_PLUGIN_DIR . 'fonts/';
        
        // フォントディレクトリを作成
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // フォント不足チェックを実行
        $instance = self::get_instance();
        $instance->check_fonts_and_download();
    }
    
    /**
     * 必要なフォントのリストを取得
     */
    public function get_required_fonts() {
        return $this->required_fonts;
    }
    
    /**
     * フォントファイルのパスを取得
     */
    public function get_font_path($font_name) {
        $fonts_dir = $this->get_fonts_dir();
        $font_path = $fonts_dir . $font_name;
        
        if (file_exists($font_path)) {
            return $font_path;
        }
        
        return false;
    }
    
    /**
     * すべてのフォントが利用可能かチェック
     */
    public function are_all_fonts_available() {
        $missing_fonts = get_option('amig_missing_fonts', array());
        return empty($missing_fonts);
    }
}
} // end class_exists Automagic_Font_Manager

// フォントマネージャーを初期化
add_action('plugins_loaded', array('Automagic_Font_Manager', 'get_instance'));