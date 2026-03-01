<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Configuration\Config;
use RuntimeException;
use JsonException;

final class PrebuiltLessonContentResolver
{
    /**
     * Returns validated JSON text if present, or null if the file does not exist.
     * Throws for unreadable or invalid JSON.
     */
    public function tryFetch(
        string $languageCodeHL,
        string $study,
        string $lesson
    ): ?string {
        $path = $this->buildPath($languageCodeHL, $study, $lesson);

        if (!is_file($path)) {
            return null;
        }

        return $this->fetch($languageCodeHL, $study, $lesson);
    }

    /**
     * Validates JSON server-side, returns original JSON text unchanged.
     */
    public function fetch(
        string $languageCodeHL,
        string $study,
        string $lesson
    ): string {
        $path = $this->buildPath($languageCodeHL, $study, $lesson);

        if (!is_file($path)) {
            throw new RuntimeException("Lesson content file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read lesson content file: {$path}");
        }

        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                "Invalid JSON in lesson content file: {$path} ({$e->getMessage()})",
                0,
                $e
            );
        }

        return $json;
    }

    private function buildPath(string $languageCodeHL, string $study, string $lesson): string
    {
        // Optional hardening: stop path traversal / weird chars
        // Keep if you want it strict.
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $study)) {
            throw new RuntimeException("Invalid study code: {$study}");
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $languageCodeHL)) {
            throw new RuntimeException("Invalid HL code: {$languageCodeHL}");
        }
        if (!preg_match('/^\d+$/', $lesson)) {
            throw new RuntimeException("Invalid lesson: {$lesson}");
        }

        $base = rtrim(Config::getDir('resources.prebuilt_lesson_content'), '/\\');

        return $base
            . '/' . $study
            . '/' . $languageCodeHL
            . '/' . $lesson
            . '.json';
    }
}
