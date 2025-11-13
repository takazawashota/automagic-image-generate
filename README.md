# Automagic Image Generate

投稿ページや固定ページ、カスタム投稿タイプのサムネイル画像をPHPで自動生成するWordPressプラグインです。

## 機能

- 🎨 **自動画像生成**: 投稿保存時に自動でサムネイル画像を生成
- 📝 **全投稿タイプ対応**: 投稿、固定ページ、カスタム投稿タイプに対応
- 🎭 **複数のスタイル**: モダン、シンプル、グラデーション、パターン、ミニマルから選択可能
- 🔧 **手動生成**: 既存投稿に対して手動で画像を生成することも可能
- 🎨 **カスタマイズ可能**: 背景色、テキスト色、アクセント色、フォントサイズを自由に設定
- 💻 **完全ローカル**: 外部APIを使用せず、PHPのGDライブラリで画像を生成

## インストール

1. このプラグインフォルダを `/wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定」→「Automagic Image Generate」でデザイン設定をカスタマイズ

## 必要要件

- WordPress 5.0以上
- PHP 7.4以上
- PHP GD拡張機能（画像処理に必要）

## 設定方法

### 基本設定

1. WordPress管理画面で「設定」→「Automagic Image Generate」を開く
2. 「自動生成を有効化」にチェック
3. 対象の投稿タイプを選択（投稿、固定ページ、カスタム投稿タイプなど）

### デザイン設定

1. **背景色**: サムネイル画像の背景色を選択（デフォルト: #4A90E2）
2. **テキスト色**: タイトルテキストの色を選択（デフォルト: #FFFFFF）
3. **アクセント色**: 装飾要素の色を選択（デフォルト: #FFD700）
4. **フォントサイズ**: タイトルのフォントサイズを設定（20〜100）
5. **画像スタイル**: 以下から選択
   - **モダン**: グラデーション + 装飾円
   - **シンプル**: 単色背景
   - **グラデーション**: カラフルなグラデーション
   - **パターン**: 幾何学模様付き
   - **ミニマル**: 余白重視のシンプルデザイン

## 使い方

### 自動生成

設定で「自動生成を有効化」にチェックを入れると、以下の条件で自動的に画像が生成されます：

- 投稿を公開したとき
- サムネイル画像が未設定の投稿のみ
- 選択した投稿タイプのみ

### 手動生成

既存の投稿に対して画像を生成する場合：

1. 「設定」→「Automagic Image Generate」を開く
2. 「手動で画像を生成」セクションで投稿IDを入力
3. 「画像を生成」ボタンをクリック

## 技術仕様

- **画像生成**: PHP GDライブラリ
- **画像サイズ**: 1200x630ピクセル（OGP推奨サイズ）
- **画像形式**: PNG
- **日本語対応**: システムフォントを自動検出
- **対応WordPress**: 5.0以上
- **PHPバージョン**: 7.4以上推奨

## 日本語フォント対応

プラグインは以下のフォントを自動的に検出します：

- macOS: ヒラギノ角ゴシック
- Linux: Takao Gothic, Noto Sans CJK
- カスタムフォント: `fonts/NotoSansJP-Regular.ttf`（プラグインディレクトリに配置可能）

フォントが見つからない場合は、GD内蔵フォントにフォールバックします。

## トラブルシューティング

### 画像が生成されない場合

1. PHP GD拡張機能がインストールされているか確認
   ```bash
   php -m | grep gd
   ```
2. WordPressのエラーログを確認（`wp-content/debug.log`）
3. アップロードディレクトリの書き込み権限を確認
4. 既にサムネイル画像が設定されている投稿は、重複して作成されません

### 日本語が文字化けする場合

**原因**: システムに日本語フォントがインストールされていない

**解決方法**:

1. **Google Fontsから日本語フォントをダウンロード**
   - [Noto Sans JP](https://fonts.google.com/noto/specimen/Noto+Sans+JP) にアクセス
   - 「Download family」をクリック
   - ダウンロードしたZIPファイルを解凍

2. **フォントファイルをプラグインディレクトリに配置**
   ```
   wp-content/plugins/automagic-image-generate/fonts/NotoSansJP-Regular.ttf
   ```

3. **WordPress管理画面で確認**
   - 「設定」→「Automagic Image Generate」を開く
   - 「システム状態」で「✓ 日本語フォントが見つかりました」と表示されることを確認

4. **サーバーにフォントをインストール（オプション）**
   
   macOS:
   ```bash
   # Homebrewでフォントをインストール
   brew tap homebrew/cask-fonts
   brew install font-noto-sans-cjk-jp
   ```
   
   Linux:
   ```bash
   # Ubuntu/Debianの場合
   sudo apt-get install fonts-noto-cjk
   
   # CentOS/RHELの場合
   sudo yum install google-noto-sans-cjk-jp-fonts
   ```

5. **対応しているフォントパス**
   プラグインは以下のパスから自動的にフォントを検索します:
   - `/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc` (macOS)
   - `/System/Library/Fonts/Hiragino Sans GB.ttc` (macOS)
   - `/usr/share/fonts/truetype/takao-gothic/TakaoPGothic.ttf` (Linux)
   - `/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc` (Linux)
   - `plugins/automagic-image-generate/fonts/NotoSansJP-Regular.ttf` (カスタム)

### よくある質問

**Q: 生成にはどのくらい時間がかかりますか？**
A: 通常1〜2秒で完了します。サーバーのスペックに依存します。

**Q: 料金はかかりますか？**
A: 完全無料です。外部APIを使用しないため、追加料金は一切発生しません。

**Q: 既存の投稿の画像を一括生成できますか？**
A: 現在は手動で1つずつ生成する必要があります。一括処理機能は今後のバージョンで追加予定です。

**Q: 生成した画像の著作権は？**
A: 生成された画像の権利は、サイト運営者に帰属します。

**Q: カスタムフォントを使用できますか？**
A: はい、TrueTypeフォント（.ttf）を`wp-content/plugins/automagic-image-generate/fonts/`に配置することで使用できます。

## カスタマイズ例

### プログラムでスタイルを変更

```php
add_filter('amig_image_settings', function($settings) {
    $settings['bg_color'] = '#2C3E50';
    $settings['text_color'] = '#ECF0F1';
    $settings['accent_color'] = '#E74C3C';
    return $settings;
});
```

## 注意事項

- 画像生成にはサーバーのメモリを使用します
- 大量の投稿を一度に公開する際は、サーバー負荷に注意してください
- 生成された画像は投稿のタイトルに基づいて作成されます
- 長いタイトルは自動的に折り返されます（最大3行）

## ライセンス

GPL v2 or later

## サポート

問題が発生した場合は、GitHubのIssueまたはWordPress.orgのサポートフォーラムでお知らせください。

## 更新履歴

### 1.0.0 (2025-11-09)
- 初回リリース
- PHP GDライブラリによる画像生成機能
- 5種類の画像スタイル対応
- 色とフォントのカスタマイズ機能
- 日本語フォント自動検出
- 手動生成機能
