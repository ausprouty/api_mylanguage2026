-- 017_seed_global_client.sql
START TRANSACTION;

-- Assumes clientCode is (or will be) unique.
-- If you don't already have a unique key, add one in an earlier migration.
-- ALTER TABLE `i18n_clients`
--   ADD UNIQUE KEY `uk_i18nClients_clientCode` (`clientCode`);

INSERT INTO `i18n_clients`
  (`clientCode`,`variant`,`clientName`,`isActive`,`createdAt`,`updatedAt`)
VALUES
  ('global', NULL, 'Global client for commonContent (auto)', 1,
   UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  ON DUPLICATE KEY UPDATE
  `clientName` = VALUES(`clientName`),
  `isActive`   = VALUES(`isActive`);
  `updatedAt` = UTC_TIMESTAMP()

COMMIT;
