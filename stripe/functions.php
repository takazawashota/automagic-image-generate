<?php
/**
 * Stripe決済とライセンスキー管理機能
 * 
 * このファイルには以下の機能が含まれます：
 * - カスタム投稿タイプ「商品」
 * - 商品の価格設定とStripe設定
 * - ライセンスキー管理
 * - 管理画面（注文一覧、ライセンスキー管理、設定ページ）
 * - ショートコード（商品表示、商品一覧、マイライセンス）
 * - REST API（ライセンスキー検証）
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// カスタム投稿タイプ「商品」
// ============================================

// カスタム投稿タイプ「商品」を登録
add_action('init', 'register_product_post_type');
function register_product_post_type() {
    $labels = array(
        'name'                  => '商品',
        'singular_name'         => '商品',
        'menu_name'             => 'Stripe商品管理',
        'add_new'               => '新規追加',
        'add_new_item'          => '新しい商品を追加',
        'edit_item'             => '商品を編集',
        'new_item'              => '新しい商品',
        'view_item'             => '商品を表示',
        'search_items'          => '商品を検索',
        'not_found'             => '商品が見つかりませんでした',
        'not_found_in_trash'    => 'ゴミ箱に商品はありません',
    );

    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'has_archive'           => true,
        'menu_icon'             => 'dashicons-cart',
        'supports'              => array('title', 'editor', 'thumbnail'),
        'rewrite'               => array('slug' => 'products'),
        'show_in_rest'          => true,
    );

    register_post_type('product', $args);
}

// 商品一覧にカスタムカラムを追加
add_filter('manage_product_posts_columns', 'add_product_columns');
function add_product_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        // タイトルの後にショートコードカラムを追加
        if ($key === 'title') {
            $new_columns['shortcodes'] = 'ショートコード';
            $new_columns['price_info'] = '価格';
        }
    }
    return $new_columns;
}

// カスタムカラムの内容を表示
add_action('manage_product_posts_custom_column', 'display_product_columns', 10, 2);
function display_product_columns($column, $post_id) {
    switch ($column) {
        case 'shortcodes':
            ?>
            <div class="shortcode-display">
                <div class="shortcode-item">
                    <strong>商品表示:</strong>
                    <input type="text" readonly value='[product_display id="<?php echo $post_id; ?>"]' onclick="this.select();" style="width: 100%; margin-top: 5px; padding: 5px; font-family: monospace; font-size: 11px;">
                </div>
                <div class="shortcode-item" style="margin-top: 10px;">
                    <strong>購入ボタン:</strong>
                    <input type="text" readonly value='[stripe_checkout product_id="<?php echo $post_id; ?>"]' onclick="this.select();" style="width: 100%; margin-top: 5px; padding: 5px; font-family: monospace; font-size: 11px;">
                </div>
            </div>
            <style>
                .shortcode-display input {
                    cursor: pointer;
                    background: #f0f0f0;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                }
                .shortcode-display input:hover {
                    background: #e8e8e8;
                }
                .shortcode-display input:focus {
                    background: #fff;
                    border-color: #2271b1;
                    outline: none;
                }
            </style>
            <?php
            break;
            
        case 'price_info':
            $one_time_price = get_post_meta($post_id, '_one_time_price', true);
            $monthly_price = get_post_meta($post_id, '_monthly_price', true);
            $yearly_price = get_post_meta($post_id, '_yearly_price', true);
            
            echo '<div style="white-space: nowrap;">';
            if ($one_time_price) {
                echo '<div><strong>買い切り:</strong> ¥' . number_format($one_time_price) . '</div>';
            }
            if ($monthly_price) {
                echo '<div><strong>月額:</strong> ¥' . number_format($monthly_price) . '/月</div>';
            }
            if ($yearly_price) {
                echo '<div><strong>年間:</strong> ¥' . number_format($yearly_price) . '/年</div>';
            }
            if (!$one_time_price && !$monthly_price && !$yearly_price) {
                echo '<span style="color: #999;">価格未設定</span>';
            }
            echo '</div>';
            break;
    }
}

// カスタムカラムをソート可能にする
add_filter('manage_edit-product_sortable_columns', 'product_sortable_columns');
function product_sortable_columns($columns) {
    $columns['price_info'] = 'price_info';
    return $columns;
}

// 商品のメタボックスを追加
add_action('add_meta_boxes', 'add_product_meta_boxes');
function add_product_meta_boxes() {
    add_meta_box(
        'product_details',
        '商品詳細設定',
        'render_product_meta_box',
        'product',
        'normal',
        'high'
    );
}

// 商品メタボックスのHTML
function render_product_meta_box($post) {
    wp_nonce_field('product_meta_box', 'product_meta_box_nonce');
    
    $price_type = get_post_meta($post->ID, '_price_type', true) ?: 'one_time';
    $one_time_price = get_post_meta($post->ID, '_one_time_price', true);
    $monthly_price = get_post_meta($post->ID, '_monthly_price', true);
    $yearly_price = get_post_meta($post->ID, '_yearly_price', true);
    $stripe_price_id_onetime = get_post_meta($post->ID, '_stripe_price_id_onetime', true);
    $stripe_price_id_monthly = get_post_meta($post->ID, '_stripe_price_id_monthly', true);
    $stripe_price_id_yearly = get_post_meta($post->ID, '_stripe_price_id_yearly', true);
    $product_description = get_post_meta($post->ID, '_product_description', true);
    ?>
    <style>
        .product-meta-field { margin-bottom: 20px; }
        .product-meta-field label { display: block; font-weight: bold; margin-bottom: 5px; }
        .product-meta-field input[type="text"],
        .product-meta-field input[type="number"],
        .product-meta-field textarea { width: 100%; padding: 8px; }
        .product-meta-field textarea { height: 100px; }
        .product-meta-field input[type="radio"] { margin-right: 5px; }
        .price-option { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }
    </style>
    
    <div class="product-meta-field">
        <label>商品説明</label>
        <textarea name="product_description"><?php echo esc_textarea($product_description); ?></textarea>
    </div>
    
    <div class="product-meta-field">
        <label>提供するプランタイプ</label>
        <p class="description" style="margin-top: 0; margin-bottom: 10px;">提供したいプランに価格とStripe Price IDを設定してください。設定済みのプランのみユーザーが選択・変更できます。</p>
    </div>
    
    <div class="product-meta-field">
        <label>買い切り価格（円）</label>
        <input type="number" name="one_time_price" value="<?php echo esc_attr($one_time_price); ?>" min="0" step="1">
    </div>
    
    <div class="product-meta-field">
        <label>月額価格（円/月）</label>
        <input type="number" name="monthly_price" value="<?php echo esc_attr($monthly_price); ?>" min="0" step="1">
    </div>
    
    <div class="product-meta-field">
        <label>年間価格（円/年）</label>
        <input type="number" name="yearly_price" value="<?php echo esc_attr($yearly_price); ?>" min="0" step="1">
    </div>
    
    <div class="product-meta-field">
        <label>Stripe Price ID（買い切り）<span style="color: #dc2626; font-weight: normal;">（必須）</span></label>
        <input type="text" name="stripe_price_id_onetime" value="<?php echo esc_attr($stripe_price_id_onetime); ?>" placeholder="price_xxxxx">
        <p class="description">Stripeダッシュボードで作成した買い切り用のPrice IDを入力してください。</p>
    </div>
    
    <div class="product-meta-field">
        <label>Stripe Price ID（月額）<span style="color: #dc2626; font-weight: normal;">（必須）</span></label>
        <input type="text" name="stripe_price_id_monthly" value="<?php echo esc_attr($stripe_price_id_monthly); ?>" placeholder="price_xxxxx">
        <p class="description">Stripeダッシュボードで作成した月額用のPrice ID（定期支払い・月次）を入力してください。</p>
    </div>
    
    <div class="product-meta-field">
        <label>Stripe Price ID（年間）<span style="color: #dc2626; font-weight: normal;">（必須）</span></label>
        <input type="text" name="stripe_price_id_yearly" value="<?php echo esc_attr($stripe_price_id_yearly); ?>" placeholder="price_xxxxx">
        <p class="description">Stripeダッシュボードで作成した年間用のPrice ID（定期支払い・年次）を入力してください。</p>
    </div>
    <?php
}

// 商品メタデータを保存
add_action('save_post_product', 'save_product_meta');
function save_product_meta($post_id) {
    if (!isset($_POST['product_meta_box_nonce']) || !wp_verify_nonce($_POST['product_meta_box_nonce'], 'product_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $fields = array(
        'one_time_price',
        'monthly_price',
        'yearly_price',
        'stripe_price_id_onetime',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'product_description'
    );
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
}

// ============================================
// ライセンスキー管理
// ============================================

// ライセンスキー用のカスタムテーブルを作成
function create_license_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        license_key varchar(255) NOT NULL,
        product_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        user_email varchar(255) NOT NULL,
        purchase_type varchar(50) NOT NULL,
        stripe_payment_intent_id varchar(255),
        stripe_subscription_id varchar(255),
        status varchar(50) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime,
        PRIMARY KEY  (id),
        UNIQUE KEY license_key (license_key),
        KEY product_id (product_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// テーマ有効化時にテーブルを作成
add_action('after_setup_theme', 'create_license_table');

// 請求履歴テーブル作成
function create_invoice_history_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stripe_invoice_history';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_email varchar(255) NOT NULL,
        license_id bigint(20),
        stripe_invoice_id varchar(255) NOT NULL,
        stripe_subscription_id varchar(255),
        amount decimal(10,2) NOT NULL,
        currency varchar(10) NOT NULL DEFAULT 'jpy',
        status varchar(50) NOT NULL,
        invoice_date datetime NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY license_id (license_id),
        KEY stripe_subscription_id (stripe_subscription_id),
        KEY invoice_date (invoice_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_setup_theme', 'create_invoice_history_table');

// ライセンスキー生成関数
function generate_license_key($product_id, $user_id) {
    $prefix = 'LIC';
    $random = strtoupper(bin2hex(random_bytes(8)));
    $checksum = substr(md5($product_id . $user_id . time()), 0, 4);
    return $prefix . '-' . substr($random, 0, 4) . '-' . substr($random, 4, 4) . '-' . substr($random, 8, 4) . '-' . strtoupper($checksum);
}

// ============================================
// Stripe設定関数
// ============================================

// 設定から現在のAPI キーを取得する関数
function get_stripe_keys() {
    $settings = get_option('stripe_payment_settings', array(
        'mode' => 'test',
        'test_publishable_key' => '',
        'test_secret_key' => '',
        'test_webhook_secret' => '',
        'live_publishable_key' => '',
        'live_secret_key' => '',
        'live_webhook_secret' => '',
        'currency' => 'jpy',
        'success_page' => 0,
        'cancel_page' => 0,
    ));
    
    $is_test_mode = ($settings['mode'] === 'test');
    
    return array(
        'publishable_key' => $is_test_mode ? $settings['test_publishable_key'] : $settings['live_publishable_key'],
        'secret_key' => $is_test_mode ? $settings['test_secret_key'] : $settings['live_secret_key'],
        'webhook_secret' => $is_test_mode ? $settings['test_webhook_secret'] : $settings['live_webhook_secret'],
        'currency' => $settings['currency'],
        'success_page' => $settings['success_page'],
        'cancel_page' => $settings['cancel_page'],
        'is_test_mode' => $is_test_mode,
    );
}

// ============================================
// 管理画面メニュー
// ============================================

// 商品管理メニューにサブメニューを追加
add_action('admin_menu', 'add_product_submenus');
function add_product_submenus() {
    // 注文一覧
    add_submenu_page(
        'edit.php?post_type=product',
        '注文一覧',
        '注文一覧',
        'manage_options',
        'product-orders',
        'render_orders_page'
    );
    
    // ライセンスキー管理
    add_submenu_page(
        'edit.php?post_type=product',
        'ライセンスキー管理',
        'ライセンスキー',
        'manage_options',
        'license-keys',
        'render_license_keys_page'
    );
    
    // 設定
    add_submenu_page(
        'edit.php?post_type=product',
        'Stripe設定',
        '設定',
        'manage_options',
        'stripe-settings',
        'render_stripe_settings_page'
    );
}

// 注文一覧ページ
function render_orders_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    // 検索とフィルター
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    
    $where = "WHERE 1=1";
    if ($search) {
        $where .= $wpdb->prepare(" AND (license_key LIKE %s OR user_email LIKE %s OR stripe_payment_intent_id LIKE %s OR stripe_subscription_id LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
    }
    if ($status_filter) {
        $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    }
    if ($type_filter) {
        // 'subscription'が選択された場合はmonthlyとyearlyの両方を含む
        if ($type_filter === 'subscription') {
            $where .= " AND purchase_type IN ('monthly', 'yearly')";
        } else {
            $where .= $wpdb->prepare(" AND purchase_type = %s", $type_filter);
        }
    }
    
    $orders = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT 100");
    
    // 統計情報
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $active_orders = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
    $total_revenue = $wpdb->get_var("
        SELECT SUM(CASE 
            WHEN lk.purchase_type = 'one_time' THEN pm.meta_value 
            ELSE 0 
        END) 
        FROM {$table_name} lk 
        LEFT JOIN {$wpdb->postmeta} pm ON lk.product_id = pm.post_id AND pm.meta_key = '_one_time_price'
        WHERE lk.status = 'active'
    ");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">注文一覧</h1>
        <hr class="wp-header-end">
        
        <div class="order-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="font-size: 14px; color: #666;">総注文数</div>
                <div style="font-size: 32px; font-weight: bold; margin-top: 10px;"><?php echo number_format($total_orders); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="font-size: 14px; color: #666;">有効な注文</div>
                <div style="font-size: 32px; font-weight: bold; margin-top: 10px; color: #10b981;"><?php echo number_format($active_orders); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="font-size: 14px; color: #666;">総売上（買い切り）</div>
                <div style="font-size: 32px; font-weight: bold; margin-top: 10px; color: #3b82f6;">¥<?php echo number_format($total_revenue ?: 0); ?></div>
            </div>
        </div>
        
        <form method="get" action="" style="margin: 20px 0;">
            <input type="hidden" name="post_type" value="product">
            <input type="hidden" name="page" value="product-orders">
            <p class="search-box" style="display: flex; gap: 10px; align-items: center;">
                <label>検索:</label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="メールアドレス、ライセンスキーなど" style="width: 300px;">
                
                <label>購入タイプ:</label>
                <select name="type">
                    <option value="">すべて</option>
                    <option value="one_time" <?php selected($type_filter, 'one_time'); ?>>買い切り</option>
                    <option value="subscription" <?php selected($type_filter, 'subscription'); ?>>サブスクリプション（全て）</option>
                    <option value="monthly" <?php selected($type_filter, 'monthly'); ?>>月額プラン</option>
                    <option value="yearly" <?php selected($type_filter, 'yearly'); ?>>年間プラン</option>
                </select>
                
                <label>ステータス:</label>
                <select name="status">
                    <option value="">すべて</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>有効</option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>無効</option>
                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>キャンセル</option>
                    <option value="payment_failed" <?php selected($status_filter, 'payment_failed'); ?>>支払い失敗</option>
                </select>
                
                <input type="submit" class="button" value="検索">
            </p>
        </form>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>商品</th>
                    <th>購入者</th>
                    <th>購入タイプ</th>
                    <th>金額</th>
                    <th>ステータス</th>
                    <th>購入日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">注文が見つかりませんでした。</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
                        if ($order->purchase_type === 'one_time') {
                            $price = get_post_meta($order->product_id, '_one_time_price', true);
                        } elseif ($order->purchase_type === 'monthly') {
                            $price = get_post_meta($order->product_id, '_monthly_price', true);
                        } else { // yearly
                            $price = get_post_meta($order->product_id, '_yearly_price', true);
                        }
                    ?>
                    <tr>
                        <td><?php echo esc_html($order->id); ?></td>
                        <td>
                            <strong><?php echo esc_html(get_the_title($order->product_id)); ?></strong>
                            <br><small style="color: #666;">ID: <?php echo esc_html($order->product_id); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($order->user_email); ?>
                            <?php if ($order->user_id): ?>
                            <br><small style="color: #666;">User ID: <?php echo esc_html($order->user_id); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $purchase_type_labels = array(
                                'one_time' => '<span style="background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">買い切り</span>',
                                'monthly' => '<span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">月額プラン</span>',
                                'yearly' => '<span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">年間プラン</span>'
                            );
                            echo $purchase_type_labels[$order->purchase_type] ?? esc_html($order->purchase_type);
                            ?>
                        </td>
                        <td>
                            <strong>¥<?php echo number_format($price ?: 0); ?></strong>
                            <?php if ($order->purchase_type === 'monthly'): ?>
                            <small style="color: #666;">/月</small>
                            <?php elseif ($order->purchase_type === 'yearly'): ?>
                            <small style="color: #666;">/年</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status_labels = array(
                                'active' => '<span style="color: #10b981; font-weight: bold;">● 有効</span>',
                                'inactive' => '<span style="color: #f59e0b; font-weight: bold;">● 無効</span>',
                                'canceled' => '<span style="color: #ef4444; font-weight: bold;">● キャンセル</span>',
                                'payment_failed' => '<span style="color: #ef4444; font-weight: bold;">● 支払い失敗</span>'
                            );
                            echo $status_labels[$order->status] ?? $order->status;
                            ?>
                        </td>
                        <td><?php echo esc_html(date('Y/m/d H:i', strtotime($order->created_at))); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=license-keys&s=' . urlencode($order->license_key)); ?>" class="button button-small">詳細</a>
                            <?php 
                            $stripe_keys = get_stripe_keys();
                            $is_test = $stripe_keys['is_test_mode'];
                            $stripe_mode = $is_test ? 'test/' : '';
                            ?>
                            <?php if ($order->stripe_subscription_id): ?>
                            <a href="https://dashboard.stripe.com/<?php echo $stripe_mode; ?>subscriptions/<?php echo esc_attr($order->stripe_subscription_id); ?>" target="_blank" class="button button-small">Stripe</a>
                            <?php elseif ($order->stripe_payment_intent_id): ?>
                            <a href="https://dashboard.stripe.com/<?php echo $stripe_mode; ?>payments/<?php echo esc_attr($order->stripe_payment_intent_id); ?>" target="_blank" class="button button-small">Stripe</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ライセンスキー管理ページ
function render_license_keys_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    // 検索とフィルター
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    $where = "WHERE 1=1";
    if ($search) {
        $where .= $wpdb->prepare(" AND (license_key LIKE %s OR user_email LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
    }
    if ($status_filter) {
        $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    }
    
    $licenses = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT 100");
    
    ?>
    <div class="wrap">
        <h1>ライセンスキー管理</h1>
        
        <form method="get" action="">
            <input type="hidden" name="page" value="license-keys">
            <p class="search-box">
                <label>検索:</label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="ライセンスキーまたはメールアドレス">
                
                <label>ステータス:</label>
                <select name="status">
                    <option value="">すべて</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>有効</option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>無効</option>
                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>キャンセル</option>
                    <option value="payment_failed" <?php selected($status_filter, 'payment_failed'); ?>>支払い失敗</option>
                </select>
                
                <input type="submit" class="button" value="検索">
            </p>
        </form>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ライセンスキー</th>
                    <th>商品</th>
                    <th>購入者</th>
                    <th>購入タイプ</th>
                    <th>ステータス</th>
                    <th>購入日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licenses)): ?>
                <tr>
                    <td colspan="8">ライセンスキーが見つかりませんでした。</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><?php echo esc_html($license->id); ?></td>
                        <td><code><?php echo esc_html($license->license_key); ?></code></td>
                        <td><?php echo esc_html(get_the_title($license->product_id)); ?></td>
                        <td>
                            <?php echo esc_html($license->user_email); ?>
                            <?php if ($license->user_id): ?>
                            <br><small>User ID: <?php echo esc_html($license->user_id); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $purchase_type_labels = array(
                                'one_time' => '<span style="background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">買い切り</span>',
                                'monthly' => '<span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">月額プラン</span>',
                                'yearly' => '<span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">年間プラン</span>'
                            );
                            echo $purchase_type_labels[$license->purchase_type] ?? esc_html($license->purchase_type);
                            ?>
                        </td>
                        <td>
                            <?php 
                            $status_labels = array(
                                'active' => '<span style="color: #10b981; font-weight: bold;">● 有効</span>',
                                'inactive' => '<span style="color: #f59e0b; font-weight: bold;">● 無効</span>',
                                'canceled' => '<span style="color: #ef4444; font-weight: bold;">● キャンセル</span>',
                                'payment_failed' => '<span style="color: #ef4444; font-weight: bold;">● 支払い失敗</span>'
                            );
                            echo $status_labels[$license->status] ?? $license->status;
                            ?>
                        </td>
                        <td><?php echo esc_html(date('Y/m/d H:i', strtotime($license->created_at))); ?></td>
                        <td>
                            <?php if ($license->stripe_subscription_id): ?>
                            <a href="https://dashboard.stripe.com/test/subscriptions/<?php echo esc_attr($license->stripe_subscription_id); ?>" target="_blank" class="button button-small">Stripeで確認</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <style>
            .search-box { display: flex; gap: 10px; align-items: center; margin: 20px 0; }
            .search-box input[type="search"] { width: 300px; }
            .search-box select { padding: 5px; }
        </style>
    </div>
    <?php
}

// Stripe設定ページ
function render_stripe_settings_page() {
    // Stripe設定の保存
    if (isset($_POST['save_stripe_settings']) && check_admin_referer('stripe_settings_nonce')) {
        $settings = array(
            'mode' => sanitize_text_field($_POST['stripe_mode']),
            'test_publishable_key' => sanitize_text_field($_POST['test_publishable_key']),
            'test_secret_key' => sanitize_text_field($_POST['test_secret_key']),
            'test_webhook_secret' => sanitize_text_field($_POST['test_webhook_secret']),
            'live_publishable_key' => sanitize_text_field($_POST['live_publishable_key']),
            'live_secret_key' => sanitize_text_field($_POST['live_secret_key']),
            'live_webhook_secret' => sanitize_text_field($_POST['live_webhook_secret']),
            'currency' => sanitize_text_field($_POST['currency']),
            'success_page' => intval($_POST['success_page']),
            'cancel_page' => intval($_POST['cancel_page']),
        );
        
        update_option('stripe_payment_settings', $settings);
        echo '<div class="notice notice-success is-dismissible"><p>Stripe設定を保存しました。</p></div>';
    }
    
    // メール設定の保存
    if (isset($_POST['save_email_settings']) && check_admin_referer('email_settings_nonce')) {
        $email_settings = array(
            'enable_emails' => isset($_POST['enable_emails']) ? 1 : 0,
            'from_email' => sanitize_email($_POST['from_email']),
            'from_name' => sanitize_text_field($_POST['from_name']),
            'reply_to' => sanitize_email($_POST['reply_to']),
            'return_path' => sanitize_email($_POST['return_path']),
            'bcc' => sanitize_email($_POST['bcc']),
            'test_email' => sanitize_email($_POST['test_email']),
            'purchase_subject' => sanitize_text_field($_POST['purchase_subject']),
            'purchase_message' => wp_kses_post($_POST['purchase_message']),
            'subscription_subject' => sanitize_text_field($_POST['subscription_subject']),
            'subscription_message' => wp_kses_post($_POST['subscription_message']),
            'renewal_subject' => sanitize_text_field($_POST['renewal_subject']),
            'renewal_message' => wp_kses_post($_POST['renewal_message']),
            'cancellation_subject' => sanitize_text_field($_POST['cancellation_subject']),
            'cancellation_message' => wp_kses_post($_POST['cancellation_message']),
            'payment_failed_subject' => sanitize_text_field($_POST['payment_failed_subject']),
            'payment_failed_message' => wp_kses_post($_POST['payment_failed_message']),
            'status_changed_subject' => sanitize_text_field($_POST['status_changed_subject']),
            'status_changed_message' => wp_kses_post($_POST['status_changed_message']),
        );
        
        update_option('stripe_email_settings', $email_settings);
        echo '<div class="notice notice-success is-dismissible"><p>メール設定を保存しました。</p></div>';
    }
    
    $settings = get_option('stripe_payment_settings', array(
        'mode' => 'test',
        'test_publishable_key' => '',
        'test_secret_key' => '',
        'test_webhook_secret' => '',
        'live_publishable_key' => '',
        'live_secret_key' => '',
        'live_webhook_secret' => '',
        'currency' => 'jpy',
        'success_page' => 0,
        'cancel_page' => 0,
    ));
    
    $email_settings = get_option('stripe_email_settings', array(
        'enable_emails' => 1,
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name'),
        'reply_to' => get_option('admin_email'),
        'return_path' => '',
        'bcc' => '',
        'test_email' => get_option('admin_email'),
        'purchase_subject' => '【{site_name}】ご購入ありがとうございます',
        'purchase_message' => "この度は {product_name} をご購入いただき、誠にありがとうございます。\n\nライセンスキーを発行いたしましたので、以下の情報をご確認ください。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nライセンスキーは大切に保管してください。\n\nマイページ: {my_account_url}",
        'subscription_subject' => '【{site_name}】サブスクリプション開始のお知らせ',
        'subscription_message' => "この度は {product_name} のサブスクリプションをご契約いただき、誠にありがとうございます。\n\nライセンスキーを発行いたしました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: サブスクリプション（月額）\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n※サブスクリプションは毎月自動的に更新されます。\n※解約をご希望の場合は、マイページから手続きを行ってください。\n\nマイページ: {my_account_url}",
        'renewal_subject' => '【{site_name}】サブスクリプション更新完了',
        'renewal_message' => "いつも {product_name} をご利用いただき、誠にありがとうございます。\n\nサブスクリプションの更新が完了いたしました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n更新金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n次回の更新も自動的に行われます。\n引き続き {product_name} をお楽しみください。\n\nマイページ: {my_account_url}",
        'cancellation_subject' => '【{site_name}】サブスクリプションが解約されました',
        'cancellation_message' => "{product_name} のサブスクリプションが解約されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nこれまで {product_name} をご利用いただき、誠にありがとうございました。\n\n※有効期限までは引き続きサービスをご利用いただけます。\n※再度ご契約をご希望の場合は、商品ページからお申し込みください。\n\nマイページ: {my_account_url}",
        'payment_failed_subject' => '【{site_name}】サブスクリプション支払いが失敗しました',
        'payment_failed_message' => "{product_name} のサブスクリプション更新の支払いに失敗しました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n請求金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n【重要】お支払い方法をご確認ください\n\n支払いが完了しない場合、サービスのご利用が制限される可能性がございます。\nお手数ですが、マイページから支払い方法の更新をお願いいたします。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
        'status_changed_subject' => '【{site_name}】サブスクリプションステータスが変更されました',
        'status_changed_message' => "{product_name} のサブスクリプションステータスが変更されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n新しいステータス: {new_status}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nステータスの変更により、サービスのご利用状況が変わる場合がございます。\n詳細はマイページからご確認ください。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
    ));
    
    $webhook_url = home_url('/wp-json/stripe/v1/webhook');
    
    // 現在のタブを取得
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'stripe';
    ?>
    <div class="wrap">
        <h1>Stripe設定</h1>
        
        <nav class="nav-tab-wrapper">
            <a href="?post_type=product&page=stripe-settings&tab=stripe" class="nav-tab <?php echo $current_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
                Stripe設定
            </a>
            <a href="?post_type=product&page=stripe-settings&tab=email" class="nav-tab <?php echo $current_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                メール設定
            </a>
        </nav>
        
        <div class="tab-content" style="background: white; padding: 20px; margin-top: 0; border: 1px solid #ccd0d4; border-top: none;">
            
            <?php if ($current_tab === 'stripe'): ?>
            <!-- Stripe設定タブ -->
            <form method="post" action="">
                <?php wp_nonce_field('stripe_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">動作モード</th>
                    <td>
                        <label>
                            <input type="radio" name="stripe_mode" value="test" <?php checked($settings['mode'], 'test'); ?>>
                            テストモード
                        </label>
                        <br>
                        <label style="margin-top: 10px; display: inline-block;">
                            <input type="radio" name="stripe_mode" value="live" <?php checked($settings['mode'], 'live'); ?>>
                            本番モード
                        </label>
                        <p class="description">テストモードでは実際に課金されません。</p>
                    </td>
                </tr>
            </table>
            
            <h2>テストモード用キー</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">テスト公開可能キー</th>
                    <td>
                        <input type="text" name="test_publishable_key" value="<?php echo esc_attr($settings['test_publishable_key']); ?>" class="regular-text" placeholder="pk_test_">
                        <p class="description">pk_test_ で始まるキー</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">テストシークレットキー</th>
                    <td>
                        <input type="password" name="test_secret_key" value="<?php echo esc_attr($settings['test_secret_key']); ?>" class="regular-text" placeholder="sk_test_">
                        <p class="description">sk_test_ で始まるキー</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">テストWebhookシークレット</th>
                    <td>
                        <input type="text" name="test_webhook_secret" value="<?php echo esc_attr($settings['test_webhook_secret']); ?>" class="regular-text" placeholder="whsec_">
                        <p class="description">whsec_ で始まるキー（オプション）</p>
                    </td>
                </tr>
            </table>
            
            <h2>本番モード用キー</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">本番公開可能キー</th>
                    <td>
                        <input type="text" name="live_publishable_key" value="<?php echo esc_attr($settings['live_publishable_key']); ?>" class="regular-text" placeholder="pk_live_">
                        <p class="description">pk_live_ で始まるキー</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">本番シークレットキー</th>
                    <td>
                        <input type="password" name="live_secret_key" value="<?php echo esc_attr($settings['live_secret_key']); ?>" class="regular-text" placeholder="sk_live_">
                        <p class="description">sk_live_ で始まるキー</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">本番Webhookシークレット</th>
                    <td>
                        <input type="text" name="live_webhook_secret" value="<?php echo esc_attr($settings['live_webhook_secret']); ?>" class="regular-text" placeholder="whsec_">
                        <p class="description">whsec_ で始まるキー（オプション）</p>
                    </td>
                </tr>
            </table>
            
            <h2>Webhook設定</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">WebhookエンドポイントURL</th>
                    <td>
                        <input type="text" value="<?php echo esc_attr($webhook_url); ?>" class="large-text" readonly onclick="this.select();">
                        <p class="description">このURLをStripeダッシュボードのWebhook設定に登録してください。</p>
                    </td>
                </tr>
            </table>
            
            <h2>その他の設定</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">通貨</th>
                    <td>
                        <select name="currency">
                            <option value="jpy" <?php selected($settings['currency'], 'jpy'); ?>>日本円 (JPY)</option>
                            <option value="usd" <?php selected($settings['currency'], 'usd'); ?>>米ドル (USD)</option>
                            <option value="eur" <?php selected($settings['currency'], 'eur'); ?>>ユーロ (EUR)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">決済成功ページ</th>
                    <td>
                        <?php 
                        wp_dropdown_pages(array(
                            'name' => 'success_page',
                            'selected' => $settings['success_page'],
                            'show_option_none' => 'ページを選択',
                            'option_none_value' => '0'
                        ));
                        ?>
                        <p class="description">決済完了後にリダイレクトするページ。「購入完了」テンプレートのページを選択してください。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">決済キャンセルページ</th>
                    <td>
                        <?php 
                        wp_dropdown_pages(array(
                            'name' => 'cancel_page',
                            'selected' => $settings['cancel_page'],
                            'show_option_none' => 'ページを選択',
                            'option_none_value' => '0'
                        ));
                        ?>
                        <p class="description">決済キャンセル時にリダイレクトするページ（オプション）</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_stripe_settings" class="button button-primary" value="設定を保存">
            </p>
        </form>
        
        <div class="card" style="max-width: 800px; margin-top: 30px;">
            <h2>Stripe設定ガイド</h2>
            <ol>
                <li><strong>Stripeアカウントにログイン</strong><br>
                    テストモード: <a href="https://dashboard.stripe.com/test/dashboard" target="_blank">https://dashboard.stripe.com/test/dashboard</a></li>
                
                <li><strong>APIキーを取得</strong><br>
                    [開発者] → [APIキー] から公開可能キーとシークレットキーをコピー</li>
                
                <li><strong>Webhookを設定</strong><br>
                    [開発者] → [Webhook] → [エンドポイントを追加]<br>
                    上記のWebhookエンドポイントURLを登録し、以下のイベントを選択：
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>checkout.session.completed</li>
                        <li>customer.subscription.deleted</li>
                        <li>customer.subscription.updated</li>
                        <li>invoice.payment_succeeded</li>
                        <li>invoice.payment_failed</li>
                    </ul>
                </li>
                
                <li><strong>Webhookシークレットをコピー</strong><br>
                    作成したWebhookの詳細ページから「署名シークレット」をコピーして上記フィールドに貼り付け</li>
            </ol>
        </div>
        
        <?php elseif ($current_tab === 'email'): ?>
        <!-- メール設定タブ -->
        
        <?php
        // テストメール送信処理
        if (isset($_POST['send_test_email']) && check_admin_referer('send_test_email_action', 'send_test_email_nonce')) {
            send_test_email();
        }
        
        // デフォルト値の定義
        $default_email_settings = array(
            'bcc' => '',
            'test_email' => get_option('admin_email'),
            'renewal_subject' => '【{site_name}】サブスクリプション更新完了',
            'renewal_message' => "いつも {product_name} をご利用いただき、誠にありがとうございます。\n\nサブスクリプションの更新が完了いたしました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n更新金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n次回の更新も自動的に行われます。\n引き続き {product_name} をお楽しみください。\n\nマイページ: {my_account_url}",
            'cancellation_subject' => '【{site_name}】サブスクリプションが解約されました',
            'cancellation_message' => "{product_name} のサブスクリプションが解約されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nこれまで {product_name} をご利用いただき、誠にありがとうございました。\n\n※有効期限までは引き続きサービスをご利用いただけます。\n※再度ご契約をご希望の場合は、商品ページからお申し込みください。\n\nマイページ: {my_account_url}",
            'payment_failed_subject' => '【{site_name}】サブスクリプション支払いが失敗しました',
            'payment_failed_message' => "{product_name} のサブスクリプション更新の支払いに失敗しました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n請求金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n【重要】お支払い方法をご確認ください\n\n支払いが完了しない場合、サービスのご利用が制限される可能性がございます。\nお手数ですが、マイページから支払い方法の更新をお願いいたします。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
            'status_changed_subject' => '【{site_name}】サブスクリプションステータスが変更されました',
            'status_changed_message' => "{product_name} のサブスクリプションステータスが変更されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n新しいステータス: {new_status}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nステータスの変更により、サービスのご利用状況が変わる場合がございます。\n詳細はマイページからご確認ください。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
        );
        
        // 保存された値とデフォルト値をマージ（空の値の場合はデフォルトを使用）
        foreach ($default_email_settings as $key => $default_value) {
            if (!isset($email_settings[$key]) || $email_settings[$key] === '') {
                $email_settings[$key] = $default_value;
            }
        }
        ?>
        
        <h2>利用可能な変数</h2>
        <p>メールの件名・本文で以下の変数が使用できます：</p>
        <ul style="list-style: disc; margin-left: 20px; margin-bottom: 20px;">
            <li><code>{site_name}</code> - サイト名</li>
            <li><code>{site_url}</code> - サイトURL</li>
            <li><code>{product_name}</code> - 商品名</li>
            <li><code>{purchase_type}</code> - 購入タイプ（買い切り/月額プラン/年間プラン）</li>
            <li><code>{license_key}</code> - ライセンスキー</li>
            <li><code>{customer_email}</code> - 顧客のメールアドレス</li>
            <li><code>{my_account_url}</code> - マイページURL</li>
            <li><code>{amount}</code> - 金額（更新・失敗メールのみ）</li>
            <li><code>{currency}</code> - 通貨（更新・失敗メールのみ）</li>
            <li><code>{new_status}</code> - 新しいステータス（ステータス変更メールのみ）</li>
        </ul>
        
        <hr style="margin: 30px 0;">
        
        <form method="post" action="">
            <?php wp_nonce_field('email_settings_nonce'); ?>
            
            <h2>基本設定</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_emails">メール送信を有効化</label>
                    </th>
                    <td>
                        <input type="checkbox" id="enable_emails" name="enable_emails" value="1" <?php checked($email_settings['enable_emails'], 1); ?>>
                        <p class="description">チェックを外すと全てのメール送信が無効になります。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_email">送信元メールアドレス</label>
                    </th>
                    <td>
                        <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr($email_settings['from_email']); ?>" class="regular-text" required>
                        <p class="description">メールの送信元として表示されるメールアドレス</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_name">送信元名</label>
                    </th>
                    <td>
                        <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($email_settings['from_name']); ?>" class="regular-text" required>
                        <p class="description">メールの送信者名として表示される名前</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reply_to">返信先メールアドレス</label>
                    </th>
                    <td>
                        <input type="email" id="reply_to" name="reply_to" value="<?php echo esc_attr($email_settings['reply_to']); ?>" class="regular-text">
                        <p class="description">空欄の場合は送信元メールアドレスが使用されます</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="return_path">Return-Path</label>
                    </th>
                    <td>
                        <input type="email" id="return_path" name="return_path" value="<?php echo esc_attr($email_settings['return_path']); ?>" class="regular-text">
                        <p class="description">バウンスメールの返送先（通常は送信元と同じで問題ありません）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bcc">BCC（管理者用）</label>
                    </th>
                    <td>
                        <input type="text" id="bcc" name="bcc" value="<?php echo esc_attr($email_settings['bcc']); ?>" class="regular-text">
                        <p class="description">全ての自動送信メールのコピーを受け取るメールアドレス（複数の場合はカンマ区切りで入力してください）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_email">テストメール送信先</label>
                    </th>
                    <td>
                        <input type="email" id="test_email" name="test_email" value="<?php echo esc_attr($email_settings['test_email']); ?>" class="regular-text">
                        <p class="description">テストメール機能で使用する送信先アドレス</p>
                    </td>
                </tr>
            </table>
            
            <hr style="margin: 30px 0;">
            
            <!-- 買い切り購入完了メール -->
            <h2>買い切り購入完了メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="purchase_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="purchase_subject" name="purchase_subject" value="<?php echo esc_attr($email_settings['purchase_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="purchase_message">本文</label>
                    </th>
                    <td>
                        <textarea id="purchase_message" name="purchase_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['purchase_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <!-- サブスクリプション開始メール -->
            <h2>サブスクリプション開始メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="subscription_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="subscription_subject" name="subscription_subject" value="<?php echo esc_attr($email_settings['subscription_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subscription_message">本文</label>
                    </th>
                    <td>
                        <textarea id="subscription_message" name="subscription_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['subscription_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <!-- サブスクリプション更新完了メール -->
            <h2>サブスクリプション更新完了メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="renewal_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="renewal_subject" name="renewal_subject" value="<?php echo esc_attr($email_settings['renewal_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="renewal_message">本文</label>
                    </th>
                    <td>
                        <textarea id="renewal_message" name="renewal_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['renewal_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <!-- サブスクリプション解約メール -->
            <h2>サブスクリプション解約メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cancellation_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="cancellation_subject" name="cancellation_subject" value="<?php echo esc_attr($email_settings['cancellation_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cancellation_message">本文</label>
                    </th>
                    <td>
                        <textarea id="cancellation_message" name="cancellation_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['cancellation_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <!-- 支払い失敗メール -->
            <h2>支払い失敗メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="payment_failed_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="payment_failed_subject" name="payment_failed_subject" value="<?php echo esc_attr($email_settings['payment_failed_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="payment_failed_message">本文</label>
                    </th>
                    <td>
                        <textarea id="payment_failed_message" name="payment_failed_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['payment_failed_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <!-- ステータス変更メール -->
            <h2>ステータス変更メール</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="status_changed_subject">件名</label>
                    </th>
                    <td>
                        <input type="text" id="status_changed_subject" name="status_changed_subject" value="<?php echo esc_attr($email_settings['status_changed_subject']); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="status_changed_message">本文</label>
                    </th>
                    <td>
                        <textarea id="status_changed_message" name="status_changed_message" rows="10" class="large-text"><?php echo esc_textarea($email_settings['status_changed_message']); ?></textarea>
                        <p class="description">空欄の場合はデフォルトのメッセージが使用されます</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_email_settings" class="button button-primary" value="設定を保存">
            </p>
        </form>
        
        <hr style="margin: 40px 0;">
        
        <!-- テストメール送信 -->
        <h2>テストメール送信</h2>
        <p>設定したメールテンプレートのテストメールを送信できます。</p>
        <form method="post" action="" style="background: #f9f9f9; padding: 20px; border-radius: 8px; max-width: 600px;">
            <?php wp_nonce_field('send_test_email_action', 'send_test_email_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email_address">送信先</label>
                    </th>
                    <td>
                        <input type="text" id="test_email_address" name="test_email_address" value="<?php echo esc_attr($email_settings['test_email']); ?>" class="regular-text" required>
                        <p class="description">複数のメールアドレスに送信する場合はカンマ区切りで入力してください（例: user1@example.com, user2@example.com）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_email_type">メール種類</label>
                    </th>
                    <td>
                        <select id="test_email_type" name="test_email_type" class="regular-text">
                            <option value="purchase">買い切り購入完了</option>
                            <option value="subscription">サブスクリプション開始</option>
                            <option value="renewal">サブスクリプション更新完了</option>
                            <option value="cancellation">サブスクリプション解約</option>
                            <option value="payment_failed">支払い失敗</option>
                            <option value="status_changed">ステータス変更</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="send_test_email" class="button button-secondary" value="テストメール送信">
            </p>
        </form>
        
        <style>
            .form-table th {
                width: 200px;
            }
            .form-table code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 13px;
            }
        </style>
        </form>
        <?php endif; ?>
        
        </div>
    </div>
    <?php
}

// ============================================
// ショートコード
// ============================================

// 商品表示用ショートコード
add_shortcode('stripe_product_display', 'product_display_shortcode');
function product_display_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0,
    ), $atts);
    
    $product_id = intval($atts['id']);
    if (!$product_id || get_post_type($product_id) !== 'product') {
        return '<p>商品が見つかりません。</p>';
    }
    
    $product = get_post($product_id);
    $one_time_price = get_post_meta($product_id, '_one_time_price', true);
    $monthly_price = get_post_meta($product_id, '_monthly_price', true);
    $yearly_price = get_post_meta($product_id, '_yearly_price', true);
    $product_description = get_post_meta($product_id, '_product_description', true);
    $thumbnail = get_the_post_thumbnail($product_id, 'medium');
    
    ob_start();
    ?>
    <div class="product-display-card">
        <?php if ($thumbnail): ?>
        <div class="product-thumbnail">
            <?php echo $thumbnail; ?>
        </div>
        <?php endif; ?>
        
        <div class="product-info">
            <h2 class="product-title"><?php echo esc_html($product->post_title); ?></h2>
            
            <?php if ($product_description): ?>
            <div class="product-description">
                <?php echo nl2br(esc_html($product_description)); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($product->post_content): ?>
            <div class="product-content">
                <?php echo wpautop($product->post_content); ?>
            </div>
            <?php endif; ?>
            
            <div class="product-pricing">
                <?php if ($one_time_price): ?>
                <div class="price-option-card" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <div class="price-card-header">
                        <span class="price-label">買い切りプラン</span>
                    </div>
                    <div class="price-card-body">
                        <span class="price-amount">¥<?php echo number_format($one_time_price); ?></span>
                        <span class="price-period">一度のお支払い</span>
                        <button class="stripe-checkout-btn" data-purchase-type="one_time">今すぐ購入</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($monthly_price): ?>
                <div class="price-option-card" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <div class="price-card-header">
                        <span class="price-label">月額プラン</span>
                    </div>
                    <div class="price-card-body">
                        <span class="price-amount">¥<?php echo number_format($monthly_price); ?></span>
                        <span class="price-period">/ 月</span>
                        <button class="stripe-checkout-btn" data-purchase-type="monthly">サブスクリプション開始</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($yearly_price): ?>
                <div class="price-option-card" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <div class="price-card-header">
                        <span class="price-label">年間プラン</span>
                        <?php if ($monthly_price): ?>
                        <span class="price-badge">お得</span>
                        <?php endif; ?>
                    </div>
                    <div class="price-card-body">
                        <span class="price-amount">¥<?php echo number_format($yearly_price); ?></span>
                        <span class="price-period">/ 年</span>
                        <button class="stripe-checkout-btn" data-purchase-type="yearly">サブスクリプション開始</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .product-display-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin: 30px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .product-thumbnail {
            width: 100%;
            overflow: hidden;
        }
        .product-thumbnail img {
            width: 100%;
            height: auto;
            display: block;
        }
        .product-info {
            padding: 30px;
        }
        .product-title {
            font-size: 28px;
            margin: 0 0 20px 0;
            color: #1a1a1a;
        }
        .product-description {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .product-content {
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .product-pricing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .price-option-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .price-option-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        .price-card-header {
            background: #f9fafb;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .price-label {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .price-badge {
            background: #3b82f6;
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .price-card-body {
            padding: 24px 16px;
            text-align: center;
        }
        .price-amount {
            display: block;
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .price-period {
            display: block;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }
        .stripe-checkout-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            width: 100%;
        }
        .stripe-checkout-btn:hover {
            background: #2563eb;
        }
        .stripe-checkout-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .product-info {
                padding: 20px;
            }
            .product-title {
                font-size: 22px;
            }
            .product-pricing {
                grid-template-columns: 1fr;
            }
            .price-amount {
                font-size: 28px;
            }
        }
    </style>
    
    <script>
    (function() {
        // jQuery が読み込まれるまで待機
        function initCheckoutButtons() {
            if (typeof jQuery === 'undefined') {
                setTimeout(initCheckoutButtons, 100);
                return;
            }
            
            jQuery(document).ready(function($) {
                $('.product-display-card .stripe-checkout-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    
                    var button = $(this);
                    var card = button.closest('.price-option-card');
                    var productId = card.data('product-id');
                    var purchaseType = button.data('purchase-type');
                    var originalText = button.text();
                    
                    console.log('Button clicked:', {productId: productId, purchaseType: purchaseType});
                    
                    button.prop('disabled', true).text('処理中...');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        method: 'POST',
                        data: {
                            action: 'create_checkout_session',
                            product_id: productId,
                            purchase_type: purchaseType,
                            nonce: '<?php echo wp_create_nonce('stripe_checkout_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('AJAX success:', response);
                            if (response.success && response.data && response.data.url) {
                                window.location.href = response.data.url;
                            } else {
                                alert('エラー: ' + (response.data && response.data.message ? response.data.message : '決済ページを開けませんでした'));
                                button.prop('disabled', false).text(originalText);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                            alert('通信エラーが発生しました: ' + error);
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                console.log('Stripe checkout buttons initialized:', $('.product-display-card .stripe-checkout-btn').length);
            });
        }
        
        initCheckoutButtons();
    })();
    </script>
    <?php
    return ob_get_clean();
}

// 商品一覧表示用ショートコード
add_shortcode('stripe_products_list', 'products_list_shortcode');
function products_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => -1,
        'columns' => 3,
    ), $atts);
    
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => intval($atts['limit']),
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $products = new WP_Query($args);
    
    if (!$products->have_posts()) {
        return '<p>商品が見つかりません。</p>';
    }
    
    $columns = intval($atts['columns']);
    
    ob_start();
    ?>
    <div class="products-grid" data-columns="<?php echo esc_attr($columns); ?>">
        <?php while ($products->have_posts()): $products->the_post(); 
            $product_id = get_the_ID();
            $one_time_price = get_post_meta($product_id, '_one_time_price', true);
            $monthly_price = get_post_meta($product_id, '_monthly_price', true);
            $yearly_price = get_post_meta($product_id, '_yearly_price', true);
            $thumbnail = get_the_post_thumbnail($product_id, 'medium');
        ?>
        <div class="product-card">
            <?php if ($thumbnail): ?>
            <div class="product-card-thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php echo $thumbnail; ?>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="product-card-content">
                <h3 class="product-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                
                <div class="product-card-excerpt">
                    <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                </div>
                
                <div class="product-card-price">
                    <?php if ($one_time_price): ?>
                    <span class="price">¥<?php echo number_format($one_time_price); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($one_time_price && ($monthly_price || $yearly_price)): ?>
                    <span class="price-separator">または</span>
                    <?php endif; ?>
                    
                    <?php if ($monthly_price): ?>
                    <span class="price">¥<?php echo number_format($monthly_price); ?>/月</span>
                    <?php endif; ?>
                    
                    <?php if ($monthly_price && $yearly_price): ?>
                    <span class="price-separator">/</span>
                    <?php endif; ?>
                    
                    <?php if ($yearly_price): ?>
                    <span class="price">¥<?php echo number_format($yearly_price); ?>/年</span>
                    <?php endif; ?>
                </div>
                
                <a href="<?php the_permalink(); ?>" class="product-card-button">詳細を見る</a>
            </div>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    
    <style>
        .products-grid {
            display: grid;
            gap: 30px;
            margin: 30px 0;
        }
        .products-grid[data-columns="1"] {
            grid-template-columns: 1fr;
        }
        .products-grid[data-columns="2"] {
            grid-template-columns: repeat(2, 1fr);
        }
        .products-grid[data-columns="3"] {
            grid-template-columns: repeat(3, 1fr);
        }
        .products-grid[data-columns="4"] {
            grid-template-columns: repeat(4, 1fr);
        }
        .product-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .product-card-thumbnail {
            width: 100%;
            overflow: hidden;
            background: #f3f4f6;
        }
        .product-card-thumbnail img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }
        .product-card:hover .product-card-thumbnail img {
            transform: scale(1.05);
        }
        .product-card-content {
            padding: 20px;
        }
        .product-card-title {
            font-size: 20px;
            margin: 0 0 12px 0;
        }
        .product-card-title a {
            color: #1a1a1a;
            text-decoration: none;
        }
        .product-card-title a:hover {
            color: #3b82f6;
        }
        .product-card-excerpt {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .product-card-price {
            font-size: 18px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        .product-card-price .price-separator {
            font-size: 14px;
            font-weight: normal;
            color: #666;
            margin: 0 8px;
        }
        .product-card-button {
            display: inline-block;
            width: 100%;
            text-align: center;
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
            font-weight: 500;
        }
        .product-card-button:hover {
            background: #2563eb;
        }
        @media (max-width: 1024px) {
            .products-grid[data-columns="4"],
            .products-grid[data-columns="3"] {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .products-grid {
                grid-template-columns: 1fr !important;
                gap: 20px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

// 有料ライセンス一覧を表示
add_shortcode('stripe_premium_licenses', 'my_licenses_shortcode');

function my_licenses_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>ライセンスキーを表示するにはログインが必要です。</p>';
    }
    
    // スクリプトをフッターに追加
    add_action('wp_footer', 'enqueue_cancel_subscription_script');
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $table_name = $wpdb->prefix . 'license_keys';
    
    $licenses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d OR user_email = %s ORDER BY created_at DESC",
        $current_user->ID,
        $current_user->user_email
    ));
    
    ob_start();
    ?>
    <div class="my-licenses">
        <h2>有料ライセンス</h2>
        
        <?php if (empty($licenses)): ?>
        <div class="no-licenses-message">
            <p>まだライセンスキーを購入していません。</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="licenses-table">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>ライセンスキー</th>
                        <th>購入タイプ</th>
                        <th>ステータス</th>
                        <th>購入日</th>
                        <th>次回請求日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licenses as $license): ?>
                <tr>
                    <td><?php echo esc_html(get_the_title($license->product_id)); ?></td>
                    <td><code class="premium-license-key"><?php echo esc_html($license->license_key); ?></code></td>
                    <td>
                        <?php 
                        $purchase_type_labels = array(
                            'one_time' => '買い切り',
                            'monthly' => '月額プラン',
                            'yearly' => '年間プラン'
                        );
                        echo $purchase_type_labels[$license->purchase_type] ?? $license->purchase_type;
                        ?>
                    </td>
                    <td>
                        <?php 
                        $status_labels = array(
                            'active' => '<span class="status-active">有効</span>',
                            'inactive' => '<span class="status-inactive">無効</span>',
                            'canceled' => '<span class="status-canceled">キャンセル</span>',
                            'payment_failed' => '<span class="status-failed">支払い失敗</span>'
                        );
                        echo $status_labels[$license->status] ?? $license->status;
                        ?>
                    </td>
                    <td><?php echo esc_html(date('Y/m/d', strtotime($license->created_at))); ?></td>
                    <td>
                        <?php 
                        $is_subscription = in_array($license->purchase_type, array('monthly', 'yearly'));
                        if ($is_subscription && $license->expires_at && $license->status === 'active') {
                            echo '<span class="next-payment-date">' . esc_html(date('Y/m/d', strtotime($license->expires_at))) . '</span>';
                        } elseif ($is_subscription && $license->status === 'canceled') {
                            echo '<span class="status-canceled">解約済み</span>';
                        } else {
                            echo '−';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($license->status === 'active'): ?>
                        <button class="change-plan-btn" 
                                data-license-id="<?php echo esc_attr($license->id); ?>"
                                data-product-id="<?php echo esc_attr($license->product_id); ?>"
                                data-purchase-type="<?php echo esc_attr($license->purchase_type); ?>"
                                data-subscription-id="<?php echo esc_attr($license->stripe_subscription_id); ?>">
                            プラン変更
                        </button>
                        <?php endif; ?>
                        <?php 
                        $is_subscription = in_array($license->purchase_type, array('monthly', 'yearly'));
                        if ($is_subscription && $license->status === 'active' && $license->stripe_subscription_id): 
                        ?>
                        <button class="cancel-subscription-btn" 
                                data-subscription-id="<?php echo esc_attr($license->stripe_subscription_id); ?>"
                                data-license-id="<?php echo esc_attr($license->id); ?>">
                            解約する
                        </button>
                        <?php endif; ?>
                        <?php if ($is_subscription): ?>
                        <button class="view-invoice-history-btn" 
                                data-license-id="<?php echo esc_attr($license->id); ?>">
                            請求履歴
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        
        <!-- プラン変更モーダル -->
        <div id="change-plan-modal" class="change-plan-modal" style="display:none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>プラン変更</h3>
                    <button class="modal-close" aria-label="閉じる">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="current-plan-info"></p>
                    <div class="available-plans"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary modal-cancel">キャンセル</button>
                </div>
            </div>
        </div>
        
        <!-- 請求履歴モーダル -->
        <div id="invoice-history-modal" class="invoice-history-modal" style="display:none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>請求履歴</h3>
                    <button class="modal-close" aria-label="閉じる">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="invoice-history-content"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary modal-cancel">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .my-licenses {
            margin: 40px 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .no-licenses-message {
            color: #999;
            padding: 40px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .no-licenses-message p {
            margin: 0;
            font-size: 16px;
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
            border: 1px solid #e5e7eb;
        }
        .licenses-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            margin: 0;
        }
        .licenses-table th {
            background: #0b6bbf;
            color: white;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }
        .licenses-table th:nth-child(1) { width: 20%; }
        .licenses-table th:nth-child(2) { width: 18%; }
        .licenses-table th:nth-child(3) { width: 12%; }
        .licenses-table th:nth-child(4) { width: 12%; }
        .licenses-table th:nth-child(5) { width: 12%; }
        .licenses-table th:nth-child(6) { width: 12%; }
        .licenses-table th:nth-child(7) { width: 14%; }
        .licenses-table td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            background: white;
            transition: background-color 0.2s;
            white-space: nowrap;
            font-size: 14px;
        }
        .licenses-table td:nth-child(1),
        .licenses-table td:nth-child(2) {
            white-space: normal;
            word-break: break-word;
        }
        .licenses-table tbody tr:hover td {
            background-color: #f9fafb;
        }
        .licenses-table tbody tr:last-child td {
            border-bottom: none;
        }
        .premium-license-key {
            background: #e8f4fd;
            padding: 8px 12px !important;
            border-radius: 6px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #0b6bbf;
            border: 1px solid #0b6bbf;
            display: inline-block;
            word-break: break-all;
            max-width: 100%;
            box-sizing: content-box;
            line-height: 1.3 !important;
        }
        .status-active { 
            color: #10b981; 
            font-weight: 700;
            background: #d1fae5;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 13px;
        }
        .status-inactive { 
            color: #f59e0b; 
            font-weight: 700;
            background: #fef3c7;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 13px;
        }
        .status-canceled { 
            color: #ef4444; 
            font-weight: 700;
            background: #fee2e2;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 13px;
        }
        .status-failed { 
            color: #dc2626; 
            font-weight: 700;
            background: #fecaca;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 13px;
        }
        .next-payment-date { 
            color: #0b6bbf; 
            font-weight: 600;
            background: #e8f4fd;
            padding: 4px 12px;
            border-radius: 6px;
            display: inline-block;
            font-size: 13px;
        }
        .change-plan-btn {
            background: #0b6bbf;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 4px;
            display: block;
            width: 100%;
        }
        .change-plan-btn:hover {
            background: #094e8f;
        }
        .sync-stripe-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 4px;
            display: block;
            width: 100%;
        }
        .sync-stripe-btn:hover {
            background: #059669;
        }
        .sync-stripe-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .cancel-subscription-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .cancel-subscription-btn:hover {
            background: #b91c1c;
        }
        .cancel-subscription-btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }
        .view-invoice-history-btn {
            background: #0b6bbf;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        .invoice-history-container {
            background: #f9fafb;
            padding: 24px;
            border-top: 3px solid #0b6bbf;
            margin: 8px 0;
        }
        .invoice-history-container h4 {
            margin: 0 0 16px 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 700;
        }
        .invoice-history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .invoice-history-table th,
        .invoice-history-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        .invoice-history-table th {
            background: #e8f4fd;
            font-weight: 700;
            font-size: 13px;
            color: #0b6bbf;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-history-table td {
            font-size: 14px;
            color: #374151;
        }
        .invoice-history-table tbody tr:hover {
            background-color: #f9fafb;
        }
        .invoice-status-paid {
            color: #059669;
            font-weight: 700;
            background: #d1fae5;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }
        .invoice-status-failed {
            color: #dc2626;
            font-weight: 700;
            background: #fee2e2;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }
        .no-invoice-history {
            text-align: center;
            color: #9ca3af;
            padding: 32px;
            margin: 0;
            font-size: 15px;
            font-weight: 500;
        }
        .loading-message {
            text-align: center;
            color: #0b6bbf;
            padding: 32px;
            margin: 0;
            font-size: 15px;
            font-weight: 500;
        }
        .error-message {
            text-align: center;
            color: #dc2626;
            padding: 32px;
            margin: 0;
            font-size: 15px;
            font-weight: 500;
        }
        
        /* プラン変更モーダル・請求履歴モーダル共通 */
        .change-plan-modal,
        .invoice-history-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
        }
        .modal-content {
            position: relative;
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 1;
        }
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0b6bbf;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 32px;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            line-height: 1;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: #374151;
        }
        .modal-body {
            padding: 24px;
        }
        .current-plan-info {
            background: #e8f4fd;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #0b6bbf;
            font-weight: 600;
        }
        .available-plans {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .plan-option {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .plan-option:hover {
            border-color: #0b6bbf;
            background: #f9fafb;
        }
        .plan-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f9fafb;
        }
        .plan-option.disabled:hover {
            border-color: #e5e7eb;
        }
        .plan-info {
            flex: 1;
        }
        .plan-name {
            font-weight: 700;
            font-size: 16px;
            color: #111827;
            margin-bottom: 4px;
        }
        .plan-price {
            color: #0b6bbf;
            font-weight: 600;
            font-size: 18px;
        }
        .plan-type {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }
        .plan-select-btn {
            background: #0b6bbf;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
        }
        .plan-select-btn:hover {
            background: #094e8f;
        }
        .plan-select-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .loading-message {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 1024px) {
            .licenses-table {
             sync-stripe-btn,
            .   min-width: 900px;
            }
            .licenses-table th,
            .licenses-table td {
                padding: 12px 8px;
                font-size: 13px;
            }
            .premium-license-key {
                font-size: 11px;
                padding: 6px 8px;
            }
        }
        
        @media (max-width: 768px) {
            .my-licenses {
                margin: 20px 0;
            }
            .licenses-table {
                min-width: 800px;
            }
            .change-plan-btn,
            .cancel-subscription-btn,
            .view-invoice-history-btn {
                padding: 6px 12px;
                font-size: 12px;
                margin-bottom: 4px;
                display: block;
                width: 100%;
            }
            .view-invoice-history-btn {
                margin-left: 0;
                margin-top: 4px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

// 解約スクリプトをフッターに出力
function enqueue_cancel_subscription_script() {
    static $script_added = false;
    if ($script_added) return;
    $script_added = true;
    
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('cancel_subscription');
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
        var cancelNonce = <?php echo json_encode($nonce); ?>;
        
        function initCancelButtons() {
            var buttons = document.querySelectorAll('.cancel-subscription-btn');
            
            buttons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    var subscriptionId = btn.getAttribute('data-subscription-id');
                    var licenseId = btn.getAttribute('data-license-id');
                    
                    console.log('Cancel button clicked', {
                        subscriptionId: subscriptionId, 
                        licenseId: licenseId, 
                        ajaxUrl: ajaxUrl, 
                        cancelNonce: cancelNonce
                    });
                    
                    if (!confirm('本当にサブスクリプションを解約しますか？\n\n解約すると、次回の請求日以降はライセンスが無効になります。')) {
                        return;
                    }
                    
                    btn.disabled = true;
                    btn.textContent = '処理中...';
                    
                    var formData = new FormData();
                    formData.append('action', 'cancel_subscription');
                    formData.append('subscription_id', subscriptionId);
                    formData.append('license_id', licenseId);
                    formData.append('nonce', cancelNonce);
                    
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        console.log('Ajax response:', data);
                        if (data.success) {
                            alert('サブスクリプションを解約しました。');
                            location.reload();
                        } else {
                            var errorMsg = (data.data && data.data.message) ? data.data.message : '解約に失敗しました';
                            alert('エラー: ' + errorMsg);
                            btn.disabled = false;
                            btn.textContent = '解約する';
                        }
                    })
                    .catch(function(error) {
                        console.error('Ajax error:', error);
                        alert('通信エラーが発生しました。');
                        btn.disabled = false;
                        btn.textContent = '解約する';
                    });
                });
            });
        }
        
        // 請求履歴モーダル表示機能
        function initInvoiceHistoryButtons() {
            var buttons = document.querySelectorAll('.view-invoice-history-btn');
            var modal = document.getElementById('invoice-history-modal');
            var modalClose = modal ? modal.querySelector('.modal-close') : null;
            var modalCancel = modal ? modal.querySelector('.modal-cancel') : null;
            var modalOverlay = modal ? modal.querySelector('.modal-overlay') : null;
            
            buttons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    var licenseId = btn.getAttribute('data-license-id');
                    openInvoiceHistoryModal(licenseId);
                });
            });
            
            function closeModal() {
                if (modal) {
                    modal.style.display = 'none';
                }
            }
            
            if (modalClose) {
                modalClose.addEventListener('click', closeModal);
            }
            if (modalCancel) {
                modalCancel.addEventListener('click', closeModal);
            }
            if (modalOverlay) {
                modalOverlay.addEventListener('click', closeModal);
            }
        }
        
        function openInvoiceHistoryModal(licenseId) {
            var modal = document.getElementById('invoice-history-modal');
            var contentContainer = modal.querySelector('.invoice-history-content');
            
            if (!modal) return;
            
            // ローディング表示
            contentContainer.innerHTML = '<div class="loading-message">読み込み中...</div>';
            
            // モーダルを表示
            modal.style.display = 'flex';
            
            // 請求履歴を取得
            var formData = new FormData();
            formData.append('action', 'get_invoice_history');
            formData.append('license_id', licenseId);
            formData.append('nonce', cancelNonce);
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.data.invoices) {
                    if (data.data.invoices.length > 0) {
                        var html = '<table class="invoice-history-table">';
                        html += '<thead><tr><th>請求日</th><th>金額</th><th>ステータス</th></tr></thead>';
                        html += '<tbody>';
                        
                        data.data.invoices.forEach(function(invoice) {
                            html += '<tr>';
                            html += '<td>' + escapeHtml(invoice.invoice_date) + '</td>';
                            html += '<td>¥' + numberFormat(invoice.amount) + ' ' + escapeHtml(invoice.currency) + '</td>';
                            html += '<td>';
                            if (invoice.status === 'paid') {
                                html += '<span class="invoice-status-paid">支払い済み</span>';
                            } else {
                                html += '<span class="invoice-status-failed">失敗</span>';
                            }
                            html += '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        contentContainer.innerHTML = html;
                    } else {
                        contentContainer.innerHTML = '<p class="no-invoice-history">請求履歴はまだありません。</p>';
                    }
                } else {
                    contentContainer.innerHTML = '<p class="error-message">請求履歴の取得に失敗しました。</p>';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                contentContainer.innerHTML = '<p class="error-message">通信エラーが発生しました。</p>';
            });
        }
        
        // プラン変更機能
        function initChangePlanButtons() {
            var changePlanButtons = document.querySelectorAll('.change-plan-btn');
            var modal = document.getElementById('change-plan-modal');
            var modalClose = modal ? modal.querySelector('.modal-close') : null;
            var modalCancel = modal ? modal.querySelector('.modal-cancel') : null;
            var modalOverlay = modal ? modal.querySelector('.modal-overlay') : null;
            
            changePlanButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    var licenseId = btn.getAttribute('data-license-id');
                    var productId = btn.getAttribute('data-product-id');
                    var purchaseType = btn.getAttribute('data-purchase-type');
                    var subscriptionId = btn.getAttribute('data-subscription-id');
                    
                    openChangePlanModal(licenseId, productId, purchaseType, subscriptionId);
                });
            });
            
            function closeModal() {
                if (modal) {
                    modal.style.display = 'none';
                }
            }
            
            if (modalClose) {
                modalClose.addEventListener('click', closeModal);
            }
            if (modalCancel) {
                modalCancel.addEventListener('click', closeModal);
            }
            if (modalOverlay) {
                modalOverlay.addEventListener('click', closeModal);
            }
        }
        
        function openChangePlanModal(licenseId, productId, purchaseType, subscriptionId) {
            var modal = document.getElementById('change-plan-modal');
            var currentPlanInfo = modal.querySelector('.current-plan-info');
            var availablePlansContainer = modal.querySelector('.available-plans');
            
            if (!modal) return;
            
            // 現在のプラン情報を表示
            var planTypeLabels = {
                'one_time': '買い切り',
                'monthly': '月額プラン',
                'yearly': '年間プラン'
            };
            var currentPlanText = planTypeLabels[purchaseType] || purchaseType;
            currentPlanInfo.textContent = '現在のプラン: ' + currentPlanText;
            
            // ローディング表示
            availablePlansContainer.innerHTML = '<div class="loading-message">利用可能なプランを読み込み中...</div>';
            
            // モーダルを表示
            modal.style.display = 'flex';
            
            // 利用可能なプランを取得
            var formData = new FormData();
            formData.append('action', 'get_available_plans');
            formData.append('license_id', licenseId);
            formData.append('product_id', productId);
            formData.append('current_type', purchaseType);
            formData.append('nonce', cancelNonce);
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                console.log('Available plans response:', data);
                if (data.success && data.data.plans) {
                    if (data.data.debug) {
                        console.log('Debug info:', data.data.debug);
                    }
                    renderAvailablePlans(data.data.plans, licenseId, subscriptionId, purchaseType);
                } else {
                    availablePlansContainer.innerHTML = '<p style="text-align:center;color:#dc2626;">プランの取得に失敗しました。</p>';
                    if (data.data && data.data.message) {
                        console.error('Error message:', data.data.message);
                    }
                }
            })
            .catch(function(error) {
                console.error('Error fetching plans:', error);
                availablePlansContainer.innerHTML = '<p style="text-align:center;color:#dc2626;">エラーが発生しました。</p>';
            });
        }
        
        function renderAvailablePlans(plans, licenseId, subscriptionId, currentType) {
            var container = document.querySelector('.available-plans');
            if (!container) return;
            
            if (plans.length === 0) {
                container.innerHTML = '<p style="text-align:center;color:#6b7280;">利用可能なプランがありません。</p>';
                return;
            }
            
            var html = '';
            plans.forEach(function(plan) {
                var isDisabled = plan.is_current || !plan.is_available;
                var disabledClass = isDisabled ? ' disabled' : '';
                html += '<div class="plan-option' + disabledClass + '">';
                html += '  <div class="plan-info">';
                html += '    <div class="plan-name">' + escapeHtml(plan.name) + '</div>';
                html += '    <div class="plan-price">¥' + numberFormat(plan.price) + '</div>';
                html += '    <div class="plan-type">' + escapeHtml(plan.type_label) + '</div>';
                if (!plan.is_available) {
                    html += '    <div style="color:#dc2626;font-size:12px;margin-top:4px;">⚠ Stripe Price ID未設定</div>';
                }
                html += '  </div>';
                if (plan.is_current) {
                    html += '  <span style="color:#6b7280;font-size:14px;">現在のプラン</span>';
                } else if (!plan.is_available) {
                    html += '  <span style="color:#9ca3af;font-size:13px;">設定が必要</span>';
                } else {
                    html += '  <button class="plan-select-btn" onclick="selectPlan(\'' + escapeHtml(plan.type) + '\', \'' + escapeHtml(plan.stripe_price_id) + '\', ' + licenseId + ', \'' + escapeHtml(subscriptionId) + '\', \'' + escapeHtml(currentType) + '\')">選択</button>';
                }
                html += '</div>';
            });
            
            container.innerHTML = html;
        }
        
        window.selectPlan = function(newType, stripePriceId, licenseId, subscriptionId, currentType) {
            if (!confirm('プランを変更しますか？')) {
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'change_subscription_plan');
            formData.append('license_id', licenseId);
            formData.append('new_type', newType);
            formData.append('stripe_price_id', stripePriceId);
            formData.append('subscription_id', subscriptionId);
            formData.append('current_type', currentType);
            formData.append('nonce', cancelNonce);
            
            // ボタンを無効化
            event.target.disabled = true;
            event.target.textContent = '処理中...';
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    alert(data.data.message || 'プランを変更しました。');
                    location.reload();
                } else {
                    alert('エラー: ' + (data.data && data.data.message ? data.data.message : 'プラン変更に失敗しました'));
                    event.target.disabled = false;
                    event.target.textContent = '選択';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('通信エラーが発生しました。');
                event.target.disabled = false;
                event.target.textContent = '選択';
            });
        }
        
        // ヘルパー関数
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function numberFormat(num) {
            return Number(num).toLocaleString('ja-JP');
        }
        
        // DOMが読み込まれたら実行
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initCancelButtons();
                initInvoiceHistoryButtons();
                initChangePlanButtons();
            });
        } else {
            initCancelButtons();
            initInvoiceHistoryButtons();
            initChangePlanButtons();
        }
    })();
    </script>
    <?php
}

// ============================================
// サブスクリプション解約
// ============================================

// サブスクリプション解約のAJAX処理
add_action('wp_ajax_cancel_subscription', 'cancel_subscription_ajax');
function cancel_subscription_ajax() {
    // Nonceチェック
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cancel_subscription')) {
        wp_send_json_error(array('message' => '不正なリクエストです。'));
        return;
    }
    
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'ログインが必要です。'));
        return;
    }
    
    $user_id = get_current_user_id();
    $subscription_id = sanitize_text_field($_POST['subscription_id']);
    $license_id = intval($_POST['license_id']);
    
    // パラメータチェック
    if (empty($subscription_id) || empty($license_id)) {
        wp_send_json_error(array('message' => '必須パラメータが不足しています。'));
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    // ライセンスが現在のユーザーのものか確認
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND stripe_subscription_id = %s",
        $license_id, $user_id, $subscription_id
    ));
    
    if (!$license) {
        wp_send_json_error(array('message' => 'ライセンスが見つからないか、権限がありません。'));
        return;
    }
    
    if (!in_array($license->purchase_type, array('monthly', 'yearly'))) {
        wp_send_json_error(array('message' => 'このライセンスはサブスクリプションではありません。'));
        return;
    }
    
    if ($license->status !== 'active') {
        wp_send_json_error(array('message' => 'このサブスクリプションは既に無効です。'));
        return;
    }
    
    // Stripe APIでサブスクリプションをキャンセル
    try {
        $stripe_keys = get_stripe_keys();
        
        // APIキーの確認
        if (empty($stripe_keys['secret_key'])) {
            wp_send_json_error(array('message' => 'Stripe APIキーが設定されていません。'));
            return;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/{$subscription_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $stripe_keys['secret_key'],
            'Content-Type: application/x-www-form-urlencoded'
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // デバッグ情報
        error_log('Stripe Cancel Subscription Response: ' . $response);
        error_log('HTTP Code: ' . $http_code);
        
        if ($curl_error) {
            wp_send_json_error(array('message' => 'cURLエラー: ' . $curl_error));
            return;
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Stripe APIエラー';
            wp_send_json_error(array('message' => 'Stripeでの解約処理に失敗しました: ' . $error_message));
            return;
        }
        
        // データベースのステータスを更新
        $wpdb->update(
            $table_name,
            array('status' => 'canceled'),
            array('id' => $license_id),
            array('%s'),
            array('%d')
        );
        
        // 成功通知メールを送信
        $user = get_userdata($user_id);
        $product_title = get_the_title($license->product_id);
        
        $to = $user->user_email;
        $subject = 'サブスクリプション解約完了 - ' . get_bloginfo('name');
        $message = "こんにちは {$user->display_name} 様\n\n";
        $message .= "以下のサブスクリプションを解約いたしました。\n\n";
        $message .= "商品: {$product_title}\n";
        $message .= "ライセンスキー: {$license->license_key}\n\n";
        $message .= "ご利用ありがとうございました。\n\n";
        $message .= "---\n";
        $message .= get_bloginfo('name');
        
        wp_mail($to, $subject, $message);
        
        wp_send_json_success(array('message' => 'サブスクリプションを解約しました。'));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'エラーが発生しました: ' . $e->getMessage()));
    }
}

// ============================================
// REST API
// ============================================

// REST APIでライセンスキーの検証エンドポイントを追加
add_action('rest_api_init', function() {
    register_rest_route('license/v1', '/verify', array(
        'methods' => 'POST',
        'callback' => 'verify_license_key_api',
        'permission_callback' => '__return_true'
    ));
});

function verify_license_key_api($request) {
    $license_key = $request->get_param('license_key');
    $product_id = $request->get_param('product_id');
    
    if (!$license_key) {
        return new WP_REST_Response(array(
            'valid' => false,
            'message' => 'ライセンスキーが指定されていません。'
        ), 400);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    $where = $wpdb->prepare("WHERE license_key = %s", $license_key);
    if ($product_id) {
        $where .= $wpdb->prepare(" AND product_id = %d", $product_id);
    }
    
    $license = $wpdb->get_row("SELECT * FROM $table_name $where");
    
    if (!$license) {
        return new WP_REST_Response(array(
            'valid' => false,
            'message' => 'ライセンスキーが見つかりません。'
        ), 404);
    }
    
    if ($license->status !== 'active') {
        return new WP_REST_Response(array(
            'valid' => false,
            'message' => 'ライセンスキーは無効です。',
            'status' => $license->status
        ), 200);
    }
    
    return new WP_REST_Response(array(
        'valid' => true,
        'message' => 'ライセンスキーは有効です。',
        'license' => array(
            'product_id' => $license->product_id,
            'product_name' => get_the_title($license->product_id),
            'purchase_type' => $license->purchase_type,
            'created_at' => $license->created_at
        )
    ), 200);
}
