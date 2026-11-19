<?php
require APP_ROOT . '/views/components/header.php';
$errors = $errors ?? [];
$tokenValid = !empty($tokenValid);
$fieldError = static fn(string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="error-' . e($key) . '"' : '';
?>
<div class="auth-page auth-narrow-page"><div class="auth-wrap auth-wrap-narrow"><nav class="public-breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(site_url()) ?>">Home</a><span>/</span><a href="<?= e(site_url('login')) ?>">Sign in</a><span>/</span><span aria-current="page">New password</span></nav>
    <section class="auth-card auth-reset-card"><div class="auth-reset-icon" aria-hidden="true"><?= $tokenValid ? '⌑' : '!' ?></div>
        <?php if (!$tokenValid): ?><div class="auth-card-heading"><span class="public-eyebrow">RESET LINK UNAVAILABLE</span><h1>This link is invalid or has expired.</h1><p>Request a new password reset link and use it within 30 minutes.</p></div><?php if(!empty($errors['_form'])): ?><div class="auth-alert is-error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?><a class="btn btn-primary auth-submit auth-link-button" href="<?= e(site_url('forgot-password')) ?>">Request a New Link <span aria-hidden="true">↗</span></a>
        <?php else: ?><div class="auth-card-heading"><span class="public-eyebrow">SECURE PASSWORD RESET</span><h1>Choose a new password.</h1><p>Use at least 10 characters. This reset link can only be used once.</p></div>
            <?php if (!empty($errors['_form'])): ?><div class="auth-alert is-error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
            <form class="auth-form auth-form-stacked" method="post" action="<?= e(site_url('reset-password')) ?>" data-auth-form><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                <label class="auth-field"><span>New password</span><span class="auth-password-wrap"><input id="reset-password" name="password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required<?= $fieldError('password') ?>><button class="auth-password-toggle" type="button" data-password-toggle="reset-password" aria-label="Show password" aria-pressed="false">Show</button></span><?php if(isset($errors['password'])): ?><small class="auth-field-error" id="error-password"><?= e($errors['password']) ?></small><?php endif; ?></label>
                <label class="auth-field"><span>Confirm new password</span><span class="auth-password-wrap"><input id="reset-password-confirm" name="password_confirmation" type="password" minlength="10" maxlength="128" autocomplete="new-password" required<?= $fieldError('password_confirmation') ?>><button class="auth-password-toggle" type="button" data-password-toggle="reset-password-confirm" aria-label="Show password" aria-pressed="false">Show</button></span><?php if(isset($errors['password_confirmation'])): ?><small class="auth-field-error" id="error-password_confirmation"><?= e($errors['password_confirmation']) ?></small><?php endif; ?></label>
                <button class="btn btn-primary auth-submit" type="submit" data-submit-label="Update Password">Update Password <span aria-hidden="true">↗</span></button>
            </form><?php endif; ?>
    </section></div></div>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
