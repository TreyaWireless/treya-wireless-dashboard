<?php
define('ZABBIX_ENTRY', 1);
require_once dirname(__FILE__).'/include/config.inc.php';

// Authenticate session - strictly secure
if (!CWebUser::isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$hostid = getRequest('hostid');
if (!$hostid) {
    echo json_encode(['error' => 'Missing hostid']);
    exit;
}

// Fetch host macros for credentials
$db_macros = DBselect(
    'SELECT macro, value FROM hostmacro WHERE hostid='.zbx_dbstr($hostid)
);
$macros = [];
while ($row = DBfetch($db_macros)) {
    $macros[$row['macro']] = $row['value'];
}

// Validate macros configuration
if (!isset($macros['{$OMADA_URL}']) || !isset($macros['{$OMADA_ID}']) || !isset($macros['{$OMADA_CLIENT_ID}']) || !isset($macros['{$OMADA_CLIENT_SECRET}'])) {
    echo json_encode(['error' => 'Omada host macros are not configured for this host']);
    exit;
}

$base_url = rtrim($macros['{$OMADA_URL}'], '/');
$omadac_id = $macros['{$OMADA_ID}'];
$client_id = $macros['{$OMADA_CLIENT_ID}'];
$client_secret = $macros['{$OMADA_CLIENT_SECRET}'];

// HTTP Request Helper using cURL
function make_request($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = "Authorization: AccessToken={$token}";
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($res === false) {
        throw new Exception("Connection to Omada Controller failed.");
    }
    
    return json_decode($res, true);
}

try {
    // 1. Get access token
    $token_url = "{$base_url}/openapi/authorize/token?grant_type=client_credentials";
    $login_res = make_request($token_url, 'POST', [
        'omadacId' => $omadac_id,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ]);
    
    $token = isset($login_res['result']['accessToken']) ? $login_res['result']['accessToken'] : null;
    if (!$token) {
        throw new Exception("Login failed: " . ($login_res['msg'] ?? 'Unknown error'));
    }

    // 2. Get Site ID
    $sites_url = "{$base_url}/openapi/v1/{$omadac_id}/sites?page=1&pageSize=10";
    $sites_res = make_request($sites_url, 'GET', null, $token);
    $site_id = $sites_res['result']['data'][0]['siteId'] ?? null;
    if (!$site_id) {
        throw new Exception("Site ID not found on this controller.");
    }

    // 3. Get Devices (paginated)
    $devices = [];
    $page = 1;
    while (true) {
        $devices_url = "{$base_url}/openapi/v1/{$omadac_id}/sites/{$site_id}/devices?page={$page}&pageSize=100";
        $devices_res = make_request($devices_url, 'GET', null, $token);
        $data_list = $devices_res['result']['data'] ?? [];
        if (empty($data_list)) {
            break;
        }
        $devices = array_merge($devices, $data_list);
        if (count($data_list) < 100) {
            break;
        }
        $page++;
    }

    // 4. Get Clients (paginated)
    $clients = [];
    $page = 1;
    while (true) {
        $clients_url = "{$base_url}/openapi/v1/{$omadac_id}/sites/{$site_id}/clients?page={$page}&pageSize=100";
        $clients_res = make_request($clients_url, 'GET', null, $token);
        $data_list = $clients_res['result']['data'] ?? [];
        if (empty($data_list)) {
            break;
        }
        $clients = array_merge($clients, $data_list);
        if (count($data_list) < 100) {
            break;
        }
        $page++;
    }

    echo json_encode([
        'status' => 'success',
        'devices' => $devices,
        'clients' => $clients
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'error_message' => $e->getMessage()
    ]);
}
