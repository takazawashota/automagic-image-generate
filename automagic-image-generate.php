<?php
/**
 * Plugin Name: Automagic Image Generate
 * Plugin URI: https://example.com/automagic-image-generate
 * Description: 投稿ページや固定ページ、カスタム投稿タイプのサムネイル画像をPHPで自動生成するプラグイン
 * Version: 1.0.0
 * Author: Shota Takazawa
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: automagic-image-generate
 * Requires PHP: 7.4
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数定義
define('AMIG_VERSION', '1.0.0');
define('AMIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMIG_PLUGIN_URL', plugin_dir_url(__FILE__));

// 汎用アップデーター用の設定
define('PUM_PLUGIN_PREFIX', 'amig');
define('PUM_PLUGIN_NAME', 'Automagic Image Generate');
define('PUM_PLUGIN_SLUG', 'automagic-image-generate');
define('PUM_PLUGIN_FILE', __FILE__);
define('PUM_LICENSE_SERVER_URL', 'https://sokulabo.com/products/wp-json/sokulabo/v1/license/verify');
define('PUM_LICENSE_PAGE_URL', 'https://sokulabo.com/products/');
define('PUM_UPDATE_SERVER_URL', 'https://sokulabo.com/products/plugins/automagic-image-generate/update-info.json');
define('PUM_LICENSE_PAGE_SLUG', 'amig-license');

// アップデーターを読み込み
require_once __DIR__ . '/universal-plugin-updater.php';

// フォントマネージャーを読み込み
require_once __DIR__ . '/font-manager.php';

class Automagic_Image_Generate {
    
    private static $instance = null;
    private $option_name = 'amig_settings';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // プラグインの初期化
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('save_post', array($this, 'auto_generate_thumbnail'), 10, 3);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_amig_bulk_generate', array($this, 'ajax_bulk_generate'));
        add_action('wp_ajax_amig_bulk_delete', array($this, 'ajax_bulk_delete'));
        add_action('wp_ajax_amig_clear_skipped', array($this, 'ajax_clear_skipped'));
        add_action('wp_ajax_amig_delete_single', array($this, 'ajax_delete_single'));
    }
    
    /**
     * 管理画面メニューの追加
     */
    public function add_admin_menu() {
        // トップレベルメニューを追加
        add_menu_page(
            'Automagic Image Generate',           // ページタイトル
            'Automagic Image Generate',           // メニュータイトル
            'manage_options',                     // 権限
            'automagic-image-generate',           // メニュースラッグ
            array($this, 'settings_page'),        // コールバック関数
            'dashicons-images-alt2',              // アイコン
            3                                     // メニューの位置（3=ダッシュボードの下）
        );
        
        // サブメニュー（設定）を追加（最初のサブメニューはメインと同じページ）
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            'Automagic Image Generate 設定',      // ページタイトル
            '設定',                                // メニュータイトル
            'manage_options',                     // 権限
            'automagic-image-generate',           // メニュースラッグ（親と同じ）
            array($this, 'settings_page')         // コールバック関数
        );
        
        // サブメニュー（一括生成）を追加
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            '一括画像生成',                        // ページタイトル
            '一括画像生成',                        // メニュータイトル
            'manage_options',                     // 権限
            'automagic-bulk-generate',            // メニュースラッグ
            array($this, 'bulk_generate_page')    // コールバック関数
        );
        
        // サブメニュー（生成済み画像管理）を追加
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            '生成済み画像管理',                    // ページタイトル
            '生成済み画像管理',                    // メニュータイトル
            'manage_options',                     // 権限
            'automagic-manage-images',            // メニュースラッグ
            array($this, 'manage_images_page')    // コールバック関数
        );
    }
    
    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'amig_general_section',
            '基本設定',
            array($this, 'general_section_callback'),
            $this->option_name
        );
        
        add_settings_field(
            'enable_auto_generation',
            '自動生成を有効化',
            array($this, 'enable_auto_generation_callback'),
            $this->option_name,
            'amig_general_section'
        );
        
        add_settings_field(
            'post_types',
            '対象の投稿タイプ',
            array($this, 'post_types_callback'),
            $this->option_name,
            'amig_general_section'
        );
        
        add_settings_section(
            'amig_design_section',
            'デザイン設定',
            array($this, 'design_section_callback'),
            $this->option_name
        );
        
        add_settings_field(
            'bg_color',
            '背景色',
            array($this, 'bg_color_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'text_color',
            'テキスト色',
            array($this, 'text_color_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'accent_color',
            'アクセント色',
            array($this, 'accent_color_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'font_size',
            'フォントサイズ',
            array($this, 'font_size_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'font_weight',
            'フォントの太さ',
            array($this, 'font_weight_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'image_style',
            '画像スタイル',
            array($this, 'image_style_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'bg_image',
            '背景画像',
            array($this, 'bg_image_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'bg_image_opacity',
            '背景画像の透明度',
            array($this, 'bg_image_opacity_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'line_height',
            '行間',
            array($this, 'line_height_callback'),
            $this->option_name,
            'amig_design_section'
        );
        
        add_settings_field(
            'letter_spacing',
            '文字間隔',
            array($this, 'letter_spacing_callback'),
            $this->option_name,
            'amig_design_section'
        );
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enable_auto_generation'] = isset($input['enable_auto_generation']) ? 1 : 0;
        
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $sanitized['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        } else {
            $sanitized['post_types'] = array('post');
        }
        
        if (isset($input['bg_color'])) {
            $sanitized['bg_color'] = sanitize_hex_color($input['bg_color']);
        }
        
        if (isset($input['text_color'])) {
            $sanitized['text_color'] = sanitize_hex_color($input['text_color']);
        }
        
        if (isset($input['accent_color'])) {
            $sanitized['accent_color'] = sanitize_hex_color($input['accent_color']);
        }
        
        if (isset($input['font_size'])) {
            $sanitized['font_size'] = absint($input['font_size']);
        }
        
        if (isset($input['font_weight'])) {
            $sanitized['font_weight'] = sanitize_text_field($input['font_weight']);
        }
        
        if (isset($input['image_style'])) {
            $sanitized['image_style'] = sanitize_text_field($input['image_style']);
        }
        
        if (isset($input['bg_image'])) {
            $sanitized['bg_image'] = absint($input['bg_image']);
        }
        
        if (isset($input['bg_image_opacity'])) {
            $sanitized['bg_image_opacity'] = min(100, max(0, absint($input['bg_image_opacity'])));
        }
        
        if (isset($input['line_height'])) {
            $sanitized['line_height'] = floatval($input['line_height']);
            if ($sanitized['line_height'] < 0.5) $sanitized['line_height'] = 0.5;
            if ($sanitized['line_height'] > 3.0) $sanitized['line_height'] = 3.0;
        }
        
        if (isset($input['letter_spacing'])) {
            $sanitized['letter_spacing'] = intval($input['letter_spacing']);
            if ($sanitized['letter_spacing'] < -20) $sanitized['letter_spacing'] = -20;
            if ($sanitized['letter_spacing'] > 50) $sanitized['letter_spacing'] = 50;
        }
        
        return $sanitized;
    }
    
    /**
     * 基本設定セクションの説明
     */
    public function general_section_callback() {
        echo '<p>投稿のタイトルを使用して、自動的にサムネイル画像を生成します。</p>';
    }
    
    /**
     * デザイン設定セクションの説明
     */
    public function design_section_callback() {
        echo '<p>生成する画像のデザインをカスタマイズできます。</p>';
    }
    
    /**
     * 自動生成有効化フィールド
     */
    public function enable_auto_generation_callback() {
        $options = get_option($this->option_name);
        $enabled = isset($options['enable_auto_generation']) ? $options['enable_auto_generation'] : 0;
        echo '<input type="checkbox" name="' . $this->option_name . '[enable_auto_generation]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label>投稿保存時に自動でサムネイル画像を生成する</label>';
    }
    
    /**
     * 投稿タイプ選択フィールド
     */
    public function post_types_callback() {
        $options = get_option($this->option_name);
        $selected_types = isset($options['post_types']) ? $options['post_types'] : array('post');
        
        $post_types = get_post_types(array('public' => true), 'objects');
        
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected_types) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="' . $this->option_name . '[post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' />';
            echo ' ' . esc_html($post_type->label);
            echo '</label>';
        }
        echo '<p class="description">サムネイル画像を自動生成する投稿タイプを選択してください</p>';
    }
    
    /**
     * 背景色フィールド
     */
    public function bg_color_callback() {
        $options = get_option($this->option_name);
        $color = isset($options['bg_color']) ? $options['bg_color'] : '#4A90E2';
        echo '<input type="color" name="' . $this->option_name . '[bg_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">サムネイル画像の背景色を選択してください</p>';
    }
    
    /**
     * テキスト色フィールド
     */
    public function text_color_callback() {
        $options = get_option($this->option_name);
        $color = isset($options['text_color']) ? $options['text_color'] : '#FFFFFF';
        echo '<input type="color" name="' . $this->option_name . '[text_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">タイトルテキストの色を選択してください</p>';
    }
    
    /**
     * アクセント色フィールド
     */
    public function accent_color_callback() {
        $options = get_option($this->option_name);
        $color = isset($options['accent_color']) ? $options['accent_color'] : '#FFD700';
        echo '<input type="color" name="' . $this->option_name . '[accent_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">アクセント要素の色を選択してください</p>';
    }
    
    /**
     * フォントサイズフィールド
     */
    public function font_size_callback() {
        $options = get_option($this->option_name);
        $size = isset($options['font_size']) ? $options['font_size'] : 48;
        echo '<input type="number" name="' . $this->option_name . '[font_size]" value="' . esc_attr($size) . '" min="20" max="100" id="amig-font-size" />';
        echo '<p class="description">タイトルのフォントサイズ（20〜100）</p>';
    }
    
    /**
     * フォントの太さフィールド
     */
    public function font_weight_callback() {
        $options = get_option($this->option_name);
        $weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        
        $weights = array(
            'light' => '細字（Light）',
            'normal' => '標準（Regular）',
            'medium' => '中太（Medium）',
            'bold' => '太字（Bold）',
            'black' => '極太（Black）'
        );
        
        echo '<select name="' . $this->option_name . '[font_weight]" id="amig-font-weight">';
        foreach ($weights as $value => $label) {
            $selected = selected($weight, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">タイトルテキストの太さを選択してください</p>';
    }
    
    /**
     * 画像スタイルフィールド
     */
    public function image_style_callback() {
        $options = get_option($this->option_name);
        $style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        
        $styles = array(
            'modern' => 'モダン（グラデーション）',
            'simple' => 'シンプル（単色）',
            'gradient' => 'グラデーション（カラフル）',
            'pattern' => 'パターン（幾何学模様）',
            'minimal' => 'ミニマル（余白重視）'
        );
        
        echo '<select name="' . $this->option_name . '[image_style]">';
        foreach ($styles as $value => $label) {
            $selected = selected($style, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">生成する画像のスタイルを選択してください</p>';
    }
    
    /**
     * 背景画像フィールド
     */
    public function bg_image_callback() {
        $options = get_option($this->option_name);
        $image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        
        $image_url = '';
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }
        
        echo '<div class="amig-image-upload">';
        echo '<input type="hidden" name="' . $this->option_name . '[bg_image]" id="amig-bg-image-id" value="' . esc_attr($image_id) . '" />';
        
        if ($image_url) {
            echo '<div id="amig-bg-image-preview" style="margin-bottom: 10px;">';
            echo '<img src="' . esc_url($image_url) . '" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;" />';
            echo '</div>';
        } else {
            echo '<div id="amig-bg-image-preview" style="margin-bottom: 10px; display: none;"></div>';
        }
        
        echo '<button type="button" class="button" id="amig-upload-bg-image">画像を選択</button>';
        if ($image_url) {
            echo ' <button type="button" class="button" id="amig-remove-bg-image">画像を削除</button>';
        }
        echo '<p class="description">背景に使用する画像をアップロードしてください。スタイルと組み合わせて使用されます。</p>';
        echo '</div>';
    }
    
    /**
     * 背景画像の透明度フィールド
     */
    public function bg_image_opacity_callback() {
        $options = get_option($this->option_name);
        $opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        
        echo '<input type="range" name="' . $this->option_name . '[bg_image_opacity]" id="amig-bg-image-opacity" value="' . esc_attr($opacity) . '" min="0" max="100" style="width: 300px;" />';
        echo ' <span id="amig-opacity-value">' . esc_html($opacity) . '%</span>';
        echo '<p class="description">背景画像の透明度（0%=透明、100%=不透明）</p>';
    }
    
    /**
     * 行間フィールド
     */
    public function line_height_callback() {
        $options = get_option($this->option_name);
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.5;
        
        echo '<input type="number" name="' . $this->option_name . '[line_height]" value="' . esc_attr($line_height) . '" step="0.1" min="0.5" max="3.0" style="width: 100px;" />';
        echo '<p class="description">テキストの行間隔（0.5〜3.0、推奨: 1.2〜1.8）</p>';
    }
    
    /**
     * 文字間隔フィールド
     */
    public function letter_spacing_callback() {
        $options = get_option($this->option_name);
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        
        echo '<input type="number" name="' . $this->option_name . '[letter_spacing]" value="' . esc_attr($letter_spacing) . '" min="-20" max="50" style="width: 100px;" />';
        echo ' <span>px</span>';
        echo '<p class="description">文字と文字の間隔（-20〜50px、0が標準）</p>';
    }
    
    /**
     * 設定画面の表示
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 現在のタブを取得
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        
        // フォントの状態をチェック
        $font_path = $this->get_japanese_font_path();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- タブナビゲーション -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=automagic-image-generate&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    基本設定
                </a>
                <a href="?page=automagic-image-generate&tab=design" class="nav-tab <?php echo $active_tab == 'design' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-customizer" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    デザイン設定
                </a>
                <a href="?page=automagic-image-generate&tab=preview" class="nav-tab <?php echo $active_tab == 'preview' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-image" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    プレビュー
                </a>
                <a href="?page=automagic-image-generate&tab=fonts" class="nav-tab <?php echo $active_tab == 'fonts' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-media-text" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    フォント管理
                </a>
            </h2>
            
            <?php if ($active_tab == 'settings'): ?>
                <!-- 基本設定タブ -->
                <form action="options.php" method="post">
                    <?php
                    settings_fields($this->option_name);
                    echo '<h2>基本設定</h2>';
                    echo '<p>投稿のタイトルを使用して、自動的にサムネイル画像を生成します。</p>';
                    echo '<table class="form-table" role="presentation">';
                    do_settings_fields($this->option_name, 'amig_general_section');
                    echo '</table>';
                    submit_button('設定を保存');
                    ?>
                </form>
                
            <?php elseif ($active_tab == 'design'): ?>
                <!-- デザイン設定タブ -->
                <form action="options.php" method="post">
                    <?php
                    settings_fields($this->option_name);
                    // デザイン設定セクションのみ表示
                    echo '<h2>デザイン設定</h2>';
                    echo '<p>生成する画像のデザインをカスタマイズできます。</p>';
                    echo '<table class="form-table" role="presentation">';
                    do_settings_fields($this->option_name, 'amig_design_section');
                    echo '</table>';
                    submit_button('設定を保存');
                    ?>
                </form>
                
            <?php elseif ($active_tab == 'preview'): ?>
                <!-- プレビュータブ -->
                <div style="margin-top: 20px;">
                    <h2>プレビュー生成</h2>
                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <div style="margin-bottom: 15px;">
                            <label for="amig-preview-text" style="display: block; margin-bottom: 8px; font-weight: 600;">プレビューテキスト:</label>
                            <input type="text" id="amig-preview-text" value="サンプルタイトル" style="width: 100%; max-width: 500px; padding: 8px 12px;" />
                        </div>
                        <button type="button" id="amig-preview-btn" class="button button-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-format-image" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            プレビューを生成
                        </button>
                        <div id="amig-preview-loading" style="display: none; color: #666; margin: 15px 0;">
                            <span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
                            画像を生成中...
                        </div>
                        <div id="amig-preview-container" style="margin-top: 20px;">
                            <p style="color: #666;">上のボタンをクリックして、現在の設定でプレビュー画像を生成します。</p>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab == 'fonts'): ?>
                <!-- フォント管理タブ -->
                <div style="margin-top: 20px;">
                    <!-- フォントファイルの状態 -->
                    <div class="notice <?php echo $font_path ? 'notice-success' : 'notice-warning'; ?>" style="padding: 15px; margin: 20px 0; border-left-width: 4px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 24px;">
                                <?php echo $font_path ? '✓' : '⚠'; ?>
                            </span>
                            <div style="flex: 1;">
                                <p style="margin: 0 0 5px 0; font-size: 15px; font-weight: 600;">
                                    <?php if ($font_path): ?>
                                        <span style="color: #2c7d2f;">日本語フォントが利用可能です</span>
                                    <?php else: ?>
                                        <span style="color: #d97706;">日本語フォントが見つかりません</span>
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($font_path): ?>
                                    <p style="margin: 0; color: #666; font-size: 13px;">
                                        使用中のフォント: <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(basename($font_path)); ?></code>
                                    </p>
                                <?php else: ?>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px; line-height: 1.6;">
                                        日本語を含むタイトルで画像を生成するには、日本語フォントが必要です。<br>
                                        <strong>フォント配置先:</strong> <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html(AMIG_PLUGIN_DIR); ?>fonts/</code>
                                    </p>
                                    <p style="margin: 10px 0 0 0;">
                                        <a href="https://fonts.google.com/noto/specimen/Noto+Sans+JP" target="_blank" class="button button-secondary" style="text-decoration: none;">
                                            <span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Google Fonts からダウンロード
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // フォントマネージャーの詳細を表示
                    do_action('amig_license_page_content');
                    ?>
                </div>
                
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        // プラグインのページでのみスクリプトを読み込む
        if ('toplevel_page_automagic-image-generate' !== $hook && 'automagic-image-generate_page_automagic-bulk-generate' !== $hook) {
            return;
        }
        
        // WordPress メディアアップローダー
        wp_enqueue_media();
        
        wp_enqueue_script(
            'amig-admin-script',
            AMIG_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            AMIG_VERSION,
            true
        );
        
        wp_localize_script('amig-admin-script', 'amigAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amig_generate_nonce')
        ));
    }
    
    /**
     * 投稿保存時に自動でサムネイル画像を生成
     */
    public function auto_generate_thumbnail($post_id, $post, $update) {
        // 自動保存、リビジョン、自動下書きの場合はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // 設定を取得
        $options = get_option($this->option_name);
        
        // 自動生成が無効の場合はスキップ
        if (!isset($options['enable_auto_generation']) || !$options['enable_auto_generation']) {
            return;
        }
        
        // 対象の投稿タイプかチェック
        $selected_types = isset($options['post_types']) ? $options['post_types'] : array('post');
        if (!in_array($post->post_type, $selected_types)) {
            return;
        }
        
        // 既にサムネイルが設定されている場合はスキップ
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        // 投稿が公開されている場合のみ生成
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // 画像を生成
        $this->generate_and_set_thumbnail($post_id);
    }
    
    /**
     * サムネイル画像を生成して設定
     */
    private function generate_and_set_thumbnail($post_id) {
        // 投稿情報を取得
        $post = get_post($post_id);
        $title = $post->post_title;
        
        if (empty($title)) {
            error_log('Automagic Image Generate: タイトルが空です');
            return false;
        }
        
        // 設定を取得
        $options = get_option($this->option_name);
        $bg_color = isset($options['bg_color']) ? $options['bg_color'] : '#4A90E2';
        $text_color = isset($options['text_color']) ? $options['text_color'] : '#FFFFFF';
        $accent_color = isset($options['accent_color']) ? $options['accent_color'] : '#FFD700';
        $font_size = isset($options['font_size']) ? $options['font_size'] : 48;
        $font_weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        $image_style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        $bg_image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        $bg_image_opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.5;
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        
        // 画像を生成
        $image_path = $this->create_thumbnail_image(
            $title,
            $bg_color,
            $text_color,
            $accent_color,
            $font_size,
            $font_weight,
            $image_style,
            $bg_image_id,
            $bg_image_opacity,
            $line_height,
            $letter_spacing
        );
        
        if (!$image_path) {
            error_log('Automagic Image Generate: 画像の生成に失敗しました');
            return false;
        }
        
        // 画像をメディアライブラリにアップロード
        $attachment_id = $this->upload_image_to_media_library($image_path, $post_id, $title);
        
        // 一時ファイルを削除
        @unlink($image_path);
        
        if ($attachment_id) {
            // サムネイルとして設定
            set_post_thumbnail($post_id, $attachment_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * サムネイル画像を生成して投稿に設定（一括生成用）
     * @param int $post_id 投稿ID
     * @return int|false 生成されたアタッチメントID、失敗時はfalse
     */
    public function generate_thumbnail_image($post_id) {
        // 投稿情報を取得
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        $title = $post->post_title;
        
        if (empty($title)) {
            return false;
        }
        
        // 設定を取得
        $options = get_option($this->option_name);
        $bg_color = isset($options['bg_color']) ? $options['bg_color'] : '#4A90E2';
        $text_color = isset($options['text_color']) ? $options['text_color'] : '#FFFFFF';
        $accent_color = isset($options['accent_color']) ? $options['accent_color'] : '#FFD700';
        $font_size = isset($options['font_size']) ? $options['font_size'] : 48;
        $font_weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        $image_style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        $bg_image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        $bg_image_opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.5;
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        
        // 画像を生成
        $image_path = $this->create_thumbnail_image(
            $title,
            $bg_color,
            $text_color,
            $accent_color,
            $font_size,
            $font_weight,
            $image_style,
            $bg_image_id,
            $bg_image_opacity,
            $line_height,
            $letter_spacing
        );
        
        if (!$image_path) {
            return false;
        }
        
        // 画像をメディアライブラリにアップロード
        $attachment_id = $this->upload_image_to_media_library($image_path, $post_id, $title);
        
        // 一時ファイルを削除
        @unlink($image_path);
        
        if ($attachment_id) {
            // このプラグインで生成した画像であることを示すメタデータを追加
            update_post_meta($attachment_id, '_amig_generated', '1');
            update_post_meta($attachment_id, '_amig_generated_date', current_time('mysql'));
            
            // サムネイルとして設定
            set_post_thumbnail($post_id, $attachment_id);
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * サムネイル画像を作成
     */
    private function create_thumbnail_image($title, $bg_color, $text_color, $accent_color, $font_size, $font_weight, $style, $bg_image_id = 0, $bg_image_opacity = 30, $line_height = 1.5, $letter_spacing = 0) {
        error_log('Automagic Image Generate: create_thumbnail_image 開始 - title: ' . $title);
        
        // GD拡張が有効か確認
        if (!extension_loaded('gd')) {
            error_log('Automagic Image Generate: GD拡張が有効化されていません');
            return false;
        }
        
        // 画像サイズ
        $width = 1200;
        $height = 630;
        
        // 画像リソースを作成
        $image = imagecreatetruecolor($width, $height);
        
        if (!$image) {
            return false;
        }
        
        // 色を変換
        $bg_rgb = $this->hex_to_rgb($bg_color);
        $text_rgb = $this->hex_to_rgb($text_color);
        $accent_rgb = $this->hex_to_rgb($accent_color);
        
        $bg_color_gd = imagecolorallocate($image, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2]);
        $text_color_gd = imagecolorallocate($image, $text_rgb[0], $text_rgb[1], $text_rgb[2]);
        $accent_color_gd = imagecolorallocate($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);
        
        // 背景画像がない場合、または透明度が低い場合は、先にスタイルを描画
        $has_bg_image = $bg_image_id && $bg_image_opacity > 0;
        
        if (!$has_bg_image) {
            // スタイルに応じた背景を描画
            switch ($style) {
                case 'gradient':
                    $this->draw_gradient_background($image, $width, $height, $bg_rgb, $accent_rgb);
                    break;
                case 'pattern':
                    imagefill($image, 0, 0, $bg_color_gd);
                    $this->draw_pattern($image, $width, $height, $accent_color_gd);
                    break;
                case 'minimal':
                    // 白背景
                    $white = imagecolorallocate($image, 255, 255, 255);
                    imagefill($image, 0, 0, $white);
                    // アクセントラインを追加
                    imagefilledrectangle($image, 0, 0, 10, $height, $accent_color_gd);
                    break;
                case 'simple':
                    imagefill($image, 0, 0, $bg_color_gd);
                    break;
                case 'modern':
                default:
                    $this->draw_modern_background($image, $width, $height, $bg_rgb, $accent_rgb);
                    break;
            }
        } else {
            // 背景画像がある場合
            // まず単色背景を描画
            imagefill($image, 0, 0, $bg_color_gd);
            
            // 背景画像を描画
            $bg_drawn = $this->draw_background_image($image, $width, $height, $bg_image_id, $bg_image_opacity);
            
            if ($bg_drawn) {
                // 背景画像の上にスタイルに応じたオーバーレイを追加
                switch ($style) {
                    case 'gradient':
                        $this->draw_gradient_overlay($image, $width, $height, $bg_rgb, $accent_rgb);
                        break;
                    case 'pattern':
                        $this->draw_pattern($image, $width, $height, $accent_color_gd);
                        break;
                    case 'modern':
                        // モダンスタイルの装飾円を追加
                        $overlay_color = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 100);
                        imagefilledellipse($image, $width * 0.8, $height * 0.3, 400, 400, $overlay_color);
                        imagefilledellipse($image, $width * 0.2, $height * 0.7, 300, 300, $overlay_color);
                        break;
                }
            }
        }
        
        // テキストを描画
        $this->draw_text_on_image($image, $title, $text_color_gd, $font_size, $font_weight, $width, $height, $style, $line_height, $letter_spacing);
        
        // 一時ファイルとして保存
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/amig-temp-' . time() . '.png';
        
        imagepng($image, $temp_file);
        imagedestroy($image);
        
        return $temp_file;
    }
    
    /**
     * 背景画像を描画
     */
    private function draw_background_image($image, $width, $height, $bg_image_id, $opacity) {
        $bg_image_path = get_attached_file($bg_image_id);
        
        if (!$bg_image_path || !file_exists($bg_image_path)) {
            return false;
        }
        
        // 画像タイプを判定
        $image_info = getimagesize($bg_image_path);
        if (!$image_info) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        
        // 画像を読み込み
        switch ($mime_type) {
            case 'image/jpeg':
                $bg_image = imagecreatefromjpeg($bg_image_path);
                break;
            case 'image/png':
                $bg_image = imagecreatefrompng($bg_image_path);
                break;
            case 'image/gif':
                $bg_image = imagecreatefromgif($bg_image_path);
                break;
            case 'image/webp':
                $bg_image = imagecreatefromwebp($bg_image_path);
                break;
            default:
                return false;
        }
        
        if (!$bg_image) {
            return false;
        }
        
        // 背景画像をリサイズしてコピー（アスペクト比を維持してカバー）
        $bg_width = imagesx($bg_image);
        $bg_height = imagesy($bg_image);
        
        // カバー用の計算
        $scale_w = $width / $bg_width;
        $scale_h = $height / $bg_height;
        $scale = max($scale_w, $scale_h);
        
        $new_w = (int)($bg_width * $scale);
        $new_h = (int)($bg_height * $scale);
        $offset_x = (int)(($width - $new_w) / 2);
        $offset_y = (int)(($height - $new_h) / 2);
        
        // 透明度設定に応じて画像を合成
        if ($opacity < 100) {
            // アルファブレンディングを有効化
            imagealphablending($image, true);
            
            // 一時的な画像を作成
            $temp_image = imagecreatetruecolor($new_w, $new_h);
            imagecopyresampled($temp_image, $bg_image, 0, 0, 0, 0, $new_w, $new_h, $bg_width, $bg_height);
            
            // 透明度を適用してコピー
            imagecopymerge($image, $temp_image, $offset_x, $offset_y, 0, 0, $new_w, $new_h, $opacity);
            
            imagedestroy($temp_image);
        } else {
            // 透明度100%の場合は直接コピー
            imagecopyresampled($image, $bg_image, $offset_x, $offset_y, 0, 0, $new_w, $new_h, $bg_width, $bg_height);
        }
        
        imagedestroy($bg_image);
        return true;
    }
    
    /**
     * グラデーションオーバーレイを描画
     */
    private function draw_gradient_overlay($image, $width, $height, $start_rgb, $end_rgb) {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = $start_rgb[0] + ($end_rgb[0] - $start_rgb[0]) * $ratio;
            $g = $start_rgb[1] + ($end_rgb[1] - $start_rgb[1]) * $ratio;
            $b = $start_rgb[2] + ($end_rgb[2] - $start_rgb[2]) * $ratio;
            
            // 半透明のグラデーション
            $color = imagecolorallocatealpha($image, $r, $g, $b, 80);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * グラデーション背景を描画
     */
    private function draw_gradient_background($image, $width, $height, $start_rgb, $end_rgb) {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = $start_rgb[0] + ($end_rgb[0] - $start_rgb[0]) * $ratio;
            $g = $start_rgb[1] + ($end_rgb[1] - $start_rgb[1]) * $ratio;
            $b = $start_rgb[2] + ($end_rgb[2] - $start_rgb[2]) * $ratio;
            
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * モダンな背景を描画（グラデーション + 装飾）
     */
    private function draw_modern_background($image, $width, $height, $bg_rgb, $accent_rgb) {
        // グラデーション背景
        $this->draw_gradient_background($image, $width, $height, $bg_rgb, $accent_rgb);
        
        // 半透明の円を追加
        $overlay_color = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 80);
        imagefilledellipse($image, $width * 0.8, $height * 0.3, 400, 400, $overlay_color);
        imagefilledellipse($image, $width * 0.2, $height * 0.7, 300, 300, $overlay_color);
    }
    
    /**
     * パターンを描画
     */
    private function draw_pattern($image, $width, $height, $accent_color) {
        $spacing = 50;
        imagesetthickness($image, 2);
        
        // 対角線パターン
        for ($x = -$height; $x < $width; $x += $spacing) {
            imageline($image, $x, 0, $x + $height, $height, $accent_color);
        }
    }
    
    /**
     * テキストを画像に描画
     */
    private function draw_text_on_image($image, $text, $color, $font_size, $font_weight, $width, $height, $style, $line_height_multiplier = 1.5, $letter_spacing = 0) {
        // システムフォントを使用（日本語対応）
        $font_path = $this->get_japanese_font_path($font_weight);
        
        if ($font_path && file_exists($font_path)) {
            // TrueTypeフォントを使用
            $this->draw_text_with_font($image, $text, $color, $font_size, $width, $height, $font_path, $style, $font_weight, $line_height_multiplier, $letter_spacing);
        } else {
            // 日本語フォントが見つからない場合はエラーログを記録
            error_log('Automagic Image Generate: 日本語フォントが見つかりません。アルファベットのみ表示されます。');
            
            // 英数字のみの場合は内蔵フォントを使用、それ以外は警告を表示
            if (preg_match('/^[a-zA-Z0-9\s\-_.,!?]+$/', $text)) {
                $this->draw_text_builtin($image, $text, $color, $width, $height, $style);
            } else {
                // 日本語が含まれる場合は警告メッセージを表示
                $this->draw_no_font_warning($image, $width, $height, $color);
            }
        }
    }
    
    /**
     * TrueTypeフォントでテキストを描画
     */
    private function draw_text_with_font($image, $text, $color, $font_size, $width, $height, $font_path, $style, $font_weight = 'normal', $line_height_multiplier = 1.5, $letter_spacing = 0) {
        // UTF-8エンコーディングを確実にする
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // テキストを折り返し
        $max_width = $width - 120;
        $lines = $this->wrap_text($text, $font_size, $font_path, $max_width, $letter_spacing);
        
        if (empty($lines)) {
            return;
        }
        
        $line_height = $font_size * $line_height_multiplier;
        $total_height = count($lines) * $line_height;
        $y_start = ($height - $total_height) / 2 + $font_size;
        
        // 太字シミュレーション用のオフセット
        $bold_offset = 0;
        switch ($font_weight) {
            case 'light':
                $bold_offset = 0;
                break;
            case 'normal':
                $bold_offset = 0;
                break;
            case 'medium':
                $bold_offset = 1;
                break;
            case 'bold':
                $bold_offset = 2;
                break;
            case 'black':
                $bold_offset = 3;
                break;
        }
        
        foreach ($lines as $index => $line) {
            // 空行をスキップ
            if (empty(trim($line))) {
                continue;
            }
            
            // 文字間隔を考慮したテキスト幅を計算
            $text_width = $this->calculate_text_width($line, $font_size, $font_path, $letter_spacing);
            
            $x = ($width - $text_width) / 2;
            $y = $y_start + ($index * $line_height);
            
            // 影を追加（ミニマル以外）
            if ($style !== 'minimal') {
                $shadow = imagecolorallocatealpha($image, 0, 0, 0, 50);
                if ($letter_spacing > 0) {
                    // 文字間隔がある場合は1文字ずつ描画
                    $current_x = $x;
                    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($chars as $char) {
                        imagettftext($image, $font_size, 0, $current_x + 3, $y + 3, $shadow, $font_path, $char);
                        $char_bbox = imagettfbbox($font_size, 0, $font_path, $char);
                        $current_x += ($char_bbox[2] - $char_bbox[0]) + $letter_spacing;
                    }
                } else {
                    imagettftext($image, $font_size, 0, $x + 3, $y + 3, $shadow, $font_path, $line);
                }
            }
            
            // 太字効果（複数回描画）
            if ($letter_spacing > 0) {
                // 文字間隔がある場合は1文字ずつ描画
                $current_x = $x;
                $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $char) {
                    if ($bold_offset > 0) {
                        for ($i = 0; $i <= $bold_offset; $i++) {
                            imagettftext($image, $font_size, 0, $current_x + $i, $y, $color, $font_path, $char);
                            if ($i > 0) {
                                imagettftext($image, $font_size, 0, $current_x, $y + $i, $color, $font_path, $char);
                            }
                        }
                    } else {
                        imagettftext($image, $font_size, 0, $current_x, $y, $color, $font_path, $char);
                    }
                    $char_bbox = imagettfbbox($font_size, 0, $font_path, $char);
                    $current_x += ($char_bbox[2] - $char_bbox[0]) + $letter_spacing;
                }
            } else {
                // 文字間隔がない場合は通常の描画
                if ($bold_offset > 0) {
                    for ($i = 0; $i <= $bold_offset; $i++) {
                        imagettftext($image, $font_size, 0, $x + $i, $y, $color, $font_path, $line);
                        if ($i > 0) {
                            imagettftext($image, $font_size, 0, $x, $y + $i, $color, $font_path, $line);
                        }
                    }
                } else {
                    imagettftext($image, $font_size, 0, $x, $y, $color, $font_path, $line);
                }
            }
        }
    }
    
    /**
     * 内蔵フォントでテキストを描画
     */
    private function draw_text_builtin($image, $text, $color, $width, $height, $style) {
        $font = 5; // 最大の内蔵フォント
        $char_width = imagefontwidth($font);
        $char_height = imagefontheight($font);
        
        // テキストを折り返し
        $max_chars = floor(($width - 120) / $char_width);
        $lines = array();
        $words = explode(' ', $text);
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= $max_chars) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if ($current_line) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        if ($current_line) {
            $lines[] = $current_line;
        }
        
        $total_height = count($lines) * ($char_height + 10);
        $y_start = ($height - $total_height) / 2;
        
        foreach ($lines as $index => $line) {
            $text_width = strlen($line) * $char_width;
            $x = ($width - $text_width) / 2;
            $y = $y_start + ($index * ($char_height + 10));
            
            imagestring($image, $font, $x, $y, $line, $color);
        }
    }
    
    /**
     * 日本語フォントがない場合の警告を表示
     */
    private function draw_no_font_warning($image, $width, $height, $text_color) {
        $font = 5;
        $warning_lines = array(
            'Japanese Font Not Found',
            'Please install a Japanese font',
            'or add font to: plugins/automagic-image-generate/fonts/'
        );
        
        $char_height = imagefontheight($font);
        $total_height = count($warning_lines) * ($char_height + 15);
        $y_start = ($height - $total_height) / 2;
        
        foreach ($warning_lines as $index => $line) {
            $char_width = imagefontwidth($font);
            $text_width = strlen($line) * $char_width;
            $x = ($width - $text_width) / 2;
            $y = $y_start + ($index * ($char_height + 15));
            
            imagestring($image, $font, $x, $y, $line, $text_color);
        }
    }
    
    /**
     * テキストを折り返し
     */
    private function wrap_text($text, $font_size, $font_path, $max_width, $letter_spacing = 0) {
        $lines = array();
        
        // UTF-8エンコーディングを確実にする
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // 日本語の場合は文字単位で折り返しを考慮
        $has_japanese = preg_match('/[ぁ-んァ-ヶー一-龠]/u', $text);
        
        if ($has_japanese) {
            // 日本語を含む場合は、適切な位置で折り返し
            $lines = $this->wrap_japanese_text($text, $font_size, $font_path, $max_width, $letter_spacing);
        } else {
            // 英語の場合は単語単位で折り返し
            $words = preg_split('/\s+/u', $text);
            $current_line = '';
            
            foreach ($words as $word) {
                if (empty($word)) continue;
                
                $test_line = $current_line . ($current_line ? ' ' : '') . $word;
                $text_width = $this->calculate_text_width($test_line, $font_size, $font_path, $letter_spacing);
                
                if ($text_width <= $max_width) {
                    $current_line = $test_line;
                } else {
                    if ($current_line) {
                        $lines[] = $current_line;
                    }
                    $current_line = $word;
                }
            }
            
            if ($current_line) {
                $lines[] = $current_line;
            }
        }
        
        // 最大3行に制限
        return array_slice($lines, 0, 3);
    }
    
    /**
     * 文字間隔を考慮したテキスト幅を計算
     */
    private function calculate_text_width($text, $font_size, $font_path, $letter_spacing = 0) {
        if ($letter_spacing <= 0) {
            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
            if ($bbox === false) return 0;
            return $bbox[2] - $bbox[0];
        }
        
        // 文字間隔がある場合は1文字ずつ計算
        $total_width = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            $bbox = imagettfbbox($font_size, 0, $font_path, $char);
            if ($bbox !== false) {
                $total_width += ($bbox[2] - $bbox[0]) + $letter_spacing;
            }
        }
        // 最後の文字の後には間隔を追加しない
        if (count($chars) > 0) {
            $total_width -= $letter_spacing;
        }
        return $total_width;
    }
    
    /**
     * 日本語テキストを折り返し
     */
    private function wrap_japanese_text($text, $font_size, $font_path, $max_width, $letter_spacing = 0) {
        $lines = array();
        $current_line = '';
        $length = mb_strlen($text, 'UTF-8');
        
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $test_line = $current_line . $char;
            
            $text_width = $this->calculate_text_width($test_line, $font_size, $font_path, $letter_spacing);
            
            if ($text_width <= $max_width) {
                $current_line = $test_line;
            } else {
                if ($current_line) {
                    $lines[] = $current_line;
                }
                $current_line = $char;
            }
        }
        
        if ($current_line) {
            $lines[] = $current_line;
        }
        
        return $lines;
    }
    
    /**
     * 日本語対応フォントのパスを取得
     */
    private function get_japanese_font_path($weight = 'normal') {
        // フォントの太さに応じたファイル名のマッピング
        $weight_mapping = array(
            'light' => array('Light', 'Thin', 'W2', 'W3'),
            'normal' => array('Regular', 'Normal', 'W4', 'W5'),
            'medium' => array('Medium', 'W6'),
            'bold' => array('Bold', 'W7', 'W8'),
            'black' => array('Black', 'Heavy', 'W9')
        );
        
        $weight_suffixes = isset($weight_mapping[$weight]) ? $weight_mapping[$weight] : $weight_mapping['normal'];
        
        // よく使われる日本語フォントのパス
        $font_dirs = array(
            // macOS
            '/System/Library/Fonts/',
            '/System/Library/Fonts/Supplemental/',
            '/Library/Fonts/',
            // Linux
            '/usr/share/fonts/truetype/takao-gothic/',
            '/usr/share/fonts/opentype/noto/',
            '/usr/share/fonts/truetype/noto/',
            '/usr/share/fonts/google-noto-cjk/',
            // プラグインディレクトリ
            AMIG_PLUGIN_DIR . 'fonts/'
        );
        
        $font_names = array(
            'NotoSansJP',
            'NotoSansCJK',
            'ヒラギノ角ゴシック',
            'Hiragino Sans GB',
            'TakaoPGothic'
        );
        
        // まず、指定された太さのフォントを探す
        foreach ($font_dirs as $dir) {
            foreach ($font_names as $name) {
                foreach ($weight_suffixes as $suffix) {
                    $possible_fonts = array(
                        $dir . $name . '-' . $suffix . '.ttf',
                        $dir . $name . '-' . $suffix . '.otf',
                        $dir . $name . '-' . $suffix . '.ttc',
                        $dir . $name . ' ' . $suffix . '.ttf',
                        $dir . $name . ' ' . $suffix . '.otf',
                        $dir . $name . ' ' . $suffix . '.ttc'
                    );
                    
                    foreach ($possible_fonts as $font) {
                        if (file_exists($font)) {
                            return $font;
                        }
                    }
                }
            }
        }
        
        // 指定された太さのフォントが見つからない場合、デフォルトのフォントを探す
        $default_fonts = array(
            '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
            '/System/Library/Fonts/Supplemental/ヒラギノ角ゴシック W3.ttc',
            '/System/Library/Fonts/Hiragino Sans GB.ttc',
            '/System/Library/Fonts/Supplemental/Hiragino Sans GB.ttc',
            '/usr/share/fonts/truetype/takao-gothic/TakaoPGothic.ttf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            AMIG_PLUGIN_DIR . 'fonts/NotoSansJP-Regular.ttf',
            AMIG_PLUGIN_DIR . 'fonts/NotoSansJP-Bold.ttf',
            AMIG_PLUGIN_DIR . 'fonts/NotoSansJP-Medium.ttf',
            AMIG_PLUGIN_DIR . 'fonts/NotoSansJP-Light.ttf',
            AMIG_PLUGIN_DIR . 'fonts/NotoSansJP-Black.ttf'
        );
        
        foreach ($default_fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return false;
    }
    
    /**
     * 16進数カラーコードをRGBに変換
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return array($r, $g, $b);
    }
    
    /**
     * 画像をメディアライブラリにアップロード
     */
    private function upload_image_to_media_library($image_path, $post_id, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // ファイル情報を準備
        $file_array = array(
            'name' => 'automagic-' . $post_id . '-' . time() . '.png',
            'tmp_name' => $image_path
        );
        
        // メディアライブラリにアップロード
        $attachment_id = media_handle_sideload($file_array, $post_id, $title);
        
        if (is_wp_error($attachment_id)) {
            error_log('Automagic Image Generate: メディアライブラリへのアップロードに失敗しました - ' . $attachment_id->get_error_message());
            return false;
        }
        
        return $attachment_id;
    }
    
    /**
     * 一括画像生成ページの表示
     */
    public function bulk_generate_page() {
        if (!current_user_can('manage_options')) {
            wp_die('このページにアクセスする権限がありません。');
        }
        
        // 公開されている投稿タイプを取得
        $post_types = get_post_types(array('public' => true), 'objects');
        
        ?>
        <div class="wrap">
            <h1>一括画像生成</h1>
            <p>選択した投稿タイプの投稿に対して、一括でサムネイル画像を生成します。</p>
                
            <div class="notice notice-info" style="margin-top: 20px;">
                <p><strong>🔍 デバッグ情報:</strong></p>
                <p>エラーが発生した場合は、以下のログファイルを確認してください：</p>
                <ul style="margin: 10px 0;">
                    <li><strong>MAMP PHPエラーログ:</strong> <code>/Applications/MAMP/logs/php_error.log</code></li>
                    <li><strong>WordPressデバッグログ:</strong> <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code></li>
                    <li><strong>ブラウザコンソール:</strong> F12キーを押してコンソールタブを確認</li>
                </ul>
                <p style="margin-top: 10px;">ログには「AMIG」で始まる詳細なデバッグ情報が記録されています。</p>
            </div>
                
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
                    <h2 style="margin-top: 0;">投稿タイプを選択</h2>
                    
                    <?php foreach ($post_types as $post_type): ?>
                        <?php
                        // SQLクエリで直接カウント（meta_queryの警告を回避）
                        global $wpdb;
                        
                        // 投稿数をカウント（サムネイルがない投稿のみ）
                        $count = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT p.ID)
                            FROM {$wpdb->posts} p
                            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
                            WHERE p.post_type = %s 
                            AND p.post_status = 'publish'
                            AND (pm.meta_value IS NULL OR pm.meta_value = '')
                        ", $post_type->name));
                        
                    // プラグインで生成したサムネイルの数をカウント
                    $count_generated = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
                        INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.post_id AND pm2.meta_key = '_amig_generated' AND pm2.meta_value = '1'
                        WHERE p.post_type = %s AND p.post_status = 'publish'
                    ", $post_type->name));
                    
                    // サムネイルが設定されている投稿数をカウント（全体）
                    $count_with_thumbnail = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
                        WHERE p.post_type = %s 
                        AND p.post_status = 'publish'
                        AND pm.meta_value IS NOT NULL 
                        AND pm.meta_value != ''
                    ", $post_type->name));
                    ?>
                    
                    <div style="background: #f9f9f9; border: 1px solid #e0e0e0; padding: 20px; border-radius: 6px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                            <div style="flex: 1; min-width: 200px;">
                                <h3 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
                                    <span class="dashicons dashicons-admin-post" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    <?php echo esc_html($post_type->label); ?>
                                </h3>
                                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                    <p style="margin: 0; color: #666; font-size: 14px;">
                                        サムネイル未設定: <strong><?php echo $count; ?></strong> 件
                                    </p>
                                    <p style="margin: 0; color: #2c7d2f; font-size: 14px;">
                                        サムネイル設定済: <strong><?php echo $count_with_thumbnail; ?></strong> 件
                                    </p>
                                    <p style="margin: 0; color: #4A90E2; font-size: 14px;">
                                        プラグイン生成: <strong><?php echo $count_generated; ?></strong> 件
                                    </p>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php if ($count > 0): ?>
                                    <button type="button" 
                                            class="button button-primary amig-bulk-generate-btn" 
                                            data-post-type="<?php echo esc_attr($post_type->name); ?>"
                                            data-count="<?php echo $count; ?>"
                                            style="display: inline-flex; align-items: center; gap: 6px;">
                                        <span class="dashicons dashicons-images-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                        一括生成
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($count_generated > 0): ?>
                                    <button type="button" 
                                            class="button button-secondary amig-bulk-delete-btn" 
                                            data-post-type="<?php echo esc_attr($post_type->name); ?>"
                                            data-count="<?php echo $count_generated; ?>"
                                            style="display: inline-flex; align-items: center; gap: 6px; color: #dc3232; border-color: #dc3232;">
                                        <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                        一括削除
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($count == 0 && $count_with_thumbnail == 0): ?>
                                    <span style="color: #666; font-style: italic;">投稿がありません</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="amig-bulk-progress" data-post-type="<?php echo esc_attr($post_type->name); ?>" style="display: none; margin-top: 15px;">
                            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span class="amig-progress-text">処理中...</span>
                                    <span class="amig-progress-count">0 / <?php echo $count; ?></span>
                                </div>
                                <div style="background: #f0f0f1; height: 24px; border-radius: 4px; overflow: hidden;">
                                    <div class="amig-progress-bar" style="background: linear-gradient(90deg, #4A90E2 0%, #357ABD 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                            <div class="amig-progress-log" style="max-height: 200px; overflow-y: auto; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="notice notice-info" style="margin-top: 20px;">
                    <p><strong>一括生成について:</strong></p>
                    <ul style="margin: 10px 0;">
                        <li>既にサムネイルが設定されている投稿には画像を生成しません。</li>
                        <li>処理中はブラウザを閉じないでください。</li>
                        <li>多数の投稿がある場合、処理に時間がかかる場合があります。</li>
                    </ul>
                </div>
                
                <div class="notice notice-warning" style="margin-top: 20px;">
                    <p><strong>⚠️ 一括削除について:</strong></p>
                    <ul style="margin: 10px 0;">
                        <li><strong>一括削除は、このプラグインで生成したサムネイル画像のみを削除します。</strong></li>
                        <li>手動でアップロードした既存のサムネイル画像は削除されません。</li>
                        <li>削除された画像はメディアライブラリからも完全に削除されます。</li>
                        <li>この操作は取り消すことができません。実行前に必ずバックアップを取ることをお勧めします。</li>
                    </ul>
                </div>
                
                <div class="notice notice-info" style="margin-top: 20px;">
                    <p><strong>💡 スキップされた投稿について:</strong></p>
                    <p>画像生成に失敗した投稿は自動的にスキップされます。エラーを修正した後、以下のボタンでスキップ状態をクリアできます。</p>
                    <button type="button" id="amig-clear-skipped" class="button" style="margin-top: 10px;">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> スキップ済みをクリア
                    </button>
                </div>
        
                <script type="text/javascript">
                // ajaxurlが定義されていない場合のために定義
                var ajaxurl = ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>';
                
                jQuery(document).ready(function($) {
                    console.log('一括生成スクリプト読み込み完了. ajaxurl:', ajaxurl);
                    
            // スキップ済みをクリアするボタン
            $('#amig-clear-skipped').on('click', function() {
                const btn = $(this);
                if (!confirm('スキップ済みの投稿をクリアしますか？\n\n次回の一括生成で、これらの投稿も処理対象になります。')) {
                    return;
                }
                
                btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>処理中...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'amig_clear_skipped',
                        nonce: '<?php echo wp_create_nonce('amig_clear_skipped'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('スキップ済みの投稿をクリアしました。\n削除件数: ' + response.data.count);
                            location.reload();
                        } else {
                            alert('エラー: ' + response.data);
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> スキップ済みをクリア');
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました');
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> スキップ済みをクリア');
                    }
                });
            });
            
            $('.amig-bulk-generate-btn').on('click', function() {
                const btn = $(this);
                const postType = btn.data('post-type');
                const totalCount = btn.data('count');
                const progressContainer = $('.amig-bulk-progress[data-post-type="' + postType + '"]');
                const progressBar = progressContainer.find('.amig-progress-bar');
                const progressText = progressContainer.find('.amig-progress-text');
                const progressCount = progressContainer.find('.amig-progress-count');
                const progressLog = progressContainer.find('.amig-progress-log');
                
                btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>処理中...');
                progressContainer.show();
                progressLog.empty();
                
                let processedCount = 0;
                
                console.log('一括生成開始:', {postType: postType, totalCount: totalCount});
                
                function processNext() {
                    const requestData = {
                        action: 'amig_bulk_generate',
                        post_type: postType,
                        nonce: '<?php echo wp_create_nonce('amig_bulk_generate'); ?>'
                    };
                    console.log('リクエスト送信:', requestData);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: requestData,
                        success: function(response) {
                            console.log('レスポンス受信:', response);
                            if (response.success) {
                                const percentage = Math.round((processedCount / totalCount) * 100);
                                
                                progressBar.css('width', percentage + '%');
                                
                                if (response.data.post_id) {
                                    processedCount++;
                                    progressCount.text(processedCount + ' / ' + totalCount);
                                    
                                    if (response.data.skipped) {
                                        progressLog.append('<div style="color: #d97706; margin-bottom: 3px;">⚠ ID: ' + response.data.post_id + ' - ' + response.data.post_title + ' (スキップ: ' + response.data.error + ')</div>');
                                    } else {
                                        progressLog.append('<div style="color: #2c7d2f; margin-bottom: 3px;">✓ ID: ' + response.data.post_id + ' - ' + response.data.post_title + '</div>');
                                    }
                                } else if (response.data.message) {
                                    progressLog.append('<div style="color: #666; margin-bottom: 3px;">• ' + response.data.message + '</div>');
                                }
                                
                                progressLog.scrollTop(progressLog[0].scrollHeight);
                                
                                if (response.data.continue && processedCount < totalCount) {
                                    progressText.text('処理中... (' + processedCount + ' / ' + totalCount + ')');
                                    setTimeout(processNext, 100); // 100msの遅延を追加
                                } else {
                                    progressText.html('<span style="color: #2c7d2f;">✓ 完了しました！</span>');
                                    progressBar.css('width', '100%');
                                    progressCount.text(totalCount + ' / ' + totalCount);
                                    btn.prop('disabled', false).html('<span class="dashicons dashicons-images-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括生成');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                }
                            } else {
                                console.error('エラーレスポンス:', response);
                                progressLog.append('<div style="color: #dc3232; margin-bottom: 3px;">✗ エラー: ' + (response.data || '不明なエラー') + '</div>');
                                progressLog.scrollTop(progressLog[0].scrollHeight);
                                progressText.html('<span style="color: #dc3232;">エラーが発生しました</span>');
                                btn.prop('disabled', false).html('<span class="dashicons dashicons-images-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括生成');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', xhr, status, error);
                            let errorMsg = '通信エラーが発生しました';
                            if (xhr.responseText) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.data) {
                                        errorMsg = response.data;
                                    }
                                } catch (e) {
                                    errorMsg = '通信エラー: ' + xhr.status + ' ' + error;
                                }
                            }
                            progressLog.append('<div style="color: #dc3232; margin-bottom: 3px;">✗ ' + errorMsg + '</div>');
                            progressLog.scrollTop(progressLog[0].scrollHeight);
                            progressText.html('<span style="color: #dc3232;">エラーが発生しました</span>');
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-images-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括生成');
                        }
                    });
                }
                
                processNext();
            });
            
            // 一括削除ボタンのクリックイベント
            $('.amig-bulk-delete-btn').on('click', function() {
                const btn = $(this);
                const postType = btn.data('post-type');
                const totalCount = btn.data('count');
                
                if (!confirm('本当にこのプラグインで生成したサムネイル画像を削除しますか？\n\n対象: ' + totalCount + ' 件\n※手動でアップロードした既存のサムネイルは削除されません。\n\nこの操作は取り消せません。')) {
                    return;
                }
                
                const progressContainer = $('.amig-bulk-progress[data-post-type="' + postType + '"]');
                const progressBar = progressContainer.find('.amig-progress-bar');
                const progressText = progressContainer.find('.amig-progress-text');
                const progressCount = progressContainer.find('.amig-progress-count');
                const progressLog = progressContainer.find('.amig-progress-log');
                
                btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>削除中...');
                progressContainer.show();
                progressLog.empty();
                progressBar.css('background', 'linear-gradient(90deg, #dc3232 0%, #a00 100%)');
                
                let processedCount = 0;
                
                function processNext() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'amig_bulk_delete',
                            post_type: postType,
                            nonce: '<?php echo wp_create_nonce('amig_bulk_delete'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                processedCount++;
                                const percentage = Math.round((processedCount / totalCount) * 100);
                                
                                progressBar.css('width', percentage + '%');
                                progressCount.text(processedCount + ' / ' + totalCount);
                                
                                if (response.data.skipped) {
                                    // スキップされた場合（プラグイン生成画像でない）
                                    progressLog.append('<div style="color: #f0ad4e; margin-bottom: 3px;">⊘ ID: ' + response.data.post_id + ' - ' + response.data.post_title + ' をスキップ（手動設定）</div>');
                                } else if (response.data.post_id) {
                                    progressLog.append('<div style="color: #dc3232; margin-bottom: 3px;">✓ ID: ' + response.data.post_id + ' - ' + response.data.post_title + ' のサムネイルを削除</div>');
                                } else if (response.data.message) {
                                    progressLog.append('<div style="color: #666; margin-bottom: 3px;">• ' + response.data.message + '</div>');
                                }
                                
                                progressLog.scrollTop(progressLog[0].scrollHeight);
                                
                                if (response.data.continue) {
                                    progressText.text('削除中... (' + processedCount + ' / ' + totalCount + ')');
                                    processNext();
                                } else {
                                    progressText.html('<span style="color: #2c7d2f;">✓ 削除完了しました！</span>');
                                    btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括削除');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                }
                            } else {
                                progressLog.append('<div style="color: #dc3232; margin-bottom: 3px;">✗ エラー: ' + response.data + '</div>');
                                progressLog.scrollTop(progressLog[0].scrollHeight);
                                progressText.html('<span style="color: #dc3232;">エラーが発生しました</span>');
                                btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括削除');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', xhr, status, error);
                            let errorMsg = '通信エラーが発生しました';
                            if (xhr.responseText) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.data) {
                                        errorMsg = response.data;
                                    }
                                } catch (e) {
                                    errorMsg = '通信エラー: ' + xhr.status + ' ' + error;
                                }
                            }
                            progressLog.append('<div style="color: #dc3232; margin-bottom: 3px;">✗ ' + errorMsg + '</div>');
                            progressLog.scrollTop(progressLog[0].scrollHeight);
                            progressText.html('<span style="color: #dc3232;">エラーが発生しました</span>');
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span> 一括削除');
                        }
                    });
                }
                
                processNext();
            });
        });
        </script>
        
        </div>
        <?php
    }
    
    /**
     * 生成済み画像管理ページの表示
     */
    public function manage_images_page() {
        if (!current_user_can('manage_options')) {
            wp_die('このページにアクセスする権限がありません。');
        }
        
        // 公開されている投稿タイプを取得
        $post_types = get_post_types(array('public' => true), 'objects');
        
        ?>
        <div class="wrap">
            <h1>生成済み画像管理</h1>
            <p>プラグインで生成したサムネイル画像を一覧表示し、個別に削除できます。</p>
            
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
                <h2 style="margin-top: 0;">投稿タイプを選択</h2>
                
                <?php foreach ($post_types as $post_type): ?>
                <?php
                global $wpdb;
                
                // プラグインで生成したサムネイルを持つ投稿を取得
                $posts_with_generated_images = $wpdb->get_results($wpdb->prepare("
                    SELECT DISTINCT p.ID, p.post_title, pm1.meta_value as thumbnail_id, pm3.meta_value as generated_date
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
                        INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.post_id AND pm2.meta_key = '_amig_generated' AND pm2.meta_value = '1'
                        LEFT JOIN {$wpdb->postmeta} pm3 ON pm1.meta_value = pm3.post_id AND pm3.meta_key = '_amig_generated_date'
                        WHERE p.post_type = %s AND p.post_status = 'publish'
                        ORDER BY p.ID DESC
                        LIMIT 100
                    ", $post_type->name));
                    
                    $count = count($posts_with_generated_images);
                    ?>
                    
                    <?php if ($count > 0): ?>
                        <div style="background: #f9f9f9; border: 1px solid #e0e0e0; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-admin-post" style="font-size: 20px;"></span>
                                <?php echo esc_html($post_type->label); ?>
                                <span style="background: #4A90E2; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: normal;">
                                    <?php echo $count; ?> 件
                                </span>
                            </h3>
                            
                            <div style="margin-bottom: 10px;">
                                <button type="button" class="button amig-select-all-btn" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                    すべて選択
                                </button>
                                <button type="button" class="button amig-deselect-all-btn" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                    すべて解除
                                </button>
                                <button type="button" class="button button-primary amig-delete-selected-btn" data-post-type="<?php echo esc_attr($post_type->name); ?>" style="margin-left: 10px; background: #dc3232; border-color: #dc3232;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> 選択項目を削除
                                </button>
                            </div>
                            
                            <div class="amig-generated-list" data-post-type="<?php echo esc_attr($post_type->name); ?>" style="max-height: 500px; overflow-y: auto; background: white; border: 1px solid #ddd; border-radius: 4px;">
                                <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                                    <thead>
                                        <tr>
                                            <td class="check-column" style="padding: 8px 10px;">
                                                <input type="checkbox" class="amig-select-all-checkbox" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                            </td>
                                            <th style="padding: 8px 10px;">ID</th>
                                            <th style="padding: 8px 10px;">サムネイル</th>
                                            <th style="padding: 8px 10px;">タイトル</th>
                                            <th style="padding: 8px 10px;">生成日時</th>
                                            <th style="padding: 8px 10px;">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($posts_with_generated_images as $post_item): ?>
                                            <tr data-post-id="<?php echo esc_attr($post_item->ID); ?>">
                                                <th class="check-column" style="padding: 8px 10px;">
                                                    <input type="checkbox" class="amig-post-checkbox" value="<?php echo esc_attr($post_item->ID); ?>" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                                </th>
                                                <td style="padding: 8px 10px;"><?php echo esc_html($post_item->ID); ?></td>
                                                <td style="padding: 8px 10px;">
                                                    <?php 
                                                    $thumbnail_url = wp_get_attachment_image_url($post_item->thumbnail_id, 'thumbnail');
                                                    if ($thumbnail_url): 
                                                    ?>
                                                        <img src="<?php echo esc_url($thumbnail_url); ?>" style="max-width: 60px; height: auto; border-radius: 3px;">
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 8px 10px;">
                                                    <a href="<?php echo get_edit_post_link($post_item->ID); ?>" target="_blank">
                                                        <?php echo esc_html($post_item->post_title); ?>
                                                    </a>
                                                </td>
                                                <td style="padding: 8px 10px;">
                                                    <?php 
                                                    if ($post_item->generated_date) {
                                                        echo esc_html(mysql2date('Y/m/d H:i', $post_item->generated_date));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 8px 10px;">
                                                    <button type="button" class="button button-small amig-delete-single-btn" 
                                                            data-post-id="<?php echo esc_attr($post_item->ID); ?>"
                                                            data-post-title="<?php echo esc_attr($post_item->post_title); ?>"
                                                            style="color: #dc3232;">
                                                        削除
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <script type="text/javascript">
                var ajaxurl = ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>';
                
                jQuery(document).ready(function($) {
                    // すべて選択
                    $('.amig-select-all-btn').on('click', function() {
                        const postType = $(this).data('post-type');
                        $('.amig-post-checkbox[data-post-type="' + postType + '"]').prop('checked', true);
                        $('.amig-select-all-checkbox[data-post-type="' + postType + '"]').prop('checked', true);
                });
                
                // すべて解除
                $('.amig-deselect-all-btn').on('click', function() {
                    const postType = $(this).data('post-type');
                    $('.amig-post-checkbox[data-post-type="' + postType + '"]').prop('checked', false);
                    $('.amig-select-all-checkbox[data-post-type="' + postType + '"]').prop('checked', false);
                });
                
                // ヘッダーのチェックボックス
                $('.amig-select-all-checkbox').on('change', function() {
                    const postType = $(this).data('post-type');
                    const isChecked = $(this).prop('checked');
                    $('.amig-post-checkbox[data-post-type="' + postType + '"]').prop('checked', isChecked);
                });
                
                // 個別削除
                $('.amig-delete-single-btn').on('click', function() {
                    const btn = $(this);
                    const postId = btn.data('post-id');
                    const postTitle = btn.data('post-title');
                    
                    if (!confirm('「' + postTitle + '」のサムネイル画像を削除しますか？\n\nこの操作は取り消せません。')) {
                        return;
                    }
                    
                    btn.prop('disabled', true).text('削除中...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'amig_delete_single',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('amig_delete_single'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('tr[data-post-id="' + postId + '"]').fadeOut(300, function() {
                                    $(this).remove();
                                });
                                alert('削除しました');
                            } else {
                                alert('エラー: ' + (response.data || '削除に失敗しました'));
                                btn.prop('disabled', false).text('削除');
                            }
                        },
                        error: function() {
                            alert('通信エラーが発生しました');
                            btn.prop('disabled', false).text('削除');
                        }
                    });
                });
                
                // 選択項目を削除
                $('.amig-delete-selected-btn').on('click', function() {
                    const btn = $(this);
                    const postType = btn.data('post-type');
                    const checkedBoxes = $('.amig-post-checkbox[data-post-type="' + postType + '"]:checked');
                    const postIds = checkedBoxes.map(function() { return $(this).val(); }).get();
                    
                    if (postIds.length === 0) {
                        alert('削除する投稿を選択してください');
                        return;
                    }
                    
                    if (!confirm(postIds.length + ' 件のサムネイル画像を削除しますか？\n\nこの操作は取り消せません。')) {
                        return;
                    }
                    
                    btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>削除中...');
                    
                    let deletedCount = 0;
                    
                    function deleteNext(index) {
                        if (index >= postIds.length) {
                            alert(deletedCount + ' 件削除しました');
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> 選択項目を削除');
                            location.reload();
                            return;
                        }
                        
                        const postId = postIds[index];
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'amig_delete_single',
                                post_id: postId,
                                nonce: '<?php echo wp_create_nonce('amig_delete_single'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    deletedCount++;
                                    $('tr[data-post-id="' + postId + '"]').fadeOut(200);
                                }
                                deleteNext(index + 1);
                            },
                            error: function() {
                                deleteNext(index + 1);
                            }
                        });
                    }
                    
                    deleteNext(0);
                });
            });
            </script>
        
        </div>
        <?php
    }
    
    /**
     * 一括画像生成のAJAXハンドラー
     */
    public function ajax_bulk_generate() {
        try {
            error_log('AMIG Bulk Generate: Start');
            error_log('POST data: ' . print_r($_POST, true));
            
            // nonce検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'amig_bulk_generate')) {
                error_log('AMIG Bulk Generate: Nonce verification failed');
                wp_send_json_error('セキュリティチェックに失敗しました');
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
            }
            
            $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
            
            if (empty($post_type)) {
                wp_send_json_error('投稿タイプが指定されていません');
            }
            
            // サムネイルがない投稿を1件取得
            // WordPress 6.x では meta_query の構造に注意が必要
            global $wpdb;
            
            // サムネイルがない、かつ生成失敗していない投稿IDを取得
            $post_id = $wpdb->get_var($wpdb->prepare("
                SELECT p.ID
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
                LEFT JOIN {$wpdb->postmeta} pm_failed ON p.ID = pm_failed.post_id AND pm_failed.meta_key = '_amig_generation_failed'
                WHERE p.post_type = %s 
                AND p.post_status = 'publish'
                AND (pm_thumb.meta_value IS NULL OR pm_thumb.meta_value = '')
                AND pm_failed.meta_id IS NULL
                ORDER BY p.ID ASC
                LIMIT 1
            ", $post_type));
            
            error_log('AMIG Bulk Generate: Found post ID: ' . ($post_id ? $post_id : 'none'));
            
            if (!$post_id) {
                error_log('AMIG Bulk Generate: No more posts to process');
                wp_send_json_success(array(
                    'continue' => false,
                    'message' => 'すべての投稿の処理が完了しました'
                ));
            }
            
            $post = get_post($post_id);
            
            error_log('AMIG Bulk Generate: Processing post ID: ' . $post->ID . ', Title: ' . $post->post_title);
            
            // 現在のサムネイル状態を確認
            $has_thumbnail_before = has_post_thumbnail($post->ID);
            error_log('AMIG Bulk Generate: Has thumbnail before: ' . ($has_thumbnail_before ? 'yes' : 'no'));
            
            // 画像を生成
            $thumbnail_id = $this->generate_thumbnail_image($post->ID);
            
            error_log('AMIG Bulk Generate: Generated thumbnail ID: ' . ($thumbnail_id ? $thumbnail_id : 'false'));
            
            // 生成後のサムネイル状態を確認
            $has_thumbnail_after = has_post_thumbnail($post->ID);
            error_log('AMIG Bulk Generate: Has thumbnail after: ' . ($has_thumbnail_after ? 'yes' : 'no'));
            
            if ($thumbnail_id && $thumbnail_id !== false) {
                error_log('AMIG Bulk Generate: Success for post ' . $post->ID);
                wp_send_json_success(array(
                    'continue' => true,
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'thumbnail_id' => $thumbnail_id
                ));
            } else {
                // 失敗した場合は、その投稿に「スキップ済み」フラグを設定して次へ進む
                error_log('AMIG Bulk Generate: Failed to generate thumbnail for post ' . $post->ID . ', marking as skipped and continuing...');
                
                // 一時的なメタデータを設定して、次回のクエリでこの投稿がスキップされるようにする
                update_post_meta($post->ID, '_amig_generation_failed', current_time('mysql'));
                
                wp_send_json_success(array(
                    'continue' => true,
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'error' => '画像生成に失敗（スキップ）',
                    'skipped' => true
                ));
            }
        } catch (Exception $e) {
            error_log('Automagic Bulk Generate Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('Automagic Bulk Generate Fatal Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('致命的なエラーが発生しました: ' . $e->getMessage());
        }
    }    /**
     * 一括画像削除のAJAXハンドラー
     */
    public function ajax_bulk_delete() {
        try {
            error_log('AMIG Bulk Delete: Start');
            
            // nonce検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'amig_bulk_delete')) {
                error_log('AMIG Bulk Delete: Nonce verification failed');
                wp_send_json_error('セキュリティチェックに失敗しました');
            }
            
            if (!current_user_can('manage_options')) {
                error_log('AMIG Bulk Delete: Permission denied');
                wp_send_json_error('権限がありません');
            }
            
            $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
            error_log('AMIG Bulk Delete: Post type = ' . $post_type);
            
            if (empty($post_type)) {
                error_log('AMIG Bulk Delete: Post type is empty');
                wp_send_json_error('投稿タイプが指定されていません');
            }
            
            // プラグインで生成したサムネイルがある投稿を1件取得
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare("
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.post_id AND pm2.meta_key = '_amig_generated' AND pm2.meta_value = '1'
                WHERE p.post_type = %s AND p.post_status = 'publish'
                LIMIT 1
            ", $post_type));
            
            error_log('AMIG Bulk Delete: Found post ID: ' . ($post_id ? $post_id : 'none'));
            
            if (!$post_id) {
                error_log('AMIG Bulk Delete: No more posts to process');
                wp_send_json_success(array(
                    'continue' => false,
                    'message' => 'すべてのサムネイルの削除が完了しました'
                ));
            }
            
            $post = get_post($post_id);
            
            error_log('AMIG Bulk Delete: Processing post ID: ' . $post->ID);
            
            // サムネイルIDを取得
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            
            error_log('AMIG Bulk Delete: Thumbnail ID: ' . $thumbnail_id);
            
            // このプラグインで生成した画像かチェック（既にクエリで確認済みだが念のため）
            $is_amig_generated = get_post_meta($thumbnail_id, '_amig_generated', true);
            
            if ($thumbnail_id && $is_amig_generated === '1') {
                // プラグインで生成した画像を削除
                // 投稿からサムネイルの関連付けを削除
                delete_post_meta($post->ID, '_thumbnail_id');
                
                // メディアファイルも削除
                wp_delete_attachment($thumbnail_id, true);
                
                error_log('AMIG Bulk Delete: Success for post ' . $post->ID);
                wp_send_json_success(array(
                    'continue' => true,
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'thumbnail_id' => $thumbnail_id
                ));
            } else {
                // 念のため：プラグインで生成した画像でない場合
                error_log('AMIG Bulk Delete: Skipping post ' . $post->ID . ' (not generated by plugin)');
                wp_send_json_success(array(
                    'continue' => true,
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'skipped' => true,
                    'error' => 'プラグイン生成画像ではないためスキップ'
                ));
            }
        } catch (Exception $e) {
            error_log('Automagic Bulk Delete Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('Automagic Bulk Delete Fatal Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('致命的なエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 個別削除のAJAXハンドラー
     */
    public function ajax_delete_single() {
        try {
            error_log('AMIG Delete Single: Start');
            
            // nonce検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'amig_delete_single')) {
                error_log('AMIG Delete Single: Nonce verification failed');
                wp_send_json_error('セキュリティチェックに失敗しました');
            }
            
            if (!current_user_can('manage_options')) {
                error_log('AMIG Delete Single: Permission denied');
                wp_send_json_error('権限がありません');
            }
            
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            error_log('AMIG Delete Single: Post ID = ' . $post_id);
            
            if (empty($post_id)) {
                error_log('AMIG Delete Single: Post ID is empty');
                wp_send_json_error('投稿IDが指定されていません');
            }
            
            // サムネイルIDを取得
            $thumbnail_id = get_post_thumbnail_id($post_id);
            
            if (!$thumbnail_id) {
                error_log('AMIG Delete Single: No thumbnail found for post ' . $post_id);
                wp_send_json_error('サムネイルが見つかりません');
            }
            
            error_log('AMIG Delete Single: Thumbnail ID: ' . $thumbnail_id);
            
            // このプラグインで生成した画像かチェック
            $is_amig_generated = get_post_meta($thumbnail_id, '_amig_generated', true);
            
            if ($is_amig_generated !== '1') {
                error_log('AMIG Delete Single: Not a plugin-generated image');
                wp_send_json_error('この画像はプラグインで生成されたものではありません');
            }
            
            // 投稿からサムネイルの関連付けを削除
            delete_post_meta($post_id, '_thumbnail_id');
            
            // メディアファイルも削除
            $deleted = wp_delete_attachment($thumbnail_id, true);
            
            if ($deleted) {
                error_log('AMIG Delete Single: Success for post ' . $post_id);
                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'thumbnail_id' => $thumbnail_id
                ));
            } else {
                error_log('AMIG Delete Single: Failed to delete attachment ' . $thumbnail_id);
                wp_send_json_error('画像の削除に失敗しました');
            }
        } catch (Exception $e) {
            error_log('AMIG Delete Single Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('AMIG Delete Single Fatal Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('致命的なエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * スキップ済みフラグをクリアするAJAXハンドラー
     */
    public function ajax_clear_skipped() {
        try {
            // nonce検証
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'amig_clear_skipped')) {
                wp_send_json_error('セキュリティチェックに失敗しました');
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
            }
            
            global $wpdb;
            
            // _amig_generation_failed メタを削除
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_amig_generation_failed'"
            );
            
            error_log('AMIG Clear Skipped: Cleared ' . $result . ' records');
            
            wp_send_json_success(array(
                'count' => $result
            ));
            
        } catch (Exception $e) {
            error_log('AMIG Clear Skipped Error: ' . $e->getMessage());
            wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
        }
    }
}

// プラグインの初期化
function automagic_image_generate_init() {
    return Automagic_Image_Generate::get_instance();
}

add_action('plugins_loaded', 'automagic_image_generate_init');

// AJAX処理（プレビュー生成用）
add_action('wp_ajax_amig_preview_generate', 'amig_preview_generate_callback');

function amig_preview_generate_callback() {
    try {
        check_ajax_referer('amig_generate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $preview_text = isset($_POST['preview_text']) ? sanitize_text_field($_POST['preview_text']) : '';
        
        if (empty($preview_text)) {
            wp_send_json_error('プレビューテキストを指定してください');
        }
        
        // 設定を取得
        $instance = Automagic_Image_Generate::get_instance();
        $options = get_option('amig_settings');
        
        $bg_color = isset($options['bg_color']) ? $options['bg_color'] : '#4A90E2';
        $text_color = isset($options['text_color']) ? $options['text_color'] : '#FFFFFF';
        $accent_color = isset($options['accent_color']) ? $options['accent_color'] : '#FFD700';
        $font_size = isset($options['font_size']) ? $options['font_size'] : 48;
        $font_weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        $image_style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        $bg_image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        $bg_image_opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.5;
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        
        // 画像を生成
        $method = new ReflectionMethod($instance, 'create_thumbnail_image');
        $method->setAccessible(true);
        $image_path = $method->invoke(
            $instance,
            $preview_text,
            $bg_color,
            $text_color,
            $accent_color,
            $font_size,
            $font_weight,
            $image_style,
            $bg_image_id,
            $bg_image_opacity,
            $line_height,
            $letter_spacing
        );
        
        if (!$image_path || !file_exists($image_path)) {
            error_log('Automagic Image Generate Preview: 画像の生成に失敗しました - path: ' . $image_path);
            wp_send_json_error('画像の生成に失敗しました。エラーログを確認してください。');
        }
        
        // アップロードディレクトリのURLを取得
        $upload_dir = wp_upload_dir();
        $filename = basename($image_path);
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        wp_send_json_success(array(
            'url' => $file_url,
            'path' => $image_path
        ));
        
    } catch (Exception $e) {
        error_log('Automagic Image Generate Preview Error: ' . $e->getMessage());
        wp_send_json_error('エラーが発生しました: ' . $e->getMessage());
    }
}

// プラグイン有効化時のフック
register_activation_hook(__FILE__, 'amig_plugin_activation');

/**
 * プラグイン有効化時の処理
 */
function amig_plugin_activation() {
    // フォントマネージャーの有効化処理を実行
    Automagic_Font_Manager::on_plugin_activation();
}
