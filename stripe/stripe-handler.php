<?php
/**
 * Stripe決済処理ハンドラー
 * 
 * このファイルはStripe決済の処理を行います
 */

// WordPressが読み込まれていない場合は終了
if (!defined('ABSPATH')) {
    exit;
}

// Stripe PHPライブラリの読み込み（Composerを使用しない場合）
// Composerを使用する場合は、require 'vendor/autoload.php'; を使用してください

// Stripe APIキーを動的に取得する関数
function get_current_stripe_keys() {
    if (function_exists('get_stripe_keys')) {
        return get_stripe_keys();
    }
    
    // フォールバック: デフォルト設定
    $settings = get_option('stripe_payment_settings', array(
        'mode' => 'test',
        'test_publishable_key' => '',
        'test_secret_key' => '',
    ));
    
    $is_test_mode = ($settings['mode'] === 'test');
    
    return array(
        'publishable_key' => $is_test_mode ? ($settings['test_publishable_key'] ?? '') : ($settings['live_publishable_key'] ?? ''),
        'secret_key' => $is_test_mode ? ($settings['test_secret_key'] ?? '') : ($settings['live_secret_key'] ?? ''),
        'webhook_secret' => $is_test_mode ? ($settings['test_webhook_secret'] ?? '') : ($settings['live_webhook_secret'] ?? ''),
        'currency' => $settings['currency'] ?? 'jpy',
        'success_page' => $settings['success_page'] ?? 0,
        'cancel_page' => $settings['cancel_page'] ?? 0,
        'is_test_mode' => $is_test_mode,
    );
}

// Stripe Checkout Sessionを作成
add_action('wp_ajax_create_checkout_session', 'create_stripe_checkout_session');
add_action('wp_ajax_nopriv_create_checkout_session', 'create_stripe_checkout_session');

function create_stripe_checkout_session() {
    check_ajax_referer('stripe_checkout_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $purchase_type = isset($_POST['purchase_type']) ? sanitize_text_field($_POST['purchase_type']) : '';
    
    if (!$product_id || !in_array($purchase_type, array('one_time', 'monthly', 'yearly'))) {
        wp_send_json_error(array('message' => '無効なリクエストです。'));
        return;
    }
    
    // Stripe APIキーを取得
    $stripe_keys = get_current_stripe_keys();
    $secret_key = $stripe_keys['secret_key'];
    
    if (empty($secret_key)) {
        wp_send_json_error(array('message' => 'Stripe APIキーが設定されていません。'));
        return;
    }
    
    // 商品情報を取得
    $product_title = get_the_title($product_id);
    
    // Price IDと価格を取得
    if ($purchase_type === 'one_time') {
        $stripe_price_id = get_post_meta($product_id, '_stripe_price_id_onetime', true);
        $price = get_post_meta($product_id, '_one_time_price', true);
        $type_label = '買い切り';
    } elseif ($purchase_type === 'monthly') {
        $stripe_price_id = get_post_meta($product_id, '_stripe_price_id_monthly', true);
        $price = get_post_meta($product_id, '_monthly_price', true);
        $type_label = '月額';
    } else { // yearly
        $stripe_price_id = get_post_meta($product_id, '_stripe_price_id_yearly', true);
        $price = get_post_meta($product_id, '_yearly_price', true);
        $type_label = '年間';
    }
    
    // Price IDも価格情報もない場合はエラー
    if (empty($stripe_price_id) && empty($price)) {
        wp_send_json_error(array(
            'message' => '価格情報が設定されていません。商品の編集画面で「' . $type_label . '価格」を入力してください。',
            'debug' => array(
                'product_id' => $product_id,
                'purchase_type' => $purchase_type,
                'stripe_price_id' => $stripe_price_id,
                'price' => $price
            )
        ));
        return;
    }
    
    // Stripe APIを初期化
    try {
        // Stripe PHPライブラリを使用する場合
        // \Stripe\Stripe::setApiKey($secret_key);
        
        // cURLを使用してStripe APIを呼び出す
        $stripe_keys = get_current_stripe_keys();
        
        // 成功・キャンセルURLを設定から取得
        if ($stripe_keys['success_page']) {
            $success_url = get_permalink($stripe_keys['success_page']) . '?session_id={CHECKOUT_SESSION_ID}';
        } else {
            $success_url = home_url('/purchase-success/?session_id={CHECKOUT_SESSION_ID}');
        }
        
        if ($stripe_keys['cancel_page']) {
            $cancel_url = get_permalink($stripe_keys['cancel_page']);
        } else {
            $cancel_url = get_permalink($product_id) . '?canceled=1';
        }
        
        $mode = ($purchase_type === 'one_time') ? 'payment' : 'subscription';
        
        $data = array(
            'mode' => $mode,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $product_id,
            'metadata' => array(
                'product_id' => $product_id,
                'purchase_type' => $purchase_type,
            )
        );
        
        // Price IDがある場合はそれを使用、ない場合は価格情報から動的に作成
        if ($stripe_price_id) {
            $data['line_items'] = array(
                array(
                    'price' => $stripe_price_id,
                    'quantity' => 1,
                )
            );
        } else {
            // WordPress側の価格情報を使用
            $line_item = array(
                'quantity' => 1,
                'price_data' => array(
                    'currency' => strtolower($stripe_keys['currency']),
                    'unit_amount' => intval($price),
                    'product_data' => array(
                        'name' => $product_title,
                    ),
                ),
            );
            
            // サブスクリプションの場合は定期課金の設定を追加
            if ($purchase_type === 'monthly') {
                $line_item['price_data']['recurring'] = array(
                    'interval' => 'month',
                );
            } elseif ($purchase_type === 'yearly') {
                $line_item['price_data']['recurring'] = array(
                    'interval' => 'year',
                );
            }
            
            $data['line_items'] = array($line_item);
        }
        
        // ログインしているユーザーの場合、メールアドレスを設定
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $data['customer_email'] = $current_user->user_email;
            $data['metadata']['user_id'] = $current_user->ID;
        }
        
        // Stripe APIを呼び出し
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $session = json_decode($response, true);
            wp_send_json_success(array(
                'sessionId' => $session['id'],
                'url' => $session['url']
            ));
        } else {
            $error = json_decode($response, true);
            wp_send_json_error(array('message' => 'Stripe APIエラー: ' . ($error['error']['message'] ?? '不明なエラー')));
        }
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'エラーが発生しました: ' . $e->getMessage()));
    }
}

// Stripe Webhookを処理
add_action('rest_api_init', function() {
    register_rest_route('stripe/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'handle_stripe_webhook',
        'permission_callback' => '__return_true'
    ));
});

function handle_stripe_webhook($request) {
    $payload = $request->get_body();
    $sig_header = $request->get_header('stripe_signature');
    
    // Webhook署名検証（本番環境では必須）
    // $endpoint_secret = 'whsec_xxxxx'; // Stripeダッシュボードから取得
    
    try {
        // $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        $event = json_decode($payload, true);
        
        // イベントタイプに応じて処理
        switch ($event['type']) {
            case 'checkout.session.completed':
                handle_checkout_completed($event['data']['object']);
                break;
            
            case 'customer.subscription.created':
                handle_subscription_created($event['data']['object']);
                break;
                
            case 'customer.subscription.deleted':
                handle_subscription_deleted($event['data']['object']);
                break;
                
            case 'customer.subscription.updated':
                handle_subscription_updated($event['data']['object']);
                break;
                
            case 'invoice.payment_succeeded':
                handle_invoice_payment_succeeded($event['data']['object']);
                break;
                
            case 'invoice.payment_failed':
                handle_invoice_payment_failed($event['data']['object']);
                break;
        }
        
        return new WP_REST_Response(array('status' => 'success'), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array('error' => $e->getMessage()), 400);
    }
}

// チェックアウト完了時の処理
function handle_checkout_completed($session) {
    global $wpdb;
    
    $product_id = $session['metadata']['product_id'] ?? null;
    $purchase_type = $session['metadata']['purchase_type'] ?? null;
    $user_id = $session['metadata']['user_id'] ?? 0;
    $customer_email = $session['customer_email'] ?? $session['customer_details']['email'] ?? '';
    
    if (!$product_id || !$purchase_type) {
        return;
    }
    
    // ライセンスキーを生成
    $license_key = generate_license_key($product_id, $user_id ?: 0);
    
    // データベースに保存
    $table_name = $wpdb->prefix . 'license_keys';
    
    $data = array(
        'license_key' => $license_key,
        'product_id' => $product_id,
        'user_id' => $user_id,
        'user_email' => $customer_email,
        'purchase_type' => $purchase_type,
        'status' => 'active',
        'created_at' => current_time('mysql')
    );
    
    if ($purchase_type === 'one_time') {
        $data['stripe_payment_intent_id'] = $session['payment_intent'] ?? '';
    } else {
        $data['stripe_subscription_id'] = $session['subscription'] ?? '';
        // サブスクリプションの場合、次回請求日を設定
        if ($purchase_type === 'yearly') {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+1 year'));
        } else {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+1 month'));
        }
    }
    
    $wpdb->insert($table_name, $data);
    
    // 購入完了メールを送信（買い切りの場合のみ、サブスクはcustomer.subscription.createdで送信）
    if ($purchase_type === 'one_time') {
        send_license_email($customer_email, $license_key, $product_id, $purchase_type);
    }
}

// サブスクリプション作成時の処理
function handle_subscription_created($subscription) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    $subscription_id = $subscription['id'];
    $customer_email = $subscription['customer_email'] ?? '';
    
    // サブスクリプションIDからライセンスキー情報を取得
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE stripe_subscription_id = %s",
        $subscription_id
    ));
    
    if ($license) {
        // ステータスを有効に更新し、次回請求日を設定
        $current_period_end = isset($subscription['current_period_end']) ? date('Y-m-d H:i:s', $subscription['current_period_end']) : date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $wpdb->update(
            $table_name,
            array(
                'status' => 'active',
                'expires_at' => $current_period_end
            ),
            array('stripe_subscription_id' => $subscription_id)
        );
        
        // サブスクリプション開始メールを送信
        send_license_email(
            $license->user_email,
            $license->license_key,
            $license->product_id,
            'subscription'
        );
    }
}

// サブスクリプション削除時の処理
function handle_subscription_deleted($subscription) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    $subscription_id = $subscription['id'];
    
    // ライセンスキー情報を取得
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE stripe_subscription_id = %s",
        $subscription_id
    ));
    
    // ライセンスキーを無効化
    $wpdb->update(
        $table_name,
        array('status' => 'canceled'),
        array('stripe_subscription_id' => $subscription_id)
    );
    
    // 解約通知メールを送信
    if ($license) {
        send_subscription_notification_email(
            $license->user_email,
            $license->product_id,
            'canceled',
            array('license_key' => $license->license_key)
        );
    }
}

// サブスクリプション更新時の処理
function handle_subscription_updated($subscription) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    $subscription_id = $subscription['id'];
    $status = $subscription['status'];
    
    // ライセンスキー情報を取得
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE stripe_subscription_id = %s",
        $subscription_id
    ));
    
    // ステータスに応じてライセンスキーを更新
    $license_status = ($status === 'active') ? 'active' : 'inactive';
    
    $update_data = array('status' => $license_status);
    
    // 次回請求日を更新
    if (isset($subscription['current_period_end'])) {
        $update_data['expires_at'] = date('Y-m-d H:i:s', $subscription['current_period_end']);
    }
    
    $wpdb->update(
        $table_name,
        $update_data,
        array('stripe_subscription_id' => $subscription_id)
    );
    
    // ステータス変更をメールで通知
    if ($license && $license_status !== 'active') {
        send_subscription_notification_email(
            $license->user_email,
            $license->product_id,
            'status_changed',
            array(
                'license_key' => $license->license_key,
                'new_status' => $license_status
            )
        );
    }
}

// 請求書支払い成功時の処理
function handle_invoice_payment_succeeded($invoice) {
    global $wpdb;
    $license_table = $wpdb->prefix . 'license_keys';
    $invoice_table = $wpdb->prefix . 'stripe_invoice_history';
    
    $subscription_id = $invoice['subscription'] ?? '';
    $invoice_id = $invoice['id'] ?? '';
    $amount = ($invoice['amount_paid'] ?? 0) / 100;
    $currency = strtoupper($invoice['currency'] ?? 'JPY');
    $customer_email = $invoice['customer_email'] ?? '';
    $invoice_date = isset($invoice['created']) ? date('Y-m-d H:i:s', $invoice['created']) : current_time('mysql');
    
    if ($subscription_id) {
        // ライセンスキー情報を取得
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $license_table WHERE stripe_subscription_id = %s",
            $subscription_id
        ));
        
        if ($license) {
            // ステータスを有効に更新し、次回請求日を更新
            $period_end = isset($invoice['lines']['data'][0]['period']['end']) ? date('Y-m-d H:i:s', $invoice['lines']['data'][0]['period']['end']) : date('Y-m-d H:i:s', strtotime('+1 month'));
            
            $wpdb->update(
                $license_table,
                array(
                    'status' => 'active',
                    'expires_at' => $period_end
                ),
                array('stripe_subscription_id' => $subscription_id)
            );
            
            // 請求履歴を保存
            $wpdb->insert(
                $invoice_table,
                array(
                    'user_id' => $license->user_id,
                    'user_email' => $license->user_email,
                    'license_id' => $license->id,
                    'stripe_invoice_id' => $invoice_id,
                    'stripe_subscription_id' => $subscription_id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'paid',
                    'invoice_date' => $invoice_date,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
            );
            
            // 初回以外の支払いの場合（billing_reason が 'subscription_cycle'）、更新通知を送信
            if (($invoice['billing_reason'] ?? '') === 'subscription_cycle') {
                send_subscription_notification_email(
                    $license->user_email,
                    $license->product_id,
                    'payment_success',
                    array(
                        'license_key' => $license->license_key,
                        'amount' => $amount,
                        'currency' => $currency
                    )
                );
            }
        }
    } else {
        // サブスクリプションIDがない場合（買い切り）も履歴を保存
        $payment_intent_id = $invoice['payment_intent'] ?? '';
        if ($payment_intent_id) {
            $license = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $license_table WHERE stripe_payment_intent_id = %s",
                $payment_intent_id
            ));
            
            if ($license) {
                $wpdb->insert(
                    $invoice_table,
                    array(
                        'user_id' => $license->user_id,
                        'user_email' => $license->user_email,
                        'license_id' => $license->id,
                        'stripe_invoice_id' => $invoice_id,
                        'stripe_subscription_id' => '',
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'invoice_date' => $invoice_date,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
                );
            }
        }
    }
}

// 請求書支払い失敗時の処理
function handle_invoice_payment_failed($invoice) {
    global $wpdb;
    $license_table = $wpdb->prefix . 'license_keys';
    $invoice_table = $wpdb->prefix . 'stripe_invoice_history';
    
    $subscription_id = $invoice['subscription'] ?? '';
    $invoice_id = $invoice['id'] ?? '';
    $amount = ($invoice['amount_due'] ?? 0) / 100;
    $currency = strtoupper($invoice['currency'] ?? 'JPY');
    $invoice_date = isset($invoice['created']) ? date('Y-m-d H:i:s', $invoice['created']) : current_time('mysql');
    
    if (!$subscription_id) {
        return;
    }
    
    // ライセンスキー情報を取得
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE stripe_subscription_id = %s",
        $subscription_id
    ));
    
    if ($license) {
        // ステータスを支払い失敗に更新
        $wpdb->update(
            $license_table,
            array('status' => 'payment_failed'),
            array('stripe_subscription_id' => $subscription_id)
        );
        
        // 請求履歴を保存
        $wpdb->insert(
            $invoice_table,
            array(
                'user_id' => $license->user_id,
                'user_email' => $license->user_email,
                'license_id' => $license->id,
                'stripe_invoice_id' => $invoice_id,
                'stripe_subscription_id' => $subscription_id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'failed',
                'invoice_date' => $invoice_date,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
        
        // 支払い失敗通知を送信
        send_subscription_notification_email(
            $license->user_email,
            $license->product_id,
            'payment_failed',
            array(
                'license_key' => $license->license_key,
                'amount' => $amount,
                'currency' => $currency
            )
        );
    }
}

// ライセンスキー送信メール
function send_license_email($email, $license_key, $product_id, $purchase_type) {
    // メール設定を取得
    $email_settings = get_option('stripe_email_settings', array(
        'enable_emails' => 1,
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name'),
        'purchase_subject' => '【{site_name}】ご購入ありがとうございます',
        'purchase_message' => '',
        'subscription_subject' => '【{site_name}】サブスクリプション開始のお知らせ',
        'subscription_message' => '',
    ));
    
    // メール送信が無効の場合は何もしない
    if (empty($email_settings['enable_emails'])) {
        return;
    }
    
    // 送信元の設定（フィルターを一度だけ適用）
    $mail_from_set = false;
    add_filter('wp_mail_from', function($original) use ($email_settings, &$mail_from_set) {
        if ($mail_from_set) return $original;
        $mail_from_set = true;
        return !empty($email_settings['from_email']) ? $email_settings['from_email'] : $original;
    }, 999);
    
    $mail_from_name_set = false;
    add_filter('wp_mail_from_name', function($original) use ($email_settings, &$mail_from_name_set) {
        if ($mail_from_name_set) return $original;
        $mail_from_name_set = true;
        return !empty($email_settings['from_name']) ? $email_settings['from_name'] : $original;
    }, 999);
    
    // HTMLメール設定
    add_filter('wp_mail_content_type', function($content_type) {
        return 'text/html';
    }, 999);
    
    // 変数の準備
    $product_name = get_the_title($product_id);
    $purchase_type_text = ($purchase_type === 'one_time') ? '買い切り' : 'サブスクリプション（月額）';
    
    $replacements = array(
        '{site_name}' => get_bloginfo('name'),
        '{site_url}' => home_url(),
        '{product_name}' => $product_name,
        '{purchase_type}' => $purchase_type_text,
        '{license_key}' => $license_key,
        '{customer_email}' => $email,
        '{my_account_url}' => home_url('/my-account/'),
        '{product_url}' => get_permalink($product_id),
    );
    
    // 購入タイプに応じて件名と本文を選択
    if ($purchase_type === 'one_time') {
        $subject = $email_settings['purchase_subject'];
        $message = $email_settings['purchase_message'];
    } else {
        $subject = $email_settings['subscription_subject'];
        $message = $email_settings['subscription_message'];
    }
    
    // 変数を置換
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    // デフォルトメッセージ（設定が空の場合）
    if (empty($message)) {
        $message = "この度は、{$product_name} をご購入いただき、誠にありがとうございます。\n\n";
        $message .= "ライセンスキーを発行いたしましたので、以下の情報をご確認ください。\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "商品名: {$product_name}\n";
        $message .= "購入タイプ: {$purchase_type_text}\n";
        $message .= "ライセンスキー: {$license_key}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "ライセンスキーは大切に保管してください。\n";
        
        if (in_array($purchase_type, array('monthly', 'yearly'))) {
            $interval_text = $purchase_type === 'monthly' ? '毎月' : '毎年';
            $message .= "\n※サブスクリプションは{$interval_text}自動的に更新されます。\n";
            $message .= "※解約をご希望の場合は、マイページから手続きを行ってください。\n";
        }
        
        $message .= "\n\nマイページ: " . home_url('/mypage/') . "\n";
    }
    
    // フッターを追加
    $message .= "\n────────────────────────\n";
    $message .= get_bloginfo('name') . "\n";
    $message .= home_url() . "\n";
    
    // メールヘッダーを設定
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>',
    );
    
    // Reply-Toが設定されている場合は追加（空の場合はfrom_emailを使用）
    $reply_to = !empty($email_settings['reply_to']) ? $email_settings['reply_to'] : $email_settings['from_email'];
    $headers[] = 'Reply-To: ' . $reply_to;
    
    // Return-Pathが設定されている場合は追加
    if (!empty($email_settings['return_path'])) {
        $headers[] = 'Return-Path: ' . $email_settings['return_path'];
    }
    
    // BCCが設定されている場合は追加
    if (!empty($email_settings['bcc'])) {
        $headers[] = 'Bcc: ' . $email_settings['bcc'];
    }
    
    // 改行をHTMLに変換
    $message = nl2br($message);
    
    // メール送信
    $result = wp_mail($email, $subject, $message, $headers);
    
    // フィルターをクリーンアップ
    remove_all_filters('wp_mail_from', 999);
    remove_all_filters('wp_mail_from_name', 999);
    remove_all_filters('wp_mail_content_type', 999);
    
    return $result;
}

// サブスクリプション通知メール送信
function send_subscription_notification_email($email, $product_id, $notification_type, $data = array()) {
    // メール設定を取得
    $email_settings = get_option('stripe_email_settings', array(
        'enable_emails' => 1,
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name'),
        'reply_to' => '',
        'return_path' => '',
        'bcc' => '',
        'renewal_subject' => '【{site_name}】サブスクリプション更新完了',
        'renewal_message' => "いつも {product_name} をご利用いただき、誠にありがとうございます。\n\nサブスクリプションの更新が完了いたしました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n更新金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n次回の更新も自動的に行われます。\n引き続き {product_name} をお楽しみください。\n\nマイページ: {my_account_url}",
        'cancellation_subject' => '【{site_name}】サブスクリプションが解約されました',
        'cancellation_message' => "{product_name} のサブスクリプションが解約されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nこれまで {product_name} をご利用いただき、誠にありがとうございました。\n\n※有効期限までは引き続きサービスをご利用いただけます。\n※再度ご契約をご希望の場合は、商品ページからお申し込みください。\n\nマイページ: {my_account_url}",
        'payment_failed_subject' => '【{site_name}】サブスクリプション支払いが失敗しました',
        'payment_failed_message' => "{product_name} のサブスクリプション更新の支払いに失敗しました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n請求金額: {amount} {currency}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\n【重要】お支払い方法をご確認ください\n\n支払いが完了しない場合、サービスのご利用が制限される可能性がございます。\nお手数ですが、マイページから支払い方法の更新をお願いいたします。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
        'status_changed_subject' => '【{site_name}】サブスクリプションステータスが変更されました',
        'status_changed_message' => "{product_name} のサブスクリプションステータスが変更されました。\n\n━━━━━━━━━━━━━━━━━━━━\n商品名: {product_name}\n購入タイプ: {purchase_type}\n新しいステータス: {new_status}\nライセンスキー: {license_key}\n━━━━━━━━━━━━━━━━━━━━\n\nステータスの変更により、サービスのご利用状況が変わる場合がございます。\n詳細はマイページからご確認ください。\n\nマイページ: {my_account_url}\n\nご不明な点がございましたら、お気軽にお問い合わせください。",
    ));
    
    // メール送信が無効の場合は何もしない
    if (empty($email_settings['enable_emails'])) {
        return;
    }
    
    // 送信元の設定
    $mail_from_set = false;
    add_filter('wp_mail_from', function($original) use ($email_settings, &$mail_from_set) {
        if ($mail_from_set) return $original;
        $mail_from_set = true;
        return !empty($email_settings['from_email']) ? $email_settings['from_email'] : $original;
    }, 999);
    
    $mail_from_name_set = false;
    add_filter('wp_mail_from_name', function($original) use ($email_settings, &$mail_from_name_set) {
        if ($mail_from_name_set) return $original;
        $mail_from_name_set = true;
        return !empty($email_settings['from_name']) ? $email_settings['from_name'] : $original;
    }, 999);
    
    // HTMLメール設定
    add_filter('wp_mail_content_type', function($content_type) {
        return 'text/html';
    }, 999);
    
    $product_name = get_the_title($product_id);
    $site_name = get_bloginfo('name');
    
    // 変数の準備
    $replacements = array(
        '{site_name}' => $site_name,
        '{site_url}' => home_url(),
        '{product_name}' => $product_name,
        '{license_key}' => $data['license_key'] ?? '',
        '{customer_email}' => $email,
        '{my_account_url}' => home_url('/mypage/'),
        '{amount}' => isset($data['amount']) ? number_format($data['amount']) : '',
        '{currency}' => $data['currency'] ?? '',
        '{new_status}' => $data['new_status'] ?? '',
    );
    
    // 通知タイプに応じて件名と本文を設定
    switch ($notification_type) {
        case 'canceled':
            $subject = $email_settings['cancellation_subject'];
            $message = $email_settings['cancellation_message'];
            
            // デフォルト件名
            if (empty($subject)) {
                $subject = '【{site_name}】サブスクリプションが解約されました';
            }
            
            // デフォルトメッセージ
            if (empty($message)) {
                $message = "{product_name} のサブスクリプションが解約されました。\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "商品名: {product_name}\n";
                $message .= "購入タイプ: {purchase_type}\n";
                $message .= "ライセンスキー: {license_key}\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "これまで {product_name} をご利用いただき、誠にありがとうございました。\n\n";
                $message .= "※有効期限までは引き続きサービスをご利用いただけます。\n";
                $message .= "※再度ご契約をご希望の場合は、商品ページからお申し込みください。\n\n";
                $message .= "マイページ: {my_account_url}\n";
            }
            break;
            
        case 'payment_success':
            $subject = $email_settings['renewal_subject'];
            $message = $email_settings['renewal_message'];
            
            // デフォルト件名
            if (empty($subject)) {
                $subject = '【{site_name}】サブスクリプション更新完了';
            }
            
            // デフォルトメッセージ
            if (empty($message)) {
                $message = "いつも {product_name} をご利用いただき、誠にありがとうございます。\n\n";
                $message .= "サブスクリプションの更新が完了いたしました。\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "商品名: {product_name}\n";
                $message .= "購入タイプ: {purchase_type}\n";
                $message .= "更新金額: {amount} {currency}\n";
                $message .= "ライセンスキー: {license_key}\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "次回の更新も自動的に行われます。\n";
                $message .= "引き続き {product_name} をお楽しみください。\n\n";
                $message .= "マイページ: {my_account_url}\n";
            }
            break;
            
        case 'payment_failed':
            $subject = $email_settings['payment_failed_subject'];
            $message = $email_settings['payment_failed_message'];
            
            // デフォルト件名
            if (empty($subject)) {
                $subject = '【{site_name}】サブスクリプション支払いが失敗しました';
            }
            
            // デフォルトメッセージ
            if (empty($message)) {
                $message = "{product_name} のサブスクリプション更新の支払いに失敗しました。\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "商品名: {product_name}\n";
                $message .= "購入タイプ: {purchase_type}\n";
                $message .= "請求金額: {amount} {currency}\n";
                $message .= "ライセンスキー: {license_key}\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "【重要】お支払い方法をご確認ください\n\n";
                $message .= "支払いが完了しない場合、サービスのご利用が制限される可能性がございます。\n";
                $message .= "お手数ですが、マイページから支払い方法の更新をお願いいたします。\n\n";
                $message .= "マイページ: {my_account_url}\n\n";
                $message .= "ご不明な点がございましたら、お気軽にお問い合わせください。\n";
            }
            break;
            
        case 'status_changed':
            $subject = $email_settings['status_changed_subject'];
            $message = $email_settings['status_changed_message'];
            
            // デフォルト件名
            if (empty($subject)) {
                $subject = '【{site_name}】サブスクリプションステータスが変更されました';
            }
            
            // デフォルトメッセージ
            if (empty($message)) {
                $message = "{product_name} のサブスクリプションステータスが変更されました。\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "商品名: {product_name}\n";
                $message .= "購入タイプ: {purchase_type}\n";
                $message .= "新しいステータス: {new_status}\n";
                $message .= "ライセンスキー: {license_key}\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "ステータスの変更により、サービスのご利用状況が変わる場合がございます。\n";
                $message .= "詳細はマイページからご確認ください。\n\n";
                $message .= "マイページ: {my_account_url}\n\n";
                $message .= "ご不明な点がございましたら、お気軽にお問い合わせください。\n";
            }
            break;
            
        default:
            return; // 不明な通知タイプの場合は何もしない
    }
    
    // 変数を置換
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    // フッターを追加
    $message .= "\n────────────────────────\n";
    $message .= get_bloginfo('name') . "\n";
    $message .= home_url() . "\n";
    
    // メールヘッダーを設定
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>',
    );
    
    // Reply-Toが設定されている場合は追加
    $reply_to = !empty($email_settings['reply_to']) ? $email_settings['reply_to'] : $email_settings['from_email'];
    $headers[] = 'Reply-To: ' . $reply_to;
    
    // Return-Pathが設定されている場合は追加
    if (!empty($email_settings['return_path'])) {
        $headers[] = 'Return-Path: ' . $email_settings['return_path'];
    }
    
    // BCCが設定されている場合は追加
    if (!empty($email_settings['bcc'])) {
        $headers[] = 'Bcc: ' . $email_settings['bcc'];
    }
    
    // 改行をHTMLに変換
    $message = nl2br($message);
    
    // メール送信
    $result = wp_mail($email, $subject, $message, $headers);
    
    // フィルターをクリーンアップ
    remove_all_filters('wp_mail_from', 999);
    remove_all_filters('wp_mail_from_name', 999);
    remove_all_filters('wp_mail_content_type', 999);
    
    return $result;
}

// テストメール送信関数
function send_test_email() {
    if (!isset($_POST['test_email_address']) || !isset($_POST['test_email_type'])) {
        return false;
    }
    
    $to_input = sanitize_text_field($_POST['test_email_address']);
    $type = sanitize_text_field($_POST['test_email_type']);
    
    if (empty($to_input)) {
        add_settings_error(
            'stripe_email_settings',
            'test_email_failed',
            'テストメール送信先が指定されていません。',
            'error'
        );
        return false;
    }
    
    // カンマ区切りで複数のメールアドレスを分割
    $email_addresses = array_map('trim', explode(',', $to_input));
    $email_addresses = array_filter($email_addresses); // 空の要素を削除
    
    // 各メールアドレスをサニタイズして検証
    $valid_emails = array();
    $invalid_emails = array();
    
    foreach ($email_addresses as $email) {
        $sanitized = sanitize_email($email);
        if (is_email($sanitized)) {
            $valid_emails[] = $sanitized;
        } else {
            $invalid_emails[] = $email;
        }
    }
    
    if (empty($valid_emails)) {
        add_settings_error(
            'stripe_email_settings',
            'test_email_failed',
            '有効なメールアドレスが指定されていません。',
            'error'
        );
        return false;
    }
    
    if (!empty($invalid_emails)) {
        add_settings_error(
            'stripe_email_settings',
            'invalid_emails',
            '無効なメールアドレス: ' . implode(', ', $invalid_emails),
            'warning'
        );
    }
    
    // メール設定を取得
    $settings = get_option('stripe_email_settings', array(
        'enable_emails' => 1,
        'from_email' => get_option('admin_email'),
        'from_name' => get_bloginfo('name'),
        'reply_to' => '',
        'return_path' => '',
        'bcc' => '',
    ));
    
    // 送信元の設定
    add_filter('wp_mail_from', function($original) use ($settings) {
        return !empty($settings['from_email']) ? $settings['from_email'] : $original;
    }, 999);
    
    add_filter('wp_mail_from_name', function($original) use ($settings) {
        return !empty($settings['from_name']) ? $settings['from_name'] : $original;
    }, 999);
    
    add_filter('wp_mail_content_type', function($content_type) {
        return 'text/html';
    }, 999);
    
    $site_name = get_bloginfo('name');
    
    // 各メールアドレスに送信
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($valid_emails as $to) {
        $replacements = array(
            '{site_name}' => $site_name,
            '{site_url}' => home_url(),
            '{product_name}' => 'テスト商品',
            '{purchase_type}' => '月額プラン',
            '{license_key}' => 'TEST-XXXX-XXXX-XXXX',
        '{customer_email}' => $to,
        '{my_account_url}' => home_url('/mypage/'),
        '{amount}' => '1,000',
        '{currency}' => 'JPY',
        '{new_status}' => 'active',
    );
    
    // メールタイプに応じて件名と本文を設定
    switch ($type) {
        case 'purchase':
            $subject = $settings['purchase_subject'] ?? '【{site_name}】ご購入ありがとうございます';
            $message = $settings['purchase_message'] ?? 'これはテストメールです。\n\n商品名: {product_name}\nライセンスキー: {license_key}';
            break;
        case 'subscription':
            $subject = $settings['subscription_subject'] ?? '【{site_name}】サブスクリプション開始のお知らせ';
            $message = $settings['subscription_message'] ?? 'これはテストメールです。\n\nサブスクリプションが開始されました。';
            break;
        case 'renewal':
            $subject = $settings['renewal_subject'] ?? '【{site_name}】サブスクリプション更新完了';
            $message = $settings['renewal_message'] ?? 'これはテストメールです。\n\nお支払い額: ¥{amount}';
            break;
        case 'cancellation':
            $subject = $settings['cancellation_subject'] ?? '【{site_name}】サブスクリプションが解約されました';
            $message = $settings['cancellation_message'] ?? 'これはテストメールです。\n\nサブスクリプションが解約されました。';
            break;
        case 'payment_failed':
            $subject = $settings['payment_failed_subject'] ?? '【{site_name}】サブスクリプション支払いが失敗しました';
            $message = $settings['payment_failed_message'] ?? 'これはテストメールです。\n\n支払いが失敗しました。';
            break;
        case 'status_changed':
            $subject = $settings['status_changed_subject'] ?? '【{site_name}】サブスクリプションステータスが変更されました';
            $message = $settings['status_changed_message'] ?? 'これはテストメールです。\n\n新しいステータス: {new_status}';
            break;
        default:
            $subject = '【{site_name}】テストメール';
            $message = 'これはテストメールです。';
    }
    
    // 変数を置換
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    // フッターを追加
    $message .= "\n────────────────────────\n";
    $message .= "これはテストメールです\n";
    $message .= get_bloginfo('name') . "\n";
    $message .= home_url() . "\n";
    
    // メールヘッダー
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
    );
    
    $reply_to = !empty($settings['reply_to']) ? $settings['reply_to'] : $settings['from_email'];
    $headers[] = 'Reply-To: ' . $reply_to;
    
    if (!empty($settings['return_path'])) {
        $headers[] = 'Return-Path: ' . $settings['return_path'];
    }
    
    // 改行をHTMLに変換
        $html_message = nl2br($message);
        
        // メール送信
        if (wp_mail($to, $subject, $html_message, $headers)) {
            $success_count++;
        } else {
            $failed_count++;
        }
    }
    
    // フィルターをクリーンアップ
    remove_all_filters('wp_mail_from', 999);
    remove_all_filters('wp_mail_from_name', 999);
    remove_all_filters('wp_mail_content_type', 999);
    
    // 結果メッセージ
    if ($success_count > 0 && $failed_count === 0) {
        add_settings_error(
            'stripe_email_settings',
            'test_email_sent',
            sprintf('テストメールを%d件送信しました。', $success_count),
            'updated'
        );
        return true;
    } elseif ($success_count > 0 && $failed_count > 0) {
        add_settings_error(
            'stripe_email_settings',
            'test_email_partial',
            sprintf('テストメールを%d件送信しました（%d件失敗）。', $success_count, $failed_count),
            'warning'
        );
        return true;
    } else {
        add_settings_error(
            'stripe_email_settings',
            'test_email_failed',
            'テストメールの送信に失敗しました。',
            'error'
        );
        return false;
    }
}

// ショートコード: Stripeチェックアウトボタン
add_shortcode('stripe_checkout', 'stripe_checkout_button_shortcode');
function stripe_checkout_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'product_id' => 0,
    ), $atts);
    
    $product_id = intval($atts['product_id']);
    if (!$product_id) {
        return '<p>商品IDが指定されていません。</p>';
    }
    
    $one_time_price = get_post_meta($product_id, '_one_time_price', true);
    $monthly_price = get_post_meta($product_id, '_monthly_price', true);
    $yearly_price = get_post_meta($product_id, '_yearly_price', true);
    
    ob_start();
    ?>
    <div class="stripe-checkout-wrapper" data-product-id="<?php echo esc_attr($product_id); ?>">
        <?php if ($one_time_price): ?>
        <div class="pricing-option">
            <h3>買い切り</h3>
            <div class="price">¥<?php echo number_format($one_time_price); ?></div>
            <button class="stripe-checkout-btn" data-purchase-type="one_time">今すぐ購入</button>
        </div>
        <?php endif; ?>
        
        <?php if ($monthly_price): ?>
        <div class="pricing-option">
            <h3>月額プラン</h3>
            <div class="price">¥<?php echo number_format($monthly_price); ?>/月</div>
            <button class="stripe-checkout-btn" data-purchase-type="monthly">サブスクリプション開始</button>
        </div>
        <?php endif; ?>
        
        <?php if ($yearly_price): ?>
        <div class="pricing-option">
            <h3>年間プラン</h3>
            <div class="price">¥<?php echo number_format($yearly_price); ?>/年</div>
            <button class="stripe-checkout-btn" data-purchase-type="yearly">サブスクリプション開始</button>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
        .stripe-checkout-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .pricing-option {
            padding: 30px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
        }
        .pricing-option h3 {
            margin-top: 0;
            font-size: 24px;
        }
        .pricing-option .price {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }
        .stripe-checkout-btn {
            background: #5469d4;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .stripe-checkout-btn:hover {
            background: #3c52b2;
        }
        .stripe-checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.stripe-checkout-btn');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.closest('.stripe-checkout-wrapper').dataset.productId;
                const purchaseType = this.dataset.purchaseType;
                
                button.disabled = true;
                button.textContent = '処理中...';
                
                // AJAXリクエストを送信
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'create_checkout_session',
                        nonce: '<?php echo wp_create_nonce('stripe_checkout_nonce'); ?>',
                        product_id: productId,
                        purchase_type: purchaseType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Stripeチェックアウトページにリダイレクト
                        window.location.href = data.data.url;
                    } else {
                        alert('エラー: ' + data.data.message);
                        button.disabled = false;
                        button.textContent = purchaseType === 'one_time' ? '今すぐ購入' : 'サブスクリプション開始';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('エラーが発生しました。');
                    button.disabled = false;
                    button.textContent = purchaseType === 'one_time' ? '今すぐ購入' : 'サブスクリプション開始';
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ============================================
// 請求履歴取得機能
// ============================================

add_action('wp_ajax_get_invoice_history', 'get_invoice_history_ajax');
function get_invoice_history_ajax() {
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
    $license_id = intval($_POST['license_id']);
    
    global $wpdb;
    $license_table = $wpdb->prefix . 'license_keys';
    $invoice_table = $wpdb->prefix . 'stripe_invoice_history';
    
    // ライセンスが現在のユーザーのものか確認
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $license_table WHERE id = %d AND user_id = %d",
        $license_id, $user_id
    ));
    
    if (!$license) {
        wp_send_json_error(array('message' => 'ライセンスが見つからないか、権限がありません。'));
        return;
    }
    
    // 請求履歴を取得
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $invoice_table WHERE license_id = %d ORDER BY invoice_date DESC",
        $license_id
    ));
    
    $formatted_invoices = array();
    foreach ($invoices as $invoice) {
        $formatted_invoices[] = array(
            'invoice_date' => date('Y/m/d H:i', strtotime($invoice->invoice_date)),
            'amount' => $invoice->amount,
            'currency' => strtoupper($invoice->currency),
            'status' => $invoice->status
        );
    }
    
    wp_send_json_success(array('invoices' => $formatted_invoices));
}

// ============================================
// プラン変更機能
// ============================================

// 利用可能なプランを取得
add_action('wp_ajax_get_available_plans', 'get_available_plans_ajax');
function get_available_plans_ajax() {
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
    
    $license_id = intval($_POST['license_id']);
    $product_id = intval($_POST['product_id']);
    $current_type = sanitize_text_field($_POST['current_type']);
    
    if (empty($license_id) || empty($product_id)) {
        wp_send_json_error(array('message' => '必須パラメータが不足しています。'));
        return;
    }
    
    // 商品情報を取得
    $one_time_price = get_post_meta($product_id, '_one_time_price', true);
    $monthly_price = get_post_meta($product_id, '_monthly_price', true);
    $yearly_price = get_post_meta($product_id, '_yearly_price', true);
    $stripe_price_id_onetime = get_post_meta($product_id, '_stripe_price_id_onetime', true);
    $stripe_price_id_monthly = get_post_meta($product_id, '_stripe_price_id_monthly', true);
    $stripe_price_id_yearly = get_post_meta($product_id, '_stripe_price_id_yearly', true);
    
    $plans = array();
    
    // 買い切りプランが利用可能な場合
    if (!empty($one_time_price)) {
        $plans[] = array(
            'type' => 'one_time',
            'name' => get_the_title($product_id) . ' (買い切り)',
            'price' => $one_time_price,
            'type_label' => '買い切り',
            'stripe_price_id' => $stripe_price_id_onetime,
            'is_current' => ($current_type === 'one_time'),
            'is_available' => !empty($stripe_price_id_onetime)
        );
    }
    
    // 月額プランが利用可能な場合
    if (!empty($monthly_price)) {
        $plans[] = array(
            'type' => 'monthly',
            'name' => get_the_title($product_id) . ' (月額)',
            'price' => $monthly_price,
            'type_label' => '月額プラン',
            'stripe_price_id' => $stripe_price_id_monthly,
            'is_current' => ($current_type === 'monthly'),
            'is_available' => !empty($stripe_price_id_monthly)
        );
    }
    
    // 年間プランが利用可能な場合
    if (!empty($yearly_price)) {
        $plans[] = array(
            'type' => 'yearly',
            'name' => get_the_title($product_id) . ' (年間)',
            'price' => $yearly_price,
            'type_label' => '年間プラン',
            'stripe_price_id' => $stripe_price_id_yearly,
            'is_current' => ($current_type === 'yearly'),
            'is_available' => !empty($stripe_price_id_yearly)
        );
    }
    
    // デバッグ情報を追加
    wp_send_json_success(array(
        'plans' => $plans,
        'debug' => array(
            'product_id' => $product_id,
            'current_type' => $current_type,
            'one_time_price' => $one_time_price,
            'monthly_price' => $monthly_price,
            'yearly_price' => $yearly_price,
            'stripe_price_id_onetime' => $stripe_price_id_onetime,
            'stripe_price_id_monthly' => $stripe_price_id_monthly,
            'stripe_price_id_yearly' => $stripe_price_id_yearly,
        )
    ));
}

// プラン変更処理
add_action('wp_ajax_change_subscription_plan', 'change_subscription_plan_ajax');
function change_subscription_plan_ajax() {
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
    $license_id = intval($_POST['license_id']);
    $new_type = sanitize_text_field($_POST['new_type']);
    $stripe_price_id = sanitize_text_field($_POST['stripe_price_id']);
    $subscription_id = sanitize_text_field($_POST['subscription_id']);
    $current_type = sanitize_text_field($_POST['current_type']);
    
    if (empty($license_id) || empty($new_type) || empty($stripe_price_id)) {
        wp_send_json_error(array('message' => '必須パラメータが不足しています。'));
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'license_keys';
    
    // ライセンスが現在のユーザーのものか確認
    $license = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
        $license_id, $user_id
    ));
    
    if (!$license) {
        wp_send_json_error(array('message' => 'ライセンスが見つからないか、権限がありません。'));
        return;
    }
    
    if ($license->status !== 'active') {
        wp_send_json_error(array('message' => 'このライセンスは現在無効です。'));
        return;
    }
    
    try {
        $stripe_keys = get_stripe_keys();
        
        if (empty($stripe_keys['secret_key'])) {
            wp_send_json_error(array('message' => 'Stripe APIキーが設定されていません。'));
            return;
        }
        
        // 現在のタイプがサブスクリプション（monthly or yearly）で、新しいタイプもサブスクリプションの場合
        $is_current_subscription = in_array($current_type, array('monthly', 'yearly'));
        $is_new_subscription = in_array($new_type, array('monthly', 'yearly'));
        
        // ケース1: サブスクリプション → サブスクリプション (プラン変更)
        if ($is_current_subscription && $is_new_subscription) {
            if (empty($subscription_id)) {
                wp_send_json_error(array('message' => 'サブスクリプションIDが必要です。'));
                return;
            }
            
            // まず現在のサブスクリプション情報を取得
            $ch_get = curl_init();
            curl_setopt($ch_get, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/{$subscription_id}");
            curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_get, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $stripe_keys['secret_key']
            ));
            
            $get_response = curl_exec($ch_get);
            $get_http_code = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
            curl_close($ch_get);
            
            if ($get_http_code !== 200) {
                wp_send_json_error(array('message' => 'サブスクリプション情報の取得に失敗しました。'));
                return;
            }
            
            $subscription_data = json_decode($get_response, true);
            
            // サブスクリプションアイテムIDを取得
            if (empty($subscription_data['items']['data'][0]['id'])) {
                wp_send_json_error(array('message' => 'サブスクリプションアイテムが見つかりません。'));
                return;
            }
            
            $subscription_item_id = $subscription_data['items']['data'][0]['id'];
            
            // サブスクリプションアイテムIDを指定して更新
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/{$subscription_id}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
                'items[0][id]' => $subscription_item_id,
                'items[0][price]' => $stripe_price_id,
                'proration_behavior' => 'always_invoice'
            )));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $stripe_keys['secret_key'],
                'Content-Type: application/x-www-form-urlencoded'
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            error_log('Stripe Update Subscription Response: ' . $response);
            error_log('HTTP Code: ' . $http_code);
            
            if ($curl_error) {
                wp_send_json_error(array('message' => 'cURLエラー: ' . $curl_error));
                return;
            }
            
            if ($http_code !== 200) {
                $error_data = json_decode($response, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Stripe APIエラー';
                wp_send_json_error(array('message' => 'プラン変更に失敗しました: ' . $error_message));
                return;
            }
            
            // 更新後のサブスクリプション情報を取得
            $updated_subscription = json_decode($response, true);
            
            // 更新後、再度サブスクリプション情報を取得して最新のcurrent_period_endを取得
            $ch_refresh = curl_init();
            curl_setopt($ch_refresh, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/{$subscription_id}");
            curl_setopt($ch_refresh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_refresh, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $stripe_keys['secret_key']
            ));
            
            $refresh_response = curl_exec($ch_refresh);
            $refresh_http_code = curl_getinfo($ch_refresh, CURLINFO_HTTP_CODE);
            curl_close($ch_refresh);
            
            if ($refresh_http_code === 200) {
                $refreshed_subscription = json_decode($refresh_response, true);
            } else {
                // リフレッシュに失敗した場合は元のレスポンスを使用
                $refreshed_subscription = $updated_subscription;
            }
            
            // 新しい請求期間終了日を取得（リフレッシュされたデータから）
            $new_expires_at = null;
            
            // まずトップレベルのcurrent_period_endを確認
            if (!empty($refreshed_subscription['current_period_end'])) {
                $new_expires_at = date('Y-m-d H:i:s', $refreshed_subscription['current_period_end']);
            }
            // なければitemsの中のperiodを確認
            elseif (!empty($refreshed_subscription['items']['data'][0]['current_period_end'])) {
                $new_expires_at = date('Y-m-d H:i:s', $refreshed_subscription['items']['data'][0]['current_period_end']);
            }
            
            // データベースのpurchase_typeとexpires_atを更新
            $update_data = array('purchase_type' => $new_type);
            $update_format = array('%s');
            
            if ($new_expires_at) {
                $update_data['expires_at'] = $new_expires_at;
                $update_format[] = '%s';
            }
            
            $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $license_id),
                $update_format,
                array('%d')
            );
            
            // プラン変更通知メールを送信
            $product_name = get_the_title($license->product_id);
            $type_labels = array(
                'monthly' => '月額プラン',
                'yearly' => '年間プラン'
            );
            $old_label = $type_labels[$current_type] ?? $current_type;
            $new_label = $type_labels[$new_type] ?? $new_type;
            
            // メール設定を取得
            $email_settings = get_option('stripe_email_settings', array(
                'enable_emails' => 1,
                'from_email' => get_option('admin_email'),
                'from_name' => get_bloginfo('name'),
            ));
            
            if (!empty($email_settings['enable_emails'])) {
                $subject = '【' . get_bloginfo('name') . '】プラン変更完了のお知らせ';
                
                $message = "プランの変更が完了しました。\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━\n";
                $message .= "商品名: {$product_name}\n";
                $message .= "変更前: {$old_label}\n";
                $message .= "変更後: {$new_label}\n";
                $message .= "ライセンスキー: {$license->license_key}\n";
                if ($new_expires_at) {
                    $message .= "次回請求日: " . date('Y年m月d日', strtotime($new_expires_at)) . "\n";
                }
                $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $message .= "新しいプランは次回請求時から適用されます。\n";
                $message .= "引き続きご利用いただき、ありがとうございます。\n\n";
                $message .= "マイページ: " . home_url('/mypage/') . "\n";
                $message .= "\n────────────────────────\n";
                $message .= get_bloginfo('name') . "\n";
                $message .= home_url() . "\n";
                
                // メールヘッダー
                $headers = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>',
                );
                
                if (!empty($email_settings['reply_to'])) {
                    $headers[] = 'Reply-To: ' . $email_settings['reply_to'];
                }
                
                if (!empty($email_settings['return_path'])) {
                    $headers[] = 'Return-Path: ' . $email_settings['return_path'];
                }
                
                if (!empty($email_settings['bcc'])) {
                    // カンマ区切りで複数のBCCアドレスに対応
                    $bcc_addresses = array_map('trim', explode(',', $email_settings['bcc']));
                    $bcc_addresses = array_filter($bcc_addresses);
                    foreach ($bcc_addresses as $bcc) {
                        if (is_email(sanitize_email($bcc))) {
                            $headers[] = 'Bcc: ' . sanitize_email($bcc);
                        }
                    }
                }
                
                // HTMLメール設定
                add_filter('wp_mail_content_type', function($content_type) {
                    return 'text/html';
                }, 999);
                
                // 改行をHTMLに変換
                $html_message = nl2br(esc_html($message));
                
                wp_mail($license->user_email, $subject, $html_message, $headers);
                
                // フィルターをリセット
                remove_filter('wp_mail_content_type', function($content_type) {
                    return 'text/html';
                }, 999);
            }
            
            wp_send_json_success(array(
                'message' => $new_label . 'に変更しました。次回請求時に新しいプランが適用されます。'
            ));
            
        // ケース2: サブスクリプション → 買い切り
        } elseif ($is_current_subscription && $new_type === 'one_time') {
            wp_send_json_error(array(
                'message' => 'サブスクリプションから買い切りへの変更は、まず現在のサブスクリプションを解約してから、買い切りプランを購入してください。'
            ));
            return;
            
        // ケース3: 買い切り → サブスクリプション
        } elseif ($current_type === 'one_time' && $is_new_subscription) {
            wp_send_json_error(array(
                'message' => '買い切りからサブスクリプションへの変更は、新たにサブスクリプションプランを購入する必要があります。'
            ));
            return;
            
        // ケース4: 買い切り → 買い切り
        } elseif ($current_type === 'one_time' && $new_type === 'one_time') {
            wp_send_json_error(array(
                'message' => '買い切りプランは変更できません。新しいプランを購入してください。'
            ));
            return;
        }
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'エラーが発生しました: ' . $e->getMessage()));
    }
}