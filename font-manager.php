<?php
/**
 * Automagic Image Generate - Font Manager
 * 
 * フォントファイルの管理とダウンロード機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセスを防止
}

class Automagic_Font_Manager {
    
    private static $instance = null;
    
    /**
     * 必要なフォントファイルのリスト
     */
    private $required_fonts = array(
        'NotoSansJP-Regular.ttf',
        'NotoSansJP-Bold.ttf',
        'NotoSansJP-Medium.ttf',
        'NotoSansJP-Light.ttf',
        'NotoSansJP-Black.ttf',
        'NotoSansJP-ExtraBold.ttf',
        'NotoSansJP-ExtraLight.ttf',
        'NotoSansJP-SemiBold.ttf',
        'NotoSansJP-Thin.ttf'
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
     * ライセンス画面でフォント状態を表示
     */
    public function display_font_status() {
        $missing_fonts = get_option('amig_missing_fonts', array());
        
        ?>
        <!-- フォント状態セクション -->
        <div class="postbox">
            <h2 class="hndle">📥 フォントファイル状態</h2>
            <div class="inside">
                <?php if (empty($missing_fonts)): ?>
                    <p>✅ <strong>すべてのフォントファイルが正常にインストールされています。</strong></p>
                    <p>インストール済み: <?php echo count($this->required_fonts); ?>個のフォントファイル</p>
                <?php else: ?>
                    <p>⚠️ <strong><?php echo count($missing_fonts); ?>個のフォントファイルが不足しています。</strong></p>
                    <p>プラグインを正常に動作させるには、日本語フォントファイルが必要です。</p>
                    
                    <h4>不足しているフォント:</h4>
                    <ul>
                        <?php foreach ($missing_fonts as $font): ?>
                            <li><?php echo esc_html($font); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p>
                        <button type="button" class="button button-primary" id="amig-download-fonts">
                            フォントファイルをダウンロード
                        </button>
                        <span id="amig-download-progress" style="display: none; margin-left: 10px;">
                            ダウンロード中... <span id="amig-download-count">0</span>/<?php echo count($missing_fonts); ?>
                        </span>
                    </p>
                    
                    <script>
                    document.getElementById('amig-download-fonts').addEventListener('click', function() {
                        var button = this;
                        var progress = document.getElementById('amig-download-progress');
                        
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
                                        button.style.display = 'inline-block';
                                    }
                                } catch (e) {
                                    progress.innerHTML = '❌ エラー: 不正な応答';
                                    button.style.display = 'inline-block';
                                }
                            }
                        };
                        
                        xhr.send('action=amig_download_fonts&_ajax_nonce=' + '<?php echo wp_create_nonce('amig_download_fonts'); ?>');
                    });
                    </script>
                <?php endif; ?>
                
                <h4>フォントについて:</h4>
                <p>
                    このプラグインでは、美しい日本語テキスト画像を生成するために、
                    Google Fontsの「Noto Sans JP」フォントファミリーを使用しています。
                    フォントファイルは有効なライセンスを持つユーザーのみダウンロード可能です。
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

// フォントマネージャーを初期化
add_action('plugins_loaded', array('Automagic_Font_Manager', 'get_instance'));