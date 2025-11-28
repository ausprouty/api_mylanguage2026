# Google Language Maintenance

## Purpose

This maintenance set covers everything related to **Google Translate
language support** and how it connects to our internal language tables:

- `languages_google` – list of languages supported by Google Translate.
- `iso_code_bridge` – mapping from Google codes to ISO 639-3 codes.
- `hl_languages.languageCodeGoogle` – the Google code attached to each
  HL language.
- `dbs_languages` – which HL languages have a complete Bible (C) and a
  Google code, so we can support translated Bible content.

This document explains how to refresh these when:
- Google adds new languages, or
- we want to re-sync our mappings and supported Bible languages.

---

## Directory layout

```text
Maintenance/GoogleLanguage/
├── php/
│   └── get-google-translation-languages.php
├── sql/
│   ├── 2025-11-xx-create-languages_google.sql
│   ├── 2025-11-xx-create-iso_code_bridge.sql
│   ├── 2025-11-xx-update-hl_languages-google.sql
│   └── 2025-11-xx-refresh-dbs_languages.sql
├── doc/
│   └── google-language-maintenance.md   (this file)
└── data/   (optional, not in git)
    └── languages_google_raw.csv
```



## Step 1 – Fetch Google language list

Script:
php/get-google-translation-languages.php

This script:

- Reads the API key from Config::get('api.google_translate_apiKey').

- Calls the Google Translate languages endpoint.

- Writes a CSV of (languageCodeGoogle, languageName) to STDOUT.

Run:
```bash
cd /home/mylanguagenet/api2.mylanguage.net.au

php Maintenance/GoogleLanguage/php/get-google-translation-languages.php \
  > Maintenance/GoogleLanguage/data/languages_google_raw.csv
````

Check the file in a text editor or spreadsheet to confirm it looks
sensible.

## Step 2 – Convert JSON to CSV

Google’s API response is stored locally as `google-languages.json` so we can reuse it without repeating API calls.

Now convert the JSON into a clean CSV (`languageCodeGoogle,languageName`), suitable for SQL import.

Run:

```bash
php Maintenance/GoogleLanguage/php/google-json-to-csv.php \
  > Maintenance/GoogleLanguage/data/languages_google_raw.csv
```

### What this script does

- Reads `Maintenance/GoogleLanguage/google-languages.json`
- Extracts:
  - `language` → Google language code (e.g., `en`, `zh-TW`, `gom`)
  - `name` → Human-readable language name (English display name)
- Outputs a CSV with header:

```text
languageCodeGoogle,languageName
```


## Step 3 – Import into languages_google

Script:
sql/2025-11-xx-create-languages_google.sql 

Typical flow:

- Create or truncate languages_google.

- Either:

  - Use LOAD DATA INFILE to import languages_google_raw.csv, or

  - Paste an INSERT script created from that CSV.

- Keep the SQL file commented so it can be re-run in the future.

## Step 4 – Maintain iso_code_bridge

Script:
sql/2025-11-xx-create-iso_code_bridge.sql (TBD)

Purpose:

- Map each languageCodeGoogle to an ISO 639-3 code where possible.

- This is used to tie Google languages to our hl_languages ISO codes.

This script:

- Creates iso_code_bridge (googleCode, iso639_3, languageName, ...).

- Inserts one row per Google language, with iso639_3 filled where
known and NULL where manual decisions are still needed.

Note:
- Do not truncate the iso_code_bridge unless you want to spend an hour filling in NULL values.

## Step 5 – Update hl_languages.languageCodeGoogle

Script:
sql/2025-11-xx-update-hl-google.sql (TBD)

Purpose:

- For each hl_languages.languageCodeIso that matches
iso_code_bridge.iso639_3, fill in languageCodeGoogle if it is
currently empty.

The SQL script should:

- Provide a preview SELECT showing which rows will be changed.

- Provide the UPDATE statement (commented out) that can be
uncommented once the preview looks correct.

## Step 6 – Refresh dbs_languages (complete Bibles with Google)

Script:
sql/2025-11-xx-refresh-dbs_languages.sql (TBD)

Purpose:

- Identify languages where our bibles table has:

  - a text Complete Bible (C), or

  - both text OT and text NT,

  - AND hl_languages.languageCodeGoogle is set.

- Populate dbs_languages with those languages only, using
collectionCode = 'C' and format = 'text'.

The script should:

- Start with a SELECT-only preview.

- Then TRUNCATE dbs_languages and INSERT the computed rows.

Notes for Future-Me

- Always run the preview SELECT sections first.

- If logic changes, update both:

  - the relevant .sql file

  - this .md file with a short note (what changed and why).

- Keep line lengths under ~80 characters in SQL and PHP for readability.

- Avoid storing API keys in these scripts; they always come from central
config (Config::get(...)).
