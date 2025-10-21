<?php
declare(strict_types=1);

namespace App\Models\Language;

use JsonSerializable;

class DbsLanguageModel implements JsonSerializable
{
    private ?string $languageCodeHL = null; //lowercased
    private ?string $collectionCode = null; //lowercased
    private ?string $format = null;  //lowercased

    /**
     * Populate from an associative array. Keys must match properties.
     */
    public function populate(array $data): void
    {
        if (\array_key_exists('languageCodeHL', $data)) {
            $this->setLanguageCodeHL(Caster::toLowerTextOrNull($data['languageCodeHL']));
        }  
        if (\array_key_exists('collectionCode', $data)) {
            $this->setCollectionCode(Caster::toLowerTextOrNull($data['collectionCode']));
        }
        if (\array_key_exists('format', $data)) {
            $this->setFormat(Caster::toLowerTextOrNull($data['format']));
        }
    }

    /**
     * Canonical array representation for logging/JSON/etc.
     */
    public function toArray(): array
    {
        return [
            'languageCodeHL' => $this->languageCodeHL,
            'collectionCode' => $this->collectionCode,
            'format'         => $this->format,
        ];
    }

    /**
     * JsonSerializable implementation delegates to toArray().
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Getters
    public function getLanguageCodeHL(): ?string
    {
        return $this->languageCodeHL;
    }

    public function getCollectionCode(): ?string
    {
        return $this->collectionCode;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    // Setters
    public function setLanguageCodeHL(?string $languageCodeHL): void
    {
        $this->languageCodeHL = Caster::toLowerTextOrNull($languageCodeHL);
    }

    public function setCollectionCode(?string $collectionCode): void
    {
       $this->collectionCode = Caster::toLowerTextOrNull($collectionCode);
    }

    public function setFormat(?string $format): void
    {
        $this->format = Caster::toLowerTextOrNull($format);
    }
}
