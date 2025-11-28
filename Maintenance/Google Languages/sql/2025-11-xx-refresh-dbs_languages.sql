-- ======================================================================
-- refresh-dbs-languages.sql
--
-- Purpose
--   Populate dbs_languages with languages that:
--     1) Have a complete Bible in text form:
--          - either collectionCode = 'C'
--          - or both 'OT' and 'NT' present (any text formats)
--     2) Have a non-empty languageCodeGoogle in hl_languages.
--
--   For these languages we store:
--     - languageCodeHL
--     - collectionCode = 'C'   (treat as complete Bible)
--     - format         = 'text'
--
-- Usage
--   1) Run the PREVIEW (section 3) to see what will be inserted.
--   2) If satisfied, run the TRUNCATE + INSERT (section 4).
--   3) Optionally run sanity checks (section 5).
--
-- Requirements
--   - bibles table with:
--       languageCodeHL, collectionCode, format
--   - hl_languages with languageCodeHL and languageCodeGoogle
--   - dbs_languages table as:
--       (languageCodeHL, collectionCode, format)
-- ======================================================================

-- ----------------------------------------------------------------------
-- 1. Ensure dbs_languages table exists (non-destructive)
-- ----------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dbs_languages (
  languageCodeHL  VARCHAR(10) NOT NULL,
  collectionCode  CHAR(8)     NOT NULL COMMENT
    'Is this ''C''omplete Bible or just ''NT''',
  format          ENUM('text','link') NOT NULL COMMENT
    'How Bible Text is displayed',
  PRIMARY KEY (languageCodeHL, collectionCode, format)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3;

-- ----------------------------------------------------------------------
-- 2. Helper: aggregate Bible coverage per language
--
--   We look ONLY at text formats:
--     - format = 'text'
--     - format LIKE 'text_%'
--     - format = 'text_json'
--
--   We ignore all formats starting with 'audio' or 'video'.
--
--   For each languageCodeHL we compute:
--     has_C  = 1 if any text Bible has collectionCode = 'C'
--     has_OT = 1 if any text Bible has collectionCode = 'OT'
--     has_NT = 1 if any text Bible has collectionCode = 'NT'
-- ----------------------------------------------------------------------
-- Optional: inspect the aggregate alone
-- SELECT *
-- FROM (
--   SELECT
--     b.languageCodeHL,
--     MAX(CASE WHEN b.collectionCode = 'C'
--              THEN 1 ELSE 0 END) AS has_C,
--     MAX(CASE WHEN b.collectionCode = 'OT'
--              THEN 1 ELSE 0 END) AS has_OT,
--     MAX(CASE WHEN b.collectionCode = 'NT'
--              THEN 1 ELSE 0 END) AS has_NT
--   FROM bibles AS b
--   WHERE
--     b.format NOT LIKE 'audio%'
--     AND b.format NOT LIKE 'video%'
--     AND (
--       b.format = 'text'
--       OR b.format LIKE 'text\_%'
--       OR b.format = 'text_json'
--     )
--   GROUP BY b.languageCodeHL
-- ) AS t
-- ORDER BY t.languageCodeHL;

-- ----------------------------------------------------------------------
-- 3. PREVIEW: which rows WILL be inserted into dbs_languages?
--
--   Criteria:
--     - has_C = 1 OR (has_OT = 1 AND has_NT = 1)
--     - hl_languages.languageCodeGoogle is NOT NULL and NOT empty
--
--   This does NOT modify any data.
-- ----------------------------------------------------------------------

SELECT
  t.languageCodeHL,
  'C'  AS collectionCode,
  'text' AS format,
  hl.languageCodeGoogle
FROM (
  SELECT
    b.languageCodeHL,
    MAX(CASE WHEN b.collectionCode = 'C'
             THEN 1 ELSE 0 END) AS has_C,
    MAX(CASE WHEN b.collectionCode = 'OT'
             THEN 1 ELSE 0 END) AS has_OT,
    MAX(CASE WHEN b.collectionCode = 'NT'
             THEN 1 ELSE 0 END) AS has_NT
  FROM bibles AS b
  WHERE
    b.format NOT LIKE 'audio%'
    AND b.format NOT LIKE 'video%'
    AND (
      b.format = 'text'
      OR b.format LIKE 'text\_%'
      OR b.format = 'text_json'
    )
  GROUP BY b.languageCodeHL
) AS t
JOIN hl_languages AS hl
  ON hl.languageCodeHL = t.languageCodeHL
WHERE
  (
    t.has_C = 1
    OR (t.has_OT = 1 AND t.has_NT = 1)
  )
  AND hl.languageCodeGoogle IS NOT NULL
  AND hl.languageCodeGoogle <> ''
ORDER BY t.languageCodeHL;

-- ----------------------------------------------------------------------
-- 4. REFRESH dbs_languages with these "C + Google" languages
--
--   IMPORTANT:
--     - Run the PREVIEW above first.
--     - This will wipe and rebuild dbs_languages.
--
--   If you want a backup:
--     CREATE TABLE dbs_languages_backup AS
--       SELECT * FROM dbs_languages;
-- ----------------------------------------------------------------------
-- Uncomment this block when you are ready to refresh.

-- TRUNCATE TABLE dbs_languages;
--
-- INSERT INTO dbs_languages (languageCodeHL, collectionCode, format)
-- SELECT
--   t.languageCodeHL,
--   'C'    AS collectionCode,
--   'text' AS format
-- FROM (
--   SELECT
--     b.languageCodeHL,
--     MAX(CASE WHEN b.collectionCode = 'C'
--              THEN 1 ELSE 0 END) AS has_C,
--     MAX(CASE WHEN b.collectionCode = 'OT'
--              THEN 1 ELSE 0 END) AS has_OT,
--     MAX(CASE WHEN b.collectionCode = 'NT'
--              THEN 1 ELSE 0 END) AS has_NT
--   FROM bibles AS b
--   WHERE
--     b.format NOT LIKE 'audio%'
--     AND b.format NOT LIKE 'video%'
--     AND (
--       b.format = 'text'
--       OR b.format LIKE 'text\_%'
--       OR b.format = 'text_json'
--     )
--   GROUP BY b.languageCodeHL
-- ) AS t
-- JOIN hl_languages AS hl
--   ON hl.languageCodeHL = t.languageCodeHL
-- WHERE
--   (
--     t.has_C = 1
--     OR (t.has_OT = 1 AND t.has_NT = 1)
--   )
--   AND hl.languageCodeGoogle IS NOT NULL
--   AND hl.languageCodeGoogle <> '';

-- ----------------------------------------------------------------------
-- 5. Sanity checks after refresh
-- ----------------------------------------------------------------------
-- How many languages in dbs_languages now?
-- SELECT COUNT(*) AS dbs_languages_rows
-- FROM dbs_languages;
--
-- Show a sample:
-- SELECT *
-- FROM dbs_languages
-- ORDER BY languageCodeHL
-- LIMIT 20;
