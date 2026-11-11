<?php
require_once __DIR__ . '/bootstrap.php';

$settings = &$_SESSION['inventory_settings'];
$error = '';

function settingsEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
		http_response_code(400);
		$error = 'Your session expired. Refresh the page and try again.';
	} else {
		$warehouseName = trim($_POST['warehouse_name'] ?? '');
		$warehouseLocation = trim($_POST['warehouse_location'] ?? '');
		$defaultReorder = filter_var($_POST['default_reorder'] ?? null, FILTER_VALIDATE_INT);

		if ($warehouseName === '' || $warehouseLocation === '' || $defaultReorder === false || $defaultReorder < 0 || $defaultReorder > 1000000) {
			$error = 'Enter a warehouse name, location, and a valid default reorder point.';
		} else {
			$settings['warehouse_name'] = inventoryTextLimit($warehouseName, 80);
			$settings['warehouse_location'] = inventoryTextLimit($warehouseLocation, 80);
			$settings['default_reorder'] = $defaultReorder;
			$settings['low_stock_notifications'] = isset($_POST['low_stock_notifications']);
			$_SESSION['settings_flash'] = 'Settings saved.';
			header('Location: settings.php');
			exit;
		}
	}
}

$flash = $_SESSION['settings_flash'] ?? '';
unset($_SESSION['settings_flash']);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Settings | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --ink:#202b28; --muted:#78827d; --line:#e6eae5; --paper:#fff; --canvas:#f4f6f2; --green:#2e725e; --green-dark:#235a4a; --green-pale:#e9f3ee; --red:#b94c43; --red-pale:#fff0ed; --shadow:0 12px 32px rgba(34,54,45,.055); }
		* { box-sizing:border-box; }
		body { margin:0; color:var(--ink); background:var(--canvas); font:14px 'DM Sans',sans-serif; }
		button,input { font:inherit; }
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
		.page-heading { padding:31px 0 24px; }
		.eyebrow { margin-bottom:7px; color:var(--green); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
		h1 { margin:0; font:700 27px 'Manrope',sans-serif; }
		.page-heading p { margin:7px 0 0; color:var(--muted); font-size:13px; }
		.panel { max-width:790px; border:1px solid var(--line); border-radius:8px; background:var(--paper); box-shadow:var(--shadow); }
		.panel-head { padding:19px 21px 16px; border-bottom:1px solid #eef0ed; }
		.panel-head h2 { margin:0; font:700 15px 'Manrope',sans-serif; }
		.panel-head p { margin:5px 0 0; color:var(--muted); font-size:11px; }
		.form-section { padding:7px 21px 18px; }
		.field-row { display:grid; grid-template-columns:minmax(150px,.75fr) minmax(220px,1.25fr); gap:20px; align-items:center; padding:15px 0; border-bottom:1px solid #eef0ed; }
		.field-copy strong { display:block; font-size:12px; }
		.field-copy span { display:block; margin-top:4px; color:var(--muted); font-size:10px; line-height:1.5; }
		.field input[type=text],.field input[type=number] { width:100%; height:39px; padding:0 11px; border:1px solid var(--line); border-radius:5px; outline-color:var(--green); color:var(--ink); background:#fff; font-size:12px; }
		.toggle { display:flex; align-items:center; gap:10px; min-height:39px; color:#45524b; font-size:12px; }
		.toggle input { width:16px; height:16px; accent-color:var(--green); }
		.form-actions { display:flex; justify-content:flex-end; padding-top:16px; }
		.button { min-height:38px; padding:0 14px; border:0; border-radius:6px; color:#fff; background:var(--green); font-size:11px; font-weight:700; }
		.button:hover { background:var(--green-dark); }
		.notice { max-width:790px; margin:0 0 14px; padding:11px 13px; border:1px solid #cde2d5; border-radius:6px; color:var(--green-dark); background:#eef7f1; font-size:12px; }
		.notice.error { border-color:#f0d1cb; color:#9c4038; background:var(--red-pale); }
		@media(max-width:1000px) { main { padding-right:24px; padding-left:24px; } }
		@media(max-width:800px) { .app { grid-template-columns:66px minmax(0,1fr); } .sidebar { align-items:center; padding:20px 8px; } .brand { padding:0; margin-bottom:35px; } .brand-name,.nav-label,.nav a span,.warehouse,.profile>div:last-child { display:none; } .nav a { justify-content:center; padding:0; } .sidebar-bottom { width:100%; } .profile { justify-content:center; padding:14px 0 0; } main { padding-right:18px; padding-left:18px; } }
		@media(max-width:560px) { .app { display:block; padding-bottom:58px; } .sidebar { position:fixed; z-index:3; right:0; bottom:0; left:0; flex-direction:row; justify-content:center; height:58px; padding:5px 12px; border-top:1px solid var(--line); border-right:0; background:#fff; } .brand,.sidebar-bottom { display:none; } .nav { display:flex; justify-content:flex-start; gap:3px; width:100%; max-width:420px; overflow-x:auto; } .nav a { width:45px; min-width:45px; min-height:42px; } main { padding:0 14px 24px; } .topbar { min-height:59px; } .page-heading { padding:25px 0 18px; } h1 { font-size:23px; } .field-row { grid-template-columns:1fr; gap:9px; } .form-section,.panel-head { padding-right:15px; padding-left:15px; } }
		.top-actions { display:flex; align-items:center; gap:10px; }
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
			<a href="inventory.php"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg><span>Inventory</span></a>
			<a href="suppliers.php"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3h6m-3-3v6"/></svg><span>Suppliers</span></a>
			<a href="orders.php"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8m-8 4h8m-8 4h5"/></svg><span>Purchase orders</span></a>
			<a href="analytics.php"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a class="active" href="settings.php" aria-current="page"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom"><div class="warehouse"><div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg><?= settingsEscape($settings['warehouse_name']) ?></div><p><?= settingsEscape($settings['warehouse_location']) ?></p></div><div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div></div>
	</aside>
	<main>
		<header class="topbar"><div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Settings</strong></div><div class="top-actions"><div class="avatar" aria-label="Jordan Davis">JD</div><form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= settingsEscape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form></div></header>
		<section class="page-heading"><div><div class="eyebrow">Workspace configuration</div><h1>Settings</h1><p>Configure your warehouse and inventory defaults.</p></div></section>
		<?php if ($flash !== ''): ?><div class="notice" role="status"><?= settingsEscape($flash) ?></div><?php endif; ?>
		<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= settingsEscape($error) ?></div><?php endif; ?>
		<section class="panel">
			<div class="panel-head"><h2>Warehouse preferences</h2><p>These details are shared across your inventory workspace.</p></div>
			<form method="post" action="settings.php">
				<input type="hidden" name="csrf" value="<?= settingsEscape($_SESSION['inventory_csrf']) ?>">
				<div class="form-section">
					<div class="field-row"><div class="field-copy"><strong>Warehouse name</strong><span>Shown in navigation and page context.</span></div><div class="field"><input type="text" name="warehouse_name" value="<?= settingsEscape($settings['warehouse_name']) ?>" maxlength="80" required></div></div>
					<div class="field-row"><div class="field-copy"><strong>Warehouse location</strong><span>City, region, or address reference.</span></div><div class="field"><input type="text" name="warehouse_location" value="<?= settingsEscape($settings['warehouse_location']) ?>" maxlength="80" required></div></div>
					<div class="field-row"><div class="field-copy"><strong>Default reorder point</strong><span>Suggested minimum stock for new products.</span></div><div class="field"><input type="number" name="default_reorder" value="<?= (int) $settings['default_reorder'] ?>" min="0" max="1000000" step="1" required></div></div>
					<div class="field-row"><div class="field-copy"><strong>Low-stock badge</strong><span>Show the number of items needing reorder in navigation.</span></div><label class="toggle"><input type="checkbox" name="low_stock_notifications" value="1" <?= $settings['low_stock_notifications'] ? 'checked' : '' ?>>Show badge</label></div>
					<div class="form-actions"><button class="button" type="submit">Save settings</button></div>
				</div>
			</form>
		</section>
	</main>
</div>
</body>
</html>