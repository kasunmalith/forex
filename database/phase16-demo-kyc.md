# Phase 16 demo verification storage

The application stores documents under the configured PRIVATE_STORAGE_PATH, defaulting to storage/private/kyc/. The storage tree is blocked by the existing storage/.htaccess, with an additional deny rule in storage/private/.htaccess. Directory listing is disabled. Do not move KYC files into uploads, assets, or a public CDN.

PHP creates per-user and per-submission directories with mode 0750 and files with mode 0640. On Linux/cPanel, set ownership so the PHP process can write and read the private directory, use directories 0750 and files 0640, and keep the path outside the public document root where possible. If it remains under the application root, retain both Apache deny rules and verify AllowOverride permits authorization directives. A cPanel Apache 2.4 .htaccess can use Require all denied and Options -Indexes; older Apache deployments need the host's equivalent Deny from all.

Allowed formats are PDF (application/pdf), JPEG (image/jpeg) and PNG (image/png), with filename extension, server detected MIME, image decode or PDF signature validation. Default per-file limit is 5 MiB and the per-user stored file total is 10 MiB. Both are configurable in config/config.php. The upload rate defaults to eight files per user per hour.

Secure access uses GET /client/verification/document?ref=MF-KYC-DOC-…; the route checks the authenticated user's ownership and streams as an attachment with nosniff and private no-store headers. Future Admin review must call KycService methods, check the manage_kyc permission, and use SecureDocumentService::streamAdminDocument; never expose storage_path or stored filenames in a URL.

Replaced documents are marked superseded and retained for audit continuity. No automatic deletion runs. Before retention cleanup is introduced, define an authorized retention period, verify no open review depends on the file, and remove the database record and private file together with compensating error handling. Back up the private tree only into encrypted, access-restricted backups; never place it in a publicly downloadable backup or web root archive. Do not log document contents, dates of birth, addresses, or document numbers. KYC_DEMO_MODE=true is the default; the page warns visitors not to upload real identity documents.

The workflow stores only demo verification states. It does not scan files for malware or perform OCR, biometrics, government checks, or external KYC verification.
