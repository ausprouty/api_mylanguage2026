<?php

use App\Configuration\Config;

/**
 * Writes a log entry to a file.
 * 
 * @param string $filename The name of the log file.
 * @param mixed $content The content to write to the log.
 */
function writeLog($filename, $content)
{
    if (
        Config::get('logging.mode') !== 'write_log'
        && Config::get('logging.mode') !== 'write_time_log'
    ) {
        return;
    }

    // Validate and sanitize the filename
    $filename = validateFilename($filename);

    // Append timestamp to filename if log mode is set to write_time_log
    if (Config::get('logging.mode') == 'write_time_log') {
        $filename = time() . '-' . $filename;
    }

    // Convert the content to a string format for logging
    $text = var_dump_ret($content);

    // Ensure the log directory exists before writing
    ensureLogDirectoryExists();

    // Construct the full file path
    $filePath = Config::getDir('logs') . $filename . '.txt';

    // Write log content to the file, log to error log if writing fails
    if (file_put_contents($filePath, $text) === false) {
        error_log("Failed to write log to $filePath");
    }
}

/**
 * Appends content to an existing log file.
 * 
 * @param string $filename The name of the log file.
 * @param mixed $content The content to append to the log.
 */
function writeLogAppend($filename, $content)
{
    $filename = validateFilename($filename);
    $text = var_dump_ret($content);
    ensureLogDirectoryExists();
    $filePath = Config::getDir('logs')  . 'APPEND-' . $filename . '.txt';

    // Append content to the file and lock it to prevent concurrent writes
    if (file_put_contents($filePath, $text, FILE_APPEND | LOCK_EX) === false) {
        error_log("Failed to append log to $filePath");
    }
}

/**
 * Writes a debug log entry to a file.
 * 
 * @param string $filename The name of the debug log file.
 * @param mixed $content The content to write to the debug log.
 */
function writeLogDebug($filename, $content)
{
    $filename = validateFilename($filename);
    $text = var_dump_ret($content);
    ensureLogDirectoryExists();
    $filePath = Config::getDir('logs') . 'DEBUG-' . $filename . '.txt';

    // Write debug log content to the file, log to error log if writing fails
    if (file_put_contents($filePath, $text) === false) {
        error_log("Failed to write debug log to $filePath");
    }
}

/**
 * Writes an error log entry to a file.
 * 
 * @param string $filename The name of the error log file.
 * @param mixed $content The content to write to the error log.
 */
function writeLogError($filename, $content)
{
    $filename = validateFilename($filename);
    $text = var_dump_ret($content);
    ensureLogDirectoryExists();
    $filePath = Config::getDir('logs')  . 'ERROR-' . $filename . '.txt';

    // Write error log content to the file, log to error log if writing fails
    if (file_put_contents($filePath, $text) === false) {
        error_log("Failed to write error log to $filePath");
    }
}

/**
 * Converts mixed content to a string using var_dump and returns it.
 * 
 * @param mixed $mixed The content to be dumped.
 * @return string The dumped content as a string.
 */
function var_dump_ret($mixed = null)
{
    ob_start();
    var_dump($mixed);
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}

/**
 * Ensures the log directory exists, creates it if it doesn't.
 * 
 * @throws RuntimeException If the directory cannot be created.
 */
function ensureLogDirectoryExists()
{
    $dir = Config::getDir('logs');
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }
}

/**
 * Validates and sanitizes a filename by replacing invalid characters.
 * 
 * @param string $filename The filename to validate.
 * @return string The sanitized filename.
 */
function validateFilename($filename)
{
    if (empty($filename)) {
        $filename = 'log-' . time();
    }
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename); // Sanitize filename
}
