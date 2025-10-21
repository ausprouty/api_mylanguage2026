-- 1) i18n_clients
CREATE TABLE IF NOT EXISTS i18n_clients (
  client_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_code  VARCHAR(64)  NOT NULL,
  client_name  VARCHAR(255) NOT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_i18n_clients_code (client_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) i18n_resources
--    type: 'interface' or 'commonContent'
--    subject: 'app' (for interfaces) or 'hope','life','jvideo', etc.
--    variant: NULL or 'wsu','uom', etc.
CREATE TABLE IF NOT EXISTS i18n_resources (
  resource_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         ENUM('interface','commonContent') NOT NULL,
  subject      VARCHAR(128) NOT NULL,
  variant      VARCHAR(128) NULL,
  description  VARCHAR(255) NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_i18n_resources (type, subject, variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) i18n_strings (English master stored once; short stable hash as key)
CREATE TABLE IF NOT EXISTS i18n_strings (
  string_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id      BIGINT UNSIGNED NOT NULL,
  resource_id    BIGINT UNSIGNED NOT NULL,
  key_hash       CHAR(40) NOT NULL,   -- e.g., sha1(english_text [+ context])
  english_text   TEXT NOT NULL,
  developer_note TEXT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_i18n_strings_client
    FOREIGN KEY (client_id) REFERENCES i18n_clients(client_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT fk_i18n_strings_resource
    FOREIGN KEY (resource_id) REFERENCES i18n_resources(resource_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  UNIQUE KEY uk_i18n_strings (client_id, resource_id, key_hash),
  KEY ix_i18n_strings_resource (resource_id),
  KEY ix_i18n_strings_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) i18n_translations (one row per language per string)
CREATE TABLE IF NOT EXISTS i18n_translations (
  translation_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  string_id       BIGINT UNSIGNED NOT NULL,
  language_hl     VARCHAR(8)  NOT NULL,     -- e.g., 'eng00','mrt00'
  language_iso    VARCHAR(16) NULL,         -- e.g., 'en','hi'
  translated_text MEDIUMTEXT NOT NULL,
  status          ENUM('draft','machine','review','approved')
                  NOT NULL DEFAULT 'draft',
  source          ENUM('human','mt','import') NOT NULL DEFAULT 'human',
  translator      VARCHAR(128) NULL,
  reviewed_by     VARCHAR(128) NULL,
  posted          DATE NULL,                -- YYYY-MM-DD (your preferred format)
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_i18n_translations_string
    FOREIGN KEY (string_id) REFERENCES i18n_strings(string_id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  UNIQUE KEY uk_i18n_translations (string_id, language_hl),
  KEY ix_i18n_translations_lang (language_hl),
  KEY ix_i18n_translations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
