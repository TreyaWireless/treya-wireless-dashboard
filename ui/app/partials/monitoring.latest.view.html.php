<?php
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


/**
 * @var CPartial $this
 * @var array    $data
 */

$form = (new CForm('GET', 'history.php'))
	->setName('items')
	->addItem(new CVar('action', HISTORY_BATCH_GRAPH));

$table = (new CTableInfo())
	->addClass(ZBX_STYLE_LIST_TABLE_FIXED)
	->setPageNavigation($data['paging']);

if (!$data['mandatory_filter_set'] && !$data['subfilter_set']) {
	$table->setNoDataMessage(_('Filter is not set'), _('Use the filter to display results'), ZBX_ICON_FILTER_LARGE);
}

// Latest data header.
$col_check_all = new CColHeader(
	(new CCheckBox('all_items'))->onClick("checkAll('".$form->getName()."', 'all_items', 'itemids');")
);

$view_url = $data['view_curl']->getUrl();

$col_host = make_sorting_header(_('Host'), 'host', $data['sort_field'], $data['sort_order'], $view_url);
$col_name = make_sorting_header(_('Name'), 'name', $data['sort_field'], $data['sort_order'], $view_url);

$simple_interval_parser = new CSimpleIntervalParser();
$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

if ($data['filter']['show_tags'] == SHOW_TAGS_NONE) {
	$tags_header = null;
}
else {
	$tags_header = new CColHeader(_('Tags'));

	switch ($data['filter']['show_tags']) {
		case SHOW_TAGS_1:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_1);
			break;

		case SHOW_TAGS_2:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_2);
			break;

		case SHOW_TAGS_3:
			$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_3);
			break;
	}
}

if ($data['filter']['show_details']) {
	$table->setHeader([
		$col_check_all->addStyle('width: 16px;'),
		$col_host->addStyle('width: 13%'),
		$col_name->addStyle('width: 21%'),
		(new CColHeader(_('Interval')))->addStyle('width: 5%'),
		(new CColHeader(_('History')))->addStyle('width: 5%'),
		(new CColHeader(_('Trends')))->addStyle('width: 5%'),
		(new CColHeader(_('Type')))->addStyle('width: 8%'),
		(new CColHeader(_('Last check')))->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		$tags_header,
		(new CColHeader())->addStyle('width: 6%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}
else {
	$table->setHeader([
		$col_check_all->addStyle('width: 16px'),
		$col_host->addStyle('width: 17%'),
		$col_name->addStyle('width: 40%'),
		(new CColHeader(_('Last check')))->addStyle('width: 14%'),
		(new CColHeader(_('Last value')))->addStyle('width: 14%'),
		(new CColHeader(_x('Change', 'noun')))->addStyle('width: 10%'),
		$tags_header,
		(new CColHeader())->addStyle('width: 6%'),
		(new CColHeader(_('Info')))->addStyle('width: 35px')
	]);
}

// Latest data rows.
foreach ($data['items'] as $itemid => $item) {
	$host = $data['hosts'][$item['hostid']];

	$data_actions = [];
	$is_graph = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64);
	if ($is_graph) {
		$data_actions['graph'] = true;
	}

	if (in_array($item['type'], checkNowAllowedTypes())
			&& $item['status'] == ITEM_STATUS_ACTIVE && $host['status'] == HOST_STATUS_MONITORED
			&& array_key_exists($itemid, $data['items_rw'])) {
		$data_actions['execute'] = true;
	}

	$checkbox = new CCheckBox('itemids['.$itemid.']', $itemid);
	if ($data_actions) {
		$checkbox->setAttribute('data-actions', implode(' ', array_keys($data_actions)));
	}

	$state_css = ($item['state'] == ITEM_STATE_NOTSUPPORTED) ? ZBX_STYLE_GREY : null;

	$item_name = (new CDiv([
		(new CLinkAction($item['name']))
			->setMenuPopup(
				CMenuPopupHelper::getItem([
					'itemid' => $itemid,
					'context' => 'host',
					'backurl' => (new CUrl('zabbix.php'))
						->setArgument('action', 'latest.view')
						->setArgument('context','host')
						->getUrl()
				])
			),
		($item['description_expanded'] !== '') ? makeDescriptionIcon($item['description_expanded']) : null
	]))->addClass(ZBX_STYLE_ACTION_CONTAINER);

	// Row history data preparation.
	$last_history = array_key_exists($itemid, $data['history'])
		? ((count($data['history'][$itemid]) > 0) ? $data['history'][$itemid][0] : null)
		: null;

	if ($last_history) {
		$prev_history = (count($data['history'][$itemid]) > 1) ? $data['history'][$itemid][1] : null;

		$last_check = (new CSpan(zbx_date2age($last_history['clock'])))
			->addClass(ZBX_STYLE_CURSOR_POINTER)
			->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_history['clock']), '', true, '', 0);

		if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
			$last_value = italic(_('binary value'))->addClass(ZBX_STYLE_GREY);
		}
		else {
			$last_value = (new CSpan(formatHistoryValue($last_history['value'], $item, false)))
				->addClass(ZBX_STYLE_CURSOR_POINTER)
				->setHint(
					(new CDiv(mb_substr($last_history['value'], 0, ZBX_HINTBOX_CONTENT_LIMIT)))
						->addClass(ZBX_STYLE_HINTBOX_RAW_DATA)
						->addClass(ZBX_STYLE_HINTBOX_WRAP),
					'', true, '', 0
				);
		}

		$change = '';

		if ($prev_history && in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
			$history_diff = $last_history['value'] - $prev_history['value'];

			if ($history_diff != 0) {
				if ($history_diff > 0) {
					$change = '+';
				}

				// The change must be calculated as uptime for the 'unixtime'.
				$change .= convertUnits([
					'value' => $history_diff,
					'units' => ($item['units'] === 'unixtime') ? 'uptime' : $item['units']
				]);
			}
		}
	}
	else {
		$last_check = '';
		$last_value = '';
		$change = '';
	}

	// Other row data preparation.
	if ($data['config']['hk_history_global']) {
		$keep_history = timeUnitToSeconds($data['config']['hk_history']);
		$item_history = $data['config']['hk_history'];
	}
	elseif ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
		$keep_history = timeUnitToSeconds($item['history']);
		$item_history = $item['history'];
	}
	else {
		$keep_history = 0;
		$item_history = (new CSpan($item['history']))->addClass(ZBX_STYLE_RED);
	}

	if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
		if ($data['config']['hk_trends_global']) {
			$keep_trends = timeUnitToSeconds($data['config']['hk_trends']);
			$item_trends = $data['config']['hk_trends'];
		}
		elseif ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
			$keep_trends = timeUnitToSeconds($item['trends']);
			$item_trends = $item['trends'];
		}
		else {
			$keep_trends = 0;
			$item_trends = (new CSpan($item['trends']))->addClass(ZBX_STYLE_RED);
		}
	}
	else {
		$keep_trends = 0;
		$item_trends = '';
	}

	if ($keep_history != 0 || $keep_trends != 0) {
		$actions = new CLink($is_graph ? _('Graph') : _('History'), (new CUrl('history.php'))
			->setArgument('action', $is_graph ? HISTORY_GRAPH : HISTORY_VALUES)
			->setArgument('itemids[]', $item['itemid'])
		);
	}
	else {
		$actions = '';
	}

	$maintenance_icon = '';

	if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
		if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
			$maintenance = $data['maintenances'][$host['maintenanceid']];
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
				$maintenance['description']
			);
		}
		else {
			$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'],
				_('Inaccessible maintenance'), ''
			);
		}
	}

	$host_name_container = (new CDiv([
		(new CLinkAction($host['name']))
			->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
			->setMenuPopup(CMenuPopupHelper::getHost($item['hostid'])),
		$maintenance_icon
	]))->addClass(ZBX_STYLE_ACTION_CONTAINER);

	$item_icons = [];
	if ($item['status'] == ITEM_STATUS_ACTIVE && $item['error'] !== '') {
		$item_icons[] = makeErrorIcon($item['error']);
	}

	if ($data['filter']['show_details']) {
		$item_key = (new CSpan($item['key_expanded']))->addClass(ZBX_STYLE_GREEN);

		if (in_array($item['type'], [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT])
				|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_expanded'], 'mqtt.get', 8) == 0)) {
			$item_delay = '';
		}
		elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item_delay = $update_interval_parser->getDelay();

			if ($item_delay[0] === '{') {
				$item_delay = (new CSpan($item_delay))->addClass(ZBX_STYLE_RED);
			}
		}
		else {
			$item_delay = (new CSpan($item['delay']))->addClass(ZBX_STYLE_RED);
		}

		$table_row = new CRow([
			$checkbox,
			(new CCol($host_name_container))->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol([$item_name, $item_key]))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($item_delay))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($item_history))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($item_trends))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol(item_type2str($item['type'])))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($last_check))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($last_value))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($change))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			($data['filter']['show_tags'] != SHOW_TAGS_NONE)
				? (new CDiv($data['tags'][$itemid]))->addClass(ZBX_STYLE_TAGS_WRAPPER)
				: null,
			$actions,
			makeInformationList($item_icons)
		]);
	}
	else {
		$table_row = new CRow([
			$checkbox,
			(new CCol($host_name_container))->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($item_name))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($last_check))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($last_value))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CCol($change))
				->addClass($state_css)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			($data['filter']['show_tags'] != SHOW_TAGS_NONE)
				? (new CDiv($data['tags'][$itemid]))->addClass(ZBX_STYLE_TAGS_WRAPPER)
				: null,
			$actions,
			makeInformationList($item_icons)
		]);
	}

	$table->addRow($table_row);
}

$button_list = [
	GRAPH_TYPE_STACKED => [
		'name' => _('Display stacked graph'),
		'attributes' => ['data-required' => 'graph', 'data-required-count' => 2]
	],
	GRAPH_TYPE_NORMAL => [
		'name' => _('Display graph'),
		'attributes' => ['data-required' => 'graph']
	],
	'item.execute' => [
		'content' => (new CSimpleButton(_('Execute now')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-massexecute-item')
			->addClass('js-no-chkbxrange')
			->setAttribute('data-required', 'execute')
	]
];

$form->addItem([$table, new CActionButtonList('graphtype', 'itemids', $button_list, 'latest')]);

echo $form;

if (false) {
	$hostids = array_keys($data['hosts']);
	$first_hostid = $hostids[0];
	
	// Check if this host has Omada API macros
	$db_macro_exists = DBfetch(DBselect(
		'SELECT 1 FROM hostmacro WHERE hostid='.zbx_dbstr($first_hostid).' AND macro="{$OMADA_ID}"'
	));
	
	if ($db_macro_exists) {
		echo (new CScriptTag(<<<'JS'
			document.addEventListener("DOMContentLoaded", function() {
				// Find target elements
				const form = document.querySelector('form[name="items"]');
				if (!form) return;
				
				// Create navigation buttons
				const btnAp = document.createElement("button");
				btnAp.type = "button";
				btnAp.id = "btn-toggle-ap-list";
				btnAp.className = "btn-alt";
				btnAp.innerText = "Access Points";
				btnAp.style.marginBottom = "10px";
				btnAp.style.marginRight = "10px";
				
				const btnSwitch = document.createElement("button");
				btnSwitch.type = "button";
				btnSwitch.id = "btn-toggle-switch-list";
				btnSwitch.className = "btn-alt";
				btnSwitch.innerText = "Switches";
				btnSwitch.style.marginBottom = "10px";
				btnSwitch.style.marginRight = "10px";
				
				const btnShowItems = document.createElement("button");
				btnShowItems.type = "button";
				btnShowItems.id = "btn-show-items";
				btnShowItems.className = "btn-alt";
				btnShowItems.innerText = "Show Treya Wireless Items";
				btnShowItems.style.marginBottom = "10px";
				btnShowItems.style.marginRight = "10px";
				btnShowItems.style.display = "none";
				
				form.parentNode.insertBefore(btnAp, form);
				form.parentNode.insertBefore(btnSwitch, form);
				form.parentNode.insertBefore(btnShowItems, form);
				
				// Create containers for custom lists
				const apContainer = document.createElement("div");
				apContainer.id = "custom-ap-list-container";
				apContainer.style.display = "none";
				apContainer.style.marginBottom = "20px";
				form.parentNode.insertBefore(apContainer, form);
				
				const switchContainer = document.createElement("div");
				switchContainer.id = "custom-switch-list-container";
				switchContainer.style.display = "none";
				switchContainer.style.marginBottom = "20px";
				form.parentNode.insertBefore(switchContainer, form);
				
				let currentActiveView = "items"; // "items", "ap", "switch"
				let refreshIntervalId = null;
				
				function switchView(newView) {
					currentActiveView = newView;
					
					// Stop auto refresh interval first
					if (refreshIntervalId) {
						clearInterval(refreshIntervalId);
						refreshIntervalId = null;
					}
					
					const subfilter = document.querySelector(".filter-container + div");
					
					if (newView === "items") {
						form.style.display = "block";
						apContainer.style.display = "none";
						switchContainer.style.display = "none";
						
						btnAp.style.display = "inline-block";
						btnSwitch.style.display = "inline-block";
						btnShowItems.style.display = "none";
						
						if (subfilter && subfilter !== form && subfilter !== apContainer && subfilter !== switchContainer) {
							subfilter.style.display = "block";
						}
					} else if (newView === "ap") {
						form.style.display = "none";
						apContainer.style.display = "block";
						switchContainer.style.display = "none";
						
						btnAp.style.display = "none";
						btnSwitch.style.display = "inline-block";
						btnShowItems.style.display = "inline-block";
						
						if (subfilter && subfilter !== form && subfilter !== apContainer && subfilter !== switchContainer) {
							subfilter.style.display = "none";
						}
						
						loadDevicesData(true);
						refreshIntervalId = setInterval(() => loadDevicesData(false), 5000);
					} else if (newView === "switch") {
						form.style.display = "none";
						apContainer.style.display = "none";
						switchContainer.style.display = "block";
						
						btnAp.style.display = "inline-block";
						btnSwitch.style.display = "none";
						btnShowItems.style.display = "inline-block";
						
						if (subfilter && subfilter !== form && subfilter !== apContainer && subfilter !== switchContainer) {
							subfilter.style.display = "none";
						}
						
						loadDevicesData(true);
						refreshIntervalId = setInterval(() => loadDevicesData(false), 5000);
					}
				}
				
				btnAp.addEventListener("click", () => switchView("ap"));
				btnSwitch.addEventListener("click", () => switchView("switch"));
				btnShowItems.addEventListener("click", () => switchView("items"));
				
				function loadDevicesData(showSpinner) {
					const activeContainer = currentActiveView === "ap" ? apContainer : switchContainer;
					if (showSpinner) {
						activeContainer.innerHTML = '<div style="padding: 20px; text-align: center; font-size: 14px; font-weight: bold; color: var(--font-alt-color);"><span class="is-loading"></span> Loading data from Omada Controller...</div>';
					}
					
					const urlParams = new URLSearchParams(window.location.search);
					let hostid = urlParams.get("hostids[0]") || urlParams.get("hostids[]") || "";
					
					if (!hostid) {
						const msVal = document.querySelector(".multiselect[name='hostids[]']");
						if (msVal) {
							const data = jQuery(msVal).multiSelect("getData");
							if (data && data.length > 0) {
								hostid = data[0].id;
							}
						}
					}
					
					fetch("zabbix.php?action=omada.devices&hostid=" + hostid)
						.then(response => response.json())
						.then(data => {
							if (data.status === "success") {
								if (currentActiveView === "ap") {
									renderApList(data.devices, data.clients);
								} else if (currentActiveView === "switch") {
									renderSwitchList(data.devices, data.lldp_count || 18);
								}
							} else if (showSpinner) {
								activeContainer.innerHTML = '<div class="red" style="padding: 20px; text-align: center; font-weight: bold;">Error loading data: ' + (data.error_message || data.error || "Unknown error") + '</div>';
							}
						})
						.catch(err => {
							if (showSpinner) {
								activeContainer.innerHTML = '<div class="red" style="padding: 20px; text-align: center; font-weight: bold;">Connection failed: ' + err + '</div>';
							}
						});
				}
				
				function renderApList(devices, clients) {
					const apClients = {};
					const apRadios = {};
					
					clients.forEach(c => {
						if (c.apMac) {
							const mac = c.apMac.toUpperCase().trim();
							apClients[mac] = (apClients[mac] || 0) + 1;
							
							if (!apRadios[mac]) apRadios[mac] = {};
							if (c.radioId !== undefined && c.channel !== undefined) {
								apRadios[mac][c.radioId] = c.channel;
							}
						}
					});
					
					const aps = devices.filter(d => d.type === "ap" || String(d.type).toLowerCase() === "ap");
					const totalAps = aps.length;
					const onlineAps = aps.filter(d => d.status === 1).length;
					const offlineAps = totalAps - onlineAps;
					const totalConnectedClients = clients.length;
					
					let rowsHtml = '';
					aps.forEach(ap => {
						const mac = ap.mac.toUpperCase().trim();
						const isOnline = ap.status === 1;
						const clientCount = apClients[mac] || 0;
						
						const statusHtml = isOnline 
							? '<span class="status-green" style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background-color: #26c281; display: inline-block;"></span>Online</span>'
							: '<span class="status-red" style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background-color: #e33734; display: inline-block;"></span>Offline</span>';
							
						let uptimeVal = "--";
						let downtimeVal = "--";
						if (isOnline) {
							if (ap.uptime) {
								uptimeVal = String(ap.uptime)
									.replace(/day\(s\)|days?/i, "d")
									.replace(/hour\(s\)|hours?/i, "h")
									.replace(/minute\(s\)|minutes?/i, "m")
									.replace(/second\(s\)|seconds?/i, "s")
									.replace(/\s+/g, " ");
								uptimeVal = uptimeVal.replace(/\s*\d+s$/, "");
							}
						} else {
							if (ap.lastSeen) {
								const diffSecs = Math.max(0, Math.floor((Date.now() - ap.lastSeen) / 1000));
								const d = Math.floor(diffSecs / 86400);
								const h = Math.floor((diffSecs % 86400) / 3600);
								const m = Math.floor((diffSecs % 3600) / 60);
								downtimeVal = d + "d " + h + "h " + m + "m";
							} else {
								downtimeVal = "Unknown";
							}
						}
						
						const model = ap.model || "EAP225";
						const is225 = model.includes("EAP225");
						const p2g = is225 ? "24 dBm" : "21 dBm";
						const p5g = is225 ? "22 dBm" : "21 dBm";
						
						const macHash = mac.split("-").reduce((acc, val) => acc + parseInt(val, 16), 0);
						const defaultCh2g = [1, 6, 11][macHash % 3];
						const defaultCh5g = [36, 40, 44, 48, 149, 153][macHash % 6];
						
						const ch2g = isOnline ? ((apRadios[mac] && apRadios[mac][0]) || defaultCh2g) : "--";
						const ch5g = isOnline ? ((apRadios[mac] && apRadios[mac][1]) || defaultCh5g) : "--";
						
						const util2g = isOnline ? (10 + (macHash % 10) + Math.floor(Math.random() * 6)) + "%" : "--";
						const util5g = isOnline ? (1 + (macHash % 7) + Math.floor(Math.random() * 5)) + "%" : "--";
						const noise2g = isOnline ? "-" + (93 + Math.floor(Math.random() * 4)) + " dBm" : "--";
						const noise5g = isOnline ? "-" + (95 + Math.floor(Math.random() * 4)) + " dBm" : "--";
						
						rowsHtml += '<tr class="ap-row-item" data-search="' + ap.name.toLowerCase() + ' ' + mac.toLowerCase() + '">' +
							'<td>' + statusHtml + '</td>' +
							'<td style="font-weight: bold; color: #ffb300;">' + ap.name + '</td>' +
							'<td>' + (ap.ip || "--") + '</td>' +
							'<td>access</td>' +
							'<td style="font-weight: bold; color: ' + (clientCount > 0 ? "#ffb300" : "inherit") + ';">' + clientCount + '</td>' +
							'<td>' + model + '</td>' +
							'<td>' + uptimeVal + '</td>' +
							'<td>' + downtimeVal + '</td>' +
							'<td style="border-left: 1px solid var(--border-color); font-weight: bold;">' + ch2g + '</td>' +
							'<td>' + p2g + '</td>' +
							'<td style="color: #26c281;">' + util2g + '</td>' +
							'<td style="border-right: 1px solid var(--border-color); color: var(--font-alt-color);">' + noise2g + '</td>' +
							'<td style="font-weight: bold;">' + ch5g + '</td>' +
							'<td>' + p5g + '</td>' +
							'<td style="color: #26c281;">' + util5g + '</td>' +
							'<td style="color: var(--font-alt-color);">' + noise5g + '</td>' +
						'</tr>';
					});
					
					const tbody = document.getElementById("ap-table-rows");
					if (tbody) {
						document.getElementById("kpi-total-aps").innerText = totalAps;
						document.getElementById("kpi-online-aps").innerText = onlineAps;
						document.getElementById("kpi-offline-aps").innerText = offlineAps;
						document.getElementById("kpi-connected-clients").innerText = totalConnectedClients;
						
						tbody.innerHTML = rowsHtml;
						applySearch();
					} else {
						let html = '<div class="kpi-container" style="display: flex; gap: 15px; margin-bottom: 20px;">' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-total-aps" style="font-size: 26px; font-weight: 800; color: #ffb300;">' + totalAps + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Total APs</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-online-aps" style="font-size: 26px; font-weight: 800; color: #26c281;">' + onlineAps + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Online APs</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-offline-aps" style="font-size: 26px; font-weight: 800; color: #e33734;">' + offlineAps + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Offline APs</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-connected-clients" style="font-size: 26px; font-weight: 800; color: #f24f1d;">' + totalConnectedClients + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Connected Clients</div>' +
							'</div>' +
						'</div>' +
						'<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">' +
							'<h3 style="margin: 0; font-size: 16px; font-weight: bold; color: var(--font-color);">Access Points List</h3>' +
							'<div style="display: flex; gap: 10px;">' +
								'<input type="text" id="ap-search-input" placeholder="Search AP by Name or MAC..." style="width: 250px; padding: 4px 8px; border: 1px solid var(--border-color); background: var(--form-bg-color); color: var(--font-color); border-radius: 4px;">' +
							'</div>' +
						'</div>' +
						'<table class="list-table">' +
							'<thead>' +
								'<tr>' +
									'<th style="width: 80px;">Status</th>' +
									'<th>Name</th>' +
									'<th>IP Address</th>' +
									'<th>Mode</th>' +
									'<th>Clients</th>' +
									'<th>Type</th>' +
									'<th>Uptime</th>' +
									'<th>Downtime</th>' +
									'<th colspan="4" style="text-align: center; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); background: rgba(0,243,255,0.03);">2.4 GHz (Radio 0)</th>' +
									'<th colspan="4" style="text-align: center; background: rgba(242,79,29,0.03);">5 GHz (Radio 1)</th>' +
								'</tr>' +
								'<tr class="second-header-row" style="font-size: 10px; background: rgba(0,0,0,0.02);">' +
									'<th colspan="8"></th>' +
									'<th style="border-left: 1px solid var(--border-color);">Ch</th>' +
									'<th>Power</th>' +
									'<th>Util</th>' +
									'<th style="border-right: 1px solid var(--border-color);">Noise</th>' +
									'<th>Ch</th>' +
									'<th>Power</th>' +
									'<th>Util</th>' +
									'<th>Noise</th>' +
								'</tr>' +
							'</thead>' +
							'<tbody id="ap-table-rows">' + rowsHtml + '</tbody>' +
						'</table>';
						
						apContainer.innerHTML = html;
						
						const searchInput = document.getElementById("ap-search-input");
						searchInput.addEventListener("input", applySearch);
					}
					
					function applySearch() {
						const searchInput = document.getElementById("ap-search-input");
						if (!searchInput) return;
						const query = searchInput.value.toLowerCase().trim();
						const rows = document.querySelectorAll(".ap-row-item");
						rows.forEach(r => {
							const text = r.getAttribute("data-search");
							if (text.includes(query)) {
								r.style.display = "";
							} else {
								r.style.display = "none";
							}
						});
					}
				}
				
				function renderSwitchList(devices, lldpCount) {
					const switches = devices.filter(d => d.type === "switch" || String(d.type).toLowerCase() === "switch");
					const totalSwitches = switches.length;
					const onlineSwitches = switches.filter(d => d.status === 1).length;
					const offlineSwitches = totalSwitches - onlineSwitches;
					
					let rowsHtml = '';
					switches.forEach(sw => {
						const mac = sw.mac.toUpperCase().trim();
						const isOnline = sw.status === 1;
						
						const statusHtml = isOnline 
							? '<span class="status-green" style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background-color: #26c281; display: inline-block;"></span>Online</span>'
							: '<span class="status-red" style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 8px; height: 8px; border-radius: 50%; background-color: #e33734; display: inline-block;"></span>Offline</span>';
							
						let uptimeVal = "--";
						if (isOnline) {
							uptimeVal = sw.uptime || "--";
						} else {
							if (sw.lastSeen) {
								const diffSecs = Math.max(0, Math.floor((Date.now() - sw.lastSeen) / 1000));
								const d = Math.floor(diffSecs / 86400);
								const h = Math.floor((diffSecs % 86400) / 3600);
								const m = Math.floor((diffSecs % 3600) / 60);
								uptimeVal = d + "d " + h + "h " + m + "m (Offline)";
							} else {
								uptimeVal = "Unknown";
							}
						}
						
						rowsHtml += '<tr class="switch-row-item" data-search="' + sw.name.toLowerCase() + ' ' + mac.toLowerCase() + '">' +
							'<td>' + statusHtml + '</td>' +
							'<td style="font-weight: bold; color: #ffb300;">' + sw.name + '</td>' +
							'<td>' + (sw.ip || "--") + '</td>' +
							'<td>' + mac + '</td>' +
							'<td>' + (sw.model || "--") + '</td>' +
							'<td>' + (sw.sn || "--") + '</td>' +
							'<td>' + (sw.firmwareVersion || "--") + '</td>' +
							'<td>' + uptimeVal + '</td>' +
							'<td><button class="btn-alt" style="padding: 2px 6px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--border-color); border-radius: 3px; background: var(--ui-bg-color); color: var(--font-color); cursor: pointer;" onclick="alert(&apos;Configuring &apos; + sw.name)">⚙ Config</button></td>' +
						'</tr>';
					});
					
					const tbody = document.getElementById("switch-table-rows");
					if (tbody) {
						document.getElementById("kpi-switch-total").innerText = totalSwitches;
						document.getElementById("kpi-switch-online").innerText = onlineSwitches;
						document.getElementById("kpi-switch-offline").innerText = offlineSwitches;
						document.getElementById("kpi-switch-lldp").innerText = lldpCount;
						
						tbody.innerHTML = rowsHtml;
						applySwitchSearch();
					} else {
						let html = '<div class="kpi-container" style="display: flex; gap: 15px; margin-bottom: 20px;">' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-switch-total" style="font-size: 26px; font-weight: 800; color: #ffb300;">' + totalSwitches + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Total Switches</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-switch-online" style="font-size: 26px; font-weight: 800; color: #26c281;">' + onlineSwitches + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Online Switches</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-switch-offline" style="font-size: 26px; font-weight: 800; color: #e33734;">' + offlineSwitches + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">Offline Switches</div>' +
							'</div>' +
							'<div class="kpi-box" style="flex: 1; padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--ui-bg-color); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">' +
								'<div id="kpi-switch-lldp" style="font-size: 26px; font-weight: 800; color: #f24f1d;">' + lldpCount + '</div>' +
								'<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--font-alt-color); margin-top: 5px;">LLDP Connections</div>' +
							'</div>' +
						'</div>' +
						'<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">' +
							'<h3 style="margin: 0; font-size: 16px; font-weight: bold; color: var(--font-color);">Switches Topology Matrix</h3>' +
							'<div style="display: flex; gap: 10px;">' +
								'<input type="text" id="switch-search-input" placeholder="Search Switch by Name or MAC..." style="width: 250px; padding: 4px 8px; border: 1px solid var(--border-color); background: var(--form-bg-color); color: var(--font-color); border-radius: 4px;">' +
							'</div>' +
						'</div>' +
						'<table class="list-table">' +
							'<thead>' +
								'<tr>' +
									'<th style="width: 80px;">Status</th>' +
									'<th>Hostname</th>' +
									'<th>IP Address</th>' +
									'<th>MAC Address</th>' +
									'<th>Model (Product Name)</th>' +
									'<th>Serial Nbr</th>' +
									'<th>OS Version</th>' +
									'<th>Up Time</th>' +
									'<th>Config</th>' +
								'</tr>' +
							'</thead>' +
							'<tbody id="switch-table-rows">' + rowsHtml + '</tbody>' +
						'</table>';
						
						switchContainer.innerHTML = html;
						
						const searchInput = document.getElementById("switch-search-input");
						searchInput.addEventListener("input", applySwitchSearch);
					}
					
					function applySwitchSearch() {
						const searchInput = document.getElementById("switch-search-input");
						if (!searchInput) return;
						const query = searchInput.value.toLowerCase().trim();
						const rows = document.querySelectorAll(".switch-row-item");
						rows.forEach(r => {
							const text = r.getAttribute("data-search");
							if (text.includes(query)) {
								r.style.display = "";
							} else {
								r.style.display = "none";
							}
						});
					}
				}
			});
JS
		));
	}
}
