# Automagic Image Generate - アップデート機能セットアップガイド

## 概要

このプラグインには自動アップデート機能が実装されています。ライセンスキー認証により、安全にアップデートを配信できます。

## ファイル構成

```
automagic-image-generate/
├── automagic-image-generate.php  # メインプラグインファイル
├── automagic-updater.php         # アップデート管理クラス
└── update-server-sample.php      # アップデートサーバーAPIサンプル
```

## アップデートサーバーのセットアップ

### 1. サーバーの準備

アップデート情報を配信するサーバーを用意します。

```
your-update-server.com/
└── api/
    └── update/
        ├── check       # アップデート情報取得
        ├── activate    # ライセンス有効化
        └── deactivate  # ライセンス無効化
```

### 2. データベースの作成

ライセンス情報を保存するデータベースを作成します。

```sql
CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(255) UNIQUE NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    expires DATE,
    max_activations INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE license_activations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(255),
    site_url VARCHAR(500),
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_key) REFERENCES licenses(license_key)
);
```

### 3. APIエンドポイントの実装

`update-server-sample.php` をベースに、本番環境用のAPIを実装します。

**重要な変更点:**
- データベース接続を実装
- セキュリティ対策を追加（HTTPS必須、レート制限など）
- ログ記録機能を実装
- エラーハンドリングを強化

### 4. プラグインZIPファイルの準備

新しいバージョンのプラグインをZIP形式で圧縮し、ダウンロード可能な場所に配置します。

```bash
cd /path/to/plugin/directory
zip -r automagic-image-generate-1.1.0.zip automagic-image-generate/
```

## プラグイン側の設定

### 1. アップデートサーバーURLの変更

`automagic-updater.php` の35行目を編集します：

```php
// 変更前
$this->update_server_url = 'https://your-update-server.com/api/update';

// 変更後（実際のサーバーURL）
$this->update_server_url = 'https://example.com/api/update';
```

### 2. ライセンスキーの生成

ライセンスキーを生成するPHPスクリプト例：

```php
function generate_license_key() {
    $segments = [];
    for ($i = 0; $i < 4; $i++) {
        $segments[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    }
    return implode('-', $segments);
}

// 使用例
echo generate_license_key(); // 出力例: A1B2-C3D4-E5F6-G7H8
```

## 使用方法

### ライセンスの有効化

1. WordPressダッシュボードにログイン
2. **設定 > Automagic Image Generate > ライセンス** に移動
3. ライセンスキーを入力
4. **ライセンスを有効化** ボタンをクリック

### アップデートの確認

#### 自動チェック
- WordPressが12時間ごとに自動的にアップデートをチェック

#### 手動チェック
1. **設定 > Automagic Image Generate > ライセンス** に移動
2. **今すぐアップデートを確認** ボタンをクリック

### アップデートのインストール

1. **ダッシュボード > 更新** に移動
2. プラグインのアップデート通知を確認
3. **今すぐ更新** をクリック

## セキュリティのベストプラクティス

### 1. HTTPS通信の必須化

```php
// アップデートサーバー側
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    http_response_code(403);
    die('HTTPS接続が必要です');
}
```

### 2. レート制限の実装

```php
// IPアドレスごとのリクエスト制限
function check_rate_limit($ip_address, $limit = 10, $period = 3600) {
    // Redis、Memcached、またはデータベースでカウント
    // $limit回/$period秒を超えたら403を返す
}
```

### 3. ライセンスキーの暗号化

```php
// データベース保存時に暗号化
$encrypted_key = openssl_encrypt(
    $license_key, 
    'AES-256-CBC', 
    $encryption_key, 
    0, 
    $iv
);
```

### 4. 署名検証

```php
// ダウンロードファイルの署名検証
function verify_package_signature($file_path, $signature) {
    $public_key = file_get_contents('public_key.pem');
    $data = file_get_contents($file_path);
    return openssl_verify($data, base64_decode($signature), $public_key, OPENSSL_ALGO_SHA256) === 1;
}
```

## トラブルシューティング

### アップデートが表示されない

1. ライセンスステータスを確認
2. サーバーログを確認
3. WordPressのデバッグモードを有効化：

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

4. 手動でキャッシュをクリア：

```php
delete_transient('amig_update_info');
```

### 通信エラー

- サーバーのファイアウォール設定を確認
- SSL証明書が有効か確認
- `wp_remote_get` / `wp_remote_post` の設定を確認

```php
// タイムアウトを延長
$response = wp_remote_get($url, array(
    'timeout' => 30,
    'sslverify' => true
));
```

## カスタマイズ例

### キャッシュ期間の変更

```php
// automagic-updater.php の31行目
private $cache_duration = 86400; // 24時間に変更
```

### 通知メールの送信

```php
// アップデート検出時にメールを送信
add_action('upgrader_process_complete', function($upgrader, $options) {
    if ($options['type'] === 'plugin' && 
        in_array('automagic-image-generate/automagic-image-generate.php', $options['plugins'])) {
        
        $admin_email = get_option('admin_email');
        wp_mail(
            $admin_email, 
            'プラグインがアップデートされました', 
            'Automagic Image Generateが最新バージョンに更新されました。'
        );
    }
}, 10, 2);
```

## ライセンスタイプ

### デモライセンス

テスト用のライセンスキー：`DEMO-1234-5678-ABCD`

- 有効期限: 2026-12-31
- 最大アクティベーション数: 1
- ステータス: active

### 本番ライセンス

実際の販売では、購入者ごとにユニークなライセンスキーを生成してください。

## サポート

問題が発生した場合は、以下の情報を含めてお問い合わせください：

- WordPressバージョン
- PHPバージョン
- プラグインバージョン
- エラーログ（`wp-content/debug.log`）
- サーバー環境（Apache/Nginx、OS）

---

**注意:** このアップデート機能は基本的な実装例です。本番環境では必ずセキュリティレビューを実施してください。
