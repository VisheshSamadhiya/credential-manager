CREATE TABLE IF NOT EXISTS credential_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    credential_id INT UNSIGNED NOT NULL,

    original_filename VARCHAR(255) NOT NULL,
    s3_key VARCHAR(1024) NOT NULL,
    content_type VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,

    uploaded_by INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_credential_attachment_s3_key (s3_key),

    KEY idx_credential_attachments_credential_id (credential_id),
    KEY idx_credential_attachments_uploaded_by (uploaded_by),

    CONSTRAINT fk_attachment_credential
        FOREIGN KEY (credential_id)
        REFERENCES credentials(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_attachment_uploaded_by
        FOREIGN KEY (uploaded_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_uca1400_ai_ci;
