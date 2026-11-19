-- Phase 07 only: short-lived, privacy-preserving login/reset request buckets.
-- bucket_hash is a SHA-256 of action plus identity and remote IP; no email or IP is stored.
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_auth_rate_limits_bucket_time (bucket_hash, attempted_at),
    KEY idx_auth_rate_limits_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
