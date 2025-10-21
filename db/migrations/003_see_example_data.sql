-- 003_seed_sample_data.sql
-- Seeds one client (wsu), two resources (interface/app + commonContent/hope),
-- two source strings (English master), and two example translations.

-- 1) Client
INSERT INTO i18n_clients (client_code, client_name)
VALUES ('wsu', 'Western Sydney University')
ON DUPLICATE KEY UPDATE client_name = VALUES(client_name);

-- 2) Resources
INSERT INTO i18n_resources (type, subject, variant, description)
VALUES
  ('interface',     'app',  'wsu', 'App interface strings for WSU'),
  ('commonContent', 'hope', 'wsu', 'Hope study content for WSU')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 3) Capture IDs for convenience
SET @client_id := (SELECT client_id FROM i18n_clients
                   WHERE client_code = 'wsu' LIMIT 1);

SET @res_interface := (SELECT resource_id FROM i18n_resources
                       WHERE type='interface' AND subject='app' AND variant='wsu' LIMIT 1);

SET @res_hope := (SELECT resource_id FROM i18n_resources
                  WHERE type='commonContent' AND subject='hope' AND variant='wsu' LIMIT 1);

-- 4) Source strings (English master), keyed by a stable hash of the text
--    Use LOWER(SHA1(...)) to produce a 40-char hex key.
INSERT INTO i18n_strings
  (client_id, resource_id, key_hash, english_text, developer_note, is_active)
VALUES
  (
    @client_id,
    @res_interface,
    LOWER(SHA1('Share this with a friend')),
    'Share this with a friend',
    'Button label on the Share dialog',
    1
  ),
  (
    @client_id,
    @res_hope,
    LOWER(SHA1('A new star appeared. The wise men began a long journey to honour the newborn king.')),
    'A new star appeared. The wise men began a long journey to honour the newborn king.',
    'Intro sentence in Hope study',
    1
  )
ON DUPLICATE KEY UPDATE
  english_text   = VALUES(english_text),
  developer_note = VALUES(developer_note),
  is_active      = 1,
  updated_at     = CURRENT_TIMESTAMP;

-- 5) Example translations
--    (Gujarati for the interface button; Marathi for the Hope sentence)
INSERT INTO i18n_translations
  (string_id, language_hl, language_iso, translated_text, status, source, posted)
SELECT
  s.string_id,
  'gjr00', 'gu',
  'મિત્ર સાથે આ શેર કરો',
  'approved', 'human', CURDATE()
FROM i18n_strings s
WHERE s.client_id=@client_id
  AND s.resource_id=@res_interface
  AND s.key_hash = LOWER(SHA1('Share this with a friend'))
ON DUPLICATE KEY UPDATE
  translated_text = VALUES(translated_text),
  status          = VALUES(status),
  updated_at      = CURRENT_TIMESTAMP;

INSERT INTO i18n_translations
  (string_id, language_hl, language_iso, translated_text, status, source, posted)
SELECT
  s.string_id,
  'mrt00', 'mr',
  'एक नवा तारा दिसला. ज्ञानी पुरुषांनी नवजात राजाचा सन्मान करण्यासाठी लांब प्रवास सुरू केला.',
  'review', 'human', CURDATE()
FROM i18n_strings s
WHERE s.client_id=@client_id
  AND s.resource_id=@res_hope
  AND s.key_hash = LOWER(SHA1('A new star appeared. The wise men began a long journey to honour the newborn king.'))
ON DUPLICATE KEY UPDATE
  translated_text = VALUES(translated_text),
  status          = VALUES(status),
  updated_at      = CURRENT_TIMESTAMP;

-- Done.
