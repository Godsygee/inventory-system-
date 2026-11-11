<?php
require_once __DIR__ . '/bootstrap.php';

$orders = &$_SESSION['purchase_orders'];
$items = &$_SESSION['inventory_items'];
$suppliers = $_SESSION['suppliers'];
$error = '';

function orderEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function orderRedirect(): never
{
	header('Location: orders.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
		http_response_code(400);
		$error = 'Your session expired. Refresh the page and try again.';
	} else {
		$action = $_POST['action'] ?? '';
		if ($action === 'receive') {
			$orderId = trim($_POST['order_id'] ?? '');
			$orderIndex = array_search($orderId, array_column($orders, 'id'), true);
			if ($orderIndex === false || $orders[$orderIndex]['status'] !== 'Ordered') {
				$error = 'This order is unavailable or has already been received.';
			} else {
				$itemIndex = array_search($orders[$orderIndex]['sku'], array_column($items, 'sku'), true);
				if ($itemIndex === false) {
					$error = 'The product linked to this order no longer exists.';
				} else {
					$items[$itemIndex]['stock'] += $orders[$orderIndex]['quantity'];
					$orders[$orderIndex]['status'] = 'Received';
					$orders[$orderIndex]['received_at'] = date('Y-m-d');
					$_SESSION['order_flash'] = 'Purchase order received and inventory updated.';
					orderRedirect();
				}
			}
		} elseif ($action === 'create') {
			$supplierId = trim($_POST['supplier_id'] ?? '');
			$sku = strtoupper(trim($_POST['sku'] ?? ''));
			$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
			$unitCost = filter_var($_POST['unit_cost'] ?? null, FILTER_VALIDATE_FLOAT);
			$supplierIndex = array_search($supplierId, array_column($suppliers, 'id'), true);
			$itemIndex = array_search($sku, array_column($items, 'sku'), true);

			if ($supplierIndex === false || $itemIndex === false || $quantity === false || $quantity < 1
				|| $unitCost === false || $unitCost < 0) {
				$error = 'Choose a supplier and product, then enter a valid quantity and unit cost.';
			} else {
				$orders[] = [
					'id' => 'PO-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
					'supplier_id' => $supplierId,
					'supplier_name' => $suppliers[$supplierIndex]['name'],
					'sku' => $sku,
					'product_name' => $items[$itemIndex]['name'],
					'quantity' => $quantity,
					'unit_cost' => round((float) $unitCost, 2),
					'status' => 'Ordered',
					'created_at' => date('Y-m-d'),
					'received_at' => null,
				];
				$_SESSION['order_flash'] = 'Purchase order created.';
				orderRedirect();
			}
		} else {
			$error = 'Choose a valid purchase-order action.';
		}
	}
}

$flash = $_SESSION['order_flash'] ?? '';
unset($_SESSION['order_flash']);
$filter = $_GET['status'] ?? 'all';
$visibleOrders = array_filter($orders, fn ($order) => $filter === 'all' || strtolower($order['status']) === strtolower($filter));
$openCount = count(array_filter($orders, fn ($order) => $order['status'] === 'Ordered'));
$orderedValue = array_sum(array_map(fn ($order) => $order['status'] === 'Ordered' ? $order['quantity'] * $order['unit_cost'] : 0, $orders));
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Purchase orders | Stockroom</title>
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
		.button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:38px; padding:0 13px; border:1px solid transparent; border-radius:6px; color:#fff; background:var(--green); font-size:11px; font-weight:700; white-space:nowrap; }
		.button:hover { background:var(--green-dark); }
		.button svg { width:15px; height:15px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; }
		.metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:13px; margin-bottom:17px; }
		.metric { min-height:100px; padding:15px 17px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:var(--shadow); }
		.metric-label { color:var(--muted); font-size:11px; font-weight:600; }
		.metric-value { margin-top:10px; font:700 23px 'Manrope',sans-serif; }
		.panel { border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:var(--shadow); }
		.panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:17px 19px 13px; }
		.panel-head h2 { margin:0; font:700 15px 'Manrope',sans-serif; }
		.panel-head p { margin:4px 0 0; color:var(--muted); font-size:11px; }
		.filters { display:flex; gap:5px; padding:0 19px 14px; }
		.filters a { padding:7px 10px; border-radius:5px; color:#737e77; text-decoration:none; font-size:10px; font-weight:700; }
		.filters a.active { color:var(--green-dark); background:var(--green-pale); }
		.notice { margin:0 0 14px; padding:11px 13px; border:1px solid #cde2d5; border-radius:6px; color:var(--green-dark); background:#eef7f1; font-size:12px; }
		.notice.error { border-color:#f0d1cb; color:#9c4038; background:var(--red-pale); }
		.table-wrap { overflow:auto; }
		table { width:100%; border-collapse:collapse; text-align:left; }
		th { height:36px; padding:0 13px; color:#929b95; background:#fafbf9; font-size:9px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; white-space:nowrap; }
		th:first-child,td:first-child { padding-left:19px; }
		th:last-child,td:last-child { padding-right:19px; }
		td { height:58px; padding:0 13px; border-top:1px solid #eef0ed; color:#59645e; font-size:10px; white-space:nowrap; }
		td strong { display:block; color:#29332e; font-size:11px; }
		.subtle { display:block; margin-top:3px; color:#929b95; font-size:9px; }
		.badge { display:inline-flex; align-items:center; gap:5px; min-height:22px; padding:0 8px; border-radius:20px; font-size:9px; font-weight:700; }
		.badge::before { width:5px; height:5px; border-radius:50%; background:currentColor; content:''; }
		.badge.ordered { color:var(--amber); background:var(--amber-pale); }
		.badge.received { color:var(--green); background:var(--green-pale); }
		.row-action { min-height:29px; padding:0 9px; border:1px solid var(--line); border-radius:5px; color:#56635c; background:#fff; font-size:10px; font-weight:600; }
		.row-action:hover { border-color:#c5d8ce; color:var(--green-dark); }
		.empty { padding:38px 20px; color:var(--muted); text-align:center; font-size:12px; }
		.backdrop { position:fixed; z-index:5; inset:0; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(25,39,32,.4); }
		.backdrop.open { display:flex; }
		.dialog { width:min(100%,440px); padding:22px; border-radius:9px; background:#fff; box-shadow:0 24px 70px rgba(23,40,31,.2); }
		.dialog-head { display:flex; justify-content:space-between; margin-bottom:18px; }
		.dialog h2 { margin:0; font:700 18px 'Manrope',sans-serif; }
		.dialog p { margin:5px 0 0; color:var(--muted); font-size:12px; }
		.close { width:30px; height:30px; border:0; border-radius:5px; color:#68746d; background:#f2f5f1; font-size:18px; }
		.fields { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
		.field { display:grid; gap:6px; }
		.field.full { grid-column:1/-1; }
		.field label { color:#59645e; font-size:11px; font-weight:700; }
		.field input,.field select { width:100%; height:37px; padding:0 10px; border:1px solid var(--line); border-radius:5px; outline-color:var(--green); color:var(--ink); background:#fff; font-size:12px; }
		.dialog-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
		.cancel { min-height:38px; padding:0 13px; border:1px solid var(--line); border-radius:6px; color:#45524b; background:#fff; font-size:11px; font-weight:700; }
		@media(max-width:1000px) { main { padding-right:22px; padding-left:22px; } }
		@media(max-width:800px) { .app { grid-template-columns:66px minmax(0,1fr); } .sidebar { align-items:center; padding:20px 8px; } .brand { padding:0; margin-bottom:35px; } .brand-name,.nav-label,.nav a span,.warehouse,.profile>div:last-child { display:none; } .nav a { justify-content:center; padding:0; } .sidebar-bottom { width:100%; } .profile { justify-content:center; padding:14px 0 0; } main { padding-right:18px; padding-left:18px; } }
		@media(max-width:560px) { .app { display:block; padding-bottom:58px; } .sidebar { position:fixed; z-index:3; right:0; bottom:0; left:0; flex-direction:row; justify-content:center; height:58px; padding:5px 12px; border-top:1px solid var(--line); border-right:0; background:#fff; } .brand,.sidebar-bottom { display:none; } .nav { display:flex; justify-content:flex-start; gap:3px; width:100%; max-width:420px; overflow-x:auto; } .nav a { width:45px; min-width:45px; min-height:42px; } main { padding:0 14px 24px; } .topbar { min-height:59px; } .page-heading { align-items:flex-start; padding:25px 0 18px; } h1 { font-size:23px; } .page-heading p { max-width:230px; line-height:1.5; } .page-heading>.button { width:39px; min-width:39px; padding:0; overflow:hidden; font-size:0; } .page-heading>.button svg { width:17px; height:17px; flex:none; } .metrics { gap:8px; } .metric { padding:12px; } .metric-value { font-size:20px; } }
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
			<a class="active" href="orders.php" aria-current="page"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8m-8 4h8m-8 4h5"/></svg><span>Purchase orders</span></a>
			<a href="analytics.php"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom"><div class="warehouse"><div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg><?= orderEscape($inventorySettings['warehouse_name']) ?></div><p><?= orderEscape($inventorySettings['warehouse_location']) ?></p></div><div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div></div>
	</aside>
	<main>
		<header class="topbar"><div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Purchase orders</strong></div><div class="top-actions"><div class="avatar" aria-label="Jordan Davis">JD</div><form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= orderEscape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form></div></header>
		<section class="page-heading"><div><div class="eyebrow">Procurement / Main warehouse</div><h1>Purchase orders</h1><p>Replenish stock and record incoming deliveries.</p></div><button class="button" type="button" id="open-dialog"><svg viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg>New order</button></section>
		<?php if ($flash !== ''): ?><div class="notice" role="status"><?= orderEscape($flash) ?></div><?php endif; ?>
		<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= orderEscape($error) ?></div><?php endif; ?>
		<section class="metrics" aria-label="Purchase order summary"><article class="metric"><div class="metric-label">Open orders</div><div class="metric-value"><?= number_format($openCount) ?></div></article><article class="metric"><div class="metric-label">Committed value</div><div class="metric-value">$<?= number_format($orderedValue, 2) ?></div></article><article class="metric"><div class="metric-label">Orders received</div><div class="metric-value"><?= number_format(count(array_filter($orders, fn ($order) => $order['status'] === 'Received'))) ?></div></article></section>
		<section class="panel">
			<div class="panel-head"><div><h2>Order history</h2><p><?= count($visibleOrders) ?> of <?= count($orders) ?> orders</p></div></div>
			<nav class="filters" aria-label="Filter purchase orders"><a class="<?= $filter === 'all' ? 'active' : '' ?>" href="orders.php">All orders</a><a class="<?= $filter === 'Ordered' ? 'active' : '' ?>" href="orders.php?status=Ordered">Open</a><a class="<?= $filter === 'Received' ? 'active' : '' ?>" href="orders.php?status=Received">Received</a></nav>
			<?php if ($visibleOrders): ?><div class="table-wrap"><table><thead><tr><th>Order</th><th>Supplier</th><th>Product</th><th>Quantity</th><th>Order total</th><th>Date</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
			<?php foreach (array_reverse($visibleOrders) as $order): ?>
				<tr><td><strong><?= orderEscape($order['id']) ?></strong></td><td><?= orderEscape($order['supplier_name']) ?></td><td><strong><?= orderEscape($order['product_name']) ?></strong><span class="subtle"><?= orderEscape($order['sku']) ?></span></td><td><?= number_format($order['quantity']) ?></td><td>$<?= number_format($order['quantity'] * $order['unit_cost'], 2) ?></td><td><?= orderEscape($order['status'] === 'Received' ? $order['received_at'] : $order['created_at']) ?><span class="subtle"><?= $order['status'] === 'Received' ? 'Received' : 'Ordered' ?></span></td><td><span class="badge <?= strtolower($order['status']) ?>"><?= orderEscape($order['status']) ?></span></td><td><?php if ($order['status'] === 'Ordered'): ?><form method="post" onsubmit="return confirm('Receive this order and add its quantity to inventory?')"><input type="hidden" name="csrf" value="<?= orderEscape($_SESSION['inventory_csrf']) ?>"><input type="hidden" name="action" value="receive"><input type="hidden" name="order_id" value="<?= orderEscape($order['id']) ?>"><button class="row-action" type="submit">Receive</button></form><?php else: ?><span class="subtle">Complete</span><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div><?php else: ?><div class="empty"><?= $orders ? 'No orders match this filter.' : 'No purchase orders yet. Create an order to begin replenishing inventory.' ?></div><?php endif; ?>
		</section>
	</main>
</div>
<div class="backdrop" id="backdrop" role="presentation">
	<form class="dialog" id="order-form" method="post" action="orders.php">
		<input type="hidden" name="csrf" value="<?= orderEscape($_SESSION['inventory_csrf']) ?>"><input type="hidden" name="action" value="create">
		<div class="dialog-head"><div><h2>New purchase order</h2><p>Choose a supplier and add one product line.</p></div><button class="close" type="button" id="close-dialog" aria-label="Close dialog">×</button></div>
		<div class="fields">
			<div class="field full"><label for="supplier-id">Supplier</label><select id="supplier-id" name="supplier_id" required><?php foreach ($suppliers as $supplier): ?><option value="<?= orderEscape($supplier['id']) ?>"><?= orderEscape($supplier['name']) ?> · <?= number_format($supplier['lead_days']) ?> day lead</option><?php endforeach; ?></select></div>
			<div class="field full"><label for="product-sku">Product</label><select id="product-sku" name="sku" required><?php foreach ($items as $item): ?><option value="<?= orderEscape($item['sku']) ?>" data-price="<?= number_format($item['price'], 2, '.', '') ?>"><?= orderEscape($item['name']) ?> · <?= orderEscape($item['sku']) ?> (<?= number_format($item['stock']) ?> on hand)</option><?php endforeach; ?></select></div>
			<div class="field"><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" min="1" step="1" value="1" required></div>
			<div class="field"><label for="unit-cost">Unit cost ($)</label><input id="unit-cost" name="unit_cost" type="number" min="0" step="0.01" value="0.00" required></div>
		</div>
		<div class="dialog-actions"><button class="cancel" type="button" id="cancel-dialog">Cancel</button><button class="button" type="submit" <?= !$suppliers || !$items ? 'disabled' : '' ?>>Create order</button></div>
	</form>
</div>
<script>
	const backdrop = document.getElementById('backdrop');
	function hideDialog() { backdrop.classList.remove('open'); }
	document.getElementById('open-dialog').addEventListener('click', () => {
		backdrop.classList.add('open');
		document.getElementById('unit-cost').value = document.getElementById('product-sku').selectedOptions[0]?.dataset.price || '0.00';
		document.getElementById('supplier-id').focus();
	});
	document.getElementById('product-sku').addEventListener('change', (event) => {
		document.getElementById('unit-cost').value = event.target.selectedOptions[0]?.dataset.price || '0.00';
	});
	document.getElementById('close-dialog').addEventListener('click', hideDialog);
	document.getElementById('cancel-dialog').addEventListener('click', hideDialog);
	backdrop.addEventListener('click', (event) => { if (event.target === backdrop) hideDialog(); });
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideDialog(); });
</script>
</body>
</html>