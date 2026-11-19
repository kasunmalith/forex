-- Phase 21 additive CMS publishing and slug history support.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' AND COLUMN_NAME='published_at')=0,'ALTER TABLE pages ADD COLUMN published_at DATETIME NULL AFTER status','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' AND COLUMN_NAME='version')=0,'ALTER TABLE pages ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER meta_description','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='articles' AND COLUMN_NAME='version')=0,'ALTER TABLE articles ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='articles' AND COLUMN_NAME='image_alt')=0,'ALTER TABLE articles ADD COLUMN image_alt VARCHAR(255) NULL AFTER featured_image','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='faqs' AND COLUMN_NAME='version')=0,'ALTER TABLE faqs ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS cms_slug_redirects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('article','page') NOT NULL,
    old_slug VARCHAR(240) NOT NULL,
    target_slug VARCHAR(240) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cms_slug_redirect (entity_type, old_slug),
    KEY idx_cms_slug_target (entity_type, target_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key,setting_value,setting_type,is_public) VALUES
('default_seo_title','MultiForex | Demo Global Trading Platform','string',1),
('default_seo_description','Explore simulated markets and demo trading tools with virtual funds on MultiForex.','text',1),
('footer_copyright','Demo environment','string',1),
('social_links','{}','json',1)
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
