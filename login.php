<?php
require_once __DIR__ . '/bootstrap.php';

$isFirstLogin = empty($_SESSION['auth_password_hash']);
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$action = $_POST['action'] ?? '';

	if (!hash_equals($_SESSION['inventory_csrf'], $_POST['csrf'] ?? '')) {
		http_response_code(400);
		$error = 'Your session expired. Refresh the page and try again.';
	} elseif ($isFirstLogin && $action === 'setup') {
		$confirmation = $_POST['password_confirmation'] ?? '';
		if (strlen($username) < 3 || strlen($username) > 60) {
			$error = 'Username must be between 3 and 60 characters.';
		} elseif (strlen($password) < 12) {
			$error = 'Choose a password with at least 12 characters.';
		} elseif (!hash_equals($password, $confirmation)) {
			$error = 'The password confirmation does not match.';
		} else {
			session_regenerate_id(true);
			$_SESSION['auth_username'] = $username;
			$_SESSION['auth_user'] = $username;
			$_SESSION['auth_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
			header('Location: dashboard.php');
			exit;
		}
	} elseif (!$isFirstLogin && $action === 'login') {
		if (hash_equals((string) ($_SESSION['auth_username'] ?? ''), $username)
			&& password_verify($password, $_SESSION['auth_password_hash'])) {
			session_regenerate_id(true);
			$_SESSION['auth_user'] = $username;
			header('Location: dashboard.php');
			exit;
		}
		$error = 'Username or password is incorrect.';
	} else {
		$error = 'This sign-in request could not be completed. Refresh and try again.';
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#235a4a">
	<title><?= $isFirstLogin ? 'Set up workspace' : 'Sign in' ?> | Stockroom</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --ink:#202b28; --muted:#78827d; --line:#e4e9e3; --green:#2e725e; --green-dark:#235a4a; --green-pale:#e9f3ee; --red:#b94c43; --red-pale:#fff0ed; }
		* { box-sizing:border-box; }
		body { min-height:100vh; margin:0; color:var(--ink); background:#f4f6f2; font:14px 'DM Sans',sans-serif; }
		.page { display:grid; grid-template-columns:minmax(320px,.9fr) minmax(420px,1.1fr); min-height:100vh; }
		.brand-panel { position:relative; display:flex; flex-direction:column; justify-content:space-between; overflow:hidden; padding:38px clamp(28px,6vw,82px); color:#f8fbf8; background:#235a4a; }
		.brand-panel::after { position:absolute; inset:0; opacity:.11; background-image:linear-gradient(135deg,transparent 49.5%,#dcebe1 49.7%,#dcebe1 50.2%,transparent 50.4%),linear-gradient(45deg,transparent 49.5%,#dcebe1 49.7%,#dcebe1 50.2%,transparent 50.4%); background-size:54px 54px; content:''; pointer-events:none; }
		.brand,.brand-copy,.brand-foot { position:relative; z-index:1; }
		.brand { display:flex; align-items:center; gap:11px; color:#fff; text-decoration:none; }
		.brand-mark { display:grid; place-items:center; width:38px; height:38px; border-radius:10px; color:var(--green-dark); background:#edf5ee; }
		.brand-mark svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
		.brand-name { font:800 18px 'Manrope',sans-serif; }
		.brand-name span { color:#b8d5c2; }
		.brand-copy { max-width:440px; margin:55px auto; }
		.brand-copy .eyebrow { color:#b8d5c2; font-size:10px; font-weight:700; letter-spacing:1.3px; text-transform:uppercase; }
		.brand-copy h1 { margin:13px 0 12px; font:700 clamp(32px,4vw,48px)/1.12 'Manrope',sans-serif; letter-spacing:0; }
		.brand-copy p { max-width:370px; margin:0; color:#d5e5da; font-size:14px; line-height:1.7; }
		.brand-foot { color:#c0d8c8; font-size:11px; }
		.form-panel { display:grid; place-items:center; padding:36px 24px; background:#f8faf7; }
		.form-wrap { width:min(100%,390px); animation:arrive .45s ease-out both; }
		@keyframes arrive { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
		.form-eyebrow { color:var(--green); font-size:10px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; }
		.form-wrap h2 { margin:9px 0 7px; font:700 27px 'Manrope',sans-serif; letter-spacing:0; }
		.intro { margin:0 0 25px; color:var(--muted); font-size:12px; line-height:1.6; }
		.error { margin-bottom:16px; padding:11px 12px; border:1px solid #f0d1cb; border-radius:6px; color:#9c4038; background:var(--red-pale); font-size:12px; }
		.fields { display:grid; gap:15px; }
		.field { display:grid; gap:7px; }
		.field label { color:#46524b; font-size:11px; font-weight:700; }
		.field input { width:100%; height:43px; padding:0 12px; border:1px solid #dfe5de; border-radius:6px; outline:none; color:var(--ink); background:#fff; font-size:13px; }
		.field input:focus { border-color:#6b9b84; box-shadow:0 0 0 3px rgba(46,114,94,.1); }
		.submit { display:flex; align-items:center; justify-content:center; width:100%; min-height:43px; margin-top:5px; border:0; border-radius:6px; color:#fff; background:var(--green); font-size:12px; font-weight:700; cursor:pointer; }
		.submit:hover { background:var(--green-dark); }
		.note { margin:15px 0 0; color:#909a93; font-size:10px; line-height:1.6; }
		@media(max-width:760px) { .page { grid-template-columns:1fr; } .brand-panel { min-height:205px; padding:21px 24px; } .brand-copy { margin:26px 0 18px; } .brand-copy h1 { margin:8px 0; font-size:29px; } .brand-copy p { font-size:12px; } .brand-foot { display:none; } .form-panel { padding:33px 22px 45px; } }
		@media(prefers-reduced-motion:reduce) { *,*::before,*::after { animation-duration:.01ms !important; animation-iteration-count:1 !important; scroll-behavior:auto !important; } }
	</style>
</head>
<body>
<div class="page">
	<section class="brand-panel" aria-label="Stockroom">
		<a class="brand" href="login.php"><span class="brand-mark"><svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4m-8 4v10"/></svg></span><span class="brand-name">stock<span>room</span></span></a>
		<div class="brand-copy"><div class="eyebrow">Inventory workspace</div><h1>Know what’s on your shelves.</h1><p>One clear view of your products, suppliers, and incoming stock.</p></div>
		<div class="brand-foot">Stockroom inventory management</div>
	</section>
	<main class="form-panel">
		<div class="form-wrap">
			<div class="form-eyebrow"><?= $isFirstLogin ? 'Workspace setup' : 'Welcome back' ?></div>
			<h2><?= $isFirstLogin ? 'Create your sign-in' : 'Sign in to Stockroom' ?></h2>
			<p class="intro"><?= $isFirstLogin ? 'Set a username and password for this workspace.' : 'Enter your workspace credentials to continue.' ?></p>
			<?php if ($error !== ''): ?><div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
			<form method="post" action="login.php" class="fields" autocomplete="on">
				<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['inventory_csrf'], ENT_QUOTES, 'UTF-8') ?>">
				<input type="hidden" name="action" value="<?= $isFirstLogin ? 'setup' : 'login' ?>">
				<div class="field"><label for="username">Username</label><input id="username" name="username" type="text" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" minlength="3" maxlength="60" autocomplete="username" required autofocus></div>
				<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="<?= $isFirstLogin ? '12' : '1' ?>" autocomplete="<?= $isFirstLogin ? 'new-password' : 'current-password' ?>" required></div>
				<?php if ($isFirstLogin): ?><div class="field"><label for="password-confirmation">Confirm password</label><input id="password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div><?php endif; ?>
				<button class="submit" type="submit"><?= $isFirstLogin ? 'Create workspace sign-in' : 'Sign in' ?></button>
			</form>
			<?php if ($isFirstLogin): ?><p class="note">This prototype stores sign-in details in the current PHP session. Use a persistent user store before deploying for multiple users.</p><?php endif; ?>
		</div>
	</main>
</div>
</body>
</html>