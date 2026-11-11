<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

if (!isset($_SESSION['inventory_items'])) {
	$_SESSION['inventory_items'] = [
		['sku' => 'WH-2048', 'name' => 'Wireless Headphones', 'category' => 'Electronics', 'stock' => 42, 'reorder' => 20, 'price' => 89.00, 'location' => 'A-01-04'],
		['sku' => 'OF-1182', 'name' => 'A5 Notebook Set', 'category' => 'Office', 'stock' => 8, 'reorder' => 15, 'price' => 12.50, 'location' => 'B-03-02'],
		['sku' => 'HM-3301', 'name' => 'Ceramic Pour-over Set', 'category' => 'Home', 'stock' => 0, 'reorder' => 10, 'price' => 34.00, 'location' => 'C-02-01'],
		['sku' => 'EL-9027', 'name' => 'USB-C Charging Cable', 'category' => 'Electronics', 'stock' => 126, 'reorder' => 30, 'price' => 14.99, 'location' => 'A-02-06'],
		['sku' => 'OF-4420', 'name' => 'Desk Organizer Tray', 'category' => 'Office', 'stock' => 11, 'reorder' => 12, 'price' => 22.00, 'location' => 'B-01-03'],
		['sku' => 'HM-7714', 'name' => 'Linen Table Runner', 'category' => 'Home', 'stock' => 34, 'reorder' => 10, 'price' => 28.00, 'location' => 'C-04-02'],
	];
}

if (!isset($_SESSION['suppliers'])) {
	$_SESSION['suppliers'] = [
		['id' => 'SUP-1001', 'name' => 'Northwest Audio Supply', 'contact' => 'Maya Chen', 'email' => 'maya@nwaudio.example', 'phone' => '(503) 555-0148', 'lead_days' => 5],
		['id' => 'SUP-1002', 'name' => 'Paper & Pine Wholesale', 'contact' => 'Leo Brooks', 'email' => 'leo@paperpine.example', 'phone' => '(503) 555-0182', 'lead_days' => 3],
		['id' => 'SUP-1003', 'name' => 'Hearthside Goods', 'contact' => 'Avery Jones', 'email' => 'avery@hearthside.example', 'phone' => '(503) 555-0127', 'lead_days' => 7],
	];
}

if (!isset($_SESSION['purchase_orders'])) {
	$_SESSION['purchase_orders'] = [];
}

if (!isset($_SESSION['inventory_settings'])) {
	$_SESSION['inventory_settings'] = [
		'warehouse_name' => 'Main warehouse',
		'warehouse_location' => 'Portland, Oregon',
		'default_reorder' => 10,
		'low_stock_notifications' => true,
	];
}

$inventorySettings = &$_SESSION['inventory_settings'];

function inventoryTextLimit(string $value, int $length): string
{
	return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

if (empty($_SESSION['inventory_csrf'])) {
	$_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($currentScript !== '' && $currentScript !== 'login.php' && empty($_SESSION['auth_user'])) {
	header('Location: login.php');
	exit;
}