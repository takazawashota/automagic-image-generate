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
if (!defined('AMIG_VERSION')) {
    define('AMIG_VERSION', '1.0.8');
}
if (!defined('AMIG_PLUGIN_DIR')) {
    define('AMIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('AMIG_PLUGIN_URL')) {
    define('AMIG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// 汎用アップデーター用の設定
if (!defined('PUM_PLUGIN_PREFIX'))      { define('PUM_PLUGIN_PREFIX',      'amig'); }
if (!defined('PUM_PLUGIN_NAME'))        { define('PUM_PLUGIN_NAME',        'Automagic Image Generate'); }
if (!defined('PUM_PLUGIN_SLUG'))        { define('PUM_PLUGIN_SLUG',        'automagic-image-generate'); }
if (!defined('PUM_PLUGIN_FILE'))        { define('PUM_PLUGIN_FILE',        __FILE__); }
if (!defined('PUM_LICENSE_SERVER_URL')) { define('PUM_LICENSE_SERVER_URL', 'https://sokulabo.com/products/wp-json/sokulabo/v1/license/verify'); }
if (!defined('PUM_LICENSE_PAGE_URL'))   { define('PUM_LICENSE_PAGE_URL',   'https://sokulabo.com/products/'); }
if (!defined('PUM_UPDATE_SERVER_URL'))  { define('PUM_UPDATE_SERVER_URL',  'https://sokulabo.com/products/plugins/automagic-image-generate/update-info.json'); }
if (!defined('PUM_LICENSE_PAGE_SLUG'))  { define('PUM_LICENSE_PAGE_SLUG',  'amig-license'); }

// アップデーターを読み込み
require_once __DIR__ . '/universal-plugin-updater.php';

// フォントマネージャーを読み込み
require_once __DIR__ . '/font-manager.php';

if (!class_exists('Automagic_Image_Generate')) {
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
        // 投稿編集画面メタボックス
        add_action('add_meta_boxes', array($this, 'register_post_metabox'));
        add_action('wp_ajax_amig_post_generate', array($this, 'ajax_post_generate'));
        add_action('wp_ajax_amig_post_delete', array($this, 'ajax_post_delete'));
    }
    
    /**
     * 管理画面メニューの追加
     */
    public function add_admin_menu() {
        // トップレベルメニューを追加
        add_menu_page(
            '自動画像生成',                           // ページタイトル
            '自動画像生成',                           // メニュータイトル
            'manage_options',                     // 権限
            'automagic-image-generate',           // メニュースラッグ
            array($this, 'settings_page'),        // コールバック関数
            'dashicons-images-alt2',              // アイコン
            3                                     // メニューの位置（3=ダッシュボードの下）
        );
        
        // サブメニュー（設定）を追加（最初のサブメニューはメインと同じページ）
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            '自動画像生成',                 // ページタイトル
            '自動画像生成',                                // メニュータイトル
            'manage_options',                     // 権限
            'automagic-image-generate',           // メニュースラッグ（親と同じ）
            array($this, 'settings_page')         // コールバック関数
        );
        
        // サブメニュー（一括生成）を追加
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            '一括生成',                            // ページタイトル
            '一括生成',                            // メニュータイトル
            'manage_options',                     // 権限
            'automagic-bulk-generate',            // メニュースラッグ
            array($this, 'bulk_generate_page')    // コールバック関数
        );
        
        // サブメニュー（生成済み画像管理）を追加
        add_submenu_page(
            'automagic-image-generate',           // 親メニューのスラッグ
            '生成済み画像',                        // ページタイトル
            '生成済み画像',                        // メニュータイトル
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

        add_settings_field(
            'text_align',
            'テキスト横揃え',
            array($this, 'text_align_callback'),
            $this->option_name,
            'amig_design_section'
        );

        add_settings_field(
            'text_vertical',
            'テキスト縦位置',
            array($this, 'text_vertical_callback'),
            $this->option_name,
            'amig_design_section'
        );

        add_settings_field(
            'max_chars',
            '最大表示文字数',
            array($this, 'max_chars_callback'),
            $this->option_name,
            'amig_design_section'
        );

        add_settings_field(
            'text_bg_opacity',
            'テキスト背景帯',
            array($this, 'text_bg_opacity_callback'),
            $this->option_name,
            'amig_design_section'
        );

        add_settings_field(
            'text_bg_color',
            'テキスト背景色',
            array($this, 'text_bg_color_callback'),
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

        if (isset($input['text_align'])) {
            $allowed = array('left', 'center', 'right');
            $sanitized['text_align'] = in_array($input['text_align'], $allowed, true) ? $input['text_align'] : 'center';
        }

        if (isset($input['text_vertical'])) {
            $allowed = array('top', 'middle', 'bottom');
            $sanitized['text_vertical'] = in_array($input['text_vertical'], $allowed, true) ? $input['text_vertical'] : 'middle';
        }

        if (isset($input['max_chars'])) {
            $sanitized['max_chars'] = min(200, max(0, absint($input['max_chars'])));
        }

        if (isset($input['text_bg_opacity'])) {
            $sanitized['text_bg_opacity'] = min(90, max(0, absint($input['text_bg_opacity'])));
        }

        if (isset($input['text_bg_color'])) {
            $sanitized['text_bg_color'] = sanitize_hex_color($input['text_bg_color']);
        }

        if (isset($input['text_bg_style'])) {
            $allowed = array('none', 'band', 'marker', 'block');
            $sanitized['text_bg_style'] = in_array($input['text_bg_style'], $allowed, true) ? $input['text_bg_style'] : 'band';
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
        $size = isset($options['font_size']) ? $options['font_size'] : 40;
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
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.8;
        
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

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'design';
        $options    = get_option($this->option_name, array());
        $font_path  = $this->get_japanese_font_path();

        // ── デフォルト値 ──
        $bg_color        = isset($options['bg_color'])        ? $options['bg_color']        : '#4A90E2';
        $text_color      = isset($options['text_color'])      ? $options['text_color']      : '#FFFFFF';
        $accent_color    = isset($options['accent_color'])    ? $options['accent_color']    : '#FFD700';
        $font_size       = isset($options['font_size'])       ? (int)$options['font_size']  : 40;
        $font_weight     = isset($options['font_weight'])     ? $options['font_weight']     : 'normal';
        $image_style     = isset($options['image_style'])     ? $options['image_style']     : 'modern';
        $bg_image_id     = isset($options['bg_image'])        ? (int)$options['bg_image']   : 0;
        $bg_image_opacity= isset($options['bg_image_opacity'])? (int)$options['bg_image_opacity'] : 30;
        $line_height     = isset($options['line_height'])     ? (float)$options['line_height'] : 1.8;
        $letter_spacing  = isset($options['letter_spacing'])  ? (int)$options['letter_spacing'] : 0;
        $text_align      = isset($options['text_align'])      ? $options['text_align']      : 'center';
        $text_vertical   = isset($options['text_vertical'])   ? $options['text_vertical']   : 'middle';
        $max_chars       = isset($options['max_chars'])       ? (int)$options['max_chars']  : 0;
        $text_bg_opacity = isset($options['text_bg_opacity']) ? (int)$options['text_bg_opacity'] : 0;
        $text_bg_color   = isset($options['text_bg_color'])   ? $options['text_bg_color']   : '#000000';
        $text_bg_style   = isset($options['text_bg_style'])   ? $options['text_bg_style']   : 'band';
        $enable_auto     = isset($options['enable_auto_generation']) ? (int)$options['enable_auto_generation'] : 0;
        $post_types_sel  = isset($options['post_types'])      ? (array)$options['post_types'] : array('post');

        $bg_image_url = $bg_image_id ? wp_get_attachment_url($bg_image_id) : '';

        $styles_meta = array(
            'frame'    => array('label' => 'フレーム',    'desc' => '枠線＋余白'),
            'split'    => array('label' => '二分割',      'desc' => '左カラー／右テキスト'),
            'badge'    => array('label' => 'バッジ帯',    'desc' => '下部横帯＋タイトル'),
            'diagonal' => array('label' => '斜め分割',    'desc' => '対角カラーブロック'),
            'modern'   => array('label' => 'モダン',      'desc' => 'グラデーション＋装飾'),
            'gradient' => array('label' => 'グラデーション','desc' => '上下グラデ'),
            'minimal'  => array('label' => 'ミニマル',    'desc' => '白背景＋縦ライン'),
            'simple'   => array('label' => 'シンプル',    'desc' => '単色フラット'),
        );

        $weights = array(
            'light'  => '細字',
            'normal' => '標準',
            'medium' => '中太',
            'bold'   => '太字',
            'black'  => '極太',
        );

        $all_post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap amig-wrap">

        <!-- ヘッダー -->
        <div class="amig-page-header">
            <h1 class="amig-page-title">
                <span class="dashicons dashicons-images-alt2"></span>
                Automagic Image Generate
            </h1>
        </div>

        <!-- タブ -->
        <div class="amig-tabs">
            <a href="?page=automagic-image-generate&tab=design" class="amig-tab <?php echo $active_tab === 'design' ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-admin-customizer"></span> デザイン設定
            </a>
            <a href="?page=automagic-image-generate&tab=general" class="amig-tab <?php echo $active_tab === 'general' ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-admin-settings"></span> 基本設定
            </a>
            <a href="?page=automagic-image-generate&tab=fonts" class="amig-tab <?php echo $active_tab === 'fonts' ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-media-text"></span> フォント
            </a>
        </div>

        <?php if ($active_tab === 'design'): ?>
        <!-- ══════════════════════════════════════
             デザイン設定タブ（2カラム）
        ══════════════════════════════════════ -->
        <form method="post" action="options.php" id="amig-design-form">
            <?php settings_fields($this->option_name); ?>
            <div class="amig-two-col">

                <!-- 左：コントロール -->
                <div>

                    <!-- スタイル選択 -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-layout"></span> スタイル
                        </div>
                        <div class="amig-card-body">
                            <div class="amig-style-grid">
                                <?php foreach ($styles_meta as $val => $meta): ?>
                                <label class="amig-style-card">
                                    <input type="radio"
                                           name="<?php echo esc_attr($this->option_name); ?>[image_style]"
                                           value="<?php echo esc_attr($val); ?>"
                                           <?php checked($image_style, $val); ?>>
                                    <div class="amig-style-card-inner">
                                        <canvas class="amig-style-thumb amig-style-preview-canvas"
                                                data-style="<?php echo esc_attr($val); ?>"
                                                width="320" height="168"></canvas>
                                        <div class="amig-style-label"><?php echo esc_html($meta['label']); ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- カラー -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-color-picker"></span> カラー
                        </div>
                        <div class="amig-card-body">
                            <div class="amig-color-row">
                                <div class="amig-color-field">
                                    <label>背景色</label>
                                    <div class="amig-color-wrap">
                                        <input type="color" id="amig-bg-color"
                                               name="<?php echo esc_attr($this->option_name); ?>[bg_color]"
                                               value="<?php echo esc_attr($bg_color); ?>">
                                        <input class="amig-color-hex" type="text"
                                               value="<?php echo esc_attr($bg_color); ?>"
                                               maxlength="7" data-for="amig-bg-color">
                                    </div>
                                </div>
                                <div class="amig-color-field">
                                    <label>テキスト色</label>
                                    <div class="amig-color-wrap">
                                        <input type="color" id="amig-text-color"
                                               name="<?php echo esc_attr($this->option_name); ?>[text_color]"
                                               value="<?php echo esc_attr($text_color); ?>">
                                        <input class="amig-color-hex" type="text"
                                               value="<?php echo esc_attr($text_color); ?>"
                                               maxlength="7" data-for="amig-text-color">
                                    </div>
                                </div>
                                <div class="amig-color-field">
                                    <label>アクセント色</label>
                                    <div class="amig-color-wrap">
                                        <input type="color" id="amig-accent-color"
                                               name="<?php echo esc_attr($this->option_name); ?>[accent_color]"
                                               value="<?php echo esc_attr($accent_color); ?>">
                                        <input class="amig-color-hex" type="text"
                                               value="<?php echo esc_attr($accent_color); ?>"
                                               maxlength="7" data-for="amig-accent-color">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- フォント -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-editor-textcolor"></span> フォント
                        </div>
                        <div class="amig-card-body">
                            <div class="amig-field">
                                <span class="amig-label">太さ</span>
                                <div class="amig-weight-group">
                                    <?php foreach ($weights as $wval => $wlabel): ?>
                                    <label class="amig-weight-btn">
                                        <input type="radio"
                                               name="<?php echo esc_attr($this->option_name); ?>[font_weight]"
                                               value="<?php echo esc_attr($wval); ?>"
                                               <?php checked($font_weight, $wval); ?>>
                                        <span class="amig-weight-btn-label"><?php echo esc_html($wlabel); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="amig-field">
                                <label class="amig-label" for="amig-font-size-range">
                                    サイズ
                                </label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-font-size-range"
                                           min="20" max="80" step="2"
                                           value="<?php echo esc_attr($font_size); ?>">
                                    <span class="amig-range-value" id="amig-font-size-val"><?php echo esc_html($font_size); ?>px</span>
                                </div>
                                <input type="hidden" id="amig-font-size"
                                       name="<?php echo esc_attr($this->option_name); ?>[font_size]"
                                       value="<?php echo esc_attr($font_size); ?>">
                            </div>
                            <div class="amig-field">
                                <label class="amig-label" for="amig-line-height-range">行間</label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-line-height-range"
                                           min="0.8" max="3.0" step="0.1"
                                           value="<?php echo esc_attr($line_height); ?>">
                                    <span class="amig-range-value" id="amig-line-height-val"><?php echo esc_html($line_height); ?></span>
                                </div>
                                <input type="hidden" id="amig-line-height"
                                       name="<?php echo esc_attr($this->option_name); ?>[line_height]"
                                       value="<?php echo esc_attr($line_height); ?>">
                            </div>
                            <div class="amig-field">
                                <label class="amig-label" for="amig-letter-spacing-range">文字間隔</label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-letter-spacing-range"
                                           min="-10" max="30" step="1"
                                           value="<?php echo esc_attr($letter_spacing); ?>">
                                    <span class="amig-range-value" id="amig-letter-spacing-val"><?php echo esc_html($letter_spacing); ?>px</span>
                                </div>
                                <input type="hidden" id="amig-letter-spacing"
                                       name="<?php echo esc_attr($this->option_name); ?>[letter_spacing]"
                                       value="<?php echo esc_attr($letter_spacing); ?>">
                            </div>
                            <div class="amig-field">
                                <span class="amig-label">横揃え</span>
                                <div class="amig-align-group">
                                    <?php foreach (array('left' => 'dashicons-editor-alignleft', 'center' => 'dashicons-editor-aligncenter', 'right' => 'dashicons-editor-alignright') as $av => $ai): ?>
                                    <label class="amig-align-btn <?php echo $text_align === $av ? 'is-active' : ''; ?>">
                                        <input type="radio"
                                               name="<?php echo esc_attr($this->option_name); ?>[text_align]"
                                               value="<?php echo esc_attr($av); ?>"
                                               <?php checked($text_align, $av); ?>>
                                        <span class="dashicons <?php echo esc_attr($ai); ?>"></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- テキスト配置 -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-align-center"></span> テキスト配置
                        </div>
                        <div class="amig-card-body">
                            <div class="amig-field">
                                <span class="amig-label">縦位置</span>
                                <div class="amig-align-group">
                                    <?php foreach (array('top' => '上', 'middle' => '中', 'bottom' => '下') as $vv => $vl): ?>
                                    <label class="amig-align-btn amig-align-btn-text <?php echo $text_vertical === $vv ? 'is-active' : ''; ?>">
                                        <input type="radio"
                                               name="<?php echo esc_attr($this->option_name); ?>[text_vertical]"
                                               value="<?php echo esc_attr($vv); ?>"
                                               <?php checked($text_vertical, $vv); ?>>
                                        <?php echo esc_html($vl); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="amig-field">
                                <label class="amig-label" for="amig-max-chars-range">
                                    最大表示文字数 <span class="amig-description" style="display:inline;">（0＝制限なし）</span>
                                </label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-max-chars-range"
                                           min="0" max="200" step="5"
                                           value="<?php echo esc_attr($max_chars); ?>">
                                    <span class="amig-range-value" id="amig-max-chars-val">
                                        <?php echo $max_chars > 0 ? esc_html($max_chars) . '文字' : '制限なし'; ?>
                                    </span>
                                </div>
                                <input type="hidden" id="amig-max-chars"
                                       name="<?php echo esc_attr($this->option_name); ?>[max_chars]"
                                       value="<?php echo esc_attr($max_chars); ?>">
                                <div class="amig-description">長いタイトルを自動で省略します。末尾に「…」が付きます。</div>
                            </div>
                        </div>
                    </div>

                    <!-- テキスト装飾 -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-text-page"></span> テキスト装飾
                        </div>
                        <div class="amig-card-body" style="padding-top:36px;">
                            <div class="amig-field">
                                <label class="amig-label">背景スタイル</label>
                                <div class="amig-align-group">
                                    <?php
                                    $bg_style_options = array(
                                        'none'   => 'なし',
                                        'band'   => '横帯',
                                        'marker' => 'マーカー',
                                        'block'  => 'ブロック',
                                    );
                                    foreach ($bg_style_options as $val => $lbl): ?>
                                    <label class="amig-align-btn amig-align-btn-text<?php echo $text_bg_style === $val ? ' is-active' : ''; ?>">
                                        <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[text_bg_style]"
                                               value="<?php echo esc_attr($val); ?>"
                                               <?php checked($text_bg_style, $val); ?>>
                                        <?php echo esc_html($lbl); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="amig-field">
                                <label class="amig-label">背景帯の色</label>
                                <div class="amig-color-row" style="grid-template-columns:1fr;">
                                    <div class="amig-color-field">
                                        <div class="amig-color-wrap">
                                            <input type="color" id="amig-text-bg-color"
                                                   name="<?php echo esc_attr($this->option_name); ?>[text_bg_color]"
                                                   value="<?php echo esc_attr($text_bg_color); ?>">
                                            <input class="amig-color-hex" type="text"
                                                   value="<?php echo esc_attr($text_bg_color); ?>"
                                                   maxlength="7" data-for="amig-text-bg-color">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="amig-field">
                                <label class="amig-label" for="amig-text-bg-opacity-range">
                                    背景帯の濃さ <span class="amig-description" style="display:inline;">（0＝なし）</span>
                                </label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-text-bg-opacity-range"
                                           name="<?php echo esc_attr($this->option_name); ?>[text_bg_opacity]"
                                           min="0" max="90" step="5"
                                           value="<?php echo esc_attr($text_bg_opacity); ?>">
                                    <span class="amig-range-value" id="amig-text-bg-opacity-val">
                                        <?php echo $text_bg_opacity > 0 ? esc_html($text_bg_opacity) . '%' : 'なし'; ?>
                                    </span>
                                </div>
                                <div class="amig-description">テキストの後ろに半透明の帯を描画してテキストを読みやすくします。</div>
                            </div>
                        </div>
                    </div>

                    <!-- 背景画像 -->
                    <div class="amig-card" style="margin-bottom:20px;">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-format-image"></span> 背景画像（オプション）
                        </div>
                        <div class="amig-card-body">
                            <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[bg_image]"
                                   id="amig-bg-image-id" value="<?php echo esc_attr($bg_image_id); ?>">

                            <?php if ($bg_image_url): ?>
                            <div class="amig-upload-preview" id="amig-bg-preview-wrap">
                                <img src="<?php echo esc_url($bg_image_url); ?>" alt="">
                                <button type="button" class="amig-upload-remove" id="amig-remove-bg-image">削除</button>
                            </div>
                            <?php else: ?>
                            <div class="amig-upload-preview" id="amig-bg-preview-wrap" style="display:none;">
                                <img src="" alt="" id="amig-bg-preview-img">
                                <button type="button" class="amig-upload-remove" id="amig-remove-bg-image">削除</button>
                            </div>
                            <?php endif; ?>

                            <div class="amig-upload-area" id="amig-upload-bg-image" <?php echo $bg_image_url ? 'style="display:none;"' : ''; ?>>
                                <span class="dashicons dashicons-upload" style="font-size:24px;width:24px;height:24px;color:#9ca3af;margin-bottom:6px;display:block;margin-inline:auto;"></span>
                                <p style="margin:0;font-size:13px;color:var(--amig-muted);">クリックして画像を選択</p>
                            </div>

                            <div class="amig-field" style="margin-top:14px;">
                                <label class="amig-label" for="amig-opacity-range">
                                    重ね合わせ透明度
                                </label>
                                <div class="amig-range-row">
                                    <input type="range" id="amig-opacity-range"
                                           name="<?php echo esc_attr($this->option_name); ?>[bg_image_opacity]"
                                           min="0" max="100" step="5"
                                           value="<?php echo esc_attr($bg_image_opacity); ?>">
                                    <span class="amig-range-value" id="amig-opacity-val"><?php echo esc_html($bg_image_opacity); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /左カラム -->

                <!-- 右：プレビュー（スティッキー） -->
                <div class="amig-preview-panel">
                    <div class="amig-card">
                        <div class="amig-card-header">
                            <span class="dashicons dashicons-visibility"></span> リアルタイムプレビュー
                        </div>
                        <div class="amig-card-body" style="padding:12px;">
                            <div class="amig-preview-box">
                                <!-- 画像エリア -->
                                <div class="amig-preview-canvas-wrap" id="amig-preview-wrap">
                                    <img src="" alt="プレビュー" class="amig-preview-image" id="amig-preview-image" style="display:none;">
                                    <div class="amig-preview-placeholder" id="amig-preview-placeholder">
                                        <span class="dashicons dashicons-format-image"></span>
                                        <span>設定を変更するとプレビューが表示されます</span>
                                    </div>
                                    <div class="amig-preview-overlay">
                                        <div class="amig-preview-spinner"></div>
                                    </div>
                                    <span class="amig-preview-size-badge">1200 × 630</span>
                                </div>
                                <!-- フッター -->
                                <div class="amig-preview-footer">
                                    <div class="amig-preview-footer-input">
                                        <input type="text" class="amig-preview-text-input"
                                               id="amig-preview-text"
                                               value="日本語タイトルのサンプル"
                                               placeholder="プレビュー用テキストを入力…">
                                        <button type="button" id="amig-refresh-preview" class="amig-btn amig-btn-icon" title="プレビューを今すぐ更新">
                                            <span class="dashicons dashicons-update"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="amig-save-bar" style="position:relative;padding:10px 16px;background:#fafafa;border-top:1px solid #e2e4e7;border-radius:0 0 8px 8px;text-align:right;white-space:nowrap;line-height:32px;">
                            <span class="amig-save-msg" id="amig-save-msg" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);margin:0;padding:0;">
                                <span class="dashicons dashicons-yes-alt"></span> 保存しました
                            </span>
                            <span class="amig-preview-status" id="amig-preview-status" style="display:inline-block;vertical-align:middle;white-space:nowrap;margin-right:4px;">
                                <span class="dashicons dashicons-clock"></span> 自動更新
                            </span>
                            <a href="#" id="amig-download-btn" class="amig-btn amig-btn-secondary" download="thumbnail-preview.jpg" style="display:none;vertical-align:middle;white-space:nowrap;">
                                <span class="dashicons dashicons-download"></span> ダウンロード
                            </a>&#8203;<button type="submit" class="amig-btn amig-btn-primary" style="display:inline-flex;width:auto;vertical-align:middle;float:none;">
                                <span class="dashicons dashicons-saved"></span> 設定を保存
                            </button>
                        </div>
                    </div>
                </div><!-- /右カラム -->

            </div>
        </form>

        <?php elseif ($active_tab === 'general'): ?>
        <!-- ══════════════════════════════════════
             基本設定タブ
        ══════════════════════════════════════ -->
        <form method="post" action="options.php">
            <?php settings_fields($this->option_name); ?>

            <div class="amig-card" style="max-width:680px;">
                <div class="amig-card-header">
                    <span class="dashicons dashicons-admin-settings"></span> 自動生成の設定
                </div>
                <div class="amig-card-body">

                    <div class="amig-field">
                        <label class="amig-toggle">
                            <input type="hidden"   name="<?php echo esc_attr($this->option_name); ?>[enable_auto_generation]" value="0">
                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_auto_generation]"
                                   value="1" <?php checked(1, $enable_auto); ?>>
                            <span class="amig-toggle-track"></span>
                            <span class="amig-toggle-label">投稿保存時に自動でサムネイルを生成する</span>
                        </label>
                        <div class="amig-description" style="margin-top:8px;">サムネイルが未設定の公開投稿にのみ生成されます。</div>
                    </div>

                    <hr class="amig-divider">

                    <div class="amig-field">
                        <span class="amig-label">対象の投稿タイプ</span>
                        <div class="amig-checkbox-group">
                            <?php foreach ($all_post_types as $pt): ?>
                            <label class="amig-checkbox-item">
                                <input type="checkbox"
                                       name="<?php echo esc_attr($this->option_name); ?>[post_types][]"
                                       value="<?php echo esc_attr($pt->name); ?>"
                                       <?php checked(in_array($pt->name, $post_types_sel, true)); ?>>
                                <?php echo esc_html($pt->label); ?>
                                <span style="color:var(--amig-muted);font-size:12px;">(<?php echo esc_html($pt->name); ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
                <div class="amig-save-bar">
                    <span></span>
                    <button type="submit" class="amig-btn amig-btn-primary">
                        <span class="dashicons dashicons-saved"></span> 設定を保存
                    </button>
                </div>
            </div>
        </form>

        <?php elseif ($active_tab === 'fonts'): ?>
        <!-- ══════════════════════════════════════
             フォントタブ
        ══════════════════════════════════════ -->
        <div style="max-width:680px;">

            <div class="amig-font-status <?php echo $font_path ? 'is-ok' : 'is-warn'; ?>">
                <span class="amig-font-status-icon"><?php echo $font_path ? '✅' : '⚠️'; ?></span>
                <div>
                    <strong style="display:block;margin-bottom:4px;">
                        <?php echo $font_path ? '日本語フォントが利用可能です' : '日本語フォントが見つかりません'; ?>
                    </strong>
                    <?php if ($font_path): ?>
                        <span style="font-size:13px;color:var(--amig-muted);">
                            使用中: <code><?php echo esc_html(basename($font_path)); ?></code>
                        </span>
                    <?php else: ?>
                        <p style="margin:4px 0 8px;font-size:13px;color:var(--amig-muted);">
                            配置先: <code><?php echo esc_html(AMIG_PLUGIN_DIR . 'fonts/'); ?></code>
                        </p>
                        <a href="https://fonts.google.com/noto/specimen/Noto+Sans+JP"
                           target="_blank" class="amig-btn amig-btn-secondary" style="font-size:12px;padding:5px 12px;">
                            <span class="dashicons dashicons-download"></span> Google Fonts からダウンロード
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // フォントファイル一覧
            $required_fonts = array(
                'NotoSansJP-Light.ttf'   => '細字 (Light)',
                'NotoSansJP-Regular.ttf' => '標準 (Regular)',
                'NotoSansJP-Medium.ttf'  => '中太 (Medium)',
                'NotoSansJP-Bold.ttf'    => '太字 (Bold)',
                'NotoSansJP-Black.ttf'   => '極太 (Black)',
            );
            $fonts_dir = AMIG_PLUGIN_DIR . 'fonts/';
            ?>
            <div class="amig-card">
                <div class="amig-card-header">
                    <span class="dashicons dashicons-list-view"></span> フォントファイル一覧
                </div>
                <div class="amig-card-body" style="padding:0;">
                    <table class="amig-font-table">
                        <thead>
                            <tr><th>ファイル名</th><th>ウェイト</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($required_fonts as $file => $label): ?>
                            <?php $exists = file_exists($fonts_dir . $file); ?>
                            <tr>
                                <td><code style="font-size:12px;"><?php echo esc_html($file); ?></code></td>
                                <td><?php echo esc_html($label); ?></td>
                                <td>
                                    <?php if ($exists): ?>
                                        <span class="amig-badge amig-badge-success">✓ 存在する</span>
                                    <?php else: ?>
                                        <span class="amig-badge amig-badge-warning">✕ 未配置</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php do_action('amig_license_page_content'); ?>

        </div>
        <?php endif; ?>

        </div><!-- /amig-wrap -->
        <?php
    }

    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = array(
            'toplevel_page_automagic-image-generate',
            'automagic-image-generate_page_automagic-bulk-generate',
            'automagic-image-generate_page_automagic-manage-images',
            'post.php',
            'post-new.php',
        );

        // slugベースの部分一致でも許可（メニュー名変更時のフック名ずれ対策）
        $is_amig_page = in_array($hook, $allowed_hooks, true)
            || strpos($hook, 'automagic-image-generate') !== false
            || strpos($hook, 'automagic-bulk-generate') !== false
            || strpos($hook, 'automagic-manage-images') !== false;

        if (!$is_amig_page) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'amig-admin-style',
            AMIG_PLUGIN_URL . 'assets/admin.css',
            array(),
            AMIG_VERSION
        );

        wp_enqueue_script(
            'amig-admin-script',
            AMIG_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            AMIG_VERSION,
            true
        );

        wp_localize_script('amig-admin-script', 'amigAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('amig_generate_nonce'),
        ));
    }

    /* ================================================================
       投稿編集画面 メタボックス
    ================================================================ */

    /**
     * 対象のすべての公開投稿タイプにメタボックスを登録
     */
    public function register_post_metabox() {
        $options      = get_option($this->option_name);
        $target_types = isset($options['post_types']) ? (array) $options['post_types'] : array('post');
        // 対象が空でも最低限 post には表示
        if (empty($target_types)) {
            $target_types = array('post');
        }
        foreach ($target_types as $pt) {
            add_meta_box(
                'amig-post-metabox',
                '<span class="dashicons dashicons-images-alt2" style="vertical-align:middle;margin-right:4px;"></span> OGP サムネイル',
                array($this, 'render_post_metabox'),
                $pt,
                'side',
                'high'
            );
        }
    }

    /**
     * メタボックスの HTML を描画
     */
    public function render_post_metabox($post) {
        $thumb_id    = get_post_thumbnail_id($post->ID);
        $thumb_url   = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
        $is_generated = $thumb_id ? (bool) get_post_meta($thumb_id, '_amig_generated', true) : false;
        $nonce       = wp_create_nonce('amig_post_action_' . $post->ID);
        ?>
        <div class="amig-metabox" id="amig-metabox-<?php echo esc_attr($post->ID); ?>" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">

            <!-- サムネイルプレビュー -->
            <div class="amig-mb-preview-wrap <?php echo $thumb_url ? 'has-image' : 'no-image'; ?>" id="amig-mb-preview-wrap-<?php echo esc_attr($post->ID); ?>">
                <?php if ($thumb_url): ?>
                    <img src="<?php echo esc_url($thumb_url); ?>" alt="" class="amig-mb-preview-img" id="amig-mb-img-<?php echo esc_attr($post->ID); ?>">
                <?php else: ?>
                    <div class="amig-mb-empty" id="amig-mb-empty-<?php echo esc_attr($post->ID); ?>">
                        <span class="dashicons dashicons-format-image"></span>
                        <span>未設定</span>
                    </div>
                <?php endif; ?>
                <div class="amig-mb-overlay" id="amig-mb-overlay-<?php echo esc_attr($post->ID); ?>">
                    <div class="amig-mb-spinner"></div>
                </div>
            </div>

            <!-- ステータス -->
            <div class="amig-mb-status" id="amig-mb-status-<?php echo esc_attr($post->ID); ?>">
                <?php if ($is_generated): ?>
                    <span class="amig-badge amig-badge-success">このプラグインで生成済み</span>
                <?php elseif ($thumb_id): ?>
                    <span class="amig-badge amig-badge-info">手動設定済み</span>
                <?php else: ?>
                    <span class="amig-badge amig-badge-warning">サムネイル未設定</span>
                <?php endif; ?>
            </div>

            <!-- メッセージ -->
            <div class="amig-mb-msg" id="amig-mb-msg-<?php echo esc_attr($post->ID); ?>" style="display:none;"></div>

            <!-- アクションボタン -->
            <div class="amig-mb-actions">
                <?php if ($thumb_url): ?>
                <button type="button" class="amig-btn amig-btn-secondary amig-mb-btn-generate"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-update"></span> 再生成
                </button>
                <?php else: ?>
                <button type="button" class="amig-btn amig-btn-primary amig-mb-btn-generate"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-images-alt2"></span> 生成する
                </button>
                <?php endif; ?>

                <?php if ($is_generated): ?>
                <button type="button" class="amig-btn amig-btn-danger amig-mb-btn-delete"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-trash"></span> 削除
                </button>
                <?php endif; ?>
            </div>

            <p class="amig-mb-note">タイトルを使って 1200×630px の OGP 画像を生成します。</p>
        </div>
        <?php
    }

    /**
     * AJAX: 個別投稿のサムネイル生成
     */
    public function ajax_post_generate() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        check_ajax_referer('amig_post_action_' . $post_id, 'nonce');

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('権限がありません');
        }
        if (!$post_id) {
            wp_send_json_error('無効な投稿IDです');
        }

        // 既存のプラグイン生成サムネイルを削除してから再生成
        $old_thumb_id = get_post_thumbnail_id($post_id);
        if ($old_thumb_id && get_post_meta($old_thumb_id, '_amig_generated', true)) {
            delete_post_thumbnail($post_id);
            wp_delete_attachment($old_thumb_id, true);
        }

        $attachment_id = $this->generate_thumbnail_image($post_id);
        if (!$attachment_id) {
            wp_send_json_error('画像の生成に失敗しました。フォントファイルが配置されているか確認してください。');
        }

        $thumb_url = wp_get_attachment_image_url($attachment_id, 'medium');
        wp_send_json_success(array(
            'thumb_url'     => $thumb_url,
            'attachment_id' => $attachment_id,
            'message'       => '生成しました',
        ));
    }

    /**
     * AJAX: 個別投稿のプラグイン生成サムネイル削除
     */
    public function ajax_post_delete() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        check_ajax_referer('amig_post_action_' . $post_id, 'nonce');

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('権限がありません');
        }

        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id || !get_post_meta($thumb_id, '_amig_generated', true)) {
            wp_send_json_error('このプラグインで生成されたサムネイルではありません');
        }

        delete_post_thumbnail($post_id);
        wp_delete_attachment($thumb_id, true);

        wp_send_json_success(array('message' => '削除しました'));
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
        $font_size = isset($options['font_size']) ? $options['font_size'] : 40;
        $font_weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        $image_style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        $bg_image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        $bg_image_opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.8;
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        $text_align    = isset($options['text_align'])    ? $options['text_align']    : 'center';
        $text_vertical = isset($options['text_vertical']) ? $options['text_vertical'] : 'middle';
        $max_chars     = isset($options['max_chars'])     ? (int)$options['max_chars'] : 0;
        $text_bg_opacity = isset($options['text_bg_opacity']) ? (int)$options['text_bg_opacity'] : 0;
        $text_bg_color = isset($options['text_bg_color']) ? $options['text_bg_color'] : '#000000';
        $text_bg_style = isset($options['text_bg_style']) ? $options['text_bg_style'] : 'band';
        
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
            $letter_spacing,
            $text_align,
            $text_vertical,
            $max_chars,
            $text_bg_opacity,
            $text_bg_color,
            $text_bg_style
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
        $font_size = isset($options['font_size']) ? $options['font_size'] : 40;
        $font_weight = isset($options['font_weight']) ? $options['font_weight'] : 'normal';
        $image_style = isset($options['image_style']) ? $options['image_style'] : 'modern';
        $bg_image_id = isset($options['bg_image']) ? $options['bg_image'] : 0;
        $bg_image_opacity = isset($options['bg_image_opacity']) ? $options['bg_image_opacity'] : 30;
        $line_height = isset($options['line_height']) ? $options['line_height'] : 1.8;
        $letter_spacing = isset($options['letter_spacing']) ? $options['letter_spacing'] : 0;
        $text_align    = isset($options['text_align'])    ? $options['text_align']    : 'center';
        $text_vertical = isset($options['text_vertical']) ? $options['text_vertical'] : 'middle';
        $max_chars     = isset($options['max_chars'])     ? (int)$options['max_chars'] : 0;
        $text_bg_opacity = isset($options['text_bg_opacity']) ? (int)$options['text_bg_opacity'] : 0;
        $text_bg_color = isset($options['text_bg_color']) ? $options['text_bg_color'] : '#000000';
        $text_bg_style = isset($options['text_bg_style']) ? $options['text_bg_style'] : 'band';
        
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
            $letter_spacing,
            $text_align,
            $text_vertical,
            $max_chars,
            $text_bg_opacity,
            $text_bg_color,
            $text_bg_style
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
    public function create_thumbnail_image($title, $bg_color, $text_color, $accent_color, $font_size, $font_weight, $style, $bg_image_id = 0, $bg_image_opacity = 30, $line_height = 1.8, $letter_spacing = 0, $text_align = 'center', $text_vertical = 'middle', $max_chars = 0, $text_bg_opacity = 0, $text_bg_color = '#000000', $text_bg_style = 'band') {
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
                case 'frame':
                    $this->draw_frame_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb);
                    break;
                case 'split':
                    $this->draw_split_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb);
                    break;
                case 'badge':
                    $this->draw_badge_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb);
                    break;
                case 'diagonal':
                    $this->draw_diagonal_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb);
                    break;
                case 'gradient':
                    $this->draw_gradient_background($image, $width, $height, $bg_rgb, $accent_rgb);
                    break;
                case 'minimal':
                    $white = imagecolorallocate($image, 248, 249, 250);
                    imagefill($image, 0, 0, $white);
                    imagefilledrectangle($image, 0, 0, 12, $height, $accent_color_gd);
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
                    case 'frame':
                        // 枠線のみ（透明背景に枠）
                        $this->draw_frame_overlay($image, $width, $height, $accent_color_gd);
                        break;
                    case 'split':
                        // 左帯を半透明で重ねる
                        $this->draw_split_overlay($image, $width, $height, $bg_rgb, $accent_rgb);
                        break;
                    case 'badge':
                        // 下帯を半透明で重ねる
                        $this->draw_badge_overlay($image, $width, $height, $accent_rgb);
                        break;
                    case 'diagonal':
                        $this->draw_diagonal_overlay($image, $width, $height, $accent_rgb);
                        break;
                    case 'gradient':
                        $this->draw_gradient_overlay($image, $width, $height, $bg_rgb, $accent_rgb);
                        break;
                    case 'minimal':
                        imagefilledrectangle($image, 0, 0, 12, $height, $accent_color_gd);
                        break;
                    case 'modern':
                        $overlay_color = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 100);
                        imagefilledellipse($image, (int) ($width * 0.8), (int) ($height * 0.3), 400, 400, $overlay_color);
                        imagefilledellipse($image, (int) ($width * 0.2), (int) ($height * 0.7), 300, 300, $overlay_color);
                        break;
                }
            }
        }
        
        // テキストを描画
        $this->draw_text_on_image($image, $title, $text_color_gd, $font_size, $font_weight, $width, $height, $style, $line_height, $letter_spacing, $text_align, $text_vertical, $max_chars, $text_bg_opacity, $text_bg_color, $text_bg_style);
        
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
            $r = (int) round($start_rgb[0] + ($end_rgb[0] - $start_rgb[0]) * $ratio);
            $g = (int) round($start_rgb[1] + ($end_rgb[1] - $start_rgb[1]) * $ratio);
            $b = (int) round($start_rgb[2] + ($end_rgb[2] - $start_rgb[2]) * $ratio);
            
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
            $r = (int) round($start_rgb[0] + ($end_rgb[0] - $start_rgb[0]) * $ratio);
            $g = (int) round($start_rgb[1] + ($end_rgb[1] - $start_rgb[1]) * $ratio);
            $b = (int) round($start_rgb[2] + ($end_rgb[2] - $start_rgb[2]) * $ratio);
            
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
        imagefilledellipse($image, (int) ($width * 0.8), (int) ($height * 0.3), 400, 400, $overlay_color);
        imagefilledellipse($image, (int) ($width * 0.2), (int) ($height * 0.7), 300, 300, $overlay_color);
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
     * フレームスタイル背景：単色塗り＋内側枠線＋四隅装飾
     */
    private function draw_frame_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb) {
        imagefill($image, 0, 0, $bg_color_gd);
        $this->draw_frame_overlay($image, $width, $height, $accent_color_gd);
    }

    private function draw_frame_overlay($image, $width, $height, $accent_color_gd) {
        $m = 28; // 外側マージン
        $t = 4;  // 枠線の太さ
        imagesetthickness($image, $t);
        imagerectangle($image, $m, $m, $width - $m, $height - $m, $accent_color_gd);
        // 内側細線
        $m2 = $m + 8;
        imagesetthickness($image, 1);
        imagerectangle($image, $m2, $m2, $width - $m2, $height - $m2, $accent_color_gd);
        imagesetthickness($image, 1);
    }

    /**
     * 二分割スタイル背景：左1/3アクセント色＋右2/3ベース色
     */
    private function draw_split_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb) {
        imagefill($image, 0, 0, $bg_color_gd);
        $split = (int)($width * 0.38);
        imagefilledrectangle($image, 0, 0, $split, $height, $accent_color_gd);
        // 境界に細いハイライト線
        $light = imagecolorallocatealpha($image, 255, 255, 255, 90);
        imagefilledrectangle($image, $split, 0, $split + 3, $height, $light);
    }

    private function draw_split_overlay($image, $width, $height, $bg_rgb, $accent_rgb) {
        $split = (int)($width * 0.38);
        $col = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 60);
        imagefilledrectangle($image, 0, 0, $split, $height, $col);
    }

    /**
     * バッジ帯スタイル背景：上部メイン色＋下部アクセント横帯
     */
    private function draw_badge_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb) {
        // 上部グラデーション
        $this->draw_gradient_background($image, $width, $height, $bg_rgb, array(
            max(0, $bg_rgb[0] - 40),
            max(0, $bg_rgb[1] - 40),
            max(0, $bg_rgb[2] - 40),
        ));
        // 下部アクセント帯
        $band_h = (int)($height * 0.22);
        imagefilledrectangle($image, 0, $height - $band_h, $width, $height, $accent_color_gd);
        // 帯上部に細いハイライト
        $light = imagecolorallocatealpha($image, 255, 255, 255, 80);
        imagefilledrectangle($image, 0, $height - $band_h, $width, $height - $band_h + 3, $light);
    }

    private function draw_badge_overlay($image, $width, $height, $accent_rgb) {
        $band_h = (int)($height * 0.22);
        $col = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 50);
        imagefilledrectangle($image, 0, $height - $band_h, $width, $height, $col);
    }

    /**
     * 斜め分割スタイル背景：左上アクセント三角＋右下ベース色
     */
    private function draw_diagonal_background($image, $width, $height, $bg_color_gd, $accent_color_gd, $bg_rgb, $accent_rgb) {
        imagefill($image, 0, 0, $bg_color_gd);
        // 斜め三角（左上）
        $points = array(0, 0, $width, 0, 0, $height);
        imagefilledpolygon($image, $points, 3, $accent_color_gd);
        // 境界にソフトライン
        $light = imagecolorallocatealpha($image, 255, 255, 255, 70);
        imagesetthickness($image, 3);
        imageline($image, 0, $height, $width, 0, $light);
        imagesetthickness($image, 1);
    }

    private function draw_diagonal_overlay($image, $width, $height, $accent_rgb) {
        $col = imagecolorallocatealpha($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2], 70);
        $points = array(0, 0, $width, 0, 0, $height);
        imagefilledpolygon($image, $points, 3, $col);
    }
    
    /**
     * テキストを画像に描画
     */
    private function draw_text_on_image($image, $text, $color, $font_size, $font_weight, $width, $height, $style, $line_height_multiplier = 1.8, $letter_spacing = 0, $text_align = 'center', $text_vertical = 'middle', $max_chars = 0, $text_bg_opacity = 0, $text_bg_color = '#000000', $text_bg_style = 'band') {
        // システムフォントを使用（日本語対応）
        $font_path = $this->get_japanese_font_path($font_weight);
        
        if ($font_path && file_exists($font_path)) {
            // TrueTypeフォントを使用
            $this->draw_text_with_font($image, $text, $color, $font_size, $width, $height, $font_path, $style, $font_weight, $line_height_multiplier, $letter_spacing, $text_align, $text_vertical, $max_chars, $text_bg_opacity, $text_bg_color, $text_bg_style);
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
    private function draw_text_with_font($image, $text, $color, $font_size, $width, $height, $font_path, $style, $font_weight = 'normal', $line_height_multiplier = 1.8, $letter_spacing = 0, $text_align = 'center', $text_vertical = 'middle', $max_chars = 0, $text_bg_opacity = 0, $text_bg_color = '#000000', $text_bg_style = 'band') {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // 最大表示文字数でトリミング
        if ($max_chars > 0 && mb_strlen($text, 'UTF-8') > $max_chars) {
            $text = mb_substr($text, 0, $max_chars, 'UTF-8') . '…';
        }

        // スタイル別のテキスト描画領域を決定
        $text_area_w    = $width - 120;
        $text_area_left = 60;
        $y_center_offset = 0;

        switch ($style) {
            case 'split':
                $split          = (int)($width * 0.38);
                $text_area_left = $split + 40;
                $text_area_w    = $width - $text_area_left - 40;
                break;
            case 'badge':
                if ($text_vertical === 'middle') {
                    $band_h          = (int)($height * 0.22);
                    $y_center_offset = -(int)($band_h / 2);
                }
                break;
            default:
                break;
        }

        // テキストを折り返し
        $lines = $this->wrap_text($text, $font_size, $font_path, $text_area_w, $letter_spacing);
        if (empty($lines)) return;

        $line_height  = $font_size * $line_height_multiplier;
        $total_height = count($lines) * $line_height;

        // 縦位置
        switch ($text_vertical) {
            case 'top':
                $y_start = 60 + $font_size;
                break;
            case 'bottom':
                $y_start = $height - $total_height - 50 + $font_size;
                break;
            default: // middle
                $y_start = ($height - $total_height) / 2 + $font_size + $y_center_offset;
                break;
        }

        // 横揃えモード（splitは常に左揃え）
        $align_mode = ($style === 'split') ? 'left' : $text_align;

        // テキスト背景を準備
        $draw_bg = ($text_bg_opacity > 0 && $text_bg_style !== 'none');
        $bg_fill = null;
        if ($draw_bg) {
            $bg_rgb_arr = $this->hex_to_rgb($text_bg_color);
            $gd_alpha   = max(0, min(127, (int)(127.0 * (1.0 - $text_bg_opacity / 100.0))));
            $bg_fill    = imagecolorallocatealpha($image, $bg_rgb_arr[0], $bg_rgb_arr[1], $bg_rgb_arr[2], $gd_alpha);
            imagealphablending($image, true);
            if ($text_bg_style === 'band') {
                $pad_x = 40;
                $pad_y = 16;
                $bg_x1 = max(0, $text_area_left - $pad_x);
                $bg_y1 = max(0, (int)($y_start - $font_size - $pad_y));
                $bg_x2 = min($width, $width - 60 + $pad_x);
                $bg_y2 = min($height, (int)($y_start - $font_size + $total_height + $pad_y));
                imagefilledrectangle($image, $bg_x1, $bg_y1, $bg_x2, $bg_y2, $bg_fill);
            }
        }

        // 太字シミュレーション用のオフセット
        $bold_offset = 0;
        switch ($font_weight) {
            case 'medium': $bold_offset = 1; break;
            case 'bold':   $bold_offset = 2; break;
            case 'black':  $bold_offset = 3; break;
        }

        // 影を出さないスタイル
        $no_shadow = in_array($style, array('minimal', 'split', 'frame'), true);

        foreach ($lines as $index => $line) {
            if (empty(trim($line))) continue;

            $text_width = $this->calculate_text_width($line, $font_size, $font_path, $letter_spacing);

            // 横位置
            switch ($align_mode) {
                case 'left':
                    $x = $text_area_left;
                    break;
                case 'right':
                    $x = (int)($width - $text_width - 60);
                    break;
                default: // center
                    if ($style === 'split') {
                        $x = (int)($text_area_left + ($text_area_w - $text_width) / 2);
                    } else {
                        $x = (int)(($width - $text_width) / 2);
                    }
                    break;
            }

            $y = (int)($y_start + ($index * $line_height));

            // 行ごとの背景（marker / block）
            if ($draw_bg && $bg_fill !== null && ($text_bg_style === 'marker' || $text_bg_style === 'block')) {
                $pad = 10;
                $rx1 = max(0, $x - $pad);
                $rx2 = min($width, $x + $text_width + $pad);
                if ($text_bg_style === 'marker') {
                    // マーカー：文字の下部寄り（細め）
                    $ry1 = (int)($y - (int)($font_size * 0.25));
                    $ry2 = (int)($y + 8);
                } else {
                    // ブロック：文字の高さいっぱい
                    $ry1 = (int)($y - $font_size + 4);
                    $ry2 = (int)($y + 10);
                }
                imagefilledrectangle($image, $rx1, max(0, $ry1), $rx2, min($height, $ry2), $bg_fill);
            }

            // 影（テキスト背景が濃い場合は省略）
            if (!$no_shadow && (!$draw_bg || $text_bg_opacity < 50)) {
                $shadow = imagecolorallocatealpha($image, 0, 0, 0, 50);
                if ($letter_spacing > 0) {
                    $cur_x = $x;
                    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($chars as $char) {
                        imagettftext($image, $font_size, 0, $cur_x + 3, $y + 3, $shadow, $font_path, $char);
                        $bbox   = imagettfbbox($font_size, 0, $font_path, $char);
                        $cur_x += ($bbox[2] - $bbox[0]) + $letter_spacing;
                    }
                } else {
                    imagettftext($image, $font_size, 0, $x + 3, $y + 3, $shadow, $font_path, $line);
                }
            }

            // 本文（太字効果込み）
            if ($letter_spacing > 0) {
                $cur_x = $x;
                $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $char) {
                    if ($bold_offset > 0) {
                        for ($i = 0; $i <= $bold_offset; $i++) {
                            imagettftext($image, $font_size, 0, $cur_x + $i, $y, $color, $font_path, $char);
                            if ($i > 0) imagettftext($image, $font_size, 0, $cur_x, $y + $i, $color, $font_path, $char);
                        }
                    } else {
                        imagettftext($image, $font_size, 0, $cur_x, $y, $color, $font_path, $char);
                    }
                    $bbox   = imagettfbbox($font_size, 0, $font_path, $char);
                    $cur_x += ($bbox[2] - $bbox[0]) + $letter_spacing;
                }
            } else {
                if ($bold_offset > 0) {
                    for ($i = 0; $i <= $bold_offset; $i++) {
                        imagettftext($image, $font_size, 0, $x + $i, $y, $color, $font_path, $line);
                        if ($i > 0) imagettftext($image, $font_size, 0, $x, $y + $i, $color, $font_path, $line);
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

        global $wpdb;
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap amig-wrap">

        <div class="amig-page-header">
            <h1 class="amig-page-title">
                <span class="dashicons dashicons-images-alt2"></span>
                一括画像生成
            </h1>
        </div>

        <div class="amig-alert amig-alert-info" style="margin-bottom:24px;">
            <span class="dashicons dashicons-info"></span>
            <div>
                サムネイルが未設定の公開投稿に対してまとめて生成します。<br>
                既にサムネイルが設定されている投稿はスキップされます。処理中はブラウザを閉じないでください。
            </div>
        </div>

        <?php foreach ($post_types as $post_type): ?>
        <?php
        $count_no_thumb = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_type = %s AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ", $post_type->name));

        $count_generated = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.post_id AND pm2.meta_key = '_amig_generated' AND pm2.meta_value = '1'
            WHERE p.post_type = %s AND p.post_status = 'publish'
        ", $post_type->name));

        $count_with_thumb = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_type = %s AND p.post_status = 'publish'
            AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
        ", $post_type->name));
        ?>

        <div class="amig-post-type-card" id="amig-ptcard-<?php echo esc_attr($post_type->name); ?>">
            <div class="amig-post-type-header">
                <div class="amig-post-type-info">
                    <h3>
                        <span class="dashicons dashicons-admin-post"></span>
                        <?php echo esc_html($post_type->label); ?>
                        <span style="font-weight:400;font-size:13px;color:var(--amig-muted);">(<?php echo esc_html($post_type->name); ?>)</span>
                    </h3>
                    <div class="amig-stat-row">
                        <span class="amig-stat">未設定 <strong><?php echo $count_no_thumb; ?></strong> 件</span>
                        <span class="amig-stat is-success">設定済 <strong><?php echo $count_with_thumb; ?></strong> 件</span>
                        <span class="amig-stat is-primary">プラグイン生成 <strong><?php echo $count_generated; ?></strong> 件</span>
                    </div>
                </div>
                <div class="amig-post-type-actions">
                    <?php if ($count_no_thumb > 0): ?>
                    <button type="button"
                            class="amig-btn amig-btn-primary amig-bulk-generate-btn"
                            data-post-type="<?php echo esc_attr($post_type->name); ?>"
                            data-count="<?php echo $count_no_thumb; ?>">
                        <span class="dashicons dashicons-images-alt2"></span> 一括生成
                    </button>
                    <?php else: ?>
                    <span class="amig-badge amig-badge-success">✓ すべて設定済み</span>
                    <?php endif; ?>

                    <?php if ($count_generated > 0): ?>
                    <button type="button"
                            class="amig-btn amig-btn-danger amig-bulk-delete-btn"
                            data-post-type="<?php echo esc_attr($post_type->name); ?>"
                            data-count="<?php echo $count_generated; ?>">
                        <span class="dashicons dashicons-trash"></span> 一括削除
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="amig-progress-wrap" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                <div class="amig-progress-header">
                    <span class="amig-progress-status">処理中...</span>
                    <span class="amig-progress-count">0 / <?php echo $count_no_thumb; ?></span>
                </div>
                <div class="amig-progress-track">
                    <div class="amig-progress-bar"></div>
                </div>
                <div class="amig-progress-log"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:20px;">
            <button type="button" id="amig-clear-skipped" class="amig-btn amig-btn-secondary">
                <span class="dashicons dashicons-update"></span> スキップ済みをクリア
            </button>
        </div>

        <script>
        var ajaxurl = ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        jQuery(document).ready(function($){

            $('#amig-clear-skipped').on('click', function(){
                if (!confirm('スキップ済みの投稿をクリアしますか？')) return;
                var btn = $(this);
                btn.prop('disabled', true);
                $.post(ajaxurl, {
                    action: 'amig_clear_skipped',
                    nonce:  '<?php echo esc_js(wp_create_nonce('amig_clear_skipped')); ?>'
                }, function(r){
                    alert(r.success ? 'クリアしました（' + r.data.count + ' 件）' : 'エラー: ' + r.data);
                    if (r.success) location.reload();
                }).always(function(){ btn.prop('disabled', false); });
            });

            function runBulk(postType, totalCount, action) {
                var card   = $('#amig-ptcard-' + postType);
                var wrap   = card.find('.amig-progress-wrap');
                var bar    = wrap.find('.amig-progress-bar');
                var status = wrap.find('.amig-progress-status');
                var cnt    = wrap.find('.amig-progress-count');
                var log    = wrap.find('.amig-progress-log');
                var processed = 0;

                wrap.addClass('is-open');
                log.empty();
                bar.removeClass('is-error').css('width', '0%');

                function step() {
                    $.ajax({
                        url:  ajaxurl,
                        type: 'POST',
                        data: { action: action, post_type: postType,
                                nonce: '<?php echo esc_js(wp_create_nonce('amig_bulk_generate')); ?>' },
                        success: function(r) {
                            if (r.success) {
                                if (r.data.post_id) {
                                    processed++;
                                    var pct = Math.min(100, Math.round(processed / totalCount * 100));
                                    bar.css('width', pct + '%');
                                    cnt.text(processed + ' / ' + totalCount);
                                    if (r.data.skipped) {
                                        log.append('<div class="amig-log-warning">⚠ ' + esc(r.data.post_title) + ' — ' + esc(r.data.error) + '</div>');
                                    } else {
                                        log.append('<div class="amig-log-success">✓ ' + esc(r.data.post_title) + '</div>');
                                    }
                                } else if (r.data.message) {
                                    log.append('<div class="amig-log-info">• ' + esc(r.data.message) + '</div>');
                                }
                                log.scrollTop(log[0].scrollHeight);
                                if (r.data.continue && processed < totalCount) {
                                    status.text('処理中... (' + processed + ' / ' + totalCount + ')');
                                    setTimeout(step, 80);
                                } else {
                                    status.html('<span style="color:var(--amig-success)">✓ 完了しました</span>');
                                    bar.css('width', '100%');
                                    setTimeout(function(){ location.reload(); }, 1800);
                                }
                            } else {
                                log.append('<div class="amig-log-error">✗ ' + esc(r.data || '不明なエラー') + '</div>');
                                status.html('<span style="color:var(--amig-danger)">エラーが発生しました</span>');
                                bar.addClass('is-error');
                            }
                        },
                        error: function(xhr) {
                            log.append('<div class="amig-log-error">✗ 通信エラー (' + xhr.status + ')</div>');
                            status.html('<span style="color:var(--amig-danger)">エラーが発生しました</span>');
                            bar.addClass('is-error');
                        }
                    });
                }
                step();
            }

            function esc(s) {
                return $('<div>').text(String(s)).html();
            }

            $('.amig-bulk-generate-btn').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true);
                runBulk(btn.data('post-type'), btn.data('count'), 'amig_bulk_generate');
            });

            $('.amig-bulk-delete-btn').on('click', function(){
                var btn = $(this);
                if (!confirm('このプラグインで生成したサムネイル ' + btn.data('count') + ' 件を削除しますか？\nこの操作は取り消せません。')) return;
                btn.prop('disabled', true);
                runBulk(btn.data('post-type'), btn.data('count'), 'amig_bulk_delete');
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

        global $wpdb;
        $delete_nonce  = wp_create_nonce('amig_delete_single');
        $post_types    = get_post_types(array('public' => true), 'objects');
        $total_all     = 0;
        $type_data     = array();

        foreach ($post_types as $pt) {
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT p.ID, p.post_title,
                       pm1.meta_value  AS thumbnail_id,
                       pm3.meta_value  AS generated_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.meta_value = pm2.post_id
                           AND pm2.meta_key = '_amig_generated' AND pm2.meta_value = '1'
                LEFT  JOIN {$wpdb->postmeta} pm3 ON pm1.meta_value = pm3.post_id
                           AND pm3.meta_key = '_amig_generated_date'
                WHERE p.post_type = %s AND p.post_status = 'publish'
                ORDER BY p.ID DESC
                LIMIT 200
            ", $pt->name));

            if (!empty($rows)) {
                $type_data[$pt->name] = array('label' => $pt->label, 'rows' => $rows);
                $total_all += count($rows);
            }
        }
        ?>
        <div class="wrap amig-wrap">

        <div class="amig-page-header">
            <h1 class="amig-page-title">
                <span class="dashicons dashicons-admin-media"></span>
                生成済み画像管理
            </h1>
            <?php if ($total_all > 0): ?>
            <span class="amig-badge amig-badge-info">合計 <?php echo $total_all; ?> 件</span>
            <?php endif; ?>
        </div>

        <?php if (empty($type_data)): ?>
        <div class="amig-alert amig-alert-info">
            <span class="dashicons dashicons-info"></span>
            <div>このプラグインで生成されたサムネイルはまだありません。<a href="<?php echo esc_url(admin_url('admin.php?page=automagic-bulk-generate')); ?>">一括生成ページ</a>から生成できます。</div>
        </div>

        <?php else: ?>

        <div class="amig-alert amig-alert-warning" style="margin-bottom:20px;">
            <span class="dashicons dashicons-warning"></span>
            <div>削除するとサムネイルも同時にメディアライブラリから削除されます。この操作は取り消せません。</div>
        </div>

        <?php foreach ($type_data as $pt_name => $data): ?>
        <?php $rows = $data['rows']; $count = count($rows); ?>
        <div class="amig-card" style="margin-bottom:24px;" id="amig-mgcard-<?php echo esc_attr($pt_name); ?>">
            <div class="amig-card-header" style="justify-content:space-between;">
                <span>
                    <span class="dashicons dashicons-admin-post"></span>
                    <?php echo esc_html($data['label']); ?>
                    <span class="amig-badge amig-badge-primary" style="margin-left:8px;"><?php echo $count; ?> 件</span>
                </span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button"
                            class="amig-btn amig-btn-secondary amig-mg-select-all"
                            data-pt="<?php echo esc_attr($pt_name); ?>">
                        すべて選択
                    </button>
                    <button type="button"
                            class="amig-btn amig-btn-danger amig-mg-delete-selected"
                            data-pt="<?php echo esc_attr($pt_name); ?>"
                            data-nonce="<?php echo esc_attr($delete_nonce); ?>">
                        <span class="dashicons dashicons-trash"></span> 選択削除
                    </button>
                </div>
            </div>
            <div class="amig-card-body" style="padding:0;">
                <div class="amig-mg-table-wrap">
                    <table class="amig-mg-table">
                        <thead>
                            <tr>
                                <th class="amig-mg-col-check">
                                    <input type="checkbox" class="amig-mg-all-cb" data-pt="<?php echo esc_attr($pt_name); ?>">
                                </th>
                                <th class="amig-mg-col-thumb">サムネイル</th>
                                <th class="amig-mg-col-title">タイトル</th>
                                <th class="amig-mg-col-date">生成日時</th>
                                <th class="amig-mg-col-action">操作</th>
                            </tr>
                        </thead>
                        <tbody id="amig-mg-tbody-<?php echo esc_attr($pt_name); ?>">
                        <?php foreach ($rows as $row): ?>
                        <?php $thumb_url = wp_get_attachment_image_url($row->thumbnail_id, array(80, 45)); ?>
                        <tr data-post-id="<?php echo esc_attr($row->ID); ?>" data-pt="<?php echo esc_attr($pt_name); ?>">
                            <td class="amig-mg-col-check">
                                <input type="checkbox"
                                       class="amig-mg-row-cb"
                                       value="<?php echo esc_attr($row->ID); ?>"
                                       data-pt="<?php echo esc_attr($pt_name); ?>">
                            </td>
                            <td class="amig-mg-col-thumb">
                                <?php if ($thumb_url): ?>
                                <img src="<?php echo esc_url($thumb_url); ?>" alt="" class="amig-mg-thumb">
                                <?php else: ?>
                                <span class="amig-mg-no-thumb">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="amig-mg-col-title">
                                <a href="<?php echo esc_url((string) get_edit_post_link($row->ID)); ?>" target="_blank">
                                    <?php echo esc_html($row->post_title ?: '(タイトルなし)'); ?>
                                    <span class="dashicons dashicons-external" style="font-size:12px;width:12px;height:12px;vertical-align:middle;opacity:.5;"></span>
                                </a>
                            </td>
                            <td class="amig-mg-col-date">
                                <?php echo $row->generated_date
                                    ? esc_html(mysql2date('Y/m/d H:i', $row->generated_date))
                                    : '<span style="color:var(--amig-muted)">—</span>'; ?>
                            </td>
                            <td class="amig-mg-col-action">
                                <button type="button"
                                        class="amig-btn amig-btn-danger amig-mg-delete-single"
                                        style="padding:4px 10px;font-size:12px;"
                                        data-post-id="<?php echo esc_attr($row->ID); ?>"
                                        data-post-title="<?php echo esc_attr($row->post_title); ?>"
                                        data-nonce="<?php echo esc_attr($delete_nonce); ?>">
                                    <span class="dashicons dashicons-trash" style="font-size:13px;width:13px;height:13px;"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>

        </div><!-- /amig-wrap -->
        <?php
    }

    /**
     * 一括画像生成のAJAXハンドラー
     */
    
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
} // end class_exists Automagic_Image_Generate

// プラグインの初期化
if (!function_exists('automagic_image_generate_init')) {
function automagic_image_generate_init() {
    return Automagic_Image_Generate::get_instance();
}

add_action('plugins_loaded', 'automagic_image_generate_init');
} // end function_exists automagic_image_generate_init

// AJAX処理（プレビュー生成用）
// wp_ajax_nopriv_ も登録することでセッション切れでも400でなくJSON errorを返す
add_action('wp_ajax_amig_preview_generate',        'amig_preview_generate_callback');
add_action('wp_ajax_nopriv_amig_preview_generate', 'amig_preview_generate_callback');

if (!function_exists('amig_preview_generate_callback')) {
function amig_preview_generate_callback() {
    // PHP警告がJSONを汚染しないよう表示を無効化してバッファを開始
    ini_set('display_errors', '0');
    ob_start();
    try {
        // ログイン確認（nopriv経由でも適切なエラーを返す）
        if (!is_user_logged_in()) {
            ob_end_clean();
            wp_send_json_error('ログインセッションが切れています。ページを再読み込みしてからログインし直してください。');
            return;
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'amig_generate_nonce')) {
            ob_end_clean();
            wp_send_json_error('nonce認証に失敗しました。ページを再読み込みしてから試してください。');
            return;
        }

        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_send_json_error('権限がありません');
            return;
        }

        $preview_text = isset($_POST['preview_text']) ? sanitize_text_field($_POST['preview_text']) : '';
        if (empty($preview_text)) {
            $preview_text = 'プレビューテキスト';
        }

        // GD ライブラリ確認
        if (!extension_loaded('gd')) {
            ob_end_clean();
            wp_send_json_error('PHP GD ライブラリが有効化されていません。サーバー管理者に確認してください。');
            return;
        }

        // 設定を取得（POST値があればそちらを優先してリアルタイムプレビューに対応）
        $instance = Automagic_Image_Generate::get_instance();
        $options  = get_option('amig_settings', array());
        $posted   = isset($_POST['amig_settings']) && is_array($_POST['amig_settings'])
                    ? array_map('sanitize_text_field', wp_unslash($_POST['amig_settings']))
                    : array();

        $bg_color         = isset($posted['bg_color'])         ? $posted['bg_color']         : (isset($options['bg_color'])         ? $options['bg_color']         : '#4A90E2');
        $text_color       = isset($posted['text_color'])       ? $posted['text_color']       : (isset($options['text_color'])       ? $options['text_color']       : '#FFFFFF');
        $accent_color     = isset($posted['accent_color'])     ? $posted['accent_color']     : (isset($options['accent_color'])     ? $options['accent_color']     : '#FFD700');
        $font_size        = isset($posted['font_size'])        ? intval($posted['font_size'])         : (isset($options['font_size'])        ? intval($options['font_size'])        : 40);
        $font_weight      = isset($posted['font_weight'])      ? $posted['font_weight']               : (isset($options['font_weight'])      ? $options['font_weight']              : 'normal');
        $image_style      = isset($posted['image_style'])      ? $posted['image_style']               : (isset($options['image_style'])      ? $options['image_style']              : 'modern');
        $bg_image_id      = isset($posted['bg_image'])         ? intval($posted['bg_image'])          : (isset($options['bg_image'])         ? intval($options['bg_image'])         : 0);
        $bg_image_opacity = isset($posted['bg_image_opacity']) ? intval($posted['bg_image_opacity'])  : (isset($options['bg_image_opacity']) ? intval($options['bg_image_opacity']) : 30);
        $line_height      = isset($posted['line_height'])      ? floatval($posted['line_height'])     : (isset($options['line_height'])      ? floatval($options['line_height'])     : 1.8);
        $letter_spacing   = isset($posted['letter_spacing'])   ? intval($posted['letter_spacing'])   : (isset($options['letter_spacing'])   ? intval($options['letter_spacing'])   : 0);
        $text_align       = isset($posted['text_align'])       ? $posted['text_align']               : (isset($options['text_align'])       ? $options['text_align']               : 'center');
        $text_vertical    = isset($posted['text_vertical'])    ? $posted['text_vertical']            : (isset($options['text_vertical'])    ? $options['text_vertical']            : 'middle');
        $max_chars        = isset($posted['max_chars'])        ? intval($posted['max_chars'])         : (isset($options['max_chars'])        ? intval($options['max_chars'])        : 0);
        $text_bg_opacity  = isset($posted['text_bg_opacity'])  ? intval($posted['text_bg_opacity'])   : (isset($options['text_bg_opacity'])  ? intval($options['text_bg_opacity'])  : 0);
        $text_bg_color    = isset($posted['text_bg_color'])    ? $posted['text_bg_color']             : (isset($options['text_bg_color'])    ? $options['text_bg_color']            : '#000000');
        $text_bg_style    = isset($posted['text_bg_style'])    ? $posted['text_bg_style']             : (isset($options['text_bg_style'])    ? $options['text_bg_style']            : 'band');

        // 画像を生成（create_thumbnail_image は public メソッド）
        $image_path = $instance->create_thumbnail_image(
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
            $letter_spacing,
            $text_align,
            $text_vertical,
            $max_chars,
            $text_bg_opacity,
            $text_bg_color,
            $text_bg_style
        );

        if (!$image_path || !file_exists($image_path)) {
            ob_end_clean();
            wp_send_json_error('画像の生成に失敗しました。フォントファイルが fonts/ ディレクトリに配置されているか確認してください。');
            return;
        }

        // アップロードディレクトリの URL を取得
        $upload_dir = wp_upload_dir();
        $file_url   = $upload_dir['url'] . '/' . basename($image_path);

        ob_end_clean();
        wp_send_json_success(array('url' => $file_url));

    } catch (Exception $e) {
        error_log('Automagic Image Generate Preview Error: ' . $e->getMessage());
        ob_end_clean();
        wp_send_json_error('エラー: ' . $e->getMessage());
    }
}
} // end function_exists amig_preview_generate_callback

// プラグイン有効化時のフック
register_activation_hook(__FILE__, 'amig_plugin_activation');

if (!function_exists('amig_plugin_activation')) {
/**
 * プラグイン有効化時の処理
 */
function amig_plugin_activation() {
    // フォントマネージャーの有効化処理を実行
    Automagic_Font_Manager::on_plugin_activation();
}
} // end function_exists amig_plugin_activation
