# デバッグ情報

## エラーログの確認方法

500エラーが発生した場合、以下の場所でエラーログを確認してください：

### MAMPの場合
- **PHPエラーログ**: `/Applications/MAMP/logs/php_error.log`
- **Apacheエラーログ**: `/Applications/MAMP/logs/apache_error.log`

### WordPressデバッグログ
`wp-config.php`に以下を追加してWordPressのデバッグログを有効化：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

ログファイルの場所: `wp-content/debug.log`

## 確認すべきログ

一括生成実行時に以下のようなログが出力されます：

```
AMIG Bulk Generate: Start
POST data: Array(...)
AMIG Bulk Generate: Post type = post
AMIG Bulk Generate: Found 6 posts
AMIG Bulk Generate: Processing post ID: 123, Title: サンプル投稿
AMIG Bulk Generate: Generated thumbnail ID: 456
AMIG Bulk Generate: Success for post 123
```

エラーが発生した場合は、以下のような情報が記録されます：

```
Automagic Bulk Generate Error: [エラーメッセージ]
Stack trace: [スタックトレース]
```

## よくあるエラーと解決方法

### 1. メモリ不足エラー
```
Fatal error: Allowed memory size of XXX bytes exhausted
```
**解決方法**: `wp-config.php`に追加
```php
define('WP_MEMORY_LIMIT', '256M');
```

### 2. 実行時間超過エラー
```
Maximum execution time of 30 seconds exceeded
```
**解決方法**: `wp-config.php`に追加
```php
set_time_limit(300);
```

### 3. GDライブラリエラー
```
Call to undefined function imagecreatetruecolor()
```
**解決方法**: PHPのGD拡張を有効化

### 4. フォントファイルエラー
```
Could not find/open font
```
**解決方法**: 日本語フォントを`fonts/`ディレクトリに配置

## 現在のデバッグログ出力箇所

- `ajax_bulk_generate()`: 一括生成処理のすべてのステップ
- `ajax_bulk_delete()`: 一括削除処理のすべてのステップ
- `generate_thumbnail_image()`: 画像生成処理
- JavaScript: ブラウザのコンソールに詳細なログ

## トラブルシューティング手順

1. ブラウザの開発者ツール（F12）を開く
2. コンソールタブでJavaScriptのエラーを確認
3. ネットワークタブでAJAXリクエストの詳細を確認
4. サーバーのエラーログを確認
5. WordPressのデバッグログを確認
