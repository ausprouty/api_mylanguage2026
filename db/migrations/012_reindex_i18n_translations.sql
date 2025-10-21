ALTER TABLE i18n_translations
  MODIFY languageCodeIso VARCHAR(32) NOT NULL,
  ADD UNIQUE KEY uq_string_iso (stringId, languageCodeIso),
  ADD KEY idx_iso (languageCodeIso),
  ADD KEY idx_hl  (languageCodeHL);