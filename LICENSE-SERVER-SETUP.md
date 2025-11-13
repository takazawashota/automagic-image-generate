# Automagic Image Generate - ライセンス管理システム

## サーバー設定

### 1. ファイルのアップロード

以下のファイルをサーバーにアップロードしてください:

```
https://tzweb.noor.jp/plugins/automagic-image-generate/
├── info.json           # プラグイン情報（既存）
├── verify.php          # ライセンス認証API
└── licenses.json       # ライセンスキーデータベース
```

### 2. ファイルの配置

1. `verify.php` をサーバーにアップロード
2. `licenses.json` を同じディレクトリにアップロード
3. `licenses.json` に書き込み権限を付与（パーミッション: 644 または 666）

### 3. .htaccess設定（Apache使用時）

`licenses.json` への直接アクセスを禁止するため、以下を `.htaccess` に追加:

```apache
# licenses.jsonへの直接アクセスを禁止
<Files "licenses.json">
    Order allow,deny
    Deny from all
</Files>
```

### 4. Nginx設定（Nginx使用時）

```nginx
location /plugins/automagic-image-generate/licenses.json {
    deny all;
}
```

## ライセンスキーの管理

### licenses.json の構造

```json
{
  "licenses": [
    {
      "key": "XXXX-XXXX-XXXX-XXXX",
      "status": "active",
      "expires": "2026-12-31",
      "max_sites": 1,
      "sites": [],
      "note": "説明文"
    }
  ]
}
```

### フィールド説明

- `key`: ライセンスキー（ユニーク）
- `status`: ステータス（`active` または `inactive`）
- `expires`: 有効期限（YYYY-MM-DD形式）
- `max_sites`: 使用可能サイト数（999で実質無制限）
- `sites`: 登録済みサイトURL配列（自動更新）
- `note`: 管理用メモ

### 検証用ライセンスキー

デフォルトで以下のテストキーが含まれています:

| ライセンスキー | 用途 | 有効期限 | サイト数 |
|---|---|---|---|
| `DEMO-1234-5678-ABCD` | デモ用 | 2026-12-31 | 無制限 |
| `TEST-0001-0001-0001` | テスト1 | 2025-12-31 | 1サイト |
| `TEST-0002-0002-0002` | テスト2 | 2026-06-30 | 3サイト |
| `TEST-EXPIRED-0003` | 期限切れテスト | 2024-12-31 | 1サイト |
| `TEST-INACTIVE-0004` | 無効化テスト | 2026-12-31 | 1サイト |

### 新しいライセンスの追加

`licenses.json` に新しいオブジェクトを追加:

```json
{
  "key": "PROD-XXXX-XXXX-XXXX",
  "status": "active",
  "expires": "2026-12-31",
  "max_sites": 1,
  "sites": [],
  "note": "本番用ライセンス"
}
```

### ライセンスの無効化

`status` を `inactive` に変更:

```json
{
  "key": "XXXX-XXXX-XXXX-XXXX",
  "status": "inactive",
  ...
}
```

### サイト登録の解除

`sites` 配列からサイトURLを削除（手動）、またはWordPress管理画面から無効化。

## API エンドポイント

### POST /plugins/automagic-image-generate/verify.php

#### アクション: activate（有効化）

```
action: activate
license_key: ライセンスキー
site_url: サイトURL
```

#### アクション: deactivate（無効化）

```
action: deactivate
license_key: ライセンスキー
site_url: サイトURL
```

#### アクション: check（確認）

```
action: check
license_key: ライセンスキー
site_url: サイトURL
```

## トラブルシューティング

### 404 Not Found エラー

- `verify.php` ファイルが正しくアップロードされているか確認
- PHPが正しく動作しているか確認

### ライセンスファイルが見つからない

- `licenses.json` が `verify.php` と同じディレクトリにあるか確認
- パーミッションが正しいか確認（644 または 666）

### 書き込みエラー

- `licenses.json` に書き込み権限があるか確認
- サーバーのPHPユーザーが書き込み可能か確認

## セキュリティ

1. `licenses.json` への直接アクセスを禁止（.htaccess/Nginx設定）
2. ライセンスキーは推測困難な文字列を使用
3. 本番環境ではHTTPS必須
4. 定期的に `licenses.json` をバックアップ

## 今後の拡張

データベース管理への移行時:

1. MySQL/PostgreSQLテーブル作成
2. `verify.php` ファイル内のファイル読み書きをDB操作に置き換え
3. 管理画面の追加（ライセンス発行・管理UI）
4. ライセンス使用状況の分析機能
