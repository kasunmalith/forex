<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$config = require dirname(__DIR__) . '/config/config.php';
if (($config['APP_ENV'] ?? '') !== 'development') {
    fwrite(STDERR, "Development environment required.\n");
    exit(1);
}
session_save_path(sys_get_temp_dir());
require dirname(__DIR__) . '/includes/bootstrap.php';

$tables = [
    'users','user_profiles','demo_accounts','instrument_categories','instruments','instrument_provider_mappings',
    'watchlists','orders','positions','trades','transactions','deposit_requests','withdrawal_requests',
    'kyc_submissions','kyc_documents','notifications','admin_roles','admins','admin_permissions',
    'admin_role_permissions','support_tickets','support_messages','admin_activity_logs','article_categories',
    'articles','faqs','contact_messages','site_settings','pages',
];

try {
    $pdo = App\Database::connection();
    $tableQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name');
    $failed = false;
    foreach ($tables as $table) {
        $tableQuery->execute(['schema_name' => APP_CONFIG['DB_NAME'], 'table_name' => $table]);
        $exists = (int) $tableQuery->fetchColumn() === 1;
        printf("%s table %s\n", $exists ? 'PASS' : 'FAIL', $table);
        $failed = $failed || !$exists;
    }

    foreach (['instrument_categories' => 6, 'instruments' => 19, 'admin_roles' => 3, 'admin_permissions' => 11, 'site_settings' => 10, 'article_categories' => 6, 'pages' => 5] as $table => $minimum) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        $ok = $count >= $minimum;
        printf("%s seed %s (%d rows)\n", $ok ? 'PASS' : 'FAIL', $table, $count);
        $failed = $failed || !$ok;
    }

    foreach (['users','demo_accounts','instruments','site_settings'] as $table) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        printf("PASS PDO query %s (%d rows)\n", $table, $count);
    }
    exit($failed ? 1 : 0);
} catch (Throwable $exception) {
    error_log('Development database health check failed: ' . $exception->getMessage());
    fwrite(STDERR, "FAIL database health check; see protected application log.\n");
    exit(1);
}