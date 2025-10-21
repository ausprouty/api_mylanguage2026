-- 004_create_i18n_client_resources_view.sql
DROP VIEW IF EXISTS i18n_client_resources;

CREATE VIEW i18n_client_resources AS
SELECT DISTINCT
  c.client_code,
  r.resource_id,
  r.type,
  r.subject,
  r.variant
FROM i18n_strings s
JOIN i18n_clients   c ON c.client_id   = s.client_id
JOIN i18n_resources r ON r.resource_id = s.resource_id;
