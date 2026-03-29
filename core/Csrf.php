<?php
namespace Core;

class Csrf {
    const TOKEN_KEY = '_csrf_token';

    public static function token(): string {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function validate(string $token): bool {
        $stored = $_SESSION[self::TOKEN_KEY] ?? '';
        if (empty($stored) || empty($token)) return false;
        return hash_equals($stored, $token);
    }

    public static function validateOrFail(): void {
        // Acepta el token como header HTTP o como campo POST
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
               ?? $_POST[self::TOKEN_KEY]
               ?? '';

        if (!self::validate($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode([
                'response' => false,
                'message'  => 'Solicitud inválida, recarga la página e intenta de nuevo',
            ]);
            exit;
        }
    }
}