<?php
require APP_ROOT . '/views/components/header.php';
$errors = $errors ?? [];
?>
<div class="auth-page auth-narrow-page"><div class="auth-wrap auth-wrap-narrow"><nav class="public-breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(site_url()) ?>">Home</a><span>/</span><a href="<?= e(site_url('login')) ?>">Sign in</a><span>/</span><span aria-current="page">Password reset</span></nav>
    <section class="auth-card auth-reset-card"><div class="auth-reset-icon" aria-hidden="true">↗</div><div class="auth-card-heading"><span class="public-eyebrow">ACCOUNT RECOVERY</span><h1>Forgot your password?</h1><p>Enter the email address associated with your demo account. We’ll send reset instructions if an active account matches.</p></div>
        <?php if (!empty($notice)): ?><div class="auth-alert is-success" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="auth-alert is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if (!empty($errors['_form'])): ?><div class="auth-alert is-error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
        <form class="auth-form auth-form-stacked" method="post" action="<?= e(site_url('forgot-password')) ?>" data-auth-form><?= csrf_field() ?><label class="auth-field"><span>Email address</span><input name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($email ?? '') ?>" required autofocus></label><button class="btn btn-primary auth-submit" type="submit" data-submit-label="Send Reset Instructions">Send Reset Instructions <span aria-hidden="true">↗</span></button></form>
        <p class="auth-switch"><a href="<?= e(site_url('login')) ?>">Return to sign in</a></p><p class="auth-privacy">For your privacy, the confirmation is the same whether or not an account exists.</p>
    </section>
</div></div>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
