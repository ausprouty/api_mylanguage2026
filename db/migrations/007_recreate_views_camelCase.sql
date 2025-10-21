-- Resolved bundle with camelCase output
DROP VIEW IF EXISTS `i18n_resolved_bundle`;
CREATE VIEW `i18n_resolved_bundle` AS
SELECT
  c.`clientCode`                           AS `clientCode`,
  r.`resourceId`                           AS `resourceId`,
  r.`type`                                 AS `type`,
  r.`subject`                              AS `subject`,
  r.`variant`                              AS `variant`,
  s.`stringId`                             AS `stringId`,
  s.`keyHash`                              AS `keyHash`,
  s.`englishText`                          AS `englishText`,
  t.`languageCodeHL`                       AS `languageCodeHL`,
  COALESCE(t.`translatedText`, s.`englishText`) AS `resolvedText`,
  t.`status`                               AS `translationStatus`
FROM `i18n_strings` s
JOIN `i18n_clients`   c ON c.`clientId`   = s.`clientId`
JOIN `i18n_resources` r ON r.`resourceId` = s.`resourceId`
LEFT JOIN `i18n_translations` t
  ON t.`stringId` = s.`stringId`;

-- Client/resources (distinct) with camelCase
DROP VIEW IF EXISTS `i18n_client_resources`;
CREATE VIEW `i18n_client_resources` AS
SELECT DISTINCT
  c.`clientCode`  AS `clientCode`,
  r.`resourceId`  AS `resourceId`,
  r.`type`        AS `type`,
  r.`subject`     AS `subject`,
  r.`variant`     AS `variant`
FROM `i18n_strings` s
JOIN `i18n_clients`   c ON c.`clientId`   = s.`clientId`
JOIN `i18n_resources` r ON r.`resourceId` = s.`resourceId`;
