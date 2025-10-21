-- 010_i18n_translation_queue.sql
-- Rename translation_queue -> i18n_translation_queue, normalize columns,
-- add i18n context, and ensure indexes. Idempotent for MariaDB 10.4.

START TRANSACTION;

-- A) Rename table if needed
SET @has_new := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'i18n_translation_queue'
);
SET @has_old := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'translation_queue'
);
SET @sql := IF(@has_new = 0 AND @has_old = 1,
  'RENAME TABLE translation_queue TO i18n_translation_queue;',
  'SELECT 1;'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- B) Column renames (handle legacy snake_case if present)
-- target_lang -> targetLanguageCodeIso
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='target_lang');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN target_lang targetLanguageCodeIso VARCHAR(10) NOT NULL;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- source_text -> sourceText
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='source_text');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN source_text sourceText TEXT NOT NULL;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- locked_by -> lockedBy
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='locked_by');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN locked_by lockedBy VARCHAR(64) NULL;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- locked_at -> lockedAt
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='locked_at');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN locked_at lockedAt DATETIME NULL;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- run_after -> runAfter
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='run_after');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN run_after runAfter DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP();',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- queued_at -> queuedAt
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE()
             AND table_name='i18n_translation_queue'
             AND column_name='queued_at');
SET @sql := IF(@c=1,
  'ALTER TABLE i18n_translation_queue CHANGE COLUMN queued_at queuedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP();',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- C) Ensure i18n context columns exist (add individually)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='sourceStringId');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN sourceStringId BIGINT UNSIGNED NULL AFTER id;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='sourceLanguageCodeIso');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN sourceLanguageCodeIso VARCHAR(10) NOT NULL DEFAULT ''en'' AFTER sourceStringId;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='clientCode');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN clientCode VARCHAR(16) NOT NULL DEFAULT '''' AFTER sourceLanguageCodeIso;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='resourceType');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN resourceType VARCHAR(32) NOT NULL DEFAULT '''' AFTER clientCode;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='subject');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN subject VARCHAR(64) NOT NULL DEFAULT '''' AFTER resourceType;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='variant');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN variant VARCHAR(32) NOT NULL DEFAULT '''' AFTER subject;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND column_name='stringKey');
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN stringKey VARCHAR(191) NOT NULL DEFAULT '''' AFTER variant;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- D) Indexes: drop obsolete names if present
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='uq_target_text');
SET @sql := IF(@idx=1,
  'ALTER TABLE i18n_translation_queue DROP INDEX uq_target_text;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='uq_lang_text');
SET @sql := IF(@idx=1,
  'ALTER TABLE i18n_translation_queue DROP INDEX uq_lang_text;',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Ensure required indexes exist
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='idxPick');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxPick (status, runAfter, priority, id);',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='idxLocked');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxLocked (lockedAt);',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='idxLang');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxLang (targetLanguageCodeIso);',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='idxSourceString');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxSourceString (sourceStringId);',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Unique by (sourceStringId, lang) when available
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='uqByStringId');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD UNIQUE KEY uqByStringId (sourceStringId, targetLanguageCodeIso);',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Fallback uniqueness by scope + text prefix
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='i18n_translation_queue' AND index_name='uqByScope');
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByScope (clientCode, resourceType, subject, variant, stringKey,
                               targetLanguageCodeIso, sourceText(255));',
  'SELECT 1;'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
