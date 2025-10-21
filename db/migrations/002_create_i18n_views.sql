-- 002_create_i18n_views.sql  (compat version, no JSON_OBJECTAGG)
DROP VIEW IF EXISTS i18n_resolved_bundle;

CREATE VIEW i18n_resolved_bundle AS
SELECT
  c.client_code,
  r.resource_id,
  r.type,
  r.subject,
  r.variant,
  s.string_id,
  s.key_hash,
  s.english_text,
  t.language_hl,
  COALESCE(t.translated_text, s.english_text) AS resolved_text,
  t.status AS translation_status
FROM i18n_strings s
JOIN i18n_clients   c ON c.client_id   = s.client_id
JOIN i18n_resources r ON r.resource_id = s.resource_id
LEFT JOIN i18n_translations t
  ON t.string_id = s.string_id;
