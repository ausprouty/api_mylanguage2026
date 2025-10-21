<?php
declare(strict_types=1);

namespace App\Models\Bible;


use JsonSerializable;
use App\Support\Caster;


/**
 * Model for the `bible_book_names` table.
 */
class BibleBookNameModel implements JsonSerializable
{
    private ?int $id = null;           // PK
    private string $bookId = '';       // e.g., "GEN"
    private ?string $languageCodeIso = null; // deprecated
    private ?string $languageCodeHL = null;  // primary index code
    private string $name = '';         // localized book name

    /** Hydrate from an associative array (keys must match properties). */
    public function populate(array $data): self
    {
        if (\array_key_exists('id', $data)) {
            $this->id = Caster::toIntOrNull($data['id']);
        }
        if (\array_key_exists('bookId', $data)) {
            // e.g., "GEN" — keep uppercase for consistency
            $this->bookId = Caster::toTextUpper($data['bookId']);
        }
        if (\array_key_exists('languageCodeIso', $data)) {
            $this->languageCodeIso = Caster::toTextOrNull($data['languageCodeIso']);
        }
        if (\array_key_exists('languageCodeHL', $data)) {
            $this->languageCodeHL = Caster::toTextOrNull($data['languageCodeHL']);
        }
        if (\array_key_exists('name', $data)) {
            $this->name = Caster::toText($data['name']);
        }

        return $this;
    }

    

    /** Canonical array representation. */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'bookId'          => $this->bookId,
            'languageCodeIso' => $this->languageCodeIso,
            'languageCodeHL'  => $this->languageCodeHL,
            'name'            => $this->name,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getBookId(): string { return $this->bookId; }
    public function getLanguageCodeIso(): ?string
    { return $this->languageCodeIso; }
    public function getLanguageCodeHL(): ?string
    { return $this->languageCodeHL; }
    public function getName(): string { return $this->name; }

    // Setters
    public function setId(?int $id): void { $this->id = $id; }
    public function setBookId(string $bookId): void { $this->bookId = $bookId; }
    public function setLanguageCodeIso(?string $code): void
    { $this->languageCodeIso = $code; }
    public function setLanguageCodeHL(?string $code): void
    { $this->languageCodeHL = $code; }
    public function setName(string $name): void { $this->name = $name; }
}
