<?php

/* 子テーマのfunctions.phpは、親テーマのfunctions.phpより先に読み込まれることに注意してください。 */


/**
 * 親テーマのfunctions.phpのあとで読み込みたいコードはこの中に。
 */
// add_filter('after_setup_theme', function(){
// }, 11);


/**
 * 子テーマでのファイルの読み込み
 */
add_action('wp_enqueue_scripts', function() {
	
	$timestamp = date( 'Ymdgis', filemtime( get_stylesheet_directory() . '/style.css' ) );
	wp_enqueue_style( 'child_style', get_stylesheet_directory_uri() .'/style.css', [], $timestamp );

	/* その他の読み込みファイルはこの下に記述 */

}, 11);






/*.以下カスタマイズコードになります */

// メール送信元をカスタマイズ
add_filter('wp_mail_from', 'sokulabo_custom_mail_from');
function sokulabo_custom_mail_from($original_email_address) {
    return 'info@sokulabo.com';
}

add_filter('wp_mail_from_name', 'sokulabo_custom_mail_from_name');
function sokulabo_custom_mail_from_name($original_email_from) {
    return '速ラボ PRODUCTS';
}

// wp-login.phpへのアクセスを/login/にリダイレクト
add_action('login_head', 'sokulabo_redirect_login_page_js');
function sokulabo_redirect_login_page_js() {
    // POSTデータがある場合はリダイレクトしない
    if (!empty($_POST)) {
        return;
    }
    
    // 管理画面の認証チェック（interim-login）は除外
    if (isset($_GET['interim-login'])) {
        return;
    }
    
    // 特定のアクション（パスワードリセット、ログアウトなど）は除外
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $excluded_actions = array('logout', 'lostpassword', 'resetpass', 'rp', 'register', 'postpass', 'retrievepassword');
    
    // 確認メッセージがある場合も除外（パスワードリセット送信後など）
    if (isset($_GET['checkemail']) || isset($_GET['registration']) || isset($_GET['confirmaction'])) {
        return;
    }
    
    if (!in_array($action, $excluded_actions)) {
        ?>
        <script type="text/javascript">
            window.location.href = '<?php echo home_url('/login/'); ?>';
        </script>
        <?php
        exit;
    }
}

// ログイン認証エラーを捕捉（WordPressの標準エラーメッセージを保持）
add_filter('authenticate', 'sokulabo_capture_auth_error', 100, 3);
function sokulabo_capture_auth_error($user, $username, $password) {
    if (is_wp_error($user)) {
        // エラーメッセージを取得してグローバル変数に保存
        global $sokulabo_login_error_message;
        $sokulabo_login_error_message = $user->get_error_message();
    }
    return $user;
}

// ログインエラー時にカスタムログインページへリダイレクト
add_action('wp_login_failed', 'sokulabo_redirect_on_login_fail');
function sokulabo_redirect_on_login_fail($username) {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // カスタムログインページからのリクエストのみ処理
    if (strpos($referrer, '/login') !== false) {
        global $sokulabo_login_error_message;
        
        // エラーメッセージをクッキーに保存
        if (!empty($sokulabo_login_error_message)) {
            setcookie('login_error', $sokulabo_login_error_message, time() + 60, '/');
        }
        
        wp_redirect(home_url('/login/?login_error=1'));
        exit;
    }
}

// // ユーザープロフィールのアバターを任意の画像に差し替える
// add_filter( 'get_avatar_url', 'my_custom_avatar_url', 10, 3 );
// function my_custom_avatar_url( $url, $id_or_email, $args ) {
//     // 差し替えたい画像のURLを指定（テーマ直下や外部URLもOK）
//     $custom_avatar_url = 'https://sokulabo.com/products/wp-content/uploads/2025/10/favicon-2-300x300-1.png';
//     return $custom_avatar_url;
// }

// 管理画面のプロフィールやコメント欄などにも反映させる
add_filter( 'get_avatar', 'my_custom_avatar_img', 10, 6 );
function my_custom_avatar_img( $avatar, $id_or_email, $size, $default, $alt, $args ) {
    $custom_avatar_url = 'https://sokulabo.com/products/wp-content/uploads/2025/10/favicon-2-300x300-1.png';
    $avatar = sprintf(
        '<img src="%s" alt="%s" width="%d" height="%d" class="avatar avatar-%d photo" />',
        esc_url( $custom_avatar_url ),
        esc_attr( $alt ),
        (int) $size,
        (int) $size,
        (int) $size
    );
    return $avatar;
}

/**
 * ========================
 * SokuLabo Products - ライセンス管理システム
 * ========================
 */

// カスタム投稿タイプ: ライセンス
add_action('init', 'sokulabo_register_license_post_type');
function sokulabo_register_license_post_type() {
    register_post_type('sokulabo_license', array(
        'labels' => array(
            'name' => 'ライセンス',
            'singular_name' => 'ライセンス',
            'add_new' => '新規追加',
            'add_new_item' => '新しいライセンスを追加',
            'edit_item' => 'ライセンスを編集',
            'view_item' => 'ライセンスを表示',
            'search_items' => 'ライセンスを検索',
            'not_found' => 'ライセンスが見つかりませんでした',
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-admin-network',
        'supports' => array('title', 'custom-fields'),
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'show_in_rest' => true,
    ));
}

// 管理画面カラムカスタマイズ
add_filter('manage_sokulabo_license_posts_columns', 'sokulabo_license_columns');
function sokulabo_license_columns($columns) {
    $new_columns = array(
        'cb' => $columns['cb'],
        'title' => 'ライセンスキー',
        'plugin_type' => 'プラグイン',
        'customer_id' => 'ユーザーID',
        'status' => 'ステータス',
        'expires' => '有効期限',
        'sites' => '使用サイト数',
        'max_sites' => '最大サイト数',
        'product' => '商品タイプ',
        'date' => '作成日',
    );
    return $new_columns;
}

// カラムの内容表示
add_action('manage_sokulabo_license_posts_custom_column', 'sokulabo_license_column_content', 10, 2);
function sokulabo_license_column_content($column, $post_id) {
    switch ($column) {
        case 'customer_id':
            $customer_id = get_post_meta($post_id, '_license_customer_id', true);
            if ($customer_id) {
                $user = get_userdata($customer_id);
                if ($user) {
                    echo '<a href="' . admin_url('user-edit.php?user_id=' . $customer_id) . '" target="_blank">';
                    echo esc_html($customer_id) . '<br><small>' . esc_html($user->user_login) . '</small>';
                    echo '</a>';
                } else {
                    echo esc_html($customer_id) . '<br><small style="color: red;">ユーザー削除済み</small>';
                }
            } else {
                echo '-';
            }
            break;
            
        case 'status':
            $status = get_post_status($post_id);
            if ($status === 'publish') {
                echo '<span style="color: green; font-weight: bold;">✓ 有効</span>';
            } else {
                echo '<span style="color: red;">✗ 無効</span>';
            }
            break;
            
        case 'expires':
            $expires = get_post_meta($post_id, '_license_expires', true);
            if ($expires) {
                $expires_time = strtotime($expires);
                $is_expired = $expires_time < time();
                $color = $is_expired ? 'red' : ($expires_time < strtotime('+30 days') ? 'orange' : 'inherit');
                echo '<span style="color: ' . $color . ';">' . esc_html($expires) . '</span>';
                if ($is_expired) {
                    echo '<br><small style="color: red;">期限切れ</small>';
                }
            } else {
                echo '無期限';
            }
            break;
            
        case 'sites':
            $activations = get_post_meta($post_id, '_license_activations', true);
            $activations = $activations ? json_decode($activations, true) : array();
            echo count($activations);
            break;
            
        case 'max_sites':
            $max_sites = get_post_meta($post_id, '_license_max_sites', true);
            echo $max_sites ?: '1';
            break;
            
        case 'plugin_type':
            $plugin_type = get_post_meta($post_id, '_license_plugin_type', true);
            $plugin_names = array(
                'automagic-image-generate' => 'Automagic Image Generate',
                'product-link-maker' => 'Product Link Maker',
            );
            echo isset($plugin_names[$plugin_type]) ? $plugin_names[$plugin_type] : '-';
            break;
            
        case 'product':
            $product = get_post_meta($post_id, '_license_product', true);
            echo $product ?: '-';
            break;
    }
}

// メタボックス: ライセンス情報
add_action('add_meta_boxes', 'sokulabo_license_meta_boxes');
function sokulabo_license_meta_boxes() {
    add_meta_box(
        'sokulabo_license_info',
        'ライセンス情報',
        'sokulabo_license_info_metabox',
        'sokulabo_license',
        'normal',
        'high'
    );
    
    add_meta_box(
        'sokulabo_license_activations',
        'アクティベーション情報',
        'sokulabo_license_activations_metabox',
        'sokulabo_license',
        'normal',
        'default'
    );
}

// ライセンス情報メタボックス
function sokulabo_license_info_metabox($post) {
    wp_nonce_field('sokulabo_license_meta_save', 'sokulabo_license_meta_nonce');
    
    $max_sites = get_post_meta($post->ID, '_license_max_sites', true) ?: '1';
    $expires = get_post_meta($post->ID, '_license_expires', true);
    $product = get_post_meta($post->ID, '_license_product', true);
    $customer_id = get_post_meta($post->ID, '_license_customer_id', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="license_max_sites">最大サイト数</label></th>
            <td>
                <input type="number" id="license_max_sites" name="license_max_sites" 
                       value="<?php echo esc_attr($max_sites); ?>" min="1" max="999" class="small-text">
                <p class="description">このライセンスで使用できる最大サイト数（999で実質無制限）</p>
            </td>
        </tr>
        <tr>
            <th><label for="license_expires">有効期限</label></th>
            <td>
                <input type="date" id="license_expires" name="license_expires" 
                       value="<?php echo esc_attr($expires); ?>" class="regular-text">
                <p class="description">空欄の場合は無期限</p>
            </td>
        </tr>
        <tr>
            <th><label for="license_product">商品タイプ</label></th>
            <td>
                <select id="license_product" name="license_product">
                    <option value="">未設定</option>
                    <option value="single" <?php selected($product, 'single'); ?>>シングルサイト</option>
                    <option value="3sites" <?php selected($product, '3sites'); ?>>3サイト</option>
                    <option value="unlimited" <?php selected($product, 'unlimited'); ?>>無制限</option>
                    <option value="lifetime" <?php selected($product, 'lifetime'); ?>>ライフタイム</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="license_customer_id">顧客ID</label></th>
            <td>
                <input type="number" id="license_customer_id" name="license_customer_id" 
                       value="<?php echo esc_attr($customer_id); ?>" class="small-text">
                <p class="description">WordPressユーザーID（購入者）</p>
            </td>
        </tr>
    </table>
    <?php
}

// アクティベーション情報メタボックス
function sokulabo_license_activations_metabox($post) {
    $activations = get_post_meta($post->ID, '_license_activations', true);
    $activations = $activations ? json_decode($activations, true) : array();
    
    if (empty($activations)) {
        echo '<p>まだアクティベーションされていません。</p>';
        return;
    }
    
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>サイトURL</th>
                <th>アクティベート日時</th>
                <th>最終確認</th>
                <th>IPアドレス</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activations as $index => $activation): ?>
            <tr>
                <td><a href="<?php echo esc_url($activation['site_url']); ?>" target="_blank"><?php echo esc_html($activation['site_url']); ?></a></td>
                <td><?php echo esc_html($activation['activated_at'] ?? '-'); ?></td>
                <td><?php echo esc_html($activation['last_check'] ?? '-'); ?></td>
                <td><?php echo esc_html($activation['ip'] ?? '-'); ?></td>
                <td>
                    <button type="button" class="button button-small" 
                            onclick="if(confirm('このサイトのアクティベーションを解除しますか？')) { 
                                document.getElementById('remove_activation_<?php echo $index; ?>').value = '1'; 
                                document.getElementById('post').submit(); 
                            }">
                        解除
                    </button>
                    <input type="hidden" id="remove_activation_<?php echo $index; ?>" 
                           name="remove_activation[<?php echo $index; ?>]" value="0">
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// メタデータ保存
add_action('save_post_sokulabo_license', 'sokulabo_license_save_meta', 10, 2);
function sokulabo_license_save_meta($post_id, $post) {
    // Nonce確認
    if (!isset($_POST['sokulabo_license_meta_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_license_meta_nonce'], 'sokulabo_license_meta_save')) {
        return;
    }
    
    // 自動保存をスキップ
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // メタデータ保存
    if (isset($_POST['license_max_sites'])) {
        update_post_meta($post_id, '_license_max_sites', intval($_POST['license_max_sites']));
    }
    
    if (isset($_POST['license_expires'])) {
        update_post_meta($post_id, '_license_expires', sanitize_text_field($_POST['license_expires']));
    }
    
    if (isset($_POST['license_product'])) {
        update_post_meta($post_id, '_license_product', sanitize_text_field($_POST['license_product']));
    }
    
    if (isset($_POST['license_customer_id'])) {
        update_post_meta($post_id, '_license_customer_id', intval($_POST['license_customer_id']));
    }
    
    // アクティベーション解除処理
    if (isset($_POST['remove_activation'])) {
        $activations = get_post_meta($post_id, '_license_activations', true);
        $activations = $activations ? json_decode($activations, true) : array();
        
        foreach ($_POST['remove_activation'] as $index => $remove) {
            if ($remove === '1' && isset($activations[$index])) {
                unset($activations[$index]);
            }
        }
        
        $activations = array_values($activations); // インデックスを振り直し
        update_post_meta($post_id, '_license_activations', json_encode($activations));
    }
}

// REST API: ライセンス認証エンドポイント
add_action('rest_api_init', 'sokulabo_register_license_api');
function sokulabo_register_license_api() {
    // 新しいエンドポイント
    register_rest_route('sokulabo/v1', '/license/verify', array(
        'methods' => 'POST',
        'callback' => 'sokulabo_api_verify_license',
        'permission_callback' => '__return_true',
    ));
}

// ライセンス認証API
function sokulabo_api_verify_license($request) {
    $params = $request->get_params();
    $action = $params['action'] ?? '';
    $license_key = trim($params['license_key'] ?? '');
    $site_url = trim($params['site_url'] ?? '');
    
    // パラメータ検証
    if (empty($license_key)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'ライセンスキーが指定されていません'
        ), 400);
    }
    
    // レート制限チェック
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $transient_key = 'sokulabo_rate_limit_' . md5($ip);
    $requests = get_transient($transient_key) ?: 0;
    
    if ($requests > 60) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'リクエスト制限を超えています。1時間後に再試行してください。'
        ), 429);
    }
    
    set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
    
    // ライセンス検索（タイトルとメタデータの両方で検索）
    $licenses = get_posts(array(
        'post_type' => 'sokulabo_license',
        'title' => $license_key,
        'post_status' => 'any',
        'posts_per_page' => 1,
    ));
    
    // タイトル検索で見つからない場合、全ライセンスから完全一致を探す
    if (empty($licenses)) {
        $all_licenses = get_posts(array(
            'post_type' => 'sokulabo_license',
            'post_status' => 'any',
            'posts_per_page' => -1,
        ));
        
        foreach ($all_licenses as $lic) {
            if (trim($lic->post_title) === $license_key) {
                $licenses = array($lic);
                break;
            }
        }
    }
    
    if (empty($licenses)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => '無効なライセンスキーです'
        ), 200);
    }
    
    $license = $licenses[0];
    $license_id = $license->ID;
    
    // アクション別処理
    switch ($action) {
        case 'activate':
            return sokulabo_activate_license($license_id, $license_key, $site_url);
            
        case 'deactivate':
            return sokulabo_deactivate_license($license_id, $site_url);
            
        case 'check':
            return sokulabo_check_license($license_id, $site_url);
            
        default:
            return new WP_REST_Response(array(
                'success' => false,
                'message' => '無効なアクションです'
            ), 400);
    }
}

// ライセンス有効化
function sokulabo_activate_license($license_id, $license_key, $site_url) {
    $license = get_post($license_id);
    
    // サイトURLを正規化（末尾のスラッシュを削除、小文字に統一）
    $site_url = rtrim(strtolower($site_url), '/');
    
    // 他のライセンスで既に使用されていないかチェック（1サイト1ライセンスの制限）
    $customer_id = get_post_meta($license_id, '_license_customer_id', true);
    $all_licenses = get_posts(array(
        'post_type' => 'sokulabo_license',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_license_customer_id',
                'value' => $customer_id,
            )
        )
    ));
    
    foreach ($all_licenses as $other_license) {
        // 自分自身のライセンスはスキップ
        if ($other_license->ID === $license_id) {
            continue;
        }
        
        $other_activations = get_post_meta($other_license->ID, '_license_activations', true);
        $other_activations = $other_activations ? json_decode($other_activations, true) : array();
        
        foreach ($other_activations as $activation) {
            $registered_url = rtrim(strtolower($activation['site_url']), '/');
            if ($registered_url === $site_url) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'このサイトは既に別のライセンスで使用されています（ライセンスキー: ' . $other_license->post_title . '）',
                    'existing_license' => $other_license->post_title
                ), 200);
            }
        }
    }
    
    // ステータス確認
    if ($license->post_status !== 'publish') {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'このライセンスは無効化されています'
        ), 200);
    }
    
    // 有効期限確認
    $expires = get_post_meta($license_id, '_license_expires', true);
    
    if ($expires && strtotime($expires) < time()) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'このライセンスは期限切れです'
        ), 200);
    }
    
    // アクティベーション情報取得
    $activations = get_post_meta($license_id, '_license_activations', true);
    $activations = $activations ? json_decode($activations, true) : array();
    $max_sites = intval(get_post_meta($license_id, '_license_max_sites', true) ?: 1);
    
    // サイトURLを正規化（末尾のスラッシュを削除、小文字に統一）
    $site_url = rtrim(strtolower($site_url), '/');
    
    // 既存確認（正規化して比較）
    $site_index = -1;
    foreach ($activations as $index => $activation) {
        $registered_url = rtrim(strtolower($activation['site_url']), '/');
        if ($registered_url === $site_url) {
            $site_index = $index;
            break;
        }
    }
    
    // サイト数制限確認
    if ($site_index === -1 && count($activations) >= $max_sites) {
        // 登録済みサイトのリストを作成
        $registered_sites = array_map(function($act) {
            return $act['site_url'];
        }, $activations);
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'ライセンスの使用可能サイト数を超えています（最大' . $max_sites . 'サイト）',
            'registered_sites' => $registered_sites,
            'attempted_site' => $site_url
        ), 200);
    }
    
    // アクティベーション登録
    if ($site_index === -1) {
        $activations[] = array(
            'site_url' => $site_url,
            'activated_at' => current_time('mysql'),
            'last_check' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        );
    } else {
        $activations[$site_index]['last_check'] = current_time('mysql');
    }
    
    update_post_meta($license_id, '_license_activations', json_encode($activations));
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'ライセンスが有効化されました',
        'expires' => $expires ?: '',
        'sites_used' => count($activations),
        'sites_max' => $max_sites,
    ), 200);
}

// ライセンス無効化
function sokulabo_deactivate_license($license_id, $site_url) {
    $activations = get_post_meta($license_id, '_license_activations', true);
    $activations = $activations ? json_decode($activations, true) : array();
    
    // サイトURLを正規化（末尾のスラッシュを削除、小文字に統一）
    $site_url = rtrim(strtolower($site_url), '/');
    
    // サイトを削除（正規化して比較）
    $activations = array_filter($activations, function($activation) use ($site_url) {
        $registered_url = rtrim(strtolower($activation['site_url']), '/');
        return $registered_url !== $site_url;
    });
    
    $activations = array_values($activations); // インデックス振り直し
    update_post_meta($license_id, '_license_activations', json_encode($activations));
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'ライセンスを無効化しました'
    ), 200);
}

// ライセンス確認
function sokulabo_check_license($license_id, $site_url) {
    $license = get_post($license_id);
    
    // ステータス確認
    if ($license->post_status !== 'publish') {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'このライセンスは無効化されています'
        ), 200);
    }
    
    // 有効期限確認
    $expires = get_post_meta($license_id, '_license_expires', true);
    if ($expires && strtotime($expires) < time()) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'このライセンスは期限切れです'
        ), 200);
    }
    
    // アクティベーション確認
    $activations = get_post_meta($license_id, '_license_activations', true);
    $activations = $activations ? json_decode($activations, true) : array();
    
    $is_activated = false;
    foreach ($activations as $index => $activation) {
        if ($activation['site_url'] === $site_url) {
            $is_activated = true;
            // 最終確認日時を更新
            $activations[$index]['last_check'] = current_time('mysql');
            break;
        }
    }
    
    if (!$is_activated) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'このサイトでライセンスが有効化されていません'
        ), 200);
    }
    
    update_post_meta($license_id, '_license_activations', json_encode($activations));
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'ライセンスは有効です',
        'expires' => $expires ?: ''
    ), 200);
}

// ライセンスキー生成ヘルパー（管理画面で使用）
function sokulabo_generate_license_key() {
    $prefix = 'SOKULAB';
    $parts = array();
    
    for ($i = 0; $i < 3; $i++) {
        $parts[] = strtoupper(substr(wp_generate_password(4, false), 0, 4));
    }
    
    $key_base = implode('-', $parts);
    $checksum = substr(md5($key_base . wp_salt()), 0, 4);
    
    return $prefix . '-' . $key_base . '-' . strtoupper($checksum);
}

/**
 * ========================
 * 会員登録・マイページ機能
 * ========================
 */

// 会員登録ページのショートコード
add_shortcode('sokulabo_register_form', 'sokulabo_register_form_shortcode');
function sokulabo_register_form_shortcode() {
    // 管理画面内（プレビューや編集時）、AJAX処理、REST API処理では処理しない
    if (is_admin() || 
        (defined('DOING_AJAX') && DOING_AJAX) || 
        (defined('REST_REQUEST') && REST_REQUEST) ||
        wp_doing_ajax() ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false)) {
        return '<p>会員登録フォーム（プレビューモード）</p>';
    }
    
    // ログイン済みの場合はマイページへリダイレクト
    if (is_user_logged_in()) {
        wp_redirect(home_url('/mypage/'));
        exit;
    }
    
    ob_start();
    ?>
    <style>
        .sokulabo-register-logo {
            text-align: center;
            margin: 0 auto 30px;
            max-width: 400px;
        }
        .sokulabo-register-logo h1 {
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 600;
            color: #333;
        }
        
        .sokulabo-register-form {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus {
            border-color: #0b6bbf;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 107, 191, 0.2);
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            padding: 5px;
            line-height: 1;
        }
        .password-toggle:hover {
            color: #0b6bbf;
        }
        .required {
            color: red;
        }
        .button-primary {
            width: 100%;
            padding: 6px 14px;
            background: #0b6bbf;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .button-primary:hover {
            background: #094a8a;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #0b6bbf;
            text-decoration: none;
        }
        .login-link a:hover {
            color: #094a8a;
            text-decoration: underline;
        }
        .sokulabo-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .sokulabo-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .sokulabo-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

    <div class="sokulabo-register-logo">
        <h1>速ラボ PRODUCTS</h1>
    </div>

    <div class="sokulabo-register-form">
        <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
            <div class="sokulabo-message sokulabo-success">
                <p>✓ 会員登録が完了しました！ログインページへ移動します...</p>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '<?php echo home_url('/login/'); ?>';
                }, 3000);
            </script>
        <?php elseif (isset($_GET['registered']) && $_GET['registered'] === 'pending'): ?>
            <div class="sokulabo-message sokulabo-success">
                <p>✓ 仮登録が完了しました！</p>
                <p>ご登録いただいたメールアドレスに認証URLを送信しました。<br>
                メール内のURLをクリックして、会員登録を完了してください。</p>
                <p style="margin-top: 15px; font-size: 13px; color: #666;">
                    ※メールが届かない場合は、迷惑メールフォルダをご確認ください。<br>
                    ※認証URLの有効期限は24時間です。
                </p>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="sokulabo-message sokulabo-error">
                <p>✗ <?php echo esc_html(urldecode($_GET['error'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_GET['registered']) || ($_GET['registered'] !== 'success' && $_GET['registered'] !== 'pending')): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sokulabo_register_user', 'sokulabo_register_nonce'); ?>
            <input type="hidden" name="action" value="sokulabo_register_user">
            
            <div class="form-group">
                <label for="user_login">ユーザー名 <span class="required">*</span></label>
                <input type="text" id="user_login" name="user_login" autocomplete="username" required 
                       pattern="[a-zA-Z0-9_-]+" 
                       title="半角英数字、ハイフン、アンダースコアのみ使用できます">
                <small>半角英数字、ハイフン、アンダースコアのみ</small>
            </div>
            
            <div class="form-group">
                <label for="user_email">メールアドレス <span class="required">*</span></label>
                <input type="email" id="user_email" name="user_email" autocomplete="email" required>
            </div>
            
            <div class="form-group">
                <label for="user_password">パスワード <span class="required">*</span></label>
                <div class="password-wrapper">
                    <input type="password" id="user_password" name="user_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('user_password', this)">👁️</button>
                </div>
                <small>8文字以上</small>
            </div>
            
            <div class="form-group">
                <label for="user_password_confirm">パスワード（確認） <span class="required">*</span></label>
                <div class="password-wrapper">
                    <input type="password" id="user_password_confirm" name="user_password_confirm" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePassword('user_password_confirm', this)">👁️</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="agree_terms" required>
                    <a href="<?php echo home_url('/terms/'); ?>" target="_blank">利用規約</a>に同意する
                </label>
            </div>
            
            <button type="submit" class="button button-primary button-large">登録する</button>
        </form>
        <?php endif; ?>
        
        <p class="login-link">
            すでにアカウントをお持ちの方は<a href="<?php echo home_url('/login/'); ?>">ログイン</a>
        </p>
    </div>

    <script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = '🙈';
        } else {
            input.type = 'password';
            button.textContent = '👁️';
        }
    }
    </script>

    <?php
    return ob_get_clean();
}

// 会員登録処理（ログインしていないユーザーのみ）- 仮登録
add_action('admin_post_nopriv_sokulabo_register_user', 'sokulabo_handle_register_user');
function sokulabo_handle_register_user() {
    // Nonce確認
    if (!isset($_POST['sokulabo_register_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_register_nonce'], 'sokulabo_register_user')) {
        wp_die('不正なリクエストです');
    }
    
    $user_login = sanitize_user($_POST['user_login']);
    $user_email = sanitize_email($_POST['user_email']);
    $user_password = $_POST['user_password'];
    $user_password_confirm = $_POST['user_password_confirm'];
    
    // バリデーション
    if (empty($user_login) || empty($user_email) || empty($user_password)) {
        wp_redirect(add_query_arg('error', urlencode('全ての項目を入力してください'), wp_get_referer()));
        exit;
    }
    
    if ($user_password !== $user_password_confirm) {
        wp_redirect(add_query_arg('error', urlencode('パスワードが一致しません'), wp_get_referer()));
        exit;
    }
    
    if (strlen($user_password) < 8) {
        wp_redirect(add_query_arg('error', urlencode('パスワードは8文字以上で入力してください'), wp_get_referer()));
        exit;
    }
    
    if (username_exists($user_login)) {
        wp_redirect(add_query_arg('error', urlencode('このユーザー名は既に使用されています'), wp_get_referer()));
        exit;
    }
    
    if (email_exists($user_email)) {
        wp_redirect(add_query_arg('error', urlencode('このメールアドレスは既に登録されています'), wp_get_referer()));
        exit;
    }
    
    // 仮登録データを一時保存（24時間有効）
    $activation_key = wp_generate_password(32, false);
    $temp_data = array(
        'user_login' => $user_login,
        'user_email' => $user_email,
        'user_password' => $user_password,
        'timestamp' => current_time('timestamp')
    );
    
    set_transient('sokulabo_temp_user_' . $activation_key, $temp_data, 24 * HOUR_IN_SECONDS);
    
    // 認証メール送信
    $activation_url = add_query_arg(array(
        'action' => 'activate',
        'key' => $activation_key
    ), home_url('/register/'));
    
    $to = $user_email;
    $subject = '【速ラボ PRODUCTS】会員登録の確認';
    $message = "会員登録のお申し込みありがとうございます。\n\n";
    $message .= "以下のURLをクリックして、会員登録を完了してください。\n";
    $message .= "このURLは24時間有効です。\n\n";
    $message .= $activation_url . "\n\n";
    $message .= "────────────────────────\n";
    $message .= "ユーザー名: " . $user_login . "\n";
    $message .= "メールアドレス: " . $user_email . "\n";
    $message .= "────────────────────────\n\n";
    $message .= "※このメールに心当たりがない場合は、破棄してください。\n\n";
    $message .= "速ラボ PRODUCTS\n";
    $message .= home_url();
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    if (wp_mail($to, $subject, $message, $headers)) {
        // メール送信成功
        $register_url = home_url('/register/');
        wp_redirect(add_query_arg('registered', 'pending', $register_url));
        exit;
    } else {
        // メール送信失敗
        wp_redirect(add_query_arg('error', urlencode('メールの送信に失敗しました。しばらく時間をおいて再度お試しください。'), wp_get_referer()));
        exit;
    }
}

// メール認証による本登録処理
add_action('template_redirect', 'sokulabo_handle_activation');
function sokulabo_handle_activation() {
    if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['key'])) {
        $activation_key = sanitize_text_field($_GET['key']);
        $temp_data = get_transient('sokulabo_temp_user_' . $activation_key);
        
        if (!$temp_data) {
            // 認証キーが無効または期限切れ
            wp_redirect(add_query_arg('error', urlencode('認証URLが無効または期限切れです。再度会員登録を行ってください。'), home_url('/register/')));
            exit;
        }
        
        // ユーザー作成
        $user_id = wp_create_user(
            $temp_data['user_login'],
            $temp_data['user_password'],
            $temp_data['user_email']
        );
        
        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('error', urlencode('会員登録に失敗しました: ' . $user_id->get_error_message()), home_url('/register/')));
            exit;
        }
        
        // ユーザーメタ設定
        update_user_meta($user_id, 'show_admin_bar_front', false);
        update_user_meta($user_id, 'sokulabo_activated_date', current_time('mysql'));
        
        // 一時データを削除
        delete_transient('sokulabo_temp_user_' . $activation_key);
        
        // 本登録完了メール送信
        $to = $temp_data['user_email'];
        $subject = '【速ラボ PRODUCTS】会員登録完了';
        $message = "会員登録が完了しました。\n\n";
        $message .= "以下の情報でログインできます。\n\n";
        $message .= "────────────────────────\n";
        $message .= "ユーザー名: " . $temp_data['user_login'] . "\n";
        $message .= "メールアドレス: " . $temp_data['user_email'] . "\n";
        $message .= "────────────────────────\n\n";
        $message .= "ログインページ: " . home_url('/login/') . "\n\n";
        $message .= "速ラボ PRODUCTS\n";
        $message .= home_url();
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
        
        // 本登録完了ページへリダイレクト
        wp_redirect(add_query_arg('registered', 'success', home_url('/register/')));
        exit;
    }
}

// マイページのショートコード
add_shortcode('sokulabo_mypage', 'sokulabo_mypage_shortcode');
function sokulabo_mypage_shortcode() {
    // 管理画面内（プレビューや編集時）、AJAX処理、REST API処理では処理しない
    if (is_admin() || 
        (defined('DOING_AJAX') && DOING_AJAX) || 
        (defined('REST_REQUEST') && REST_REQUEST) ||
        wp_doing_ajax() ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false)) {
        return '<p>マイページ（プレビューモード）</p>';
    }
    
    // ログインチェック
    if (!is_user_logged_in()) {
        return '<p>この内容を表示するには<a href="' . home_url('/login/') . '">ログイン</a>してください。</p>';
    }
    
    $current_user = wp_get_current_user();
    
    ob_start();
    ?>

    <style>
        .sokulabo-mypage {
            max-width: 900px;
            margin: 0 auto 40px;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logout-link {
            position: absolute;
            top: 0;
            right: 0;
            color: #666;
            text-decoration: none;
        }
        .logout-link:hover {
            color: #000;
        }
        
        /* メッセージスタイル */
        .sokulabo-message {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 18px 20px;
            margin-bottom: 25px;
            border-left: 4px solid;
            position: relative;
            animation: slideInDown 0.4s ease-out;
            box-shadow: 0 0 10px #eee;
        }
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .sokulabo-message .message-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .sokulabo-message .message-icon .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        .sokulabo-message .message-content {
            flex: 1;
        }
        .sokulabo-message .message-content strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .sokulabo-message .message-content p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .sokulabo-message .message-close {
            flex-shrink: 0;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            opacity: 0.6;
            transition: opacity 0.2s;
            border-radius: 4px;
        }
        .sokulabo-message .message-close:hover {
            opacity: 1;
            background: rgba(0,0,0,0.05);
        }
        .sokulabo-message .message-close .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .sokulabo-success {
            border-left-color: #22c55e;
        }
        .sokulabo-success .message-icon {
            background: #dcfce7;
            color: #16a34a;
        }
        .sokulabo-success .message-content strong {
            color: #16a34a;
        }
        .sokulabo-success .message-content p {
            color: #15803d;
        }
        .sokulabo-success .message-close {
            color: #16a34a;
        }
        .sokulabo-error {
            background: #fef2f2;
            border-left-color: #ef4444;
        }
        .sokulabo-error .message-icon {
            background: #fee2e2;
            color: #dc2626;
        }
        .sokulabo-error .message-content strong {
            color: #dc2626;
        }
        .sokulabo-error .message-content p {
            color: #b91c1c;
        }
        .sokulabo-error .message-close {
            color: #dc2626;
        }
        
        .mypage-section {
            margin-bottom: 40px;
        }
        .mypage-section h3 {
            margin-bottom: 20px;
        }
        .account-info {
            width: 100%;
            border-collapse: collapse;
        }
        .account-info th,
        .account-info td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .account-info th {
            width: 200px;
            font-weight: 600;
            background: #f9f9f9;
        }
        .email-edit-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .email-display {
            flex: 1;
        }
        .edit-email-btn {
            background: #0b6bbf;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }
        .edit-email-btn:hover {
            background: #094a8a;
        }
        .cancel-edit-btn {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }
        .cancel-edit-btn:hover {
            background: #5a6268;
        }
        .email-edit-form {
            display: none;
            align-items: center;
            gap: 8px;
        }
        .email-edit-form.active {
            display: flex;
        }
        .email-edit-form input[type="email"] {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .email-edit-form input[type="email"]:focus {
            outline: none;
            border-color: #0b6bbf;
        }
        .save-email-btn {
            background: #22c55e;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .save-email-btn:hover {
            background: #16a34a;
        }
        .email-error-message {
            display: none;
            color: #dc2626;
            font-size: 12px;
            margin-top: 8px;
            line-height: 1.4;
        }
        .email-error-message.show {
            display: block;
        }
        .license-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }
        .license-item:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        .license-key {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.5px;
            padding: 0 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: inline-block;
            height: 44px;
            line-height: 42px;
        }
        .license-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .license-info-item {
            padding: 12px 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .license-info-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .license-info-item strong {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .license-info-item span {
            display: block;
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }
        .license-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .license-status::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        .license-status.active {
            color: #15803d;
        }
        .license-status.active::before {
            background: #22c55e;
        }
        .license-status.inactive {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .license-status.inactive::before {
            background: #ef4444;
        }
        .license-status.expired {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .license-status.expired::before {
            background: #f59e0b;
        }
        .site-list {
            margin-top: 18px;
        }
        .site-list h4 {
            margin: 0 0 14px 0;
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .site-item {
            background: white;
            padding: 14px 16px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .site-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .site-info {
            flex: 1;
        }
        .site-url {
            color: #0b6bbf;
            text-decoration: none;
            word-break: break-all;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .site-url:hover {
            color: #094a8a;
        }
        .site-url .dashicons {
            color: #64748b;
        }
        .site-date {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #94a3b8;
        }
        .add-site-form {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .add-site-form input[type="url"] {
            width: calc(100% - 120px);
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .button-small {
            padding: 6px 12px;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .button-primary-small {
            background: #0b6bbf;
            color: #fff;
        }
        .button-primary-small:hover {
            background: #094a8a;
        }
        .button-danger-small {
            background: #dc3232;
            color: #fff;
        }
        .button-danger-small:hover {
            background: #a02020;
        }
        .no-license {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .no-license p {
            margin-bottom: 20px;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .sokulabo-mypage {
                padding: 20px;
                margin: 0 auto 30px;
            }
            
            .account-info th {
                width: 120px;
                font-size: 13px;
            }
            
            .account-info th,
            .account-info td {
                padding: 10px 8px;
            }
            
            .license-item {
                padding: 20px;
            }
            
            .license-info {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .sokulabo-mypage {
                padding: 15px;
                border-radius: 0;
            }
            
            .mypage-section h3 {
                font-size: 18px;
            }
            
            /* テーブルをカード型に変更 */
            .account-info {
                border: none;
            }
            
            .account-info tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 6px;
                overflow: hidden;
            }
            
            .account-info th,
            .account-info td {
                display: block;
                width: 100%;
                padding: 12px 15px;
                border: none;
            }
            
            .account-info th {
                background: #f5f5f5;
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .account-info td {
                background: #fff;
                font-size: 14px;
            }
            
            /* メールアドレス編集フォームをモバイル対応 */
            .email-edit-wrapper {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .email-display {
                word-break: break-all;
            }
            
            .edit-email-btn {
                width: 100%;
                padding: 8px 12px;
            }
            
            .email-edit-form {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            
            .email-edit-form input[type="email"] {
                width: 100%;
            }
            
            .email-edit-form .save-email-btn,
            .email-edit-form .cancel-edit-btn {
                width: 100%;
                padding: 10px;
            }
            
            /* メッセージボックス */
            .sokulabo-message {
                padding: 15px;
                gap: 10px;
            }
            
            .sokulabo-message .message-icon {
                width: 32px;
                height: 32px;
            }
            
            .sokulabo-message .message-icon .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            
            .sokulabo-message .message-content strong {
                font-size: 14px;
            }
            
            .sokulabo-message .message-content p {
                font-size: 13px;
            }
            
            /* ライセンス情報 */
            .license-item {
                padding: 15px;
            }
            
            .license-key {
                font-size: 14px;
                padding: 0 12px;
                height: 38px;
                line-height: 36px;
                word-break: break-all;
            }
            
            .license-info {
                gap: 10px;
            }
            
            .license-info-item {
                padding: 10px 12px;
            }
            
            .site-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .site-url {
                font-size: 13px;
                word-break: break-all;
            }
        }
    </style>

    <div class="mypage-header">
        <p>ようこそ、<?php echo esc_html($current_user->display_name); ?>さん</p>
    </div>

    <div class="sokulabo-mypage">
        
        <!-- 動的メッセージ表示エリア -->
        <div id="mypageMessage"></div>
        
        <div class="mypage-content">
            <div class="mypage-section">
                <h3>ライセンス情報</h3>
                <?php echo sokulabo_display_user_licenses($current_user->ID); ?>
            </div>
            
            <div class="mypage-section">
                <h3>アカウント情報</h3>
                <table class="account-info">
                    <tr>
                        <th>ユーザー名</th>
                        <td><?php echo esc_html($current_user->user_login); ?></td>
                    </tr>
                    <tr>
                        <th>メールアドレス</th>
                        <td>
                            <div class="email-edit-wrapper">
                                <span class="email-display" id="emailDisplay"><?php echo esc_html($current_user->user_email); ?></span>
                                <button type="button" class="edit-email-btn" onclick="toggleEmailEdit()">編集</button>
                            </div>
                            <form class="email-edit-form" id="emailEditForm" onsubmit="updateEmail(event)">
                                <input type="email" name="new_email" id="newEmail" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                                <button type="submit" class="save-email-btn" id="saveEmailBtn">保存</button>
                                <button type="button" class="cancel-edit-btn" onclick="toggleEmailEdit()">キャンセル</button>
                            </form>
                            <div class="email-error-message" id="emailErrorMessage"></div>
                        </td>
                    </tr>
                    <tr>
                        <th>登録日</th>
                        <td><?php echo date('Y年m月d日', strtotime($current_user->user_registered)); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- アカウント削除セクション -->
        <div class="mypage-section" style="margin-top: 30px; text-align: center; padding: 20px 0; border-top: 1px solid #eee;">
            <p style="color: #999; margin-bottom: 8px; font-size: 13px;">
                アカウントを削除すると、すべてのライセンスキーと登録情報が完全に削除されます。
            </p>
            <a href="#" onclick="confirmDeleteAccount(); return false;" style="color: #dc2626; text-decoration: none; font-size: 13px;">
                アカウントを削除する
            </a>
        </div>
    </div>
    
    <script>
    // AJAX用のデータをグローバル変数として定義
    const sokulabo_ajax_data = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("sokulabo_update_email_ajax"); ?>'
    };
    
    function showMessage(message, type) {
        const messageDiv = document.getElementById('mypageMessage');
        const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
        const typeClass = type === 'success' ? 'sokulabo-success' : 'sokulabo-error';
        const title = type === 'success' ? '成功' : 'エラー';
        
        messageDiv.innerHTML = `
            <div class="sokulabo-message ${typeClass}">
                <div class="message-icon">
                    <span class="dashicons ${iconClass}"></span>
                </div>
                <div class="message-content">
                    <strong>${title}</strong>
                    <p>${message}</p>
                </div>
                <button class="message-close" onclick="this.parentElement.remove()">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        `;
        
        // 自動で3秒後に消す
        setTimeout(() => {
            const msg = messageDiv.querySelector('.sokulabo-message');
            if (msg) msg.remove();
        }, 5000);
    }
    
    function toggleEmailEdit() {
        const display = document.getElementById('emailDisplay');
        const form = document.getElementById('emailEditForm');
        const btn = document.querySelector('.edit-email-btn');
        const errorMsg = document.getElementById('emailErrorMessage');
        
        if (form.classList.contains('active')) {
            form.classList.remove('active');
            display.style.display = 'inline';
            btn.style.display = 'inline-block';
            // エラーメッセージを非表示
            errorMsg.classList.remove('show');
            errorMsg.textContent = '';
        } else {
            display.style.display = 'none';
            btn.style.display = 'none';
            form.classList.add('active');
            document.getElementById('newEmail').focus();
        }
    }
    
    function updateEmail(event) {
        event.preventDefault();
        
        const submitBtn = document.getElementById('saveEmailBtn');
        const newEmail = document.getElementById('newEmail').value.trim();
        const currentEmail = document.getElementById('emailDisplay').textContent;
        const errorDiv = document.getElementById('emailErrorMessage');
        
        // 空欄の場合は編集モードを閉じる
        if (!newEmail) {
            toggleEmailEdit();
            return;
        }
        
        // 同じメールアドレスの場合は警告を表示
        if (newEmail.toLowerCase() === currentEmail.toLowerCase()) {
            errorDiv.textContent = '現在と同じメールアドレスです';
            errorDiv.classList.add('show');
            submitBtn.disabled = false;
            return;
        }
        
        // エラーメッセージをクリア
        errorDiv.classList.remove('show');
        errorDiv.textContent = '';
        
        // ボタンを無効化
        submitBtn.disabled = true;
        submitBtn.textContent = '更新中...';
        
        // AJAX送信
        const formData = new FormData();
        formData.append('action', 'sokulabo_update_email_ajax');
        formData.append('new_email', newEmail);
        formData.append('nonce', sokulabo_ajax_data.nonce);
        
        fetch(sokulabo_ajax_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 成功時：メールアドレスを更新して編集モードを閉じる
                document.getElementById('emailDisplay').textContent = newEmail;
                toggleEmailEdit();
            } else {
                // エラー時：メッセージに「既に使用されています」が含まれる場合のみ警告表示
                const errorMessage = data.data.message || '';
                if (errorMessage.includes('既に使用されています') || errorMessage.includes('already') || errorMessage.includes('exists')) {
                    const errorDiv = document.getElementById('emailErrorMessage');
                    errorDiv.textContent = errorMessage;
                    errorDiv.classList.add('show');
                } else {
                    // その他のエラーは静かに閉じる
                    toggleEmailEdit();
                }
            }
        })
        .catch(error => {
            // 通信エラーでも編集モードを閉じる
            console.error('Error:', error);
            toggleEmailEdit();
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = '保存';
        });
    }
    
    function confirmDeleteAccount() {
        if (confirm('本当にアカウントを削除しますか？\n\nこの操作は取り消せません。すべてのライセンスキーと登録情報が完全に削除されます。')) {
            if (confirm('最終確認：アカウントを完全に削除してよろしいですか？')) {
                document.getElementById('deleteAccountForm').submit();
            }
        }
    }
    </script>
    
    <!-- アカウント削除フォーム（非表示） -->
    <form id="deleteAccountForm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: none;">
        <?php wp_nonce_field('sokulabo_delete_account', 'sokulabo_delete_account_nonce'); ?>
        <input type="hidden" name="action" value="sokulabo_delete_account">
    </form>
    
    <?php
    return ob_get_clean();
}

// ユーザーのライセンス表示
function sokulabo_display_user_licenses($user_id) {
    $licenses = get_posts(array(
        'post_type' => 'sokulabo_license',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_license_customer_id',
                'value' => $user_id,
            )
        )
    ));
    
    ob_start();
    ?>

    <style>
        .license-generate-section {
            background: linear-gradient(135deg, #0b6bbf 0%, #094a8a 100%);
            color: #fff;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-header h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            border-left: none;
            padding-left: 0;
        }
        .section-description {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
        }
        .button-generate {
            background: #fff;
            color: #0b6bbf;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .button-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .button-generate .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        .sokulabo-modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
            align-items: center;
            justify-content: center;
            padding-top: 80px;
        }
        .sokulabo-modal.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .sokulabo-modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: slideDown 0.3s;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .sokulabo-modal-close {
            color: #aaa;
            position: absolute;
            right: 15px;
            top: 12px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: transparent;
            border: none;
        }
        .sokulabo-modal-close:hover {
            color: #000;
            background: rgba(0,0,0,0.1);
        }
        .sokulabo-modal h3 {
            margin-top: 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .license-list-empty {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: #f9f9f9;
            border-radius: 6px;
            margin-top: 20px;
        }
        .license-list-empty .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
    </style>

    <!-- ライセンス発行セクション -->
    <div class="license-generate-section">
        <div class="section-header">
            <h4>SokuLabo Products</h4>
            <button type="button" class="button-generate" onclick="sokulabShowGenerateModal()" title="新しいライセンスキーを発行">
                <span class="dashicons dashicons-plus-alt"></span> ライセンスキーを発行
            </button>
        </div>
        <p class="section-description">
            ライセンスキーを発行する際に、使用するプラグインのサイトURLを登録してください。<br>
            各ライセンスは1サイトで使用できます。
        </p>
    </div>
    
    <!-- ライセンス発行モーダル -->
    <div id="sokulaboGenerateModal" class="sokulabo-modal">
        <div class="sokulabo-modal-content">
            <span class="sokulabo-modal-close" onclick="sokulabCloseGenerateModal()">&times;</span>
            <h3>新しいライセンスキーを発行</h3>
            <p style="color: #666; margin-bottom: 20px; font-size: 13px;">
                プラグインを使用するサイトのURLを入力してください。<br>
                ライセンスキーが自動的に発行され、入力したサイトに紐付けられます。
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sokulaboGenerateForm">
                <?php wp_nonce_field('sokulabo_generate_license', 'sokulabo_generate_license_nonce'); ?>
                <input type="hidden" name="action" value="sokulabo_generate_license">
                
                <div class="form-group">
                    <label for="plugin_type">プラグイン <span style="color: red;">*</span></label>
                    <select id="plugin_type" 
                            name="plugin_type" 
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">プラグインを選択してください</option>
                        <option value="automagic-image-generate">Automagic Image Generate</option>
                        <option value="product-link-maker">Product Link Maker</option>
                    </select>
                    <p class="description" style="margin-top: 8px; font-size: 13px;">
                        使用するプラグインを選択してください
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="initial_site_url">サイトURL <span style="color: red;">*</span></label>
                    <input type="url" 
                           id="initial_site_url" 
                           name="initial_site_url" 
                           class="regular-text" 
                           placeholder="https://example.com"
                           required
                           pattern="https?://.+"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <p class="description" style="margin-top: 8px; font-size: 13px;">
                        例: https://yoursite.com （http:// または https:// で始まるURLを入力してください）
                    </p>
                </div>
                <div class="form-actions" style="margin-top: 20px; text-align: right;">
                    <button type="button" class="button" onclick="sokulabCloseGenerateModal()">キャンセル</button>
                    <button type="submit" class="button button-primary">発行する</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function sokulabShowGenerateModal() {
        const modal = document.getElementById('sokulaboGenerateModal');
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.getElementById('initial_site_url').focus();
    }
    
    function sokulabCloseGenerateModal() {
        const modal = document.getElementById('sokulaboGenerateModal');
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.getElementById('sokulaboGenerateForm').reset();
    }
    
    // モーダル外をクリックしたら閉じる
    window.onclick = function(event) {
        var modal = document.getElementById('sokulaboGenerateModal');
        if (event.target == modal) {
            sokulabCloseGenerateModal();
        }
    }
    
    // Escキーで閉じる
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            sokulabCloseGenerateModal();
        }
    });
    </script>

    <style>
        .license-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .license-key-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .button-copy {
            background: #0b6bbf;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            transition: all 0.2s;
            height: 42px;
            width: 42px;
        }
        .button-copy:hover {
            background: #094a8a;
        }
        .button-copy .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .button-copy.copied {
            background: #46b450;
        }
        .usage-info {
            margin-top: 5px;
        }
        .usage-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        .usage-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0b6bbf 0%, #094a8a 100%);
            transition: width 0.3s;
        }
        .site-info {
            flex: 1;
        }
        .site-url {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #0b6bbf;
            text-decoration: none;
        }
        .site-url:hover {
            text-decoration: underline;
        }
        .site-url .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .site-date {
            display: block;
            margin-top: 4px;
            color: #999;
            font-size: 12px;
        }
        .input-with-button {
            display: flex;
            gap: 10px;
        }
        .input-with-button input {
            flex: 1;
        }
        .license-actions {
            margin-top: 20px;
            text-align: right;
        }
        .button-delete-license {
            background: transparent;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 0 16px;
            height: 44px;
            line-height: 44px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .button-delete-license:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
        }
        .button-delete-license .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .button-generate {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    <?php if (empty($licenses)): ?>
        <div class="license-list-empty">
            <span class="dashicons dashicons-admin-network"></span>
            <p>まだライセンスキーが発行されていません。</p>
            <p>上の「ライセンスキーを発行」ボタンをクリックして新しいライセンスキーを作成してください。</p>
        </div>
    <?php else: ?>
        <?php
    foreach ($licenses as $license):
        $license_key = $license->post_title;
        $license_id = $license->ID;
        $status = $license->post_status;
        $expires = get_post_meta($license_id, '_license_expires', true);
        $max_sites = get_post_meta($license_id, '_license_max_sites', true) ?: 1;
        $product = get_post_meta($license_id, '_license_product', true);
        $plugin_type = get_post_meta($license_id, '_license_plugin_type', true);
        $activations = get_post_meta($license_id, '_license_activations', true);
        $activations = $activations ? json_decode($activations, true) : array();
        
        // プラグイン名を取得
        $plugin_names = array(
            'automagic-image-generate' => 'Automagic Image Generate',
            'product-link-maker' => 'Product Link Maker',
        );
        $plugin_name = isset($plugin_names[$plugin_type]) ? $plugin_names[$plugin_type] : '不明';
        
        // ステータス判定
        $status_class = 'active';
        $status_text = '有効';
        if ($status !== 'publish') {
            $status_class = 'inactive';
            $status_text = '無効';
        } elseif ($expires && strtotime($expires) < time()) {
            $status_class = 'expired';
            $status_text = '期限切れ';
        }
        ?>
        <div class="license-item">
            <div class="license-header">
                <div class="license-key-wrapper">
                    <div class="license-key"><?php echo esc_html($license_key); ?></div>
                    <button type="button" class="button-copy" onclick="sokulabCopyLicenseKey('<?php echo esc_js($license_key); ?>', this)" title="コピー">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
                <span class="license-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            
            <div class="license-info">
                <div class="license-info-item">
                    <strong>プラグイン</strong>
                    <span><?php echo esc_html($plugin_name); ?></span>
                </div>
                <div class="license-info-item">
                    <strong>使用状況</strong>
                    <?php 
                    $site_count = count($activations);
                    $progress_percent = $max_sites > 0 ? ($site_count / $max_sites) * 100 : 0;
                    ?>
                    <div class="usage-info">
                        <span><?php echo $site_count; ?> / <?php echo esc_html($max_sites); ?> サイト</span>
                        <div class="usage-bar">
                            <div class="usage-bar-fill" style="width: <?php echo min(100, $progress_percent); ?>%;"></div>
                        </div>
                    </div>
                </div>
                <?php if ($expires): ?>
                <div class="license-info-item">
                    <strong>有効期限</strong>
                    <?php
                    $expires_time = strtotime($expires);
                    $is_expired = $expires_time < time();
                    $days_until_expiry = floor(($expires_time - time()) / (60 * 60 * 24));
                    ?>
                    <span style="<?php echo $is_expired ? 'color: #d63638;' : ($days_until_expiry <= 30 ? 'color: #dba617;' : ''); ?>">
                        <?php echo esc_html($expires); ?>
                        <?php if ($is_expired): ?>
                            <small style="color: #d63638; display: block; font-size: 12px;">期限切れ</small>
                        <?php elseif ($days_until_expiry <= 30 && $days_until_expiry > 0): ?>
                            <small style="color: #dba617; display: block; font-size: 12px;">残り<?php echo $days_until_expiry; ?>日</small>
                        <?php endif; ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="license-info-item">
                    <strong>有効期限</strong>
                    <span style="color: #00a32a;">無期限</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="site-list">
                <h4>登録済みサイト</h4>
                <?php if (empty($activations)): ?>
                    <p style="color: #666; font-size: 14px; padding: 20px; display: flex; justify-content: center; align-items: center; gap: 4px;">
                        <span class="dashicons dashicons-info" style="font-size: 20px;"></span>
                        まだサイトが登録されていません
                    </p>
                <?php else: ?>
                    <?php foreach ($activations as $index => $activation): ?>
                        <div class="site-item">
                            <div class="site-info">
                                <a href="<?php echo esc_url($activation['site_url']); ?>" class="site-url" target="_blank">
                                    <span class="dashicons dashicons-admin-site"></span>
                                    <?php echo esc_html($activation['site_url']); ?>
                                </a>
                                <small class="site-date">登録: <?php echo esc_html($activation['activated_at'] ?? '-'); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="license-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                    <?php wp_nonce_field('sokulabo_delete_license', 'sokulabo_delete_license_nonce'); ?>
                    <input type="hidden" name="action" value="sokulabo_delete_license">
                    <input type="hidden" name="license_id" value="<?php echo $license_id; ?>">
                    <button type="submit" class="button-delete-license" 
                            onclick="return confirm('このライセンスキーを削除しますか？\n登録されているサイトの認証も解除されます。')">
                        <span class="dashicons dashicons-trash"></span> このライセンスを削除
                    </button>
                </form>
            </div>
        </div>
        <?php
    endforeach;
    ?>
    <?php endif; ?>
        
    <script>
    function sokulabCopyLicenseKey(key, button) {
        navigator.clipboard.writeText(key).then(function() {
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="dashicons dashicons-yes"></span>';
            button.classList.add('copied');
            button.title = 'コピーしました！';
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.classList.remove('copied');
                button.title = 'コピー';
            }, 2000);
        }).catch(function(err) {
            alert('コピーに失敗しました: ' + err);
        });
    }
    </script>
    <?php
    
    return ob_get_clean();
}

// ライセンスキー生成処理
add_action('admin_post_sokulabo_generate_license', 'sokulabo_handle_generate_license');
function sokulabo_handle_generate_license() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_die('ログインしてください');
    }
    
    // Nonce確認
    if (!isset($_POST['sokulabo_generate_license_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_generate_license_nonce'], 'sokulabo_generate_license')) {
        wp_die('不正なリクエストです');
    }
    
    $user_id = get_current_user_id();
    $plugin_type = isset($_POST['plugin_type']) ? sanitize_text_field($_POST['plugin_type']) : '';
    $initial_site_url = isset($_POST['initial_site_url']) ? esc_url_raw(trim($_POST['initial_site_url'])) : '';
    
    // プラグインタイプの検証
    $allowed_plugins = array('automagic-image-generate', 'product-link-maker');
    if (empty($plugin_type) || !in_array($plugin_type, $allowed_plugins)) {
        wp_redirect(add_query_arg('error', urlencode('プラグインを選択してください'), home_url('/mypage/')));
        exit;
    }
    
    // サイトURLの検証
    if (empty($initial_site_url) || !filter_var($initial_site_url, FILTER_VALIDATE_URL)) {
        wp_redirect(add_query_arg('error', urlencode('有効なサイトURLを入力してください'), home_url('/mypage/')));
        exit;
    }
    
    // 同一サイトURLで既に有効なライセンスがないか確認
    $existing_licenses = get_posts(array(
        'post_type' => 'sokulabo_license',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_license_customer_id',
                'value' => $user_id,
            )
        )
    ));
    
    foreach ($existing_licenses as $existing_license) {
        $existing_activations = get_post_meta($existing_license->ID, '_license_activations', true);
        $existing_activations = $existing_activations ? json_decode($existing_activations, true) : array();
        
        foreach ($existing_activations as $activation) {
            $registered_url = rtrim(strtolower($activation['site_url']), '/');
            if ($registered_url === $initial_site_url) {
                wp_redirect(add_query_arg('error', urlencode('このサイトURLには既にライセンスが発行されています'), home_url('/mypage/')));
                exit;
            }
        }
    }
    
    // ユーザー固有のライセンスキーを生成
    $license_key = sokulabo_generate_user_license_key($user_id);
    
    // ライセンス投稿を作成
    $license_id = wp_insert_post(array(
        'post_type' => 'sokulabo_license',
        'post_title' => $license_key,
        'post_status' => 'publish',
        'post_author' => $user_id,
    ));
    
    if (is_wp_error($license_id)) {
        wp_redirect(add_query_arg('error', urlencode('ライセンスの作成に失敗しました'), home_url('/mypage/')));
        exit;
    }
    
    // メタデータ設定（初期アクティベーション情報は設定しない）
    update_post_meta($license_id, '_license_customer_id', $user_id);
    update_post_meta($license_id, '_license_max_sites', 1); // 1サイトのみ
    update_post_meta($license_id, '_license_product', 'user_generated');
    update_post_meta($license_id, '_license_plugin_type', $plugin_type); // プラグインタイプを保存
    update_post_meta($license_id, '_license_initial_site_url', $initial_site_url); // 登録予定サイトURLを保存（参考用）
    update_post_meta($license_id, '_license_activations', json_encode(array())); // 空の配列で初期化
    // 有効期限は設定しない（無期限）
    
    wp_redirect(add_query_arg('success', urlencode('ライセンスキーを発行しました'), home_url('/mypage/')));
    exit;
}

// ユーザー固有のライセンスキー生成（ユーザーIDをベースに一意性を確保）
function sokulabo_generate_user_license_key($user_id) {
    $prefix = 'SKL';  // SokuLaboの略称
    $user_part = strtoupper(substr(md5($user_id), 0, 4));
    $timestamp = strtoupper(base_convert(time(), 10, 36));
    $random = strtoupper(substr(wp_generate_password(4, false), 0, 4));
    
    // ユニーク性を確保するために既存チェック
    do {
        $unique_part = strtoupper(substr(md5(uniqid() . $user_id . mt_rand()), 0, 4));
        $key_base = $prefix . '-' . $user_part . '-' . $timestamp . '-' . $unique_part;
        
        // チェックサム生成
        $checksum = substr(md5($key_base . wp_salt()), 0, 4);
        $license_key = $key_base . '-' . strtoupper($checksum);
        
        // 既存チェック
        $existing = get_posts(array(
            'post_type' => 'sokulabo_license',
            'title' => $license_key,
            'posts_per_page' => 1,
        ));
        
    } while (!empty($existing));
    
    return $license_key;
}

// ライセンス削除処理
add_action('admin_post_sokulabo_delete_license', 'sokulabo_handle_delete_license');
function sokulabo_handle_delete_license() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_die('ログインしてください');
    }
    
    // Nonce確認
    if (!isset($_POST['sokulabo_delete_license_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_delete_license_nonce'], 'sokulabo_delete_license')) {
        wp_die('不正なリクエストです');
    }
    
    $license_id = intval($_POST['license_id']);
    $current_user_id = get_current_user_id();
    
    // ライセンスの所有者確認
    $license_owner = get_post_meta($license_id, '_license_customer_id', true);
    if ($license_owner != $current_user_id) {
        wp_redirect(add_query_arg('error', urlencode('このライセンスを削除する権限がありません'), home_url('/mypage/')));
        exit;
    }
    
    // ライセンスキーを取得（通知用）
    $license = get_post($license_id);
    $license_key = $license->post_title;
    
    // ライセンスを削除（ゴミ箱へ）
    $result = wp_trash_post($license_id);
    
    if ($result) {
        // 削除成功後、各サイトのキャッシュをクリアするためのフック
        do_action('sokulabo_license_deleted', $license_key, $license_id);
        
        wp_redirect(add_query_arg('success', urlencode('ライセンスキーを削除しました'), home_url('/mypage/')));
    } else {
        wp_redirect(add_query_arg('error', urlencode('ライセンスの削除に失敗しました'), home_url('/mypage/')));
    }
    exit;
}

// メールアドレス更新処理（AJAX用）
add_action('wp_ajax_sokulabo_update_email_ajax', 'sokulabo_handle_update_email_ajax');
function sokulabo_handle_update_email_ajax() {
    // Nonce確認
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sokulabo_update_email_ajax')) {
        wp_send_json_error(array('message' => '不正なリクエストです'));
    }
    
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'ログインしてください'));
    }
    
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    
    // 入力値の取得とトリミング
    $new_email = isset($_POST['new_email']) ? trim(sanitize_email($_POST['new_email'])) : '';
    
    // 空欄チェック
    if (empty($new_email)) {
        wp_send_json_error(array('message' => 'メールアドレスを入力してください'));
    }
    
    // メールアドレスの形式チェック
    if (!is_email($new_email)) {
        wp_send_json_error(array('message' => '有効なメールアドレスを入力してください'));
    }
    
    // 既存のメールアドレスと同じかチェック（小文字に統一して比較）
    if (strtolower($current_user->user_email) === strtolower($new_email)) {
        wp_send_json_error(array('message' => '現在と同じメールアドレスです'));
    }
    
    // 他のユーザーが使用していないかチェック
    $existing_user_id = email_exists($new_email);
    if ($existing_user_id && $existing_user_id != $current_user_id) {
        wp_send_json_error(array('message' => 'このメールアドレスは既に使用されています'));
    }
    
    // メールアドレスを更新
    $result = wp_update_user(array(
        'ID' => $current_user_id,
        'user_email' => $new_email
    ));
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => 'メールアドレスの更新に失敗しました'));
    } else {
        wp_send_json_success(array('message' => 'メールアドレスを更新しました'));
    }
}

// メールアドレス更新処理（従来のフォーム送信用 - 互換性のため残す）
add_action('admin_post_sokulabo_update_email', 'sokulabo_handle_update_email');
function sokulabo_handle_update_email() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_die('ログインしてください');
    }
    
    // Nonce確認
    if (!isset($_POST['sokulabo_update_email_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_update_email_nonce'], 'sokulabo_update_email')) {
        wp_die('不正なリクエストです');
    }
    
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    
    // 入力値の取得とトリミング
    $new_email = isset($_POST['new_email']) ? trim(sanitize_email($_POST['new_email'])) : '';
    
    // 空欄チェック
    if (empty($new_email)) {
        wp_redirect(add_query_arg('error', urlencode('メールアドレスを入力してください'), home_url('/mypage/')));
        exit;
    }
    
    // メールアドレスの形式チェック
    if (!is_email($new_email)) {
        wp_redirect(add_query_arg('error', urlencode('有効なメールアドレスを入力してください'), home_url('/mypage/')));
        exit;
    }
    
    // 既存のメールアドレスと同じかチェック（小文字に統一して比較）
    if (strtolower($current_user->user_email) === strtolower($new_email)) {
        $redirect_url = add_query_arg('error', urlencode('現在と同じメールアドレスです'), home_url('/mypage/'));
        wp_redirect($redirect_url);
        exit;
    }
    
    // 他のユーザーが使用していないかチェック
    $existing_user_id = email_exists($new_email);
    if ($existing_user_id && $existing_user_id != $current_user_id) {
        wp_redirect(add_query_arg('error', urlencode('このメールアドレスは既に使用されています'), home_url('/mypage/')));
        exit;
    }
    
    // メールアドレスを更新
    $result = wp_update_user(array(
        'ID' => $current_user_id,
        'user_email' => $new_email
    ));
    
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg('error', urlencode('メールアドレスの更新に失敗しました'), home_url('/mypage/')));
    } else {
        wp_redirect(add_query_arg('success', urlencode('メールアドレスを更新しました'), home_url('/mypage/')));
    }
    exit;
}

add_action('admin_post_sokulabo_delete_account', 'sokulabo_handle_delete_account');
function sokulabo_handle_delete_account() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_die('ログインしてください');
    }
    
    // Nonce確認
    if (!isset($_POST['sokulabo_delete_account_nonce']) || 
        !wp_verify_nonce($_POST['sokulabo_delete_account_nonce'], 'sokulabo_delete_account')) {
        wp_die('不正なリクエストです');
    }
    
    $current_user_id = get_current_user_id();
    
    // 管理者は削除不可
    if (user_can($current_user_id, 'administrator')) {
        wp_redirect(add_query_arg('error', urlencode('管理者アカウントは削除できません'), home_url('/mypage/')));
        exit;
    }
    
    // ユーザーに紐づくライセンスをすべて削除
    $licenses = get_posts(array(
        'post_type' => 'sokulabo_license',
        'author' => $current_user_id,
        'posts_per_page' => -1,
    ));
    
    foreach ($licenses as $license) {
        wp_delete_post($license->ID, true); // 完全削除
    }
    
    // ユーザーを削除
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $result = wp_delete_user($current_user_id);
    
    if ($result) {
        // ログアウトしてトップページにリダイレクト
        wp_logout();
        wp_redirect(add_query_arg('account_deleted', '1', home_url()));
    } else {
        wp_redirect(add_query_arg('error', urlencode('アカウントの削除に失敗しました'), home_url('/mypage/')));
    }
    exit;
}

// ログイン・マイページ切り替えボタンのショートコード
add_shortcode('sokulabo_login_button', 'sokulabo_login_button_shortcode');
function sokulabo_login_button_shortcode($atts) {
    // パラメータの設定（デフォルト値）
    $atts = shortcode_atts(array(
        'login_text' => 'ログイン',
        'register_text' => '会員登録',
        'mypage_text' => 'マイページ',
        'logout_text' => 'ログアウト',
        'show_logout' => 'false',
        'show_register' => 'true',
        'style' => 'default', // default, button, link
        'class' => '',
    ), $atts);
    
    $output = '';
    
    // CSS スタイル
    $output .= '<style>
        .sokulabo-login-button {
            display: inline-block;
            margin: 10px 0;
        }
        .sokulabo-login-button.style-default a {
            font-size: 13px;
            display: inline-block;
            padding: 5px 16px;
            border: 1px solid #0b6bbf;
            color: #0b6bbf;
            text-decoration: none;
            border-radius: 3px;
            font-weight: 500;
        }
        .sokulabo-login-button.style-default a.register-btn {
            border-color: #0b6bbf;
            color: #fff;
            background: #0b6bbf;
            margin-left: 10px;
        }
        .sokulabo-login-button.style-default a.register-btn:hover {
            border-color: #094a87;
            background: #094a87;
        }
        .sokulabo-login-button.style-button a {
            display: inline-block;
            padding: 10px 20px;
            background: #0b6bbf;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: 2px solid #0b6bbf;
            transition: all 0.2s ease;
        }
        .sokulabo-login-button.style-button a:hover {
            background: #094a8a;
            border-color: #094a8a;
        }
        .sokulabo-login-button.style-link a {
            color: #0b6bbf;
            text-decoration: underline;
            transition: color 0.2s ease;
        }
        .sokulabo-login-button.style-link a:hover {
            color: #094a8a;
        }
        .sokulabo-login-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .sokulabo-logout-link {
            font-size: 14px;
            opacity: 0.8;
        }
    </style>';
    
    // ボタンの生成
    $output .= '<div class="sokulabo-login-button style-' . esc_attr($atts['style']) . ' ' . esc_attr($atts['class']) . '">';
    
    if (is_user_logged_in()) {
        // ログイン済みの場合
        $current_user = wp_get_current_user();
        
        if ($atts['show_logout'] === 'true') {
            // マイページとログアウトの両方を表示
            $output .= '<div class="sokulabo-login-buttons">';
            $output .= '<a href="' . home_url('/mypage/') . '">' . esc_html($atts['mypage_text']) . '</a>';
            $output .= '<a href="' . wp_logout_url(get_permalink()) . '" class="sokulabo-logout-link">(' . esc_html($atts['logout_text']) . ')</a>';
            $output .= '</div>';
        } else {
            // マイページのみ表示
            $output .= '<a href="' . home_url('/mypage/') . '">' . esc_html($atts['mypage_text']) . '</a>';
        }
    } else {
        // 未ログインの場合
        $output .= '<a href="' . home_url('/login/') . '">' . esc_html($atts['login_text']) . '</a>';
        
        // 会員登録ボタンを追加（show_register="true"の場合のみ）
        if ($atts['show_register'] === 'true') {
            $output .= '<a href="' . home_url('/register/') . '" class="register-btn">' . esc_html($atts['register_text']) . '</a>';
        }
    }
    
    $output .= '</div>';
    
    return $output;
}

// 管理バーを非表示（会員用）
add_action('after_setup_theme', 'sokulabo_hide_admin_bar_for_subscribers');
function sokulabo_hide_admin_bar_for_subscribers() {
    if (!current_user_can('edit_posts')) {
        show_admin_bar(false);
    }
}

// ログインフォームのショートコード
add_shortcode('sokulabo_login_form', 'sokulabo_login_form_shortcode');
function sokulabo_login_form_shortcode() {
    // 管理画面内（プレビューや編集時）、AJAX処理、REST API処理では処理しない
    if (is_admin() || 
        (defined('DOING_AJAX') && DOING_AJAX) || 
        (defined('REST_REQUEST') && REST_REQUEST) ||
        wp_doing_ajax() ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false)) {
        return '<p>ログインフォーム（プレビューモード）</p>';
    }
    
    // ログイン済みの場合はマイページへリダイレクト
    if (is_user_logged_in()) {
        wp_redirect(home_url('/mypage/'));
        exit;
    }
    
    ob_start();
    ?>
    <style>
        .sokulabo-login-logo {
            text-align: center;
            margin: 0 auto 30px;
            max-width: 400px;
        }
        .sokulabo-login-logo h1 {
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 600;
            color: #333;
        }
        
        .sokulabo-login-form,
        .sokulabo-register-form {
            max-width: 400px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sokulabo-login-form .form-group,
        .sokulabo-register-form .form-group {
            margin-bottom: 20px;
        }
        
        .sokulabo-login-form label,
        .sokulabo-register-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .sokulabo-login-form input[type="text"],
        .sokulabo-login-form input[type="email"], 
        .sokulabo-login-form input[type="password"],
        .sokulabo-register-form input[type="text"],
        .sokulabo-register-form input[type="email"],
        .sokulabo-register-form input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }
        
        .sokulabo-login-form input[type="text"]:focus,
        .sokulabo-login-form input[type="email"]:focus,
        .sokulabo-login-form input[type="password"]:focus,
        .sokulabo-register-form input[type="text"]:focus,
        .sokulabo-register-form input[type="email"]:focus,
        .sokulabo-register-form input[type="password"]:focus {
            border-color: #0b6bbf;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 107, 191, 0.2);
        }
        
        .sokulabo-login-form .checkbox-group,
        .sokulabo-register-form .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sokulabo-login-form .checkbox-group label,
        .sokulabo-register-form .checkbox-group label {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            padding: 5px;
            line-height: 1;
        }
        
        .password-toggle:hover {
            color: #0b6bbf;
        }
        
        .sokulabo-login-form .submit-btn,
        .sokulabo-register-form .submit-btn {
            width: 100%;
            padding: 6px 14px;
            background: #0b6bbf;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .sokulabo-login-form .submit-btn:hover,
        .sokulabo-register-form .submit-btn:hover {
            background: #094a87;
        }
        
        .sokulabo-login-form .form-links,
        .sokulabo-register-form .form-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .sokulabo-login-form .form-links p,
        .sokulabo-register-form .form-links p {
            margin: 10px 0;
        }
        
        .sokulabo-login-form .form-links a,
        .sokulabo-register-form .form-links a {
            color: #0b6bbf;
            text-decoration: none;
        }
        
        .sokulabo-login-form .form-links a:hover,
        .sokulabo-register-form .form-links a:hover {
            text-decoration: underline;
        }
        
        .sokulabo-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .sokulabo-message.sokulabo-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .sokulabo-message.sokulabo-success {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }
        
        #login_error.notice-error {
            border: none;
            padding: 15px 20px;
            margin: 0 auto 24px;
            background: #ffe7e7;
            color: #721c24;
            border-left: 4px solid #d63638;
            font-size: 13px;
            max-width: 400px;
        }
        
        @media (max-width: 480px) {
            .sokulabo-login-form,
            .sokulabo-register-form {
                margin: 20px;
                padding: 30px 20px;
            }
            
            #login_error.notice-error {
                max-width: 340px!important;
                padding: 15px 20px;
            }
        }
    </style>

    <div class="sokulabo-login-logo">
        <h1>速ラボ PRODUCTS</h1>
    </div>
    
    <?php 
    // エラーメッセージの表示（フォームの外側・上部）
    $error_msg = '';
    
    // クッキーからエラーメッセージを取得
    if (isset($_COOKIE['login_error'])) {
        $error_msg = $_COOKIE['login_error'];
        // クッキーを削除
        setcookie('login_error', '', time() - 3600, '/');
    }
    // URLパラメータからも取得（フォールバック）
    elseif (isset($_GET['login_error']) && isset($_COOKIE['login_error'])) {
        $error_msg = $_COOKIE['login_error'];
        setcookie('login_error', '', time() - 3600, '/');
    }
    elseif (isset($_GET['error'])) {
        $error_msg = $_GET['error'];
    }
    
    if (!empty($error_msg)): 
    ?>
        <div id="login_error" class="notice notice-error" style="border: none; padding: 15px 20px; margin: 0 auto 24px; background: #ffe7e7; color: #721c24; border-left: 4px solid #d63638; font-size: 13px; max-width: 400px;">
            <p style="margin: 0.5em 0; padding: 2px;"><?php echo wp_kses_post($error_msg); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sokulabo-login-form">
        <form method="post" action="<?php echo wp_login_url(); ?>">
            <div class="form-group">
                <label for="user_login">ユーザー名またはメールアドレス</label>
                <input type="text" name="log" id="user_login" autocomplete="username" required>
            </div>
            
            <div class="form-group">
                <label for="user_pass">パスワード</label>
                <div class="password-wrapper">
                    <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordLogin('user_pass', this)">👁️</button>
                </div>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="rememberme" value="forever">
                    ログイン状態を保持する
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-btn">ログイン</button>
            </div>
            
            <input type="hidden" name="redirect_to" value="<?php echo home_url('/mypage/'); ?>">
        </form>
        
        <div class="form-links">
            <p><a href="<?php echo wp_lostpassword_url(); ?>">パスワードをお忘れですか？</a></p>
            <p><a href="<?php echo home_url('/register/'); ?>">会員登録はこちら</a></p>
        </div>
    </div>
    
    <script>
    function togglePasswordLogin(inputId, button) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = '🙈';
        } else {
            input.type = 'password';
            button.textContent = '👁️';
        }
    }
    </script>
    
    <?php
    
    return ob_get_clean();
}


/**
 * ========================
 * サイドバー用ユーザー情報ウィジェット
 * ========================
 */

// Dashiconsを読み込む（フロントエンドで使用）
add_action('wp_enqueue_scripts', 'sokulabo_enqueue_dashicons');
function sokulabo_enqueue_dashicons() {
    wp_enqueue_style('dashicons');
}

// ユーザー情報表示ショートコード
add_shortcode('user_info_widget', 'sokulabo_user_info_widget_shortcode');
function sokulabo_user_info_widget_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_avatar' => 'yes',
        'avatar_size' => '80',
    ), $atts);
    
    ob_start();
    
    // CSSを先に出力
    ?>
    <style>
    .sidebar-user-info {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px 30px;
        text-align: center;
    }
    .sidebar-user-info .user-avatar {
        margin-bottom: 15px;
    }
    .sidebar-user-info .user-avatar img {
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .sidebar-user-info .user-details {
        margin-bottom: 15px;
    }
    .sidebar-user-info .user-name {
        font-size: 16px;
        margin: 0 0 5px 0;
        color: #333;
    }
    .sidebar-user-info .user-email {
        font-size: 12px;
        color: #666;
        margin: 0;
        word-break: break-all;
    }
    .sidebar-user-info .user-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sidebar-user-info .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .sidebar-user-info .btn .btn-icon {
        font-size: 18px;
        line-height: 1;
        display: inline-block;
    }
    .sidebar-user-info .btn-mypage {
        background: #fff;
        border:1px solid #0b6bbf;
        color: #0b6bbf;
    }
    .sidebar-user-info .btn-logout {

    }
    .sidebar-user-info.logged-out {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }
    .sidebar-user-info.logged-out .guest-message {
        margin-bottom: 15px;
    }
    .sidebar-user-info.logged-out .guest-message p {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: #333;
    }
    .sidebar-user-info.logged-out .user-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
    }
    .sidebar-user-info.logged-out .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 12px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .sidebar-user-info.logged-out .btn-login {
        background: #fff;
        border:1px solid #0b6bbf;
        color: #0b6bbf;
    }
    .sidebar-user-info.logged-out .btn-login:hover {

    }
    .sidebar-user-info.logged-out .btn-register {
        border:1px solid #0b6bbf;
        background: #0b6bbf;
        color: #fff;
    }
    .sidebar-user-info.logged-out .btn-register:hover {

    }
    .sidebar-user-info.logged-out .benefits {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 15px;
        text-align: left;
    }
    .sidebar-user-info.logged-out .benefits-title {
        font-weight: 600;
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #333;
    }
    .sidebar-user-info.logged-out .benefits ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .sidebar-user-info.logged-out .benefits li {
        padding: 2px 0 2px 20px;
        position: relative;
        font-size: 13px;
        color: #666;
    }
    .sidebar-user-info.logged-out .benefits li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #46b450;
        font-weight: bold;
    }
    .sidebar-user-info .dashicons {
        font-family: dashicons;
        width: 18px;
        height: 18px;
        font-size: 18px;
        line-height: 1;
        vertical-align: middle;
        display: inline-block;
    }
    .sidebar-user-info .dashicons:before {
        width: 18px;
        height: 18px;
    }
    </style>
    <?php
    
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $display_name = $current_user->display_name;
        $user_email = $current_user->user_email;
        $avatar_size = intval($atts['avatar_size']);
        ?>
        <div class="sidebar-user-info logged-in">
            <?php if ($atts['show_avatar'] === 'yes') : ?>
                <div class="user-avatar">
                    <?php echo get_avatar($user_id, $avatar_size); ?>
                </div>
            <?php endif; ?>
            
            <div class="user-details">
                <p class="user-name">
                    <strong><?php echo esc_html($display_name); ?></strong> さん
                </p>
                <p class="user-email"><?php echo esc_html($user_email); ?></p>
            </div>
            
            <div class="user-actions">
                <a href="<?php echo home_url('/mypage/'); ?>" class="btn btn-mypage">
                    マイページ
                </a>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-logout">
                    ログアウト
                </a>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="sidebar-user-info logged-out">
            <div class="guest-message">
                <p>会員登録でさらに便利に！</p>
            </div>
            
            <div class="user-actions">
                <a href="<?php echo home_url('/login/'); ?>" class="btn btn-login">
                    ログイン
                </a>
                <a href="<?php echo home_url('/register/'); ?>" class="btn btn-register">
                    新規会員登録
                </a>
            </div>
            
            <div class="benefits">
                <p class="benefits-title">会員特典</p>
                <ul>
                    <li>製品ダウンロード</li>
                    <li>ライセンス認証</li>
                    <li>ライセンスキー管理</li>
                    <li>会員限定コンテンツの閲覧</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    return ob_get_clean();
}

// シンプルなログインリンクのショートコード
add_shortcode('login_link', 'sokulabo_login_link_shortcode');
function sokulabo_login_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'ログイン',
        'class' => '',
    ), $atts);
    
    if (is_user_logged_in()) {
        return '';
    }
    
    $login_url = home_url('/login/');
    $class = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf(
        '<a href="%s"%s>%s</a>',
        esc_url($login_url),
        $class,
        esc_html($atts['text'])
    );
}

// シンプルな会員登録リンクのショートコード
add_shortcode('register_link', 'sokulabo_register_link_shortcode');
function sokulabo_register_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => '会員登録',
        'class' => '',
    ), $atts);
    
    if (is_user_logged_in()) {
        return '';
    }
    
    $register_url = home_url('/register/');
    $class = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf(
        '<a href="%s"%s>%s</a>',
        esc_url($register_url),
        $class,
        esc_html($atts['text'])
    );
}

// マイページリンクのショートコード
add_shortcode('mypage_link', 'sokulabo_mypage_link_shortcode');
function sokulabo_mypage_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'マイページ',
        'class' => '',
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '';
    }
    
    $mypage_url = home_url('/mypage/');
    $class = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf(
        '<a href="%s"%s>%s</a>',
        esc_url($mypage_url),
        $class,
        esc_html($atts['text'])
    );
}

// 現在のユーザー名を表示するショートコード
add_shortcode('current_username', 'sokulabo_current_username_shortcode');
function sokulabo_current_username_shortcode($atts) {
    $atts = shortcode_atts(array(
        'suffix' => 'さん',
        'default' => 'ゲスト',
    ), $atts);
    
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $name = $current_user->display_name;
        return esc_html($name . $atts['suffix']);
    }
    
    return esc_html($atts['default']);
}

// ログアウトリンクのショートコード
add_shortcode('logout_link', 'sokulabo_logout_link_shortcode');
function sokulabo_logout_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => 'ログアウト',
        'class' => '',
        'redirect' => home_url(),
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '';
    }
    
    $logout_url = wp_logout_url($atts['redirect']);
    $class = $atts['class'] ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf(
        '<a href="%s"%s>%s</a>',
        esc_url($logout_url),
        $class,
        esc_html($atts['text'])
    );
}


/**
 * ========================
 * パスワードリセットページのカスタマイズ
 * ========================
 */

// ログインページのカスタムスタイル
add_action('login_enqueue_scripts', 'sokulabo_custom_login_styles');
function sokulabo_custom_login_styles() {
    ?>
    <style type="text/css">
        /* 全体の背景 */
        body.login {
            background: #fff;
        }
        
        /* ログインフォームコンテナ */
        #login {
            width: 400px;
            padding: 8% 0 0;
        }
        
        /* ロゴエリア */
        #login h1 {
            margin-bottom: 20px;
            color: #0b6bbf;
        }
        
        #login h1 a {
            background-image: none !important;
            width: auto !important;
            height: auto !important;
            text-indent: 0 !important;
            font-size: 28px;
            font-weight: 700;
            color: #333;
            text-decoration: none;
            display: block;
            text-align: center;
            padding: 0;
            margin: 0 auto 30px;
        }
        
        #login h1 a::after {

        }
        
        /* フォームボックス */
        .login form {
            margin: 24px auto !important;
            padding: 40px !important;
            background: #fff !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
            border: none!important;
        }
        
        /* フォームタイトル */
        .login form .message,
        .login #login_error {
            border: none;
            padding: 15px 20px;
            margin: 0 0 20px;
        }
        
        .login form .message {
            background: #e7f3ff;
            color: #004085;
            border-left: 4px solid #2271b1;
        }
        
        .login #login_error {
            background: #ffe7e7;
            color: #721c24;
            border-left: 4px solid #d63638;
        }
        
        /* ラベル */
        .login label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        /* 入力フィールド */
        .login input[type="text"],
        .login input[type="password"],
        .login input[type="email"] {
            width: 100% !important;
            padding: 10px!important;
            border: 1px solid #ddd!important;
            border-radius: 4px !important;
            font-size: 16px !important;
            transition: border-color 0.2s ease !important;
            box-sizing: border-box !important;
            background-color: #f7f7f7 !important;
        }   
        
        .login input[type="text"]:focus,
        .login input[type="password"]:focus,
        .login input[type="email"]:focus {
            border-color: #2271b1 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1) !important;
        }
        
        /* 送信ボタン */
        .login .button-primary {
            width: 100% !important;
            background: #0b6bbf !important;
            color: white !important;
            border: none !important;
            border-radius: 4px !important;
            font-size: 16px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: background-color 0.2s ease !important;
        }
        
        .login .button-primary:hover,
        .login .button-primary:focus {
            background: #094a87 !important;
        }
        
        /* フォーム内のpタグ */
        #login form p.forgetmenot {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        /* チェックボックス */
        .login .forgetmenot {
            margin: 15px 0 20px;
        }
        
        .login input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .login .forgetmenot label {
            font-weight: 400;
            display: inline;
            margin: 0;
        }
        
        /* ナビゲーションリンク */
        .login #nav,
        .login #backtoblog {
            text-align: center;
            padding: 0;
            margin: 20px 0 0;
        }
        
        .login #nav a,
        .login #backtoblog a {
            color: #2271b1;
            border: 1px solid #2271b1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: i            color: #2271b1;
            border: 1px solid #2271b1;nline-block;
            padding: 8px 15px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .login #nav a:hover,
        .login #backtoblog a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        /* パスワードリセット固有のスタイル */
        .login .message.reset-pass {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        /* 言語スイッチャー */
        .login .language-switcher {
            display: none;
        }
        
        /* レスポンシブ */
        @media screen and (max-width: 480px) {
            #login {
                width: 90%;
                padding: 5% 0 0;
            }
            
            .login form {
                padding: 30px 20px;
            }
            
            #login h1 a {
                font-size: 24px;
            }
        }
        
        /* プライバシーポリシーリンク */
        .login .privacy-policy-page-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .login .privacy-policy-page-link a {
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }
        
        .login .privacy-policy-page-link a:hover {
            opacity: 1;
        }

        .login .message, .login .notice, .login .success {
            box-shadow: 0 0 10px #eee !important;
        }
    </style>
    <?php
}

// ログインページのロゴリンク先を変更
add_filter('login_headerurl', 'sokulabo_login_logo_url');
function sokulabo_login_logo_url() {
    return home_url();
}

// ログインページのロゴのタイトルを変更
add_filter('login_headertext', 'sokulabo_login_logo_title');
function sokulabo_login_logo_title() {
    return get_bloginfo('name');
}

// パスワードリセットメールの件名をカスタマイズ
add_filter('retrieve_password_title', 'sokulabo_retrieve_password_title');
function sokulabo_retrieve_password_title($title) {
    return '【速ラボ PRODUCTS】パスワードリセットのご案内';
}

// パスワードリセットメールの本文をカスタマイズ
add_filter('retrieve_password_message', 'sokulabo_retrieve_password_message', 10, 4);
function sokulabo_retrieve_password_message($message, $key, $user_login, $user_data) {
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    
    $message = "パスワードリセットのリクエストを受け付けました。\n\n";
    $message .= "アカウント情報:\n";
    $message .= "ユーザー名: " . $user_login . "\n";
    $message .= "メールアドレス: " . $user_data->user_email . "\n\n";
    $message .= "パスワードをリセットするには、以下のURLにアクセスしてください。\n\n";
    $message .= $reset_url . "\n\n";
    $message .= "※このリンクは1回のみ有効で、期限があります。\n";
    $message .= "※このメールに心当たりがない場合は、無視してください。\n\n";
    $message .= "────────────────────────\n";
    $message .= get_bloginfo('name') . "\n";
    $message .= home_url() . "\n";
    
    return $message;
}

// ログインページのリンクをカスタマイズ
add_action('login_footer', 'sokulabo_customize_login_links');
function sokulabo_customize_login_links() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // すべてのwp-login.phpリンクを/login/に変更
            var loginLinks = document.querySelectorAll('a[href*="wp-login.php"]');
            loginLinks.forEach(function(link) {
                // action=があるリンク（パスワードリセット等）以外を変更
                if (!link.href.includes('action=') && !link.href.includes('checkemail=')) {
                    link.href = '<?php echo home_url('/login/'); ?>';
                }
            });
            
            // "← サイト名に移動"を削除
            var backToBlog = document.getElementById('backtoblog');
            if (backToBlog) {
                backToBlog.remove();
            }
        });
    </script>
    <?php
}
