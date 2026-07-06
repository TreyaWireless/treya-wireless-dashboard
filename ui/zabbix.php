<?php
/*
** Treya Wireless Web Frontend Router
** Redirects all legacy zabbix.php requests to treya.php
**/

$query_str = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '';
header('Location: treya.php'.$query_str, true, 301);
exit;
