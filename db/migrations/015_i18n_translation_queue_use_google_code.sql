START TRANSACTION;

SET @db := DATABASE();

/* 1) Add Google columns if missing (nullable during backfill) */
SET @has_src_g := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='sourceLanguageCodeGoogle'
);
SET @sql := IF(@has_src_g=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN sourceLanguageCodeGoogle VARCHAR(16) NULL AFTER sourceLanguageCodeIso',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_tgt_g := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='targetLanguageCodeGoogle'
);
SET @sql := IF(@has_tgt_g=0,
  'ALTER TABLE i18n_translation_queue ADD COLUMN targetLanguageCodeGoogle VARCHAR(16) NULL AFTER targetLanguageCodeIso',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 2) Backfill Google codes from hl_languages (prefer google, else existing ISO) */
UPDATE i18n_translation_queue q
LEFT JOIN hl_languages ls
  ON (ls.languageCodeIso COLLATE utf8mb4_unicode_ci
      = q.sourceLanguageCodeIso COLLATE utf8mb4_unicode_ci)
LEFT JOIN hl_languages lt
  ON (lt.languageCodeIso COLLATE utf8mb4_unicode_ci
      = q.targetLanguageCodeIso COLLATE utf8mb4_unicode_ci)
SET
  q.sourceLanguageCodeGoogle = LOWER(COALESCE(NULLIF(ls.languageCodeGoogle,''), NULLIF(q.sourceLanguageCodeIso,''))),
  q.targetLanguageCodeGoogle = LOWER(COALESCE(NULLIF(lt.languageCodeGoogle,''), NULLIF(q.targetLanguageCodeIso,'')))
WHERE (q.sourceLanguageCodeGoogle IS NULL OR q.sourceLanguageCodeGoogle = '')
   OR (q.targetLanguageCodeGoogle IS NULL OR q.targetLanguageCodeGoogle = '');

/* 3) Deduplicate potential collisions BEFORE adding uniques */

/* 3a) Scope + keyHash + targetGoogle */
DELETE q1 FROM i18n_translation_queue q1
JOIN i18n_translation_queue q2
  ON q1.id < q2.id
 AND q1.clientCode = q2.clientCode
 AND q1.resourceType = q2.resourceType
 AND q1.subject = q2.subject
 AND q1.variant = q2.variant
 AND q1.sourceKeyHash = q2.sourceKeyHash
 AND q1.targetLanguageCodeGoogle = q2.targetLanguageCodeGoogle;

/* 3b) Scope + stringKey + sourceText(255) + targetGoogle */
DELETE q1 FROM i18n_translation_queue q1
JOIN i18n_translation_queue q2
  ON q1.id < q2.id
 AND q1.clientCode = q2.clientCode
 AND q1.resourceType = q2.resourceType
 AND q1.subject = q2.subject
 AND q1.variant = q2.variant
 AND q1.stringKey = q2.stringKey
 AND LEFT(q1.sourceText,255) = LEFT(q2.sourceText,255)
 AND q1.targetLanguageCodeGoogle = q2.targetLanguageCodeGoogle;

/* 3c) sourceStringId + targetGoogle */
DELETE q1 FROM i18n_translation_queue q1
JOIN i18n_translation_queue q2
  ON q1.id < q2.id
 AND COALESCE(q1.sourceStringId,0) = COALESCE(q2.sourceStringId,0)
 AND q1.targetLanguageCodeGoogle = q2.targetLanguageCodeGoogle;

/* 4) Add Google-based indexes (idempotent) */
SET @has_u1 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeKeyHashG');
SET @sql := IF(@has_u1=0,
  'ALTER TABLE i18n_translation_queue ADD UNIQUE KEY uqByScopeKeyHashG (clientCode,resourceType,subject,variant,sourceKeyHash,targetLanguageCodeGoogle)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_u2 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeG');
SET @sql := IF(@has_u2=0,
  'ALTER TABLE i18n_translation_queue ADD UNIQUE KEY uqByScopeG (clientCode,resourceType,subject,variant,stringKey,targetLanguageCodeGoogle,sourceText(255))',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_u3 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByStringIdG');
SET @sql := IF(@has_u3=0,
  'ALTER TABLE i18n_translation_queue ADD UNIQUE KEY uqByStringIdG (sourceStringId,targetLanguageCodeGoogle)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_ix := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='idxLangG');
SET @sql := IF(@has_ix=0,
  'ALTER TABLE i18n_translation_queue ADD KEY idxLangG (targetLanguageCodeGoogle)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 5) Make Google columns NOT NULL (with sensible default for source) */
ALTER TABLE i18n_translation_queue
  MODIFY sourceLanguageCodeGoogle VARCHAR(16) NOT NULL DEFAULT 'en',
  MODIFY targetLanguageCodeGoogle VARCHAR(16) NOT NULL;

/* 6) OPTIONAL: drop old ISO-based uniques (keep columns for now) */
SET @has_old1 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeKeyHash');
SET @sql := IF(@has_old1>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByScopeKeyHash', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_old2 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScope');
SET @sql := IF(@has_old2>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByScope', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_old3 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByStringId');
SET @sql := IF(@has_old3>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByStringId', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_old4 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='idxLang');
SET @sql := IF(@has_old4>0, 'ALTER TABLE i18n_translation_queue DROP INDEX idxLang', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

// (Optionally later) drop ISO columns after code is updated
ALTER TABLE i18n_translation_queue
  DROP COLUMN sourceLanguageCodeIso,
  DROP COLUMN targetLanguageCodeIso;


ALTER TABLE i18n_translation_queue
  MODIFY sourceLanguageCodeGoogle VARCHAR(16)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  MODIFY targetLanguageCodeGoogle VARCHAR(16)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;


COMMIT;
