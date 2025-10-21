<?php

namespace App\Factories;

use App\Models\Bible\PassageModel;

class PassageFactory
{
    public static function createFromData($data): PassageModel
    {
        $biblePassage = new PassageModel();
        if ($data instanceof PassageModel) {
            $biblePassage->setBpid($data->getBpid());
            $biblePassage->setReferenceLocalLanguage($data->getReferenceLocalLanguage());
            $biblePassage->setPassageText($data->getPassageText());
            $biblePassage->setPassageUrl($data->getPassageUrl());
            $biblePassage->setDateLastUsed($data->getDateLastUsed());
            $biblePassage->setDateChecked($data->getDateChecked());
            $biblePassage->setTimesUsed($data->getTimesUsed());
        } else {
            $biblePassage->setBpid(is_array($data) ? $data['bpid'] : $data->bpid);
            $biblePassage->setReferenceLocalLanguage(is_array($data) ? $data['referenceLocalLanguage'] : $data->referenceLocalLanguage);
            $biblePassage->setPassageText(is_array($data) ? $data['passageText'] : $data->passageText);
            $biblePassage->setPassageUrl(is_array($data) ? $data['passageUrl'] : $data->passageUrl);
            $biblePassage->setDateLastUsed(is_array($data) ? $data['dateLastUsed'] : $data->dateLastUsed);
            $biblePassage->setDateChecked(is_array($data) ? $data['dateChecked'] : $data->dateChecked);
            $biblePassage->setTimesUsed(is_array($data) ? $data['timesUsed'] : $data->timesUsed);
        }
        
        return $biblePassage;
    }
}
