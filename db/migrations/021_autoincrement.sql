-- 1) See current definition
SHOW CREATE TABLE i18n_translation_queue\G

-- 2) If `id` exists but isn’t AUTO_INCREMENT:
ALTER TABLE i18n_translation_queue
  MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (id);

-- 3) If `id` does NOT exist yet (rare, but code expects it):
ALTER TABLE i18n_translation_queue
  ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
