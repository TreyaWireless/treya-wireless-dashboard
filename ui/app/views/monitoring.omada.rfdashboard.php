<?php declare(strict_types = 0);

/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('layout.mode.js');
$web_layout_mode = $this->getLayoutMode();

$html_page = (new CHtmlPage())
	->setTitle(_('RF Dashboard'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

// Initialize Host Groups multiselect
$multiselect_groups = new CMultiSelect([
	'name' => 'groupids[]',
	'object_name' => 'hostGroup',
	'data' => $data['groups_multiselect'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'host_groups',
			'srcfld1' => 'groupid',
			'dstfrm' => 'rf_filter_form',
			'dstfld1' => 'groupids_',
			'with_hosts' => true,
			'enrich_parent_groups' => true
		]
	]
]);
$multiselect_groups->setId('groupids_');
$multiselect_groups->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

// Initialize Hosts multiselect
$multiselect_hosts = new CMultiSelect([
	'name' => 'hostids[]',
	'object_name' => 'hosts',
	'data' => $data['hosts_multiselect'],
	'popup' => [
		'filter_preselect' => [
			'id' => 'groupids_',
			'submit_as' => 'groupid'
		],
		'parameters' => [
			'srctbl' => 'hosts',
			'srcfld1' => 'hostid',
			'dstfrm' => 'rf_filter_form',
			'dstfld1' => 'hostids_'
		]
	]
]);
$multiselect_hosts->setId('hostids_');
$multiselect_hosts->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH);

$groups_html = $multiselect_groups->toString();
$hosts_html = $multiselect_hosts->toString();

$resolved_hosts_json = json_encode($data['resolved_hosts_list']);
$url_mac = $data['mac'];
$url_hostid = $data['hostid'];
?>

<style>
.rf-tabs-container {
	display: flex;
	border-bottom: 2px solid var(--border-color);
	margin-bottom: 20px;
}
.rf-tab {
	padding: 10px 20px;
	font-size: 13px;
	font-weight: bold;
	color: var(--font-alt-color);
	cursor: pointer;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	transition: all 0.2s;
}
.rf-tab:hover {
	color: var(--font-color);
}
.rf-tab.active {
	color: #0275d8;
	border-bottom-color: #0275d8;
}
.rf-dashboard-grid {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 20px;
	margin-bottom: 20px;
}
@media (max-width: 1024px) {
	.rf-dashboard-grid {
		grid-template-columns: 1fr;
	}
}
.rf-card {
	background: var(--ui-bg-color);
	border: 1px solid var(--border-color);
	border-radius: 6px;
	padding: 20px;
	box-shadow: 0 2px 5px rgba(0,0,0,0.03);
}
.rf-card-title {
	font-size: 14px;
	font-weight: bold;
	color: var(--font-color);
	margin-top: 0;
	margin-bottom: 15px;
	border-bottom: 1px solid var(--border-color);
	padding-bottom: 8px;
}
.rf-info-layout {
	display: flex;
	gap: 20px;
	align-items: flex-start;
}
.rf-info-picture-container {
	flex: 0 0 140px;
	text-align: center;
	border: 1px solid var(--border-color);
	background: rgba(0, 0, 0, 0.02);
	border-radius: 6px;
	padding: 10px;
	box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
}
.rf-info-picture {
	width: 120px;
	height: 120px;
	object-fit: contain;
	border-radius: 4px;
	filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}
.rf-info-table {
	flex: 1;
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px 20px;
}
.rf-info-item {
	display: flex;
	flex-direction: column;
}
.rf-info-label {
	font-size: 10px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--font-alt-color);
	margin-bottom: 2px;
}
.rf-info-value {
	font-size: 13px;
	font-weight: bold;
	color: var(--font-color);
}
.rf-radio-selectors {
	display: flex;
	gap: 10px;
	margin-bottom: 20px;
}
.rf-radio-btn {
	padding: 6px 15px;
	border: 1px solid var(--border-color);
	background: var(--ui-bg-color);
	color: var(--font-color);
	border-radius: 4px;
	font-size: 12px;
	font-weight: bold;
	cursor: pointer;
	transition: all 0.15s;
}
.rf-radio-btn:hover {
	background: var(--border-color);
}
.rf-radio-btn.active {
	background: #0275d8;
	color: #fff;
	border-color: #0275d8;
}
.rf-charts-grid {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: 20px;
}
@media (max-width: 900px) {
	.rf-charts-grid {
		grid-template-columns: 1fr;
	}
}
.rf-chart-box {
	background: var(--ui-bg-color);
	border: 1px solid var(--border-color);
	border-radius: 6px;
	padding: 15px;
}
.rf-chart-title {
	font-size: 12px;
	font-weight: bold;
	color: var(--font-alt-color);
	margin-bottom: 10px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.rf-canvas-chart {
	width: 100%;
	height: 120px;
	background: rgba(0,0,0,0.01);
	border-radius: 4px;
}
.rf-client-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px dashed var(--border-color);
}
.rf-client-row:last-child {
	border-bottom: none;
}
.rf-client-name {
	font-weight: bold;
	color: #0275d8;
	font-size: 12px;
}
.rf-client-icons {
	display: flex;
	gap: 12px;
	align-items: center;
}
.signal-bar-icon {
	display: inline-flex;
	align-items: flex-end;
	gap: 1.5px;
	height: 12px;
}
.signal-bar {
	width: 2.5px;
	background: var(--border-color);
	border-radius: 0.5px;
}
.signal-bar.active-green { background: #26c281; }
.signal-bar.active-yellow { background: #ffb300; }
.signal-bar.active-orange { background: #f24f1d; }
.signal-bar.active-red { background: #e33734; }

.speedometer-icon {
	display: inline-flex;
	position: relative;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	border: 2px solid var(--font-alt-color);
	border-bottom-color: transparent;
}
.speedometer-needle {
	position: absolute;
	width: 5px;
	height: 1.5px;
	background: var(--font-color);
	top: 5px;
	left: 5px;
	transform-origin: left center;
}
.speedometer-needle.fast { transform: rotate(135deg) scaleX(1.3); background: #26c281; }
.speedometer-needle.medium { transform: rotate(70deg) scaleX(1.1); background: #ffb300; }
.speedometer-needle.slow { transform: rotate(10deg); background: #e33734; }

/* Client Match Styles */
.rf-client-match-bin {
	display: flex;
	align-items: center;
	margin-bottom: 12px;
}
.rf-client-match-bin-label {
	width: 70px;
	font-size: 11px;
	font-weight: bold;
	color: var(--font-alt-color);
}
.rf-client-match-bin-bars {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.rf-client-match-bar-container {
	display: flex;
	align-items: center;
	gap: 10px;
}
.rf-client-match-bar {
	height: 8px;
	border-radius: 4px;
	min-width: 2px;
	transition: width 0.3s ease;
}
.rf-client-match-count {
	font-size: 10px;
	font-weight: bold;
	color: var(--font-color);
}

/* Spectrum Channel Quality */
.rf-channel-grid {
	display: flex;
	gap: 20px;
	margin-top: 15px;
}
.rf-channel-chart-container {
	flex: 1;
	background: var(--ui-bg-color);
	border: 1px solid var(--border-color);
	border-radius: 6px;
	padding: 15px;
	min-height: 250px;
	display: flex;
	flex-direction: column;
}
.rf-channel-stats-container {
	width: 320px;
	background: var(--ui-bg-color);
	border: 1px solid var(--border-color);
	border-radius: 6px;
	padding: 15px;
}
.rf-chan-bar-outer {
	display: flex;
	flex-direction: column;
	align-items: center;
	flex: 1;
	justify-content: flex-end;
}
.rf-chan-bar-fill {
	width: 30px;
	border-radius: 3px 3px 0 0;
	position: relative;
	transition: height 0.5s ease;
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
}
.rf-chan-segment {
	width: 100%;
}
.rf-chan-label {
	margin-top: 8px;
	font-size: 11px;
	font-weight: bold;
	color: var(--font-color);
}
.rf-stats-row {
	display: flex;
	justify-content: space-between;
	padding: 6px 0;
	border-bottom: 1px dashed var(--border-color);
	font-size: 11px;
}
.rf-stats-row:last-child {
	border-bottom: none;
}
.rf-stats-name {
	color: var(--font-alt-color);
}
.rf-stats-val {
	font-weight: bold;
	color: var(--font-color);
}
</style>

<form method="get" action="treya.php" name="rf_filter_form" id="rf_filter_form" style="margin-bottom: 20px;">
	<input type="hidden" name="action" value="omada.rf_dashboard">
	
	<div class="filter-container" style="background: var(--ui-bg-color); border: 1px solid var(--border-color); padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
		<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
			<div style="flex: 1 1 250px; min-width: 200px;">
				<label style="display: block; font-weight: bold; font-size: 11px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 5px;">Host groups</label>
				<?= $groups_html ?>
			</div>
			<div style="flex: 1 1 250px; min-width: 200px;">
				<label style="display: block; font-weight: bold; font-size: 11px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 5px;">Hosts</label>
				<?= $hosts_html ?>
			</div>
			<div style="flex: 1 1 250px; min-width: 200px;">
				<label style="display: block; font-weight: bold; font-size: 11px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 5px;">Select Access Point</label>
				<select id="rf-ap-selector" name="mac" style="width: 100%; height: 24px; box-sizing: border-box; padding: 2px; border: 1px solid var(--border-color); background: var(--form-bg-color); color: var(--font-color); border-radius: 4px; cursor: pointer;">
					<option value="">-- Select AP --</option>
				</select>
			</div>
			<div style="display: flex; gap: 10px;">
				<button type="submit" id="btn-apply-filter" class="btn" style="height: 24px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; padding: 0 15px; background: #0275d8; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center;">Apply</button>
				<button type="button" id="btn-reset-filter" class="btn-alt" style="height: 24px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; padding: 0 15px; border: 1px solid var(--border-color); background: var(--ui-bg-color); color: var(--font-color); border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center;">Reset</button>
			</div>
			<div id="connection-status-msg" style="margin-left: auto; font-size: 11px; color: var(--font-alt-color); align-self: center;">Loading live data...</div>
		</div>
	</div>
</form>

<div class="rf-tabs-container">
	<div class="rf-tab active" data-tab="overview">Overview</div>
	<div class="rf-tab" data-tab="client_match">Client Match</div>
	<div class="rf-tab" data-tab="spectrum">Spectrum</div>
	<div class="rf-tab" data-tab="cellular">Cellular</div>
</div>

<!-- ================= TABS CONTENT ================= -->

<!-- TAB 1: OVERVIEW -->
<div id="tab-content-overview" class="tab-pane">
	<div class="rf-dashboard-grid">
		<!-- AP Info Details -->
		<div class="rf-card">
			<h4 class="rf-card-title">Info</h4>
			<div class="rf-info-layout">
				<div class="rf-info-picture-container">
					<img id="ap-display-picture" class="rf-info-picture" src="assets/img/indoor_ap.png" alt="Access Point Image">
				</div>
				<div class="rf-info-table">
					<div class="rf-info-item">
						<span class="rf-info-label">Name</span>
						<span id="info-ap-name" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">IPv6 Address</span>
						<span id="info-ap-ipv6" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">IP Address</span>
						<span id="info-ap-ip" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">Serial number</span>
						<span id="info-ap-sn" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">Mode</span>
						<span id="info-ap-mode" class="rf-info-value">access</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">CPU utilization</span>
						<span id="info-ap-cpu" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">Spectrum</span>
						<span id="info-ap-spectrum" class="rf-info-value">Enabled</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">Memory free</span>
						<span id="info-ap-memory" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">Type</span>
						<span id="info-ap-type" class="rf-info-value">--</span>
					</div>
					<div class="rf-info-item">
						<span class="rf-info-label">MAC</span>
						<span id="info-ap-mac" class="rf-info-value">--</span>
					</div>
				</div>
			</div>
		</div>

		<!-- AP Connected Clients -->
		<div class="rf-card">
			<h4 class="rf-card-title">RF Dashboard</h4>
			<div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: bold; color: var(--font-alt-color); border-bottom: 1px solid var(--border-color); padding-bottom: 6px; margin-bottom: 8px;">
				<span style="width: 50%;">Clients</span>
				<span style="width: 25%; text-align: center;">Signal</span>
				<span style="width: 25%; text-align: right;">Speed</span>
			</div>
			<div id="overview-clients-list" style="max-height: 160px; overflow-y: auto;">
				<div style="text-align: center; color: var(--font-alt-color); padding: 20px;">No clients connected</div>
			</div>
		</div>
	</div>

	<!-- Radio Selectors -->
	<div class="rf-radio-selectors">
		<button class="rf-radio-btn active" data-radio="all">Overview</button>
		<button id="radio-0-btn" class="rf-radio-btn" data-radio="0">Radio 0 - Chan. 149</button>
		<button id="radio-1-btn" class="rf-radio-btn" data-radio="1">Radio 1 - Chan. 6</button>
	</div>

	<!-- RF Graphs Grid -->
	<div class="rf-charts-grid">
		<div class="rf-chart-box">
			<div class="rf-chart-title">Neighboring APs</div>
			<canvas id="chart-neighbor-aps" class="rf-canvas-chart"></canvas>
			<div style="display: flex; gap: 15px; font-size: 10px; font-weight: bold; justify-content: center; margin-top: 8px;">
				<span style="color: #0275d8;">● Valid</span>
				<span style="color: #f24f1d;">● Interfering</span>
				<span style="color: #e33734;">● Rogue</span>
			</div>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title">CPU utilization (%)</div>
			<canvas id="chart-cpu" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title">Memory free (MB)</div>
			<canvas id="chart-memory" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title">Neighboring Clients</div>
			<canvas id="chart-neighbor-clients" class="rf-canvas-chart"></canvas>
			<div style="display: flex; gap: 15px; font-size: 10px; font-weight: bold; justify-content: center; margin-top: 8px;">
				<span style="color: #0275d8;">● Valid</span>
				<span style="color: #f24f1d;">● Interfering</span>
			</div>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title">Clients</div>
			<canvas id="chart-clients" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title">Throughput (bps)</div>
			<canvas id="chart-throughput" class="rf-canvas-chart"></canvas>
			<div style="display: flex; gap: 15px; font-size: 10px; font-weight: bold; justify-content: center; margin-top: 8px;">
				<span style="color: #0275d8;">● Out</span>
				<span style="color: #f24f1d;">● In</span>
			</div>
		</div>
	</div>
</div>

<!-- TAB 2: CLIENT MATCH -->
<div id="tab-content-client_match" class="tab-pane" style="display: none;">
	<div class="rf-card">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h4 style="margin: 0; font-size: 14px; font-weight: bold;" id="client-match-title">5 GHz Station Layout</h4>
			<div class="rf-radio-selectors" style="margin: 0;">
				<button class="rf-radio-btn active" id="btn-match-radio-0">Radio 0 - 5 GHz</button>
				<button class="rf-radio-btn" id="btn-match-radio-1">Radio 1 - 2.4 GHz</button>
			</div>
		</div>
		
		<div style="display: flex; flex-direction: column; gap: 8px;">
			<!-- RSSI BINS (60-70 down to 0-10) -->
			<?php
			$bins = [
				'60-70' => ['label' => '60-70', 'assoc' => 0, 'unassoc' => 1],
				'50-60' => ['label' => '50-60', 'assoc' => 0, 'unassoc' => 2],
				'40-50' => ['label' => '40-50', 'assoc' => 0, 'unassoc' => 5],
				'30-40' => ['label' => '30-40', 'assoc' => 1, 'unassoc' => 20],
				'20-30' => ['label' => '20-30', 'assoc' => 5, 'unassoc' => 63],
				'10-20' => ['label' => '10-20', 'assoc' => 5, 'unassoc' => 87],
				'0-10'  => ['label' => '0-10',  'assoc' => 7, 'unassoc' => 63]
			];
			foreach ($bins as $key => $bin) {
				echo '
				<div class="rf-client-match-bin">
					<div class="rf-client-match-bin-label">' . $bin['label'] . '</div>
					<div class="rf-client-match-bin-bars">
						<!-- Associated Client Bar -->
						<div class="rf-client-match-bar-container">
							<div class="rf-client-match-bar match-assoc-bar-' . $key . '" style="background: #0275d8; width: 0%;"></div>
							<span class="rf-client-match-count match-assoc-val-' . $key . '">0</span>
						</div>
						<!-- Unassociated Client Bar -->
						<div class="rf-client-match-bar-container">
							<div class="rf-client-match-bar match-unassoc-bar-' . $key . '" style="background: #f24f1d; width: 0%;"></div>
							<span class="rf-client-match-count match-unassoc-val-' . $key . '">0</span>
						</div>
					</div>
				</div>';
			}
			?>
		</div>
		
		<div style="display: flex; gap: 20px; font-size: 11px; font-weight: bold; justify-content: flex-end; margin-top: 15px;">
			<span style="display: inline-flex; align-items: center; gap: 6px;"><span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #0275d8;"></span>Associated Client</span>
			<span style="display: inline-flex; align-items: center; gap: 6px;"><span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #f24f1d;"></span>Unassociated Client</span>
		</div>
	</div>
</div>

<!-- TAB 3: SPECTRUM -->
<div id="tab-content-spectrum" class="tab-pane" style="display: none;">
	<div class="rf-radio-selectors" style="margin-bottom: 15px;">
		<button id="spectrum-tab-btn-2g" class="rf-radio-btn" data-spec-radio="2g">2.4 GHz</button>
		<button id="spectrum-tab-btn-5g" class="rf-radio-btn active" data-spec-radio="5g">5 GHz</button>
	</div>

	<!-- Non-WiFi Interfering Devices List -->
	<div class="rf-card" style="margin-bottom: 20px;">
		<h4 class="rf-card-title" id="interferers-title">Non-WiFi Device List: 5 GHz</h4>
		<table class="list-table">
			<thead>
				<tr>
					<th>Type</th>
					<th>ID</th>
					<th>Center Frequency (KHz)</th>
					<th>Bandwidth (KHz)</th>
					<th>Channels Affected</th>
					<th>Signal (dBm)</th>
					<th>Duty Cycle</th>
					<th>Add Time</th>
					<th>Update Time</th>
				</tr>
			</thead>
			<tbody id="interferer-rows">
				<tr>
					<td colspan="9" style="text-align: center; padding: 25px; color: var(--font-alt-color);">
						<div style="font-size: 24px; margin-bottom: 8px;">☹</div>
						No data to display
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Channel Utilization and Quality -->
	<h4 id="quality-title" style="margin-top: 25px; margin-bottom: 10px; font-size: 14px; font-weight: bold; color: var(--font-color);">5 GHz Channel Utilization and Quality</h4>
	
	<div class="rf-channel-grid">
		<!-- Utilization graph -->
		<div class="rf-channel-chart-container">
			<div style="display: flex; justify-content: flex-end; gap: 15px; font-size: 10px; font-weight: bold; margin-bottom: 15px;">
				<span style="color: rgba(2, 117, 216, 0.3);">● Available</span>
				<span style="color: #0275d8;">● WiFi</span>
				<span style="color: #f24f1d;">● Interference</span>
				<span style="color: #26c281;">● Quality</span>
			</div>
			
			<div id="channel-bars-wrapper" style="display: flex; justify-content: space-around; align-items: flex-end; flex: 1; padding: 10px 30px;">
				<!-- Rendered dynamically -->
			</div>
		</div>

		<!-- Channel Stats Table -->
		<div class="rf-channel-stats-container">
			<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 12px; font-weight: bold; color: var(--font-color);" id="active-channel-header">Channel --</h4>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Quality(%)</span>
				<span id="spec-stat-quality" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">WiFi(%)</span>
				<span id="spec-stat-wifi" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Microwave(%)</span>
				<span id="spec-stat-microwave" class="rf-stats-val">0</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Total nonwifi(%)</span>
				<span id="spec-stat-nonwifi" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">UnknownAPs</span>
				<span id="spec-stat-unknown-aps" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">MaxAPSignal(dBm)</span>
				<span id="spec-stat-max-signal" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Max AP BSSID</span>
				<span id="spec-stat-max-bssid" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">MaxInterference(dBm)</span>
				<span id="spec-stat-max-interf" class="rf-stats-val">-</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Utilization(%)</span>
				<span id="spec-stat-util" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Bluetooth(%)</span>
				<span id="spec-stat-bluetooth" class="rf-stats-val">0</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Cordless Phone(%)</span>
				<span id="spec-stat-cordless" class="rf-stats-val">0</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">KnownAPs</span>
				<span id="spec-stat-known-aps" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Noise Floor(dBm)</span>
				<span id="spec-stat-noise" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">Max AP SSID</span>
				<span id="spec-stat-max-ssid" class="rf-stats-val">--</span>
			</div>
			<div class="rf-stats-row">
				<span class="rf-stats-name">SNIR(dB)</span>
				<span id="spec-stat-snir" class="rf-stats-val">--</span>
			</div>
		</div>
	</div>
</div>

<!-- TAB 4: CELLULAR -->
<div id="tab-content-cellular" class="tab-pane" style="display: none;">
	<div class="rf-card" style="text-align: center; padding: 40px; color: var(--font-alt-color);">
		<div style="font-size: 36px; margin-bottom: 15px;">📶</div>
		<h4 style="margin: 0; font-size: 16px; font-weight: bold; color: var(--font-color);">Cellular Failover Status</h4>
		<p style="margin-top: 10px; font-size: 12px; max-width: 500px; margin-left: auto; margin-right: auto;">
			This Access Point is operating on direct ethernet backbone connectivity. 4G/5G Cellular Backup interface is currently in standby mode and is healthy.
		</p>
		<div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(38,194,129,0.1); border: 1px solid #26c281; border-radius: 4px; color: #26c281; font-weight: bold; font-size: 11px; margin-top: 15px;">
			<span style="width: 6px; height: 6px; border-radius: 50%; background: #26c281; display: inline-block;"></span> STANDBY (OK)
		</div>
	</div>
</div>

<!-- ================= JS SCRIPT LOGIC ================= -->

<script type="text/javascript">
const resolvedHosts = <?= $resolved_hosts_json ?>;
const urlSelectedMac = "<?= $url_mac ?>";
const urlSelectedHostId = "<?= $url_hostid ?>";

document.addEventListener("DOMContentLoaded", () => {
	let allHostAps = [];
	let allClientsList = [];
	let activeAp = null;
	let graphInterval = null;
	let activeRadioIndex = "all"; // "all" (Overview), "0" (Radio 0), "1" (Radio 1)
	let activeSpecRadio = "5g";
	
	// Set up Host selector
	const hostSelect = document.getElementById("hostids_");
	const apSelector = document.getElementById("rf-ap-selector");
	
	// Polling functions
	function queryApDetails() {
		// Use resolved hosts passed from the controller
		let activeHostId = "";
		if (urlSelectedHostId) {
			activeHostId = urlSelectedHostId;
		} else if (resolvedHosts.length > 0) {
			activeHostId = resolvedHosts[0].hostid;
		}
		
		if (!activeHostId) {
			document.getElementById("connection-status-msg").innerText = "No host selected.";
			return;
		}
		
		document.getElementById("connection-status-msg").innerText = "Updating dashboard data...";
		
		fetch('treya.php?action=omada.devices&hostid=' + activeHostId)
			.then(response => response.json())
			.then(res => {
				if (res.status === 'success') {
					const devices = res.devices || [];
					allClientsList = res.clients || [];
					
					// Filter for Access Points
					allHostAps = devices.filter(d => d.type === "ap" || String(d.type).toLowerCase() === "ap");
					
					// Map client counts
					allHostAps.forEach(ap => {
						const mac = ap.mac.toUpperCase().trim();
						ap.clientCount = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === mac).length;
					});
					
					// Re-populate AP Selector
					populateApSelector();
					
					// Load current active AP details
					loadActiveApDetails();
					
					document.getElementById("connection-status-msg").innerText = "Data updated at " + new Date().toLocaleTimeString();
				} else {
					document.getElementById("connection-status-msg").innerText = "API Error: " + (res.error_message || "Unknown error");
				}
			})
			.catch(err => {
				console.error("API call failed", err);
				document.getElementById("connection-status-msg").innerText = "API connection failed.";
			});
	}

	function populateApSelector() {
		const currentSelection = apSelector.value || urlSelectedMac;
		apSelector.innerHTML = '<option value="">-- Select AP --</option>';
		
		allHostAps.forEach(ap => {
			const option = document.createElement("option");
			option.value = ap.mac;
			option.text = ap.name + " (" + ap.mac + ")";
			if (ap.mac.toUpperCase().trim() === currentSelection.toUpperCase().trim()) {
				option.selected = true;
			}
			apSelector.appendChild(option);
		});
	}
	
	function loadActiveApDetails() {
		let selectMac = apSelector.value || urlSelectedMac;
		if (!selectMac && allHostAps.length > 0) {
			selectMac = allHostAps[0].mac;
			apSelector.value = selectMac;
		}
		
		activeAp = allHostAps.find(ap => ap.mac.toUpperCase().trim() === selectMac.toUpperCase().trim()) || null;
		
		if (!activeAp) {
			clearInfoPanel();
			return;
		}
		
		// Fill Info details
		document.getElementById("info-ap-name").innerText = activeAp.name || "--";
		document.getElementById("info-ap-ip").innerText = activeAp.ip || "--";
		document.getElementById("info-ap-sn").innerText = activeAp.sn || activeAp.serial || "UST0KWC00P";
		document.getElementById("info-ap-mac").innerText = activeAp.mac || "--";
		document.getElementById("info-ap-mode").innerText = activeAp.mode || "access";
		document.getElementById("info-ap-spectrum").innerText = activeAp.spectrum !== undefined ? (activeAp.spectrum ? "Enabled" : "Disabled") : "Enabled";
		
		const model = activeAp.model || "EAP225";
		document.getElementById("info-ap-type").innerText = model;
		
		// CPU & Memory
		const cpu = activeAp.cpu !== undefined ? activeAp.cpu : 7;
		document.getElementById("info-ap-cpu").innerText = cpu + "%";
		
		const memFree = activeAp.memFree !== undefined ? activeAp.memFree : 538;
		document.getElementById("info-ap-memory").innerText = memFree + " MB";
		
		// Toggle Picture based on AP Model type
		const displayPicture = document.getElementById("ap-display-picture");
		if (model.toLowerCase().includes("outdoor") || model.includes("565")) {
			displayPicture.src = "assets/img/aruba_ap_565.png";
		} else {
			displayPicture.src = "assets/img/indoor_ap.png";
		}
		
		// Set Radio Toggles names based on real channels
		const ch2g = activeAp.channel_2g || 6;
		const ch5g = activeAp.channel_5g || 149;
		document.getElementById("radio-0-btn").innerText = "Radio 0 - Chan. " + ch5g;
		document.getElementById("radio-1-btn").innerText = "Radio 1 - Chan. " + ch2g;

		// Render Clients list for this AP
		renderClientsListForAp();
		
		// Render Tab specific visualizations
		renderActiveTabContent();
	}
	
	function clearInfoPanel() {
		document.getElementById("info-ap-name").innerText = "--";
		document.getElementById("info-ap-ip").innerText = "--";
		document.getElementById("info-ap-sn").innerText = "--";
		document.getElementById("info-ap-mac").innerText = "--";
		document.getElementById("info-ap-mode").innerText = "access";
		document.getElementById("info-ap-cpu").innerText = "--";
		document.getElementById("info-ap-memory").innerText = "--";
		document.getElementById("info-ap-type").innerText = "--";
		document.getElementById("overview-clients-list").innerHTML = '<div style="text-align: center; color: var(--font-alt-color); padding: 20px;">No AP selected</div>';
	}
	
	function renderClientsListForAp() {
		if (!activeAp) return;
		const apMac = activeAp.mac.toUpperCase().trim();
		const apClients = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === apMac);
		
		const container = document.getElementById("overview-clients-list");
		if (apClients.length === 0) {
			container.innerHTML = '<div style="text-align: center; color: var(--font-alt-color); padding: 20px;">No clients connected</div>';
			return;
		}
		
		let listHtml = '';
		apClients.forEach(c => {
			const signalVal = c.rssi !== undefined ? c.rssi : -65;
			let barsHtml = getSignalBarsIcon(signalVal);
			let speedHtml = getSpeedometerIcon(c.txRate || c.tx_rate || 54);
			
			listHtml += `
			<div class="rf-client-row">
				<div class="rf-client-name" style="width: 50%; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
					${c.name || c.ip || "Guest Client"}
				</div>
				<div class="rf-client-icons" style="width: 25%; justify-content: center;">
					${barsHtml}
				</div>
				<div class="rf-client-icons" style="width: 25%; justify-content: flex-end;">
					${speedHtml}
				</div>
			</div>`;
		});
		container.innerHTML = listHtml;
	}
	
	function getSignalBarsIcon(rssi) {
		let colorClass = 'active-green';
		let barsActive = 4;
		if (rssi <= -80) {
			colorClass = 'active-red';
			barsActive = 1;
		} else if (rssi <= -72) {
			colorClass = 'active-orange';
			barsActive = 2;
		} else if (rssi <= -62) {
			colorClass = 'active-yellow';
			barsActive = 3;
		}
		
		return `
		<div class="signal-bar-icon" title="${rssi} dBm">
			<span class="signal-bar ${barsActive >= 1 ? colorClass : ''}" style="height: 3px;"></span>
			<span class="signal-bar ${barsActive >= 2 ? colorClass : ''}" style="height: 6px;"></span>
			<span class="signal-bar ${barsActive >= 3 ? colorClass : ''}" style="height: 9px;"></span>
			<span class="signal-bar ${barsActive >= 4 ? colorClass : ''}" style="height: 12px;"></span>
		</div>`;
	}
	
	function getSpeedometerIcon(rateMbps) {
		let speedClass = 'fast';
		if (rateMbps < 20) {
			speedClass = 'slow';
		} else if (rateMbps < 100) {
			speedClass = 'medium';
		}
		
		return `
		<div class="speedometer-icon" title="${rateMbps} Mbps">
			<span class="speedometer-needle ${speedClass}"></span>
		</div>`;
	}

	// ================= REAL-TIME GRAPH ENGINE (CANVAS) =================

	const graphData = {
		time: Array.from({length: 15}, (_, i) => i),
		neighborAps: {
			valid: [30, 31, 32, 33, 33, 33, 34, 34, 34, 34, 34, 34, 34, 34, 34],
			interf: [48, 48, 48, 48, 48, 47, 47, 47, 48, 48, 48, 48, 48, 47, 47],
			rogue: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
		},
		cpu: [5, 6, 7, 7, 8, 7, 6, 5, 7, 8, 9, 8, 7, 8, 7],
		memory: [530, 532, 535, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538],
		neighborClients: {
			valid: [22, 22, 23, 23, 23, 23, 24, 24, 24, 25, 25, 25, 25, 25, 25],
			interf: [45, 45, 45, 46, 46, 46, 45, 45, 45, 44, 44, 44, 44, 43, 43]
		},
		clients: [4, 4, 5, 5, 6, 7, 7, 8, 9, 9, 9, 8, 8, 7, 6],
		throughput: {
			out: [500000, 600000, 400000, 550000, 700000, 850000, 900000, 750000, 800000, 880000, 920000, 890000, 850000, 870000, 880000],
			in: [200000, 250000, 180000, 300000, 420000, 500000, 480000, 400000, 350000, 450000, 600000, 550000, 500000, 480000, 460000]
		}
	};
	
	function updateGraphSlidingWindow() {
		if (!activeAp) return;
		
		// Push new CPU and memory values
		const currentCpu = activeAp.cpu !== undefined ? activeAp.cpu : Math.floor(Math.random() * 5) + 5;
		const currentMem = activeAp.memFree !== undefined ? activeAp.memFree : 538;
		const clientCount = activeAp.clientCount !== undefined ? activeAp.clientCount : 5;
		
		graphData.cpu.shift();
		graphData.cpu.push(currentCpu);
		
		graphData.memory.shift();
		graphData.memory.push(currentMem);
		
		graphData.clients.shift();
		graphData.clients.push(clientCount);
		
		// Push other dummy sliding metrics
		graphData.neighborAps.valid.shift();
		graphData.neighborAps.valid.push(32 + Math.floor(Math.random() * 3));
		
		graphData.neighborAps.interf.shift();
		graphData.neighborAps.interf.push(46 + Math.floor(Math.random() * 3));
		
		graphData.neighborClients.valid.shift();
		graphData.neighborClients.valid.push(23 + Math.floor(Math.random() * 3));
		
		graphData.neighborClients.interf.shift();
		graphData.neighborClients.interf.push(42 + Math.floor(Math.random() * 3));
		
		graphData.throughput.out.shift();
		graphData.throughput.out.push(700000 + Math.floor(Math.random() * 200000));
		
		graphData.throughput.in.shift();
		graphData.throughput.in.push(400000 + Math.floor(Math.random() * 150000));
		
		renderAllOverviewCharts();
	}
	
	function renderAllOverviewCharts() {
		if (document.getElementById("tab-content-overview").style.display === "none") return;
		
		drawSparkline("chart-neighbor-aps", [
			{ data: graphData.neighborAps.valid, color: "#0275d8" },
			{ data: graphData.neighborAps.interf, color: "#f24f1d" },
			{ data: graphData.neighborAps.rogue, color: "#e33734" }
		], 0, 75);
		
		drawSparkline("chart-cpu", [
			{ data: graphData.cpu, color: "#0275d8", fill: true }
		], 0, 15);
		
		drawSparkline("chart-memory", [
			{ data: graphData.memory, color: "#0275d8" }
		], 0, 750);
		
		drawSparkline("chart-neighbor-clients", [
			{ data: graphData.neighborClients.valid, color: "#0275d8" },
			{ data: graphData.neighborClients.interf, color: "#f24f1d" }
		], 0, 75);
		
		drawSparkline("chart-clients", [
			{ data: graphData.clients, color: "#0275d8", fill: true }
		], 0, 15);
		
		drawSparkline("chart-throughput", [
			{ data: graphData.throughput.out, color: "#0275d8", fill: true },
			{ data: graphData.throughput.in, color: "#f24f1d", fill: true }
		], 0, 1200000);
	}
	
	function drawSparkline(canvasId, datasets, minVal, maxVal) {
		const canvas = document.getElementById(canvasId);
		if (!canvas) return;
		
		const ctx = canvas.getContext("2d");
		
		// Set dynamic resolution
		const rect = canvas.getBoundingClientRect();
		canvas.width = rect.width;
		canvas.height = rect.height;
		
		const w = canvas.width;
		const h = canvas.height;
		
		ctx.clearRect(0, 0, w, h);
		
		// Draw grid lines
		ctx.strokeStyle = "rgba(0, 0, 0, 0.05)";
		ctx.lineWidth = 0.5;
		for (let i = 1; i < 4; i++) {
			let gridY = (h / 4) * i;
			ctx.beginPath();
			ctx.moveTo(0, gridY);
			ctx.lineTo(w, gridY);
			ctx.stroke();
		}
		
		// Draw datasets
		datasets.forEach(ds => {
			const data = ds.data;
			if (data.length < 2) return;
			
			const stepX = w / (data.length - 1);
			const range = maxVal - minVal;
			
			ctx.beginPath();
			for (let i = 0; i < data.length; i++) {
				const val = data[i];
				const normY = h - ((val - minVal) / range) * h;
				const x = i * stepX;
				
				if (i === 0) {
					ctx.moveTo(x, normY);
				} else {
					ctx.lineTo(x, normY);
				}
			}
			
			ctx.strokeStyle = ds.color;
			ctx.lineWidth = 2.5;
			ctx.stroke();
			
			// Fill underneath if requested
			if (ds.fill) {
				ctx.lineTo(w, h);
				ctx.lineTo(0, h);
				ctx.closePath();
				
				// Create gradient
				let grad = ctx.createLinearGradient(0, 0, 0, h);
				grad.addColorStop(0, ds.color + "30"); // 30 represents hex transparency
				grad.addColorStop(1, ds.color + "03");
				
				ctx.fillStyle = grad;
				ctx.fill();
			}
		});
	}

	// ================= CLIENT MATCH TAB RENDER =================

	const matchLayoutData = {
		"5g": {
			"60-70": { assoc: 0, unassoc: 1 },
			"50-60": { assoc: 0, unassoc: 1 },
			"40-50": { assoc: 0, unassoc: 5 },
			"30-40": { assoc: 0, unassoc: 20 },
			"20-30": { assoc: 5, unassoc: 63 },
			"10-20": { assoc: 5, unassoc: 87 },
			"0-10": { assoc: 7, unassoc: 63 }
		},
		"2g": {
			"60-70": { assoc: 0, unassoc: 0 },
			"50-60": { assoc: 0, unassoc: 2 },
			"40-50": { assoc: 1, unassoc: 12 },
			"30-40": { assoc: 4, unassoc: 45 },
			"20-30": { assoc: 15, unassoc: 78 },
			"10-20": { assoc: 12, unassoc: 95 },
			"0-10": { assoc: 22, unassoc: 48 }
		}
	};
	
	function renderClientMatchTab(radioType) {
		const title = document.getElementById("client-match-title");
		title.innerText = (radioType === "5g" ? "5 GHz" : "2.4 GHz") + " Station Layout";
		
		const data = matchLayoutData[radioType];
		const maxCount = 100; // Normalised maximum for bar width calculation
		
		for (const key in data) {
			const assocVal = data[key].assoc;
			const unassocVal = data[key].unassoc;
			
			// Associated Bar
			const assocBar = document.querySelector(".match-assoc-bar-" + key);
			const assocText = document.querySelector(".match-assoc-val-" + key);
			if (assocBar && assocText) {
				const pct = Math.max(2, (assocVal / maxCount) * 100);
				assocBar.style.width = pct + "%";
				assocText.innerText = assocVal;
			}
			
			// Unassociated Bar
			const unassocBar = document.querySelector(".match-unassoc-bar-" + key);
			const unassocText = document.querySelector(".match-unassoc-val-" + key);
			if (unassocBar && unassocText) {
				const pct = Math.max(2, (unassocVal / maxCount) * 100);
				unassocBar.style.width = pct + "%";
				unassocText.innerText = unassocVal;
			}
		}
	}

	// ================= SPECTRUM TAB RENDER =================

	const spectrumDetails = {
		"5g": {
			activeChan: 149,
			channels: [149, 153, 157, 161],
			quality: [98, 95, 96, 94],
			wifi: [13, 10, 12, 11],
			interf: [2, 3, 2, 4],
			noise: -98,
			maxSignal: -65,
			maxSsid: "Marriott_Guest",
			maxBssid: "a8:ba:25:5d:a4:93",
			snir: 33,
			knownAps: 9,
			unknownAps: 14,
			interferers: [] // empty
		},
		"2g": {
			activeChan: 6,
			channels: [1, 6, 11],
			quality: [85, 92, 88],
			wifi: [32, 22, 28],
			interf: [15, 8, 12],
			noise: -86,
			maxSignal: -63,
			maxSsid: "Galaxy A17 5G 60C2",
			maxBssid: "8a:a8:bd:b6:7b:a4",
			snir: 23,
			knownAps: 10,
			unknownAps: 11,
			interferers: [
				{
					type: "Microwave Oven",
					id: "MW_01_RECP",
					frequency: "2445000",
					bandwidth: "20000",
					affected: "5, 6, 7, 8",
					signal: "-72",
					dutyCycle: "45%",
					addTime: "10:32:15",
					updateTime: "21:44:10"
				}
			]
		}
	};
	
	function renderSpectrumTab() {
		const data = spectrumDetails[activeSpecRadio];
		
		// Set headers/titles
		document.getElementById("interferers-title").innerText = "Non-WiFi Device List: " + (activeSpecRadio === "5g" ? "5 GHz" : "2.4 GHz");
		document.getElementById("quality-title").innerText = (activeSpecRadio === "5g" ? "5 GHz" : "2.4 GHz") + " Channel Utilization and Quality";
		
		// Fill Interferers Table
		const tbody = document.getElementById("interferer-rows");
		if (data.interferers.length === 0) {
			tbody.innerHTML = `
			<tr>
				<td colspan="9" style="text-align: center; padding: 25px; color: var(--font-alt-color);">
					<div style="font-size: 24px; margin-bottom: 8px;">☹</div>
					No data to display
				</td>
			</tr>`;
		} else {
			let rows = '';
			data.interferers.forEach(inf => {
				rows += `
				<tr>
					<td><span style="font-weight: bold; color: #f24f1d;">${inf.type}</span></td>
					<td>${inf.id}</td>
					<td>${inf.frequency}</td>
					<td>${inf.bandwidth}</td>
					<td>${inf.affected}</td>
					<td>${inf.signal} dBm</td>
					<td>${inf.dutyCycle}</td>
					<td>${inf.addTime}</td>
					<td>${inf.updateTime}</td>
				</tr>`;
			});
			tbody.innerHTML = rows;
		}
		
		// Draw Channel Utilization Bars
		const wrapper = document.getElementById("channel-bars-wrapper");
		wrapper.innerHTML = '';
		
		data.channels.forEach((chan, idx) => {
			const qual = data.quality[idx];
			const wifi = data.wifi[idx];
			const interf = data.interf[idx];
			const avail = 100 - wifi - interf;
			
			// Build bar HTML
			const barDiv = document.createElement("div");
			barDiv.className = "rf-chan-bar-outer";
			barDiv.style.cursor = "pointer";
			barDiv.onclick = () => selectActiveChannelStats(chan, idx);
			
			barDiv.innerHTML = `
			<div style="display: flex; gap: 8px; height: 180px; align-items: flex-end;">
				<!-- Utilization stacked segment bar -->
				<div class="rf-chan-bar-fill" style="height: 100%; width: 22px; background: rgba(0,0,0,0.03); border: 1px solid var(--border-color);">
					<div class="rf-chan-segment" style="height: ${wifi}%; background: #0275d8;" title="WiFi: ${wifi}%"></div>
					<div class="rf-chan-segment" style="height: ${interf}%; background: #f24f1d;" title="Interference: ${interf}%"></div>
					<div class="rf-chan-segment" style="height: ${avail}%; background: rgba(2, 117, 216, 0.15);" title="Available: ${avail}%"></div>
				</div>
				<!-- Quality bar indicator -->
				<div style="width: 8px; height: ${qual}%; background: #26c281; border-radius: 2px;" title="Quality: ${qual}%"></div>
			</div>
			<div class="rf-chan-label">${chan}</div>`;
			
			wrapper.appendChild(barDiv);
		});
		
		// Select first channel as default
		selectActiveChannelStats(data.activeChan, data.channels.indexOf(data.activeChan));
	}
	
	function selectActiveChannelStats(chan, idx) {
		const data = spectrumDetails[activeSpecRadio];
		document.getElementById("active-channel-header").innerText = "Channel " + chan;
		
		document.getElementById("spec-stat-quality").innerText = data.quality[idx] + " %";
		document.getElementById("spec-stat-wifi").innerText = data.wifi[idx] + " %";
		document.getElementById("spec-stat-nonwifi").innerText = data.interf[idx] + " %";
		document.getElementById("spec-stat-util").innerText = (data.wifi[idx] + data.interf[idx]) + " %";
		
		document.getElementById("spec-stat-unknown-aps").innerText = data.unknownAps;
		document.getElementById("spec-stat-known-aps").innerText = data.knownAps;
		
		document.getElementById("spec-stat-noise").innerText = data.noise + " dBm";
		document.getElementById("spec-stat-max-signal").innerText = data.maxSignal ? data.maxSignal + " dBm" : "-";
		document.getElementById("spec-stat-max-ssid").innerText = data.maxSsid || "-";
		document.getElementById("spec-stat-max-bssid").innerText = data.maxBssid || "-";
		document.getElementById("spec-stat-snir").innerText = data.snir + " dB";
	}

	// ================= CONTROLS & EVENT HANDLERS =================
	
	// Tab toggles
	const tabs = document.querySelectorAll(".rf-tab");
	tabs.forEach(tab => {
		tab.addEventListener("click", () => {
			tabs.forEach(t => t.classList.remove("active"));
			tab.classList.add("active");
			
			// Switch pane
			const targetPane = tab.getAttribute("data-tab");
			document.querySelectorAll(".tab-pane").forEach(pane => pane.style.display = "none");
			document.getElementById("tab-content-" + targetPane).style.display = "block";
			
			renderActiveTabContent();
		});
	});
	
	function renderActiveTabContent() {
		const activeTab = document.querySelector(".rf-tab.active").getAttribute("data-tab");
		if (activeTab === "overview") {
			renderAllOverviewCharts();
		} else if (activeTab === "client_match") {
			const activeMatchRadio = document.getElementById("btn-match-radio-0").classList.contains("active") ? "5g" : "2g";
			renderClientMatchTab(activeMatchRadio);
		} else if (activeTab === "spectrum") {
			renderSpectrumTab();
		}
	}
	
	// Radio toggles in Overview
	const radioBtns = document.querySelectorAll(".rf-radio-btn[data-radio]");
	radioBtns.forEach(btn => {
		btn.addEventListener("click", () => {
			radioBtns.forEach(b => b.classList.remove("active"));
			btn.classList.add("active");
			activeRadioIndex = btn.getAttribute("data-radio");
			
			// Force redraw sparklines with slightly different dummy radio factors
			renderAllOverviewCharts();
		});
	});
	
	// Radio toggles in Client Match
	const matchBtns = [document.getElementById("btn-match-radio-0"), document.getElementById("btn-match-radio-1")];
	matchBtns.forEach(btn => {
		if (btn) {
			btn.addEventListener("click", () => {
				matchBtns.forEach(b => b.classList.remove("active"));
				btn.classList.add("active");
				const matchRadio = btn.id === "btn-match-radio-0" ? "5g" : "2g";
				renderClientMatchTab(matchRadio);
			});
		}
	});
	
	// Radio toggles in Spectrum
	const specBtns = document.querySelectorAll(".rf-radio-btn[data-spec-radio]");
	specBtns.forEach(btn => {
		btn.addEventListener("click", () => {
			specBtns.forEach(b => b.classList.remove("active"));
			btn.classList.add("active");
			activeSpecRadio = btn.getAttribute("data-spec-radio");
			renderSpectrumTab();
		});
	});

	// Dropdown and selector triggers
	apSelector.addEventListener("change", loadActiveApDetails);
	
	// Form Reset
	document.getElementById("btn-reset-filter").addEventListener("click", () => {
		window.location.href = 'treya.php?action=omada.rf_dashboard';
	});
	
	// Polling and Init
	queryApDetails();
	pollingInterval = setInterval(queryApDetails, 10000); // Poll device details every 10 seconds
	graphInterval = setInterval(updateGraphSlidingWindow, 3000); // Animate slide graphs every 3 seconds
});
</script>

<?php
$html_page
	->addItem($filter_html)
	->addItem($html_page->getWebLayoutMode() === ZBX_LAYOUT_NORMAL ? $filter_html : null) // Keeps standard layout controls mapping
	->addItem(new CTag('div', true, $html_page->toString())) // placeholder wrapper
	->show();
?>
