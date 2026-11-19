-- Phase 18 additive admin security foundation. Apply once after the Phase 17 migration.
ALTER TABLE admin_roles
    ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT FALSE AFTER description,
    ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER is_system;

ALTER TABLE admins
    MODIFY COLUMN status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
    ADD COLUMN session_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER status;

ALTER TABLE admin_permissions
    ADD COLUMN display_name VARCHAR(120) NULL AFTER permission_key,
    ADD COLUMN module VARCHAR(40) NOT NULL DEFAULT 'general' AFTER display_name;

INSERT INTO admin_roles (name, slug, description, is_system, status) VALUES
('Super Admin','super-admin','Full application-level administrative access.',TRUE,'active'),
('Administrator','administrator','Broad operational access without role or system management.',TRUE,'active'),
('Support','support','Client support and basic user context.',TRUE,'active'),
('Compliance / Verification','compliance','Identity verification review access.',TRUE,'active'),
('Finance / Demo Funds','finance','Virtual demo funding and transaction review access.',TRUE,'active'),
('Content Manager','content-manager','Public content and FAQ management access.',TRUE,'active')
ON DUPLICATE KEY UPDATE description=VALUES(description),is_system=TRUE,status='active';

INSERT INTO admin_permissions (permission_key,display_name,module,description) VALUES
('dashboard.view','View dashboard','dashboard','Open the protected administration landing page.'),
('users.view','View users','users','View basic client account information.'),
('users.manage','Manage users','users','Manage client account records and status.'),
('accounts.view','View demo accounts','accounts','View virtual demo account information.'),
('accounts.manage','Manage demo accounts','accounts','Manage virtual demo accounts.'),
('trading.view','View demo trading','trading','Review demo orders, positions, trades, and ledger records.'),
('trading.manage','Manage demo trading','trading','Manage demo trading records.'),
('instruments.view','View instruments','instruments','View simulated instrument configuration.'),
('instruments.manage','Manage instruments','instruments','Manage simulated instrument configuration.'),
('funds.view','View demo funds','funds','View virtual demo deposit, withdrawal, and transaction records.'),
('funds.process','Process demo funds','funds','Process virtual demo funding requests.'),
('kyc.view','View verification','kyc','View identity verification submissions.'),
('kyc.review','Review verification','kyc','Review identity verification submissions and documents.'),
('support.view','View support','support','View client support tickets and messages.'),
('support.reply','Reply to support','support','Reply to client support tickets.'),
('notifications.manage','Manage notifications','notifications','Manage client notifications where supported.'),
('cms.view','View content','cms','View public pages, articles, and FAQs.'),
('cms.manage','Manage content','cms','Manage public pages, articles, and FAQs.'),
('settings.view','View settings','settings','View application settings.'),
('settings.manage','Manage settings','settings','Manage application settings.'),
('admins.view','View administrators','admins','View administrator accounts.'),
('admins.manage','Manage administrators','admins','Manage administrator accounts.'),
('roles.view','View roles','roles','View administrator roles and permissions.'),
('roles.manage','Manage roles','roles','Manage role and permission assignments.'),
('audit.view','View audit logs','audit','View administrator security and activity events.'),
('manage_users','Manage users (legacy)','users','Legacy compatibility permission for existing service integrations.'),
('manage_accounts','Manage accounts (legacy)','accounts','Legacy compatibility permission for existing service integrations.'),
('manage_trading','Manage trading (legacy)','trading','Legacy compatibility permission for existing service integrations.'),
('manage_instruments','Manage instruments (legacy)','instruments','Legacy compatibility permission for existing service integrations.'),
('manage_kyc','Manage verification (legacy)','kyc','Legacy compatibility permission for existing service integrations.'),
('manage_funding','Manage demo funds (legacy)','funds','Legacy compatibility permission for existing service integrations.'),
('manage_support','Manage support (legacy)','support','Legacy compatibility permission for existing service integrations.'),
('manage_content','Manage content (legacy)','cms','Legacy compatibility permission for existing service integrations.'),
('manage_admins','Manage administrators (legacy)','admins','Legacy compatibility permission for existing service integrations.'),
('manage_settings','Manage settings (legacy)','settings','Legacy compatibility permission for existing service integrations.'),
('view_logs','View audit logs (legacy)','audit','Legacy compatibility permission for existing service integrations.')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),module=VALUES(module),description=VALUES(description);

UPDATE admin_permissions SET display_name=permission_key WHERE display_name IS NULL;
ALTER TABLE admin_permissions MODIFY COLUMN display_name VARCHAR(120) NOT NULL;

DELETE arp FROM admin_role_permissions arp
JOIN admin_roles r ON r.id=arp.role_id AND r.is_system=TRUE;

-- Super Admin receives each permission explicitly, including compatibility keys.
INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.slug='super-admin' AND r.status='active';

-- Broad operational role; role management, administrator management, and settings writes remain excluded.
INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key IN (
'dashboard.view','users.view','users.manage','accounts.view','accounts.manage','trading.view','trading.manage',
'instruments.view','instruments.manage','funds.view','funds.process','kyc.view','kyc.review','support.view','support.reply',
'notifications.manage','cms.view','cms.manage','settings.view','admins.view','audit.view',
'manage_users','manage_accounts','manage_trading','manage_instruments','manage_kyc','manage_funding','manage_support','manage_content','view_logs'
) WHERE r.slug='administrator' AND r.status='active';

INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key IN (
'dashboard.view','users.view','support.view','support.reply','notifications.manage','manage_support'
) WHERE r.slug='support' AND r.status='active';

INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key IN (
'dashboard.view','users.view','kyc.view','kyc.review','audit.view','manage_kyc'
) WHERE r.slug='compliance' AND r.status='active';

INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key IN (
'dashboard.view','users.view','accounts.view','funds.view','funds.process','audit.view','manage_accounts','manage_funding','view_logs'
) WHERE r.slug='finance' AND r.status='active';

INSERT INTO admin_role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key IN (
'dashboard.view','cms.view','cms.manage','manage_content'
) WHERE r.slug='content-manager' AND r.status='active';
