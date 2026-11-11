<?php
require_once __DIR__ . '/bootstrap.php';

$suppliers = &$_SESSION['suppliers'];
$error = '';

function supplierEscape($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function supplierRedirect(): never
{
	header('Location: suppliers.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
		http_response_code(400);
		$error = 'Your session expired. Refresh the page and try again.';
	} else {
		$action = $_POST['action'] ?? '';
		$id = trim($_POST['id'] ?? '');
		if ($action === 'delete') {
			$hasOrders = array_filter($_SESSION['purchase_orders'], fn ($order) => $order['supplier_id'] === $id);
			if ($hasOrders) {
				$error = 'This supplier has purchase-order history and cannot be deleted.';
			} else {
				$suppliers = array_values(array_filter($suppliers, fn ($supplier) => $supplier['id'] !== $id));
				$_SESSION['supplier_flash'] = 'Supplier deleted.';
				supplierRedirect();
			}
		} elseif ($action === 'create' || $action === 'update') {
			$name = trim($_POST['name'] ?? '');
			$contact = trim($_POST['contact'] ?? '');
			$email = strtolower(trim($_POST['email'] ?? ''));
			$phone = trim($_POST['phone'] ?? '');
			$leadDays = filter_var($_POST['lead_days'] ?? null, FILTER_VALIDATE_INT);
			$originalId = trim($_POST['original_id'] ?? '');
			$existingIndex = array_search($originalId, array_column($suppliers, 'id'), true);

			if ($name === '' || $contact === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === ''
				|| $leadDays === false || $leadDays < 0 || $leadDays > 365) {
				$error = 'Enter a valid supplier name, contact, email, phone, and lead time.';
			} elseif ($action === 'update' && $existingIndex === false) {
				$error = 'This supplier could not be found. Refresh the page and try again.';
			} else {
				$duplicateEmail = array_filter($suppliers, fn ($supplier) => $supplier['email'] === $email && $supplier['id'] !== $originalId);
				if ($duplicateEmail) {
					$error = 'A supplier with that email address already exists.';
				} else {
					$supplier = [
						'id' => $action === 'update' ? $originalId : 'SUP-' . strtoupper(bin2hex(random_bytes(3))),
						'name' => inventoryTextLimit($name, 80),
						'contact' => inventoryTextLimit($contact, 80),
						'email' => $email,
						'phone' => inventoryTextLimit($phone, 30),
						'lead_days' => $leadDays,
					];
					if ($action === 'create') {
						$suppliers[] = $supplier;
						$_SESSION['supplier_flash'] = 'Supplier added.';
					} else {
					$suppliers[$existingIndex] = $supplier;
						$_SESSION['supplier_flash'] = 'Supplier details updated.';
					}
					supplierRedirect();
				}
			}
		} else {
			$error = 'Choose a valid supplier action.';
		}
	}
}

$flash = $_SESSION['supplier_flash'] ?? '';
unset($_SESSION['supplier_flash']);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f4f6f2">
	<title>Suppliers | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --ink:#202b28; --muted:#78827d; --line:#e6eae5; --paper:#fff; --canvas:#f4f6f2; --green:#2e725e; --green-dark:#235a4a; --green-pale:#e9f3ee; --red:#b94c43; --red-pale:#fff0ed; --shadow:0 12px 32px rgba(34,54,45,.055); }
		* { box-sizing:border-box; }
		body { margin:0; color:var(--ink); background:var(--canvas); font:14px 'DM Sans',sans-serif; }
		button,input { font:inherit; }
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
		.panel { border:1px solid var(--line); border-radius:8px; background:var(--paper); box-shadow:var(--shadow); }
		.panel-head { display:flex; align-items:center; justify-content:space-between; padding:18px 19px 14px; }
		.panel-head h2 { margin:0; font:700 15px 'Manrope',sans-serif; }
		.panel-head p { margin:4px 0 0; color:var(--muted); font-size:11px; }
		.notice { margin:0 0 14px; padding:11px 13px; border:1px solid #cde2d5; border-radius:6px; color:var(--green-dark); background:#eef7f1; font-size:12px; }
		.notice.error { border-color:#f0d1cb; color:#9c4038; background:var(--red-pale); }
		.table-wrap { overflow:auto; }
		table { width:100%; border-collapse:collapse; text-align:left; }
		th { height:36px; padding:0 14px; color:#929b95; background:#fafbf9; font-size:9px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; white-space:nowrap; }
		th:first-child,td:first-child { padding-left:19px; }
		th:last-child,td:last-child { padding-right:19px; }
		td { height:63px; padding:0 14px; border-top:1px solid #eef0ed; color:#59645e; font-size:11px; white-space:nowrap; }
		td strong { display:block; color:#29332e; font-size:12px; }
		.subtle { display:block; margin-top:4px; color:#929b95; font-size:10px; }
		.actions { display:flex; justify-content:flex-end; gap:6px; }
		.actions form { margin:0; }
		.actions button { min-height:29px; padding:0 9px; border:1px solid var(--line); border-radius:5px; color:#56635c; background:#fff; font-size:10px; font-weight:600; }
		.actions .delete { color:#a6534c; }
		.empty { padding:34px; color:var(--muted); text-align:center; }
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
		.field input { width:100%; height:37px; padding:0 10px; border:1px solid var(--line); border-radius:5px; outline-color:var(--green); font-size:12px; }
		.dialog-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
		.cancel { min-height:38px; padding:0 13px; border:1px solid var(--line); border-radius:6px; color:#45524b; background:#fff; font-size:11px; font-weight:700; }
		@media(max-width:1000px) { main { padding-right:24px; padding-left:24px; } }
		@media(max-width:800px) { .app { grid-template-columns:66px minmax(0,1fr); } .sidebar { align-items:center; padding:20px 8px; } .brand { padding:0; margin-bottom:35px; } .brand-name,.nav-label,.nav a span,.warehouse,.profile>div:last-child { display:none; } .nav a { justify-content:center; padding:0; } .sidebar-bottom { width:100%; } .profile { justify-content:center; padding:14px 0 0; } main { padding-right:18px; padding-left:18px; } }
		@media(max-width:560px) { .app { display:block; padding-bottom:58px; } .sidebar { position:fixed; z-index:3; right:0; bottom:0; left:0; flex-direction:row; justify-content:center; height:58px; padding:5px 12px; border-top:1px solid var(--line); border-right:0; background:#fff; } .brand,.sidebar-bottom { display:none; } .nav { display:flex; justify-content:space-around; width:100%; max-width:420px; } .nav a { width:48px; min-height:42px; } .nav a:nth-child(n+5) { display:none; } main { padding:0 14px 24px; } .topbar { min-height:59px; } .page-heading { align-items:flex-start; padding:25px 0 18px; } h1 { font-size:23px; } .page-heading p { max-width:230px; line-height:1.5; } .page-heading>.button { width:39px; min-width:39px; padding:0; overflow:hidden; font-size:0; } .page-heading>.button svg { width:17px; height:17px; flex:none; } }
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
			<a class="active" href="suppliers.php" aria-current="page"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3h6m-3-3v6"/></svg><span>Suppliers</span></a>
			<a href="orders.php"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8m-8 4h8m-8 4h5"/></svg><span>Purchase orders</span></a>
			<a href="analytics.php"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg><span>Analytics</span></a>
			<a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4L5.3 15a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1L9 7.4h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1-.1 2Z" transform="translate(1 -1) scale(.92)"/></svg><span>Settings</span></a>
		</nav>
		<div class="sidebar-bottom"><div class="warehouse"><div class="warehouse-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-8h6v8"/></svg><?= supplierEscape($inventorySettings['warehouse_name']) ?></div><p><?= supplierEscape($inventorySettings['warehouse_location']) ?></p></div><div class="profile"><div class="avatar">JD</div><div><strong>Jordan Davis</strong><small>Store manager</small></div></div></div>
	</aside>
	<main>
		<header class="topbar"><div class="breadcrumb">Workspace <span aria-hidden="true"> / </span> <strong>Suppliers</strong></div><div class="top-actions"><div class="avatar" aria-label="Jordan Davis">JD</div><form class="sign-out-form" method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= supplierEscape($_SESSION['inventory_csrf']) ?>"><button class="sign-out" type="submit">Sign out</button></form></div></header>
		<section class="page-heading"><div><div class="eyebrow">Vendor directory</div><h1>Suppliers</h1><p>Keep vendor contacts and delivery lead times up to date.</p></div><button class="button" type="button" id="open-dialog"><svg viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg>Add supplier</button></section>
		<?php if ($flash !== ''): ?><div class="notice" role="status"><?= supplierEscape($flash) ?></div><?php endif; ?>
		<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= supplierEscape($error) ?></div><?php endif; ?>
		<section class="panel">
			<div class="panel-head"><div><h2>Supplier directory</h2><p><?= count($suppliers) ?> registered suppliers</p></div></div>
			<div class="table-wrap"><table><thead><tr><th>Supplier</th><th>Contact</th><th>Email</th><th>Phone</th><th>Lead time</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
			<?php foreach ($suppliers as $supplier): ?>
				<tr><td><strong><?= supplierEscape($supplier['name']) ?></strong><span class="subtle"><?= supplierEscape($supplier['id']) ?></span></td><td><?= supplierEscape($supplier['contact']) ?></td><td><a href="mailto:<?= supplierEscape($supplier['email']) ?>"><?= supplierEscape($supplier['email']) ?></a></td><td><?= supplierEscape($supplier['phone']) ?></td><td><?= number_format($supplier['lead_days']) ?> days</td><td><div class="actions"><button class="edit-button" type="button" data-supplier='<?= supplierEscape(json_encode($supplier, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>'>Edit</button><form method="post" onsubmit="return confirm('Delete this supplier?')"><input type="hidden" name="csrf" value="<?= supplierEscape($_SESSION['inventory_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= supplierEscape($supplier['id']) ?>"><button class="delete" type="submit">Delete</button></form></div></td></tr>
			<?php endforeach; ?>
			</tbody></table><?php if (!$suppliers): ?><div class="empty">Add a supplier to start creating purchase orders.</div><?php endif; ?></div>
		</section>
	</main>
</div>
<div class="backdrop" id="backdrop" role="presentation">
	<form class="dialog" id="supplier-form" method="post" action="suppliers.php">
		<input type="hidden" name="csrf" value="<?= supplierEscape($_SESSION['inventory_csrf']) ?>"><input type="hidden" name="action" id="form-action" value="create"><input type="hidden" name="original_id" id="original-id" value="">
		<div class="dialog-head"><div><h2 id="dialog-title">Add supplier</h2><p id="dialog-description">Add a vendor to your directory.</p></div><button class="close" type="button" id="close-dialog" aria-label="Close dialog">×</button></div>
		<div class="fields">
			<div class="field full"><label for="supplier-name">Supplier name</label><input id="supplier-name" name="name" required maxlength="80" placeholder="e.g. North Coast Supply"></div>
			<div class="field"><label for="supplier-contact">Contact person</label><input id="supplier-contact" name="contact" required maxlength="80"></div>
			<div class="field"><label for="supplier-lead">Lead time (days)</label><input id="supplier-lead" name="lead_days" type="number" min="0" max="365" step="1" value="5" required></div>
			<div class="field"><label for="supplier-email">Email</label><input id="supplier-email" name="email" type="email" required maxlength="120"></div>
			<div class="field"><label for="supplier-phone">Phone</label><input id="supplier-phone" name="phone" type="tel" required maxlength="30"></div>
		</div>
		<div class="dialog-actions"><button class="cancel" type="button" id="cancel-dialog">Cancel</button><button class="button" type="submit" id="submit-label">Add supplier</button></div>
	</form>
</div>
<script>
	const backdrop = document.getElementById('backdrop');
	const form = document.getElementById('supplier-form');
	function showDialog(supplier = null) {
		form.reset();
		document.getElementById('form-action').value = supplier ? 'update' : 'create';
		document.getElementById('original-id').value = supplier?.id || '';
		document.getElementById('dialog-title').textContent = supplier ? 'Edit supplier' : 'Add supplier';
		document.getElementById('dialog-description').textContent = supplier ? 'Update supplier contact details.' : 'Add a vendor to your directory.';
		document.getElementById('submit-label').textContent = supplier ? 'Save changes' : 'Add supplier';
		if (supplier) {
			form.elements.name.value = supplier.name;
			form.elements.contact.value = supplier.contact;
			form.elements.email.value = supplier.email;
			form.elements.phone.value = supplier.phone;
			form.elements.lead_days.value = supplier.lead_days;
		}
		backdrop.classList.add('open');
		document.getElementById('supplier-name').focus();
	}
	function hideDialog() { backdrop.classList.remove('open'); }
	document.getElementById('open-dialog').addEventListener('click', () => showDialog());
	document.getElementById('close-dialog').addEventListener('click', hideDialog);
	document.getElementById('cancel-dialog').addEventListener('click', hideDialog);
	backdrop.addEventListener('click', (event) => { if (event.target === backdrop) hideDialog(); });
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideDialog(); });
	document.querySelectorAll('.edit-button').forEach((button) => button.addEventListener('click', () => showDialog(JSON.parse(button.dataset.supplier))));
</script>
</body>
</html>