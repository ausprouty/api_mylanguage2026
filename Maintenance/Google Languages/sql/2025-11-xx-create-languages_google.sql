-- ======================================================================
-- 2025-11-xx-create-languages_google.sql
--
-- Purpose
--   Create (or recreate) the languages_google table and populate it
--   from the CSV produced by:
--
--   php Maintenance/GoogleLanguage/php/get-google-translation-languages.php \
--       > Maintenance/GoogleLanguage/data/languages_google_raw.csv
--
-- Notes
--   - This script is safe to re-run.
--   - It uses LOAD DATA INFILE; adjust the file path and LOCAL keyword
--     to match your MySQL / MariaDB setup.
--   - Run from a MySQL client connected to the api2 database.
-- ======================================================================

-- ----------------------------------------------------------------------
-- 1. (Optional) Backup existing table
--    Uncomment and run once if you want a snapshot before changes.
-- ----------------------------------------------------------------------
-- DROP TABLE IF EXISTS languages_google_backup;
-- CREATE TABLE languages_google_backup AS
--   SELECT * FROM languages_google;

-- ----------------------------------------------------------------------
-- 2. Create or recreate the languages_google table
-- ----------------------------------------------------------------------

DROP TABLE IF EXISTS languages_google;

CREATE TABLE languages_google (
  languageCodeGoogle VARCHAR(32) NOT NULL,
  languageName       VARCHAR(191) NOT NULL,
  PRIMARY KEY (languageCodeGoogle)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

--- 3. Load data from CSV
--
-- The CSV is expected to have a header row:
--   languageCodeGoogle,languageName
--
-- IMPORTANT:
--   - If LOCAL is disabled on your server, use LOAD DATA INFILE (no LOCAL),
--     and make sure the CSV file is readable by the MySQL server process
--     and located in a directory allowed by secure_file_priv (if set).
--   - If you know LOCAL is enabled and prefer it, you can switch to the
--     LOAD DATA LOCAL INFILE variant below.

-- Adjust this path to where the file lives ON THE SERVER:
--   /home/mylanguagenet/api2.mylanguage.net.au/Maintenance/GoogleLanguage/data/languages_google_raw.csv

LOAD DATA INFILE
  '/home/mylanguagenet/api2.mylanguage.net.au/Maintenance/GoogleLanguage/data/languages_google_raw.csv'
INTO TABLE languages_google
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
  ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(languageCodeGoogle, languageName);

-- If you ever need the LOCAL variant instead, comment out the block above
-- and use this one (only if local_infile is enabled):

-- LOAD DATA LOCAL INFILE
--   '/home/mylanguagenet/api2.mylanguage.net.au/Maintenance/GoogleLanguage/data/languages_google_raw.csv'
-- INTO TABLE languages_google
-- CHARACTER SET utf8mb4
-- FIELDS TERMINATED BY ','
--   ENCLOSED BY '"'
-- LINES TERMINATED BY '\n'
-- IGNORE 1 LINES
-- (languageCodeGoogle, languageName);

-- ----------------------------------------------------------------------
-- 4. Quick sanity checks
-- ----------------------------------------------------------------------

-- How many languages did we load?
SELECT COUNT(*) AS languages_loaded
FROM languages_google;

-- Show a small sample so we can eyeball the data.
SELECT *
FROM languages_google
ORDER BY languageCodeGoogle
LIMIT 20;
