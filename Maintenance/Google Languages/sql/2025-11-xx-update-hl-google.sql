-- ======================================================================
-- 2025-11-xx-update-hl_languages-google.sql
--
-- Purpose
--   Use iso_code_bridge to fill hl_languages.languageCodeGoogle
--   where it is currently empty.
--
--   This script:
--     - NEVER overwrites existing languageCodeGoogle values.
--     - Only uses iso_code_bridge rows where iso639_3 is NOT NULL.
--     - If multiple googleCodes share the same iso639_3, chooses the
--       lexicographically smallest googleCode for that iso.
--
-- Usage
--   1) Run the PREVIEW SELECT (section 2) to see what would change.
--   2) If satisfied, run the UPDATE (section 3).
--   3) Optionally re-run the PREVIEW to confirm results.
--
-- Requirements
--   - iso_code_bridge populated and curated.
--   - hl_languages.languageCodeIso holds ISO 639-3 codes.
-- ======================================================================

-- ----------------------------------------------------------------------
-- 1. Helper: derived mapping "iso → googleCode"
--
--    For each iso639_3, we choose a single googleCode:
--      MIN(googleCode)
--    This avoids ambiguous joins if multiple rows share the same iso.
--
--    We will reuse this subquery in both PREVIEW and UPDATE.
-- ----------------------------------------------------------------------
-- Example of the mapping alone (optional):
-- SELECT
--   m.iso639_3,
--   m.googleCode
-- FROM (
--   SELECT
--     iso639_3,
--     MIN(googleCode) AS googleCode
--   FROM iso_code_bridge
--   WHERE iso639_3 IS NOT NULL
--   GROUP BY iso639_3
-- ) AS m
-- ORDER BY m.iso639_3;

-- ----------------------------------------------------------------------
-- 2. PREVIEW: which hl_languages rows would be updated?
--
--    This does NOT change data. It shows:
--      - HL codes and names
--      - existing languageCodeGoogle (old_google)
--      - proposed googleCode (new_google)
-- ----------------------------------------------------------------------

SELECT
  hl.languageCodeHL,
  hl.languageCodeIso,
  hl.name,
  hl.languageCodeGoogle AS old_google,
  m.googleCode          AS new_google
FROM hl_languages AS hl
JOIN (
  SELECT
    iso639_3,
    MIN(googleCode) AS googleCode
  FROM iso_code_bridge
  WHERE iso639_3 IS NOT NULL
  GROUP BY iso639_3
) AS m
  ON hl.languageCodeIso = m.iso639_3
WHERE
  (hl.languageCodeGoogle IS NULL
   OR hl.languageCodeGoogle = '')
ORDER BY
  hl.languageCodeIso,
  hl.languageCodeHL;

-- ----------------------------------------------------------------------
-- 3. UPDATE: fill missing hl_languages.languageCodeGoogle
--
--    IMPORTANT:
--      - Run the PREVIEW above first.
--      - This UPDATE only touches rows where languageCodeGoogle is
--        NULL or '' (empty string).
--
--    If you want an extra safety net, you can:
--      CREATE TABLE hl_languages_backup AS
--        SELECT * FROM hl_languages;
--    before running this UPDATE.
-- ----------------------------------------------------------------------
-- Uncomment the UPDATE when ready.

-- UPDATE hl_languages AS hl
-- JOIN (
--   SELECT
--     iso639_3,
--     MIN(googleCode) AS googleCode
--   FROM iso_code_bridge
--   WHERE iso639_3 IS NOT NULL
--   GROUP BY iso639_3
-- ) AS m
--   ON hl.languageCodeIso = m.iso639_3
-- SET hl.languageCodeGoogle = m.googleCode
-- WHERE
--   (hl.languageCodeGoogle IS NULL
--    OR hl.languageCodeGoogle = '');

-- ----------------------------------------------------------------------
-- 4. Sanity checks after UPDATE (run manually)
-- ----------------------------------------------------------------------
-- How many hl_languages rows now have a google code?
-- SELECT COUNT(*) AS hl_with_google
-- FROM hl_languages
-- WHERE languageCodeGoogle IS NOT NULL
--   AND languageCodeGoogle <> '';
--
-- Any hl_languages rows with an ISO that appears in iso_code_bridge
-- but still missing a google code?
-- SELECT
--   hl.languageCodeHL,
--   hl.languageCodeIso,
--   hl.name
-- FROM hl_languages AS hl
-- JOIN (
--   SELECT DISTINCT iso639_3
--   FROM iso_code_bridge
--   WHERE iso639_3 IS NOT NULL
-- ) AS b
--   ON hl.languageCodeIso = b.iso639_3
-- WHERE
--   hl.languageCodeGoogle IS NULL
--   OR hl.languageCodeGoogle = ''
-- ORDER BY
--   hl.languageCodeIso,
--   hl.languageCodeHL;
