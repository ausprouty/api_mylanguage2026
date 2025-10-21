<?php

namespace App\Services;

use App\Configuration\Config;

/**
 * Service for managing the storage and retrieval of study content files.
 * Files are stored in a subdirectory of the ROOT_RESOURCES directory.
 */
class ResourceStorageService
{
    /**
     * @var string $storagePath The absolute path to the storage directory.
     */
    private $storagePath;

    /**
     * Constructor for the StorageService.
     *
     * @param string $storagePath The relative path within ROOT_RESOURCES for storing files.
     */
    public function __construct(string $storagePath)
    {
        // Combine ROOT_RESOURCES with the provided storage subdirectory
        $this->storagePath = Config::getDir('resources.root') . $storagePath;
    }

    /**
     * Retrieves a stored file's content by its key.
     *
     * @param string $key The unique key (filename) identifying the stored file.
     * @return string|null The content of the file if it exists, or null if the file is not found.
     */
    public function retrieve(string $key): ?string
    {
        // Construct the full file path
        $filePath = realpath($this->storagePath . '/' . $key);

        // Normalize paths for case-insensitive comparison
        $normalizedFilePath = str_replace('\\', '/', strtolower($filePath));
        $normalizedStoragePath = str_replace('\\', '/', strtolower($this->storagePath));

        // Check if the file exists within the allowed storage path
        if (
            $filePath === false ||
            strpos($normalizedFilePath, $normalizedStoragePath) !== 0
            || !file_exists($filePath)
        ) {
            return null; // File does not exist or is outside the storage path
        }

        // Return the file content
        return file_get_contents($filePath);
    }


    /**
     * Stores content in a file identified by a unique key.
     *
     * @param string $key The unique key (filename) for storing the file.
     * @param string $content The content to be written to the file.
     * @return void
     */
    public function store(string $key, string $content): void
    {
        // Construct the full file path
        $filePath = $this->storagePath . '/' . $key;

        // Write the content to the file
        file_put_contents($filePath, $content);
    }
}
