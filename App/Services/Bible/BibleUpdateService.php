<?php

namespace App\Services\Bible;

use App\Models\Bible\BibleModel;
use App\Repositories\LanguageRepository;
use App\Services\Database\DatabaseService;

class BibleUpdateService
{
    private $databaseService;
    private $bibleModel;

    public function __construct(DatabaseService $databaseService, BibleModel $bibleModel)
    {
        $this->databaseService = $databaseService;
        $this->bibleModel = $bibleModel;
    }

    public function updateBibleDatabaseWithData(array $translations, LanguageRepository $languageRepository)
    {
        $count = 0;
        $audioTypes = ['audio_drama', 'audio', 'audio_stream', 'audio_drama_stream'];
        $textTypes = ['text_plain', 'text_format', 'text_usx', 'text_html', 'text_json'];
        $videoTypes = ['video_stream', 'video'];

        foreach ($translations as $translation) {
            $this->bibleModel->setLanguageData($translation->autonym, $translation->language, $translation->iso);

            foreach ($translation->filesets as $fileset) {
                $count++;
                $this->bibleModel->resetMediaFlags();

                foreach ($fileset as $item) {
                    $this->bibleModel->determineMediaType($item->type, $audioTypes, $textTypes, $videoTypes);
                    $this->bibleModel->prepareForSave('bible_brain', $item->id, $item->volume ?? null, $item->size, $item->type);
                    $this->bibleModel->addBibleBrainBible();
                }
            }
        }

        // Update the `checkedBBBibles` field for the given language code
        $languageCodeIso = $translations[0]->iso ?? null;
        if ($languageCodeIso) {
            $languageRepository->updateLanguageCheckedDate($languageCodeIso);
        }
    }
}
