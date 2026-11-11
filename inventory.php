<?php
require_once __DIR__ . '/bootstrap.php';

$categories = ['Electronics', 'Office', 'Home'];
$items = &$_SESSION['inventory_items'];
$error = '';

function inventoryEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function inventoryStatus(array $item): array
{
	if ($item['stock'] === 0) {
		return ['Out of stock', 'out'];
	}
	if ($item['stock'] <= $item['reorder']) {
		return ['Low stock', 'low'];
	}
	return ['In stock', 'good'];
}

function inventoryRedirect(): never
{
	header('Location: inventory.php');
	exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="inventory.csv"');
	$output = fopen('php://output', 'w');
	fputcsv($output, ['SKU', 'Product', 'Category', 'In stock', 'Reorder point', 'Unit price', 'Location', 'Status']);
	foreach ($items as $item) {
		[$status] = inventoryStatus($item);
		fputcsv($output, [$item['sku'], $item['name'], $item['category'], $item['stock'], $item['reorder'], number_format($item['price'], 2, '.', ''), $item['location'], $status]);
	}
	fclose($output);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
		http_response_code(400);
		$error = 'Your session expired. Refresh the page and try again.';
	} else {
		$action = $_POST['action'] ?? '';
		$sku = strtoupper(trim($_POST['sku'] ?? ''));

		if ($action === 'delete') {
			$openOrders = array_filter($_SESSION['purchase_orders'], fn ($order) => $order['sku'] === $sku && $order['status'] === 'Ordered');
			if ($openOrders) {
				$error = 'This product has an open purchase order and cannot be deleted.';
			} else {
				$items = array_values(array_filter($items, fn ($item) => $item['sku'] !== $sku));
				$_SESSION['inventory_flash'] = 'Product deleted.';
				inventoryRedirect();
			}
		} else {

		$name = trim($_POST['name'] ?? '');
		$category = $_POST['category'] ?? '';
		$location = strtoupper(trim($_POST['location'] ?? ''));
		$stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
		$reorder = filter_var($_POST['reorder'] ?? null, FILTER_VALIDATE_INT);
		$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
		$originalSku = strtoupper(trim($_POST['original_sku'] ?? ''));

		if ($sku === '' || $name === '' || $location === '' || !in_array($category, $categories, true)
			|| $stock === false || $stock < 0 || $reorder === false || $reorder < 0
			|| $price === false || $price < 0) {
			$error = 'Enter a valid product name, SKU, category, stock, reorder point, price, and location.';
		} elseif ($action === 'create' || $action === 'update') {
			$duplicate = array_filter($items, fn ($item) => $item['sku'] === $sku && ($action !== 'update' || $item['sku'] !== $originalSku));
			$existingIndex = array_search($originalSku, array_column($items, 'sku'), true);
			$skuHasOpenOrders = $action === 'update' && $sku !== $originalSku && array_filter(
				$_SESSION['purchase_orders'],
				fn ($order) => $order['sku'] === $originalSku && $order['status'] === 'Ordered'
			);

			if ($duplicate) {
				$error = 'That SKU is already in your inventory.';
			} elseif ($skuHasOpenOrders) {
				$error = 'This product has an open purchase order, so its SKU cannot be changed.';
			} elseif ($action === 'update' && $existingIndex === false) {
				$error = 'This product could not be found. Refresh the page and try again.';
			} else {
				$product = [
					'sku' => $sku,
					'name' => inventoryTextLimit($name, 80),
					'category' => $category,
					'stock' => $stock,
					'reorder' => $reorder,
					'price' => round((float) $price, 2),
					'location' => inventoryTextLimit($location, 30),
				];

				if ($action === 'create') {
					$items[] = $product;
					$_SESSION['inventory_flash'] = 'Product added to inventory.';
				} else {
					$items[$existingIndex] = $product;
					$_SESSION['inventory_flash'] = 'Product details updated.';
				}
				inventoryRedirect();
			}
		} else {
			$error = 'Choose a valid inventory action.';
		}
		}
	}
}

$flash = $_SESSION['inventory_flash'] ?? '';
unset($_SESSION['inventory_flash']);
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$filteredItems = array_filter($items, function ($item) use ($search, $categoryFilter, $statusFilter) {
	[$status, $statusClass] = inventoryStatus($item);
	$matchesSearch = $search === '' || stripos(implode(' ', [$item['sku'], $item['name'], $item['category'], $item['location']]), $search) !== false;
	$matchesCategory = $categoryFilter === 'all' || $item['category'] === $categoryFilter;
	$matchesStatus = $statusFilter === 'all'
		|| ($statusFilter === 'alerts' && $statusClass !== 'good')
		|| ($statusFilter === 'low' && $statusClass === 'low')
		|| ($statusFilter === 'out' && $statusClass === 'out');
	return $matchesSearch && $matchesCategory && $matchesStatus;
});
$totalUnits = array_sum(array_column($items, 'stock'));
$lowCount = count(array_filter($items, fn ($item) => $item['stock'] > 0 && $item['stock'] <= $item['reorder']));
$outCount = count(array_filter($items, fn ($item) => $item['stock'] === 0));
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Inventory | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --ink:#202b28; --muted:#78827d; --line:#e6eae5; --paper:#fff; --canvas:#f4f6f2; --green:#2e725e; --green-dark:#235a4a; --green-pale:#e9f3ee; --amber:#a96818; --amber-pale:#fff3df; --red:#b94c43; --red-pale:#fff0ed; --shadow:0 12px 32px rgba(34,54,45,.055); }
		* { box-sizing:border-box; }
		body { margin:0; color:var(--ink); background:var(--canvas); font:14px 'DM Sans',sans-serif; }
		button,input,select { font:inherit; }
		button { cursor:pointer; }
		.app { min-height:100vh; display:grid; grid-template-columns:248px minmax(0,1fr); }
		.sidebar { display:flex; flex-direction:column; padding:27px 18px 20px; border-right:1px solid var(--line); background:#fcfdfb; }
		.brand { display:flex; align-items:center; gap:11px; padding:0 12px; margin-bottom:42px; }
		.brand-mark { display:grid; place-items:center; width:35px; height:35px; border-radius:10px; color:#fff; background:var(--green); }
		.brand-name { font:800 17px 'Manrope',sans-serif; }
		.brand-name span { color:var(--green); }
		.nav-label { margin:0 12px 10px; color:#a1aaa5; font-size:10px; font-weight:700; letter-spacing:1.1px; text-transform:uppercase; }
		.nav { display:grid; gap:5px; }
		.nav a { display:flex; align-items:center; gap:12px; min-height:43px; padding:0 12px; border-radius:7px; color:#727d77; text-decoration:none; font-weight:600; }
		.nav a.active { color:var(--green-dark); background:var(--green-pale); }
		.nav svg { width:18px; height:18px; flex:none; fill:none; stroke:currentColor; stroke-width:1.7; stroke-linecap:round; stroke-linejoin:round; }
		.nav-count { margin-left:auto; color:var(--amber); font-size:12px; }
		.sidebar-bottom { margin-top:auto; }
		.warehouse { margin:0 5px 17px; padding:13px; border:1px solid var(--line); border-radius:8px; background:#fff; }
		.warehouse-title { display:flex; align-items:center; gap:8px; font-weight:700; }
		.warehouse-title svg { width:17px; color:var(--green); }
		.warehouse p { margin:7px 0 0 25px; color:var(--muted); font-size:12px; }
		.profile { display:flex; align-items:center; gap:10px; padding:14px 8px 0; border-top:1px solid var(--line); }
		.avatar { display:grid; place-items:center; width:34px; height:34px; border-radius:50%; color:#fff; background:#b46e4f; font-size:12px; font-weight:700; }
		.profile strong,.profile small { display:block; }
		.profile strong { font-size:12px; }
		.profile small { margin-top:2px; color:var(--muted); font-size:11px; }
		main { min-width:0; padding:0 40px 42px; }
		.topbar { display:flex; align-items:center; justify-content:space-between; min-height:76px; border-bottom:1px solid var(--line); }
		.breadcrumb { color:var(--muted); font-size:12px; }
		.breadcrumb strong { color:var(--ink); font-weight:600; }
		.top-actions { display:flex; align-items:center; gap:12px; }
		.page-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; padding:31px 0 24px; }
		.eyebrow { margin-bottom:7px; color:var(--green); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
		h1 { margin:0; font:700 27px 'Manrope',sans-serif; }
		.page-heading p { margin:7px 0 0; color:var(--muted); font-size:13px; }
		.primary-button,.secondary-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:39px; padding:0 14px; border:1px solid transparent; border-radius:6px; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; }
		.primary-button { color:#fff; background:var(--green); }
		.primary-button:hover { background:var(--green-dark); }
		.secondary-button { color:#45524b; border-color:var(--line); background:#fff; }
		.primary-button svg,.secondary-button svg { width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
		.summary { display:flex; flex-wrap:wrap; gap:9px; margin-bottom:20px; }
		.summary span { padding:8px 11px; border:1px solid var(--line); border-radius:6px; color:#637069; background:#fff; font-size:11px; }
		.summary strong { color:var(--ink); }
		.panel { min-width:0; border:1px solid var(--line); border-radius:8px; background:var(--paper); box-shadow:var(--shadow); }
		.panel-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 19px 15px; }
		.panel-heading h2 { margin:0; font:700 15px 'Manrope',sans-serif; }
		.panel-heading p { margin:4px 0 0; color:var(--muted); font-size:11px; }
		.toolbar { display:flex; align-items:center; gap:9px; padding:0 19px 16px; }
		.search { display:flex; align-items:center; gap:8px; width:min(340px,100%); height:37px; padding:0 10px; border:1px solid var(--line); border-radius:6px; }
		.search svg { width:16px; height:16px; flex:none; fill:none; stroke:#98a19b; stroke-width:1.8; stroke-linecap:round; }
		.search input { width:100%; min-width:0; border:0; outline:0; color:var(--ink); font-size:12px; }
		select { height:37px; padding:0 28px 0 10px; border:1px solid var(--line); border-radius:6px; color:#57635c; background:#fff; font-size:11px; }
		.toolbar .spacer { flex:1; }
		.table-wrap { overflow:auto; }
		table { width:100%; border-collapse:collapse; text-align:left; }
		th { height:36px; padding:0 13px; color:#929b95; background:#fafbf9; font-size:9px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; white-space:nowrap; }
		th:first-child,td:first-child { padding-left:19px; }
		th:last-child,td:last-child { padding-right:19px; }
		td { height:59px; padding:0 13px; border-top:1px solid #eef0ed; color:#4e5953; font-size:11px; white-space:nowrap; }
		td strong { color:#29332e; font-size:11px; }
		.product { display:flex; align-items:center; gap:10px; min-width:205px; }
		.thumb { display:grid; place-items:center; width:34px; height:34px; flex:none; border-radius:7px; color:var(--green); background:#eff5f0; }
		.thumb.office { color:var(--amber); background:#fbf3e7; }
		.thumb.home { color:#9c6259; background:#f8efec; }
		.thumb svg { width:17px; height:17px; fill:none; stroke:currentColor; stroke-width:1.6; stroke-linecap:round; stroke-linejoin:round; }
		.product-info strong,.product-info span { display:block; }
		.product-info span { margin-top:3px; color:#8d9791; font-size:10px; }
		.badge { display:inline-flex; align-items:center; gap:5px; min-height:22px; padding:0 8px; border-radius:20px; font-size:10px; font-weight:700; }
		.badge::before { width:5px; height:5px; border-radius:50%; background:currentColor; content:''; }
		.badge.good { color:var(--green); background:var(--green-pale); }
		.badge.low { color:var(--amber); background:var(--amber-pale); }
		.badge.out { color:var(--red); background:var(--red-pale); }
		.row-actions { display:flex; justify-content:flex-end; gap:5px; }
		.row-actions button { min-height:29px; padding:0 8px; border:1px solid var(--line); border-radius:5px; color:#56635c; background:#fff; font-size:10px; font-weight:600; }
		.row-actions button:hover { border-color:#c5d8ce; color:var(--green-dark); }
		.row-actions .delete { color:#a6534c; }
		.empty { padding:36px 15px; color:var(--muted); text-align:center; }
		.notice { margin:0 0 14px; padding:11px 13px; border:1px solid #cde2d5; border-radius:6px; color:var(--green-dark); background:#eef7f1; font-size:12px; }
		.notice.error { border-color:#f0d1cb; color:#9c4038; background:var(--red-pale); }
		.dialog-backdrop { position:fixed; z-index:5; inset:0; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(25,39,32,.4); }
		.dialog-backdrop.open { display:flex; }
		.dialog { width:min(100%,460px); padding:22px; border-radius:9px; background:#fff; box-shadow:0 24px 70px rgba(23,40,31,.2); }
		.dialog-head { display:flex; justify-content:space-between; gap:12px; margin-bottom:18px; }
		.dialog h2 { margin:0; font:700 18px 'Manrope',sans-serif; }
		.dialog-head p { margin:5px 0 0; color:var(--muted); font-size:12px; }
		.close { width:30px; height:30px; border:0; border-radius:5px; color:#68746d; background:#f2f5f1; font-size:18px; }
		.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
		.field { display:grid; gap:6px; }
		.field.full { grid-column:1/-1; }
		.field label { color:#59645e; font-size:11px; font-weight:700; }
		.field input,.field select { width:100%; height:37px; padding:0 10px; border:1px solid var(--line); border-radius:5px; outline-color:var(--green); color:var(--ink); background:#fff; font-size:12px; }
		.dialog-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
		@media(max-width:1100px) { main { padding-right:25px; padding-left:25px; } }
		@media(max-width:800px) { .app { grid-template-columns:66px minmax(0,1fr); } .sidebar { align-items:center; padding:20px 8px; } .brand { padding:0; margin-bottom:35px; } .brand-name,.nav-label,.nav a span,.nav-count,.warehouse,.profile>div:last-child { display:none; } .nav a { justify-content:center; padding:0; } .sidebar-bottom { width:100%; } .profile { justify-content:center; padding-right:0; padding-left:0; } main { padding-right:18px; padding-left:18px; } }
		@media(max-width:560px) { .app { display:block; padding-bottom:58px; } .sidebar { position:fixed; z-index:3; right:0; bottom:0; left:0; flex-direction:row; justify-content:center; height:58px; padding:5px 12px; border-top:1px solid var(--line); border-right:0; background:#fff; } .brand,.sidebar-bottom { display:none; } .nav { display:flex; justify-content:space-around; max-width:420px; width:100%; } .nav a { width:48px; min-height:42px; } .nav a:nth-child(n+5) { display:none; } main { padding:0 14px 24px; } .topbar { min-height:59px; } .page-heading { align-items:flex-start; padding:25px 0 18px; } h1 { font-size:23px; } .page-heading p { max-width:230px; line-height:1.5; } .page-heading>.primary-button { width:39px; min-width:39px; padding:0; overflow:hidden; font-size:0; } .page-heading>.primary-button svg { width:17px; height:17px; flex:none; } .toolbar { align-items:stretch; flex-wrap:wrap; padding:0 12px 13px; } .search { width:100%; } .toolbar .spacer { display:none; } .toolbar select { flex:1; } .toolbar .secondary-button { flex:1; } th:first-child,td:first-child { padding-left:12px; } th:last-child,td:last-child { padding-right:12px; } }
		@media(max-width:560px) { .nav { display:flex; justify-content:flex-start; gap:0; width:100%; max-width:420px; overflow-x:auto; } .nav a { flex:0 0 40px; width:40px; min-width:40px; } .nav a:nth-child(n+5) { display:flex; } }
		.sign-out-form { margin:0; }
		.sign-out { min-height:32px; padding:0 10px; border:1px solid var(--line); border-radius:5px; color:#56635c; background:#fff; font-size:10px; font-weight:700; white-space:nowrap; }
	</style>
</head>
<body>
<div class="app">
	<aside class="sidebar" aria-label="Main navigation">
		<div class="brand"><div class="brand-mark" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4m-8 4v10"/></svg></div><div class="brand-name">stock<span>room</span></div></div>
		<div class="nav-label">Workspace</div>
		<nav class="nav">
			<a href="dashboard.php"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg><span>Overview</span></a>
			<a class="active" href="inventory.php" aria-current="page"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg><span>Inventory</span></a>
			<a href="suppliers.php"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3h6m-3-3v6"/></svg><span>Suppliers</span></a>
			<a href="orders.php"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8m-8 4h8m-8 4h5"/></svg><span>Purchase orders</span></a>
			<a href="inventory.php?status=alerts"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg><span>Low stock</span><?php if ($inventorySettings['low_stock_notifications']): ?><span class="nav-count"><?= $lowCount + $outCount ?></span><?php endif; ?></a>
			<a href="analytics.php"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom"><div class="warehouse"><div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg><?= inventoryEscape($inventorySettings['warehouse_name']) ?></div><p><?= inventoryEscape($inventorySettings['warehouse_location']) ?></p></div><div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div></div>
	</aside>
	<main>
		<header class="topbar"><div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Inventory</strong></div><div class="top-actions"><div class="avatar" aria-label="Jordan Davis">JD</div><form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= inventoryEscape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form></div></header>
		<section class="page-heading"><div><div class="eyebrow">Stockroom / <?= inventoryEscape($inventorySettings['warehouse_name']) ?></div><h1>Inventory</h1><p>Track product levels, locations, and replenishment.</p></div><button class="primary-button" type="button" id="open-dialog"><svg viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg>Add item</button></section>
		<?php if ($flash !== ''): ?><div class="notice" role="status"><?= inventoryEscape($flash) ?></div><?php endif; ?>
		<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= inventoryEscape($error) ?></div><?php endif; ?>
		<div class="summary"><span><strong><?= count($items) ?></strong> products</span><span><strong><?= number_format($totalUnits) ?></strong> units on hand</span><span><strong><?= $lowCount + $outCount ?></strong> replenishment alerts</span></div>
		<section class="panel" aria-labelledby="inventory-title">
			<div class="panel-heading"><div><h2 id="inventory-title">Product catalog</h2><p><?= count($filteredItems) ?> of <?= count($items) ?> products shown</p></div><a class="secondary-button" href="inventory.php?export=csv"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5 5 5 5-5m-5 5V3"/></svg>Export CSV</a></div>
			<form class="toolbar" method="get" action="inventory.php">
				<label class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" name="search" value="<?= inventoryEscape($search) ?>" placeholder="Search name, SKU, or location" aria-label="Search inventory"></label>
				<span class="spacer"></span>
				<select name="category" aria-label="Filter by category"><option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All categories</option><?php foreach ($categories as $category): ?><option value="<?= inventoryEscape($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= inventoryEscape($category) ?></option><?php endforeach; ?></select>
				<select name="status" aria-label="Filter by stock status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All stock</option><option value="alerts" <?= $statusFilter === 'alerts' ? 'selected' : '' ?>>Needs reorder</option><option value="low" <?= $statusFilter === 'low' ? 'selected' : '' ?>>Low stock</option><option value="out" <?= $statusFilter === 'out' ? 'selected' : '' ?>>Out of stock</option></select>
				<button class="secondary-button" type="submit">Apply filters</button>
			</form>
			<div class="table-wrap"><table>
				<thead><tr><th>Product</th><th>Category</th><th>In stock</th><th>Unit price</th><th>Location</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
				<tbody>
				<?php foreach ($filteredItems as $item): [$status, $statusClass] = inventoryStatus($item); ?>
					<tr>
						<td><div class="product"><div class="thumb <?= strtolower(inventoryEscape($item['category'])) ?>"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></div><div class="product-info"><strong><?= inventoryEscape($item['name']) ?></strong><span><?= inventoryEscape($item['sku']) ?></span></div></div></td>
						<td><?= inventoryEscape($item['category']) ?></td>
						<td><strong><?= number_format($item['stock']) ?></strong> <span class="product-info"><span>units</span></span></td>
						<td>$<?= number_format($item['price'], 2) ?></td>
						<td><?= inventoryEscape($item['location']) ?></td>
						<td><span class="badge <?= inventoryEscape($statusClass) ?>"><?= inventoryEscape($status) ?></span></td>
						<td><div class="row-actions"><button type="button" class="edit-button" data-item='<?= inventoryEscape(json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>'>Edit</button><form method="post" onsubmit="return confirm('Delete <?= inventoryEscape($item['name']) ?> from inventory?')"><input type="hidden" name="csrf" value="<?= inventoryEscape($_SESSION['inventory_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="sku" value="<?= inventoryEscape($item['sku']) ?>"><button class="delete" type="submit">Delete</button></form></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if (!$filteredItems): ?><div class="empty">No products match these filters.</div><?php endif; ?>
			</div>
		</section>
	</main>
</div>

<div class="dialog-backdrop" id="dialog-backdrop" role="presentation">
	<form class="dialog" id="item-form" method="post" action="inventory.php">
		<input type="hidden" name="csrf" value="<?= inventoryEscape($_SESSION['inventory_csrf']) ?>">
		<input type="hidden" name="action" id="form-action" value="create">
		<input type="hidden" name="original_sku" id="original-sku" value="">
		<div class="dialog-head"><div><h2 id="dialog-title">Add inventory item</h2><p id="dialog-description">Add a product to your catalog.</p></div><button class="close" type="button" id="close-dialog" aria-label="Close dialog">×</button></div>
		<div class="form-grid">
			<div class="field full"><label for="item-name">Product name</label><input id="item-name" name="name" required maxlength="80" placeholder="e.g. Cotton tote bag"></div>
			<div class="field"><label for="item-sku">SKU</label><input id="item-sku" name="sku" required maxlength="30" placeholder="e.g. AC-1042"></div>
			<div class="field"><label for="item-category">Category</label><select id="item-category" name="category" required><?php foreach ($categories as $category): ?><option value="<?= inventoryEscape($category) ?>"><?= inventoryEscape($category) ?></option><?php endforeach; ?></select></div>
			<div class="field"><label for="item-stock">Initial stock</label><input id="item-stock" name="stock" type="number" min="0" step="1" value="0" required></div>
			<div class="field"><label for="item-reorder">Reorder point</label><input id="item-reorder" name="reorder" type="number" min="0" step="1" value="<?= (int) $inventorySettings['default_reorder'] ?>" required></div>
			<div class="field"><label for="item-price">Unit price ($)</label><input id="item-price" name="price" type="number" min="0" step="0.01" value="0.00" required></div>
			<div class="field"><label for="item-location">Location</label><input id="item-location" name="location" maxlength="30" required placeholder="e.g. A-01-01"></div>
		</div>
		<div class="dialog-actions"><button class="secondary-button" type="button" id="cancel-dialog">Cancel</button><button class="primary-button" type="submit" id="submit-label">Add item</button></div>
	</form>
</div>
<script>
	const backdrop = document.getElementById('dialog-backdrop');
	const form = document.getElementById('item-form');
	const showDialog = (item = null) => {
		form.reset();
		document.getElementById('form-action').value = item ? 'update' : 'create';
		document.getElementById('original-sku').value = item?.sku || '';
		document.getElementById('dialog-title').textContent = item ? 'Edit inventory item' : 'Add inventory item';
		document.getElementById('dialog-description').textContent = item ? 'Update product details and stock levels.' : 'Add a product to your catalog.';
		document.getElementById('submit-label').textContent = item ? 'Save changes' : 'Add item';
		if (item) {
			for (const key of ['name', 'sku', 'category', 'stock', 'reorder', 'price', 'location']) {
				form.elements[key].value = item[key];
			}
		}
		backdrop.classList.add('open');
		document.getElementById('item-name').focus();
	};
	const hideDialog = () => backdrop.classList.remove('open');
	document.getElementById('open-dialog').addEventListener('click', () => showDialog());
	document.getElementById('close-dialog').addEventListener('click', hideDialog);
	document.getElementById('cancel-dialog').addEventListener('click', hideDialog);
	backdrop.addEventListener('click', (event) => { if (event.target === backdrop) hideDialog(); });
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideDialog(); });
	document.querySelectorAll('.edit-button').forEach((button) => {
		button.addEventListener('click', () => showDialog(JSON.parse(button.dataset.item)));
	});
</script>
</body>
</html>
