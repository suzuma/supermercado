<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: REPOSITORIO DE USUARIOS — LOGIN Y CRUD BASE
*/
namespace App\Repositories;

use Core\{Auth, Log};
use App\Helpers\ResponseHelper;
use App\Models\Usuario;
use Exception;

class UsuarioRepository {
    private $usuario;

    public function __construct()
    {
        $this->usuario = new Usuario;
    }

    // -------------------------------------------------------
    // Autenticación
    // -------------------------------------------------------
    public function autenticar(string $email, string $password): ResponseHelper
    {
        $rh = new ResponseHelper;

        try {
            $row = $this->usuario
                ->with('rol')
                ->where('email', $email)
                ->where('activo', 1)
                ->first();

            if (is_object($row) && $this->verificarPassword($password, $row->password)) {
                // Migración transparente: si el hash es SHA1 (legacy), rehashear a bcrypt
                if (!$this->esBcrypt($row->password)) {
                    $row->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $row->save();
                }

                Auth::signIn([
                    'id'       => $row->id,
                    'nombre'   => $row->nombre,
                    'apellido' => $row->apellido,
                    'email'    => $row->email,
                    'rol_id'   => $row->rol_id,
                    'rol'      => $row->rol->nombre,
                ]);
                $rh->setResponse(true, 'Bienvenido ' . $row->nombre);
            } else {
                $rh->setResponse(false, 'Correo o contraseña incorrectos');
                Log::critical(UsuarioRepository::class, "Intento fallido de autenticación: $email");
            }
        } catch (Exception $e) {
            Log::error(UsuarioRepository::class, $e->getMessage());
            $rh->setResponse(false, 'Error inesperado, intenta de nuevo');
        }

        return $rh;
    }

    private function verificarPassword(string $password, string $hash): bool
    {
        if ($this->esBcrypt($hash)) {
            return password_verify($password, $hash);
        }
        // Soporte legacy para hashes SHA1 existentes en BD
        return hash_equals($hash, sha1($password));
    }

    private function esBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$');
    }

    // -------------------------------------------------------
    // CRUD
    // -------------------------------------------------------
    public function obtener(int $id): Usuario
    {
        $usuario = new Usuario;

        try {
            $usuario = $this->usuario->with('rol')->findOrFail($id);
        } catch (Exception $e) {
            Log::error(UsuarioRepository::class, $e->getMessage());
        }

        return $usuario;
    }

    public function listar()
    {
        try {
            return $this->usuario
                ->with('rol')
                ->orderBy('nombre')
                ->get();
        } catch (Exception $e) {
            Log::error(UsuarioRepository::class, $e->getMessage());
            return collect();
        }
    }

    public function guardar(Usuario $model): ResponseHelper
    {
        $rh = new ResponseHelper;

        try {
            $this->usuario->id       = $model->id;
            $this->usuario->rol_id   = $model->rol_id;
            $this->usuario->nombre   = $model->nombre;
            $this->usuario->apellido = $model->apellido;
            $this->usuario->email    = $model->email;
            $this->usuario->activo   = $model->activo ?? 1;

            if (!empty($model->id)) {
                $this->usuario->exists = true;
                if (!empty($model->password)) {
                    $this->usuario->password = password_hash($model->password, PASSWORD_BCRYPT, ['cost' => 12]);
                }
            } else {
                $this->usuario->password = password_hash($model->password, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $this->usuario->save();
            $rh->setResponse(true, 'Usuario guardado correctamente');
        } catch (Exception $e) {
            Log::error(UsuarioRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el usuario');
        }

        return $rh;
    }

    public function eliminar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper;

        try {
            if (Auth::getCurrentUser()->id == $id) {
                return $rh->setResponse(false, 'No puedes eliminarte a ti mismo');
            }
            $this->usuario->destroy($id);
            $rh->setResponse(true, 'Usuario eliminado');
        } catch (Exception $e) {
            Log::error(UsuarioRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo eliminar el usuario');
        }

        return $rh;
    }


    public function listarTodos(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return $this->usuario
                ->with('rol')
                ->orderBy('nombre')
                ->get();
        } catch (\Exception $e) {
            \Core\Log::error(UsuarioRepository::class, $e->getMessage());
            return collect();
        }
    }
}