-- ======================================================================
-- 2025-11-xx-create-iso_code_bridge.sql
--
-- Purpose
--   Ensure iso_code_bridge exists and is up to date with
--   languages_google, WITHOUT losing any hand-edited iso639_3 values.
--
--   Workflow:
--     1) languages_google is refreshed from Google (CSV import).
--     2) This script:
--          - creates iso_code_bridge if it doesn't exist
--          - finds NEW Google codes in languages_google
--          - inserts them into iso_code_bridge
--              * with iso639_3 guessed from hl_languages.name when possible
--              * otherwise iso639_3 = NULL for you to research manually
--
--   IMPORTANT:
--     - Existing rows in iso_code_bridge are NEVER overwritten here.
--     - Your hand-edited iso639_3 values are preserved.
-- ======================================================================

-- ----------------------------------------------------------------------
-- 1. Create table if it does not exist
--    (Non-destructive: will not drop existing data.)
-- ----------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS iso_code_bridge (
  googleCode   VARCHAR(32)  NOT NULL,
  iso639_3     CHAR(3)      DEFAULT NULL,
  languageName VARCHAR(191) NOT NULL,
  PRIMARY KEY (googleCode),
  KEY idx_iso3 (iso639_3)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 2. Preview: which Google codes are NOT yet in iso_code_bridge?
--
--    This is read-only. Look at the result and see which new codes
--    will be inserted if you run the INSERT below.
-- ----------------------------------------------------------------------

SELECT
  lg.languageCodeGoogle          AS googleCode,
  lg.languageName                AS googleName,
  hl.languageCodeIso             AS guessed_iso639_3,
  hl.name                        AS matched_hl_name
FROM languages_google AS lg
LEFT JOIN iso_code_bridge AS b
  ON b.googleCode = lg.languageCodeGoogle
LEFT JOIN hl_languages AS hl
  ON UPPER(hl.name) = UPPER(lg.languageName)
WHERE b.googleCode IS NULL
ORDER BY lg.languageCodeGoogle;

-- ----------------------------------------------------------------------
-- 3. Insert NEW codes into iso_code_bridge
--
--    - Only codes that do NOT already exist in iso_code_bridge.
--    - iso639_3 is taken from hl_languages.name when there is an
--      exact, case-insensitive name match.
--    - If there is no such match, iso639_3 will be NULL.
--
--    You can run the SELECT above first, then run this block when
--    you are satisfied with what will be inserted.
-- ----------------------------------------------------------------------

INSERT INTO iso_code_bridge (googleCode, iso639_3, languageName)
SELECT
  lg.languageCodeGoogle              AS googleCode,
  hl.languageCodeIso                 AS iso639_3,   -- may be NULL
  lg.languageName                    AS languageName
FROM languages_google AS lg
LEFT JOIN iso_code_bridge AS b
  ON b.googleCode = lg.languageCodeGoogle
LEFT JOIN hl_languages AS hl
  ON UPPER(hl.name) = UPPER(lg.languageName)
WHERE b.googleCode IS NULL;

-- ----------------------------------------------------------------------
-- 4. Sanity checks
-- ----------------------------------------------------------------------

-- How many total rows in the bridge now?
SELECT COUNT(*) AS bridge_rows
FROM iso_code_bridge;

-- How many rows still need manual iso639_3 decisions?
SELECT COUNT(*) AS rows_with_null_iso639_3
FROM iso_code_bridge
WHERE iso639_3 IS NULL;

-- Sample of rows with NULL iso639_3 to research manually.
SELECT *
FROM iso_code_bridge
WHERE iso639_3 IS NULL
ORDER BY googleCode
LIMIT 50;
