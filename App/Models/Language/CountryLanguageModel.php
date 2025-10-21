<?php
declare(strict_types=1);

namespace App\Models\Language;

use JsonSerializable;
use App\Support\Caster;

final class CountryLanguageModel implements JsonSerializable
{
    private ?int $id = null;
    private ?string $countryCode = null;        // ISO-3166 alpha-2, upper
    private ?string $languageCodeIso = null;    // lower
    private ?string $languageCodeHL = null;     // lowered
    private ?string $languageNameEnglish = null;
    private ?string $languageCodeJF = null;     // optional, computed (lower)

    /**
     * Populate from an associative array. Keys must match properties.
     * Safe-casts and normalizes values.
     */
    public function populate(array $data): void
    {
        if (\array_key_exists('id', $data)) {
            $this->setId(Caster::toIntOrNull($data['id']));
        }

        if (\array_key_exists('countryCode', $data)) {
            $cc = Caster::toTextOrNull($data['countryCode']);
            $this->setCountryCode($this->normCountry($cc));
        }

        if (\array_key_exists('languageCodeIso', $data)) {
            $iso = Caster::toTextOrNull($data['languageCodeIso']);
            $this->setLanguageCodeIso($this->normLang($iso));
        }

        if (\array_key_exists('languageCodeHL', $data)) {
            $hl = Caster::toTextOrNull($data['languageCodeHL']);
            $this->setLanguageCodeHL($this->normLang($hl));
        }

        if (\array_key_exists('languageNameEnglish', $data)) {
            $this->setLanguageNameEnglish(
                Caster::toTextOrNull($data['languageNameEnglish'])
            );
        }

        if (\array_key_exists('languageCodeJF', $data)) {
            $jf = Caster::toTextOrNull($data['languageCodeJF']);
            $this->setLanguageCodeJF($this->normLang($jf));
        }
    }

    /**
     * Canonical array representation for logging/JSON/etc.
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'countryCode'         => $this->countryCode,
            'languageCodeIso'     => $this->languageCodeIso,
            'languageCodeHL'      => $this->languageCodeHL,
            'languageNameEnglish' => $this->languageNameEnglish,
            'languageCodeJF'      => $this->languageCodeJF,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // --- Getters ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getLanguageCodeIso(): ?string
    {
        return $this->languageCodeIso;
    }

    public function getLanguageCodeHL(): ?string
    {
        return $this->languageCodeHL;
    }

    public function getLanguageNameEnglish(): ?string
    {
        return $this->languageNameEnglish;
    }

    public function getLanguageCodeJF(): ?string
    {
        return $this->languageCodeJF;
    }

    // --- Setters (safe-casting + normalization at the boundary) ---
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $this->normCountry($countryCode);
    }

    public function setLanguageCodeIso(?string $languageCodeIso): void
    {
        $this->languageCodeIso = $this->normLang($languageCodeIso);
    }

    public function setLanguageCodeHL(?string $languageCodeHL): void
    {
        $this->languageCodeHL = $this->normLang($languageCodeHL);
    }

    public function setLanguageNameEnglish(?string $name): void
    {
        $this->languageNameEnglish = $name === null
            ? null
            : Caster::toTextOrNull($name);
    }

    public function setLanguageCodeJF(?string $code): void
    {
        $this->languageCodeJF = $this->normLang($code);
    }

    /**
     * Compute and set languageCodeJF using a resolver: fn(string $hl): ?string
     */
    public function withLanguageCodeJF(callable $resolver): self
    {
        $hl = $this->languageCodeHL ?? '';
        $this->languageCodeJF = $hl !== '' ? $this->normLang(($resolver)($hl)) : null;
        return $this;
        // Note: resolver may return null; we normalize if not null.
    }

    /**
     * Batch helper: augment an array of models with JF codes.
     *
     * @param CountryLanguageModel[] $languages
     * @param callable(string):(?string) $resolver
     * @return CountryLanguageModel[]
     */
    public static function addLanguageCodeJF(
        array $languages,
        callable $resolver
    ): array {
        foreach ($languages as $lang) {
            if ($lang instanceof self) {
                $lang->withLanguageCodeJF($resolver);
            }
        }
        return $languages;
    }

    
}
