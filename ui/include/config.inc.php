<?php
ob_start(function($buffer) {
	$script = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
	$is_ui_request = ($script === 'zabbix.php' || $script === 'treya.php');

	$is_json = false;
	foreach (headers_list() as $header) {
		if (stripos($header, 'Content-Type:') !== false) {
			if (stripos($header, 'application/json') !== false || 
				stripos($header, 'application/javascript') !== false || 
				stripos($header, 'application/json-rpc') !== false ||
				stripos($header, 'image/') !== false) {
				$is_json = true;
				break;
			}
		}
	}

	// Only skip JSON/JS/Images if this is NOT a direct UI request from zabbix.php/treya.php
	if ($is_json && !$is_ui_request) {
		return $buffer;
	}

	$placeholders = [
		'zabbix.php' => '___ZABBIX_PHP_URL___',
		'zabbix_server.conf' => '___ZABBIX_SERVER_CONF___',
		'zabbix.conf.php' => '___ZABBIX_CONF_PHP___',
		'zabbix-server' => '___ZABBIX_SERVER_SVC___',
		'zabbix.com' => '___ZABBIX_COM_URL___',
		'zabbix.org' => '___ZABBIX_ORG_URL___',
		'zbx_session' => '___ZBX_SESSION_COOKIE___',
	];
	foreach ($placeholders as $original => $placeholder) {
		$buffer = str_replace($original, $placeholder, $buffer);
	}
	$buffer = str_replace('Zabbix', 'Treya', $buffer);
	foreach ($placeholders as $original => $placeholder) {
		$buffer = str_replace($placeholder, $original, $buffer);
	}
	return $buffer;
});


/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/classes/core/APP.php';

try {
	APP::getInstance()->run(APP::EXEC_MODE_DEFAULT);
}
catch (DBException $e) {
	echo (new CView('general.warning', [
		'header' => 'Database error',
		'messages' => [$e->getMessage()],
		'theme' => ZBX_DEFAULT_THEME
	]))->getOutput();

	exit;
}
catch (ConfigFileException $e) {
	switch ($e->getCode()) {
		case CConfigFile::CONFIG_NOT_FOUND:
			redirect('setup.php');
			exit;

		case CConfigFile::CONFIG_ERROR:
			echo (new CView('general.warning', [
				'header' => 'Configuration file error',
				'messages' => [$e->getMessage()],
				'theme' => ZBX_DEFAULT_THEME
			]))->getOutput();

			exit;

		case CConfigFile::CONFIG_VAULT_ERROR:
			echo (new CView('general.warning', [
				'header' => _('Vault connection failed.'),
				'messages' => [$e->getMessage()],
				'theme' => ZBX_DEFAULT_THEME
			]))->getOutput();

			exit;
	}
}
catch (Exception $e) {
	echo (new CView('general.warning', [
		'header' => $e->getMessage(),
		'messages' => [],
		'theme' => ZBX_DEFAULT_THEME
	]))->getOutput();

	exit;
}

CProfiler::getInstance()->start();

global $page;

$page = [
	'title' => null,
	'file' => null,
	'scripts' => null,
	'type' => null,
	'menu' => null
];
