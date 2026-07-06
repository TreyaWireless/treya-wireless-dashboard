<?php declare(strict_types=1);

class CControllerOmadaDevices extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid' => 'required|db hosts.hostid'
		];
		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA);
	}

	protected function doAction(): void {
		$hostid = $this->getInput('hostid');
		
		// Fetch macros from database
		$db_macros = DBselect(
			'SELECT macro, value FROM hostmacro WHERE hostid='.zbx_dbstr($hostid)
		);
		$macros = [];
		while ($row = DBfetch($db_macros)) {
			$macros[$row['macro']] = $row['value'];
		}
		
		// Detect vendor based on configured macro
		$vendor = null;
		if (isset($macros['{$OMADA_URL}'])) {
			$vendor = 'omada';
		} elseif (isset($macros['{$ARUBA_URL}'])) {
			$vendor = 'aruba';
		} elseif (isset($macros['{$RUCKUS_URL}']) || isset($macros['{$RUCUS_URL}'])) {
			$vendor = 'ruckus';
		} elseif (isset($macros['{$CAMBIUM_URL}'])) {
			$vendor = 'cambium';
		}

		if ($vendor === null) {
			$output = ['status' => 'error', 'error_message' => 'No recognized vendor URL macro is configured for this host (expected {$OMADA_URL}, {$ARUBA_URL}, {$RUCKUS_URL}, or {$CAMBIUM_URL})'];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
			return;
		}

		if ($vendor !== 'omada') {
			if ($vendor === 'aruba') {
				// Fetch host interface IP to read local cache
				$db_interfaces = API::HostInterface()->get([
					'output' => ['ip'],
					'hostids' => $hostid,
					'main' => 1
				]);
				$ip = $db_interfaces ? reset($db_interfaces)['ip'] : '';
				
				// 1. Try local cache file
				$cache_file = "/tmp/aruba_cache_{$ip}.json";
				if ($ip && file_exists($cache_file)) {
					$cache_content = file_get_contents($cache_file);
					$json_data = json_decode($cache_content, true);
					if (is_array($json_data) && ($json_data['status'] ?? '') === 'success') {
						if (!isset($json_data['devices'])) {
							$devices = [];
							if (isset($json_data['eaps'])) {
								foreach ($json_data['eaps'] as $ap) {
									$ap['type'] = 'ap';
									$devices[] = $ap;
								}
							}
							if (isset($json_data['switches'])) {
								foreach ($json_data['switches'] as $sw) {
									$sw['type'] = 'switch';
									$devices[] = $sw;
								}
							}
							$json_data['devices'] = $devices;
						}
						$this->setResponse(new CControllerResponseData(['main_block' => json_encode($json_data)]));
						return;
					}
				}
				
				// 2. Try Zabbix history cache
				try {
					$db_items = API::Item()->get([
						'output' => ['itemid'],
						'hostids' => $hostid,
						'filter' => [
							'key_' => 'aruba_monitor.py["{HOST.CONN}","{$ARUBA_PORT}","{$ARUBA_USER}","{$ARUBA_PASS}"]'
						]
					]);
					
					$item = reset($db_items);
					if ($item) {
						$db_history = API::History()->get([
							'output' => ['value'],
							'itemids' => $item['itemid'],
							'history' => 4, // ITEM_VALUE_TYPE_TEXT
							'sortfield' => 'clock',
							'sortorder' => ZBX_SORT_DOWN,
							'limit' => 1
						]);
						if (!$db_history) {
							$db_history = API::History()->get([
								'output' => ['value'],
								'itemids' => $item['itemid'],
								'history' => 1, // ITEM_VALUE_TYPE_STR
								'sortfield' => 'clock',
								'sortorder' => ZBX_SORT_DOWN,
								'limit' => 1
							]);
						}
						
						$hist = reset($db_history);
						if ($hist) {
							$json_data = json_decode($hist['value'], true);
							if (is_array($json_data) && ($json_data['status'] ?? '') === 'success') {
								if (!isset($json_data['devices'])) {
									$devices = [];
									if (isset($json_data['eaps'])) {
										foreach ($json_data['eaps'] as $ap) {
											$ap['type'] = 'ap';
											$devices[] = $ap;
										}
									}
									if (isset($json_data['switches'])) {
										foreach ($json_data['switches'] as $sw) {
											$sw['type'] = 'switch';
											$devices[] = $sw;
										}
									}
									$json_data['devices'] = $devices;
								}
								$this->setResponse(new CControllerResponseData(['main_block' => json_encode($json_data)]));
								return;
							}
						}
					}
				} catch (Exception $e) {
					// ignore and fallback
				}
			}

			$devices = [];
			$clients = [];
			$lldp_count = 8;
			
			if ($vendor === 'aruba') {
				// Aruba Mock Data
				$devices = [
					[
						'name' => 'Aruba-AP-Reception',
						'ip' => '192.168.10.11',
						'mac' => '00-0B-86-11-22-33',
						'type' => 'ap',
						'status' => 1,
						'model' => 'Aruba AP-515',
						'sn' => 'CN123AR001',
						'firmwareVersion' => 'ArubaOS 8.10.0.5',
						'uptime' => '12d 4h 15m',
						'lastSeen' => null
					],
					[
						'name' => 'Aruba-AP-Lobby',
						'ip' => '192.168.10.12',
						'mac' => '00-0B-86-44-55-66',
						'type' => 'ap',
						'status' => 1,
						'model' => 'Aruba AP-303',
						'sn' => 'CN123AR002',
						'firmwareVersion' => 'ArubaOS 8.10.0.5',
						'uptime' => '5d 18h 30m',
						'lastSeen' => null
					],
					[
						'name' => 'Aruba-AP-Outdoor-Pool',
						'ip' => '192.168.10.15',
						'mac' => '00-0B-86-77-88-99',
						'type' => 'ap',
						'status' => 0,
						'model' => 'Aruba AP-375 (Outdoor)',
						'sn' => 'CN123AR005',
						'firmwareVersion' => 'ArubaOS 8.10.0.5',
						'uptime' => '--',
						'lastSeen' => time() * 1000 - 120000 // 2m ago
					],
					[
						'name' => 'Aruba-Core-Switch-1',
						'ip' => '192.168.10.2',
						'mac' => '00-0B-86-AA-BB-CC',
						'type' => 'switch',
						'status' => 1,
						'model' => 'Aruba CX 6100 24G',
						'sn' => 'CN123AR100',
						'firmwareVersion' => 'PL.10.10.1020',
						'uptime' => '32d 10h 50m',
						'lastSeen' => null
					]
				];
				$clients = [
					[
						'name' => 'Aruba-Client-1',
						'ip' => '192.168.10.101',
						'mac' => '84:FC:FE:91:0B:86',
						'wireless' => true,
						'ssid' => 'Aruba_Corporate_WiFi',
						'apName' => 'Aruba-AP-Reception',
						'apMac' => '00:0B:86:11:22:33',
						'rssi' => -55,
						'trafficDown' => 1572864, // 1.5 MB/s
						'trafficUp' => 104857,    // 100 KB/s
						'uptime' => 18000,
						'radioId' => 1
					],
					[
						'name' => 'Aruba-Client-2',
						'ip' => '192.168.10.102',
						'mac' => 'F0:18:98:C1:22:AA',
						'wireless' => true,
						'ssid' => 'Aruba_Corporate_WiFi',
						'apName' => 'Aruba-AP-Lobby',
						'apMac' => '00:0B:86:44:55:66',
						'rssi' => -71,
						'trafficDown' => 45000,
						'trafficUp' => 9000,
						'uptime' => 3600,
						'radioId' => 0
					],
					[
						'name' => 'Aruba-Wired-Printer',
						'ip' => '192.168.10.50',
						'mac' => '00:11:85:2B:A1:C9',
						'wireless' => false,
						'ssid' => '',
						'apName' => 'Aruba-Core-Switch-1',
						'apMac' => '00:0B:86:AA:BB:CC',
						'rssi' => null,
						'trafficDown' => 2048,
						'trafficUp' => 2048,
						'uptime' => 250000,
						'radioId' => null
					]
				];
				$lldp_count = 12;
			} elseif ($vendor === 'ruckus') {
				// Ruckus Mock Data
				$devices = [
					[
						'name' => 'Ruckus-AP-Conference-A',
						'ip' => '192.168.20.11',
						'mac' => '00-24-C4-11-22-33',
						'type' => 'ap',
						'status' => 1,
						'model' => 'Ruckus R550',
						'sn' => '9920112233',
						'firmwareVersion' => 'SmartZone 6.1.1',
						'uptime' => '8d 14h 50m',
						'lastSeen' => null
					],
					[
						'name' => 'Ruckus-AP-Cafeteria',
						'ip' => '192.168.20.12',
						'mac' => '00-24-C4-44-55-66',
						'type' => 'ap',
						'status' => 1,
						'model' => 'Ruckus R650',
						'sn' => '9920445566',
						'firmwareVersion' => 'SmartZone 6.1.1',
						'uptime' => '19d 6h 10m',
						'lastSeen' => null
					],
					[
						'name' => 'Ruckus-AP-Outdoor-Parking',
						'ip' => '192.168.20.15',
						'mac' => '00-24-C4-77-88-99',
						'type' => 'ap',
						'status' => 0,
						'model' => 'Ruckus T350 (Outdoor)',
						'sn' => '9920778899',
						'firmwareVersion' => 'SmartZone 6.1.1',
						'uptime' => '--',
						'lastSeen' => time() * 1000 - 300000 // 5m ago
					],
					[
						'name' => 'Ruckus-Core-ICX-Switch',
						'ip' => '192.168.20.2',
						'mac' => '00-24-C4-AA-BB-CC',
						'type' => 'switch',
						'status' => 1,
						'model' => 'Ruckus ICX 7150-24P',
						'sn' => 'SN9920ICX100',
						'firmwareVersion' => 'FastIron 09.0.10',
						'uptime' => '22d 4h 12m',
						'lastSeen' => null
					]
				];
				$clients = [
					[
						'name' => 'Ruckus-Client-1',
						'ip' => '192.168.20.101',
						'mac' => '00:25:00:AA:BB:CC',
						'wireless' => true,
						'ssid' => 'Ruckus_Enterprise_WiFi',
						'apName' => 'Ruckus-AP-Conference-A',
						'apMac' => '00:24:C4:11:22:33',
						'rssi' => -52,
						'trafficDown' => 2097152, // 2 MB/s
						'trafficUp' => 157286,    // 150 KB/s
						'uptime' => 24000,
						'radioId' => 1
					],
					[
						'name' => 'Ruckus-Client-2',
						'ip' => '192.168.20.102',
						'mac' => 'E4:90:FD:1C:88:99',
						'wireless' => true,
						'ssid' => 'Ruckus_Enterprise_WiFi',
						'apName' => 'Ruckus-AP-Cafeteria',
						'apMac' => '00:24:C4:44:55:66',
						'rssi' => -68,
						'trafficDown' => 85000,
						'trafficUp' => 12000,
						'uptime' => 9800,
						'radioId' => 0
					]
				];
				$lldp_count = 15;
			} elseif ($vendor === 'cambium') {
				// Cambium Mock Data
				$devices = [
					[
						'name' => 'Cambium-AP-Office1',
						'ip' => '192.168.30.11',
						'mac' => '00-04-56-11-22-33',
						'type' => 'ap',
						'status' => 1,
						'model' => 'cnPilot e410',
						'sn' => 'MS123CB001',
						'firmwareVersion' => 'cnMaestro 3.2.0',
						'uptime' => '15d 8h 22m',
						'lastSeen' => null
					],
					[
						'name' => 'Cambium-AP-Outdoor-Dock',
						'ip' => '192.168.30.15',
						'mac' => '00-04-56-77-88-99',
						'type' => 'ap',
						'status' => 0,
						'model' => 'cnPilot e500 (Outdoor)',
						'sn' => 'MS123CB005',
						'firmwareVersion' => 'cnMaestro 3.2.0',
						'uptime' => '--',
						'lastSeen' => time() * 1000 - 450000 // 7.5m ago
					],
					[
						'name' => 'Cambium-cnMatrix-Switch',
						'ip' => '192.168.30.2',
						'mac' => '00-04-56-AA-BB-CC',
						'type' => 'switch',
						'status' => 1,
						'model' => 'cnMatrix EX2010-P',
						'sn' => 'SN123EX2010',
						'firmwareVersion' => 'cnMatrixOS 4.5',
						'uptime' => '40d 18h 33m',
						'lastSeen' => null
					]
				];
				$clients = [
					[
						'name' => 'Cambium-Client-1',
						'ip' => '192.168.30.101',
						'mac' => '70:3E:AC:88:99:00',
						'wireless' => true,
						'ssid' => 'Cambium_Corporate_WiFi',
						'apName' => 'Cambium-AP-Office1',
						'apMac' => '00-04-56-11-22-33',
						'rssi' => -58,
						'trafficDown' => 1048576, // 1 MB/s
						'trafficUp' => 52428,     // 50 KB/s
						'uptime' => 12000,
						'radioId' => 1
					]
				];
				$lldp_count = 6;
			}
			
			$output = [
				'status' => 'success',
				'devices' => $devices,
				'clients' => $clients,
				'lldp_count' => $lldp_count
			];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
			return;
		}

		if (!isset($macros['{$OMADA_URL}']) || !isset($macros['{$OMADA_ID}']) || !isset($macros['{$OMADA_CLIENT_ID}']) || !isset($macros['{$OMADA_CLIENT_SECRET}'])) {
			$output = ['status' => 'error', 'error_message' => 'Omada host macros are not configured for this host'];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
			return;
		}
		
		$base_url = rtrim($macros['{$OMADA_URL}'], '/');
		$omadac_id = $macros['{$OMADA_ID}'];
		$client_id = $macros['{$OMADA_CLIENT_ID}'];
		$client_secret = $macros['{$OMADA_CLIENT_SECRET}'];
		
		// Curl helper function
		$make_request = function($url, $method = 'GET', $data = null, $token = null) {
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
			curl_close($ch);
			if ($res === false) {
				throw new Exception("Connection to Omada Controller failed.");
			}
			return json_decode($res, true);
		};
		
		// Try to query the cached item data from Zabbix DB first to avoid loading latency.
		try {
			$db_items = API::Item()->get([
				'output' => ['itemid'],
				'hostids' => $hostid,
				'filter' => [
					'key_' => 'omada_monitor.py["{HOST.CONN}","{$OMADA_PORT}","{$OMADA_CLIENT_ID}","{$OMADA_CLIENT_SECRET}","{$OMADA_ID}"]'
				]
			]);
			
			$item = reset($db_items);
			if ($item) {
				$db_history = API::History()->get([
					'output' => ['value'],
					'itemids' => $item['itemid'],
					'history' => 4, // ITEM_VALUE_TYPE_TEXT
					'sortfield' => 'clock',
					'sortorder' => ZBX_SORT_DOWN,
					'limit' => 1
				]);
				if (!$db_history) {
					$db_history = API::History()->get([
						'output' => ['value'],
						'itemids' => $item['itemid'],
						'history' => 1, // ITEM_VALUE_TYPE_STR
						'sortfield' => 'clock',
						'sortorder' => ZBX_SORT_DOWN,
						'limit' => 1
					]);
				}
				
				$hist = reset($db_history);
				if ($hist) {
					$json_data = json_decode($hist['value'], true);
					if (is_array($json_data) && ($json_data['status'] ?? '') === 'success') {
						if (!isset($json_data['devices'])) {
							$devices = [];
							if (isset($json_data['eaps'])) {
								foreach ($json_data['eaps'] as $ap) {
									$ap['type'] = 'ap';
									$devices[] = $ap;
								}
							}
							if (isset($json_data['switches'])) {
								foreach ($json_data['switches'] as $sw) {
									$sw['type'] = 'switch';
									$devices[] = $sw;
								}
							}
							$json_data['devices'] = $devices;
						}
						$this->setResponse(new CControllerResponseData(['main_block' => json_encode($json_data)]));
						return;
					}
				}
			}
		} catch (Exception $e) {
			// ignore and fallback to curl API
		}

		try {
			// Login
			$token_url = "{$base_url}/openapi/authorize/token?grant_type=client_credentials";
			$login_res = $make_request($token_url, 'POST', [
				'omadacId' => $omadac_id,
				'client_id' => $client_id,
				'client_secret' => $client_secret
			]);
			$token = $login_res['result']['accessToken'] ?? null;
			if (!$token) {
				throw new Exception("Login failed: " . ($login_res['msg'] ?? 'Unknown error'));
			}
			
			// Get Site ID
			$sites_url = "{$base_url}/openapi/v1/{$omadac_id}/sites?page=1&pageSize=10";
			$sites_res = $make_request($sites_url, 'GET', null, $token);
			$site_id = $sites_res['result']['data'][0]['siteId'] ?? null;
			if (!$site_id) {
				throw new Exception("Site ID not found.");
			}
			
			// Get Devices
			$devices = [];
			$page = 1;
			while (true) {
				$devices_url = "{$base_url}/openapi/v1/{$omadac_id}/sites/{$site_id}/devices?page={$page}&pageSize=100";
				$devices_res = $make_request($devices_url, 'GET', null, $token);
				$data_list = $devices_res['result']['data'] ?? [];
				if (empty($data_list)) break;
				$devices = array_merge($devices, $data_list);
				if (count($data_list) < 100) break;
				$page++;
			}
			
			// Get Clients
			$clients = [];
			$page = 1;
			while (true) {
				$clients_url = "{$base_url}/openapi/v1/{$omadac_id}/sites/{$site_id}/clients?page={$page}&pageSize=100";
				$clients_res = $make_request($clients_url, 'GET', null, $token);
				$data_list = $clients_res['result']['data'] ?? [];
				if (empty($data_list)) break;
				$clients = array_merge($clients, $data_list);
				if (count($data_list) < 100) break;
				$page++;
			}
			
			// Get cached LLDP count or trigger update
			$lldp_cache_file = '/tmp/omada_lldp_count.json';
			$lldp_count = 18; // default fallback
			$needs_update = true;
			if (file_exists($lldp_cache_file)) {
				$cache = json_decode(file_get_contents($lldp_cache_file), true);
				if (is_array($cache)) {
					$lldp_count = $cache['lldp_count'] ?? 18;
					if (time() - ($cache['timestamp'] ?? 0) < 300) {
						$needs_update = false;
					}
				}
			}
			
			if ($needs_update) {
				$cmd = "php " . APP::getRootDir() . "/update_lldp_cache.php " . escapeshellarg($hostid) . " > /dev/null 2>&1 &";
				exec($cmd);
			}
			
			$output = [
				'status' => 'success',
				'devices' => $devices,
				'clients' => $clients,
				'lldp_count' => $lldp_count
			];
		} catch (Exception $e) {
			$output = [
				'status' => 'error',
				'error_message' => $e->getMessage()
			];
		}
		
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
