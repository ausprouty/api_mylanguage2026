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
    public function tryFetch(string $languageCodeHL, string $study, string $lesson): ?string
    {
        $base = rtrim(Config::getDir('resources.prebuilt_lesson_content'), '/\\');
        $path = $base . '/' . $study . '/' . $languageCodeHL . '/' . $lesson . '.json';

        if (!is_file($path)) {
            return null;
        }

        return $this->fetch($languageCodeHL, $study, $lesson);
    }

     /**
      * Validates JSON server-side, returns original JSON text unchanged.
      */
     public function fetch(string $languageCodeHL, string $study, string $lesson): string
     {

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
