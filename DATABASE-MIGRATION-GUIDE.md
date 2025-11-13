# データベース移行ガイド

## 1. データベース設計

### テーブル構造

#### licenses テーブル
```sql
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    expires_at DATE NOT NULL,
    max_sites INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    note TEXT,
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### license_activations テーブル
```sql
CREATE TABLE license_activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_check_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_site (license_id, site_url),
    INDEX idx_license_id (license_id),
    INDEX idx_site_url (site_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### license_logs テーブル（オプション：監査用）
```sql
CREATE TABLE license_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    action ENUM('activate', 'deactivate', 'check') NOT NULL,
    site_url VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    result ENUM('success', 'failed') NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. 既存データの移行

### マイグレーションスクリプト (migrate-licenses.php)

```php
<?php
/**
 * licenses.json からデータベースへの移行スクリプト
 */

// データベース接続設定
$db_host = 'localhost';
$db_name = 'your_database';
$db_user = 'your_username';
$db_pass = 'your_password';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // licenses.json を読み込み
    $json_data = json_decode(file_get_contents('licenses.json'), true);
    
    if (!$json_data || !isset($json_data['licenses'])) {
        die("licenses.json の読み込みに失敗しました\n");
    }
    
    $pdo->beginTransaction();
    
    foreach ($json_data['licenses'] as $license) {
        // ライセンスを挿入
        $stmt = $pdo->prepare("
            INSERT INTO licenses (license_key, status, expires_at, max_sites, note)
            VALUES (:key, :status, :expires, :max_sites, :note)
        ");
        
        $stmt->execute([
            'key' => $license['key'],
            'status' => $license['status'],
            'expires' => $license['expires'],
            'max_sites' => $license['max_sites'],
            'note' => isset($license['note']) ? $license['note'] : null
        ]);
        
        $license_id = $pdo->lastInsertId();
        
        // サイト登録を挿入
        if (!empty($license['sites'])) {
            $stmt = $pdo->prepare("
                INSERT INTO license_activations (license_id, site_url)
                VALUES (:license_id, :site_url)
            ");
            
            foreach ($license['sites'] as $site_url) {
                $stmt->execute([
                    'license_id' => $license_id,
                    'site_url' => $site_url
                ]);
            }
        }
        
        echo "Migrated: {$license['key']}\n";
    }
    
    $pdo->commit();
    echo "\n移行完了！\n";
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    die("エラー: " . $e->getMessage() . "\n");
}
```

## 3. 新しい verify.php の実装

```php
<?php
/**
 * Automagic Image Generate - License Verification API (Database Version)
 */

header('Content-Type: application/json');

// データベース接続設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// データベース接続
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'データベース接続エラー'
            ]);
            exit;
        }
    }
    
    return $pdo;
}

// ログ記録
function log_action($pdo, $license_id, $action, $site_url, $result, $message = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO license_logs 
            (license_id, action, site_url, ip_address, user_agent, result, message)
            VALUES (:license_id, :action, :site_url, :ip, :ua, :result, :message)
        ");
        
        $stmt->execute([
            'license_id' => $license_id,
            'action' => $action,
            'site_url' => $site_url,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'result' => $result,
            'message' => $message
        ]);
    } catch (PDOException $e) {
        // ログエラーは無視（メイン処理には影響させない）
    }
}

// リクエストメソッドチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// パラメータ取得
$action = $_POST['action'] ?? '';
$license_key = trim($_POST['license_key'] ?? '');
$site_url = trim($_POST['site_url'] ?? '');

// パラメータ検証
if (empty($license_key)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ライセンスキーが指定されていません'
    ]);
    exit;
}

$pdo = get_db_connection();

// ライセンス情報取得
try {
    $stmt = $pdo->prepare("
        SELECT id, license_key, status, expires_at, max_sites
        FROM licenses
        WHERE license_key = :key
    ");
    $stmt->execute(['key' => $license_key]);
    $license = $stmt->fetch();
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー'
    ]);
    exit;
}

// アクション処理
switch ($action) {
    case 'activate':
        if (!$license) {
            echo json_encode([
                'success' => false,
                'message' => '無効なライセンスキーです'
            ]);
            exit;
        }
        
        // ステータス確認
        if ($license['status'] !== 'active') {
            log_action($pdo, $license['id'], 'activate', $site_url, 'failed', 'ライセンスが無効');
            echo json_encode([
                'success' => false,
                'message' => 'このライセンスは無効化されています'
            ]);
            exit;
        }
        
        // 有効期限確認
        $expires = strtotime($license['expires_at']);
        if ($expires && $expires < time()) {
            log_action($pdo, $license['id'], 'activate', $site_url, 'failed', '期限切れ');
            echo json_encode([
                'success' => false,
                'message' => 'このライセンスは期限切れです'
            ]);
            exit;
        }
        
        try {
            // 現在のサイト数を取得
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count, 
                       EXISTS(SELECT 1 FROM license_activations 
                              WHERE license_id = :id AND site_url = :url) as is_registered
                FROM license_activations
                WHERE license_id = :id
            ");
            $stmt->execute([
                'id' => $license['id'],
                'url' => $site_url
            ]);
            $activation_info = $stmt->fetch();
            
            $site_registered = (bool)$activation_info['is_registered'];
            $sites_count = (int)$activation_info['count'];
            
            // サイト数制限確認
            if (!$site_registered && $sites_count >= $license['max_sites']) {
                log_action($pdo, $license['id'], 'activate', $site_url, 'failed', 'サイト数上限');
                echo json_encode([
                    'success' => false,
                    'message' => 'ライセンスの使用可能サイト数を超えています（最大' . $license['max_sites'] . 'サイト）'
                ]);
                exit;
            }
            
            // サイトを登録（未登録の場合）
            if (!$site_registered) {
                $stmt = $pdo->prepare("
                    INSERT INTO license_activations 
                    (license_id, site_url, ip_address, user_agent)
                    VALUES (:license_id, :site_url, :ip, :ua)
                ");
                $stmt->execute([
                    'license_id' => $license['id'],
                    'site_url' => $site_url,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                $sites_count++;
            } else {
                // 既存の場合は最終確認日時を更新
                $stmt = $pdo->prepare("
                    UPDATE license_activations
                    SET last_check_at = NOW()
                    WHERE license_id = :id AND site_url = :url
                ");
                $stmt->execute([
                    'id' => $license['id'],
                    'url' => $site_url
                ]);
            }
            
            log_action($pdo, $license['id'], 'activate', $site_url, 'success');
            
            echo json_encode([
                'success' => true,
                'message' => 'ライセンスが有効化されました',
                'expires' => $license['expires_at'],
                'sites_used' => $sites_count,
                'sites_max' => $license['max_sites']
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'データベースエラー'
            ]);
        }
        break;
        
    case 'deactivate':
        if (!$license) {
            echo json_encode([
                'success' => true,
                'message' => 'ライセンスを無効化しました'
            ]);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                DELETE FROM license_activations
                WHERE license_id = :id AND site_url = :url
            ");
            $stmt->execute([
                'id' => $license['id'],
                'url' => $site_url
            ]);
            
            log_action($pdo, $license['id'], 'deactivate', $site_url, 'success');
            
            echo json_encode([
                'success' => true,
                'message' => 'ライセンスを無効化しました'
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'データベースエラー'
            ]);
        }
        break;
        
    case 'check':
        if (!$license) {
            echo json_encode([
                'success' => false,
                'message' => '無効なライセンスキーです'
            ]);
            exit;
        }
        
        // ステータス確認
        if ($license['status'] !== 'active') {
            echo json_encode([
                'success' => false,
                'message' => 'このライセンスは無効化されています'
            ]);
            exit;
        }
        
        // 有効期限確認
        $expires = strtotime($license['expires_at']);
        if ($expires && $expires < time()) {
            echo json_encode([
                'success' => false,
                'message' => 'このライセンスは期限切れです'
            ]);
            exit;
        }
        
        try {
            // サイトが登録されているか確認
            $stmt = $pdo->prepare("
                SELECT id FROM license_activations
                WHERE license_id = :id AND site_url = :url
            ");
            $stmt->execute([
                'id' => $license['id'],
                'url' => $site_url
            ]);
            $activation = $stmt->fetch();
            
            if (!$activation) {
                echo json_encode([
                    'success' => false,
                    'message' => 'このサイトでライセンスが有効化されていません'
                ]);
                exit;
            }
            
            // 最終確認日時を更新
            $stmt = $pdo->prepare("
                UPDATE license_activations
                SET last_check_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $activation['id']]);
            
            log_action($pdo, $license['id'], 'check', $site_url, 'success');
            
            echo json_encode([
                'success' => true,
                'message' => 'ライセンスは有効です',
                'expires' => $license['expires_at']
            ]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'データベースエラー'
            ]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '無効なアクションです'
        ]);
        break;
}
```

## 4. 管理画面の実装（オプション）

### admin.php - ライセンス管理画面

```php
<?php
/**
 * ライセンス管理画面
 * Basic認証などで保護することを推奨
 */

// Basic認証（設定例）
/*
$users = ['admin' => 'password123'];
if (!isset($_SERVER['PHP_AUTH_USER']) || 
    !isset($users[$_SERVER['PHP_AUTH_USER']]) ||
    $users[$_SERVER['PHP_AUTH_USER']] !== $_SERVER['PHP_AUTH_PW']) {
    header('WWW-Authenticate: Basic realm="License Admin"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Unauthorized');
}
*/

require_once 'verify.php'; // DB接続関数を利用

$pdo = get_db_connection();

// ライセンス一覧取得
$stmt = $pdo->query("
    SELECT 
        l.*,
        COUNT(la.id) as active_sites
    FROM licenses l
    LEFT JOIN license_activations la ON l.id = la.license_id
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$licenses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ライセンス管理</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; }
        .expired { color: orange; }
    </style>
</head>
<body>
    <h1>ライセンス管理</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ライセンスキー</th>
                <th>ステータス</th>
                <th>有効期限</th>
                <th>サイト数</th>
                <th>作成日</th>
                <th>備考</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($licenses as $license): ?>
            <tr>
                <td><?= htmlspecialchars($license['id']) ?></td>
                <td><code><?= htmlspecialchars($license['license_key']) ?></code></td>
                <td class="status-<?= htmlspecialchars($license['status']) ?>">
                    <?= htmlspecialchars($license['status']) ?>
                </td>
                <td class="<?= strtotime($license['expires_at']) < time() ? 'expired' : '' ?>">
                    <?= htmlspecialchars($license['expires_at']) ?>
                </td>
                <td>
                    <?= $license['active_sites'] ?> / <?= $license['max_sites'] ?>
                </td>
                <td><?= htmlspecialchars($license['created_at']) ?></td>
                <td><?= htmlspecialchars($license['note'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
```

## 5. 移行手順

### ステップ1: データベース準備
```bash
mysql -u username -p database_name < create_tables.sql
```

### ステップ2: データ移行
```bash
php migrate-licenses.php
```

### ステップ3: verify.php を置き換え
- 新しいDB版verify.phpをアップロード
- DB接続情報を設定

### ステップ4: 動作確認
- WordPressからライセンス認証をテスト
- ログテーブルで動作確認

### ステップ5: 旧ファイル削除
```bash
# バックアップ後
mv licenses.json licenses.json.backup
```

## 6. メリット

### パフォーマンス
- インデックスによる高速検索
- 同時アクセスに強い
- キャッシュ戦略が使える

### 機能拡張
- 複雑なクエリが可能
- 統計情報の取得が容易
- ライセンス使用パターンの分析

### 管理性
- GUIでの管理が可能
- バックアップ/リストアが容易
- スケーラビリティ

## 7. セキュリティ強化

### データベース
- 最小権限のユーザーを使用
- SSL/TLS接続を使用
- 定期的なバックアップ

### API
- レート制限の実装
- IPホワイトリスト（オプション）
- Basic認証またはAPIキー

### 監査
- license_logsテーブルで全操作を記録
- 不正アクセスの検出
- 定期的なログ分析
