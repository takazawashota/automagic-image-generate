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
require_once AMIG_PLUGIN_DIR . 'universal-plugin-updater.php';

// フォントマネージャーを読み込み
require_once AMIG_PLUGIN_DIR . 'font-manager.php';

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
        
        // フォントの状態をチェック
        $font_path = $this->get_japanese_font_path();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
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
            
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections($this->option_name);
                submit_button('設定を保存');
                ?>
            </form>
            
            <hr>
            
            <h2>プレビュー</h2>
            <div style="background: #f5f5f5; padding: 20px; border-radius: 5px;">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="amig-preview-text" value="サンプルタイトル" style="width: 400px; max-width: 100%;" />
                    <button type="button" id="amig-preview-btn" class="button button-primary">プレビューを生成</button>
                </div>
                <div id="amig-preview-loading" style="display: none; color: #666; margin: 15px 0;">
                    <span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
                    画像を生成中...
                </div>
                <div id="amig-preview-container" style="margin-top: 20px;">
                    <p style="color: #666;">上のボタンをクリックして、現在の設定でプレビュー画像を生成します。</p>
                </div>
            </div>

            <?php
            // フォントマネージャーなど他の機能が表示できるようにフックを提供
            do_action('amig_license_page_content');
            ?>
        </div>
        <?php
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        // トップレベルメニューのページスラッグに変更
        if ('toplevel_page_automagic-image-generate' !== $hook) {
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
            error_log('Automagic Image Generate: imagecreatetruecolor 失敗');
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
        error_log('Automagic Image Generate: draw_background_image 開始 - bg_image_id: ' . $bg_image_id . ', opacity: ' . $opacity);
        
        $bg_image_path = get_attached_file($bg_image_id);
        
        if (!$bg_image_path || !file_exists($bg_image_path)) {
            error_log('Automagic Image Generate: 背景画像が見つかりません - path: ' . $bg_image_path);
            return false;
        }
        
        error_log('Automagic Image Generate: 背景画像パス: ' . $bg_image_path);
        
        // 画像タイプを判定
        $image_info = getimagesize($bg_image_path);
        if (!$image_info) {
            error_log('Automagic Image Generate: getimagesize 失敗');
            return false;
        }
        
        $mime_type = $image_info['mime'];
        error_log('Automagic Image Generate: 画像タイプ: ' . $mime_type);
        
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
