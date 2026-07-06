<?php
/*
** Treya Wireless Web Frontend Router
**/

// Redirect GET browser requests to treya.php for clean URL bar, but execute POST/AJAX requests directly
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$is_post && !$is_ajax) {
	$query_str = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '';
	header('Location: treya.php'.$query_str, true, 302);
	exit;
}

require_once dirname(__FILE__).'/treya.php';
