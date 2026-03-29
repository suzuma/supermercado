<?php
/*
    autor: Noe Cazarez Camargo
    descripcion: HELPER DE CORREO — envío de emails transaccionales vía PHPMailer
*/
namespace App\Helpers;

use Core\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class MailHelper
{
    /**
     * Envía un correo usando la configuración SMTP del .env.
     * $template: nombre del archivo en app/Views/emails/ (sin extensión)
     * $vars: variables disponibles dentro de la plantilla
     */
    public static function send(string $to, string $toName, string $subject, string $template, array $vars = []): bool
    {
        // Verificar que hay configuración SMTP básica
        if (empty($_ENV['MAIL_HOST']) || empty($_ENV['MAIL_USERNAME'])) {
            Log::warning(self::class, 'SMTP no configurado — email no enviado a ' . $to);
            return false;
        }

        try {
            $mail = new PHPMailer(true);

            // Servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls') === 'ssl'
                                    ? PHPMailer::ENCRYPTION_SMTPS
                                    : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
            $mail->CharSet    = 'UTF-8';

            // Remitente y destinatario
            $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME'] ?? 'Supermercado Web');
            $mail->addAddress($to, $toName);

            // Asunto y cuerpo HTML
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = self::renderTemplate($template, $vars);
            $mail->AltBody = strip_tags($mail->Body);

            $mail->send();
            return true;
        } catch (MailException $e) {
            Log::error(self::class, "Error enviando a $to — " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error(self::class, "Error inesperado enviando a $to — " . $e->getMessage());
            return false;
        }
    }

    /** Renderiza una plantilla PHP de email y devuelve el HTML como string */
    private static function renderTemplate(string $template, array $vars): string
    {
        $path = _APP_PATH_ . "Views/emails/{$template}.php";

        if (!file_exists($path)) {
            Log::error(self::class, "Plantilla de email no encontrada: $path");
            return '';
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }
}