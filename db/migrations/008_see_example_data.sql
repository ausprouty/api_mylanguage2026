-- 003_seed_sample_data_camel.sql
-- Seeds one client (wsu), two resources (interface/app + commonContent/hope),
-- two source strings (English master), and two example translations.

-- 1) Client
INSERT INTO i18n_clients (clientCode, clientName)
VALUES ('wsu', 'Western Sydney University')
ON DUPLICATE KEY UPDATE clientName = VALUES(clientName);

-- 2) Resources
INSERT INTO i18n_resources (type, subject, variant, description)
VALUES
  ('interface',     'app',  'wsu', 'App interface strings for WSU'),
  ('commonContent', 'hope', 'wsu', 'Hope study content for WSU')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 3) Capture IDs for convenience
SET @clientId := (SELECT clientId FROM i18n_clients
                  WHERE clientCode = 'wsu' LIMIT 1);

SET @resInterface := (SELECT resourceId FROM i18n_resources
                      WHERE type='interface' AND subject='app' AND variant='wsu' LIMIT 1);

SET @resHope := (SELECT resourceId FROM i18n_resources
                 WHERE type='commonContent' AND subject='hope' AND variant='wsu' LIMIT 1);

-- 4) Source strings (English master), keyed by a stable hash of the text
--    Use LOWER(SHA1(...)) to produce a 40-char hex key.
INSERT INTO i18n_strings
  (clientId, resourceId, keyHash, englishText, developerNote, isActive)
VALUES
  (
    @clientId,
    @resInterface,
    LOWER(SHA1('Share this with a friend')),
    'Share this with a friend',
    'Button label on the Share dialog',
    1
  ),
  (
    @clientId,
    @resHope,
    LOWER(SHA1('A new star appeared. The wise men began a long journey to honour the newborn king.')),
    'A new star appeared. The wise men began a long journey to honour the newborn king.',
    'Intro sentence in Hope study',
    1
  )
ON DUPLICATE KEY UPDATE
  englishText   = VALUES(englishText),
  developerNote = VALUES(developerNote),
  isActive      = 1,
  updatedAt     = CURRENT_TIMESTAMP;

-- 5) Example translations
--    (Gujarati for the interface button; Marathi for the Hope sentence)
INSERT INTO i18n_translations
  (stringId, languageCodeHL, languageCodeIso, translatedText, status, source, posted)
SELECT
  s.stringId,
  'gjr00', 'gu',
  'મિત્ર સાથે આ શેર કરો',
  'approved', 'human', CURDATE()
FROM i18n_strings s
WHERE s.clientId=@clientId
  AND s.resourceId=@resInterface
  AND s.keyHash = LOWER(SHA1('Share this with a friend'))
ON DUPLICATE KEY UPDATE
  translatedText = VALUES(translatedText),
  status         = VALUES(status),
  updatedAt      = CURRENT_TIMESTAMP;

INSERT INTO i18n_translations
  (stringId, languageCodeHL, languageCodeIso, translatedText, status, source, posted)
SELECT
  s.stringId,
  'mrt00', 'mr',
  'एक नवा तारा दिसला. ज्ञानी पुरुषांनी नवजात राजाचा सन्मान करण्यासाठी लांब प्रवास सुरू केला.',
  'review', 'human', CURDATE()
FROM i18n_strings s
WHERE s.clientId=@clientId
  AND s.resourceId=@resHope
  AND s.keyHash = LOWER(SHA1('A new star appeared. The wise men began a long journey to honour the newborn king.'))
ON DUPLICATE KEY UPDATE
  translatedText = VALUES(translatedText),
  status         = VALUES(status),
  updatedAt      = CURRENT_TIMESTAMP;

-- Done.
