<?php

namespace App\Controllers\BibleStudy\Monolingual;

use App\Services\Language\TranslationService;
use App\Models\Language\LanguageModel;
use App\Repositories\LanguageRepository;
use App\Configuration\Config;
use App\Services\LoggerService;

class MonolingualTemplateTranslationController
{
    private LanguageRepository $languageRepository;
    private string $templateName;
    private ?string $template = null;
    private array $translation1 = [];
    private string $languageCodeHL1;
    private LanguageModel $language1;
    private string $translationFile;

    public function __construct(LanguageRepository $languageRepository, string $templateName, string $translationFile, string $languageCodeHL1)
    {
        $this->languageRepository = $languageRepository;
        $this->templateName = $templateName;
        $this->translationFile = $translationFile;
        $this->languageCodeHL1 = $languageCodeHL1;

        $this->initializeTemplate();
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    private function initializeTemplate(): void
    {
        $this->setLanguage();
        $this->loadTemplate();
        $this->loadTranslation();
        $this->applyPlaceholders();
    }

    private function setLanguage(): void
    {
        $this->language1 = new LanguageModel($this->languageRepository);
        $this->language1->findOneLanguageByLanguageCodeHL($this->languageCodeHL1);
    }

    private function loadTemplate(): void
    {
        $filePath = Config::getDir('resources.root') . $this->templateName . '.twig';

        if (!file_exists($filePath)) {
            LoggerService::logError('MonolingualTemplateTranslationController-28', 'ERROR - no such template as ' . $filePath);
            return;
        }

        $this->template = file_get_contents($filePath);
    }

    private function loadTranslation(): void
    {
        $translationModel = new TranslationService($this->languageCodeHL1, $this->translationFile);
        $this->translation1 = $translationModel->getTranslationFile();
    }

    private function applyPlaceholders(): void
    {
        $this->replaceTranslationPlaceholders();
        $this->replaceFontPlaceholders();
    }

    private function replaceTranslationPlaceholders(): void
    {
        if ($this->template === null) {
            return;
        }

        foreach ($this->translation1 as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $wrappedValue = '<span dir="{{dir_language1}}" style="font-family:{{font_language1}};">' . $value . '</span>';
            $this->template = str_replace($placeholder, $wrappedValue, $this->template);
        }
    }

    private function replaceFontPlaceholders(): void
    {
        if ($this->template === null) {
            return;
        }

        $this->template = str_replace('{{dir_language1}}', $this->language1->getDirection(), $this->template);
        $this->template = str_replace('{{font_language1}}', $this->language1->getFont(), $this->template);
    }
}
