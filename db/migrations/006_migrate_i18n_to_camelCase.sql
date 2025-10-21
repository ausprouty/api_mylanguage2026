START TRANSACTION;

-- ===== i18n_clients =====
ALTER TABLE `i18n_clients`
  CHANGE COLUMN `client_id`   `clientId`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN `client_code` `clientCode` VARCHAR(64)  NOT NULL,
  CHANGE COLUMN `client_name` `clientName` VARCHAR(255) NOT NULL,
  CHANGE COLUMN `is_active`   `isActive`   TINYINT(1)   NOT NULL DEFAULT 1,
  CHANGE COLUMN `created_at`  `createdAt`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN `updated_at`  `updatedAt`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP;

-- keep existing unique name or rename if you like
ALTER TABLE `i18n_clients`
  DROP INDEX `uk_i18n_clients_code`,
  ADD UNIQUE KEY `uk_i18nClients_clientCode` (`clientCode`);

-- ===== i18n_resources =====
ALTER TABLE `i18n_resources`
  CHANGE COLUMN `resource_id` `resourceId` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN `type`        `type`       ENUM('interface','commonContent') NOT NULL,
  CHANGE COLUMN `subject`     `subject`    VARCHAR(128) NOT NULL,
  CHANGE COLUMN `variant`     `variant`    VARCHAR(128) NULL,
  CHANGE COLUMN `description` `description` VARCHAR(255) NULL,
  CHANGE COLUMN `created_at`  `createdAt`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN `updated_at`  `updatedAt`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `i18n_resources`
  DROP INDEX `uk_i18n_resources`,
  ADD UNIQUE KEY `uk_i18nResources_typeSubjectVariant` (`type`,`subject`,`variant`);

-- ===== i18n_strings =====
-- drop FKs first (names might differ in your DB; adjust if needed)
ALTER TABLE `i18n_strings` DROP FOREIGN KEY `fk_i18n_strings_client`;
ALTER TABLE `i18n_strings` DROP FOREIGN KEY `fk_i18n_strings_resource`;

ALTER TABLE `i18n_strings`
  CHANGE COLUMN `string_id`      `stringId`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN `client_id`      `clientId`      BIGINT UNSIGNED NOT NULL,
  CHANGE COLUMN `resource_id`    `resourceId`    BIGINT UNSIGNED NOT NULL,
  CHANGE COLUMN `key_hash`       `keyHash`       CHAR(40) NOT NULL,
  CHANGE COLUMN `english_text`   `englishText`   TEXT NOT NULL,
  CHANGE COLUMN `developer_note` `developerNote` TEXT NULL,
  CHANGE COLUMN `is_active`      `isActive`      TINYINT(1) NOT NULL DEFAULT 1,
  CHANGE COLUMN `created_at`     `createdAt`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN `updated_at`     `updatedAt`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP;

-- indexes
ALTER TABLE `i18n_strings`
  DROP INDEX `uk_i18n_strings`,
  DROP INDEX `ix_i18n_strings_resource`,
  DROP INDEX `ix_i18n_strings_client`,
  ADD UNIQUE KEY `uk_i18nStrings_clientResourceKeyHash` (`clientId`,`resourceId`,`keyHash`),
  ADD KEY `ix_i18nStrings_resourceId` (`resourceId`),
  ADD KEY `ix_i18nStrings_clientId` (`clientId`);

-- re-add FKs with camelCase column refs
ALTER TABLE `i18n_strings`
  ADD CONSTRAINT `fk_i18nStrings_clientId`
    FOREIGN KEY (`clientId`) REFERENCES `i18n_clients`(`clientId`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_i18nStrings_resourceId`
    FOREIGN KEY (`resourceId`) REFERENCES `i18n_resources`(`resourceId`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- ===== i18n_translations =====
-- drop FK first
ALTER TABLE `i18n_translations` DROP FOREIGN KEY `fk_i18n_translations_string`;

ALTER TABLE `i18n_translations`
  CHANGE COLUMN `translation_id`  `translationId`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN `string_id`       `stringId`       BIGINT UNSIGNED NOT NULL,
  CHANGE COLUMN `language_hl`     `languageCodeHL` VARCHAR(8)  NOT NULL,
  CHANGE COLUMN `language_iso`    `languageCodeIso`    VARCHAR(16) NULL,
  CHANGE COLUMN `translated_text` `translatedText` MEDIUMTEXT NOT NULL,
  CHANGE COLUMN `status`          `status`
         ENUM('draft','machine','review','approved') NOT NULL DEFAULT 'draft',
  CHANGE COLUMN `source`          `source`
         ENUM('human','mt','import') NOT NULL DEFAULT 'human',
  CHANGE COLUMN `translator`      `translator`  VARCHAR(128) NULL,
  CHANGE COLUMN `reviewed_by`     `reviewedBy`  VARCHAR(128) NULL,
  -- 'posted' is fine as a single-word field in your schema standard
  CHANGE COLUMN `created_at`      `createdAt`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHANGE COLUMN `updated_at`      `updatedAt`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP;

-- indexes
ALTER TABLE `i18n_translations`
  DROP INDEX `uk_i18n_translations`,
  DROP INDEX `ix_i18n_translations_lang`,
  DROP INDEX `ix_i18n_translations_status`,
  ADD UNIQUE KEY `uk_i18nTranslations_stringId_languageCodeHL` (`stringId`,`languageCodeHL`),
  ADD KEY `ix_i18nTranslations_languageCodeHL` (`languageCodeHL`),
  ADD KEY `ix_i18nTranslations_status` (`status`);

-- re-add FK
ALTER TABLE `i18n_translations`
  ADD CONSTRAINT `fk_i18nTranslations_stringId`
    FOREIGN KEY (`stringId`) REFERENCES `i18n_strings`(`stringId`)
    ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;
