<?php
namespace App\Support;

use App\Services\LoggerService;

final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        LoggerService::logError('Exception', $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        JsonResponder::out([
            'status'  => 'error',
            'message' => 'Server error',
        ], 500);
    }

    public static function handleError(
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        // Convert all errors to exceptions so we end up in handleException.
        self::handleException(new \ErrorException(
            $message, 0, $severity, $file, $line
        ));
        return true; // prevent PHP default handler
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) return;

        LoggerService::logCritical('Shutdown', $err['message'], [
            'file' => $err['file'] ?? null,
            'line' => $err['line'] ?? null,
        ]);

        // Safe minimal output (headers may already be sent)
        if (!headers_sent()) {
            JsonResponder::out([
                'status'  => 'error',
                'message' => 'Fatal error',
            ], 500);
        }
    }
}
