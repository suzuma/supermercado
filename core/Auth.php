<?php
namespace Core;

class Auth {
    const METHOD = 'aes-256-cbc';

    public static function signIn(array $data): void {
        $isProduction = ServicesContainer::getConfig()['environment'] === 'prod';

        setcookie(
            ServicesContainer::getConfig()['session-name'],
            self::encryptCookie(json_encode($data)),
            [
                'expires'  => time() + 86400,
                'path'     => '/',
                'secure'   => $isProduction,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }

    public static function destroy(): void {
        if (empty($_COOKIE[ServicesContainer::getConfig()['session-name']])) return;

        unset($_COOKIE[ServicesContainer::getConfig()['session-name']]);
        setcookie(ServicesContainer::getConfig()['session-name'], '', -1, '/');
    }

    public static function getCurrentUser(): \stdClass {
        if (empty($_COOKIE[ServicesContainer::getConfig()['session-name']])) {
            throw new \Exception('Auth cookie is not defined');
        }

        $decrypted = self::decryptCookie($_COOKIE[ServicesContainer::getConfig()['session-name']]);
        $data      = json_decode($decrypted, true);

        if (!is_array($data)) {
            throw new \Exception('Auth cookie inválida o con formato legacy');
        }

        return (object)$data;
    }

    public static function isLoggedIn(): bool {
        if (empty($_COOKIE[ServicesContainer::getConfig()['session-name']])) return false;

        try {
            $decrypted = self::decryptCookie($_COOKIE[ServicesContainer::getConfig()['session-name']]);
            $data      = json_decode($decrypted, true);
            return is_array($data) && isset($data['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function encryptCookie(string $value) : string {
        $key = self::aud();
        $openCrypt = new OpenCrypt($key);
        return $openCrypt->encrypt($value);
    }

    private static function decryptCookie(string $value) : string {
        $key = self::aud();
        $openCrypt = new OpenCrypt($key);
        return $openCrypt->decrypt($value);
    }



    private static function aud() : string {
        $aud = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $aud = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $aud = $_SERVER['REMOTE_ADDR'];
        }

        $aud .= @$_SERVER['HTTP_USER_AGENT'];
        $aud .= gethostname();

        return md5(ServicesContainer::getConfig()['secret-key'] . $aud);
    }
}





class OpenCrypt
{
    /**
     * The cipher method. For a list of available cipher methods, use openssl_get_cipher_methods()
     */
    const CIPHER_METHOD = "AES-256-CBC";

    /**
     * When OPENSSL_RAW_DATA is specified, the returned data is returned as-is.
     */
    const OPTIONS = OPENSSL_RAW_DATA;

    /**
     * The key
     *
     * Should have been previously generated in a cryptographically safe way, like openssl_random_pseudo_bytes
     */
    private $secretKey;

    /**
     * IV - A non-NULL Initialization Vector.
     *
     * Encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
     */
    private $iv;

    public function __construct(
        string $secretKey,
        string $iv = null
    ) {
        $this->secretKey = hash('sha256', $secretKey);

        $this->iv = $iv ?: self::generateIV();
    }

    public function encrypt(string $value): string {
        $iv = self::generateIV();
        $output = openssl_encrypt(
            $value,
            self::CIPHER_METHOD,
            $this->secretKey,
            self::OPTIONS,
            $iv
        );
        // Prefijamos el IV al ciphertext para recuperarlo en decrypt()
        return base64_encode($iv . $output);
    }

    public function decrypt(string $value): string {
        $data      = base64_decode($value);
        $ivLength  = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv        = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        return openssl_decrypt(
            $ciphertext,
            self::CIPHER_METHOD,
            $this->secretKey,
            self::OPTIONS,
            $iv
        );
    }

    public function iv()
    {
        return $this->iv;
    }

    /**
     * Generate IV
     *
     * @return int Returns a string of pseudo-random bytes, with the number of bytes expected by the method AES-256-CBC
     */
    public static function generateIV()
    {
        $ivNumBytes = openssl_cipher_iv_length(self::CIPHER_METHOD);
        return openssl_random_pseudo_bytes($ivNumBytes);
    }

    /**
     * Generate a key
     *
     * @param int $length The length of the desired string of bytes. Must be a positive integer.
     *
     * @return int Returns the hexadecimal representation of a binary data
     */
    public static function generateKey($length = 512)
    {
        $bytes = openssl_random_pseudo_bytes($length);
        return bin2hex($bytes);
    }
}