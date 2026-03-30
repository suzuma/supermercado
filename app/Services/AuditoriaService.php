<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Auditoria;
use Core\{Auth, Log};
use Exception;

class AuditoriaService
{
    public static function registrar(
        string $modulo,
        string $accion,
        string $descripcion,
        ?int $referenciaId = null
    ): void {
        try {
            $usuario = Auth::getCurrentUser();
            if (!$usuario) {
                return;
            }

            $registro = new Auditoria();
            $registro->usuario_id    = $usuario->id;
            $registro->modulo        = $modulo;
            $registro->accion        = $accion;
            $registro->descripcion   = $descripcion;
            $registro->referencia_id = $referenciaId;
            $registro->ip            = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $registro->created_at    = date('Y-m-d H:i:s');
            $registro->save();
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
        }
    }
}
