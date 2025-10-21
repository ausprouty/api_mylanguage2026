<?php
declare(strict_types=1);

namespace App\Models\Video;

use JsonSerializable;
use App\Support\Caster;

final class VideoModel implements JsonSerializable
{
    private const SOURCES = ['arclight', 'vimeo', 'youtube'];

    private int $id = 0;
    private string $title = '';           // <= 100 chars in DB
    private string $verses = '';          // <= 25 chars in DB

    private string $videoSource = 'arclight'; // enum in DB
    private string $videoPrefix = '';         // <= 15 chars, lower
    private string $videoCode   = '-jf';      // <= 100 chars
    private string $videoSegment = '';        // <= 15 chars (raw token)

    private int $startTime = 0; // 0 = start
    private int $stopTime  = 0; // 0 = no end

    // -------- Hydration (canonical/clean only) --------

    /**
     * Populate with already-normalized values.
     * Factories should convert strings, "MM:SS", etc. BEFORE calling this.
     */
    public function populate(array $data): self
    {
        if (\array_key_exists('id', $data)) {
            $this->setId(Caster::toNonNegativeIntOrZeroOrZero($data['id']));
        }
        if (\array_key_exists('title', $data)) {
            $this->setTitle(Caster::toText($data['title']));
        }
        if (\array_key_exists('verses', $data)) {
            $this->setVerses(Caster::toText($data['verses']));
        }
        if (\array_key_exists('videoSource', $data)) {
            $this->setVideoSource(Caster::toLowerText($data['videoSource']));
        }
        if (\array_key_exists('videoPrefix', $data)) {
            $this->setVideoPrefix(Caster::toLowerText($data['videoPrefix']));
        }
        if (\array_key_exists('videoCode', $data)) {
            $this->setVideoCode(Caster::toText($data['videoCode']));
        }
        if (\array_key_exists('videoSegment', $data)) {
            // Accept raw token only (factory strips ?/&/segment=)
            $this->setVideoSegment(Caster::toText($data['videoSegment']));
        }
        if (\array_key_exists('startTime', $data)) {
            $this->setStartTime(
                Caster::toNonNegativeIntOrZero($data['startTime'])
            );
        }
        if (\array_key_exists('stopTime', $data)) {
            $this->setStopTime(
                Caster::toNonNegativeIntOrZero($data['stopTime'])
            );
        }
        return $this;
    }

    // -------- Projection --------

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'verses'              => $this->verses,
            'videoSource'         => $this->videoSource,
            'videoPrefix'         => $this->videoPrefix,
            'videoCode'           => $this->videoCode,
            'videoSegment'        => $this->videoSegment,
            'startTime'           => $this->startTime,
            'stopTime'            => $this->stopTime,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // -------- Getters --------

    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getVerses(): string { return $this->verses; }

    public function getVideoSource(): string { return $this->videoSource; }
    public function getVideoPrefix(): string { return $this->videoPrefix; }
    public function getVideoCode(): string { return $this->videoCode; }
    public function getVideoSegment(): string { return $this->videoSegment; }

    public function getStartTime(): int
    { return $this->startTime; }

    public function getStopTime(): int
    { return $this->stopTime; }

    // -------- Setters (normalize/guard at boundary) --------

    public function setId(int $id): void
    {
        $this->id = \max(0, $id);
    }

    public function setTitle(string $title): void
    {
        $this->title = Caster::toText($title);
    }

    public function setVerses(string $verses): void
    {
        $this->verses = Caster::toText($verses);
    }

    public function setVideoSource(string $source): void
    {
        $s = Caster::toLowerText($source);
        $this->videoSource = \in_array($s, self::SOURCES, true) ? $s : 'arclight';
    }

    public function setVideoPrefix(string $prefix): void
    {
        $this->videoPrefix = Caster::toLowerText($prefix);
    }

    public function setVideoCode(string $code): void
    {
        $this->videoCode = Caster::toText($code);
    }

    /**
     * Store the raw token only (e.g., "JESUS-123").
     * Factories should strip leading '?', '&', and "segment=" if present.
     */
    public function setVideoSegment(string $segment): void
    {
        $this->videoSegment = Caster::toText($segment);
    }

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
    public function setStopTime(int|string|null $value): void
    {
        if (is_string($value)) {
            $value = preg_replace('/:+/', ':', trim($value));
        }
        $this->stopTime = Caster::toSecondsOrZero($value);
    }
}
