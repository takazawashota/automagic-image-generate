# WordPress統合型ライセンス管理システム - 本番向け仕様

## システム概要

WordPressの既存機能を最大限活用し、管理コストを最小化したライセンス管理システム。

## アーキテクチャ

```
WordPress サイト (ライセンス管理サーバー)
├── カスタム投稿タイプ「Licenses」
├── カスタムユーザーロール「License Customer」
├── REST API エンドポイント
└── 管理画面 (WordPress標準UI)

プラグイン (クライアント)
└── ライセンス認証 → REST API経由で照会
```

## 推奨仕様

### 1. データ構造

#### カスタム投稿タイプ: `amig_license`

```php
投稿タイトル: ライセンスキー (例: AMIG-XXXX-XXXX-XXXX)
投稿ステータス: 
  - publish (有効)
  - draft (無効)
  - trash (削除済み)

カスタムフィールド (post_meta):
  - _license_customer_id (購入者のWordPressユーザーID)
  - _license_max_sites (最大サイト数)
  - _license_expires (有効期限 Y-m-d)
  - _license_product (商品タイプ: single/unlimited/lifetime)
  - _license_activations (JSON: アクティベーション情報)
    例: [
      {
        "site_url": "https://example.com",
        "activated_at": "2025-11-13 10:00:00",
        "last_check": "2025-11-13 15:30:00",
        "ip": "123.45.67.89"
      }
    ]
```

#### カスタムユーザーロール: `license_customer`

```php
権限:
  - read (基本権限のみ)
  - view_own_licenses (自分のライセンス閲覧)

メタデータ (user_meta):
  - billing_email
  - billing_name
  - purchase_history (購入履歴)
```

### 2. REST APIエンドポイント

#### 認証: WordPress Application Password

WordPressの「アプリケーションパスワード」機能を使用してREST API認証。

#### エンドポイント

```
POST /wp-json/amig/v1/license/verify
POST /wp-json/amig/v1/license/activate
POST /wp-json/amig/v1/license/deactivate
GET  /wp-json/amig/v1/license/info
```

### 3. セキュリティ

- **認証**: nonce + license_key の組み合わせ
- **レート制限**: Transient APIで実装
- **ログ**: WordPressログテーブル活用
- **暗号化**: wp_hash()でキー生成

### 4. 管理画面

WordPress標準UIを活用:
- **ライセンス一覧**: 投稿一覧画面
- **カスタムカラム**: 有効期限、使用サイト数、顧客
- **メタボックス**: アクティベーション情報表示
- **クイック編集**: ステータス変更、期限延長

### 5. 通知機能

- **メール**: wp_mail()で期限切れ通知
- **管理画面通知**: Admin Noticesで警告表示

## メリット

### WordPress統合のメリット

1. **ゼロから構築不要**
   - ユーザー管理: WordPress標準
   - 認証: Application Password
   - データベース: wp_posts, wp_postmeta

2. **セキュリティ**
   - WordPress更新で自動強化
   - 既存のセキュリティプラグイン活用
   - HTTPS強制、ログイン保護

3. **管理コスト削減**
   - 慣れたWordPress管理画面
   - バックアップ: 既存プラグイン使用
   - 多言語化: 既存翻訳プラグイン

4. **拡張性**
   - WooCommerce連携可能
   - Easy Digital Downloads連携
   - メンバーシップサイト統合

5. **運用効率**
   - CSV一括インポート/エクスポート
   - 検索・フィルタリング機能
   - 一括編集機能

## 実装例

### プラグイン構成

```
amig-license-server/
├── amig-license-server.php (メインファイル)
├── includes/
│   ├── class-license-post-type.php (カスタム投稿タイプ)
│   ├── class-license-api.php (REST API)
│   ├── class-license-admin.php (管理画面)
│   └── class-license-validator.php (検証ロジック)
├── admin/
│   ├── metaboxes.php
│   └── admin-columns.php
└── templates/
    └── email-expiration-notice.php
```

### データフロー

```
1. 顧客購入 (WooCommerce等)
   ↓
2. ライセンスキー自動生成
   ↓
3. カスタム投稿作成 (amig_license)
   ↓
4. 購入者にメール送信
   ↓
5. 顧客がWordPress管理画面でアクティベート
   OR
   プラグインから直接API経由でアクティベート
```

## WooCommerce連携 (推奨)

### 商品設定

```php
商品タイプ: シンプル商品 / サブスクリプション

商品メタデータ:
  - _amig_license_type (single/3sites/unlimited)
  - _amig_license_duration (365日 / lifetime)
  - _amig_auto_generate_license (自動生成: yes/no)
```

### 購入完了時の処理

```php
add_action('woocommerce_payment_complete', 'amig_generate_license_on_purchase');

function amig_generate_license_on_purchase($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        
        if (get_post_meta($product_id, '_amig_auto_generate_license', true) === 'yes') {
            $license_key = amig_generate_license_key();
            $license_type = get_post_meta($product_id, '_amig_license_type', true);
            $duration = get_post_meta($product_id, '_amig_license_duration', true);
            
            // ライセンス投稿作成
            $license_id = wp_insert_post([
                'post_type' => 'amig_license',
                'post_title' => $license_key,
                'post_status' => 'publish',
                'post_author' => $order->get_customer_id()
            ]);
            
            // メタデータ保存
            update_post_meta($license_id, '_license_customer_id', $order->get_customer_id());
            update_post_meta($license_id, '_license_product', $license_type);
            update_post_meta($license_id, '_license_max_sites', amig_get_max_sites($license_type));
            update_post_meta($license_id, '_license_expires', amig_calculate_expiry($duration));
            update_post_meta($license_id, '_license_order_id', $order_id);
            
            // 注文メタに保存
            $order->add_meta_data('_amig_license_key', $license_key);
            $order->save();
            
            // メール送信
            amig_send_license_email($order->get_billing_email(), $license_key);
        }
    }
}
```

## データベース比較

### カスタム投稿タイプ方式 (推奨)

**メリット:**
- WordPress標準機能で完結
- プラグイン削除時にデータも削除可能
- リビジョン、トラッシュ機能が使える
- 既存プラグイン(検索、CSV等)が使える
- マルチサイト対応が容易

**デメリット:**
- 大量データ(10万件以上)では遅い可能性
- 複雑なクエリは重い

**推奨規模:** ~10,000ライセンス

### カスタムテーブル方式

**メリット:**
- 大量データに強い
- 複雑なクエリが高速
- カスタムインデックス

**デメリット:**
- 実装コスト高
- WordPress標準機能が使えない
- マイグレーション/バックアップが複雑

**推奨規模:** 10,000+ライセンス

## 推奨構成 (段階的実装)

### フェーズ1: MVP (最小構成)

```
✓ カスタム投稿タイプ
✓ REST API (verify/activate/deactivate)
✓ シンプルな管理画面
✓ 手動ライセンス発行
```

**所要時間:** 2-3日
**適用規模:** ~100ライセンス

### フェーズ2: 自動化

```
✓ WooCommerce連携
✓ 自動ライセンス生成
✓ メール通知
✓ 期限切れアラート
```

**所要時間:** 3-4日
**適用規模:** ~1,000ライセンス

### フェーズ3: 最適化

```
✓ キャッシュ戦略
✓ レート制限
✓ 詳細ログ
✓ ダッシュボード統計
```

**所要時間:** 2-3日
**適用規模:** ~5,000ライセンス

### フェーズ4: スケール (必要に応じて)

```
✓ カスタムテーブル移行
✓ インデックス最適化
✓ 外部API連携
✓ サブスクリプション管理
```

**所要時間:** 5-7日
**適用規模:** 10,000+ライセンス

## セキュリティベストプラクティス

### 1. ライセンスキー生成

```php
function amig_generate_license_key() {
    $prefix = 'AMIG';
    $parts = [];
    
    for ($i = 0; $i < 3; $i++) {
        $parts[] = strtoupper(substr(wp_generate_password(4, false), 0, 4));
    }
    
    // チェックサム追加
    $key_base = implode('-', $parts);
    $checksum = substr(md5($key_base . wp_salt()), 0, 4);
    
    return $prefix . '-' . $key_base . '-' . strtoupper($checksum);
}
```

### 2. API認証

```php
function amig_verify_api_request($license_key) {
    // レート制限チェック
    $transient_key = 'amig_rate_limit_' . md5($_SERVER['REMOTE_ADDR']);
    $requests = get_transient($transient_key) ?: 0;
    
    if ($requests > 60) { // 1時間に60回まで
        return new WP_Error('rate_limit', 'リクエスト制限を超えています');
    }
    
    set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
    
    // ライセンスキー検証
    $license = get_page_by_title($license_key, OBJECT, 'amig_license');
    
    if (!$license || $license->post_status !== 'publish') {
        return new WP_Error('invalid_license', '無効なライセンスキーです');
    }
    
    return $license;
}
```

### 3. データ暗号化

```php
function amig_encrypt_activation_data($data) {
    return base64_encode(json_encode($data));
}

function amig_decrypt_activation_data($encrypted) {
    return json_decode(base64_decode($encrypted), true);
}
```

## 運用コスト比較

### カスタムシステム (PHP + MySQL)
- **初期構築**: 10-15日
- **月額運用**: サーバー費用 + 保守
- **スキル要件**: PHP, MySQL, セキュリティ知識

### WordPress統合システム (推奨)
- **初期構築**: 3-5日
- **月額運用**: WordPress運用費のみ
- **スキル要件**: WordPress基本知識のみ

**コスト削減**: 約60-70%

## まとめ: 推奨仕様

```
✅ カスタム投稿タイプでライセンス管理
✅ WordPress REST API for 認証
✅ WooCommerce連携 (販売自動化)
✅ Application Password認証
✅ wp_posts/wp_postmeta活用
✅ 段階的実装 (MVP → 自動化 → 最適化)
✅ 既存WordPressサイトに統合可能
```

**次のステップ:**
1. WordPressサイトにライセンスサーバープラグイン実装
2. カスタム投稿タイプ「amig_license」作成
3. REST APIエンドポイント実装
4. クライアントプラグインの認証URL変更

この方式なら、既存のWordPressスキルで管理でき、将来的な拡張も容易です。
