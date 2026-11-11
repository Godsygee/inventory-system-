<?php
require_once __DIR__ . '/bootstrap.php';

$items = $_SESSION['inventory_items'];
$categories = [];
$totalValue = 0;
$totalUnits = 0;
$lowItems = [];
$outOfStock = 0;
$healthyItems = 0;

function analyticsEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function analyticsStatus(array $item): array
{
	if ($item['stock'] === 0) {
		return ['Out of stock', 'out'];
	}
	if ($item['stock'] <= $item['reorder']) {
		return ['Low stock', 'low'];
	}
	return ['In stock', 'good'];
}

foreach ($items as $item) {
	$category = $item['category'];
	$value = $item['stock'] * $item['price'];
	$totalValue += $value;
	$totalUnits += $item['stock'];
	if (!isset($categories[$category])) {
		$categories[$category] = ['products' => 0, 'units' => 0, 'value' => 0, 'alerts' => 0];
	}
	$categories[$category]['products']++;
	$categories[$category]['units'] += $item['stock'];
	$categories[$category]['value'] += $value;
	if ($item['stock'] === 0) {
		$outOfStock++;
		$categories[$category]['alerts']++;
		$lowItems[] = $item;
	} elseif ($item['stock'] <= $item['reorder']) {
		$categories[$category]['alerts']++;
		$lowItems[] = $item;
	} else {
		$healthyItems++;
	}
}

uasort($categories, fn ($left, $right) => $right['value'] <=> $left['value']);
usort($lowItems, fn ($left, $right) => $left['stock'] <=> $right['stock']);
$highestCategoryValue = max(array_column($categories ?: [['value' => 0]], 'value'));
$highestValueItems = $items;
usort($highestValueItems, fn ($left, $right) => ($right['stock'] * $right['price']) <=> ($left['stock'] * $left['price']));
$stockHealth = count($items) > 0 ? (int) round($healthyItems / count($items) * 100) : 0;
$lowOnlyCount = count($lowItems) - $outOfStock;
if ($items) {
	$healthySlice = round($healthyItems / count($items) * 100, 1);
	$lowSlice = round($lowOnlyCount / count($items) * 100, 1);
	$lowSliceEnd = min(100, $healthySlice + $lowSlice);
	$donutStyle = 'conic-gradient(var(--green) 0 ' . $healthySlice . '%, #d8aa62 ' . $healthySlice . '% ' . $lowSliceEnd . '%, #d98175 ' . $lowSliceEnd . '% 100%)';
} else {
	$donutStyle = 'conic-gradient(#eef1ed 0 100%)';
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="inventory-analytics.csv"');
	$output = fopen('php://output', 'w');
	fputcsv($output, ['Category', 'Products', 'Units on hand', 'Stock value', 'Replenishment alerts']);
	foreach ($categories as $name => $summary) {
		fputcsv($output, [$name, $summary['products'], $summary['units'], number_format($summary['value'], 2, '.', ''), $summary['alerts']]);
	}
	fputcsv($output, ['TOTAL', count($items), $totalUnits, number_format($totalValue, 2, '.', ''), count($lowItems)]);
	fclose($output);
	exit;
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Analytics | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --ink:#202b28; --muted:#78827d; --line:#e6eae5; --paper:#fff; --canvas:#f4f6f2; --green:#2e725e; --green-dark:#235a4a; --green-pale:#e9f3ee; --amber:#a96818; --amber-pale:#fff3df; --red:#b94c43; --red-pale:#fff0ed; --shadow:0 12px 32px rgba(34,54,45,.055); }
		* { box-sizing:border-box; }
		body { margin:0; color:var(--ink); background:var(--canvas); font:14px 'DM Sans',sans-serif; }
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
		.page-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; padding:31px 0 24px; }
		.eyebrow { margin-bottom:7px; color:var(--green); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
		h1 { margin:0; font:700 27px 'Manrope',sans-serif; }
		.page-heading p { margin:7px 0 0; color:var(--muted); font-size:13px; }
		.button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:38px; padding:0 13px; border:1px solid var(--line); border-radius:6px; color:#45524b; background:#fff; text-decoration:none; font-size:11px; font-weight:700; white-space:nowrap; }
		.button svg { width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
		.button:hover { border-color:#c5d8ce; color:var(--green-dark); }
		.metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
		.metric { min-height:127px; padding:17px 18px; border:1px solid var(--line); border-radius:8px; background:var(--paper); box-shadow:var(--shadow); }
		.metric-label { color:var(--muted); font-size:11px; font-weight:600; }
		.metric-value { margin-top:13px; font:700 25px 'Manrope',sans-serif; }
		.metric-note { margin-top:5px; color:var(--muted); font-size:10px; }
		.good { color:var(--green); }
		.warn { color:var(--amber); }
		.bad { color:var(--red); }
		.content-grid { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(280px,.9fr); gap:16px; margin-top:17px; }
		.panel { min-width:0; border:1px solid var(--line); border-radius:8px; background:var(--paper); box-shadow:var(--shadow); }
		.panel-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:17px 18px 12px; }
		.panel-heading h2 { margin:0; font:700 14px 'Manrope',sans-serif; }
		.panel-heading p { margin:4px 0 0; color:var(--muted); font-size:10px; }
		.category-list { display:grid; gap:16px; padding:9px 18px 19px; }
		.category-row { display:grid; gap:7px; }
		.category-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
		.category-name { font-size:11px; font-weight:700; }
		.category-value { color:#57635c; font-size:10px; font-weight:600; }
		.bar-track { height:7px; overflow:hidden; border-radius:5px; background:#eef1ed; }
		.bar { height:100%; min-width:2px; border-radius:5px; background:var(--green); }
		.bar.units { background:#bc8a45; }
		.category-meta { display:flex; gap:12px; color:var(--muted); font-size:10px; }
		.health-content { display:grid; grid-template-columns:130px minmax(0,1fr); align-items:center; gap:16px; padding:7px 18px 20px; }
		.donut { display:grid; place-items:center; width:124px; aspect-ratio:1; border-radius:50%; }
		.donut::before { width:88px; height:88px; border-radius:50%; background:#fff; content:''; grid-area:1/1; }
		.donut-center { z-index:1; grid-area:1/1; text-align:center; }
		.donut-center strong { display:block; font:700 22px 'Manrope',sans-serif; }
		.donut-center span { color:var(--muted); font-size:9px; }
		.legend { display:grid; gap:12px; }
		.legend-row { display:grid; grid-template-columns:9px minmax(0,1fr) auto; align-items:center; gap:7px; color:#626e67; font-size:10px; }
		.legend-dot { width:8px; height:8px; border-radius:50%; background:var(--green); }
		.legend-dot.low { background:#d8aa62; }
		.legend-dot.out { background:#d98175; }
		.legend-row strong { color:var(--ink); }
		.table-panel { margin-top:16px; }
		.table-wrap { overflow:auto; }
		table { width:100%; border-collapse:collapse; text-align:left; }
		th { height:35px; padding:0 13px; color:#929b95; background:#fafbf9; font-size:9px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; white-space:nowrap; }
		th:first-child,td:first-child { padding-left:18px; }
		th:last-child,td:last-child { padding-right:18px; }
		td { height:49px; padding:0 13px; border-top:1px solid #eef0ed; color:#4e5953; font-size:10px; white-space:nowrap; }
		td strong { color:#29332e; font-size:11px; }
		.product-name { display:block; color:#29332e; font-size:11px; font-weight:700; }
		.sku { display:block; margin-top:3px; color:#8d9791; font-size:9px; }
		.badge { display:inline-flex; align-items:center; gap:5px; min-height:21px; padding:0 8px; border-radius:20px; font-size:9px; font-weight:700; white-space:nowrap; }
		.badge::before { width:5px; height:5px; border-radius:50%; background:currentColor; content:''; }
		.badge.low { color:var(--amber); background:var(--amber-pale); }
		.badge.out { color:var(--red); background:var(--red-pale); }
		.empty { padding:24px 18px; color:var(--muted); font-size:11px; }
		@media(max-width:1100px) { main { padding-right:25px; padding-left:25px; } .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
		@media(max-width:850px) { .app { grid-template-columns:66px minmax(0,1fr); } .sidebar { align-items:center; padding:20px 8px; } .brand { padding:0; margin-bottom:35px; } .brand-name,.nav-label,.nav a span,.nav-count,.warehouse,.profile>div:last-child { display:none; } .nav a { justify-content:center; padding:0; } .sidebar-bottom { width:100%; } .profile { justify-content:center; padding-right:0; padding-left:0; } main { padding-right:20px; padding-left:20px; } .content-grid { grid-template-columns:1fr; } }
		@media(max-width:560px) { .app { display:block; padding-bottom:58px; } .sidebar { position:fixed; z-index:3; right:0; bottom:0; left:0; flex-direction:row; justify-content:center; height:58px; padding:5px 12px; border-top:1px solid var(--line); border-right:0; background:#fff; } .brand,.sidebar-bottom { display:none; } .nav { display:flex; justify-content:space-around; max-width:420px; width:100%; } .nav a { width:48px; min-height:42px; } .nav a:nth-child(n+5) { display:none; } main { padding:0 14px 24px; } .topbar { min-height:59px; } .page-heading { align-items:flex-start; padding:25px 0 18px; } h1 { font-size:23px; } .page-heading p { max-width:230px; line-height:1.5; } .page-heading .button { width:39px; min-width:39px; padding:0; overflow:hidden; font-size:0; } .page-heading .button svg { width:17px; height:17px; flex:none; } .metrics { gap:9px; } .metric { min-height:112px; padding:13px; } .metric-label { font-size:10px; } .metric-value { font-size:21px; } .content-grid { gap:12px; margin-top:12px; } .health-content { grid-template-columns:110px minmax(0,1fr); gap:10px; padding-right:12px; padding-left:12px; } .donut { width:104px; } .donut::before { width:74px; height:74px; } }
		@media(max-width:560px) { .nav { display:flex; justify-content:flex-start; gap:0; width:100%; max-width:420px; overflow-x:auto; } .nav a { flex:0 0 40px; width:40px; min-width:40px; } .nav a:nth-child(n+5) { display:flex; } }
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
			<a class="active" href="analytics.php" aria-current="page"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a href="inventory.php?status=alerts"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg><span>Low stock</span><?php if ($inventorySettings['low_stock_notifications']): ?><span class="nav-count"><?= count($lowItems) ?></span><?php endif; ?></a>
			<a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom"><div class="warehouse"><div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg><?= analyticsEscape($inventorySettings['warehouse_name']) ?></div><p><?= analyticsEscape($inventorySettings['warehouse_location']) ?></p></div><div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div></div>
	</aside>
	<main>
		<header class="topbar"><div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Analytics</strong></div><div class="top-actions"><div class="avatar" aria-label="Jordan Davis">JD</div><form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= analyticsEscape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form></div></header>
		<section class="page-heading"><div><div class="eyebrow"><?= analyticsEscape($inventorySettings['warehouse_name']) ?> / Snapshot</div><h1>Inventory analytics</h1><p>Understand current stock levels and where value is held.</p></div><a class="button" href="analytics.php?export=csv"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5 5 5 5-5m-5 5V3"/></svg>Export report</a></section>
		<section class="metrics" aria-label="Inventory analytics summary">
			<article class="metric"><div class="metric-label">Catalog products</div><div class="metric-value"><?= number_format(count($items)) ?></div><div class="metric-note">Across <?= count($categories) ?> categories</div></article>
			<article class="metric"><div class="metric-label">Units on hand</div><div class="metric-value"><?= number_format($totalUnits) ?></div><div class="metric-note">Current physical stock</div></article>
			<article class="metric"><div class="metric-label">Stock value</div><div class="metric-value">$<?= number_format($totalValue, 0) ?></div><div class="metric-note">At listed unit prices</div></article>
			<article class="metric"><div class="metric-label">Replenishment alerts</div><div class="metric-value <?= $lowItems ? 'warn' : 'good' ?>"><?= number_format(count($lowItems)) ?></div><div class="metric-note"><span class="bad"><?= number_format($outOfStock) ?> out</span> · <span class="warn"><?= number_format(count($lowItems) - $outOfStock) ?> low</span></div></article>
		</section>
		<section class="content-grid">
			<article class="panel">
				<div class="panel-heading"><div><h2>Category breakdown</h2><p>Inventory value and unit distribution by category</p></div></div>
				<div class="category-list">
					<?php foreach ($categories as $name => $summary): ?>
					<div class="category-row">
						<div class="category-top"><span class="category-name"><?= analyticsEscape($name) ?></span><span class="category-value">$<?= number_format($summary['value'], 2) ?></span></div>
						<div class="bar-track" role="img" aria-label="<?= analyticsEscape($name) ?> stock value, <?= $totalValue > 0 ? (int) round($summary['value'] / $totalValue * 100) : 0 ?> percent"><div class="bar" style="width:<?= $highestCategoryValue > 0 ? round($summary['value'] / $highestCategoryValue * 100, 1) : 0 ?>%"></div></div>
						<div class="category-meta"><span><?= number_format($summary['products']) ?> products</span><span><?= number_format($summary['units']) ?> units</span><span><?= number_format($totalValue > 0 ? round($summary['value'] / $totalValue * 100) : 0) ?>% of value</span></div>
					</div>
					<?php endforeach; ?>
					<?php if (!$categories): ?><div class="empty">Add products to see category analytics.</div><?php endif; ?>
				</div>
			</article>
			<article class="panel">
				<div class="panel-heading"><div><h2>Stock health</h2><p>Products against their reorder points</p></div></div>
				<div class="health-content">
					<div class="donut" style="background:<?= analyticsEscape($donutStyle) ?>" role="img" aria-label="<?= $stockHealth ?> percent of products at healthy stock levels"><div class="donut-center"><strong><?= $stockHealth ?>%</strong><span>healthy</span></div></div>
					<div class="legend">
						<div class="legend-row"><span class="legend-dot"></span><span>Healthy stock</span><strong><?= number_format($healthyItems) ?></strong></div>
						<div class="legend-row"><span class="legend-dot low"></span><span>Low stock</span><strong><?= number_format($lowOnlyCount) ?></strong></div>
						<div class="legend-row"><span class="legend-dot out"></span><span>Out of stock</span><strong><?= number_format($outOfStock) ?></strong></div>
					</div>
				</div>
			</article>
		</section>
		<section class="content-grid">
			<article class="panel">
				<div class="panel-heading"><div><h2>Highest inventory value</h2><p>Products ranked by units on hand × unit price</p></div><a class="button" href="inventory.php">View catalog</a></div>
				<div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Units</th><th>Stock value</th></tr></thead><tbody>
				<?php foreach (array_slice($highestValueItems, 0, 5) as $item): ?>
				<tr><td><span class="product-name"><?= analyticsEscape($item['name']) ?></span><span class="sku"><?= analyticsEscape($item['sku']) ?></span></td><td><?= analyticsEscape($item['category']) ?></td><td><?= number_format($item['stock']) ?></td><td><strong>$<?= number_format($item['stock'] * $item['price'], 2) ?></strong></td></tr>
				<?php endforeach; ?>
				</tbody></table><?php if (!$items): ?><div class="empty">No inventory to rank yet.</div><?php endif; ?></div>
			</article>
			<article class="panel">
				<div class="panel-heading"><div><h2>Replenishment queue</h2><p>At or below reorder point</p></div><a class="button" href="inventory.php?status=alerts">View all</a></div>
				<?php if ($lowItems): ?><div class="table-wrap"><table><thead><tr><th>Product</th><th>Available</th><th>Status</th></tr></thead><tbody>
				<?php foreach (array_slice($lowItems, 0, 5) as $item): [$status, $statusClass] = analyticsStatus($item); ?>
				<tr><td><span class="product-name"><?= analyticsEscape($item['name']) ?></span><span class="sku"><?= analyticsEscape($item['sku']) ?></span></td><td><?= number_format($item['stock']) ?> / <?= number_format($item['reorder']) ?></td><td><span class="badge <?= analyticsEscape($statusClass) ?>"><?= analyticsEscape($status) ?></span></td></tr>
				<?php endforeach; ?>
				</tbody></table></div><?php else: ?><div class="empty">All products are above their reorder points.</div><?php endif; ?>
			</article>
		</section>
	</main>
</div>
</body>
</html>
