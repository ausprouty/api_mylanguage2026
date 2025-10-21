START TRANSACTION;

SET @db := DATABASE();

/* 1) Add languageCodeGoogle if missing */
SET @col := (
  SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=@db AND table_name='i18n_translations' AND column_name='languageCodeGoogle'
);
SET @sql := IF(@col=0,
  'ALTER TABLE i18n_translations ADD COLUMN languageCodeGoogle VARCHAR(16) NULL AFTER stringId',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 2) Backfill languageCodeGoogle (prefer hl_languages.google, else existing ISO/HL), lowercased */
UPDATE i18n_translations t
LEFT JOIN hl_languages l
       ON l.languageCodeHL  = t.languageCodeHL
       OR l.languageCodeIso = t.languageCodeIso
SET t.languageCodeGoogle = LOWER(COALESCE(
      NULLIF(l.languageCodeGoogle, ''),
      NULLIF(t.languageCodeIso, ''),
      NULLIF(t.languageCodeHL, '')
    ))
WHERE t.languageCodeGoogle IS NULL OR t.languageCodeGoogle = '';

/* 3) Deduplicate by (stringId, languageCodeGoogle) keeping newest */
DELETE t1 FROM i18n_translations t1
JOIN i18n_translations t2
  ON t1.stringId = t2.stringId
 AND t1.languageCodeGoogle = t2.languageCodeGoogle
 AND (t1.updatedAt < t2.updatedAt
      OR (t1.updatedAt = t2.updatedAt AND t1.translationId < t2.translationId));

/* 4) Ensure unique key on (stringId, languageCodeGoogle) */
SET @u := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=@db AND table_name='i18n_translations' AND index_name='uq_translations_string_google'
);
SET @sql := IF(@u=0,
  'ALTER TABLE i18n_translations ADD UNIQUE KEY uq_translations_string_google (stringId, languageCodeGoogle)',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 5) OPTIONAL: drop old uniques on HL/ISO to avoid future conflicts (same table only) */
SET @u_hl := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=@db AND table_name='i18n_translations' AND index_name='uk_i18nTranslations_stringId_languageCodeHL'
);
SET @sql := IF(@u_hl>0,
  'ALTER TABLE i18n_translations DROP INDEX uk_i18nTranslations_stringId_languageCodeHL',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @u_iso := (
  SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema=@db AND table_name='i18n_translations' AND index_name='uq_string_iso'
);
SET @sql := IF(@u_iso>0,
  'ALTER TABLE i18n_translations DROP INDEX uq_string_iso',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 6) OPTIONAL: relax HL/ISO (keep columns for future manual workflows) */
ALTER TABLE i18n_translations
  MODIFY languageCodeHL  VARCHAR(8)  NULL,
  MODIFY languageCodeIso VARCHAR(32) NULL;

COMMIT;
