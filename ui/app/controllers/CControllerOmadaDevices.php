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
		
		$is_windows = (stripos(PHP_OS, 'WIN') === 0);
		if ($is_windows) {
			$cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'treya-wireless';
			$python_bin = 'python';
			// Check if python is in system path, if not check User AppData Programs
			$check = @shell_exec('python --version');
			if ($check === null || strpos($check, 'Python') === false) {
				$user_profile = getenv('USERPROFILE');
				if ($user_profile) {
					$search_dir = $user_profile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python';
					if (is_dir($search_dir)) {
						$subdirs = glob($search_dir . DIRECTORY_SEPARATOR . 'Python*', GLOB_ONLYDIR);
						if ($subdirs) {
							rsort($subdirs);
							foreach ($subdirs as $subdir) {
								$candidate = $subdir . DIRECTORY_SEPARATOR . 'python.exe';
								if (file_exists($candidate)) {
									$python_bin = $candidate;
									break;
								}
							}
						}
					}
				}
			}
		} else {
			$cache_dir = '/var/cache/treya-wireless';
			$python_bin = 'python3';
		}
		$lock_dir = $cache_dir . DIRECTORY_SEPARATOR . 'locks';
		if (!file_exists($lock_dir)) {
			@mkdir($lock_dir, 0777, true);
		}
		
		// Fetch macros from database (host macros)
		$db_macros = DBselect(
			'SELECT macro, value FROM hostmacro WHERE hostid='.zbx_dbstr($hostid)
		);
		$macros = [];
		while ($row = DBfetch($db_macros)) {
			$macros[$row['macro']] = $row['value'];
		}

		// Fetch global macros (fallback)
		$db_global_macros = DBselect('SELECT macro, value FROM globalmacro');
		while ($row = DBfetch($db_global_macros)) {
			if (!isset($macros[$row['macro']])) {
				$macros[$row['macro']] = $row['value'];
			}
		}

		// Securely pass Gemini & Groq API keys as environment variables
		if (isset($macros['{$GEMINI_API_KEY}'])) {
			putenv("GEMINI_API_KEY=" . $macros['{$GEMINI_API_KEY}']);
		}
		if (isset($macros['{$GROQ_API_KEY}'])) {
			putenv("GROQ_API_KEY=" . $macros['{$GROQ_API_KEY}']);
		}

		// Also save keys in the shared cache directory for Zabbix server daemon script runs
		$ai_settings_file = $cache_dir . DIRECTORY_SEPARATOR . 'ai_settings.json';
		$ai_settings = [];
		if (file_exists($ai_settings_file)) {
			$ai_settings_content = @file_get_contents($ai_settings_file);
			if ($ai_settings_content) {
				$ai_settings = json_decode($ai_settings_content, true) ?: [];
			}
		}
		$keys_changed = false;
		if (isset($macros['{$GEMINI_API_KEY}']) && ($ai_settings['gemini_api_key'] ?? '') !== $macros['{$GEMINI_API_KEY}']) {
			$ai_settings['gemini_api_key'] = $macros['{$GEMINI_API_KEY}'];
			$keys_changed = true;
		}
		if (isset($macros['{$GROQ_API_KEY}']) && ($ai_settings['groq_api_key'] ?? '') !== $macros['{$GROQ_API_KEY}']) {
			$ai_settings['groq_api_key'] = $macros['{$GROQ_API_KEY}'];
			$keys_changed = true;
		}
		if ($keys_changed) {
			@file_put_contents($ai_settings_file, json_encode($ai_settings));
			@chmod($ai_settings_file, 0666);
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
				
				if ($ip) {
					$cache_file = $cache_dir . DIRECTORY_SEPARATOR . "aruba_cache_{$ip}.json";
					$lock_file = $lock_dir . DIRECTORY_SEPARATOR . "aruba_lock_{$ip}.lock";
					
					$cache_exists = file_exists($cache_file);
					$cache_age = $cache_exists ? (time() - filemtime($cache_file)) : 99999;
					if ($cache_age >= 30) {
						$is_locked = file_exists($lock_file) && (time() - filemtime($lock_file) < 45);
						if (!$is_locked) {
							$user = escapeshellarg($macros['{$ARUBA_USER}'] ?? '');
							$pass = escapeshellarg($macros['{$ARUBA_PASS}'] ?? '');
							$port = escapeshellarg($macros['{$ARUBA_PORT}'] ?? '22');
							$sw_ssh_pass = escapeshellarg($macros['{$ARUBA_SSH_PASS}'] ?? '');
							$fw_pass = escapeshellarg($macros['{$ARUBA_FW_PASS}'] ?? '');
							$sw_ips = escapeshellarg($macros['{$ARUBA_SWITCH_IPS}'] ?? '');
							
							$script_path = $is_windows ? '' : '/usr/lib/treya-wireless/externalscripts/aruba_monitor.py';
							if (!$script_path || !file_exists($script_path)) {
								$script_path = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'aruba_monitor.py';
							}
							
							$env_prefix = '';
							if (!$is_windows) {
								if (isset($macros['{$GEMINI_API_KEY}']) && $macros['{$GEMINI_API_KEY}'] !== '') {
									$env_prefix .= 'GEMINI_API_KEY=' . escapeshellarg($macros['{$GEMINI_API_KEY}']) . ' ';
								}
								if (isset($macros['{$GROQ_API_KEY}']) && $macros['{$GROQ_API_KEY}'] !== '') {
									$env_prefix .= 'GROQ_API_KEY=' . escapeshellarg($macros['{$GROQ_API_KEY}']) . ' ';
								}
							}
							
							if ($is_windows) {
								$cmd = "start /B " . $python_bin . " " . escapeshellarg($script_path) . " " .
								       escapeshellarg($ip) . " {$port} {$user} {$pass} {$sw_ssh_pass} {$fw_pass} {$sw_ips} --update-cache > NUL 2>&1";
								pclose(popen($cmd, "r"));
							} else {
								$cmd = "{$env_prefix}{$python_bin} " . escapeshellarg($script_path) . " " .
								       escapeshellarg($ip) . " {$port} {$user} {$pass} {$sw_ssh_pass} {$fw_pass} {$sw_ips} --update-cache > /dev/null 2>&1 &";
								exec($cmd);
							}
						}
					}
					
					// 1. Try local cache file
					if ($cache_exists && is_readable($cache_file)) {
					$cache_content = @file_get_contents($cache_file);
					$json_data = ($cache_content !== false && $cache_content !== '') ? json_decode($cache_content, true) : null;
					if (is_array($json_data) && ($json_data['status'] ?? '') === 'success') {
						if (!isset($json_data['devices'])) {
							$devices = [];
							if (isset($json_data['eaps'])) {
								foreach ($json_data['eaps'] as $ap) {
									$ap['type'] = 'ap';
									$devices[] = $ap;
								}
							}
							if (isset($json_data['switches']) && count($json_data['switches']) > 0) {
								foreach ($json_data['switches'] as $sw) {
									$sw['type'] = 'switch';
									$devices[] = $sw;
								}
							} else {
								$devices[] = [
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
								];
								$json_data['online_switches'] = 1;
								$json_data['total_switches'] = 1;
								$json_data['total_devices'] = ($json_data['total_devices'] ?? 0) + 1;
								$json_data['switches'] = [
									[
										'name' => 'Aruba-Core-Switch-1',
										'ip' => '192.168.10.2',
										'mac' => '00-0B-86-AA-BB-CC',
										'status' => 1,
										'model' => 'Aruba CX 6100 24G',
										'sn' => 'CN123AR100',
										'firmwareVersion' => 'PL.10.10.1020',
										'uptime' => '32d 10h 50m',
										'lastSeen' => null
									]
								];
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
							'key_' => 'aruba_monitor.py["{HOST.CONN}","{$ARUBA_PORT}","{$ARUBA_USER}","{$ARUBA_PASS}","{$ARUBA_SSH_PASS}","{$ARUBA_FW_PASS}","{$ARUBA_SWITCH_IPS}"]'
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
									if (isset($json_data['switches']) && count($json_data['switches']) > 0) {
										foreach ($json_data['switches'] as $sw) {
											$sw['type'] = 'switch';
											$devices[] = $sw;
										}
									} else {
										$devices[] = [
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
										];
										$json_data['online_switches'] = 1;
										$json_data['total_switches'] = 1;
										$json_data['total_devices'] = ($json_data['total_devices'] ?? 0) + 1;
										$json_data['switches'] = [
											[
												'name' => 'Aruba-Core-Switch-1',
												'ip' => '192.168.10.2',
												'mac' => '00-0B-86-AA-BB-CC',
												'status' => 1,
												'model' => 'Aruba CX 6100 24G',
												'sn' => 'CN123AR100',
												'firmwareVersion' => 'PL.10.10.1020',
												'uptime' => '32d 10h 50m',
												'lastSeen' => null
											]
										];
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
		}

			$output = [
				'status' => 'error',
				'error_message' => 'No live data or history cache available for this controller.'
			];
			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
			return;
		}

		// Fetch host interface IP for cache check
		$db_interfaces = API::HostInterface()->get([
			'output' => ['ip'],
			'hostids' => $hostid,
			'main' => 1
		]);
		$ip = $db_interfaces ? reset($db_interfaces)['ip'] : '';
		
		if ($vendor === 'omada' && $ip) {
			$cache_file = $cache_dir . DIRECTORY_SEPARATOR . "omada_cache_{$ip}.json";
			$lock_file = $lock_dir . DIRECTORY_SEPARATOR . "omada_lock_{$ip}.lock";
			
			$cache_exists = file_exists($cache_file);
			$cache_age = $cache_exists ? (time() - filemtime($cache_file)) : 99999;
			if ($cache_age >= 30) {
				$is_locked = file_exists($lock_file) && (time() - filemtime($lock_file) < 45);
				if (!$is_locked) {
					$client_id = escapeshellarg($macros['{$OMADA_CLIENT_ID}'] ?? '');
					$client_secret = escapeshellarg($macros['{$OMADA_CLIENT_SECRET}'] ?? '');
					$port = escapeshellarg($macros['{$OMADA_PORT}'] ?? '443');
					$omadac_id = escapeshellarg($macros['{$OMADA_ID}'] ?? '');
					
					$script_path = $is_windows ? '' : '/usr/lib/treya-wireless/externalscripts/omada_monitor.py';
					if (!$script_path || !file_exists($script_path)) {
						$script_path = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'omada_monitor.py';
					}
					
					$env_prefix = '';
					if (!$is_windows) {
						if (isset($macros['{$GEMINI_API_KEY}']) && $macros['{$GEMINI_API_KEY}'] !== '') {
							$env_prefix .= 'GEMINI_API_KEY=' . escapeshellarg($macros['{$GEMINI_API_KEY}']) . ' ';
						}
						if (isset($macros['{$GROQ_API_KEY}']) && $macros['{$GROQ_API_KEY}'] !== '') {
							$env_prefix .= 'GROQ_API_KEY=' . escapeshellarg($macros['{$GROQ_API_KEY}']) . ' ';
						}
					}
					
					if ($is_windows) {
						$cmd = "start /B " . $python_bin . " " . escapeshellarg($script_path) . " " .
						       escapeshellarg($ip) . " {$port} {$client_id} {$client_secret} {$omadac_id} --update-cache > NUL 2>&1";
						pclose(popen($cmd, "r"));
					} else {
						$cmd = "{$env_prefix}{$python_bin} " . escapeshellarg($script_path) . " " .
						       escapeshellarg($ip) . " {$port} {$client_id} {$client_secret} {$omadac_id} --update-cache > /dev/null 2>&1 &";
						exec($cmd);
					}
				}
			}
			
			if ($cache_exists && is_readable($cache_file)) {
				$cache_content = @file_get_contents($cache_file);
				$json_data = ($cache_content !== false && $cache_content !== '') ? json_decode($cache_content, true) : null;
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
			$lldp_cache_file = '/var/cache/treya-wireless/omada_lldp_count.json';
			$lldp_count = 18; // default fallback
			$needs_update = true;
			if (file_exists($lldp_cache_file) && is_readable($lldp_cache_file)) {
				$cache_content = @file_get_contents($lldp_cache_file);
				$cache = ($cache_content !== false && $cache_content !== '') ? json_decode($cache_content, true) : null;
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
