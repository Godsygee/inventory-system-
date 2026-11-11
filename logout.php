<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
	http_response_code(400);
	exit('Invalid sign-out request.');
}

unset($_SESSION['auth_user']);
session_regenerate_id(true);
header('Location: login.php?logged_out=1');
exit;