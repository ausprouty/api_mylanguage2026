<?php
declare(strict_types=1);

namespace App\Models\Bible;

use JsonSerializable;
use App\Support\Caster;
use App\Interfaces\ArclightVideoInterface;

class PassageReferenceModel implements ArclightVideoInterface, JsonSerializable
{
    private ?string $entry = null;
    private ?string $bookName = null;
    private ?string $bookID = null;
    private ?string $uversionBookID = null;
    private ?int $bookNumber = null;
    private ?string $testament = null;
    private ?int $chapterStart = null;
    private ?int $verseStart = null;
    private ?int $chapterEnd = null;
    private ?int $verseEnd = null;
    private ?string $passageID = null;

    private ?string $videoSource = null;
    private ?string $videoPrefix = null;
    private ?string $videoCode = null;
    private ?string $videoSegment = null;
    private ?int $startTime = 0;
    private ?int $endTime = 0;

    public function populate(array $data): self
    {
        // Buckets used for casting
        $intKeys  = ['bookNumber','chapterStart','verseStart','chapterEnd','verseEnd'];
        $strKeys  = [
            'entry','bookName','bookID','uversionBookID','testament','passageID',
            'videoSource','videoPrefix','videoCode','videoSegment'
        ];
        $timeKeys = ['startTime','endTime']; // stored as strings (e.g., "MM:SS", "0")

        // Strings
        foreach ($strKeys as $k) {
            if (\array_key_exists($k, $data) && \property_exists($this, $k)) {
                $this->$k = Caster::toTextOrNull($data[$k]);
            }
        }

        // Ints (null or >= 0)
        foreach ($intKeys as $k) {
            if (\array_key_exists($k, $data) && \property_exists($this, $k)) {
                $this->$k = Caster::toIntOrNull($data[$k]);
            }
        }

        // Times -> canonical string or null
        foreach ($timeKeys as $k) {
            if (\array_key_exists($k, $data) && \property_exists($this, $k)) {
                $this->$k = Caster::toSecondsOrZero($data[$k]);
            }
        }

        // Light normalization for specific fields
        if ($this->bookID !== null)         { $this->bookID = \strtoupper($this->bookID); }
        if ($this->uversionBookID !== null) { $this->uversionBookID = \strtoupper($this->uversionBookID); }
        if ($this->videoSource !== null)    { $this->videoSource = \strtolower($this->videoSource); }
        if ($this->testament !== null)      { $this->testament = \strtoupper(\trim($this->testament)); }

        return $this;
    }

    

    public function toArray(): array
    {
        return [
            'entry'          => $this->entry,
            'bookName'       => $this->bookName,
            'bookID'         => $this->bookID,
            'uversionBookID' => $this->uversionBookID,
            'bookNumber'     => $this->bookNumber,
            'testament'      => $this->testament,
            'chapterStart'   => $this->chapterStart,
            'verseStart'     => $this->verseStart,
            'chapterEnd'     => $this->chapterEnd,
            'verseEnd'       => $this->verseEnd,
            'passageID'      => $this->passageID,
            'videoSource'    => $this->videoSource,
            'videoPrefix'    => $this->videoPrefix,
            'videoCode'      => $this->videoCode,
            'videoSegment'   => $this->videoSegment,
            'startTime'      => $this->startTime,
            'endTime'        => $this->endTime,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Getters
    public function getEntry(): ?string { return $this->entry; }
    public function getBookName(): ?string { return $this->bookName; }
    public function getBookID(): ?string { return $this->bookID; }
    public function getUversionBookID(): ?string { return $this->uversionBookID; }
    public function getBookNumber(): ?int { return $this->bookNumber; }
    public function getTestament(): ?string { return $this->testament; }
    public function getChapterStart(): ?int { return $this->chapterStart; }
    public function getVerseStart(): ?int { return $this->verseStart; }
    public function getChapterEnd(): ?int { return $this->chapterEnd; }
    public function getVerseEnd(): ?int { return $this->verseEnd; }
    public function getPassageID(): ?string { return $this->passageID; }
    public function getVideoSource(): ?string { return $this->videoSource; }
    public function getVideoPrefix(): ?string { return $this->videoPrefix; }
    public function getVideoCode(): ?string { return $this->videoCode; }
    public function getVideoSegment(): ?string { return $this->videoSegment; }
    public function getStartTime(): ?int { return $this->startTime; }
    public function getEndTime(): ?int { return $this->endTime; }

    // Setters
    public function setEntry(?string $v): void { $this->entry = $v; }
    public function setBookName(?string $v): void { $this->bookName = $v; }
    public function setBookID(?string $v): void { $this->bookID = $v; }
    public function setUversionBookID(?string $v): void { $this->uversionBookID = $v; }
    public function setBookNumber(?int $v): void { $this->bookNumber = $v; }
    public function setTestament(?string $v): void { $this->testament = $v; }
    public function setChapterStart(?int $v): void { $this->chapterStart = $v; }
    public function setVerseStart(?int $v): void { $this->verseStart = $v; }
    public function setChapterEnd(?int $v): void { $this->chapterEnd = $v; }
    public function setVerseEnd(?int $v): void { $this->verseEnd = $v; }
    public function setPassageID(?string $v): void { $this->passageID = $v; }

    public function setVideoSource(?string $v): void { $this->videoSource = $v; }
    public function setVideoPrefix(?string $v): void { $this->videoPrefix = $v; }
    public function setVideoCode(?string $v): void { $this->videoCode = $v; }
    public function setVideoSegment(?string $v): void { $this->videoSegment = $v; }

    /**
     * Accepts int seconds, "SS", "MM:SS", "HH:MM:SS", "start", "", null.
     * Also tolerates accidental double colons like "MM::SS".
     * Always stores a non-negative integer.
     */
    public function setStartTime(int|string|null $value): void
    {
        if (is_string($value)) {
            // Collapse any accidental multiple colons (e.g., "MM::SS" -> "MM:SS")
            $value = preg_replace('/:+/', ':', trim($value));
        }
        $this->startTime = Caster::toSecondsOrZero($value);
    }

    /**
     * Accepts int seconds, "SS", "MM:SS", "HH:MM:SS", "", null.
     * Also tolerates accidental double colons like "MM::SS".
     * Always stores a non-negative integer.
     */
    public function setEndTime(int|string|null $value): void
    {
        if (is_string($value)) {
            $value = preg_replace('/:+/', ':', trim($value));
        }
        $this->endTime = Caster::toSecondsOrZero($value);
    }
}
