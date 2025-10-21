<?php
declare(strict_types=1);

namespace App\Models;

use JsonSerializable;
use App\Support\Caster;

/**
 * AskQuestionModel
 *
 * Canonical, constructor-less model with normalization at the boundaries.
 * Use populate(array $data) or individual setters. All string fields are
 * trimmed; language codes are lowercased; weight is clamped to >= 0.
 */
final class AskQuestionModel implements JsonSerializable
{
    private ?int $id = null;

    private string $languageCodeHL     = ''; // lower
    private string $name               = '';
    private string $ethnicName         = '';
    private string $url                = '';
    private string $contactPage        = '';
    private string $languageCodeTracts = ''; // lower
    private string $promoText          = '';
    private string $promoImage         = '';
    private string $tagline            = '';
    private int    $weight             = 0;

    /**
     * Hydrate from associative array (normalizes via setters).
     */
    public function populate(array $data): self
    {
        if (\array_key_exists('id', $data)) {
            $this->setId(Caster::toIntOrNull($data['id']));
        }
        if (\array_key_exists('languageCodeHL', $data)) {
            $this->setLanguageCodeHL(Caster::toLowerText((string)$data['languageCodeHL']));
        }
        if (\array_key_exists('name', $data)) {
            $this->setName(Caster::toText($data['name']));
        }
        if (\array_key_exists('ethnicName', $data)) {
            $this->setEthnicName(Caster::toText($data['ethnicName']));
        }
        if (\array_key_exists('url', $data)) {
            $this->setUrl(Caster::toText($data['url']));
        }
        if (\array_key_exists('contactPage', $data)) {
            $this->setContactPage(Caster::toText($data['contactPage']));
        }
        if (\array_key_exists('languageCodeTracts', $data)) {
            $this->setLanguageCodeTracts(Caster::toLowerText((string)$data['languageCodeTracts']));
        }
        if (\array_key_exists('promoText', $data)) {
            $this->setPromoText(Caster::toText($data['promoText']));
        }
        if (\array_key_exists('promoImage', $data)) {
            $this->setPromoImage(Caster::toText($data['promoImage']));
        }
        if (\array_key_exists('tagline', $data)) {
            $this->setTagline(Caster::toText($data['tagline']));
        }
        if (\array_key_exists('weight', $data)) {
            $this->setWeight(Caster::toNonNegativeIntOrZero($data['weight']));
        }

        return $this;
    }

    /**
     * Back-compat setter for DB rows (array or object).
     * Prefer using populate() with arrays going forward.
     */
    public function setValues(object|array $data): void
    {
        $arr = \is_array($data) ? $data : (array)$data;
        $this->populate($arr);
    }

    /** Canonical array representation. */
    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'languageCodeHL'     => $this->languageCodeHL,
            'name'               => $this->name,
            'ethnicName'         => $this->ethnicName,
            'url'                => $this->url,
            'contactPage'        => $this->contactPage,
            'languageCodeTracts' => $this->languageCodeTracts,
            'promoText'          => $this->promoText,
            'promoImage'         => $this->promoImage,
            'tagline'            => $this->tagline,
            'weight'             => $this->weight,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ---------------- Getters ----------------

    public function getId(): ?int { return $this->id; }
    public function getLanguageCodeHL(): string { return $this->languageCodeHL; }
    public function getName(): string { return $this->name; }
    public function getEthnicName(): string { return $this->ethnicName; }
    public function getUrl(): string { return $this->url; }
    public function getContactPage(): string { return $this->contactPage; }
    public function getLanguageCodeTracts(): string { return $this->languageCodeTracts; }
    public function getPromoText(): string { return $this->promoText; }
    public function getPromoImage(): string { return $this->promoImage; }
    public function getTagline(): string { return $this->tagline; }
    public function getWeight(): int { return $this->weight; }

    // ---------------- Setters (normalized) ----------------

    public function setId(?int $id): void
    {
        // Accept null or int; ignore non-numeric elsewhere via populate().
        $this->id = $id;
    }

    public function setLanguageCodeHL(string $v): void
    {
        $this->languageCodeHL = Caster::toLowerText($v);
    }

    public function setName(string $v): void
    {
        $this->name = Caster::toText($v);
    }

    public function setEthnicName(string $v): void
    {
        $this->ethnicName = Caster::toText($v);
    }

    public function setUrl(string $v): void
    {
        $this->url = Caster::toText($v);
    }

    public function setContactPage(string $v): void
    {
        $this->contactPage = Caster::toText($v);
    }

    public function setLanguageCodeTracts(string $v): void
    {
        $this->languageCodeTracts = Caster::toLowerText($v);
    }

    public function setPromoText(string $v): void
    {
        $this->promoText = Caster::toText($v);
    }

    public function setPromoImage(string $v): void
    {
        $this->promoImage = Caster::toText($v);
    }

    public function setTagline(string $v): void
    {
        $this->tagline = Caster::toText($v);
    }

    public function setWeight(int $v): void
    {
        $this->weight = \max(0, $v);
    }
}
