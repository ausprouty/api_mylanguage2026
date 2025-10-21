-- 009_update_translation_queue.sql
START TRANSACTION;

-- 0) Ensure the table exists (fresh envs)
CREATE TABLE IF NOT EXISTS translation_queue (
  id INT(11) NOT NULL AUTO_INCREMENT,
  targetLanguageCodeIso VARCHAR(10) NOT NULL,
  sourceText TEXT NOT NULL,
  status ENUM('queued','processing','failed') NOT NULL DEFAULT 'queued',
  lockedBy VARCHAR(64) DEFAULT NULL,
  lockedAt DATETIME DEFAULT NULL,
  attempts TINYINT(4) NOT NULL DEFAULT 0,
  runAfter DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  priority TINYINT(4) NOT NULL DEFAULT 0,
  queuedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1) Rename old snake_case columns to camelCase IF they still exist
-- target_lang -> targetLanguageCodeIso
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'target_lang');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN target_lang targetLanguageCodeIso VARCHAR(10) NOT NULL;',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- source_text -> sourceText
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'source_text');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN source_text sourceText TEXT NOT NULL;',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- locked_by -> lockedBy
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'locked_by');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN locked_by lockedBy VARCHAR(64) NULL;',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- locked_at -> lockedAt
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'locked_at');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN locked_at lockedAt DATETIME NULL;',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- run_after -> runAfter
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'run_after');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN run_after runAfter DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP();',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- queued_at -> queuedAt
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE()
             AND table_name   = 'translation_queue'
             AND column_name  = 'queued_at');
SET @sql := IF(@c=1,
  'ALTER TABLE translation_queue CHANGE COLUMN queued_at queuedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP();',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Drop obsolete indexes IF they exist
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='uq_target_text');
SET @sql := IF(@idx=1, 'ALTER TABLE translation_queue DROP INDEX uq_target_text;', 'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='uq_lang_text');
SET @sql := IF(@idx=1, 'ALTER TABLE translation_queue DROP INDEX uq_lang_text;', 'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idx_pick');
SET @sql := IF(@idx=1, 'ALTER TABLE translation_queue DROP INDEX idx_pick;', 'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idx_locked');
SET @sql := IF(@idx=1, 'ALTER TABLE translation_queue DROP INDEX idx_locked;', 'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idx_lang');
SET @sql := IF(@idx=1, 'ALTER TABLE translation_queue DROP INDEX idx_lang;', 'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Add desired indexes IF missing
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='uqTargetText');
SET @sql := IF(@idx=0,
  'ALTER TABLE translation_queue ADD UNIQUE KEY uqTargetText (targetLanguageCodeIso, sourceText(255));',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idxPick');
SET @sql := IF(@idx=0,
  'ALTER TABLE translation_queue ADD KEY idxPick (status, runAfter, priority, id);',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idxLocked');
SET @sql := IF(@idx=0,
  'ALTER TABLE translation_queue ADD KEY idxLocked (lockedAt);',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='translation_queue' AND index_name='idxLang');
SET @sql := IF(@idx=0,
  'ALTER TABLE translation_queue ADD KEY idxLang (targetLanguageCodeIso);',
  'DO 0;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
