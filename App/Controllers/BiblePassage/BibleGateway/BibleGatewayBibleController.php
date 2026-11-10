
$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lines === false) {
    LoggerService::logError("Unable to read file: {$filename}");
    return 0;
}

$count = 0;
$languageName = null;
$languageCodeIso = null;
$defaultBible = null;

foreach ($lines as $line) {

    // Language heading
    if (strpos($line, 'class="lang"') !== false) {
        $languageName = $this->parser->parseLanguageName($line);
        $languageCodeIso = $this->parser->parseLanguageCodeIso($line);
        $defaultBible = $this->parser->parseDefaultBible($line);
        continue;
    }

    // Skip spacer lines
    if (strpos($line, 'class="spacer"') !== false) {
        continue;
    }

    // Must have language information
    if (!$languageName || !$languageCodeIso) {
        continue;
    }

    // Extract Bible information
    $bibleData = $this->parser->parseExternalIdAndVolumeName($line);

    $externalId = $bibleData['externalId'];
    $volumeName = $bibleData['volumeName'];

    if (!$externalId || !$volumeName) {
        continue;
    }

    $existingRecord = $this->repository->recordExists($externalId);

    if ($existingRecord !== false) {
        $this->repository->updateVerified($existingRecord);

        $this->repository->updateLanguage(
            $existingRecord,
            $languageCodeIso,
            $languageName
        );

        $this->repository->updateBibleWeight(
            $existingRecord,
            $externalId === $defaultBible ? 9 : 0
        );
    } else {
        $data = [
            ':source' => 'bible_gateway',
            ':externalId' => $externalId,
            ':volumeName' => $volumeName,
            ':languageName' => $languageName,
            ':languageCodeIso' => $languageCodeIso,
            ':collectionCode' => 'C',
            ':format' => 'text',
            ':text' => 'Y',
            ':weight' => $externalId === $defaultBible ? 9 : 0,
            ':dateVerified' => date('Y-m-d'),
        ];

        $this->repository->insertBibleRecord($data);
        $count++;
    }
}

return $count;
