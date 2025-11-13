<?php
/**
 * Automagic Image Generate - Update Manager
 * 
 * Plugin Update Checkerを使用したアップデート機能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセスを防止
}

// Plugin Update Checkerライブラリを読み込み
require_once dirname(__FILE__) . '/lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Automagic_Image_Generate_Updater {
    
    private static $instance = null;
    
    /**
     * Update Checkerインスタンス
     */
    private $update_checker;
    
    /**
     * ライセンスキー
     */
    private $license_key;
    
    /**
     * メタデータURL（GitHubまたは独自サーバー）
     */
    private $metadata_url;
    
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
        // ライセンスキーを取得
        $this->license_key = get_option('amig_license_key', '');
        
        // メタデータURL（変更してください）
        // GitHub使用例: 'https://github.com/username/repository/'
        // 独自サーバー例: 'https://tzweb.noor.jp/plugins/automagic-image-generate/info.json'
        $this->metadata_url = 'https://tzweb.noor.jp/plugins/automagic-image-generate/info.json';
        
        // Plugin Update Checkerを初期化
        $this->init_update_checker();
        
        // 管理画面のフックを登録
        add_action('admin_init', array($this, 'register_license_settings'));
        add_action('admin_menu', array($this, 'add_license_page'), 99);
        add_action('admin_notices', array($this, 'license_notice'));
    }
    
    /**
     * Update Checkerを初期化
     */
    private function init_update_checker() {
        $plugin_file = dirname(__FILE__) . '/automagic-image-generate.php';
        
        // Plugin Update Checkerのインスタンスを作成
        $this->update_checker = PucFactory::buildUpdateChecker(
            $this->metadata_url,
            $plugin_file,
            'automagic-image-generate'
        );
        
        // ライセンスキーをクエリパラメータとして追加
        if (!empty($this->license_key)) {
            $this->update_checker->addQueryArgFilter(function($queryArgs) {
                $queryArgs['license_key'] = $this->license_key;
                $queryArgs['site_url'] = get_site_url();
                $queryArgs['license_status'] = get_option('amig_license_status', 'inactive');
                return $queryArgs;
            });
        }
        
        // カスタムリクエストヘッダーを追加（オプション）
        $this->update_checker->addHttpRequestArgFilter(function($args) {
            $args['headers']['X-License-Key'] = $this->license_key;
            $args['headers']['X-License-Status'] = get_option('amig_license_status', 'inactive');
            return $args;
        });
        
        // アップデート情報を取得した後のフィルター（ライセンスチェック）
        $this->update_checker->addFilter('request_info_result', array($this, 'filter_update_by_license'));
    }
    
    /**
     * ライセンス状態に応じてアップデート情報をフィルター
     */
    public function filter_update_by_license($pluginInfo, $result = null) {
        $license_status = get_option('amig_license_status', 'inactive');
        
        // ライセンスが無効な場合
        if ($license_status !== 'active' || empty($this->license_key)) {
            // アップデート情報は表示するが、ダウンロードURLを削除
            if ($pluginInfo) {
                $pluginInfo->download_url = '';
                $pluginInfo->upgrade_notice = '⚠️ アップデートをダウンロードするには有効なライセンスが必要です。設定 > ライセンス からライセンスキーを入力してください。';
            }
            return $pluginInfo;
        }
        
        // ライセンスの有効期限をチェック
        $license_expires = get_option('amig_license_expires', '');
        if (!empty($license_expires) && strtotime($license_expires) < time()) {
            if ($pluginInfo) {
                $pluginInfo->download_url = '';
                $pluginInfo->upgrade_notice = '⚠️ ライセンスの有効期限が切れています（期限: ' . $license_expires . '）。ライセンスを更新してください。';
            }
            return $pluginInfo;
        }
        
        return $pluginInfo;
    }
    
    /**
     * ライセンス設定を登録
     */
    public function register_license_settings() {
        register_setting('amig_license_settings', 'amig_license_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
    }
    
    /**
     * ライセンス通知を表示
     */
    public function license_notice() {
        $license_status = get_option('amig_license_status', 'inactive');
        
        // 更新画面でのみ表示
        $screen = get_current_screen();
        if (!$screen || ($screen->id !== 'update-core' && $screen->id !== 'plugins')) {
            return;
        }
        
        // ライセンスが無効な場合
        if ($license_status !== 'active' || empty($this->license_key)) {
            // アップデートが利用可能かチェック
            if ($this->update_checker) {
                $update = $this->update_checker->getUpdate();
                if ($update) {
                    ?>
                    <div class="notice notice-warning">
                        <p>
                            <strong>Automagic Image Generate:</strong> 
                            新しいバージョン <?php echo esc_html($update->version); ?> が利用可能ですが、アップデートするには有効なライセンスが必要です。
                            <a href="<?php echo admin_url('options-general.php?page=amig-license'); ?>">ライセンスを有効化</a>
                        </p>
                    </div>
                    <?php
                }
            }
            return;
        }
        
        // ライセンスの有効期限をチェック
        $license_expires = get_option('amig_license_expires', '');
        if (!empty($license_expires)) {
            $expires_timestamp = strtotime($license_expires);
            $days_until_expiry = floor(($expires_timestamp - time()) / (60 * 60 * 24));
            
            // 期限切れ
            if ($expires_timestamp < time()) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Automagic Image Generate:</strong> 
                        ライセンスの有効期限が切れています（期限: <?php echo esc_html($license_expires); ?>）。
                        アップデートを受け取るにはライセンスを更新してください。
                    </p>
                </div>
                <?php
            }
            // 30日以内に期限切れ
            elseif ($days_until_expiry <= 30 && $days_until_expiry > 0) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>Automagic Image Generate:</strong> 
                        ライセンスの有効期限まで残り <?php echo esc_html($days_until_expiry); ?> 日です（期限: <?php echo esc_html($license_expires); ?>）。
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * ライセンス設定ページを追加
     */
    public function add_license_page() {
        add_submenu_page(
            'options-general.php',
            'ライセンス設定',
            'ライセンス',
            'manage_options',
            'amig-license',
            array($this, 'license_page')
        );
    }
    
    /**
     * ライセンス設定ページの表示
     */
    public function license_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // ライセンスを有効化
        if (isset($_POST['amig_activate_license']) && check_admin_referer('amig_license_activate')) {
            $license_key = sanitize_text_field($_POST['amig_license_key']);
            
            // ライセンスキーが空の場合はエラー
            if (empty($license_key)) {
                echo '<div class="notice notice-error"><p>ライセンスキーを入力してください。</p></div>';
            } else {
                $result = $this->activate_license($license_key);
                
                if ($result['success']) {
                    update_option('amig_license_key', $license_key);
                    update_option('amig_license_status', 'active');
                    update_option('amig_license_expires', $result['expires']);
                    
                    // キャッシュをクリアしてアップデート情報を即座に更新
                    $this->clear_update_cache();
                    
                    echo '<div class="notice notice-success"><p>ライセンスが有効化されました。アップデート情報を更新しています...</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                }
            }
        }
        
        // ライセンスを無効化
        if (isset($_POST['amig_deactivate_license']) && check_admin_referer('amig_license_deactivate')) {
            // ローカルでライセンス情報を削除（サーバーリクエストなし）
            delete_option('amig_license_key');
            delete_option('amig_license_status');
            delete_option('amig_license_expires');
            $this->license_key = '';
            
            // Update Checkerを再初期化
            $this->init_update_checker();
            
            // キャッシュをクリアしてアップデート情報を即座に更新
            $this->clear_update_cache();
            
            echo '<div class="notice notice-success"><p>ライセンスが無効化されました。</p></div>';
        }
        
        $license_status = get_option('amig_license_status', 'inactive');
        $license_expires = get_option('amig_license_expires', '');
        
        ?>
        <div class="wrap">
            <h1>Automagic Image Generate - ライセンス設定</h1>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>ライセンスキー</h2>
                
                <?php if ($license_status === 'active'): ?>
                    <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
                        <strong style="color: #155724;">✓ ライセンスは有効です</strong>
                        <?php if ($license_expires): ?>
                            <p style="margin: 5px 0 0 0; color: #155724;">有効期限: <?php echo esc_html($license_expires); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post">
                        <?php wp_nonce_field('amig_license_deactivate'); ?>
                        <p>
                            <strong>現在のライセンスキー:</strong><br>
                            <code style="background: #f5f5f5; padding: 5px 10px; display: inline-block; margin-top: 5px;">
                                <?php echo esc_html($this->mask_license_key($this->license_key)); ?>
                            </code>
                        </p>
                        <p>
                            <button type="submit" name="amig_deactivate_license" class="button button-secondary">
                                ライセンスを無効化
                            </button>
                        </p>
                    </form>
                    
                <?php else: ?>
                    <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
                        <strong style="color: #856404;">⚠ ライセンスが無効です</strong>
                        <p style="margin: 5px 0 0 0; color: #856404;">アップデートを受け取るにはライセンスキーを入力してください。</p>
                    </div>
                    
                    <form method="post">
                        <?php wp_nonce_field('amig_license_activate'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="amig_license_key">ライセンスキー</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="amig_license_key" 
                                           name="amig_license_key" 
                                           value="<?php echo esc_attr($this->license_key); ?>" 
                                           class="regular-text"
                                           placeholder="XXXX-XXXX-XXXX-XXXX">
                                    <p class="description">
                                        購入時に送信されたライセンスキーを入力してください。
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" name="amig_activate_license" class="button button-primary">
                                ライセンスを有効化
                            </button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * ライセンスキーをマスク
     */
    private function mask_license_key($key) {
        if (strlen($key) < 8) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
    
    /**
     * ライセンスを有効化
     */
    private function activate_license($license_key) {
        // メタデータURLにライセンス検証リクエストを送信
        $verify_url = str_replace('/info.json', '/verify.php', $this->metadata_url);
        
        $response = wp_remote_post($verify_url, array(
            'timeout' => 15,
            'body' => array(
                'action' => 'activate',
                'license_key' => $license_key,
                'site_url' => get_site_url(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => phpversion()
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'サーバーに接続できませんでした: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // 404エラーの場合はサーバー未設定エラー
        if ($response_code === 404) {
            return array(
                'success' => false,
                'message' => 'ライセンス認証サーバーが見つかりません。サーバー管理者にお問い合わせください。'
            );
        }
        
        $body = json_decode($response_body, true);
        
        if (!$body) {
            return array(
                'success' => false,
                'message' => '無効な応答を受信しました'
            );
        }
        
        // ライセンスが有効化されたらUpdate Checkerを再初期化
        if (isset($body['success']) && $body['success']) {
            $this->license_key = $license_key;
            $this->init_update_checker();
        }
        
        return $body;
    }
    
    /**
     * 手動でアップデートをチェック
     */
    public function manual_check_update() {
        if ($this->update_checker) {
            $update = $this->update_checker->checkForUpdates();
            return $update;
        }
        return null;
    }
    
    /**
     * アップデートキャッシュをクリア
     */
    public function clear_update_cache() {
        // WordPress標準のアップデートキャッシュをクリア
        delete_site_transient('update_plugins');
        
        // Plugin Update Checkerのキャッシュをクリア
        $plugin_slug = 'automagic-image-generate';
        $cache_keys = array(
            'puc_update_checker-' . $plugin_slug,
            'puc_request_info-' . $plugin_slug,
            'puc_cached_update-' . $plugin_slug,
            'external_updates-' . $plugin_slug
        );
        
        foreach ($cache_keys as $key) {
            delete_site_transient($key);
            delete_transient($key);
            delete_option($key);
        }
        
        // カスタムキャッシュもクリア
        delete_transient('amig_update_info');
        
        // Update Checkerに強制的に最新情報を取得させる
        if ($this->update_checker) {
            // ライセンスキーとステータスを再設定してから強制チェック
            $this->license_key = get_option('amig_license_key', '');
            $this->init_update_checker();
            $this->update_checker->checkForUpdates();
        }
        
        return true;
    }
}

// アップデーターを初期化
add_action('plugins_loaded', array('Automagic_Image_Generate_Updater', 'get_instance'));
