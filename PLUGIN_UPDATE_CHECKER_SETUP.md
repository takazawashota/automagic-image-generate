# Automagic Image Generate - Plugin Update Checker セットアップガイド

## 概要

このプラグインは **[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)** ライブラリを使用して、自動アップデート機能を提供します。

## 必要なファイル

```
automagic-image-generate/
├── automagic-image-generate.php  # メインプラグインファイル
├── automagic-updater.php         # アップデート管理クラス
├── info.json                     # メタデータサンプル
├── lib/
│   └── plugin-update-checker/    # Plugin Update Checkerライブラリ
└── update-server-sample.php      # サーバー側APIサンプル
```

## Plugin Update Checkerのインストール

ライブラリは既に `lib/plugin-update-checker/` ディレクトリに含まれています。

### 手動でインストールする場合

```bash
cd /path/to/plugin
mkdir -p lib
cd lib
curl -L https://github.com/YahnisElsts/plugin-update-checker/archive/refs/heads/master.zip -o puc.zip
unzip puc.zip
mv plugin-update-checker-master plugin-update-checker
rm puc.zip
```

### Composerでインストールする場合

```bash
composer require yahnis-elsts/plugin-update-checker
```

## サーバーのセットアップ

### オプション1: 独自サーバー

#### 1. メタデータファイルの配置

`info.json` をWebサーバーに配置します。

```
https://tzweb.noor.jp/plugins/automagic-image-generate/info.json
```

**info.json の内容:**
```json
{
    "name": "Automagic Image Generate",
    "version": "1.1.0",
    "download_url": "https://tzweb.noor.jp/downloads/automagic-image-generate-1.1.0.zip",
    "requires": "5.0",
    "tested": "6.4",
    "requires_php": "7.4",
    "sections": {
        "description": "プラグインの説明",
        "changelog": "<h3>1.1.0</h3><ul><li>新機能追加</li></ul>"
    }
}
```

#### 2. ライセンス検証APIの配置

`update-server-sample.php` をサーバーに配置し、データベース接続を実装します。

```
https://tzweb.noor.jp/plugins/automagic-image-generate/verify
```

#### 3. プラグイン側の設定

`automagic-updater.php` の46行目を編集:

```php
$this->metadata_url = 'https://tzweb.noor.jp/plugins/automagic-image-generate/info.json';
```

### オプション2: GitHub Releases

GitHubリポジトリを使用する場合:

```php
// automagic-updater.php の70-76行を以下に変更:
$this->update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/username/repository/',
    $plugin_file,
    'automagic-image-generate'
);
```

**必要な設定:**
1. GitHubリポジトリを作成
2. Releasesでバージョンをタグ付け（例: v1.1.0）
3. ZIPファイルを添付

## ライセンス管理

### ライセンスキーの生成

```php
function generate_license_key() {
    $segments = [];
    for ($i = 0; $i < 4; $i++) {
        $segments[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    }
    return implode('-', $segments);
}

echo generate_license_key(); // 例: A1B2-C3D4-E5F6-G7H8
```

### データベース設計

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

## 使用方法

### ライセンスの有効化

1. WordPressダッシュボード → **設定 > ライセンス**
2. ライセンスキーを入力
3. **ライセンスを有効化** をクリック

### アップデートの確認

#### 自動チェック
Plugin Update Checkerが12時間ごとに自動チェック

#### 手動チェック
ライセンス設定ページの **今すぐアップデートを確認** ボタン

### アップデートのインストール

1. **ダッシュボード > 更新**
2. プラグインのアップデート通知を確認
3. **今すぐ更新**

## API仕様

### GET /info.json

**リクエスト:**
```
GET /info.json?license_key=XXX&site_url=https://example.com
```

**レスポンス:**
```json
{
    "name": "Automagic Image Generate",
    "version": "1.1.0",
    "download_url": "https://...",
    "requires": "5.0",
    "tested": "6.4",
    "sections": {...}
}
```

### POST /verify

**ライセンス有効化:**
```
POST /verify
{
    "action": "activate",
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://example.com"
}
```

**レスポンス:**
```json
{
    "success": true,
    "message": "ライセンスが有効化されました",
    "expires": "2026-12-31"
}
```

## セキュリティ

### 1. HTTPS必須

```php
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    http_response_code(403);
    die('HTTPS接続が必要です');
}
```

### 2. レート制限

```php
// IPアドレスごとのリクエスト制限
$redis = new Redis();
$redis->connect('127.0.0.1');
$key = 'rate_limit:' . $_SERVER['REMOTE_ADDR'];
$requests = $redis->incr($key);
$redis->expire($key, 3600);

if ($requests > 100) {
    http_response_code(429);
    die('レート制限を超えました');
}
```

### 3. 署名検証

```php
// ZIPファイルに署名を追加
$signature = hash_hmac('sha256', file_get_contents('plugin.zip'), SECRET_KEY);

// info.jsonに署名を含める
{
    "download_url": "...",
    "download_signature": "abc123..."
}
```

## トラブルシューティング

### Plugin Update Checkerが読み込まれない

```bash
# ライブラリの存在確認
ls -la lib/plugin-update-checker/plugin-update-checker.php
```

### アップデートが表示されない

1. ライセンスステータスを確認
2. キャッシュをクリア:
```php
delete_site_transient('update_plugins');
```

### デバッグモードを有効化

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// automagic-updater.php に追加
$this->update_checker->addFilter('request_info_result', function($info) {
    error_log('Update Info: ' . print_r($info, true));
    return $info;
});
```

## カスタマイズ例

### チェック間隔の変更

```php
$this->update_checker->checkPeriod = 6; // 6時間ごと
```

### ベータ版のサポート

```php
// info.jsonに追加
{
    "version": "1.2.0-beta",
    "stability": "beta"
}

// プラグイン側
$this->update_checker->setBranch('beta');
```

### カスタムフィルター

```php
// ダウンロード前の処理
$this->update_checker->addFilter('pre_download', function($result, $url) {
    // ログ記録など
    error_log('Downloading from: ' . $url);
    return $result;
}, 10, 2);
```

## テスト用ライセンス

デモライセンスキー: **`DEMO-1234-5678-ABCD`**
- 有効期限: 2026-12-31
- 最大アクティベーション: 1

## 参考リンク

- [Plugin Update Checker GitHub](https://github.com/YahnisElsts/plugin-update-checker)
- [Plugin Update Checker Documentation](https://github.com/YahnisElsts/plugin-update-checker/blob/master/README.md)

---

**注意:** 本番環境では必ずHTTPSを使用し、適切なセキュリティ対策を実施してください。
