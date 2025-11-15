<?php
/**
 * Universal Plugin Update Manager
 * 
 * 汎用的なPlugin Update Checkerを使用したアップデート機能
 * 定数設定により複数のプラグインで使い回し可能
 */

if (!defined('ABSPATH')) {
    exit; // 直接アクセスを防止
}

// デフォルト設定（定数が定義されていない場合のフォールバック）
if (!defined('PUM_PLUGIN_PREFIX')) {
    define('PUM_PLUGIN_PREFIX', 'amig');
}

if (!defined('PUM_PLUGIN_NAME')) {
    define('PUM_PLUGIN_NAME', 'Automagic Image Generate');
}

if (!defined('PUM_PLUGIN_SLUG')) {
    define('PUM_PLUGIN_SLUG', 'automagic-image-generate');
}

if (!defined('PUM_LICENSE_SERVER_URL')) {
    define('PUM_LICENSE_SERVER_URL', 'https://sokulabo.com/products/wp-json/sokulabo/v1/license/verify');
}

if (!defined('PUM_LICENSE_PAGE_URL')) {
    define('PUM_LICENSE_PAGE_URL', 'https://sokulabo.com/products/');
}

if (!defined('PUM_UPDATE_SERVER_URL')) {
    define('PUM_UPDATE_SERVER_URL', 'https://sokulabo.com/products/plugins/' . PUM_PLUGIN_SLUG . '/update-info.json');
}

if (!defined('PUM_LICENSE_PAGE_SLUG')) {
    define('PUM_LICENSE_PAGE_SLUG', PUM_PLUGIN_PREFIX . '-license');
}

if (!defined('PUM_PLUGIN_FILE')) {
    define('PUM_PLUGIN_FILE', dirname(__FILE__) . '/' . PUM_PLUGIN_SLUG . '.php');
}

// Plugin Update Checkerライブラリを読み込み
require_once dirname(__FILE__) . '/lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// クラスの重複定義を防ぐ
if (!class_exists('Universal_Plugin_Updater')) {

class Universal_Plugin_Updater {
    
    /**
     * プラグインごとのインスタンスを保持
     * @var array
     */
    private static $instances = array();
    
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
     * プラグイン設定
     */
    private $config;
    
    /**
     * プラグイン固有のインスタンスを取得（プラグインスラッグで識別）
     * 
     * @param string $plugin_slug オプション。指定しない場合は PUM_PLUGIN_SLUG を使用
     * @return Universal_Plugin_Updater
     */
    public static function get_instance($plugin_slug = null) {
        // プラグインスラッグが指定されていない場合は定数から取得
        if ($plugin_slug === null) {
            $plugin_slug = defined('PUM_PLUGIN_SLUG') ? PUM_PLUGIN_SLUG : 'automagic-image-generate';
        }
        
        // プラグインごとにインスタンスを作成
        if (!isset(self::$instances[$plugin_slug])) {
            self::$instances[$plugin_slug] = new self();
        }
        
        return self::$instances[$plugin_slug];
    }
    
    /**
     * 設定を初期化
     */
    private function init_config() {
        $this->config = array(
            'prefix' => PUM_PLUGIN_PREFIX,
            'plugin_prefix' => PUM_PLUGIN_PREFIX,
            'plugin_name' => PUM_PLUGIN_NAME,
            'plugin_slug' => PUM_PLUGIN_SLUG,
            'slug' => PUM_PLUGIN_SLUG,
            'plugin_file' => PUM_PLUGIN_FILE,
            'license_server_url' => PUM_LICENSE_SERVER_URL,
            'license_page_url' => PUM_LICENSE_PAGE_URL,
            'update_server_url' => PUM_UPDATE_SERVER_URL,
            'license_page_slug' => PUM_LICENSE_PAGE_SLUG,
            'license_key_option' => PUM_PLUGIN_PREFIX . '_license_key',
            'license_status_option' => PUM_PLUGIN_PREFIX . '_license_status',
            'license_expires_option' => PUM_PLUGIN_PREFIX . '_license_expires',
            'license_last_check_option' => PUM_PLUGIN_PREFIX . '_license_last_check',
            'license_settings_group' => PUM_PLUGIN_PREFIX . '_license_settings',
            'nonce_action' => PUM_PLUGIN_PREFIX . '_license_nonce',
            'nonce_activate_action' => PUM_PLUGIN_PREFIX . '_license_activate',
            'nonce_deactivate_action' => PUM_PLUGIN_PREFIX . '_license_deactivate',
            'nonce_check_action' => PUM_PLUGIN_PREFIX . '_license_check',
        );
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        // 設定を初期化
        $this->init_config();
        
        // ライセンスキーを取得
        $this->license_key = get_option($this->config['license_key_option'], '');
        
        // アップデート情報のURL
        $this->metadata_url = $this->config['update_server_url'];
        
        // Plugin Update Checkerを初期化
        $this->init_update_checker();
        
        // 管理画面のフックを登録
        add_action('admin_init', array($this, 'register_license_settings'));
        add_action('admin_menu', array($this, 'add_license_page'), 99);
        add_action('admin_notices', array($this, 'license_notice'));
        
        // ライセンス状態を定期的に確認（1日1回）
        add_action('admin_init', array($this, 'check_license_validity'));
    }
    
    /**
     * Update Checkerを初期化
     */
    private function init_update_checker() {
        // Plugin Update Checkerのインスタンスを作成
        $this->update_checker = PucFactory::buildUpdateChecker(
            $this->metadata_url,
            $this->config['plugin_file'],
            $this->config['plugin_slug']
        );
        
        // ライセンスキーをクエリパラメータとして追加
        if (!empty($this->license_key)) {
            $this->update_checker->addQueryArgFilter(function($queryArgs) {
                $queryArgs['license_key'] = $this->license_key;
                $queryArgs['site_url'] = get_site_url();
                $queryArgs['license_status'] = get_option($this->config['license_status_option'], 'inactive');
                return $queryArgs;
            });
        }
        
        // カスタムリクエストヘッダーを追加（オプション）
        $this->update_checker->addHttpRequestArgFilter(function($args) {
            $args['headers']['X-License-Key'] = $this->license_key;
            $args['headers']['X-License-Status'] = get_option($this->config['license_status_option'], 'inactive');
            return $args;
        });
        
        // ライセンスキーをクエリパラメータとして追加
        if (!empty($this->license_key)) {
            $config = $this->config; // クロージャ内で使用するため
            $this->update_checker->addQueryArgFilter(function($queryArgs) use ($config) {
                $queryArgs['license_key'] = $this->license_key;
                $queryArgs['site_url'] = get_site_url();
                $queryArgs['license_status'] = get_option($config['license_status_option'], 'inactive');
                return $queryArgs;
            });
        }
        
        // カスタムリクエストヘッダーを追加（オプション）
        $config = $this->config; // クロージャ内で使用するため
        $this->update_checker->addHttpRequestArgFilter(function($args) use ($config) {
            $args['headers']['X-License-Key'] = $this->license_key;
            $args['headers']['X-License-Status'] = get_option($config['license_status_option'], 'inactive');
            return $args;
        });
        
        // アップデート情報を取得した後のフィルター（ライセンスチェック）
        $this->update_checker->addFilter('request_info_result', array($this, 'filter_update_by_license'));
    }
    
    /**
     * ライセンス状態に応じてアップデート情報をフィルター
     */
    public function filter_update_by_license($pluginInfo, $result = null) {
        $license_status = get_option($this->config['license_status_option'], 'inactive');
        
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
        $license_expires = get_option($this->config['license_expires_option'], '');
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
        register_setting($this->config['license_settings_group'], $this->config['license_key_option'], array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
    }
    
    /**
     * ライセンス通知を表示
     */
    public function license_notice() {
        $license_status = get_option($this->config['license_status_option'], 'inactive');
        
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
                            <strong><?php echo esc_html($this->config['plugin_name']); ?>:</strong> 
                            新しいバージョン <?php echo esc_html($update->version); ?> が利用可能ですが、アップデートするには有効なライセンスが必要です。
                            <a href="<?php echo admin_url('options-general.php?page=' . $this->config['license_page_slug']); ?>">ライセンスを有効化</a>
                        </p>
                    </div>
                    <?php
                }
            }
            return;
        }
        
        // ライセンスの有効期限をチェック
        $license_expires = get_option($this->config['license_expires_option'], '');
        if (!empty($license_expires)) {
            $expires_timestamp = strtotime($license_expires);
            $days_until_expiry = floor(($expires_timestamp - time()) / (60 * 60 * 24));
            
            // 期限切れ
            if ($expires_timestamp < time()) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php echo esc_html($this->config['plugin_name']); ?>:</strong> 
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
                        <strong><?php echo esc_html($this->config['plugin_name']); ?>:</strong> 
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
            $this->config['plugin_name'] . ' ライセンス設定',
            'ライセンス',
            'manage_options',
            $this->config['license_page_slug'],
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
        if (isset($_POST[$this->config['prefix'] . '_activate_license']) && check_admin_referer($this->config['nonce_activate_action'])) {
            $license_key = sanitize_text_field($_POST[$this->config['prefix'] . '_license_key']);
            
            // ライセンスキーが空の場合はエラー
            if (empty($license_key)) {
                echo '<div class="notice notice-error"><p>ライセンスキーを入力してください。</p></div>';
            } else {
                $result = $this->activate_license($license_key);
                
                if ($result['success']) {
                    update_option($this->config['license_key_option'], $license_key);
                    update_option($this->config['license_status_option'], 'active');
                    update_option($this->config['license_expires_option'], $result['expires']);
                    
                    // キャッシュをクリアしてアップデート情報を即座に更新
                    $this->clear_update_cache();
                    
                    echo '<div class="notice notice-success"><p>ライセンスが有効化されました。アップデート情報を更新しています...</p></div>';
                } else {
                    $error_message = esc_html($result['message']);
                    
                    // 詳細情報がある場合は追加表示
                    if (isset($result['registered_sites']) && isset($result['attempted_site'])) {
                        $error_message .= '<br><br><strong>詳細:</strong><br>';
                        $error_message .= '有効化を試みたサイト: ' . esc_html($result['attempted_site']) . '<br>';
                        $error_message .= '既に登録されているサイト:<br>';
                        foreach ($result['registered_sites'] as $site) {
                            $error_message .= '・' . esc_html($site) . '<br>';
                        }
                        $error_message .= '<br>このライセンスを使用するには、マイページで既存のサイトを削除してから再度お試しください。';
                    }
                    
                    echo '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
                }
            }
        }
        
        // ライセンスを無効化
        if (isset($_POST[$this->config['prefix'] . '_deactivate_license']) && check_admin_referer($this->config['nonce_deactivate_action'])) {
            // サーバーに無効化を通知
            $current_license = get_option($this->config['license_key_option'], '');
            $site_url = get_site_url();
            
            if (!empty($current_license)) {
                $api_url = $this->config['license_server_url'];
                
                $response = wp_remote_post($api_url, array(
                    'body' => json_encode(array(
                        'license_key' => $current_license,
                        'site_url' => $site_url,
                        'action' => 'deactivate'
                    )),
                    'headers' => array('Content-Type' => 'application/json'),
                    'timeout' => 15,
                ));
                
                $deactivation_success = false;
                if (is_wp_error($response)) {
                    echo '<div class="notice notice-warning"><p>サーバー側の無効化に失敗しました: ' . esc_html($response->get_error_message()) . '</p></div>';
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if ($response_code === 200 && $data && isset($data['success'])) {
                        if ($data['success']) {
                            $deactivation_success = true;
                        } else {
                            echo '<div class="notice notice-warning"><p>サーバー側の無効化に失敗しました: ' . (isset($data['message']) ? esc_html($data['message']) : '不明なエラー') . '</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-warning"><p>サーバー側の無効化に失敗しました (HTTP ' . $response_code . ')</p></div>';
                    }
                }
            }
            
            // ローカルでライセンス情報を削除
            delete_option($this->config['license_key_option']);
            delete_option($this->config['license_status_option']);
            delete_option($this->config['license_expires_option']);
            $this->license_key = '';
            
            // Update Checkerを再初期化
            $this->init_update_checker();
            
            // キャッシュをクリアしてアップデート情報を即座に更新
            $this->clear_update_cache();
            
            if (isset($deactivation_success) && $deactivation_success) {
                echo '<div class="notice notice-success"><p>✓ ライセンスが無効化されました（サーバー側も正常に解除されました）</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>ライセンスがローカルで無効化されました。</p></div>';
            }
        }
        
        // ライセンス状態を今すぐ確認
        if (isset($_POST[$this->config['prefix'] . '_check_license_now']) && check_admin_referer($this->config['nonce_check_action'])) {
            $api_url = $this->config['license_server_url'];
            
            $response = wp_remote_post($api_url, array(
                'body' => json_encode(array(
                    'action' => 'check',
                    'license_key' => $this->license_key,
                    'site_url' => get_site_url(),
                )),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 15,
            ));
            
            if (is_wp_error($response)) {
                echo '<div class="notice notice-error"><p>サーバーに接続できませんでした: ' . esc_html($response->get_error_message()) . '</p></div>';
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $body = json_decode($response_body, true);
                
                if ($body && isset($body['success'])) {
                    if ($body['success']) {
                        // 有効期限情報を更新
                        if (isset($body['expires'])) {
                            update_option($this->config['license_expires_option'], $body['expires']);
                        }
                        update_option($this->config['license_last_check_option'], time());
                        echo '<div class="notice notice-success"><p>✓ ライセンスは有効です。' . (isset($body['message']) ? ' ' . esc_html($body['message']) : '') . '</p></div>';
                    } else {
                        // ライセンスが無効になっている
                        delete_option($this->config['license_status_option']);
                        delete_option($this->config['license_expires_option']);
                        $this->clear_update_cache();
                        echo '<div class="notice notice-error"><p>✗ ' . esc_html($body['message'] ?? 'ライセンスが無効です') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>サーバーから無効な応答を受信しました。</p></div>';
                }
            }
        }

        
        $license_status = get_option($this->config['license_status_option'], 'inactive');
        $license_expires = get_option($this->config['license_expires_option'], '');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->config['plugin_name']); ?> - ライセンス設定</h1>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>ライセンスキー</h2>
                
                <?php if ($license_status === 'active'): ?>
                    <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
                        <strong style="color: #155724;">✓ ライセンスは有効です</strong>
                        <?php if ($license_expires): ?>
                            <p style="margin: 5px 0 0 0; color: #155724;">有効期限: <?php echo esc_html($license_expires); ?></p>
                        <?php endif; ?>
                        <?php 
                        $last_check = get_option($this->config['license_last_check_option'], 0);
                        if ($last_check > 0): 
                            $hours_since_check = (time() - $last_check) / 3600;
                        ?>
                            <p style="margin: 5px 0 0 0; color: #155724; font-size: 12px;">
                                最終確認: <?php echo human_time_diff($last_check, time()); ?>前
                                <?php if ($hours_since_check > 24): ?>
                                    <span style="color: #856404;">（24時間以上経過しています。「今すぐ確認」を推奨）</span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post" style="margin-bottom: 15px;">
                        <?php wp_nonce_field($this->config['nonce_deactivate_action']); ?>
                        <p>
                            <strong>現在のライセンスキー:</strong><br>
                            <code style="background: #f5f5f5; padding: 5px 10px; display: inline-block; margin-top: 5px;">
                                <?php echo esc_html($this->mask_license_key($this->license_key)); ?>
                            </code>
                        </p>
                        <p>
                            <button type="submit" name="<?php echo esc_attr($this->config['prefix']); ?>_deactivate_license" class="button button-secondary">
                                ライセンスを無効化
                            </button>
                        </p>
                    </form>
                    
                    <form method="post" style="border-top: 1px solid #ddd; padding-top: 15px;">
                        <?php wp_nonce_field($this->config['nonce_check_action']); ?>
                        <p style="margin-bottom: 10px;">
                            <strong>ライセンス状態の確認</strong><br>
                            <span style="font-size: 13px; color: #666;">
                                サーバーに接続して、ライセンスが引き続き有効かどうかを確認します。
                            </span>
                        </p>
                        <p>
                            <button type="submit" name="<?php echo esc_attr($this->config['prefix']); ?>_check_license_now" class="button">
                                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> 今すぐ確認
                            </button>
                        </p>
                    </form>
                    
                <?php else: ?>
                    <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
                        <strong style="color: #856404;">⚠ ライセンスが無効です</strong>
                        <p style="margin: 5px 0 0 0; color: #856404;">アップデートを受け取るにはライセンスキーを入力してください。</p>
                        <p style="margin: 10px 0 0 0; color: #856404;">
                            ライセンスキーをお持ちでない方は、
                            <a href="<?php echo esc_url($this->config['license_page_url']); ?>" target="_blank" style="color: #0073aa; font-weight: bold;">
                                <?php echo esc_url($this->config['license_page_url']); ?>
                            </a>
                            で会員登録してマイページからライセンスキーを発行してください。
                        </p>
                    </div>
                    
                    <form method="post">
                        <?php wp_nonce_field($this->config['nonce_activate_action']); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($this->config['prefix']); ?>_license_key">ライセンスキー</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="<?php echo esc_attr($this->config['prefix']); ?>_license_key" 
                                           name="<?php echo esc_attr($this->config['prefix']); ?>_license_key" 
                                           value="<?php echo esc_attr($this->license_key); ?>" 
                                           class="regular-text"
                                           placeholder="<?php echo esc_attr(strtoupper($this->config['prefix'])); ?>-XXXX-YYYY-ZZZZ-AAAA">
                                    <p class="description">
                                        <a href="<?php echo esc_url($this->config['license_page_url']); ?>" target="_blank"><?php echo esc_url($this->config['license_page_url']); ?></a> で会員登録し、マイページから発行したライセンスキーを入力してください。
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" name="<?php echo esc_attr($this->config['prefix']); ?>_activate_license" class="button button-primary">
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
        // REST APIエンドポイント
        $api_url = $this->config['license_server_url'];
        
        $response = wp_remote_post($api_url, array(
            'body' => json_encode(array(
                'action' => 'activate',
                'license_key' => $license_key,
                'site_url' => get_site_url(),
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return array(
                'success' => false,
                'message' => 'サーバーに接続できませんでした: ' . $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // 404エラーの場合はREST API未設定エラー
        if ($response_code === 404) {
            return array(
                'success' => false,
                'message' => 'ライセンス認証APIが見つかりません (404)。' . $this->config['license_server_url'] . ' の管理者にお問い合わせください。'
            );
        }
        
        // 500エラーの場合
        if ($response_code >= 500) {
            return array(
                'success' => false,
                'message' => 'サーバーエラーが発生しました (HTTP ' . $response_code . ')。しばらくしてから再試行してください。'
            );
        }
        
        $body = json_decode($response_body, true);
        
        if (!$body) {
            return array(
                'success' => false,
                'message' => '無効な応答を受信しました。サーバーからの応答: ' . substr($response_body, 0, 100)
            );
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
        $plugin_slug = $this->config['slug'];
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
        delete_transient($this->config['prefix'] . '_update_info');
        
        // Update Checkerに強制的に最新情報を取得させる
        if ($this->update_checker) {
            // ライセンスキーとステータスを再設定してから強制チェック
            $this->license_key = get_option($this->config['license_key_option'], '');
            $this->init_update_checker();
            $this->update_checker->checkForUpdates();
        }
        
        return true;
    }
    
    /**
     * ライセンスの有効性を定期的に確認（1日1回）
     */
    public function check_license_validity() {
        // 有効なライセンスがある場合のみチェック
        $license_status = get_option($this->config['license_status_option'], 'inactive');
        if ($license_status !== 'active' || empty($this->license_key)) {
            return;
        }
        
        // 最終チェック時刻を取得
        $last_check = get_option($this->config['license_last_check_option'], 0);
        $check_interval = DAY_IN_SECONDS; // 24時間
        
        // 24時間以内にチェック済みならスキップ
        if (time() - $last_check < $check_interval) {
            return;
        }
        
        // サーバーにライセンス状態を確認
        $api_url = $this->config['license_server_url'];
        
        $response = wp_remote_post($api_url, array(
            'body' => json_encode(array(
                'action' => 'check',
                'license_key' => $this->license_key,
                'site_url' => get_site_url(),
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10,
        ));
        
        // チェック時刻を更新
        update_option($this->config['license_last_check_option'], time());
        
        if (is_wp_error($response)) {
            // ネットワークエラーは無視（サーバーダウンの可能性）
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        
        // ライセンスが無効になっている場合、ローカル情報をクリア
        if ($body && isset($body['success']) && !$body['success']) {
            delete_option($this->config['license_status_option']);
            delete_option($this->config['license_expires_option']);
            
            // デバッグログ
            
            // キャッシュをクリア
            $this->clear_update_cache();
        }
    }
}

} // end class_exists check

// 汎用アップデーターを初期化
// 注意: このファイルを使用するプラグインは、読み込み前に以下の定数を定義してください
/*
使用例:

// プラグインのメインファイルで以下の定数を定義してから読み込む
define('PUM_PLUGIN_PREFIX', 'your_prefix');           // 例: 'amig' - プラグインの接頭辞
define('PUM_PLUGIN_NAME', 'Your Plugin Name');        // 例: 'Automagic Image Generate' - プラグイン名
define('PUM_PLUGIN_SLUG', 'your-plugin-slug');        // 例: 'automagic-image-generate' - プラグインスラッグ
define('PUM_PLUGIN_FILE', __FILE__);                  // メインプラグインファイルのパス
define('PUM_LICENSE_SERVER_URL', 'https://your-server.com/wp-json/your-namespace/v1/license/verify'); // ライセンス認証APIエンドポイント
define('PUM_LICENSE_PAGE_URL', 'https://your-server.com/products/'); // ユーザー向け製品ページURL（オプション）
define('PUM_UPDATE_SERVER_URL', 'https://your-server.com/plugins/your-plugin/update-info.json'); // アップデート情報JSONのURL
define('PUM_LICENSE_PAGE_SLUG', 'your-prefix-license'); // ライセンスページのスラッグ（オプション）

require_once plugin_dir_path(__FILE__) . 'universal-plugin-updater.php';

注意事項:
- 定数を定義しない場合は、ファイル内のデフォルト値（Automagic Image Generate用）が使用されます
- PUM_LICENSE_PAGE_URL が未定義の場合、PUM_LICENSE_SERVER_URL がフォールバックとして使用されます
- PUM_LICENSE_PAGE_SLUG が未定義の場合、'{PREFIX}-license' が自動生成されます

重要な注意事項:
- 複数のプラグインで同時に使用可能（クラス名の衝突を自動回避）
- 各プラグインは独立したインスタンスで管理されます
- プラグインスラッグで識別されるため、異なるスラッグを使用してください
*/

// 自動初期化（現在のプラグインスラッグで初期化）
// 複数プラグインで使用しても問題なく動作します
add_action('plugins_loaded', array('Universal_Plugin_Updater', 'get_instance'));

// 後方互換性のため、古いクラス名でもアクセス可能にする
if (!class_exists('Automagic_Image_Generate_Updater')) {
    class_alias('Universal_Plugin_Updater', 'Automagic_Image_Generate_Updater');
}
