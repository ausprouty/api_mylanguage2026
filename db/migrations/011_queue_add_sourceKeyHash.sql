-- 011_queue_add_sourceKeyHash.sql
-- Add sourceKeyHash (CHAR(40)), backfill, and switch UNIQUE to use the hash.

START TRANSACTION;

-- 1) Add column if missing
SET @c := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND column_name  = 'sourceKeyHash'
);
SET @sql := IF(@c=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN sourceKeyHash CHAR(40) NOT NULL DEFAULT '''' AFTER stringKey;',
  'SELECT 1;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Backfill empty hashes
-- Prefer hashing stringKey when present (to match i18n_strings.keyHash),
-- otherwise fall back to hashing full sourceText.
UPDATE i18n_translation_queue
   SET sourceKeyHash = SHA1(CASE WHEN stringKey <> '' THEN stringKey ELSE sourceText END)
 WHERE (sourceKeyHash IS NULL OR sourceKeyHash = '');

-- 3) Drop old scope+text UNIQUE if present (was using sourceText(255))
SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='i18n_translation_queue'
    AND index_name='uqByScope'
);
SET @sql := IF(@idx=1,
  'ALTER TABLE i18n_translation_queue DROP INDEX uqByScope;',
  'SELECT 1;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Ensure supporting index on the hash
SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='i18n_translation_queue'
    AND index_name='idxKeyHash'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxKeyHash (sourceKeyHash);',
  'SELECT 1;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) Add new scope+hash UNIQUE (idempotent)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=DATABASE()
    AND table_name='i18n_translation_queue'
    AND index_name='uqByScopeKeyHash'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE i18n_translation_queue
       ADD UNIQUE KEY uqByScopeKeyHash
         (clientCode, resourceType, subject, variant, sourceKeyHash, targetLanguageCodeIso);',
  'SELECT 1;');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
