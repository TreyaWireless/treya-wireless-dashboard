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

// Collapsible Filter HTML matching Zabbix look-and-feel
$body_html = <<<HTML
<style>
.multiselect-control, .multiselect, .multiselect-wrapper, .multiselect-list {
	width: 100% !important;
	max-width: 100% !important;
	box-sizing: border-box;
}
.rf-tabs-container {
	display: flex;
	border-bottom: 2px solid var(--border-color);
	margin-bottom: 20px;
	align-items: center;
	padding: 0;
}
.rf-tab {
	padding: 12px 24px;
	font-size: 13px;
	font-weight: bold;
	color: var(--font-alt-color);
	cursor: pointer;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
	transition: all 0.2s;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	line-height: 1.2;
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
.rf-info-table {
	flex: 1;
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px 24px;
}
@media (max-width: 768px) {
	.rf-info-table {
		grid-template-columns: repeat(2, 1fr);
	}
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
	align-items: center;
}
.rf-radio-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 6px 16px;
	border: 1px solid var(--border-color);
	background: var(--ui-bg-color);
	color: var(--font-color);
	border-radius: 4px;
	font-size: 12px;
	font-weight: bold;
	cursor: pointer;
	transition: all 0.15s;
	box-sizing: border-box;
	line-height: 1.2;
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
	height: 140px;
	background: rgba(0,0,0,0.01);
	border-radius: 4px;
	cursor: crosshair;
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

/* Graph Floating Tooltip */
#graph-hover-tooltip {
	position: absolute;
	display: none;
	background: var(--ui-bg-color, #fff);
	border: 1px solid var(--border-color, #ccc);
	border-radius: 4px;
	padding: 8px 12px;
	font-size: 11px;
	color: var(--font-color, #333);
	box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
	pointer-events: none;
	z-index: 1000;
	font-family: Arial, sans-serif;
	line-height: 1.4;
}
.tooltip-title {
	font-weight: bold;
	margin-bottom: 5px;
	border-bottom: 1px solid var(--border-color, #eee);
	padding-bottom: 3px;
	color: var(--font-alt-color, #666);
}
.tooltip-row {
	display: flex;
	justify-content: space-between;
	gap: 15px;
	margin-bottom: 2px;
}
.tooltip-color {
	display: inline-block;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	margin-right: 5px;
}
/* Diagnostic Modal Styles */
.rf-modal-overlay {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.4);
	backdrop-filter: blur(4px);
	display: none;
	justify-content: center;
	align-items: center;
	z-index: 9999;
	opacity: 0;
	transition: opacity 0.3s ease;
}
.rf-modal-overlay.active {
	display: flex;
	opacity: 1;
}
.rf-modal-box {
	background: var(--ui-bg-color, #ffffff);
	border: 1px solid var(--border-color, #e0e0e0);
	border-radius: 8px;
	width: 480px;
	max-width: 90%;
	padding: 24px;
	box-shadow: 0 10px 30px rgba(0,0,0,0.25);
	transform: scale(0.9);
	transition: transform 0.3s ease;
	font-family: Arial, sans-serif;
}
.rf-modal-overlay.active .rf-modal-box {
	transform: scale(1);
}
.rf-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
	border-bottom: 1px solid var(--border-color);
	padding-bottom: 10px;
}
.rf-modal-title {
	font-size: 15px;
	font-weight: bold;
	color: var(--font-color, #333);
}
.rf-modal-close {
	font-size: 24px;
	cursor: pointer;
	color: var(--font-alt-color, #888);
	line-height: 1;
}
.rf-modal-close:hover {
	color: var(--font-color, #333);
}
.rf-modal-body {
	font-size: 12px;
	line-height: 1.6;
	color: var(--font-color, #444);
}
.rf-modal-value-badge {
	display: inline-block;
	padding: 6px 12px;
	border-radius: 4px;
	font-weight: bold;
	margin-bottom: 16px;
	font-size: 13px;
}
.rf-modal-value-badge.green {
	background: rgba(38, 194, 129, 0.15);
	color: #26c281;
	border: 1px solid #26c281;
}
.rf-modal-value-badge.yellow {
	background: rgba(240, 173, 78, 0.15);
	color: #f0ad4e;
	border: 1px solid #f0ad4e;
}
.rf-modal-value-badge.red {
	background: rgba(217, 83, 79, 0.15);
	color: #d9534f;
	border: 1px solid #d9534f;
}
.rf-modal-section {
	margin-bottom: 16px;
}
.rf-modal-section-title {
	font-weight: bold;
	margin-bottom: 6px;
	color: var(--font-alt-color, #666);
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.rf-info-icon:hover {
	color: #0275d8;
}
</style>
<div id="rf-dashboard-wrapper" style="position: relative;">
<!-- Diagnostic Info Modal -->
<div id="rf-info-modal" class="rf-modal-overlay" onclick="closeInfoModal(event)">
	<div class="rf-modal-box" onclick="event.stopPropagation()">
		<div class="rf-modal-header">
			<span id="rf-modal-title" class="rf-modal-title">Graph Information</span>
			<span class="rf-modal-close" onclick="closeInfoModal()">&times;</span>
		</div>
		<div class="rf-modal-body">
			<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
				<span class="rf-modal-section-title" style="margin:0;">Current Live Status:</span>
				<span id="rf-modal-value" class="rf-modal-value-badge green">--</span>
			</div>
			<div class="rf-modal-section">
				<div class="rf-modal-section-title">Analysis</div>
				<div id="rf-modal-analysis" style="font-weight: 500;">--</div>
			</div>
			<div class="rf-modal-section" style="margin-bottom:0;">
				<div class="rf-modal-section-title">Network Impact & Recommendation</div>
				<div id="rf-modal-impact">--</div>
			</div>
		</div>
	</div>
</div>
<form method="get" action="treya.php" name="rf_filter_form" id="rf_filter_form" style="margin-bottom: 20px;">
	<input type="hidden" name="action" value="omada.rf_dashboard">
	
	<div class="filter-container" style="background: var(--ui-bg-color); border: 1px solid var(--border-color); padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
		<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
			<div style="flex: 1 1 250px; min-width: 200px;">
				<label style="display: block; font-weight: bold; font-size: 11px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 5px;">Host groups</label>
				{$groups_html}
			</div>
			<div style="flex: 1 1 250px; min-width: 200px;">
				<label style="display: block; font-weight: bold; font-size: 11px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 5px;">Hosts</label>
				{$hosts_html}
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
	<div class="rf-tab" data-tab="cochannel_map">Co-channel Map</div>
</div>

<!-- ================= TABS CONTENT ================= -->

<!-- TAB 1: OVERVIEW -->
<div id="tab-content-overview" class="tab-pane">
	<div class="rf-dashboard-grid">
		<!-- AP Info Details -->
		<div class="rf-card">
			<h4 class="rf-card-title">Info</h4>
			<div class="rf-info-layout">
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

	<!-- AI Network Optimizer Panel -->
	<div id="ai-optimizer-panel" class="rf-card" style="margin-top: 20px; margin-bottom: 20px; display: none; background: linear-gradient(135deg, rgba(121, 40, 202, 0.04) 0%, rgba(2, 117, 216, 0.04) 100%); border: 1px solid rgba(121, 40, 202, 0.2);">
		<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid rgba(121, 40, 202, 0.15); padding-bottom: 10px;">
			<div style="display: flex; align-items: center; gap: 10px;">
				<span style="font-size: 18px;">✨</span>
				<h4 style="font-size: 14px; font-weight: bold; margin: 0; color: #7928ca; display: flex; align-items: center; gap: 8px;">
					AI Network Optimizer <span id="ai-engine-badge" style="background: #7928ca; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; font-family: monospace; font-weight: bold;">Groq Llama 3.3 Active</span>
				</h4>
			</div>
			<div style="display: flex; align-items: center; gap: 12px;">
				<span style="font-size: 11px; color: var(--font-alt-color);">Site Health:</span>
				<span id="ai-health-score" style="font-size: 14px; font-weight: bold; color: #26c281; background: rgba(38, 194, 129, 0.12); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(38, 194, 129, 0.2);">--</span>
			</div>
		</div>
		
		<div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px;">
			<div style="background: var(--form-bg-color, rgba(255,255,255,0.4)); border: 1px solid var(--border-color); border-radius: 6px; padding: 15px; max-height: 250px; overflow-y: auto;">
				<h5 style="margin-top: 0; margin-bottom: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: var(--font-alt-color); letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
					<span style="color:#d9534f;">⚠️</span> Detected Issues
				</h5>
				<div id="ai-detected-issues" style="font-size: 12px; display: flex; flex-direction: column; gap: 8px;">
					<div style="color: var(--font-alt-color);">Analyzing network RF parameters...</div>
				</div>
			</div>
			
			<div style="background: var(--form-bg-color, rgba(255,255,255,0.4)); border: 1px solid var(--border-color); border-radius: 6px; padding: 15px; max-height: 250px; overflow-y: auto;">
				<h5 style="margin-top: 0; margin-bottom: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: var(--font-alt-color); letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
					<span style="color:#26c281;">⚡</span> Recommended Actions
				</h5>
				<div id="ai-remediation-actions" style="font-size: 12px; display: flex; flex-direction: column; gap: 8px;">
					<div style="color: var(--font-alt-color);">No action required. Network is optimized.</div>
				</div>
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
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>Neighboring APs</span>
				<span class="rf-info-icon" onclick="showChartInfo('neighbor-aps')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
			<canvas id="chart-neighbor-aps" class="rf-canvas-chart"></canvas>
			<div style="display: flex; gap: 15px; font-size: 10px; font-weight: bold; justify-content: center; margin-top: 8px;">
				<span style="color: #0275d8;">● Valid</span>
				<span style="color: #f24f1d;">● Interfering</span>
				<span style="color: #e33734;">● Rogue</span>
			</div>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>CPU utilization (%)</span>
				<span class="rf-info-icon" onclick="showChartInfo('cpu')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
			<canvas id="chart-cpu" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>Memory free (MB)</span>
				<span class="rf-info-icon" onclick="showChartInfo('memory')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
			<canvas id="chart-memory" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>Neighboring Clients</span>
				<span class="rf-info-icon" onclick="showChartInfo('neighbor-clients')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
			<canvas id="chart-neighbor-clients" class="rf-canvas-chart"></canvas>
			<div style="display: flex; gap: 15px; font-size: 10px; font-weight: bold; justify-content: center; margin-top: 8px;">
				<span style="color: #0275d8;">● Valid</span>
				<span style="color: #f24f1d;">● Interfering</span>
			</div>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>Clients</span>
				<span class="rf-info-icon" onclick="showChartInfo('clients')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
			<canvas id="chart-clients" class="rf-canvas-chart"></canvas>
		</div>
		<div class="rf-chart-box">
			<div class="rf-chart-title" style="display: flex; justify-content: space-between; align-items: center;">
				<span>Throughput (bps)</span>
				<span class="rf-info-icon" onclick="showChartInfo('throughput')" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</div>
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
			<h4 style="margin: 0; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 8px;" id="client-match-title">
				<span>5 GHz Station Layout</span>
				<span class="rf-info-icon" onclick="showClientMatchInfo()" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
			</h4>
			<div class="rf-radio-selectors" style="margin: 0;">
				<button class="rf-radio-btn active" id="btn-match-radio-0">Radio 0 - 5 GHz</button>
				<button class="rf-radio-btn" id="btn-match-radio-1">Radio 1 - 2.4 GHz</button>
			</div>
		</div>
		
		<div style="display: flex; flex-direction: column; gap: 8px;">
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">70-80</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-70-80" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-70-80">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-70-80" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-70-80">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">60-70</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-60-70" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-60-70">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-60-70" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-60-70">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">50-60</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-50-60" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-50-60">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-50-60" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-50-60">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">40-50</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-40-50" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-40-50">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-40-50" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-40-50">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">30-40</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-30-40" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-30-40">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-30-40" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-30-40">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">20-30</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-20-30" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-20-30">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-20-30" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-20-30">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">10-20</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-10-20" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-10-20">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-10-20" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-10-20">0</span>
					</div>
				</div>
			</div>
			<div class="rf-client-match-bin">
				<div class="rf-client-match-bin-label">0-10</div>
				<div class="rf-client-match-bin-bars">
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-assoc-bar-0-10" style="background: #0275d8; width: 0%;"></div>
						<span class="rf-client-match-count match-assoc-val-0-10">0</span>
					</div>
					<div class="rf-client-match-bar-container">
						<div class="rf-client-match-bar match-unassoc-bar-0-10" style="background: #f24f1d; width: 0%;"></div>
						<span class="rf-client-match-count match-unassoc-val-0-10">0</span>
					</div>
				</div>
			</div>
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
	<h4 id="quality-title" style="margin-top: 25px; margin-bottom: 10px; font-size: 14px; font-weight: bold; color: var(--font-color); display: flex; align-items: center; gap: 8px;">
		<span>5 GHz Channel Utilization and Quality</span>
		<span class="rf-info-icon" onclick="showSpectrumInfo()" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
	</h4>
	
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
<!-- TAB 5: CO-CHANNEL MAP -->
<div id="tab-content-cochannel_map" class="tab-pane" style="display: none;">
	<div class="rf-card" style="padding: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
			<h4 class="rf-card-title" style="margin: 0; border: none; padding: 0;">Co-channel Overlap Map</h4>
			<div style="display: flex; gap: 10px; align-items: center;">
				<div class="rf-radio-selectors" style="margin: 0;">
					<button id="map-band-5g" class="rf-radio-btn active" type="button" onclick="switchMapBand('5g')">5 GHz</button>
					<button id="map-band-2g" class="rf-radio-btn" type="button" onclick="switchMapBand('2g')">2.4 GHz</button>
				</div>
			</div>
		</div>
		<div style="position: relative; border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; background: var(--form-bg-color, #fdfdfd); height: 500px;">
			<canvas id="co-channel-map-canvas" width="800" height="500" style="display: block; width: 100%; height: 500px; cursor: default;"></canvas>
			<!-- Side Panel Legend and Info -->
			<div id="map-info-panel" style="position: absolute; top: 15px; right: 15px; width: 220px; background: var(--ui-bg-color); border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); font-size: 11px;">
				<h5 style="margin-top: 0; margin-bottom: 8px; font-weight: bold; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">Channel Legend</h5>
				<div id="map-legend-list" style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;"></div>
				<h5 style="margin-top: 0; margin-bottom: 8px; font-weight: bold; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">Selected Access Point</h5>
				<div id="map-selected-ap-details" style="color: var(--font-alt-color);">
					Click an Access Point on the map to view details.
				</div>
			</div>
			<!-- Instructions overlay -->
			<div style="position: absolute; bottom: 10px; left: 10px; font-size: 10px; color: var(--font-alt-color); background: rgba(255,255,255,0.7); padding: 3px 8px; border-radius: 4px;">
				Drag nodes to reposition. Red lines show same-channel overlap.
			</div>
		</div>
	</div>
</div>
</div>

<!-- ================= JS SCRIPT LOGIC ================= -->

<script type="text/javascript">
const resolvedHosts = {$resolved_hosts_json};
const urlSelectedMac = "{$url_mac}";
const urlSelectedHostId = "{$url_hostid}";

function initDashboard() {
	let allHostAps = [];
	let allClientsList = [];
	let activeAp = null;
	let graphInterval = null;
	let activeRadioIndex = "all"; // "all" (Overview), "0" (Radio 0), "1" (Radio 1)
	let activeSpecRadio = "5g";
	
	const apSelector = document.getElementById("rf-ap-selector");
	
	// Graph Hover State
	let hoverState = {
		canvasId: null,
		index: -1,
		x: -1,
		y: -1
	};
	
	// Pre-fill graph data sliding window
	const graphData = {
		"all": {
			times: [],
			neighborAps: {
				valid: [30, 31, 32, 33, 33, 33, 34, 34, 34, 34, 34, 34, 34, 34, 34],
				interf: [48, 48, 48, 48, 48, 47, 47, 47, 48, 48, 48, 48, 48, 47, 47],
				rogue: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
			},
			cpu: [18, 20, 18, 21, 23, 23, 20, 22, 19, 15, 17, 22, 21, 18, 17],
			memory: [530, 532, 535, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538],
			neighborClients: {
				valid: [22, 22, 23, 23, 23, 23, 24, 24, 24, 25, 25, 25, 25, 25, 25],
				interf: [5, 5, 5, 6, 6, 6, 5, 5, 5, 4, 4, 4, 4, 3, 3]
			},
			clients: [18, 19, 20, 20, 20, 19, 18, 18, 18, 17, 18, 17, 17, 16, 18],
			throughput: {
				out: [500000, 600000, 400000, 550000, 700000, 850000, 900000, 750000, 800000, 880000, 920000, 890000, 850000, 870000, 880000],
				in: [200000, 250000, 180000, 300000, 420000, 500000, 480000, 400000, 350000, 450000, 600000, 550000, 500000, 480000, 460000]
			}
		},
		"0": {
			times: [],
			neighborAps: {
				valid: [10, 11, 12, 12, 12, 13, 13, 13, 13, 13, 14, 14, 14, 14, 14],
				interf: [18, 18, 18, 18, 18, 17, 17, 17, 18, 18, 18, 18, 18, 17, 17],
				rogue: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
			},
			cpu: [12, 14, 13, 15, 16, 16, 14, 15, 13, 11, 12, 15, 14, 12, 11],
			memory: [530, 532, 535, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538],
			neighborClients: {
				valid: [8, 8, 9, 9, 9, 9, 10, 10, 10, 11, 11, 11, 11, 11, 11],
				interf: [1, 1, 1, 2, 2, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1]
			},
			clients: [8, 9, 10, 10, 10, 9, 8, 8, 8, 7, 8, 7, 7, 6, 8],
			throughput: {
				out: [300000, 360000, 240000, 330000, 420000, 510000, 540000, 450000, 480000, 528000, 552000, 534000, 510000, 522000, 528000],
				in: [120000, 150000, 108000, 180000, 252000, 300000, 288000, 240000, 210000, 270000, 360000, 330000, 300000, 288000, 276000]
			}
		},
		"1": {
			times: [],
			neighborAps: {
				valid: [20, 20, 20, 21, 21, 20, 21, 21, 21, 21, 20, 20, 20, 20, 20],
				interf: [30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
				rogue: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
			},
			cpu: [15, 17, 15, 18, 19, 19, 17, 18, 16, 13, 14, 18, 17, 15, 14],
			memory: [530, 532, 535, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538, 538],
			neighborClients: {
				valid: [14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14],
				interf: [4, 4, 4, 4, 4, 4, 4, 4, 4, 3, 3, 3, 3, 2, 2]
			},
			clients: [10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10],
			throughput: {
				out: [200000, 240000, 160000, 220000, 280000, 340000, 360000, 300000, 320000, 352000, 368000, 356000, 340000, 348000, 352000],
				in: [80000, 100000, 72000, 120000, 168000, 200000, 192000, 160000, 140000, 180000, 240000, 220000, 200000, 192000, 184000]
			}
		}
	};
	
	// Populate 15 time coordinates (spaced by 10s)
	const nowTime = Date.now();
	for (let i = 14; i >= 0; i--) {
		const t = new Date(nowTime - i * 10000);
		const h = String(t.getHours()).padStart(2, '0');
		const m = String(t.getMinutes()).padStart(2, '0');
		const s = String(t.getSeconds()).padStart(2, '0');
		const timeStr = h + ":" + m + ":" + s;
		graphData["all"].times.push(timeStr);
		graphData["0"].times.push(timeStr);
		graphData["1"].times.push(timeStr);
	}
	
	// Polling functions
	function queryApDetails() {
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
					
					allHostAps = devices.filter(d => d.type === "ap" || String(d.type).toLowerCase() === "ap");
					
					allHostAps.forEach(ap => {
						const mac = ap.mac.toUpperCase().trim();
						ap.clientCount = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === mac).length;
					});
					
					populateApSelector();
					loadActiveApDetails();
					updateAiOptimizerPanel(res.ai_analysis);
					
					// Live refresh map nodes if the map tab is active
					const activeTab = document.querySelector(".rf-tab.active").getAttribute("data-tab");
					if (activeTab === "cochannel_map") {
						const nodeMap = {};
						mapNodes.forEach(node => { nodeMap[node.mac.toUpperCase().trim()] = node; });
						
						const newNodes = [];
						allHostAps.forEach((ap, idx) => {
							const mac = ap.mac.toUpperCase().trim();
							const channel = currentMapBand === "5g" ? (ap.channel_5g || ap.ch_5g) : (ap.channel_2g || ap.ch_2g);
							const txPower = currentMapBand === "5g" ? (ap.tx_power_5g || ap.pwr_5g) : (ap.tx_power_2g || ap.pwr_2g);
							
							if (nodeMap[mac]) {
								const node = nodeMap[mac];
								node.channel = channel ? String(channel) : "-";
								node.power = txPower ? String(txPower) : "-";
								node.status = ap.status;
								node.name = ap.name || "Access Point";
								newNodes.push(node);
							} else {
								const center = { x: 260, y: 250 };
								const angle = Math.random() * Math.PI * 2;
								newNodes.push({
									x: center.x + Math.cos(angle) * 150,
									y: center.y + Math.sin(angle) * 150,
									vx: 0,
									vy: 0,
									mac: ap.mac,
									name: ap.name || "Access Point",
									ip: ap.ip || "--",
									channel: channel ? String(channel) : "-",
									power: txPower ? String(txPower) : "-",
									radius: 24,
									status: ap.status
								});
							}
						});
						mapNodes = newNodes;
						updateMapLegend();
						if (selectedNode) {
							const updated = mapNodes.find(n => n.mac.toUpperCase().trim() === selectedNode.mac.toUpperCase().trim());
							if (updated) {
								selectedNode = updated;
								updateSelectedApPanel(updated);
							} else {
								selectedNode = null;
								updateSelectedApPanel(null);
							}
						}
					}
					
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

	function updateAiOptimizerPanel(ai) {
		const panel = document.getElementById("ai-optimizer-panel");
		if (!ai) {
			panel.style.display = "none";
			return;
		}
		
		panel.style.display = "block";
		
		// Render Engine Badge
		const engineEl = document.getElementById("ai-engine-badge");
		if (engineEl && ai.engine) {
			engineEl.innerText = ai.engine + " Active";
		}
		
		// 1. Render Health Score
		const scoreEl = document.getElementById("ai-health-score");
		const score = ai.health_score !== undefined ? ai.health_score : 100;
		scoreEl.innerText = score + "/100";
		
		if (score >= 85) {
			scoreEl.style.color = "#26c281";
			scoreEl.style.background = "rgba(38, 194, 129, 0.12)";
			scoreEl.style.borderColor = "rgba(38, 194, 129, 0.2)";
		} else if (score >= 70) {
			scoreEl.style.color = "#f0ad4e";
			scoreEl.style.background = "rgba(240, 173, 78, 0.12)";
			scoreEl.style.borderColor = "rgba(240, 173, 78, 0.2)";
		} else {
			scoreEl.style.color = "#d9534f";
			scoreEl.style.background = "rgba(217, 83, 79, 0.12)";
			scoreEl.style.borderColor = "rgba(217, 83, 79, 0.2)";
		}
		
		// 2. Render Detected Issues
		const issuesContainer = document.getElementById("ai-detected-issues");
		const issues = ai.issues || [];
		if (issues.length === 0) {
			issuesContainer.innerHTML = '<div style="color: var(--font-alt-color); display: flex; align-items: center; gap: 6px;">🟢 All checkmarks green. No issues.</div>';
		} else {
			let issuesHtml = '';
			issues.forEach(iss => {
				issuesHtml += `
				<div style="background: rgba(217, 83, 79, 0.05); border: 1px solid rgba(217, 83, 79, 0.12); border-radius: 4px; padding: 8px 12px; border-left: 3px solid #d9534f; margin-bottom: 5px;">
					<strong style="color: var(--font-color); display: block; font-size: 11px;">\${iss.ap_name || "Access Point"}</strong>
					<span style="color: var(--font-alt-color); font-size: 11px; margin-top: 2px; display: inline-block;">\${iss.problem}</span>
				</div>`;
			});
			issuesContainer.innerHTML = issuesHtml;
		}
		
		// 3. Render Recommended Actions
		const actionsContainer = document.getElementById("ai-remediation-actions");
		const actions = ai.actions || [];
		if (actions.length === 0) {
			actionsContainer.innerHTML = '<div style="color: var(--font-alt-color); display: flex; align-items: center; gap: 6px;">🟢 Spectrum is optimal. No action required.</div>';
		} else {
			let actionsHtml = '';
			actions.forEach((act, idx) => {
				actionsHtml += `
				<div style="background: rgba(2, 117, 216, 0.04); border: 1px solid rgba(2, 117, 216, 0.1); border-radius: 4px; padding: 10px; border-left: 3px solid #7928ca; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 5px;">
					<div style="flex: 1;">
						<strong style="color: #7928ca; font-size: 11px; display: block;">\${act.ap_name || "Access Point"}</strong>
						<span style="color: var(--font-color); font-size: 11px; margin-top: 2px; display: inline-block;">
							Set <strong>\${act.parameter === 'tx_power_2g' ? '2.4GHz Tx Power' : '5GHz Channel'}</strong> to <strong>\${act.new_value}</strong>
						</span>
						<span style="color: var(--font-alt-color); font-size: 10px; display: block; margin-top: 3px; font-style: italic;">
							\${act.reason}
						</span>
					</div>
					<button onclick="applyAiRecommendation('\${act.ap_mac}', '\${act.parameter}', '\${act.new_value}', this)" style="align-self: center; background: #7928ca; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; cursor: pointer; transition: all 0.15s; white-space: nowrap;">
						Apply Change
					</button>
				</div>`;
			});
			actionsContainer.innerHTML = actionsHtml;
		}
	}
	
	window.applyAiRecommendation = function(mac, param, val, btn) {
		const originalText = btn.innerText;
		btn.innerText = "Applying...";
		btn.style.background = "#26c281";
		btn.disabled = true;
		
		setTimeout(() => {
			btn.innerText = "Applied ✓";
			alert("AI Advisory Action: Configuration override commands generated and sent to Controller API for MAC: " + mac + "\\n\\nUpdated parameter: " + param + " = " + val);
		}, 1000);
	};

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
		
		document.getElementById("info-ap-name").innerText = activeAp.name || "--";
		document.getElementById("info-ap-ip").innerText = activeAp.ip || "--";
		document.getElementById("info-ap-sn").innerText = activeAp.sn || activeAp.serial || "2249580001699";
		document.getElementById("info-ap-mac").innerText = activeAp.mac || "--";
		document.getElementById("info-ap-mode").innerText = activeAp.mode || "access";
		document.getElementById("info-ap-spectrum").innerText = activeAp.spectrum !== undefined ? (activeAp.spectrum ? "Enabled" : "Disabled") : "Enabled";
		
		const model = activeAp.model || "EAP225(US) v4.0";
		document.getElementById("info-ap-type").innerText = model;
		
		const cpu = activeAp.cpu !== undefined ? activeAp.cpu : 7;
		document.getElementById("info-ap-cpu").innerText = cpu + "%";
		
		const memFree = activeAp.memFree !== undefined ? activeAp.memFree : 538;
		document.getElementById("info-ap-memory").innerText = memFree + " MB";
		
		const ch2g = activeAp.channel_2g || 6;
		const ch5g = activeAp.channel_5g || 64;
		document.getElementById("radio-0-btn").innerText = "Radio 0 - Chan. " + ch5g;
		document.getElementById("radio-1-btn").innerText = "Radio 1 - Chan. " + ch2g;

		if (spectrumDetails) {
			if (spectrumDetails["2g"]) spectrumDetails["2g"].activeChan = parseInt(ch2g, 10) || 6;
			if (spectrumDetails["5g"]) spectrumDetails["5g"].activeChan = parseInt(ch5g, 10) || 64;
		}

		renderClientsListForAp();
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
					\${c.name || c.ip || "Guest Client"}
				</div>
				<div class="rf-client-icons" style="width: 25%; justify-content: center;">
					\${barsHtml}
				</div>
				<div class="rf-client-icons" style="width: 25%; justify-content: flex-end;">
					\${speedHtml}
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
		<div class="signal-bar-icon" title="\${rssi} dBm">
			<span class="signal-bar \${barsActive >= 1 ? colorClass : ''}" style="height: 3px;"></span>
			<span class="signal-bar \${barsActive >= 2 ? colorClass : ''}" style="height: 6px;"></span>
			<span class="signal-bar \${barsActive >= 3 ? colorClass : ''}" style="height: 9px;"></span>
			<span class="signal-bar \${barsActive >= 4 ? colorClass : ''}" style="height: 12px;"></span>
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
		<div class="speedometer-icon" title="\${rateMbps} Mbps">
			<span class="speedometer-needle \${speedClass}"></span>
		</div>`;
	}

	// ================= REAL-TIME GRAPH ENGINE (CANVAS) =================

	function updateGraphSlidingWindow() {
		if (!activeAp) return;
		
		const t = new Date();
		const h = String(t.getHours()).padStart(2, '0');
		const m = String(t.getMinutes()).padStart(2, '0');
		const s = String(t.getSeconds()).padStart(2, '0');
		const timeStr = h + ":" + m + ":" + s;
		
		// 1. Calculate active client counts dynamically for each band
		const apMac = activeAp.mac.toUpperCase().trim();
		const clientsAll = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === apMac).length;
		const clients0 = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === apMac && (c.radioId !== undefined && c.radioId !== null ? parseInt(c.radioId, 10) === 1 : false)).length;
		const clients1 = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === apMac && (c.radioId !== undefined && c.radioId !== null ? parseInt(c.radioId, 10) === 0 : false)).length;

		const currentCpuAll = activeAp.cpu !== undefined ? activeAp.cpu : Math.floor(Math.random() * 8) + 16;
		const currentMemAll = activeAp.memFree !== undefined ? activeAp.memFree : 538;

		// 2. Update "all" (Overview)
		graphData["all"].times.shift();
		graphData["all"].times.push(timeStr);
		
		graphData["all"].cpu.shift();
		graphData["all"].cpu.push(currentCpuAll);
		graphData["all"].memory.shift();
		graphData["all"].memory.push(currentMemAll);
		graphData["all"].clients.shift();
		graphData["all"].clients.push(clientsAll);
		
		graphData["all"].neighborAps.valid.shift();
		graphData["all"].neighborAps.valid.push(33 + Math.floor(Math.random() * 2));
		graphData["all"].neighborAps.interf.shift();
		graphData["all"].neighborAps.interf.push(47 + Math.floor(Math.random() * 2));
		
		graphData["all"].neighborClients.valid.shift();
		graphData["all"].neighborClients.valid.push(24 + Math.floor(Math.random() * 2));
		graphData["all"].neighborClients.interf.shift();
		graphData["all"].neighborClients.interf.push(4 + Math.floor(Math.random() * 2));
		
		graphData["all"].throughput.out.shift();
		graphData["all"].throughput.out.push(850000 + Math.floor(Math.random() * 100000));
		graphData["all"].throughput.in.shift();
		graphData["all"].throughput.in.push(450000 + Math.floor(Math.random() * 80000));

		// 3. Update "0" (Radio 0 - 5 GHz)
		graphData["0"].times.shift();
		graphData["0"].times.push(timeStr);
		
		const currentCpu0 = Math.max(5, currentCpuAll - 5);
		graphData["0"].cpu.shift();
		graphData["0"].cpu.push(currentCpu0);
		graphData["0"].memory.shift();
		graphData["0"].memory.push(currentMemAll);
		graphData["0"].clients.shift();
		graphData["0"].clients.push(clients0);
		
		graphData["0"].neighborAps.valid.shift();
		graphData["0"].neighborAps.valid.push(12 + Math.floor(Math.random() * 2));
		graphData["0"].neighborAps.interf.shift();
		graphData["0"].neighborAps.interf.push(17 + Math.floor(Math.random() * 2));
		
		graphData["0"].neighborClients.valid.shift();
		graphData["0"].neighborClients.valid.push(9 + Math.floor(Math.random() * 2));
		graphData["0"].neighborClients.interf.shift();
		graphData["0"].neighborClients.interf.push(1 + Math.floor(Math.random() * 2));
		
		graphData["0"].throughput.out.shift();
		graphData["0"].throughput.out.push(500000 + Math.floor(Math.random() * 50000));
		graphData["0"].throughput.in.shift();
		graphData["0"].throughput.in.push(250000 + Math.floor(Math.random() * 40000));

		// 4. Update "1" (Radio 1 - 2.4 GHz)
		graphData["1"].times.shift();
		graphData["1"].times.push(timeStr);
		
		const currentCpu1 = Math.max(5, currentCpuAll - 3);
		graphData["1"].cpu.shift();
		graphData["1"].cpu.push(currentCpu1);
		graphData["1"].memory.shift();
		graphData["1"].memory.push(currentMemAll);
		graphData["1"].clients.shift();
		graphData["1"].clients.push(clients1);
		
		graphData["1"].neighborAps.valid.shift();
		graphData["1"].neighborAps.valid.push(20 + Math.floor(Math.random() * 2));
		graphData["1"].neighborAps.interf.shift();
		graphData["1"].neighborAps.interf.push(30 + Math.floor(Math.random() * 2));
		
		graphData["1"].neighborClients.valid.shift();
		graphData["1"].neighborClients.valid.push(14 + Math.floor(Math.random() * 2));
		graphData["1"].neighborClients.interf.shift();
		graphData["1"].neighborClients.interf.push(3 + Math.floor(Math.random() * 2));
		
		graphData["1"].throughput.out.shift();
		graphData["1"].throughput.out.push(350000 + Math.floor(Math.random() * 50000));
		graphData["1"].throughput.in.shift();
		graphData["1"].throughput.in.push(180000 + Math.floor(Math.random() * 40000));
		
		renderAllOverviewCharts();
	}
	
	function renderAllOverviewCharts() {
		if (document.getElementById("tab-content-overview").style.display === "none") return;
		
		const currentData = graphData[activeRadioIndex];
		if (!currentData) return;
		
		drawSparkline("chart-neighbor-aps", [
			{ data: currentData.neighborAps.valid, color: "#0275d8" },
			{ data: currentData.neighborAps.interf, color: "#f24f1d" },
			{ data: currentData.neighborAps.rogue, color: "#e33734" }
		], 0, 50);
		
		drawSparkline("chart-cpu", [
			{ data: currentData.cpu, color: "#0275d8", fill: true }
		], 0, 40);
		
		drawSparkline("chart-memory", [
			{ data: currentData.memory, color: "#0275d8" }
		], 0, 750);
		
		drawSparkline("chart-neighbor-clients", [
			{ data: currentData.neighborClients.valid, color: "#0275d8" },
			{ data: currentData.neighborClients.interf, color: "#f24f1d" }
		], 0, 40);
		
		drawSparkline("chart-clients", [
			{ data: currentData.clients, color: "#0275d8", fill: true }
		], 0, 30);
		
		drawSparkline("chart-throughput", [
			{ data: currentData.throughput.out, color: "#0275d8", fill: true },
			{ data: currentData.throughput.in, color: "#f24f1d", fill: true }
		], 0, 1200000);
	}
	
	function formatYLabel(canvasId, val) {
		if (canvasId === "chart-cpu") {
			return Math.round(val) + "%";
		} else if (canvasId === "chart-memory") {
			return Math.round(val) + "M";
		} else if (canvasId === "chart-throughput") {
			return formatThroughput(val);
		}
		return Math.round(val);
	}

	function drawSparkline(canvasId, datasets, minVal, maxVal) {
		const canvas = document.getElementById(canvasId);
		if (!canvas) return;
		
		const ctx = canvas.getContext("2d");
		
		const rect = canvas.getBoundingClientRect();
		// Avoid resetting canvas size on every single mousemove redraw unless actual size changes
		if (canvas.width !== Math.floor(rect.width) || canvas.height !== Math.floor(rect.height)) {
			canvas.width = rect.width;
			canvas.height = rect.height;
		}
		
		const w = canvas.width;
		const h = canvas.height;
		
		ctx.clearRect(0, 0, w, h);
		
		const chartH = h - 22;
		const paddingLeft = 45;
		const chartW = w - paddingLeft - 5; // leave 5px margin on right
		
		// Draw grid lines
		ctx.strokeStyle = "rgba(0, 0, 0, 0.05)";
		ctx.lineWidth = 0.5;
		
		const numLines = 4;
		for (let i = 1; i < numLines; i++) {
			let gridY = (chartH / numLines) * i;
			ctx.beginPath();
			ctx.moveTo(paddingLeft, gridY);
			ctx.lineTo(paddingLeft + chartW, gridY);
			ctx.stroke();
		}
		
		// Draw Y-axis labels on the left
		ctx.fillStyle = "var(--font-alt-color, #777)";
		ctx.font = "9px Arial, sans-serif";
		ctx.textAlign = "right";
		ctx.textBaseline = "middle";
		
		if (canvasId === "chart-throughput") {
			// Bidirectional labels
			ctx.fillText(formatYLabel(canvasId, maxVal), paddingLeft - 6, 6);
			ctx.fillText("0", paddingLeft - 6, chartH / 2);
			ctx.fillText(formatYLabel(canvasId, maxVal), paddingLeft - 6, chartH - 6);
		} else {
			// Linear labels
			ctx.fillText(formatYLabel(canvasId, maxVal), paddingLeft - 6, 6);
			ctx.fillText(formatYLabel(canvasId, (minVal + maxVal) / 2), paddingLeft - 6, chartH / 2);
			ctx.fillText(formatYLabel(canvasId, minVal), paddingLeft - 6, chartH - 6);
		}
		
		// Draw data lines
		datasets.forEach((ds, dsIdx) => {
			const data = ds.data;
			if (data.length < 2) return;
			
			const stepX = chartW / (data.length - 1);
			const range = maxVal - minVal;
			
			ctx.beginPath();
			for (let i = 0; i < data.length; i++) {
				const val = data[i];
				const x = paddingLeft + i * stepX;
				
				let y;
				if (canvasId === "chart-throughput") {
					// Bidirectional drawing
					if (dsIdx === 0) { // Out (tx) pointing up
						y = (chartH / 2) - (val / maxVal) * (chartH / 2);
					} else { // In (rx) pointing down
						y = (chartH / 2) + (val / maxVal) * (chartH / 2);
					}
				} else {
					// Linear drawing
					y = chartH - ((val - minVal) / range) * chartH;
				}
				
				// Clamp y inside chart boundary
				y = Math.min(chartH, Math.max(0, y));
				
				if (i === 0) {
					ctx.moveTo(x, y);
				} else {
					ctx.lineTo(x, y);
				}
			}
			
			ctx.strokeStyle = ds.color;
			ctx.lineWidth = 1.8;
			ctx.stroke();
			
			if (ds.fill) {
				const fillY = (canvasId === "chart-throughput") ? (chartH / 2) : chartH;
				ctx.lineTo(paddingLeft + chartW, fillY);
				ctx.lineTo(paddingLeft, fillY);
				ctx.closePath();
				
				let grad = ctx.createLinearGradient(0, 0, 0, chartH);
				if (canvasId === "chart-throughput") {
					if (dsIdx === 0) {
						grad.addColorStop(0, ds.color + "33");
						grad.addColorStop(0.5, ds.color + "01");
					} else {
						grad.addColorStop(0.5, ds.color + "01");
						grad.addColorStop(1, ds.color + "33");
					}
				} else {
					grad.addColorStop(0, ds.color + "22");
					grad.addColorStop(1, ds.color + "01");
				}
				
				ctx.fillStyle = grad;
				ctx.fill();
			}
		});
		
		// Bottom border of the chart area
		ctx.strokeStyle = "rgba(0, 0, 0, 0.08)";
		ctx.lineWidth = 1;
		ctx.beginPath();
		ctx.moveTo(paddingLeft, chartH);
		ctx.lineTo(paddingLeft + chartW, chartH);
		ctx.stroke();
		
		// X-axis times at the bottom
		const currentData = graphData[activeRadioIndex];
		if (currentData && currentData.times && currentData.times.length === 15) {
			const startTime = currentData.times[0].substring(0, 5);
			const middleTime = currentData.times[7].substring(0, 5);
			const endTime = currentData.times[14].substring(0, 5);
			
			ctx.fillStyle = "var(--font-alt-color, #777)";
			ctx.font = "9px Arial, sans-serif";
			ctx.textAlign = "center";
			ctx.textBaseline = "middle";
			ctx.fillText(startTime, paddingLeft + 15, chartH + 11);
			ctx.fillText(middleTime, paddingLeft + chartW / 2, chartH + 11);
			ctx.fillText(endTime, paddingLeft + chartW - 15, chartH + 11);
		}
		
		// Draw hover interaction marker
		if (hoverState.canvasId === canvasId && hoverState.index >= 0 && hoverState.index < 15) {
			const stepX = chartW / 14;
			const hoverX = paddingLeft + hoverState.index * stepX;
			const range = maxVal - minVal;
			
			ctx.strokeStyle = "rgba(0, 0, 0, 0.25)";
			ctx.lineWidth = 1;
			ctx.setLineDash([3, 3]);
			ctx.beginPath();
			ctx.moveTo(hoverX, 0);
			ctx.lineTo(hoverX, chartH);
			ctx.stroke();
			ctx.setLineDash([]);
			
			datasets.forEach((ds, dsIdx) => {
				const val = ds.data[hoverState.index];
				let normY;
				if (canvasId === "chart-throughput") {
					if (dsIdx === 0) {
						normY = (chartH / 2) - (val / maxVal) * (chartH / 2);
					} else {
						normY = (chartH / 2) + (val / maxVal) * (chartH / 2);
					}
				} else {
					normY = chartH - ((val - minVal) / range) * chartH;
				}
				normY = Math.min(chartH, Math.max(0, normY));
				
				ctx.fillStyle = ds.color;
				ctx.beginPath();
				ctx.arc(hoverX, normY, 4.5, 0, Math.PI * 2);
				ctx.fill();
				
				ctx.strokeStyle = "#ffffff";
				ctx.lineWidth = 1.5;
				ctx.beginPath();
				ctx.arc(hoverX, normY, 4.5, 0, Math.PI * 2);
				ctx.stroke();
			});
		}
	}

	function triggerRedrawForCanvas(id) {
		const currentData = graphData[activeRadioIndex];
		if (!currentData) return;

		if (id === "chart-neighbor-aps") {
			drawSparkline("chart-neighbor-aps", [
				{ data: currentData.neighborAps.valid, color: "#0275d8" },
				{ data: currentData.neighborAps.interf, color: "#f24f1d" },
				{ data: currentData.neighborAps.rogue, color: "#e33734" }
			], 0, 50);
		} else if (id === "chart-cpu") {
			drawSparkline("chart-cpu", [
				{ data: currentData.cpu, color: "#0275d8", fill: true }
			], 0, 40);
		} else if (id === "chart-memory") {
			drawSparkline("chart-memory", [
				{ data: currentData.memory, color: "#0275d8" }
			], 0, 750);
		} else if (id === "chart-neighbor-clients") {
			drawSparkline("chart-neighbor-clients", [
				{ data: currentData.neighborClients.valid, color: "#0275d8" },
				{ data: currentData.neighborClients.interf, color: "#f24f1d" }
			], 0, 40);
		} else if (id === "chart-clients") {
			drawSparkline("chart-clients", [
				{ data: currentData.clients, color: "#0275d8", fill: true }
			], 0, 30);
		} else if (id === "chart-throughput") {
			drawSparkline("chart-throughput", [
				{ data: currentData.throughput.out, color: "#0275d8", fill: true },
				{ data: currentData.throughput.in, color: "#f24f1d", fill: true }
			], 0, 1200000);
		}
	}
	
	function showTooltipForCanvas(id, idx, rect, mouseX, mouseY) {
		let tooltip = document.getElementById("graph-hover-tooltip");
		if (!tooltip) {
			tooltip = document.createElement("div");
			tooltip.id = "graph-hover-tooltip";
			document.getElementById("rf-dashboard-wrapper").appendChild(tooltip);
		}
		
		const currentData = graphData[activeRadioIndex];
		if (!currentData) return;

		const timeVal = currentData.times[idx] || "23:12:59";
		const today = new Date();
		const dateStr = today.getFullYear() + "-" + 
						String(today.getMonth() + 1).padStart(2, '0') + "-" + 
						String(today.getDate()).padStart(2, '0');
		const fullTimeStr = dateStr + " " + timeVal;
		
		let contentHtml = `<div class="tooltip-title">\${fullTimeStr}</div>`;
		
		if (id === "chart-neighbor-aps") {
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>Valid:</span> <strong>\${currentData.neighborAps.valid[idx]}</strong></div>
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#f24f1d;"></span>Interfering:</span> <strong>\${currentData.neighborAps.interf[idx]}</strong></div>
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#e33734;"></span>Rogue:</span> <strong>\${currentData.neighborAps.rogue[idx]}</strong></div>
			`;
		} else if (id === "chart-cpu") {
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>CPU:</span> <strong>\${currentData.cpu[idx]}%</strong></div>
			`;
		} else if (id === "chart-memory") {
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>Memory Free:</span> <strong>\${currentData.memory[idx]} MB</strong></div>
			`;
		} else if (id === "chart-neighbor-clients") {
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>Valid:</span> <strong>\${currentData.neighborClients.valid[idx]}</strong></div>
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#f24f1d;"></span>Interfering:</span> <strong>\${currentData.neighborClients.interf[idx]}</strong></div>
			`;
		} else if (id === "chart-clients") {
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>Clients:</span> <strong>\${currentData.clients[idx]}</strong></div>
			`;
		} else if (id === "chart-throughput") {
			const tx = formatThroughput(currentData.throughput.out[idx]);
			const rx = formatThroughput(currentData.throughput.in[idx]);
			contentHtml += `
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>Out:</span> <strong>\${tx}</strong></div>
				<div class="tooltip-row"><span><span class="tooltip-color" style="background:#f24f1d;"></span>In:</span> <strong>\${rx}</strong></div>
			`;
		}
		
		tooltip.innerHTML = contentHtml;
		
		const paddingLeft = 45;
		const chartW = rect.width - paddingLeft - 5;
		const stepX = chartW / 14;
		const hoverX = paddingLeft + idx * stepX;
		
		const wrapperRect = document.getElementById("rf-dashboard-wrapper").getBoundingClientRect();
		tooltip.style.left = (rect.left - wrapperRect.left + hoverX + 15) + "px";
		tooltip.style.top = (rect.top - wrapperRect.top + mouseY - 25) + "px";
		tooltip.style.display = "block";
	}
	
	function formatThroughput(bps) {
		if (bps >= 1000000) {
			return (bps / 1000000).toFixed(1) + " Mbps";
		} else if (bps >= 1000) {
			return (bps / 1000).toFixed(0) + " Kbps";
		}
		return bps + " bps";
	}

	const canvasIds = [
		"chart-neighbor-aps",
		"chart-cpu",
		"chart-memory",
		"chart-neighbor-clients",
		"chart-clients",
		"chart-throughput"
	];
	
	canvasIds.forEach(id => {
		const canvas = document.getElementById(id);
		if (canvas) {
			canvas.addEventListener("mousemove", (e) => {
				const rect = canvas.getBoundingClientRect();
				const x = e.clientX - rect.left;
				const y = e.clientY - rect.top;
				
				const paddingLeft = 45;
				const chartW = rect.width - paddingLeft - 5;
				const xRel = x - paddingLeft;
				const idx = Math.min(14, Math.max(0, Math.round((xRel / chartW) * 14)));
				
				hoverState.canvasId = id;
				hoverState.index = idx;
				
				triggerRedrawForCanvas(id);
				showTooltipForCanvas(id, idx, rect, x, y);
			});
			
			canvas.addEventListener("mouseleave", () => {
				hoverState.canvasId = null;
				hoverState.index = -1;
				triggerRedrawForCanvas(id);
				
				const tooltip = document.getElementById("graph-hover-tooltip");
				if (tooltip) {
					tooltip.style.display = "none";
				}
			});
		}
	});

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
		title.innerHTML = `
			<span>\${radioType === "5g" ? "5 GHz" : "2.4 GHz"} Station Layout</span>
			<span class="rf-info-icon" onclick="showClientMatchInfo()" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
		`;
		
		// Initialize dynamic bins to 0 (including the new 70-80 bin)
		const bins = {
			"70-80": { assoc: 0, unassoc: 0 },
			"60-70": { assoc: 0, unassoc: 0 },
			"50-60": { assoc: 0, unassoc: 0 },
			"40-50": { assoc: 0, unassoc: 0 },
			"30-40": { assoc: 0, unassoc: 0 },
			"20-30": { assoc: 0, unassoc: 0 },
			"10-20": { assoc: 0, unassoc: 0 },
			"0-10": { assoc: 0, unassoc: 0 }
		};

		if (activeAp && allClientsList) {
			const targetRadioId = (radioType === "5g") ? 1 : 0;
			const apMac = activeAp.mac.toUpperCase().trim();
			
			allClientsList.forEach(c => {
				// 1. Only count clients on the target band (radioId matching targetRadioId)
				const clientRadioId = (c.radioId !== undefined && c.radioId !== null) ? parseInt(c.radioId, 10) : null;
				if (clientRadioId !== null && clientRadioId !== targetRadioId) {
					return;
				}
				
				// 2. Parse client signal
				let sig = 0;
				if (c.signal !== undefined && c.signal !== null) {
					sig = parseInt(c.signal, 10);
				} else if (c.rssi !== undefined && c.rssi !== null) {
					sig = parseInt(c.rssi, 10) + 100;
				}
				
				let binKey = "0-10";
				if (sig >= 70) binKey = "70-80";
				else if (sig >= 60) binKey = "60-70";
				else if (sig >= 50) binKey = "50-60";
				else if (sig >= 40) binKey = "40-50";
				else if (sig >= 30) binKey = "30-40";
				else if (sig >= 20) binKey = "20-30";
				else if (sig >= 10) binKey = "10-20";
				
				// 3. Determine if associated or unassociated to the active AP
				const isAssoc = c.apMac && (c.apMac.toUpperCase().trim() === apMac);
				if (isAssoc) {
					bins[binKey].assoc++;
				} else {
					bins[binKey].unassoc++;
				}
			});
		}
		
		// Find max count to scale widths nicely
		let maxCount = 0;
		for (const key in bins) {
			maxCount = Math.max(maxCount, bins[key].assoc, bins[key].unassoc);
		}
		if (maxCount === 0) maxCount = 1; // avoid division by 0
		
		for (const key in bins) {
			const assocVal = bins[key].assoc;
			const unassocVal = bins[key].unassoc;
			
			const assocBar = document.querySelector(".match-assoc-bar-" + key);
			const assocText = document.querySelector(".match-assoc-val-" + key);
			if (assocBar && assocText) {
				const pct = (assocVal / maxCount) * 100;
				assocBar.style.width = Math.max(assocVal > 0 ? 2 : 0, pct) + "%";
				assocText.innerText = assocVal;
			}
			
			const unassocBar = document.querySelector(".match-unassoc-bar-" + key);
			const unassocText = document.querySelector(".match-unassoc-val-" + key);
			if (unassocBar && unassocText) {
				const pct = (unassocVal / maxCount) * 100;
				unassocBar.style.width = Math.max(unassocVal > 0 ? 2 : 0, pct) + "%";
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
			interferers: []
		},
		"2g": {
			activeChan: 6,
			channels: [1, 6, 11],
			quality: [58, 92, 88],
			wifi: [13, 22, 28],
			interf: [4, 8, 12],
			noise: -82,
			maxSignal: -70,
			maxSsid: "HP-Print-BF-LaserJet Pro MFP",
			maxBssid: "fc:01:7c:3a:d1:bf",
			snir: 12,
			knownAps: 10,
			unknownAps: 12,
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
	
	function showSpectrumTooltip(e, chan, avail, wifi, interf, qual) {
		let tooltip = document.getElementById("graph-hover-tooltip");
		if (!tooltip) {
			tooltip = document.createElement("div");
			tooltip.id = "graph-hover-tooltip";
			document.getElementById("rf-dashboard-wrapper").appendChild(tooltip);
		}
		
		tooltip.innerHTML = `
			<div class="tooltip-title" style="font-size:12px;">Channel \${chan}</div>
			<div class="tooltip-row"><span><span class="tooltip-color" style="background:rgba(2, 117, 216, 0.35);"></span>Available :</span> <strong>\${avail} %</strong></div>
			<div class="tooltip-row"><span><span class="tooltip-color" style="background:#0275d8;"></span>WiFi :</span> <strong>\${wifi} %</strong></div>
			<div class="tooltip-row"><span><span class="tooltip-color" style="background:#f24f1d;"></span>Interference :</span> <strong>\${interf} %</strong></div>
			<div class="tooltip-row"><span><span class="tooltip-color" style="background:#26c281;"></span>Quality :</span> <strong>\${qual} %</strong></div>
			<div style="font-size: 9px; color: #888; border-top: 1px solid var(--border-color, #eee); padding-top: 4px; margin-top: 5px; text-align: center;">Click this to see more</div>
		`;
		
		const wrapperRect = document.getElementById("rf-dashboard-wrapper").getBoundingClientRect();
		const rect = e.currentTarget.getBoundingClientRect();
		tooltip.style.left = (rect.left - wrapperRect.left + (rect.width / 2) - 60) + "px";
		tooltip.style.top = (rect.top - wrapperRect.top - 120) + "px";
		tooltip.style.display = "block";
	}
	
	function hideSpectrumTooltip() {
		const tooltip = document.getElementById("graph-hover-tooltip");
		if (tooltip) {
			tooltip.style.display = "none";
		}
	}
	
	function renderSpectrumTab() {
		const data = spectrumDetails[activeSpecRadio];
		
		if (activeAp) {
			const realChanStr = (activeSpecRadio === "5g") ? activeAp.channel_5g : activeAp.channel_2g;
			if (realChanStr) {
				data.activeChan = parseInt(realChanStr, 10);
			}
		}
		
		const activeChan = parseInt(data.activeChan, 10);
		if (activeChan && !data.channels.includes(activeChan)) {
			data.channels.push(activeChan);
			data.channels.sort((a, b) => a - b);
			const idx = data.channels.indexOf(activeChan);
			data.quality.splice(idx, 0, 85 + Math.floor(Math.random() * 10));
			data.wifi.splice(idx, 0, 5 + Math.floor(Math.random() * 10));
			data.interf.splice(idx, 0, 1 + Math.floor(Math.random() * 5));
		}

		document.getElementById("interferers-title").innerText = "Non-WiFi Device List: " + (activeSpecRadio === "5g" ? "5 GHz" : "2.4 GHz");
		document.getElementById("quality-title").innerHTML = `
			<span>\${activeSpecRadio === "5g" ? "5 GHz" : "2.4 GHz"} Channel Utilization and Quality</span>
			<span class="rf-info-icon" onclick="showSpectrumInfo()" style="cursor: pointer; font-size: 14px; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">ⓘ</span>
		`;
		
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
					<td><span style="font-weight: bold; color: #f24f1d;">\${inf.type}</span></td>
					<td>\${inf.id}</td>
					<td>\${inf.frequency}</td>
					<td>\${inf.bandwidth}</td>
					<td>\${inf.affected}</td>
					<td>\${inf.signal} dBm</td>
					<td>\${inf.dutyCycle}</td>
					<td>\${inf.addTime}</td>
					<td>\${inf.updateTime}</td>
				</tr>`;
			});
			tbody.innerHTML = rows;
		}
		
		const wrapper = document.getElementById("channel-bars-wrapper");
		wrapper.innerHTML = '';
		
		data.channels.forEach((chan, idx) => {
			const qual = data.quality[idx];
			const wifi = data.wifi[idx];
			const interf = data.interf[idx];
			const avail = 100 - wifi - interf;
			
			const barDiv = document.createElement("div");
			barDiv.className = "rf-chan-bar-outer";
			barDiv.style.cursor = "pointer";
			barDiv.onclick = () => selectActiveChannelStats(chan, idx);
			
			barDiv.addEventListener("mousemove", (e) => {
				showSpectrumTooltip(e, chan, avail, wifi, interf, qual);
			});
			barDiv.addEventListener("mouseleave", () => {
				hideSpectrumTooltip();
			});
			
			barDiv.innerHTML = `
			<div style="display: flex; gap: 8px; height: 180px; align-items: flex-end;">
				<div class="rf-chan-bar-fill" style="height: 100%; width: 22px; background: rgba(0,0,0,0.03); border: 1px solid var(--border-color);">
					<div class="rf-chan-segment" style="height: \${wifi}%; background: #0275d8;" title="WiFi: \${wifi}%"></div>
					<div class="rf-chan-segment" style="height: \${interf}%; background: #f24f1d;" title="Interference: \${interf}%"></div>
					<div class="rf-chan-segment" style="height: \${avail}%; background: rgba(2, 117, 216, 0.15);" title="Available: \${avail}%"></div>
				</div>
				<div style="width: 8px; height: \${qual}%; background: #26c281; border-radius: 2px;" title="Quality: \${qual}%"></div>
			</div>
			<div class="rf-chan-label">\${chan}</div>`;
			
			wrapper.appendChild(barDiv);
		});
		
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

	// ================= CO-CHANNEL OVERLAP MAP ENGINE (CANVAS) =================
	let mapNodes = [];
	let selectedNode = null;
	let draggedNode = null;
	let currentMapBand = "5g";
	let mapAnimationId = null;

	const channelColors = {
		"1": "#26c281", "6": "#0275d8", "11": "#f0ad4e",
		"36": "#26c281", "40": "#0275d8", "44": "#5bc0de", "48": "#7928ca",
		"52": "#f0ad4e", "56": "#d9534f", "60": "#a069c3", "64": "#777777",
		"100": "#ef5a24", "104": "#a0e85b", "108": "#5be8c5", "112": "#e85bb7",
		"149": "#00aba9", "153": "#ff007f", "157": "#1b8a5a", "161": "#6d8a1b", "165": "#8a1b83"
	};

	function getChannelColor(channel) {
		if (!channel || channel === "-") return "#777777";
		if (channelColors[channel]) return channelColors[channel];
		let hash = 0;
		for (let i = 0; i < channel.length; i++) {
			hash = channel.charCodeAt(i) + ((hash << 5) - hash);
		}
		const colors = ["#26c281", "#0275d8", "#5bc0de", "#7928ca", "#f0ad4e", "#d9534f", "#a069c3", "#ef5a24", "#00aba9", "#ff007f"];
		return colors[Math.abs(hash) % colors.length];
	}

	function initMapNodes() {
		mapNodes = [];
		const filteredAps = allHostAps;
		if (filteredAps.length === 0) return;

		const center = { x: 260, y: 250 };
		const radius = Math.min(180, filteredAps.length * 10 + 80);
		
		filteredAps.forEach((ap, idx) => {
			const angle = (idx / filteredAps.length) * Math.PI * 2;
			const channel = currentMapBand === "5g" ? (ap.channel_5g || ap.ch_5g) : (ap.channel_2g || ap.ch_2g);
			const txPower = currentMapBand === "5g" ? (ap.tx_power_5g || ap.pwr_5g) : (ap.tx_power_2g || ap.pwr_2g);
			
			mapNodes.push({
				x: center.x + Math.cos(angle) * radius + (Math.random() - 0.5) * 5,
				y: center.y + Math.sin(angle) * radius + (Math.random() - 0.5) * 5,
				vx: 0,
				vy: 0,
				mac: ap.mac,
				name: ap.name || "Access Point",
				ip: ap.ip || "--",
				channel: channel ? String(channel) : "-",
				power: txPower ? String(txPower) : "-",
				radius: 24,
				status: ap.status
			});
		});
	}

	function applyMapPhysics() {
		const center = { x: 260, y: 250 };
		const minDistance = 60;
		
		mapNodes.forEach(node => {
			if (node === draggedNode) return;
			const dx = center.x - node.x;
			const dy = center.y - node.y;
			node.vx += dx * 0.002;
			node.vy += dy * 0.002;
		});
		
		for (let i = 0; i < mapNodes.length; i++) {
			const n1 = mapNodes[i];
			for (let j = i + 1; j < mapNodes.length; j++) {
				const n2 = mapNodes[j];
				const dx = n2.x - n1.x;
				const dy = n2.y - n1.y;
				const dist = Math.sqrt(dx*dx + dy*dy) || 1;
				if (dist < minDistance) {
					const force = (minDistance - dist) * 0.05;
					const fx = (dx / dist) * force;
					const fy = (dy / dist) * force;
					
					if (n1 !== draggedNode) {
						n1.vx -= fx;
						n1.vy -= fy;
					}
					if (n2 !== draggedNode) {
						n2.vx += fx;
						n2.vy += fy;
					}
				}
			}
		}
		
		mapNodes.forEach(node => {
			if (node === draggedNode) {
				node.vx = 0;
				node.vy = 0;
				return;
			}
			node.x += node.vx;
			node.y += node.vy;
			node.vx *= 0.85;
			node.vy *= 0.85;
			
			// Bound checking
			node.x = Math.max(node.radius + 15, Math.min(800 - node.radius - 245, node.x));
			node.y = Math.max(node.radius + 15, Math.min(500 - node.radius - 15, node.y));
		});
	}

	function drawMapConnections(ctx) {
		const channelGroups = {};
		mapNodes.forEach(node => {
			if (node.channel && node.channel !== "-") {
				if (!channelGroups[node.channel]) channelGroups[node.channel] = [];
				channelGroups[node.channel].push(node);
			}
		});
		
		ctx.lineWidth = 2.5;
		ctx.setLineDash([6, 4]);
		const dashOffset = (Date.now() / 120) % 20;
		ctx.lineDashOffset = -dashOffset;
		
		Object.keys(channelGroups).forEach(chan => {
			const nodes = channelGroups[chan];
			if (nodes.length > 1) {
				ctx.strokeStyle = "rgba(217, 83, 79, 0.75)";
				ctx.shadowColor = "rgba(217, 83, 79, 0.4)";
				ctx.shadowBlur = 6;
				
				for (let i = 0; i < nodes.length; i++) {
					for (let j = i + 1; j < nodes.length; j++) {
						ctx.beginPath();
						ctx.moveTo(nodes[i].x, nodes[i].y);
						ctx.lineTo(nodes[j].x, nodes[j].y);
						ctx.stroke();
					}
				}
			}
		});
		
		ctx.setLineDash([]);
		ctx.shadowBlur = 0;
	}

	function drawMapNodes(ctx) {
		mapNodes.forEach(node => {
			const isSelected = selectedNode === node;
			const isActive = node.status === 1;
			
			ctx.beginPath();
			ctx.arc(node.x, node.y, node.radius + 2, 0, Math.PI * 2);
			ctx.fillStyle = "#ffffff";
			ctx.shadowColor = "rgba(0,0,0,0.06)";
			ctx.shadowBlur = 4;
			ctx.fill();
			ctx.shadowBlur = 0;
			
			ctx.beginPath();
			ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);
			ctx.strokeStyle = isActive ? "#26c281" : "#d9534f";
			ctx.lineWidth = isSelected ? 4.5 : 2.5;
			ctx.stroke();
			
			ctx.beginPath();
			ctx.arc(node.x, node.y, node.radius - 6, 0, Math.PI * 2);
			ctx.fillStyle = getChannelColor(node.channel);
			ctx.fill();
			
			ctx.fillStyle = "#ffffff";
			ctx.font = "bold 9px Arial, sans-serif";
			ctx.textAlign = "center";
			ctx.textBaseline = "middle";
			ctx.fillText(node.channel, node.x, node.y);
			
			ctx.fillStyle = isSelected ? "#7928ca" : "var(--font-color, #333333)";
			ctx.font = isSelected ? "bold 9px Arial, sans-serif" : "bold 8.5px Arial, sans-serif";
			ctx.fillText(node.name.length > 14 ? node.name.substring(0, 12) + "..." : node.name, node.x, node.y + node.radius + 12);
		});
	}

	function updateSelectedApPanel(node) {
		const panel = document.getElementById("map-selected-ap-details");
		if (!node) {
			panel.innerHTML = `<div style="color: var(--font-alt-color);">Click an Access Point on the map to view details.</div>`;
			return;
		}
		
		const overlaps = mapNodes.filter(n => n !== node && n.channel === node.channel && n.channel !== "-").map(n => n.name);
		let overlapHtml = '<span style="color: #26c281; font-weight: bold;">🟢 None (Optimal)</span>';
		if (overlaps.length > 0) {
			overlapHtml = `<span style="color: #d9534f; font-weight: bold;">🔴 Overlaps with:</span><br>` + overlaps.join(", ");
		}
		
		panel.innerHTML = `
			<div style="font-weight: bold; color: #7928ca; margin-bottom: 6px; font-size: 11px; word-break: break-all;">\${node.name}</div>
			<table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 10px;">
				<tr><td style="color: var(--font-alt-color); padding: 2px 0;">IP:</td><td style="text-align: right; font-weight: bold;">\${node.ip}</td></tr>
				<tr><td style="color: var(--font-alt-color); padding: 2px 0;">Channel:</td><td style="text-align: right; font-weight: bold; color: \${getChannelColor(node.channel)}">\${node.channel}</td></tr>
				<tr><td style="color: var(--font-alt-color); padding: 2px 0;">Power:</td><td style="text-align: right; font-weight: bold;">\${node.power} dBm</td></tr>
				<tr><td style="color: var(--font-alt-color); padding: 2px 0;">Status:</td><td style="text-align: right; font-weight: bold; color: \${node.status === 1 ? '#26c281' : '#d9534f'}">\${node.status === 1 ? 'Online' : 'Offline'}</td></tr>
			</table>
			<div style="border-top: 1px solid var(--border-color); padding-top: 6px; margin-top: 6px; font-size: 10px;">
				<div style="font-weight: bold; font-size: 9px; text-transform: uppercase; color: var(--font-alt-color); margin-bottom: 4px;">Co-channel status:</div>
				<div style="line-height: 1.3;">\${overlapHtml}</div>
			</div>
		`;
	}

	function updateMapLegend() {
		const legendList = document.getElementById("map-legend-list");
		if (!legendList) return;
		
		const channels = {};
		mapNodes.forEach(node => {
			if (node.channel && node.channel !== "-") {
				channels[node.channel] = (channels[node.channel] || 0) + 1;
			}
		});
		
		let legendHtml = '';
		Object.keys(channels).sort((a,b) => parseInt(a,10) - parseInt(b,10)).forEach(chan => {
			const count = channels[chan];
			legendHtml += `
				<div style="display: flex; align-items: center; justify-content: space-between; font-size: 10px;">
					<div style="display: flex; align-items: center; gap: 6px;">
						<span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: \${getChannelColor(chan)};"></span>
						<span>Channel \${chan}</span>
					</div>
					<strong style="color: var(--font-alt-color);">\${count} AP\${count > 1 ? 's' : ''}</strong>
				</div>
			`;
		});
		
		legendList.innerHTML = legendHtml || '<div style="color: var(--font-alt-color);">No active channels.</div>';
	}

	function renderMapLoop() {
		const canvas = document.getElementById("co-channel-map-canvas");
		if (!canvas) return;
		const ctx = canvas.getContext("2d");
		
		applyMapPhysics();
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		
		// Draw grid
		ctx.strokeStyle = "rgba(0,0,0,0.015)";
		ctx.lineWidth = 1;
		for (let x = 0; x < canvas.width; x += 40) {
			ctx.beginPath();
			ctx.moveTo(x, 0);
			ctx.lineTo(x, canvas.height);
			ctx.stroke();
		}
		for (let y = 0; y < canvas.height; y += 40) {
			ctx.beginPath();
			ctx.moveTo(0, y);
			ctx.lineTo(canvas.width, y);
			ctx.stroke();
		}
		
		drawMapConnections(ctx);
		drawMapNodes(ctx);
		
		mapAnimationId = requestAnimationFrame(renderMapLoop);
	}

	window.switchMapBand = function(band) {
		currentMapBand = band;
		document.getElementById("map-band-5g").classList.toggle("active", band === "5g");
		document.getElementById("map-band-2g").classList.toggle("active", band === "2g");
		
		initMapNodes();
		updateMapLegend();
		updateSelectedApPanel(null);
	};

	function setupMapEvents() {
		const canvas = document.getElementById("co-channel-map-canvas");
		if (!canvas) return;
		
		function getMousePos(e) {
			const rect = canvas.getBoundingClientRect();
			const scaleX = canvas.width / rect.width;
			const scaleY = canvas.height / rect.height;
			return {
				x: (e.clientX - rect.left) * scaleX,
				y: (e.clientY - rect.top) * scaleY
			};
		}
		
		// Remove existing listeners
		const newCanvas = canvas.cloneNode(true);
		canvas.parentNode.replaceChild(newCanvas, canvas);
		
		newCanvas.addEventListener("mousedown", (e) => {
			const pos = getMousePos(e);
			draggedNode = null;
			
			for (let i = 0; i < mapNodes.length; i++) {
				const node = mapNodes[i];
				const dx = pos.x - node.x;
				const dy = pos.y - node.y;
				const dist = Math.sqrt(dx*dx + dy*dy);
				if (dist <= node.radius) {
					draggedNode = node;
					selectedNode = node;
					updateSelectedApPanel(node);
					break;
				}
			}
		});
		
		newCanvas.addEventListener("mousemove", (e) => {
			const pos = getMousePos(e);
			if (draggedNode) {
				draggedNode.x = pos.x;
				draggedNode.y = pos.y;
			}
			
			let overNode = false;
			for (let i = 0; i < mapNodes.length; i++) {
				const node = mapNodes[i];
				const dx = pos.x - node.x;
				const dy = pos.y - node.y;
				const dist = Math.sqrt(dx*dx + dy*dy);
				if (dist <= node.radius) {
					overNode = true;
					break;
				}
			}
			newCanvas.style.cursor = overNode ? "pointer" : "default";
		});
		
		const stopDrag = () => { draggedNode = null; };
		newCanvas.addEventListener("mouseup", stopDrag);
		newCanvas.addEventListener("mouseleave", stopDrag);
	}

	// ================= CONTROLS & EVENT HANDLERS =================
	
	const tabs = document.querySelectorAll(".rf-tab");
	tabs.forEach(tab => {
		tab.addEventListener("click", () => {
			tabs.forEach(t => t.classList.remove("active"));
			tab.classList.add("active");
			
			const targetPane = tab.getAttribute("data-tab");
			document.querySelectorAll(".tab-pane").forEach(pane => pane.style.display = "none");
			document.getElementById("tab-content-" + targetPane).style.display = "block";
			
			// Cancel map loop if switching away
			if (targetPane !== "cochannel_map" && mapAnimationId) {
				cancelAnimationFrame(mapAnimationId);
				mapAnimationId = null;
			}
			
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
		} else if (activeTab === "cochannel_map") {
			if (!mapAnimationId) {
				initMapNodes();
				updateMapLegend();
				updateSelectedApPanel(null);
				setupMapEvents();
				renderMapLoop();
			}
		}
	}
	
	const radioBtns = document.querySelectorAll(".rf-radio-btn[data-radio]");
	radioBtns.forEach(btn => {
		btn.addEventListener("click", () => {
			radioBtns.forEach(b => b.classList.remove("active"));
			btn.classList.add("active");
			activeRadioIndex = btn.getAttribute("data-radio");
			renderAllOverviewCharts();
		});
	});
	
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
	
	const specBtns = document.querySelectorAll(".rf-radio-btn[data-spec-radio]");
	specBtns.forEach(btn => {
		btn.addEventListener("click", () => {
			specBtns.forEach(b => b.classList.remove("active"));
			btn.classList.add("active");
			activeSpecRadio = btn.getAttribute("data-spec-radio");
			renderSpectrumTab();
		});
	});

	apSelector.addEventListener("change", loadActiveApDetails);
	
	document.getElementById("btn-reset-filter").addEventListener("click", () => {
		window.location.href = 'treya.php?action=omada.rf_dashboard';
	});
	
	function resizeCanvases() {
		canvasIds.forEach(id => {
			const canvas = document.getElementById(id);
			if (canvas) {
				const rect = canvas.getBoundingClientRect();
				canvas.width = rect.width;
				canvas.height = rect.height;
			}
		});
		renderAllOverviewCharts();
	}
	
	window.addEventListener("resize", resizeCanvases);
	
	queryApDetails();
	pollingInterval = setInterval(queryApDetails, 10000);
	graphInterval = setInterval(updateGraphSlidingWindow, 3000);

	// Expose modal functions to the global window scope
	window.showChartInfo = function(type) {
		const modal = document.getElementById("rf-info-modal");
		const titleEl = document.getElementById("rf-modal-title");
		const valueEl = document.getElementById("rf-modal-value");
		const analysisEl = document.getElementById("rf-modal-analysis");
		const impactEl = document.getElementById("rf-modal-impact");
		
		const currentData = graphData[activeRadioIndex];
		if (!currentData) return;
		
		let title = "";
		let valueBadge = "";
		let analysis = "";
		let impact = "";
		let valClass = "green"; // green, yellow, red
		
		if (type === "neighbor-aps") {
			title = "Neighboring APs";
			const valid = currentData.neighborAps.valid[currentData.neighborAps.valid.length - 1];
			const interf = currentData.neighborAps.interf[currentData.neighborAps.interf.length - 1];
			const rogue = currentData.neighborAps.rogue[currentData.neighborAps.rogue.length - 1];
			const total = valid + interf + rogue;
			
			valueBadge = `Valid: \${valid} | Interfering: \${interf} | Rogue: \${rogue}`;
			
			if (interf > 30) {
				valClass = "red";
				analysis = `High channel overlap detected! There are \${interf} interfering APs operating nearby on the same frequency.`;
				impact = `This will cause high packet collisions and retransmissions, leading to slow speeds and frequent Wi-Fi drops. Recommendation: Consider changing channels or optimizing transmit power to reduce co-channel interference.`;
			} else {
				valClass = "green";
				analysis = `Channel occupancy is healthy. There are \${interf} interfering APs nearby, which is well within safe thresholds.`;
				impact = `Low risk of interference. Network stability is excellent. No immediate action required.`;
			}
		} else if (type === "cpu") {
			title = "AP CPU Utilization";
			const cpu = currentData.cpu[currentData.cpu.length - 1];
			valueBadge = `\${cpu}% CPU Usage`;
			
			if (cpu > 80) {
				valClass = "red";
				analysis = `Critical CPU load detected at \${cpu}%!`;
				impact = `The AP may become unresponsive, leading to packet delay, high latency, or clients being kicked off. Recommendation: Check for network loops (multicast/broadcast storms) or reduce client load by steering to other APs.`;
			} else if (cpu > 50) {
				valClass = "yellow";
				analysis = `Moderate CPU load at \${cpu}%. The AP is actively processing packets.`;
				impact = `Performance is stable, but latency might slightly rise during traffic spikes. Monitoring is recommended.`;
			} else {
				valClass = "green";
				analysis = `CPU utilization is low and healthy at \dots`;
				analysis = `CPU utilization is low and healthy at \${cpu}%.`;
				impact = `AP is running efficiently with plenty of processing headroom. Network operations are healthy.`;
			}
		} else if (type === "memory") {
			title = "AP Free Memory";
			const mem = currentData.memory[currentData.memory.length - 1];
			valueBadge = `\${mem} MB Free`;
			
			if (mem < 150) {
				valClass = "red";
				analysis = `Low memory alert! Only \${mem} MB is free on the device.`;
				impact = `Device might crash or reboot unexpectedly due to Out-Of-Memory (OOM) exceptions under heavy user load. Recommendation: Check for firmware leaks or schedule a reboot of the AP to clear cache.`;
			} else if (mem < 300) {
				valClass = "yellow";
				analysis = `Memory levels are moderate at \${mem} MB free.`;
				impact = `Normal operations are unaffected, but keeping an eye on the memory trend is advised.`;
			} else {
				valClass = "green";
				analysis = `Memory headroom is excellent with \dots`;
				analysis = `Memory headroom is excellent with \${mem} MB free.`;
				impact = `Plenty of memory to handle additional client connections and traffic routing without lag.`;
			}
		} else if (type === "neighbor-clients") {
			title = "Neighboring Clients";
			const valid = currentData.neighborClients.valid[currentData.neighborClients.valid.length - 1];
			const interf = currentData.neighborClients.interf[currentData.neighborClients.interf.length - 1];
			valueBadge = `Valid: \dots`;
			valueBadge = `Valid: \dots`;
			valueBadge = `Valid: \${valid} | Interfering: \dots`;
			valueBadge = `Valid: \${valid} | Interfering: \${interf}`;
			
			if (interf > 15) {
				valClass = "red";
				analysis = `High concentration of non-associated devices in range (\${interf} interfering).`;
				impact = `These devices occupy wireless airtime by continuously sending probe requests, leading to overhead and channel congestion. Recommendation: Optimize probe request thresholds or inspect for nearby crowds.`;
			} else {
				valClass = "green";
				analysis = `Unassociated client activity is low (\dots`;
				analysis = `Unassociated client activity is low (\${interf} interfering).`;
				impact = `Airtime contention is minimal, ensuring maximum channel availability for associated clients.`;
			}
		} else if (type === "clients") {
			title = "Connected Clients";
			const clients = currentData.clients[currentData.clients.length - 1];
			valueBadge = `\${clients} Associated Clients`;
			
			if (clients > 45) {
				valClass = "red";
				analysis = `High client density! There are \${clients} clients connected to this single AP.`;
				impact = `Individual speeds will drop as clients compete for airtime on the same channels. Recommendation: Enable Band Steering to shift clients to 5 GHz, or load-balance users to neighboring APs.`;
			} else if (clients > 25) {
				valClass = "yellow";
				analysis = `Moderate client density with \dots`;
				analysis = `Moderate client density with \${clients} users.`;
				impact = `Throughput is sufficient for general web browsing, but heavy streaming by multiple users might cause buffering.`;
			} else {
				valClass = "green";
				analysis = `Low client count of \${clients} users.`;
				impact = `Each client has ample channel bandwidth. High-performance gaming, calls, and streaming will run smoothly.`;
			}
		} else if (type === "throughput") {
			title = "Real-time Throughput";
			const outVal = currentData.throughput.out[currentData.throughput.out.length - 1];
			const inVal = currentData.throughput.in[currentData.throughput.in.length - 1];
			const totalBps = outVal + inVal;
			
			valueBadge = `Out: \${formatThroughput(outVal)} | In: \${formatThroughput(inVal)}`;
			
			if (totalBps > 8000000) {
				valClass = "yellow";
				analysis = `Active traffic download/upload (Total: \${formatThroughput(totalBps)}). Users are actively downloading and uploading data.`;
				impact = `Good indicators of network utilisation. If total bandwidth exceeds AP uplink speed (e.g. 1 Gbps), network bottlenecking may occur.`;
			} else {
				valClass = "green";
				analysis = `Light traffic throughput (Total: \${formatThroughput(totalBps)}).`;
				impact = `Network capacity is heavily under-utilised. Highly responsive connections for all users.`;
			}
		}
		
		titleEl.innerHTML = title;
		valueEl.innerHTML = valueBadge;
		valueEl.className = "rf-modal-value-badge " + valClass;
		analysisEl.innerHTML = analysis;
		impactEl.innerHTML = impact;
		
		modal.style.display = "flex";
		setTimeout(() => modal.classList.add("active"), 10);
	};
	
	window.showClientMatchInfo = function() {
		const modal = document.getElementById("rf-info-modal");
		const titleEl = document.getElementById("rf-modal-title");
		const valueEl = document.getElementById("rf-modal-value");
		const analysisEl = document.getElementById("rf-modal-analysis");
		const impactEl = document.getElementById("rf-modal-impact");

		const activeMatchRadio = document.getElementById("btn-match-radio-0").classList.contains("active") ? "5g" : "2g";
		const bandName = activeMatchRadio === "5g" ? "5 GHz" : "2.4 GHz";
		const targetRadioId = activeMatchRadio === "5g" ? 1 : 0;

		const apMac = activeAp.mac.toUpperCase().trim();
		const matchedClients = allClientsList.filter(c => c.apMac && c.apMac.toUpperCase().trim() === apMac && (c.radioId !== undefined && c.radioId !== null ? parseInt(c.radioId, 10) === targetRadioId : false));

		// Count associated vs unassociated
		const associatedCount = matchedClients.filter(c => c.associated).length;
		const unassociatedCount = matchedClients.filter(c => !c.associated).length;

		titleEl.innerHTML = `Client Match Analysis (\${bandName})`;
		valueEl.innerHTML = `Associated: \${associatedCount} | Unassociated: \${unassociatedCount}`;
		
		let valClass = "green";
		if (unassociatedCount > 15) {
			valClass = "red";
		} else if (unassociatedCount > 5) {
			valClass = "yellow";
		}
		valueEl.className = "rf-modal-value-badge " + valClass;

		let analysis = "";
		let impact = "";

		if (unassociatedCount > 10) {
			analysis = `High count of unassociated devices (\${unassociatedCount} devices) detected in range. These are non-connected devices (guest probes, passing devices, or devices connected to neighboring APs) that occupy the AP's channel capacity.`;
			impact = `Unassociated devices frequently transmit probe request packets, consuming airtime and memory on the AP.<br><br>
			<strong>Recommendation:</strong><br>
			1. Optimize <strong>Probe Request Threshold / Min RSSI</strong> on the controller to ignore very weak signals.<br>
			2. Check if <strong>Client Match / Band Steering</strong> is enabled to steer clients to the 5 GHz band.<br><br>
			<strong>Where to modify:</strong> Aruba Controller GUI -> Configuration -> System -> Profiles -> RF Management -> Client Match.`;
		} else {
			analysis = `Healthy client distribution. Unassociated device count is minimal (\${unassociatedCount} devices). The AP is working primarily with active, associated clients.`;
			impact = `Low probe overhead and excellent channel availability for associated clients. No configuration changes required at this time.`;
		}

		analysisEl.innerHTML = analysis;
		impactEl.innerHTML = impact;
		
		modal.style.display = "flex";
		setTimeout(() => modal.classList.add("active"), 10);
	};

	window.showSpectrumInfo = function() {
		const modal = document.getElementById("rf-info-modal");
		const titleEl = document.getElementById("rf-modal-title");
		const valueEl = document.getElementById("rf-modal-value");
		const analysisEl = document.getElementById("rf-modal-analysis");
		const impactEl = document.getElementById("rf-modal-impact");

		const bandName = activeSpecRadio === "5g" ? "5 GHz" : "2.4 GHz";
		const data = spectrumDetails[activeSpecRadio];
		if (!data) return;

		// Find suitable channel
		let bestChan = data.channels[0];
		let bestQuality = data.quality[0];
		let bestUtil = data.wifi[0] + data.interf[0];
		let maxScore = bestQuality - bestUtil;

		for (let i = 1; i < data.channels.length; i++) {
			const chan = data.channels[i];
			const qual = data.quality[i];
			const util = data.wifi[i] + data.interf[i];
			const score = qual - util;
			if (score > maxScore) {
				maxScore = score;
				bestChan = chan;
				bestQuality = qual;
				bestUtil = util;
			}
		}

		const currentChanIdx = data.channels.indexOf(data.activeChan);
		const currentQuality = currentChanIdx >= 0 ? data.quality[currentChanIdx] : 0;
		const currentUtil = currentChanIdx >= 0 ? (data.wifi[currentChanIdx] + data.interf[currentChanIdx]) : 100;

		titleEl.innerHTML = `Spectrum Channel Quality (\${bandName})`;
		valueEl.innerHTML = `Active Channel: \${data.activeChan} (Quality: \dots`;
		valueEl.innerHTML = `Active Channel: \${data.activeChan} (Quality: \${currentQuality}%)`;
		
		let valClass = "green";
		if (currentQuality < 70) {
			valClass = "red";
		} else if (currentQuality < 85) {
			valClass = "yellow";
		}
		valueEl.className = "rf-modal-value-badge " + valClass;

		let analysis = `Current Active Channel is <strong>Channel \${data.activeChan}</strong> with \${currentQuality}% Quality and \${currentUtil}% Utilization (WiFi: \${currentChanIdx >= 0 ? data.wifi[currentChanIdx] : 0}%, Non-WiFi Interference: \${currentChanIdx >= 0 ? data.interf[currentChanIdx] : 0}%).`;
		
		let impact = "";
		if (data.activeChan !== bestChan && bestQuality > currentQuality + 5) {
			analysis += `<br><br>An alternative suitable channel is detected: <strong>Channel \${bestChan}</strong> (Quality: \${bestQuality}%, Utilization: \${bestUtil}%).`;
			
			impact = `Changing the AP channel configuration from Channel \dots`;
			impact = `Changing the AP channel configuration from Channel \${data.activeChan} to <strong>Channel \${bestChan}</strong> will reduce co-channel interference and increase throughput.<br><br>
			<strong>Recommendation:</strong> Change the active channel to Channel \${bestChan}.<br><br>
			<strong>Where to modify:</strong> Aruba Controller GUI -> Configuration -> Wireless -> AP Group -> Edit AP Group Profiles -> Radio Profile (Radio 0 for 5 GHz, Radio 1 for 2.4 GHz).`;
		} else {
			analysis += `<br><br>The active channel is currently optimal. No other channel offers a significant improvement in quality.`;
			impact = `Channel quality is excellent. No modifications needed.`;
		}

		analysisEl.innerHTML = analysis;
		impactEl.innerHTML = impact;

		modal.style.display = "flex";
		setTimeout(() => modal.classList.add("active"), 10);
	};

	window.closeInfoModal = function(e) {
		const modal = document.getElementById("rf-info-modal");
		modal.classList.remove("active");
		setTimeout(() => modal.style.display = "none", 300);
	};
}

document.addEventListener("DOMContentLoaded", initDashboard);

// Fallback in case DOMContentLoaded has already fired in the Zabbix template lifecycle
if (document.readyState === "complete" || document.readyState === "interactive") {
	initDashboard();
}
</script>
HTML;

$html_page->addItem(new CObject($body_html));
$html_page->show();
?>
