<?php
/**
 * Automagic Image Generate - License Verification API
 * 
 * このファイルをサーバーの /plugins/automagic-image-generate/verify.php にアップロード
 * 例: https://tzweb.noor.jp/plugins/automagic-image-generate/verify.php
 */

header('Content-Type: application/json');

// ライセンスキーリストファイルのパス
$license_file = __DIR__ . '/licenses.json';

/**
 * ライセンスキーリストの構造例:
 * {
 *   "licenses": [
 *     {
 *       "key": "TEST-1234-5678-ABCD",
 *       "status": "active",
 *       "expires": "2026-12-31",
 *       "max_sites": 1,
 *       "sites": []
 *     },
 *     {
 *       "key": "DEMO-1234-5678-ABCD",
 *       "status": "active",
 *       "expires": "2026-12-31",
 *       "max_sites": 999,
 *       "sites": []
 *     }
 *   ]
 * }
 */

// リクエストメソッドチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method Not Allowed'
    ));
    exit;
}

// パラメータ取得
$action = isset($_POST['action']) ? $_POST['action'] : '';
$license_key = isset($_POST['license_key']) ? trim($_POST['license_key']) : '';
$site_url = isset($_POST['site_url']) ? trim($_POST['site_url']) : '';

// パラメータ検証
if (empty($license_key)) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'ライセンスキーが指定されていません'
    ));
    exit;
}

// ライセンスファイルの存在確認
if (!file_exists($license_file)) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'ライセンスファイルが見つかりません'
    ));
    exit;
}

// ライセンスデータ読み込み
$license_data = json_decode(file_get_contents($license_file), true);
if (!$license_data || !isset($license_data['licenses'])) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'ライセンスデータの読み込みに失敗しました'
    ));
    exit;
}

// ライセンスキー検索
$found_license = null;
$license_index = -1;
foreach ($license_data['licenses'] as $index => $license) {
    if ($license['key'] === $license_key) {
        $found_license = $license;
        $license_index = $index;
        break;
    }
}

// アクション処理
switch ($action) {
    case 'activate':
        if (!$found_license) {
            echo json_encode(array(
                'success' => false,
                'message' => '無効なライセンスキーです'
            ));
            exit;
        }
        
        // ステータス確認
        if ($found_license['status'] !== 'active') {
            echo json_encode(array(
                'success' => false,
                'message' => 'このライセンスは無効化されています'
            ));
            exit;
        }
        
        // 有効期限確認
        $expires = strtotime($found_license['expires']);
        if ($expires && $expires < time()) {
            echo json_encode(array(
                'success' => false,
                'message' => 'このライセンスは期限切れです'
            ));
            exit;
        }
        
        // サイト数制限確認
        $sites = isset($found_license['sites']) ? $found_license['sites'] : array();
        $max_sites = isset($found_license['max_sites']) ? (int)$found_license['max_sites'] : 1;
        
        // 既に登録済みかチェック
        $site_registered = in_array($site_url, $sites);
        
        if (!$site_registered && count($sites) >= $max_sites) {
            echo json_encode(array(
                'success' => false,
                'message' => 'ライセンスの使用可能サイト数を超えています（最大' . $max_sites . 'サイト）'
            ));
            exit;
        }
        
        // サイトを登録（未登録の場合）
        if (!$site_registered) {
            $license_data['licenses'][$license_index]['sites'][] = $site_url;
            file_put_contents($license_file, json_encode($license_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ライセンスが有効化されました',
            'expires' => $found_license['expires'],
            'sites_used' => count($sites) + ($site_registered ? 0 : 1),
            'sites_max' => $max_sites
        ));
        break;
        
    case 'deactivate':
        if (!$found_license) {
            echo json_encode(array(
                'success' => true,
                'message' => 'ライセンスを無効化しました'
            ));
            exit;
        }
        
        // サイトリストから削除
        $sites = isset($found_license['sites']) ? $found_license['sites'] : array();
        $sites = array_filter($sites, function($site) use ($site_url) {
            return $site !== $site_url;
        });
        $license_data['licenses'][$license_index]['sites'] = array_values($sites);
        file_put_contents($license_file, json_encode($license_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ライセンスを無効化しました'
        ));
        break;
        
    case 'check':
        if (!$found_license) {
            echo json_encode(array(
                'success' => false,
                'message' => '無効なライセンスキーです'
            ));
            exit;
        }
        
        // ステータス確認
        if ($found_license['status'] !== 'active') {
            echo json_encode(array(
                'success' => false,
                'message' => 'このライセンスは無効化されています'
            ));
            exit;
        }
        
        // 有効期限確認
        $expires = strtotime($found_license['expires']);
        if ($expires && $expires < time()) {
            echo json_encode(array(
                'success' => false,
                'message' => 'このライセンスは期限切れです'
            ));
            exit;
        }
        
        // サイトが登録されているか確認
        $sites = isset($found_license['sites']) ? $found_license['sites'] : array();
        $site_registered = in_array($site_url, $sites);
        
        if (!$site_registered) {
            echo json_encode(array(
                'success' => false,
                'message' => 'このサイトでライセンスが有効化されていません'
            ));
            exit;
        }
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ライセンスは有効です',
            'expires' => $found_license['expires']
        ));
        break;
        
    default:
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'message' => '無効なアクションです'
        ));
        break;
}
