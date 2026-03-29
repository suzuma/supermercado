<?php
namespace Core;

use Throwable;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Phroute\Phroute\Exception\HttpMethodNotAllowedException;

class ExceptionHandler {

    public static function register(): void {
        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handle(Throwable $e): void {
        [$status, $title, $message] = self::classify($e);

        Log::error(self::class, sprintf(
            '[%s] %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        if (self::expectsJson()) {
            self::jsonResponse($status, $message);
        } else {
            self::htmlResponse($status, $title, $message);
        }
    }

    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = sprintf('%s in %s:%d', $error['message'], $error['file'], $error['line']);
            Log::critical(self::class, '[FatalError] ' . $message);

            if (self::expectsJson()) {
                self::jsonResponse(500, 'Error interno del servidor');
            } else {
                self::htmlResponse(500, 'Error interno', 'Algo salió mal. Por favor intenta más tarde.');
            }
        }
    }

    // -------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------
    private static function classify(Throwable $e): array {
        if ($e instanceof HttpRouteNotFoundException) {
            return [404, 'Página no encontrada', 'La ruta que buscas no existe.'];
        }
        if ($e instanceof HttpMethodNotAllowedException) {
            return [405, 'Método no permitido', 'El método HTTP no está permitido para esta ruta.'];
        }
        return [500, 'Error interno', 'Algo salió mal. Por favor intenta más tarde.'];
    }

    private static function expectsJson(): bool {
        $xhr    = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $hasCsrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']);
        return $xhr || $hasCsrf || str_contains($accept, 'application/json');
    }

    private static function jsonResponse(int $status, string $message): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['response' => false, 'message' => $message]);
        exit;
    }

    private static function htmlResponse(int $status, string $title, string $message): void {
        http_response_code($status);
        $view = _APP_PATH_ . "Views/errors/{$status}.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo self::fallbackHtml($status, $title, $message);
        }
        exit;
    }

    private static function fallbackHtml(int $status, string $title, string $message): string {
        return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
            <title>{$status} — {$title}</title>
            <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
            min-height:100vh;margin:0;background:#f5f7f4;}
            .box{text-align:center;padding:2rem;}
            h1{font-size:5rem;margin:0;color:#1a6b2f;}
            h2{color:#333;}p{color:#666;}</style></head>
            <body><div class='box'>
            <h1>{$status}</h1><h2>{$title}</h2><p>{$message}</p>
            <a href='javascript:history.back()'>Regresar</a>
            </div></body></html>";
    }
}