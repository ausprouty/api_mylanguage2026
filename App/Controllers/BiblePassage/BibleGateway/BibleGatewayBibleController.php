<?php

namespace App\Controllers\BiblePassage\BibleGateway;

use App\Repositories\BibleGatewayRepository;
use App\Services\Bible\BibleGatewayDataParserService;
use App\Services\Language\LanguageLookupService;
use App\Configuration\Config;
use App\Services\LoggerService;

class BibleGatewayBibleController
{
    private $repository;
    private $parser;
    private $languageLookupService;

    public function __construct(
        BibleGatewayRepository $repository,
        BibleGatewayDataParserService $parser,
        LanguageLookupService $languageLookupService
    ) {
        $this->repository = $repository;
        $this->parser = $parser;
        $this->languageLookupService = $languageLookupService;
    }

    public function import()
    {
        $filename = Config::getDir('imports') . 'BibleGatewayBibles.txt';
        if (!file_exists($filename)) {
            // Log an error, display an error message, or provide instructions
            LoggerService::logError("File not found: {$filename}");
            return;
        }

        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;

        foreach ($lines as $line) {
            if (strpos($line, 'class="lang"') !== false) {
                $languageName = $this->parser->parseLanguageName($line);
                $languageCodeIso = $this->parser->parseLanguageCodeIso($line);
                $defaultBible = $this->parser->parseDefaultBible($line);
            } elseif (strpos($line, 'class="spacer"') === false) {
                $externalId = $this->parser->parseExternalId($line);
                $existingRecord = $this->repository->recordExists($externalId);

                if ($existingRecord) {
                    $this->repository->updateVerified($existingRecord);
                    $this->repository->updateLanguage($existingRecord, $languageCodeIso, $languageName);
                    $this->repository->updateBibleWeight($existingRecord, $externalId === $defaultBible ? 9 : 0);
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
        }

        return $count;
    }
}
