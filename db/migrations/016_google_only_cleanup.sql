START TRANSACTION;

SET @db := DATABASE();

/******************************************************************
 * A) i18n_translation_queue  — remove ISO, keep Google only
 ******************************************************************/

/* A1) Ensure Google columns exist and are populated */
SET @has_src_g := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='sourceLanguageCodeGoogle'
);
SET @sql := IF(@has_src_g=0,
  'ALTER TABLE i18n_translation_queue
     ADD COLUMN sourceLanguageCodeGoogle VARCHAR(16) NULL
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER sourceLanguageCodeIso',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_tgt_g := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='targetLanguageCodeGoogle'
);
SET @sql := IF(@has_tgt_g=0,
  'ALTER TABLE i18n_translation_queue
     ADD COLUMN targetLanguageCodeGoogle VARCHAR(16) NULL
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER targetLanguageCodeIso',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
/* A2) Backfill Google codes only if ISO columns still exist (collation-safe) */

-- Check column presence once
SET @has_src_iso := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='sourceLanguageCodeIso'
);
SET @has_tgt_iso := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='targetLanguageCodeIso'
);

-- Backfill sourceLanguageCodeGoogle from sourceLanguageCodeIso (if column exists)
SET @sql := IF(@has_src_iso=1,
  'UPDATE i18n_translation_queue q
     LEFT JOIN hl_languages ls
       ON (ls.languageCodeIso COLLATE utf8mb4_unicode_ci
           = q.sourceLanguageCodeIso COLLATE utf8mb4_unicode_ci)
     SET q.sourceLanguageCodeGoogle = LOWER(COALESCE(NULLIF(ls.languageCodeGoogle, ''''),
                                                     NULLIF(q.sourceLanguageCodeIso, '''')))
   WHERE q.sourceLanguageCodeGoogle IS NULL OR q.sourceLanguageCodeGoogle = '''' ',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill targetLanguageCodeGoogle from targetLanguageCodeIso (if column exists)
SET @sql := IF(@has_tgt_iso=1,
  'UPDATE i18n_translation_queue q
     LEFT JOIN hl_languages lt
       ON (lt.languageCodeIso COLLATE utf8mb4_unicode_ci
           = q.targetLanguageCodeIso COLLATE utf8mb4_unicode_ci)
     SET q.targetLanguageCodeGoogle = LOWER(COALESCE(NULLIF(lt.languageCodeGoogle, ''''),
                                                     NULLIF(q.targetLanguageCodeIso, '''')))
   WHERE q.targetLanguageCodeGoogle IS NULL OR q.targetLanguageCodeGoogle = '''' ',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


/* A3) Make Google columns NOT NULL, keep sensible default on source */
ALTER TABLE i18n_translation_queue
  MODIFY sourceLanguageCodeGoogle VARCHAR(16)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  MODIFY targetLanguageCodeGoogle VARCHAR(16)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

/* A4) Ensure Google-based uniques exist */
SET @u1 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeKeyHashG');
SET @sql := IF(@u1=0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByScopeKeyHashG (clientCode,resourceType,subject,variant,sourceKeyHash,targetLanguageCodeGoogle)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @u2 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeG');
SET @sql := IF(@u2=0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByScopeG (clientCode,resourceType,subject,variant,stringKey,targetLanguageCodeGoogle,sourceText(255))',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @u3 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByStringIdG');
SET @sql := IF(@u3=0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByStringIdG (sourceStringId,targetLanguageCodeGoogle)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* A5) Drop ISO-based indexes if they still exist */
SET @d1 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScopeKeyHash');
SET @sql := IF(@d1>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByScopeKeyHash', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @d2 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByScope');
SET @sql := IF(@d2>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByScope', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @d3 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='uqByStringId');
SET @sql := IF(@d3>0, 'ALTER TABLE i18n_translation_queue DROP INDEX uqByStringId', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @d4 := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND index_name='idxLang');
SET @sql := IF(@d4>0, 'ALTER TABLE i18n_translation_queue DROP INDEX idxLang', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* A6) Finally drop ISO columns (now unused) */
SET @has_src_iso := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='sourceLanguageCodeIso'
);
SET @sql := IF(@has_src_iso>0,
  'ALTER TABLE i18n_translation_queue DROP COLUMN sourceLanguageCodeIso',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_tgt_iso := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translation_queue' AND column_name='targetLanguageCodeIso'
);
SET @sql := IF(@has_tgt_iso>0,
  'ALTER TABLE i18n_translation_queue DROP COLUMN targetLanguageCodeIso',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


/******************************************************************
 * B) i18n_translations — drop HL/ISO, keep Google only
 ******************************************************************/

/* B1) Ensure Google unique exists */
SET @ug := (SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translations' AND index_name='uq_translations_string_google');
SET @sql := IF(@ug=0,
  'ALTER TABLE i18n_translations
     ADD UNIQUE KEY uq_translations_string_google (stringId, languageCodeGoogle)',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* B2) Drop all indexes that reference HL/ISO if present */
SET @drop_list := (
  SELECT GROUP_CONCAT(DISTINCT CONCAT('DROP INDEX `',index_name,'`') SEPARATOR ', ')
  FROM information_schema.statistics
  WHERE table_schema=@db AND table_name='i18n_translations'
    AND index_name IN ('uk_i18nTranslations_stringId_languageCodeHL','uq_string_iso',
                       'idx_iso','idx_hl','ix_translations_string_langHL','ix_translations_string_langIso')
);
SET @sql := IF(@drop_list IS NOT NULL,
  CONCAT('ALTER TABLE i18n_translations ', @drop_list),
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* B3) Drop columns HL/ISO if they still exist */
SET @has_hl := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translations' AND column_name='languageCodeHL'
);
SET @sql := IF(@has_hl>0,
  'ALTER TABLE i18n_translations DROP COLUMN languageCodeHL',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_iso := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema=@db AND table_name='i18n_translations' AND column_name='languageCodeIso'
);
SET @sql := IF(@has_iso>0,
  'ALTER TABLE i18n_translations DROP COLUMN languageCodeIso',
  'SELECT 1'
); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


/******************************************************************
 * C) i18n_resolved_bundle — switch HL → Google (BASE TABLE only)
 ******************************************************************/
-- Detect existence and type
SET @tbl_exists := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_resolved_bundle'
);
SET @is_base := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_resolved_bundle'
    AND TABLE_TYPE   = 'BASE TABLE'
);

-- Columns present?
SET @has_hl_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_resolved_bundle'
    AND column_name  = 'languageCodeHL'
);
SET @has_g_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_resolved_bundle'
    AND column_name  = 'languageCodeGoogle'
);

-- Add Google column if it's a BASE TABLE and missing
SET @sql := IF(@tbl_exists=1 AND @is_base=1 AND @has_g_col=0,
  'ALTER TABLE i18n_resolved_bundle
     ADD COLUMN languageCodeGoogle VARCHAR(16)
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER languageCodeHL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill Google from HL via hl_languages (only if BASE TABLE and HL exists)
SET @sql := IF(@tbl_exists=1 AND @is_base=1 AND @has_hl_col=1,
  'UPDATE i18n_resolved_bundle b
     LEFT JOIN hl_languages l
       ON (l.languageCodeHL COLLATE utf8mb4_unicode_ci
           = b.languageCodeHL COLLATE utf8mb4_unicode_ci)
    SET b.languageCodeGoogle = LOWER(NULLIF(l.languageCodeGoogle, ''''))
  WHERE (b.languageCodeGoogle IS NULL OR b.languageCodeGoogle = '''')',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Make NOT NULL after backfill (if BASE TABLE and Google col exists)
SET @sql := IF(@tbl_exists=1 AND @is_base=1 AND @has_g_col=0,
  'ALTER TABLE i18n_resolved_bundle
     MODIFY languageCodeGoogle VARCHAR(16)
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Drop old HL column only if BASE TABLE
SET @sql := IF(@tbl_exists=1 AND @is_base=1 AND @has_hl_col=1,
  'ALTER TABLE i18n_resolved_bundle DROP COLUMN languageCodeHL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
