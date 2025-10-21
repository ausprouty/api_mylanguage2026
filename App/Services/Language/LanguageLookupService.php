<?php

namespace App\Services\Language;

use App\Repositories\LanguageRepository;
use App\Configuration\Config;

class LanguageLookupService
{
    private $repository;

    public function __construct(LanguageRepository $repository)
    {
        $this->repository = $repository;
    }

    public function findOrInsertLanguageCode($code, $languageName)
    {
        $isoCode = $this->repository->findOneByCode('languageCodeIso', $code)
            ?? $this->repository->findOneByCode('languageCodeGoogle', $code)
            ?? $this->repository->findOneByCode('languageCodeBrowser', $code);

        if (!$isoCode) {
            $isoCode = $this->repository->insertLanguage($code, $languageName);
        }

        return $isoCode;
    }
    /**
     * Get the next language for DBS based on the current language code.
     *
     * @param string $languageCodeHL The current language code.
     * @return string The next language code or 'End' if none found.
     */
    public static function getNextLanguageForDbs(string $languageCodeHL): string
    {
        $directory = Config::getDir('resources.translations') . 'languages/';
        $scanned_directory = array_diff(scandir($directory), ['..', '.']);
        sort($scanned_directory); // Ensure sorted order for comparison

        foreach ($scanned_directory as $dir) {
            if ($dir > $languageCodeHL) {
                return $dir;
            }
        }
        return 'end';
    }
}
