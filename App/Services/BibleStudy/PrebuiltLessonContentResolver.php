<?php
declare(strict_types=1);

namespace App\Services\BibleStudy;

use App\Configuration\Config;
use RuntimeException;
use JsonException;

final class PrebuiltLessonContentResolver
{
    /**
     * Validates JSON server-side, returns original JSON text unchanged.
     */
    public function fetch(string $languageCodeHL, string $study, string $lesson): string
    {
        $base = rtrim(Config::getDir('resources.prebuilt_lesson_content'), '/\\');

        $path = $base
            . '/'
            . $study
            . '/'
            . $languageCodeHL
            . '/'
            . $lesson
            . '.json';

        if (!is_file($path)) {
            throw new RuntimeException("Lesson content file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read lesson content file: {$path}");
        }

        // Validate JSON now (server-side), but do not re-encode for delivery.
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
}
