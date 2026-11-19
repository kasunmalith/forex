# Phase 18: Admin authentication and RBAC

This phase provides the MultiForex administration security foundation. It does not create an administrator automatically and does not implement operational admin modules.

## Database migration

On the existing WAMP installation, apply `database/migrations/phase18_admin_rbac.sql` once after the Phase 17 migration:

```powershell
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe --host=localhost --user=root forex --execute "SOURCE C:/wamp64/www/forex/database/migrations/phase18_admin_rbac.sql"
```

The migration adds system-role state, role state, disabled admin status, Admin session-version state, permission labels/groups, and the Phase 18 permission catalogue/mappings. It preserves existing admins and legacy permission keys. Back up the database before applying it in production.

## Create the first administrator

There are no default credentials and no web-based setup route. On Windows WAMP, run PowerShell:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
& C:\wamp64\www\forex\tools\create-admin.ps1 -Name 'Your Name' -Email 'you@example.com'
```

The password is requested with a secure PowerShell prompt and is not passed as a command-line argument. The first administrator must use the `super-admin` role. The PHP script only runs in CLI and never prints the password.

To reset an existing Admin password from WAMP PowerShell, run:

```powershell
& C:\wamp64\www\forex\tools\reset-admin-password.ps1 -Email 'you@example.com'
```

The reset command also uses a secure password prompt, logs a secret-free audit event, and increments the Admin session version so existing Admin sessions are rejected on their next request.

On a Unix-like production terminal, provide the password through a hidden shell prompt and invoke PHP:

```sh
read -s -p 'Administrator password: ' MULTIFOREX_ADMIN_PASSWORD; echo
export MULTIFOREX_ADMIN_PASSWORD
php tools/create-admin.php --name='Your Name' --email='you@example.com' --role=super-admin
unset MULTIFOREX_ADMIN_PASSWORD
```

If the hosting provider does not offer terminal access, arrange temporary trusted shell access with the provider. This project intentionally has no public first-admin creation route.

## Roles and permissions

| Role | Phase 18 permissions |
| --- | --- |
| Super Admin | Every permission currently seeded, including compatibility permissions |
| Administrator | Dashboard; users, accounts, trading, instruments, funds, verification, support, notifications, content, settings view, administrators view, audit; operational management permissions except administrator/role management and settings writes |
| Support | Dashboard, basic user view, support view/reply, notification management |
| Compliance / Verification | Dashboard, basic user view, verification view/review, audit view |
| Finance / Demo Funds | Dashboard, user and demo account view, funds view/process, audit view |
| Content Manager | Dashboard and content view/manage |

The full machine permission catalogue is seeded in the migration. `admin_role_permissions` explicitly maps Super Admin to all permissions. Permissions are queried from the database for each protected request, so role changes take effect on the next request. Legacy keys remain for existing service integrations; only Super Admin and the matching operational role receive the legacy key.

## Protect future routes and APIs

For an HTML route, call `AdminAuthorizationService::requirePermission('users.view')` before accessing data. For an API route, use the same call and return a JSON 403 response when the permission is absent. Do not rely on the sidebar to protect data. Add new keys to the permission seed and map them to roles explicitly in a later migration.

## Session and production notes

- Admin identity is stored in `$_SESSION['admin_auth']`; client identity remains in `$_SESSION['auth']`.
- Admin sessions expire after the configurable 30-minute inactivity timeout. The account and role status are checked against MySQL on each protected request.
- Admin password resets increment `admins.session_version`; older Admin sessions are rejected on their next request.
- Login throttling uses separate, hashed email/IP and IP-only buckets in `auth_rate_limits`; the default is five failures in fifteen minutes.
- Administrator password reset is handled through trusted CLI tooling; no Admin password-reset web flow is implemented.
- HTTPS must be enabled in production so the existing environment-aware session cookie uses `Secure`; retain `HttpOnly`, `SameSite=Lax`, and strict session mode.
- Admin pages set noindex, no-store, CSP, frame, and content-sniffing headers. Keep `APP_DEBUG` disabled in production.
- Admin audit events contain action, target identifiers, IP, and user agent. Passwords, password hashes, session IDs, CSRF tokens, and secret values are never passed to the logger.
