<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($title ?? 'Administrator sign in · MultiForex') ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body class="admin-login-page">
<main class="admin-login-wrap">
    <a class="admin-login-brand" href="<?= e(site_url()) ?>" aria-label="MultiForex home"><span class="admin-brand-mark">MF</span><span>MultiForex<small>Administration</small></span></a>
    <section class="admin-login-card" aria-labelledby="admin-login-title">
        <div class="admin-eyebrow">SECURE ADMIN AREA</div>
        <h1 id="admin-login-title">Administrator sign in</h1>
        <p class="admin-muted">Use your authorized administrator account to continue.</p>
        <?php if (!empty($notice)): ?><div class="admin-alert admin-alert-info" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(admin_url('login')) ?>" data-admin-login novalidate>
            <input type="hidden" name="_admin_csrf" value="<?= e($csrfToken) ?>">
            <label class="admin-field"><span>Email address</span><input type="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="username" inputmode="email" maxlength="254" required autofocus></label>
            <label class="admin-field"><span>Password</span><span class="admin-password-wrap"><input id="admin-password" type="password" name="password" autocomplete="current-password" maxlength="1024" required><button type="button" class="admin-password-toggle" data-password-toggle aria-controls="admin-password" aria-pressed="false">Show</button></span></label>
            <button class="admin-button admin-button-primary admin-login-submit" type="submit"><span data-button-label>Sign in</span><span class="admin-spinner" aria-hidden="true"></span></button>
        </form>
        <p class="admin-login-note">Administrator access is provisioned through secure command-line tooling.</p>
    </section>
    <footer class="admin-credit">Software Development by <a href="https://www.energeticedge.lk/" target="_blank" rel="noopener noreferrer">Energetic Edge (Pvt) Ltd</a></footer>
</main>
<script src="<?= e(asset_url('js/admin.js')) ?>" defer></script>
</body>
</html>
