<?php
require APP_ROOT . '/views/components/header.php';
$errors = $errors ?? [];
?>
<div class="auth-page auth-login-page"><div class="auth-wrap"><nav class="public-breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(site_url()) ?>">Home</a><span>/</span><span aria-current="page">Sign in</span></nav>
    <section class="auth-layout"><div class="auth-intro"><span class="public-eyebrow"><i></i> YOUR DEMO WORKSPACE</span><h1>Welcome<br><em>back.</em></h1><p>Sign in to continue to your protected demo account and virtual balance.</p><div class="auth-visual auth-visual-login" aria-hidden="true"><span class="auth-visual-tag">SECURE SESSION / ACTIVE</span><div class="auth-login-orbit"><b>V</b><i></i><i></i><i></i></div><span class="auth-visual-foot">PRIVATE · SESSION PROTECTED</span></div><div class="auth-proof"><span>02</span><p>Your account data is loaded from your profile after secure sign in.</p></div></div>
    <div class="auth-card auth-card-compact"><div class="auth-card-heading"><span class="public-eyebrow">DEMO ACCOUNT ACCESS</span><h2>Sign in</h2><p>Enter the email and password used for your demo account.</p></div>
        <?php if (!empty($notice)): ?><div class="auth-alert is-success" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="auth-alert is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if (!empty($errors['_form'])): ?><div class="auth-alert is-error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
        <form class="auth-form auth-form-stacked" method="post" action="<?= e(site_url('login')) ?>" data-auth-form>
            <?= csrf_field() ?>
            <label class="auth-field"><span>Email address</span><input name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($email ?? '') ?>" required autofocus></label>
            <label class="auth-field"><span>Password</span><span class="auth-password-wrap"><input id="login-password" name="password" type="password" maxlength="128" autocomplete="current-password" required><button class="auth-password-toggle" type="button" data-password-toggle="login-password" aria-label="Show password" aria-pressed="false">Show</button></span></label>
            <div class="auth-inline-link"><a href="<?= e(site_url('forgot-password')) ?>">Forgot password?</a></div>
            <button class="btn btn-primary auth-submit" type="submit" data-submit-label="Sign In">Sign In <span aria-hidden="true">↗</span></button>
        </form>
        <p class="auth-switch">New to the platform? <a href="<?= e(site_url('register')) ?>">Create a demo account</a></p>
        <p class="auth-privacy">Demo environment · Virtual funds only · No real trades</p>
    </div></section>
</div>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
