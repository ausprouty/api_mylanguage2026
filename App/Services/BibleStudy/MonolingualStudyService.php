<?php

namespace App\Services\BibleStudy;

use App\Models\Language\LanguageModel;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Services\TranslationService;
use App\Configuration\Config;

class MonolingualStudyService extends AbstractBibleStudyService
{
    protected $language;

    public function getLanguageInfo(): LanguageModel
    {
        return $this->languageRepository
            ->findOneLanguageByLanguageCodeHL(
                $this->languageCodeHL1
            );
    }

    public function getBibleInfo(): BibleModel
    {
        return $this->bibleRepository
            ->findBestBibleByLanguageCodeHL(
                $this->languageCodeHL1
            );
    }

    public function getPassageModel(): PassageModel
    {
        $result =
            $this->biblePassageService->getPassageModel(
                $this->primaryBible,
                $this->passageReferenceInfo
            );
        return $result;
    }


    public function getTwigTranslationArray(): array
    {
        $data =  $this->translationService->loadTranslation($this->languageCodeHL1, $this->study);
        $descriptionTwigKey = $this->studyReferenceInfo->getDescriptionTwigKey();
        $data['title'] = $data[$descriptionTwigKey];
        $data['language'] = $this->primaryLanguage->getName();
        if ($this->format == 'pdf') {
            $data['qr_code1'] =   Config::getUrl('resources.qr_codes') . $this->qrcode1;
        }
        return $data;
    }
    public function getBiblePassageDetailsArray(): array
    { 
        $data = [];
        $data['bible_reference'] = $this->primaryBiblePassage->getReferenceLocalLanguage();
        $data['bible_text'] = $this->primaryBiblePassage->getPassageText();
        $data['bible_url'] = $this->primaryBiblePassage->getPassageUrl();
        if ($this->primaryVideoUrl){
            $data['video_url'] = $this->primaryVideoUrl;
        }
        
        return $data;
    }

    public function getStudyTemplateName(string $study, string $format): void
    {
        $this->studyTemplateName = $this->templateService->
            getStudyTemplateName('monolingual', $study, $format);
    }
    public function getVideoTemplateName(?string $videoUrl, string $format): void{
        if (!$videoUrl){ 
            $this->videoTemplateName =  'videoBlank.twig';
        }
        elseif ($format == 'pdf'){ 
            $this->videoTemplateName =  'videoQrcode.twig';
        }
        elseif ($format == 'view'){ 
            $this->videoTemplateName =  'videoIframe.twig';
        }
        else{
            $this->videoTemplateName =  'videoBlank.twig'; 
        }
    }

    public function assembleOutput(): string
    {
        $translations = array();
        $translations['language1'] = $this->twigTranslation1;
        print_r ('starting assembleOutput<br><br> ');
        $biblePassageDetailsArray = $this->getBiblePassageDetailsArray();
        //print_r ($biblePassageDetailsArray);
        $text = $this->twigService->buildMonolingualTwig(
            $this->studyTemplateName, 
            $this->bibleTemplateName,
            $this->videoTemplateName,
            $translations,
            $biblePassageDetailsArray
        );
        return $text;
    }
}
