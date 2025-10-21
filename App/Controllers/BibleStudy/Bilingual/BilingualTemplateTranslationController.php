<?php
namespace App\Controllers\BibleStudy\Bilingual;

use App\Contracts\Translation\TranslationService; // the interface
use App\Configuration\Config;
use App\Services\LoggerService;

final class BilingualTemplateTranslationController
{
    private string $templateName;
    private string $translationFile;
    private ?string $template = null;
    private array $translation1 = [];
    private array $translation2 = [];
    private string $languageCodeHL1;
    private string $languageCodeHL2;

    public function __construct(
        string $templateName,
        string $translationFile,
        string $languageCodeHL1,
        string $languageCodeHL2,
        private TranslationService $translation // injected ONCE
    ) {
        $this->templateName   = $templateName;
        $this->translationFile= $translationFile;
        $this->languageCodeHL1= $languageCodeHL1;
        $this->languageCodeHL2= $languageCodeHL2;

        $this->setTemplate();
        $this->setTranslation1();
        $this->setTranslation2();
        $this->replacePlaceholders();
    }

    public function getTemplate(): ?string { return $this->template; }

    private function setTemplate(): void
    {
        $filename = Config::getDir('resources.templates') . $this->templateName . '.twig';
        if (!file_exists($filename)) {
            LoggerService::logError('BilingualTemplateTranslationController-28', 'ERROR - no such template as ' . $filename);
            return;
        }
        $this->template = file_get_contents($filename);
    }

    private function setTranslation1(): void
    {
        // call your service for language 1 (adjust method name to yours)
        $this->translation1 = $this->translation->getTranslationFile($this->languageCodeHL1, $this->translationFile);
    }

    private function setTranslation2(): void
    {
        // same service, different language
        $this->translation2 = $this->translation->getTranslationFile($this->languageCodeHL2, $this->translationFile);
    }

    private function replacePlaceholders(): void
    {
        if ($this->template === null) return;

        foreach ($this->translation1 as $key => $value) {
            $this->template = str_replace('{{' . $key . '}}', $value, $this->template);
        }
        foreach ($this->translation2 as $key => $value) {
            $this->template = str_replace('||' . $key . '||', $value, $this->template);
        }
    }
}
