<?php
// brand.conf.php - Custom Branding Configuration for Zabbix Frontend
// Brand: Treya Wireless

return [
	'BRAND_LOGO' => 'local/logo_normal.svg',
	'BRAND_LOGO_SIDEBAR' => 'local/logo_sidebar.svg',
	'BRAND_LOGO_SIDEBAR_COMPACT' => 'local/logo_sidebar_compact.svg',
	'BRAND_HELP_URL' => 'https://www.treyawireless.com/support',
	'BRAND_FOOTER' => [
		'Treya Wireless NOC. ',
		COPYR(), ' 2026 ',
		(new CLink('Treya Wireless', 'https://www.treyawireless.com/'))
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT)
			->setTarget('_blank')
	]
];
