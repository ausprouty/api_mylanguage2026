START TRANSACTION;

-- 1) Add UNIQUE (clientId, resourceId, keyHash) only if missing
SET @exists_ux := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND index_name   = 'ux_client_res_hash'
);
SET @sql := IF(@exists_ux = 0,
  'ALTER TABLE i18n_strings
     ADD UNIQUE KEY ux_client_res_hash (clientId, resourceId, keyHash)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Ensure PRIMARY KEY on stringId if there is no PK yet
SET @has_pk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND constraint_type = 'PRIMARY KEY'
);
SET @sql := IF(@has_pk = 0,
  'ALTER TABLE i18n_strings
     ADD PRIMARY KEY (stringId)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Ensure stringId is indexed if table already had a different PK
SET @idx_sid := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND column_name  = 'stringId'
);
SET @has_pk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND constraint_type = 'PRIMARY KEY'
);

-- If there is a PK (maybe on other cols) and stringId has no index, add one
SET @exists_ux_sid := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND index_name   = 'ux_strings_sid'
);
SET @sql := IF(@has_pk > 0 AND @idx_sid = 0 AND @exists_ux_sid = 0,
  'ALTER TABLE i18n_strings
     ADD UNIQUE KEY ux_strings_sid (stringId)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Make stringId AUTO_INCREMENT if it isn't already
SET @is_auto := (
  SELECT CASE WHEN UPPER(EXTRA) LIKE '%AUTO_INCREMENT%' THEN 1 ELSE 0 END
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'i18n_strings'
    AND column_name  = 'stringId'
  LIMIT 1
);
SET @sql := IF(@is_auto = 0,
  'ALTER TABLE i18n_strings
     MODIFY stringId BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;
