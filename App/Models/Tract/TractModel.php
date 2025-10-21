<?php
declare(strict_types=1);

namespace App\Models\Tract;

use JsonSerializable;
use App\Support\Caster;

final class TractModel implements JsonSerializable
{
    private ?int $id = null;
    private ?string $languageCodeHL1 = null; // lowercased
    private ?string $languageCodeHL2 = null; // lowercased
    private string $name = '';
    private string $webpage = '';
    private ?bool $valid = null;
    private ?string $validMessage = null;

    /**
     * Populate from an associative array with safe casting/normalization.
     */
    public function populate(array $data): self
    {
        if (\array_key_exists('id', $data)) {
            $this->setId(Caster::toIntOrNull($data['id']));
        }

        if (\array_key_exists('languageCodeHL1', $data)) {
            $this->setLanguageCodeHL1(Caster::toLowerTextOrNull($data['languageCodeHL1']));
        }

        if (\array_key_exists('languageCodeHL2', $data)) {
            $this->setLanguageCodeHL2(Caster::toLowerTextOrNull($data['languageCodeHL2']));
        }

        if (\array_key_exists('name', $data)) {
            $this->setName(Caster::toText($data['name']));
        }

        if (\array_key_exists('webpage', $data)) {
            $this->setWebpage(Caster::toText($data['webpage']));
        }

        if (\array_key_exists('valid', $data)) {
            $this->setValid($data['valid'] === null ? null : Caster::toBool($data['valid']));
        }

        if (\array_key_exists('validMessage', $data)) {
            $this->setValidMessage(Caster::toTextOrNull($data['validMessage']));
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'languageCodeHL1' => $this->languageCodeHL1,
            'languageCodeHL2' => $this->languageCodeHL2,
            'name'            => $this->name,
            'webpage'         => $this->webpage,
            'valid'           => $this->valid,
            'validMessage'    => $this->validMessage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // --- Getters
    public function getId(): ?int { return $this->id; }
    public function getLanguageCodeHL1(): ?string { return $this->languageCodeHL1; }
    public function getLanguageCodeHL2(): ?string { return $this->languageCodeHL2; }
    public function getName(): string { return $this->name; }
    public function getWebpage(): string { return $this->webpage; }
    public function isValid(): ?bool { return $this->valid; }
    public function getValidMessage(): ?string { return $this->validMessage; }

    // --- Setters (normalize at the boundary)
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setLanguageCodeHL1(?string $code): void
    {
        $this->languageCodeHL1 = Caster::toLowerTextOrNull($code);
    }

    public function setLanguageCodeHL2(?string $code): void
    {
        $this->languageCodeHL2 = Caster::toLowerTextOrNull($code);
    }

    public function setName(string $name): void
    {
        $this->name = Caster::toText($name);
    }

    public function setWebpage(string $url): void
    {
        $this->webpage = Caster::toText($url);
    }

    public function setValid(?bool $valid): void
    {
        $this->valid = $valid;
    }

    public function setValidMessage(?string $msg): void
    {
        $this->validMessage = Caster::toTextOrNull($msg);
    }
}
