-- 018_fix_resolved_bundle_view_google.sql
DROP VIEW IF EXISTS `i18n_resolved_bundle`;

CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW `i18n_resolved_bundle` AS
SELECT
  s.`stringId`                           AS `stringId`,
  s.`clientId`                           AS `clientId`,
  s.`resourceId`                         AS `resourceId`,
  s.`keyHash`                            AS `sourceKeyHash`,
  'en'                                   AS `sourceLanguageCodeGoogle`,
  s.`englishText`                        AS `sourceText`,
  t.`languageCodeGoogle`                 AS `languageCodeGoogle`,
  COALESCE(NULLIF(t.`translatedText`, ''), s.`englishText`) AS `resolvedText`
FROM `i18n_strings` s
LEFT JOIN `i18n_translations` t
  ON t.`stringId` = s.`stringId`;
