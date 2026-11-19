-- Phase 16: additive demo verification workflow metadata; no table recreation.
ALTER TABLE kyc_submissions
    ADD COLUMN submission_reference VARCHAR(32) NULL AFTER id,
    ADD COLUMN submitted_at DATETIME NULL AFTER reviewed_at,
    ADD COLUMN profile_snapshot JSON NULL AFTER submitted_at,
    ADD COLUMN reviewer_message VARCHAR(500) NULL AFTER rejection_reason,
    ADD COLUMN internal_review_note VARCHAR(1000) NULL AFTER reviewer_message,
    ADD COLUMN resubmission_category ENUM('identity','proof_of_address','profile') NULL AFTER reviewer_message,
    MODIFY status ENUM('not_submitted','draft','pending','submitted','in_review','verified','rejected','resubmission_required') NOT NULL DEFAULT 'not_submitted',
    ADD UNIQUE KEY uq_kyc_submission_reference (submission_reference);

UPDATE kyc_submissions SET status='submitted' WHERE status='pending';
ALTER TABLE kyc_submissions
    MODIFY status ENUM('not_submitted','draft','submitted','in_review','verified','rejected','resubmission_required') NOT NULL DEFAULT 'not_submitted';

ALTER TABLE kyc_documents
    ADD COLUMN document_reference VARCHAR(40) NULL AFTER id,
    ADD COLUMN document_category ENUM('identity','proof_of_address') NOT NULL DEFAULT 'identity' AFTER user_id,
    ADD COLUMN sha256 CHAR(64) NULL AFTER file_size,
    MODIFY document_type ENUM('passport','national_id','driver_licence','utility_bill','bank_statement','government_letter','other') NOT NULL,
    MODIFY status ENUM('pending','accepted','rejected','superseded') NOT NULL DEFAULT 'pending',
    ADD UNIQUE KEY uq_kyc_document_reference (document_reference);
