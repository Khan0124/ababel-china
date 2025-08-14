<?php
namespace App\Core;

class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Convert all errors to exceptions to be handled uniformly
        self::log('php_error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        $isApi = isset($_SERVER['REQUEST_URI']) && str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/api/');

        self::log('exception', [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        http_response_code(500);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Internal Server Error',
            ]);
        } else {
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p>An internal error occurred. Please try again later.</p>";
        }
    }

    private static function log(string $event, array $context): void
    {
        $payload = json_encode([
            'event' => $event,
            'timestamp' => date('c'),
            'context' => $context,
        ]);
        error_log($payload);
    }
}