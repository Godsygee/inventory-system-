<?php
require_once __DIR__ . '/bootstrap.php';
$inventory = $_SESSION['inventory_items'];

$totalUnits = array_sum(array_column($inventory, 'stock'));
$lowStock = count(array_filter($inventory, fn ($item) => $item['stock'] > 0 && $item['stock'] <= $item['reorder']));
$outOfStock = count(array_filter($inventory, fn ($item) => $item['stock'] === 0));
$stockValue = array_sum(array_map(fn ($item) => $item['stock'] * $item['price'], $inventory));

function escape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function stockStatus(array $item): array
{
	if ($item['stock'] === 0) {
		return ['Out of stock', 'out'];
	}
	if ($item['stock'] <= $item['reorder']) {
		return ['Low stock', 'low'];
	}
	return ['In stock', 'good'];
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Inventory overview | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			color-scheme: light;
			--ink: #202b28;
			--muted: #78827d;
			--line: #e6eae5;
			--paper: #ffffff;
			--canvas: #f4f6f2;
			--green: #2e725e;
			--green-dark: #235a4a;
			--green-pale: #e9f3ee;
			--amber: #a96818;
			--amber-pale: #fff3df;
			--red: #b94c43;
			--red-pale: #fff0ed;
			--shadow: 0 12px 32px rgba(34, 54, 45, .055);
		}
		* { box-sizing: border-box; }
		body { margin: 0; background: var(--canvas); color: var(--ink); font-family: 'DM Sans', sans-serif; font-size: 14px; }
		button, input, select { font: inherit; }
		button { cursor: pointer; }
		.app { display: grid; grid-template-columns: 248px minmax(0, 1fr); min-height: 100vh; }
		.sidebar { display: flex; flex-direction: column; padding: 27px 18px 20px; background: #fcfdfb; border-right: 1px solid var(--line); }
		.brand { display: flex; align-items: center; gap: 11px; padding: 0 12px; margin-bottom: 42px; }
		.brand-mark { display: grid; place-items: center; width: 35px; height: 35px; border-radius: 10px; color: white; background: var(--green); }
		.brand-name { font: 800 17px 'Manrope', sans-serif; letter-spacing: 0; }
		.brand-name span { color: var(--green); }
		.nav-label { margin: 0 12px 10px; color: #a1aaa5; font-size: 10px; font-weight: 700; letter-spacing: 1.1px; text-transform: uppercase; }
		.nav { display: grid; gap: 5px; }
		.nav a { display: flex; align-items: center; gap: 12px; min-height: 43px; padding: 0 12px; border-radius: 7px; color: #727d77; text-decoration: none; font-weight: 600; }
		.nav a.active { color: var(--green-dark); background: var(--green-pale); }
		.nav svg { width: 18px; height: 18px; flex: none; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
		.nav-count { margin-left: auto; color: var(--amber); font-size: 12px; }
		.sidebar-bottom { margin-top: auto; }
		.warehouse { margin: 0 5px 17px; padding: 13px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
		.warehouse-title { display: flex; align-items: center; gap: 8px; font-weight: 700; }
		.warehouse-title svg { width: 17px; color: var(--green); }
		.warehouse p { margin: 7px 0 0 25px; color: var(--muted); font-size: 12px; }
		.profile { display: flex; align-items: center; gap: 10px; padding: 14px 8px 0; border-top: 1px solid var(--line); }
		.avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; color: #fff; background: #b46e4f; font-size: 12px; font-weight: 700; }
		.profile strong, .profile small { display: block; }
		.profile strong { font-size: 12px; }
		.profile small { margin-top: 2px; color: var(--muted); font-size: 11px; }
		main { min-width: 0; padding: 0 40px 42px; }
		.topbar { display: flex; align-items: center; justify-content: space-between; min-height: 76px; border-bottom: 1px solid var(--line); }
		.breadcrumb { color: var(--muted); font-size: 12px; }
		.breadcrumb strong { color: var(--ink); font-weight: 600; }
		.top-actions { display: flex; align-items: center; gap: 12px; }
		.icon-button { display: grid; place-items: center; width: 37px; height: 37px; padding: 0; border: 1px solid var(--line); border-radius: 7px; color: #66726c; background: #fff; }
		.icon-button svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
		.page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; padding: 31px 0 24px; }
		.eyebrow { margin-bottom: 7px; color: var(--green); font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
		h1 { margin: 0; font: 700 27px 'Manrope', sans-serif; letter-spacing: 0; }
		.page-heading p { margin: 7px 0 0; color: var(--muted); font-size: 13px; }
		.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 39px; padding: 0 14px; border: 1px solid transparent; border-radius: 6px; font-size: 12px; font-weight: 700; white-space: nowrap; }
		.primary-button { color: #fff; background: var(--green); }
		.primary-button:hover { background: var(--green-dark); }
		.secondary-button { color: #45524b; border-color: var(--line); background: #fff; }
		.primary-button svg, .secondary-button svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
		.metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 15px; }
		.metric { min-height: 134px; padding: 18px 19px; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); box-shadow: var(--shadow); }
		.metric-top { display: flex; align-items: center; justify-content: space-between; color: var(--muted); font-size: 12px; font-weight: 600; }
		.metric-icon { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; color: var(--green); background: var(--green-pale); }
		.metric-icon.warn { color: var(--amber); background: var(--amber-pale); }
		.metric-icon.red { color: var(--red); background: var(--red-pale); }
		.metric-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
		.metric-value { margin-top: 13px; font: 700 25px 'Manrope', sans-serif; letter-spacing: 0; }
		.metric-foot { display: flex; align-items: center; gap: 5px; margin-top: 5px; color: var(--muted); font-size: 11px; }
		.trend { color: var(--green); font-weight: 700; }
		.content-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(250px, .85fr); gap: 17px; margin-top: 20px; }
		.panel { min-width: 0; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); box-shadow: var(--shadow); }
		.panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 19px 15px; }
		.panel-heading h2 { margin: 0; font: 700 15px 'Manrope', sans-serif; letter-spacing: 0; }
		.panel-heading p { margin: 4px 0 0; color: var(--muted); font-size: 11px; }
		.text-link { border: 0; padding: 4px 0; color: var(--green); background: transparent; font-size: 11px; font-weight: 700; }
		.stock-list { display: grid; }
		.stock-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 19px; border-top: 1px solid #eef0ed; }
		.stock-row:first-child { border-top: 0; }
		.product { display: flex; align-items: center; gap: 11px; min-width: 0; }
		.product-thumb { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 7px; color: var(--green); background: #eff5f0; }
		.product-thumb.office { color: #a96818; background: #fbf3e7; }
		.product-thumb.home { color: #9c6259; background: #f8efec; }
		.product-thumb svg { width: 19px; height: 19px; stroke: currentColor; fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
		.product-info { min-width: 0; }
		.product-info strong { display: block; overflow: hidden; color: #29332e; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
		.product-info span { display: block; margin-top: 4px; color: var(--muted); font-size: 10px; }
		.stock-meta { display: flex; align-items: flex-end; gap: 14px; }
		.stock-qty { min-width: 50px; text-align: right; }
		.stock-qty strong { display: block; font-size: 12px; }
		.stock-qty span { color: var(--muted); font-size: 10px; }
		.badge { display: inline-flex; align-items: center; gap: 5px; min-height: 22px; padding: 0 8px; border-radius: 20px; font-size: 10px; font-weight: 700; white-space: nowrap; }
		.badge::before { width: 5px; height: 5px; border-radius: 50%; background: currentColor; content: ''; }
		.badge.good { color: var(--green); background: var(--green-pale); }
		.badge.low { color: var(--amber); background: var(--amber-pale); }
		.badge.out { color: var(--red); background: var(--red-pale); }
		.activity-list { padding: 0 19px 4px; }
		.activity { display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 10px; padding: 12px 0; border-top: 1px solid #eef0ed; }
		.activity:first-child { border-top: 0; }
		.activity-icon { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 50%; color: var(--green); background: var(--green-pale); }
		.activity-icon.orange { color: var(--amber); background: var(--amber-pale); }
		.activity-icon svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
		.activity p { margin: 0; color: #4e5953; font-size: 11px; line-height: 1.55; }
		.activity p strong { color: var(--ink); }
		.activity time { display: block; margin-top: 3px; color: #9aa39e; font-size: 10px; }
		.table-panel { margin-top: 18px; }
		.table-tools { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 19px 15px; }
		.filters { display: flex; align-items: center; gap: 4px; overflow-x: auto; }
		.filter-button { min-height: 29px; padding: 0 10px; border: 0; border-radius: 5px; color: #7a847e; background: transparent; font-size: 11px; font-weight: 600; white-space: nowrap; }
		.filter-button.active { color: var(--green-dark); background: var(--green-pale); }
		.search-box { display: flex; align-items: center; gap: 7px; width: 205px; height: 34px; padding: 0 9px; border: 1px solid var(--line); border-radius: 6px; }
		.search-box svg { width: 15px; height: 15px; flex: none; stroke: #98a19b; fill: none; stroke-width: 1.8; stroke-linecap: round; }
		.search-box input { width: 100%; min-width: 0; border: 0; outline: 0; color: var(--ink); font-size: 11px; }
		.search-box input::placeholder { color: #a1aaa5; }
		.table-wrap { overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; text-align: left; }
		th { height: 35px; padding: 0 14px; color: #929b95; background: #fafbf9; font-size: 9px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; white-space: nowrap; }
		th:first-child, td:first-child { padding-left: 19px; }
		th:last-child, td:last-child { padding-right: 19px; }
		td { height: 53px; padding: 0 14px; border-top: 1px solid #eef0ed; color: #4e5953; font-size: 11px; white-space: nowrap; }
		td strong { color: #29332e; font-size: 11px; }
		.sku { color: #8d9791; font-size: 10px; }
		.table-product { display: flex; align-items: center; gap: 9px; min-width: 180px; }
		.table-product .product-thumb { width: 30px; height: 30px; }
		.table-product .product-thumb svg { width: 16px; height: 16px; }
		.category { color: #78827d; }
		.table-empty { display: none; padding: 30px; color: var(--muted); text-align: center; }
		.modal-backdrop { position: fixed; z-index: 5; inset: 0; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(25, 39, 32, .38); }
		.modal-backdrop.open { display: flex; }
		.modal { width: min(100%, 430px); padding: 23px; border-radius: 9px; background: #fff; box-shadow: 0 24px 70px rgba(23, 40, 31, .2); }
		.modal-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 19px; }
		.modal h2 { margin: 0; font: 700 18px 'Manrope', sans-serif; }
		.modal p { margin: 5px 0 0; color: var(--muted); font-size: 12px; }
		.close-button { display: grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 5px; color: #68746d; background: #f2f5f1; }
		.close-button svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; }
		.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; }
		.field { display: grid; gap: 6px; }
		.field.full { grid-column: 1 / -1; }
		.field label { color: #59645e; font-size: 11px; font-weight: 700; }
		.field input, .field select { width: 100%; height: 37px; padding: 0 10px; border: 1px solid var(--line); border-radius: 5px; outline-color: var(--green); color: var(--ink); background: white; font-size: 12px; }
		.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 19px; }
		@media (max-width: 1100px) {
			main { padding-right: 25px; padding-left: 25px; }
			.metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			.content-grid { grid-template-columns: minmax(0, 1.5fr) minmax(230px, .8fr); }
		}
		@media (max-width: 800px) {
			.app { grid-template-columns: 66px minmax(0, 1fr); }
			.sidebar { align-items: center; padding: 20px 8px; }
			.brand { padding: 0; margin-bottom: 35px; }
			.brand-name, .nav-label, .nav a span, .nav-count, .warehouse, .profile > div:last-child { display: none; }
			.nav { width: 100%; }
			.nav a { justify-content: center; padding: 0; }
			.sidebar-bottom { width: 100%; }
			.profile { justify-content: center; padding-right: 0; padding-left: 0; }
			main { padding-right: 20px; padding-left: 20px; }
			.content-grid { grid-template-columns: 1fr; }
		}
		@media (max-width: 560px) {
			.app { display: block; padding-bottom: 58px; }
			.sidebar { position: fixed; z-index: 3; right: 0; bottom: 0; left: 0; flex-direction: row; justify-content: center; height: 58px; padding: 5px 12px; border-top: 1px solid var(--line); border-right: 0; background: #fff; }
			.brand, .sidebar-bottom { display: none; }
			.nav { display: flex; justify-content: space-around; max-width: 420px; }
			.nav a { width: 48px; min-height: 42px; }
			.nav a:nth-child(n+5) { display: none; }
			.nav svg { width: 19px; height: 19px; }
			main { padding: 0 15px 25px; }
			.topbar { min-height: 59px; }
			.top-actions { gap: 7px; }
			.top-actions .icon-button { width: 33px; height: 33px; }
			.page-heading { align-items: flex-start; padding: 25px 0 18px; }
			h1 { font-size: 23px; }
			.page-heading p { max-width: 230px; line-height: 1.5; }
			.page-heading > .primary-button { width: 39px; min-width: 39px; padding: 0; font-size: 0; }
			.page-heading > .primary-button svg { width: 17px; height: 17px; }
			.metrics { gap: 9px; }
			.metric { min-height: 119px; padding: 13px; }
			.metric-top { font-size: 10px; }
			.metric-value { font-size: 21px; }
			.metric-foot { font-size: 9px; }
			.content-grid { margin-top: 13px; gap: 13px; }
			.panel-heading { padding: 15px 14px 12px; }
			.stock-row { padding-right: 14px; padding-left: 14px; }
			.stock-meta { gap: 8px; }
			.stock-meta .badge { padding: 0 6px; font-size: 9px; }
			.table-panel { margin-top: 13px; }
			.table-tools { align-items: flex-start; flex-direction: column; padding: 0 12px 12px; }
			.filters { width: 100%; }
			.search-box { width: 100%; }
			th:first-child, td:first-child { padding-left: 13px; }
			th:last-child, td:last-child { padding-right: 13px; }
		}
		@media(max-width:560px) { .nav { display:flex; justify-content:flex-start; gap:0; width:100%; max-width:420px; overflow-x:auto; } .nav a { flex:0 0 40px; width:40px; min-width:40px; } .nav a:nth-child(n+5) { display:flex; } }
		.sign-out-form { margin:0; }
		.sign-out { min-height:32px; padding:0 10px; border:1px solid var(--line); border-radius:5px; color:#56635c; background:#fff; font-size:10px; font-weight:700; white-space:nowrap; }
	</style>
</head>
<body>
<div class="app">
	<aside class="sidebar" aria-label="Main navigation">
		<div class="brand">
			<div class="brand-mark" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 7 8-4 8 4v10l-8 4-8-4z"/><path d="m4 7 8 4 8-4M12 21V11"/></svg></div>
			<div class="brand-name">stock<span>room</span></div>
		</div>
		<div class="nav-label">Workspace</div>
		<nav class="nav">
			<a class="active" href="#overview" aria-current="page"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg><span>Overview</span></a>
			<a href="#inventory"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg><span>Inventory</span></a>
			<a href="suppliers.php"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3h6m-3-3v6"/></svg><span>Suppliers</span></a>
			<a href="orders.php"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8m-8 4h8m-8 4h5"/></svg><span>Purchase orders</span></a>
			<a href="#inventory" data-filter-link="low"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg><span>Low stock</span><?php if ($inventorySettings['low_stock_notifications']): ?><span class="nav-count"><?= $lowStock + $outOfStock ?></span><?php endif; ?></a>
			<a href="analytics.php"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom">
			<div class="warehouse">
				<div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg> <?= escape($inventorySettings['warehouse_name']) ?></div>
				<p><?= escape($inventorySettings['warehouse_location']) ?></p>
			</div>
			<div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div>
		</div>
	</aside>

	<main id="overview">
		<header class="topbar">
			<div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Overview</strong></div>
			<div class="top-actions">
				<button class="icon-button" type="button" aria-label="Notifications" title="Notifications"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 13h4"/></svg></button>
				<div class="avatar" aria-label="Jordan Davis">JD</div>
				<form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= escape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form>
			</div>
		</header>

		<section class="page-heading">
			<div><div class="eyebrow">Monday, November 10, 2026</div><h1>Inventory overview</h1><p>Here’s what’s happening with your stock today.</p></div>
			<button class="primary-button" type="button" id="open-modal"><svg viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg>Add item</button>
		</section>

		<section class="metrics" aria-label="Inventory summary">
			<article class="metric"><div class="metric-top"><span>Total products</span><span class="metric-icon"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></span></div><div class="metric-value" id="product-count"><?= count($inventory) ?></div><div class="metric-foot"><span class="trend">+2</span> added this month</div></article>
			<article class="metric"><div class="metric-top"><span>Units on hand</span><span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg></span></div><div class="metric-value" id="unit-count"><?= number_format($totalUnits) ?></div><div class="metric-foot"><span class="trend">+8.4%</span> from last month</div></article>
			<article class="metric"><div class="metric-top"><span>Low stock alerts</span><span class="metric-icon warn"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg></span></div><div class="metric-value" id="low-count"><?= $lowStock + $outOfStock ?></div><div class="metric-foot">Needs your attention</div></article>
			<article class="metric"><div class="metric-top"><span>Inventory value</span><span class="metric-icon red"><svg viewBox="0 0 24 24"><path d="M12 2v20m5-15H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span></div><div class="metric-value" id="stock-value">$<?= number_format($stockValue, 0) ?></div><div class="metric-foot">Across all products</div></article>
		</section>

		<section class="content-grid">
			<article class="panel">
				<div class="panel-heading"><div><h2>Stock at a glance</h2><p>Items that may need replenishment</p></div><button class="text-link" type="button" data-filter-link="low">View all</button></div>
				<div class="stock-list">
					<?php foreach (array_slice($inventory, 0, 4) as $item): [$status, $statusClass] = stockStatus($item); ?>
					<div class="stock-row">
						<div class="product"><div class="product-thumb <?= strtolower(escape($item['category'])) ?>"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></div><div class="product-info"><strong><?= escape($item['name']) ?></strong><span><?= escape($item['sku']) ?> · <?= escape($item['location']) ?></span></div></div>
						<div class="stock-meta"><div class="stock-qty"><strong><?= number_format($item['stock']) ?></strong><span>units</span></div><span class="badge <?= escape($statusClass) ?>"><?= escape($status) ?></span></div>
					</div>
					<?php endforeach; ?>
				</div>
			</article>
			<article class="panel" id="activity">
				<div class="panel-heading"><div><h2>Recent activity</h2><p>Latest changes across your stock</p></div><button class="text-link" type="button">See all</button></div>
				<div class="activity-list">
					<div class="activity"><span class="activity-icon"><svg viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg></span><div><p><strong>New stock received</strong> for USB-C Charging Cable</p><time>Today, 10:42 AM · 48 units</time></div></div>
					<div class="activity"><span class="activity-icon orange"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg></span><div><p><strong>Low stock alert</strong> for A5 Notebook Set</p><time>Today, 9:18 AM · 8 units left</time></div></div>
					<div class="activity"><span class="activity-icon"><svg viewBox="0 0 24 24"><path d="M20 7h-9m3 10H4m12-3 4 3-4 3M8 4 4 7l4 3"/></svg></span><div><p><strong>Stock adjusted</strong> for Desk Organizer Tray</p><time>Yesterday, 4:06 PM · Jordan Davis</time></div></div>
					<div class="activity"><span class="activity-icon"><svg viewBox="0 0 24 24"><path d="M20 7h-9m3 10H4m12-3 4 3-4 3M8 4 4 7l4 3"/></svg></span><div><p><strong>Order fulfilled</strong> for Wireless Headphones</p><time>Yesterday, 1:32 PM · 6 units</time></div></div>
				</div>
			</article>
		</section>

		<section class="panel table-panel" id="inventory">
			<div class="panel-heading"><div><h2>All inventory</h2><p>Manage and monitor your product catalog</p></div><button class="secondary-button" type="button" id="export-button"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5 5 5 5-5m-5 5V3"/></svg>Export</button></div>
			<div class="table-tools">
				<div class="filters" role="group" aria-label="Filter inventory">
					<button class="filter-button active" type="button" data-filter="all">All items</button>
					<button class="filter-button" type="button" data-filter="Electronics">Electronics</button>
					<button class="filter-button" type="button" data-filter="Office">Office</button>
					<button class="filter-button" type="button" data-filter="Home">Home</button>
					<button class="filter-button" type="button" data-filter="low">Low stock</button>
				</div>
				<label class="search-box"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="search-input" type="search" placeholder="Search products..." aria-label="Search products"></label>
			</div>
			<div class="table-wrap">
				<table>
					<thead><tr><th>Product</th><th>Category</th><th>In stock</th><th>Unit price</th><th>Location</th><th>Status</th></tr></thead>
					<tbody id="inventory-body">
						<?php foreach ($inventory as $item): [$status, $statusClass] = stockStatus($item); ?>
						<tr data-category="<?= escape($item['category']) ?>" data-stock="<?= (int) $item['stock'] ?>" data-reorder="<?= (int) $item['reorder'] ?>" data-search="<?= escape(strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['category'])) ?>">
							<td><div class="table-product"><div class="product-thumb <?= strtolower(escape($item['category'])) ?>"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></div><div><strong><?= escape($item['name']) ?></strong><div class="sku"><?= escape($item['sku']) ?></div></div></div></td>
							<td class="category"><?= escape($item['category']) ?></td>
							<td><strong><?= number_format($item['stock']) ?></strong> <span class="sku">units</span></td>
							<td>$<?= number_format($item['price'], 2) ?></td>
							<td><?= escape($item['location']) ?></td>
							<td><span class="badge <?= escape($statusClass) ?>"><?= escape($status) ?></span></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="table-empty" id="empty-state">No products match your search.</div>
			</div>
		</section>
	</main>
</div>

<div class="modal-backdrop" id="item-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
	<form class="modal" id="item-form">
		<div class="modal-head"><div><h2 id="modal-title">Add inventory item</h2><p>Add a product to your catalog.</p></div><button class="close-button" type="button" id="close-modal" aria-label="Close"><svg viewBox="0 0 24 24"><path d="m18 6-12 12M6 6l12 12"/></svg></button></div>
		<div class="form-grid">
			<div class="field full"><label for="item-name">Product name</label><input id="item-name" name="name" required maxlength="60" placeholder="e.g. Cotton tote bag"></div>
			<div class="field"><label for="item-sku">SKU</label><input id="item-sku" name="sku" required maxlength="20" placeholder="e.g. AC-1042"></div>
			<div class="field"><label for="item-category">Category</label><select id="item-category" name="category"><option>Electronics</option><option>Office</option><option>Home</option></select></div>
			<div class="field"><label for="item-stock">Initial stock</label><input id="item-stock" name="stock" type="number" min="0" value="0" required></div>
			<div class="field"><label for="item-reorder">Reorder point</label><input id="item-reorder" name="reorder" type="number" min="0" value="10" required></div>
			<div class="field"><label for="item-price">Unit price ($)</label><input id="item-price" name="price" type="number" min="0" step="0.01" value="0.00" required></div>
			<div class="field"><label for="item-location">Location</label><input id="item-location" name="location" maxlength="20" placeholder="e.g. A-01-01" required></div>
		</div>
		<div class="modal-actions"><button class="secondary-button" type="button" id="cancel-modal">Cancel</button><button class="primary-button" type="submit">Add item</button></div>
	</form>
</div>

<script>
	const body = document.getElementById('inventory-body');
	const searchInput = document.getElementById('search-input');
	const filterButtons = [...document.querySelectorAll('.filter-button')];
	let activeFilter = 'all';

	function updateInventoryView() {
		const query = searchInput.value.trim().toLowerCase();
		let visible = 0;
		[...body.rows].forEach((row) => {
			const matchesCategory = activeFilter === 'all'
				|| (activeFilter === 'low'
					? Number(row.dataset.stock) <= Number(row.dataset.reorder)
					: row.dataset.category === activeFilter);
			const matchesSearch = row.dataset.search.includes(query);
			row.hidden = !(matchesCategory && matchesSearch);
			if (!row.hidden) visible += 1;
		});
		document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
	}

	filterButtons.forEach((button) => button.addEventListener('click', () => {
		activeFilter = button.dataset.filter;
		filterButtons.forEach((item) => item.classList.toggle('active', item === button));
		updateInventoryView();
	}));
	searchInput.addEventListener('input', updateInventoryView);

	document.querySelectorAll('[data-filter-link]').forEach((link) => link.addEventListener('click', () => {
		activeFilter = link.dataset.filterLink;
		filterButtons.forEach((button) => button.classList.toggle('active', button.dataset.filter === activeFilter));
		updateInventoryView();
	}));

	document.getElementById('export-button').addEventListener('click', () => {
		const rows = [['Product', 'SKU', 'Category', 'In stock', 'Reorder point', 'Unit price', 'Location', 'Status']];
		[...body.rows].filter((row) => !row.hidden).forEach((row) => {
			const cells = row.cells;
			rows.push([
				cells[0].querySelector('strong').textContent,
				cells[0].querySelector('.sku').textContent,
				cells[1].textContent,
				cells[2].textContent.replace(' units', '').trim(),
				row.dataset.reorder,
				cells[3].textContent.replace('$', ''),
				cells[4].textContent,
				cells[5].textContent.trim(),
			]);
		});
		const csv = rows.map((row) => row.map((value) => `"${value.replaceAll('"', '""')}"`).join(',')).join('\n');
		const link = document.createElement('a');
		link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
		link.download = 'inventory.csv';
		link.click();
		URL.revokeObjectURL(link.href);
	});

	const modal = document.getElementById('item-modal');
	const form = document.getElementById('item-form');
	const openModal = () => { modal.classList.add('open'); document.getElementById('item-name').focus(); };
	const closeModal = () => { modal.classList.remove('open'); form.reset(); };
	document.getElementById('open-modal').addEventListener('click', openModal);
	document.getElementById('close-modal').addEventListener('click', closeModal);
	document.getElementById('cancel-modal').addEventListener('click', closeModal);
	modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		const data = new FormData(form);
		const name = data.get('name').trim();
		const sku = data.get('sku').trim();
		if ([...body.rows].some((row) => row.cells[0].querySelector('.sku').textContent.toLowerCase() === sku.toLowerCase())) {
			document.getElementById('item-sku').setCustomValidity('This SKU is already in the inventory.');
			document.getElementById('item-sku').reportValidity();
			return;
		}
		document.getElementById('item-sku').setCustomValidity('');
		const category = data.get('category');
		const stock = Number(data.get('stock'));
		const reorder = Number(data.get('reorder'));
		const price = Number(data.get('price'));
		const location = data.get('location').trim();
		const status = stock === 0 ? ['Out of stock', 'out'] : stock <= reorder ? ['Low stock', 'low'] : ['In stock', 'good'];
		const row = body.insertRow();
		row.dataset.category = category;
		row.dataset.stock = stock;
		row.dataset.reorder = reorder;
		row.dataset.search = `${name} ${sku} ${category}`.toLowerCase();
		const safe = (value) => { const span = document.createElement('span'); span.textContent = value; return span.innerHTML; };
		row.innerHTML = `<td><div class="table-product"><div class="product-thumb ${category.toLowerCase()}"><svg viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/></svg></div><div><strong>${safe(name)}</strong><div class="sku">${safe(sku)}</div></div></div></td><td class="category">${safe(category)}</td><td><strong>${stock.toLocaleString()}</strong> <span class="sku">units</span></td><td>$${price.toFixed(2)}</td><td>${safe(location)}</td><td><span class="badge ${status[1]}">${status[0]}</span></td>`;
		document.getElementById('product-count').textContent = body.rows.length;
		document.getElementById('unit-count').textContent = [...body.rows].reduce((sum, item) => sum + Number(item.dataset.stock), 0).toLocaleString();
		document.getElementById('low-count').textContent = [...body.rows].filter((item) => Number(item.dataset.stock) <= Number(item.dataset.reorder)).length;
		const totalValue = [...body.rows].reduce((sum, item) => sum + Number(item.cells[3].textContent.replace('$', '')) * Number(item.dataset.stock), 0);
		document.getElementById('stock-value').textContent = `$${Math.round(totalValue).toLocaleString()}`;
		closeModal();
		activeFilter = 'all';
		filterButtons.forEach((button) => button.classList.toggle('active', button.dataset.filter === 'all'));
		searchInput.value = '';
		updateInventoryView();
	});
</script>
</body>
</html>
