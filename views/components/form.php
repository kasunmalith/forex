<form method="post" action="<?= e($action ?? site_url()) ?>">
    <?= csrf_field() ?>
    <?= $fields ?? '' /* Trusted server-rendered form fields only. */ ?>
</form>
