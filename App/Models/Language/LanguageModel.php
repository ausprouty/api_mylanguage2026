<?php
declare(strict_types=1);

namespace App\Models\Language;

use JsonSerializable;
use App\Support\Caster;

/**
 * Represents a Language entity with associated properties and methods.
 */
class LanguageModel implements JsonSerializable
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $ethnicName = null;

    // Many code fields are identifiers; store as string.
    private ?int $languageCodeBibleBrain = null;
    private ?string $languageCodeBing = null;
    private ?string $languageCodeBrowser = null;
    private ?string $languageCodeDrupal = null;
    private ?string $languageCodeGoogle = null;
    private ?string $languageCodeHL = null;  
    private ?string $languageCodeIso = null;
    private ?int $languageCodeJF = null;
    private ?string $languageCodeTracts = null;
    private ?string $direction = null;
    private ?string $numeralSet = null;
    private ?bool $isChinese = false;
    private ?bool $isHindu = false;
    private ?bool $celebrateLunarNewYear = false;
    private ?string $font = null;
    private ?string $fontData = null;
    private ?string $mylanguage = null;
    private ?int $requests = null;
    private ?string $checkedBBBibles = null;

    /**
     * Populate the model from an associative array. Keys must match properties.
     */
    public function populate(array $data): void
    {
        // which keys need special casting
        $boolKeys = ['isChinese','isHindu','celebrateLunarNewYear'];
        $intKeys  = ['id','languageCodeBibleBrain','languageCodeJF','requests'];

        foreach ($data as $key => $value) {
            if (!\property_exists($this, $key)) {
                continue;
            }
            if (\in_array($key, $boolKeys, true)) {
                // Accept 0/1 or '0'/'1' from DB and cast to bool
                $this->$key = (bool)$value;
                continue;
            }
            if (\in_array($key, $intKeys, true)) {
                $this->$key = ($value === null ? null : (int)$value);
                continue;
            }
            // everything else as-is
            $this->$key = $value;
        }
    }

    /**
     * Canonical array representation for logging/JSON/etc.
     */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'ethnicName'             => $this->ethnicName,
            'languageCodeBibleBrain' => $this->languageCodeBibleBrain,
            'languageCodeBing'       => $this->languageCodeBing,
            'languageCodeBrowser'    => $this->languageCodeBrowser,
            'languageCodeDrupal'     => $this->languageCodeDrupal,
            'languageCodeGoogle'     => $this->languageCodeGoogle,
            'languageCodeHL'         => $this->languageCodeHL,
            'languageCodeIso'        => $this->languageCodeIso,
            'languageCodeJF'         => $this->languageCodeJF,
            'languageCodeTracts'     => $this->languageCodeTracts,
            'direction'              => $this->direction,
            'numeralSet'             => $this->numeralSet,
            'isChinese'              => $this->isChinese,
            'isHindu'                => $this->isHindu,
            'celebrateLunarNewYear'  => $this->celebrateLunarNewYear,
            'font'                   => $this->font,
            'fontData'               => $this->fontData,
            'mylanguage'             => $this->mylanguage,
            'requests'               => $this->requests,
            'checkedBBBibles'        => $this->checkedBBBibles,
        ];
    }

    /**
     * JsonSerializable implementation delegates to toArray().
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Debug-only: safe property dump without deprecated setAccessible().
     * Note: only includes visible state via getters + known fields.
     */
    public function debugProperties(): array
    {
        // Prefer explicit toArray; this method exists for quick dev inspection.
        return $this->toArray();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function getEthnicName(): ?string { return $this->ethnicName; }

    public function getLanguageCodeBibleBrain(): ?int
    { return $this->languageCodeBibleBrain; }

    public function getLanguageCodeBing(): ?string
    { return $this->languageCodeBing; }

    public function getLanguageCodeBrowser(): ?string
    { return $this->languageCodeBrowser; }

    public function getLanguageCodeDrupal(): ?string
    { return $this->languageCodeDrupal; }

    public function getLanguageCodeGoogle(): ?string
    { return $this->languageCodeGoogle; }

    public function getLanguageCodeHL(): ?string
    { return $this->languageCodeHL; }

    public function getLanguageCodeIso(): ?string
    { return $this->languageCodeIso; }

    public function getLanguageCodeJF(): ?int
    { return $this->languageCodeJF; }

    public function getLanguageCodeTracts(): ?string
    { return $this->languageCodeTracts; }

    public function getDirection(): ?string
    { return $this->direction; }

    public function getNumeralSet(): ?string
    { return $this->numeralSet; }

    public function getIsChinese(): ?bool
    { return $this->isChinese; }

    public function getIsHindu(): ?bool
    { return $this->isHindu; }

    public function getCelebrateLunarNewYear(): ?bool
    { return $this->celebrateLunarNewYear; }

    public function getFont(): ?string
    { return $this->font; }

    public function getFontData(): ?string
    { return $this->fontData; }

    public function getMyLanguage(): ?string
    { return $this->mylanguage; }

    public function getRequests(): ?int
    { return $this->requests; }

    public function getCheckedBBBibles(): ?string
    { return $this->checkedBBBibles; }
}
