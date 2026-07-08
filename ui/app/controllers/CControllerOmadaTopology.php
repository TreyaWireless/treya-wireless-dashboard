<?php declare(strict_types=1);

class CControllerOmadaTopology extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'groupids' => 'array_db hosts_groups.groupid',
			'hostids' => 'array_db hosts.hostid'
		];
		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_MAPS);
	}

	protected function doAction(): void {
		$groupids = $this->getInput('groupids', []);
		$hostids = $this->getInput('hostids', []);
		
		// Fetch hosts with any vendor URL macro configured
		$all_omada_hostids = [];
		$db_omada_hosts = DBselect(
			'SELECT DISTINCT hostid FROM hostmacro WHERE macro IN (\'{$OMADA_URL}\', \'{$ARUBA_URL}\', \'{$RUCKUS_URL}\', \'{$RUCUS_URL}\', \'{$CAMBIUM_URL}\')'
		);
		while ($row = DBfetch($db_omada_hosts)) {
			$all_omada_hostids[] = $row['hostid'];
		}

		if ($all_omada_hostids) {
			$permitted_hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $all_omada_hostids,
				'preservekeys' => true
			]);
			$all_omada_hostids = array_keys($permitted_hosts);
		}

		$resolved_hostids = [];

		if (!$groupids && !$hostids) {
			// If filters are empty, fallback to ALL Omada hosts
			$resolved_hostids = $all_omada_hostids;
		}
		else {
			$temp_hostids = $hostids;

			if ($groupids) {
				// Query all hosts in the selected groups
				$db_group_hosts = API::Host()->get([
					'output' => ['hostid'],
					'groupids' => $groupids,
					'preservekeys' => true
				]);
				$temp_hostids = array_unique(array_merge($temp_hostids, array_keys($db_group_hosts)));
			}

			// Filter to only those with Omada macro configured
			$resolved_hostids = array_values(array_intersect($temp_hostids, $all_omada_hostids));
		}
		
		// Fetch selected groups for multiselect
		$groups_ms = [];
		if ($groupids) {
			$db_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);
			foreach ($db_groups as $group) {
				$groups_ms[] = [
					'id' => $group['groupid'],
					'name' => $group['name']
				];
			}
		}
		
		// Fetch selected hosts for multiselect
		$hosts_ms = [];
		if ($hostids) {
			$db_hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $hostids,
				'preservekeys' => true
			]);
			foreach ($db_hosts as $host) {
				$hosts_ms[] = [
					'id' => $host['hostid'],
					'name' => $host['name']
				];
			}
		}
		
		$data = [
			'groups_multiselect' => $groups_ms,
			'hosts_multiselect' => $hosts_ms,
			'resolved_hostids' => $resolved_hostids,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];
		
		$response = new CControllerResponseData($data);
		$response->setTitle(_('Network topology'));
		$this->setResponse($response);
	}
}
