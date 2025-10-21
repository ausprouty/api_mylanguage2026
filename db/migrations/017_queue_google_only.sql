START TRANSACTION;

-- uqByScopeKeyHashG
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'uqByScopeKeyHashG'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByScopeKeyHashG
       (clientCode, resourceType, subject, variant, sourceKeyHash,
        targetLanguageCodeGoogle)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uqByStringIdG
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'uqByStringIdG'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByStringIdG
       (sourceStringId, targetLanguageCodeGoogle)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- uqByScopeG
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'uqByScopeG'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD UNIQUE KEY uqByScopeG
       (clientCode, resourceType, subject, variant, stringKey,
        targetLanguageCodeGoogle, sourceText(255))',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- idxPick
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'idxPick'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD KEY idxPick (status, runAfter, priority, id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- idxLocked
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'idxLocked'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD KEY idxLocked (lockedAt)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- idxSourceString
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'idxSourceString'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD KEY idxSourceString (sourceStringId)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- idxKeyHash
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'idxKeyHash'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD KEY idxKeyHash (sourceKeyHash)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- idxLangG
SET @exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_translation_queue'
    AND index_name   = 'idxLangG'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE i18n_translation_queue
     ADD KEY idxLangG (targetLanguageCodeGoogle)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
