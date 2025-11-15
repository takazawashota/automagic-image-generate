# Universal Plugin Updater - 使用ガイド

このファイル（universal-plugin-updater.php）は汎用的なWordPressプラグインアップデート機能を提供します。

## 特徴

- Plugin Update Checkerライブラリを使用
- ライセンス管理機能付き
- 設定可能な定数により複数のプラグインで使い回し可能
- 自動アップデート通知とダウンロード機能

## セットアップ方法

### 1. 必要な定数を定義

プラグインのメインファイルで、以下の定数を定義してからこのファイルを読み込みます：

```php
// プラグイン固有の設定
define('PUM_PLUGIN_PREFIX', 'your_prefix');           // 例: 'amig'
define('PUM_PLUGIN_NAME', 'Your Plugin Name');        // 例: 'Automagic Image Generate'  
define('PUM_PLUGIN_SLUG', 'your-plugin-slug');        // 例: 'automagic-image-generate'
define('PUM_PLUGIN_FILE', __FILE__);                  // メインプラグインファイルのパス

// サーバー設定
define('PUM_LICENSE_SERVER_URL', 'https://your-server.com/wp-json/your-namespace/v1/license/verify');
define('PUM_UPDATE_SERVER_URL', 'https://your-server.com/plugins/your-plugin/update-info.json');

// オプション（定義しない場合は自動生成）
define('PUM_LICENSE_PAGE_SLUG', 'your-prefix-license'); // 例: 'amig-license'

// アップデーターを読み込み
require_once plugin_dir_path(__FILE__) . 'universal-plugin-updater.php';
```

### 2. ディレクトリ構造

```
your-plugin/
├── your-plugin.php (メインファイル)
├── universal-plugin-updater.php (このファイル)
└── lib/
    └── plugin-update-checker/ (Plugin Update Checkerライブラリ)
```

## 自動作成されるオプション

定数を基に以下のWordPressオプションが自動作成されます：

- `{PREFIX}_license_key` - ライセンスキー
- `{PREFIX}_license_status` - ライセンス状態
- `{PREFIX}_license_expires` - ライセンス有効期限
- `{PREFIX}_license_last_check` - 最終確認時刻

## 管理画面

- **場所**: 設定 > ライセンス
- **機能**: ライセンス有効化、無効化、状態確認
- **通知**: アップデート利用可能時の通知

## 使用例 (Automagic Image Generate)

```php
// automagic-image-generate.php
define('PUM_PLUGIN_PREFIX', 'amig');
define('PUM_PLUGIN_NAME', 'Automagic Image Generate');
define('PUM_PLUGIN_SLUG', 'automagic-image-generate');
define('PUM_PLUGIN_FILE', __FILE__);
define('PUM_LICENSE_SERVER_URL', 'https://sokulabo.com/products/wp-json/sokulabo/v1/license/verify');
define('PUM_UPDATE_SERVER_URL', 'https://sokulabo.com/products/plugins/automagic-image-generate/update-info.json');

require_once plugin_dir_path(__FILE__) . 'universal-plugin-updater.php';
```

## サーバー側要件

### 1. ライセンス認証API

`PUM_LICENSE_SERVER_URL` で指定したエンドポイントで以下のアクションを処理：

- `activate` - ライセンス有効化
- `deactivate` - ライセンス無効化  
- `check` - ライセンス状態確認

### 2. アップデート情報JSON

`PUM_UPDATE_SERVER_URL` で指定したURLでPlugin Update Checker形式のJSONを提供

## 後方互換性

既存のコードとの互換性のため、`Automagic_Image_Generate_Updater`クラスでも引き続きアクセス可能です。

## カスタマイズ

必要に応じて以下をカスタマイズできます：

- ライセンス画面の見た目
- エラーメッセージ
- 通知の表示条件
- アップデートチェックの頻度

## トラブルシューティング

1. **定数が定義されていない**: デフォルト値が使用されますが、プラグイン固有の値を定義することを推奨
2. **ライブラリが見つからない**: `lib/plugin-update-checker/`ディレクトリが存在することを確認
3. **ライセンス認証エラー**: サーバー側のAPIエンドポイントが正常に動作することを確認