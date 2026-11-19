-- Phase 17 additive support/notification metadata. Apply once to the Phase 02 schema.
ALTER TABLE notifications
    ADD COLUMN related_type VARCHAR(40) NULL AFTER link,
    ADD COLUMN related_reference VARCHAR(80) NULL AFTER related_type,
    ADD UNIQUE KEY uq_notifications_user_event (user_id, related_type, related_reference);

ALTER TABLE support_tickets
    MODIFY COLUMN status ENUM('open','in_progress','waiting_client','resolved','closed') NOT NULL DEFAULT 'open',
    ADD UNIQUE KEY uq_support_ticket_owner (id,user_id),
    ADD COLUMN client_request_token CHAR(64) NULL AFTER status,
    ADD COLUMN resolved_at DATETIME NULL AFTER closed_at,
    ADD UNIQUE KEY uq_support_ticket_client_request (user_id, client_request_token);

ALTER TABLE support_messages
    ADD COLUMN is_read BOOLEAN NOT NULL DEFAULT FALSE AFTER created_at,
    ADD COLUMN client_request_token CHAR(64) NULL AFTER is_read,
    ADD UNIQUE KEY uq_support_message_ticket_owner (id,ticket_id),
    ADD UNIQUE KEY uq_support_message_client_request (ticket_id, client_request_token);

CREATE TABLE support_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attachment_reference VARCHAR(32) NOT NULL,
    original_filename VARCHAR(180) NOT NULL,
    stored_filename VARCHAR(100) NOT NULL,
    storage_path VARCHAR(500) NOT NULL COMMENT 'Private relative path, never a public URL',
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_attachment_reference (attachment_reference),
    UNIQUE KEY uq_support_attachment_message (message_id),
    KEY idx_support_attachment_ticket_owner (ticket_id,user_id),
    CONSTRAINT fk_support_attachment_message FOREIGN KEY (message_id,ticket_id) REFERENCES support_messages (id,ticket_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_support_attachment_ticket_owner FOREIGN KEY (ticket_id,user_id) REFERENCES support_tickets (id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_permissions (permission_key,description)
VALUES ('manage_support','Manage client support tickets and replies')
ON DUPLICATE KEY UPDATE description=VALUES(description);
