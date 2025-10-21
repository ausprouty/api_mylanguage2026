<?php
declare(strict_types=1);

namespace App\Models\Country;

use JsonSerializable;
use App\Support\Caster;

/**
 * CountryModel
 *
 * Constructor-less, normalized model:
 *  - Codes (country/continent) are uppercased; empty => null
 *  - Names are trimmed; empty => null
 *  - EU membership is tri-state (?bool). populate() parses truthy/falsey.
 */
final class CountryModel implements JsonSerializable
{
    private ?string $countryCodeIso      = null; // e.g., "AU"
    private ?string $countryCodeIso3     = null; // e.g., "AUS"
    private ?string $countryNameEnglish  = null;
    private ?string $countryName         = null; // local or preferred name
    private ?string $continentCode       = null; // e.g., "OC", "EU"
    private ?string $continentName       = null; // e.g., "Oceania"
    private ?bool   $inEuropeanUnion     = null;

    /**
     * Populate from an associative array.
     * Back-compat: accepts legacy 'contenentName' and maps it to 'continentName'.
     */
    public function populate(array $data): void
    {
        // Back-compat key mapping
        if (\array_key_exists('contenentName', $data) && !\array_key_exists('continentName', $data)) {
            $data['continentName'] = $data['contenentName'];
        }

        if (\array_key_exists('countryCodeIso', $data)) {
            $this->setCountryCodeIso($data['countryCodeIso']);
        }
        if (\array_key_exists('countryCodeIso3', $data)) {
            $this->setCountryCodeIso3($data['countryCodeIso3']);
        }
        if (\array_key_exists('countryNameEnglish', $data)) {
            $this->setCountryNameEnglish($data['countryNameEnglish']);
        }
        if (\array_key_exists('countryName', $data)) {
            $this->setCountryName($data['countryName']);
        }
        if (\array_key_exists('continentCode', $data)) {
            $this->setContinentCode($data['continentCode']);
        }
        if (\array_key_exists('continentName', $data)) {
            $this->setContinentName($data['continentName']);
        }
        if (\array_key_exists('inEuropeanUnion', $data)) {
            $this->setInEuropeanUnion(
                $data['inEuropeanUnion'] === null ? null : Caster::toBool($data['inEuropeanUnion'])
            );
        }
    }

    public function toArray(): array
    {
        return [
            'countryCodeIso'     => $this->countryCodeIso,
            'countryCodeIso3'    => $this->countryCodeIso3,
            'countryNameEnglish' => $this->countryNameEnglish,
            'countryName'        => $this->countryName,
            'continentCode'      => $this->continentCode,
            'continentName'      => $this->continentName,
            'inEuropeanUnion'    => $this->inEuropeanUnion,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // --- Getters ---
    public function getCountryCodeIso(): ?string       { return $this->countryCodeIso; }
    public function getCountryCodeIso3(): ?string      { return $this->countryCodeIso3; }
    public function getCountryNameEnglish(): ?string   { return $this->countryNameEnglish; }
    public function getCountryName(): ?string          { return $this->countryName; }
    public function getContinentCode(): ?string        { return $this->continentCode; }
    public function getContinentName(): ?string        { return $this->continentName; }
    public function isInEuropeanUnion(): ?bool         { return $this->inEuropeanUnion; }

    // --- Setters (normalize at the boundary) ---
    public function setCountryCodeIso(?string $code): void
    {
        $s = Caster::toTextOrNull($code);
        $this->countryCodeIso = $s === null ? null : \strtoupper($s);
    }

    public function setCountryCodeIso3(?string $code3): void
    {
        $s = Caster::toTextOrNull($code3);
        $this->countryCodeIso3 = $s === null ? null : \strtoupper($s);
    }

    public function setCountryNameEnglish(?string $name): void
    {
        $this->countryNameEnglish = Caster::toTextOrNull($name);
    }

    public function setCountryName(?string $name): void
    {
        $this->countryName = Caster::toTextOrNull($name);
    }

    public function setContinentCode(?string $code): void
    {
        $s = Caster::toTextOrNull($code);
        $this->continentCode = $s === null ? null : \strtoupper($s);
    }

    public function setContinentName(?string $name): void
    {
        $this->continentName = Caster::toTextOrNull($name);
    }

    public function setInEuropeanUnion(?bool $inEu): void
    {
        $this->inEuropeanUnion = $inEu;
    }
}
