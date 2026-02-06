<?php
declare(strict_types=1);

namespace App\Models\Bible;

use JsonSerializable;
use App\Support\Caster;

class PassageModel implements JsonSerializable
{
    private string  $bpid = '';
    private ?string $dateChecked = null;   // YYYY-MM-DD
    private ?string $dateLastUsed = null;  // YYYY-MM-DD
    private string  $passageText = '';
    private string  $passageUrl  = '';
    private string  $referenceLocalLanguage = '';
    private int     $timesUsed = 0;

    /** Hydrate from an associative array (keys must match properties). */
    public function populate(array $data): self
{
    // Non-nullable strings
    foreach (['bpid','passageText','passageUrl','referenceLocalLanguage'] as $k) {
        if (array_key_exists($k, $data)) {
            $this->$k = Caster::toString($data[$k]);
        }
    }

    // Dates (nullable, strict YYYY-MM-DD or DateTimeInterface)
    foreach (['dateChecked','dateLastUsed'] as $k) {
        if (array_key_exists($k, $data)) {
            $this->$k = Caster::toDateYmdOrNull($data[$k]);
        }
    }

    // Non-negative int
    if (array_key_exists('timesUsed', $data)) {
        $this->timesUsed = Caster::toNonNegativeIntOrZero($data['timesUsed']);
    }

    return $this;
}




    /** Canonical array representation. */
    public function toArray(): array
    {
        return [
            'bpid'                   => $this->bpid,
            'dateChecked'            => $this->dateChecked,
            'dateLastUsed'           => $this->dateLastUsed,
            'passageText'            => $this->passageText,
            'passageUrl'             => $this->passageUrl,
            'referenceLocalLanguage' => $this->referenceLocalLanguage,
            'timesUsed'              => $this->timesUsed,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Getters
    public function getBpid(): string { return $this->bpid; }
    public function getDateChecked(): ?string { return $this->dateChecked; }
    public function getDateLastUsed(): ?string { return $this->dateLastUsed; }
    public function getPassageText(): string { return $this->passageText; }
    public function getPassageUrl(): string { return $this->passageUrl; }
    public function getReferenceLocalLanguage(): string
    { return $this->referenceLocalLanguage; }
    public function getTimesUsed(): int { return $this->timesUsed; }

    // Setters
    public function setBpid(string $bpid): void { $this->bpid = $bpid; }

    public function setDateChecked(?string $date): void
    {
        $this->assertDateOrNull($date, 'dateChecked');
        $this->dateChecked = $date;
    }

    public function setDateLastUsed(?string $date): void
    {
        $this->assertDateOrNull($date, 'dateLastUsed');
        $this->dateLastUsed = $date;
    }

    public function setPassageText(string $text): void
    { $this->passageText = $text; }

    public function setPassageUrl(string $url): void
    { $this->passageUrl = $url; }

    public function setReferenceLocalLanguage(string $ref): void
    {
        $this->referenceLocalLanguage =
            $this->normalizeReferenceLocalLanguage($ref);
    }

    public function setTimesUsed(int $times): void
    { $this->timesUsed = $times; }

    /** Increment usage and stamp today’s date (YYYY-MM-DD). */
    public function updateUsage(): void
    {
        $this->dateLastUsed = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->timesUsed++;
    }

    /** Build an ID like "John:3:16-18". */
    public static function createBiblePassageId(
        string $book,
        int $chapter,
        ?int $verseStart = null,
        ?int $verseEnd = null
    ): string {
        $parts = [$book, (string) $chapter];
        if ($verseStart !== null) {
            $range = (string) $verseStart;
            if ($verseEnd !== null && $verseEnd > $verseStart) {
                $range .= '-' . $verseEnd;
            }
            $parts[] = $range;
        }
        return \implode(':', $parts);
    }

    // Internal
    private function assertDateOrNull(?string $date, string $field): void
    {
        if ($date === null) return;
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException(
                "Invalid date format for {$field}; expected YYYY-MM-DD"
            );
        }
    }

    /**
     * Normalise the local-language reference so it only contains
     * "BookName Chapter[:Verse[-Verse]]" and strips any trailing
     * translation names or extra lines.
     */
    private function normalizeReferenceLocalLanguage(
        string $value
    ): string {
        // Trim basic whitespace first
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        // 1) Remove everything after the first newline
        //    e.g. "Лука 18:18-30\nБиблия, нов превод ..." -> "Лука 18:18-30"
        $value = preg_replace('~\R.*$~u', '', $value);

        // 2) Keep "BookName Chapter[:Verse[-Verse]]" only
        //    Examples:
        //      "2 Corinthians 5:16-21 NIV" -> "2 Corinthians 5:16-21"
        //      "1 John 5:1-7"              -> "1 John 5:1-7"
        //      "Лука 18:18-30 ..."         -> "Лука 18:18-30"
        if (preg_match(
            '~^(.+?)\s+(\d+(?::\d+(?:[-–]\d+)?)?)~u',
            $value,
            $m
        )) {
            $value = trim($m[1] . ' ' . $m[2]);
        }
        return trim($value);
    }
}
