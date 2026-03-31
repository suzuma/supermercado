<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\{Configuracion, Usuario};
use App\Repositories\UsuarioRepository;
use Core\{Auth, Controller, Log};

class ConfiguracionController extends Controller
{
    private $usuarioRepo;

    public function __construct()
    {
        parent::__construct();
        $this->usuarioRepo = new UsuarioRepository();
    }

    // ── Vista principal ───────────────────────────────────────
    public function getIndex()
    {
        $config = [
            'negocio_nombre'    => Configuracion::get('negocio_nombre'),
            'negocio_direccion' => Configuracion::get('negocio_direccion'),
            'negocio_telefono'  => Configuracion::get('negocio_telefono'),
            'negocio_email'     => Configuracion::get('negocio_email'),
            'negocio_rfc'       => Configuracion::get('negocio_rfc'),
            'negocio_logo'      => Configuracion::get('negocio_logo'),
            'timezone'          => Configuracion::get('timezone', 'America/Hermosillo'),
            'moneda'            => Configuracion::get('moneda', 'MXN'),
            'ticket_mensaje'    => Configuracion::get('ticket_mensaje'),
        ];

        $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA);
        $usuarios  = $this->usuarioRepo->listarTodos();

        return $this->render('configuracion/index.twig', [
            'title'     => 'Configuración',
            'config'    => $config,
            'timezones' => $timezones,
            'usuarios'  => $usuarios,
        ]);
    }

    // ── Guardar datos del negocio ─────────────────────────────
    public function postNegocio()
    {
        $rh = new ResponseHelper();

        try {
            if (empty($_POST['negocio_nombre'])) {
                return $rh->setResponse(false, 'El nombre del negocio es requerido');
            }

            $campos = [
                'negocio_nombre', 'negocio_direccion', 'negocio_telefono',
                'negocio_email',  'negocio_rfc',       'timezone',
                'moneda',         'ticket_mensaje',
            ];

            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    Configuracion::set($campo, trim($_POST[$campo]));
                }
            }

            // Subir logo si viene
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $nombreLogo = $this->subirLogo($_FILES['logo']);
                if ($nombreLogo) {
                    Configuracion::set('negocio_logo', $nombreLogo);
                }
            }

            $rh->setResponse(true, 'Configuración guardada correctamente');
        } catch (\Exception $e) {
            Log::error(ConfiguracionController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar la configuración');
        }

        echo json_encode($rh);
    }

    // ── Cambiar contraseña ────────────────────────────────────
    public function postPassword()
    {
        $rh = new ResponseHelper();

        try {
            $actual   = $_POST['password_actual'] ?? '';
            $nueva    = $_POST['password_nueva'] ?? '';
            $confirma = $_POST['password_confirma'] ?? '';

            if (empty($actual) || empty($nueva) || empty($confirma)) {
                return $rh->setResponse(false, 'Todos los campos son requeridos');
            }

            if (strlen($nueva) < 6) {
                return $rh->setResponse(false, 'La nueva contraseña debe tener mínimo 6 caracteres');
            }

            if ($nueva !== $confirma) {
                return $rh->setResponse(false, 'Las contraseñas no coinciden');
            }

            $userId  = Auth::getCurrentUser()->id;
            $usuario = Usuario::findOrFail($userId);

            if ($usuario->password !== sha1($actual)) {
                return $rh->setResponse(false, 'La contraseña actual es incorrecta');
            }

            $usuario->password = sha1($nueva);
            $usuario->exists   = true;
            $usuario->save();

            $rh->setResponse(true, 'Contraseña actualizada correctamente');
        } catch (\Exception $e) {
            Log::error(ConfiguracionController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar la contraseña');
        }

        echo json_encode($rh);
    }

    // ── Guardar usuario (gestión) ─────────────────────────────
    public function postUsuario()
    {
        $rh = new ResponseHelper();

        try {
            $esNuevo = empty($_POST['id']);

            if (empty($_POST['nombre']) || empty($_POST['apellido']) || empty($_POST['email'])) {
                return $rh->setResponse(false, 'Nombre, apellido y correo son requeridos');
            }

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                return $rh->setResponse(false, 'El correo no tiene un formato válido');
            }

            if ($esNuevo && empty($_POST['password'])) {
                return $rh->setResponse(false, 'La contraseña es requerida para nuevos usuarios');
            }

            $usuario           = $esNuevo ? new Usuario() : Usuario::findOrFail((int)$_POST['id']);
            $usuario->rol_id   = $_POST['rol_id'];
            $usuario->nombre   = $_POST['nombre'];
            $usuario->apellido = $_POST['apellido'];
            $usuario->email    = $_POST['email'];
            $usuario->activo   = $_POST['activo'] ?? 1;

            if (!empty($_POST['password'])) {
                $usuario->password = sha1($_POST['password']);
            }

            if (!$esNuevo) {
                $usuario->exists = true;
            }

            $usuario->save();
            $rh->setResponse(true, $esNuevo ? 'Usuario creado correctamente' : 'Usuario actualizado correctamente');
        } catch (\Exception $e) {
            Log::error(ConfiguracionController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el usuario');
        }

        echo json_encode($rh);
    }

    // ── Desactivar usuario ────────────────────────────────────
    public function postDesactivarUsuario()
    {
        $rh = new ResponseHelper();

        try {
            $id = (int)$_POST['id'];

            if ($id === Auth::getCurrentUser()->id) {
                return $rh->setResponse(false, 'No puedes desactivarte a ti mismo');
            }

            $usuario         = Usuario::findOrFail($id);
            $usuario->activo = 0;
            $usuario->exists = true;
            $usuario->save();

            $rh->setResponse(true, 'Usuario desactivado');
        } catch (\Exception $e) {
            Log::error(ConfiguracionController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar el usuario');
        }

        echo json_encode($rh);
    }

    // ── Helper: subir logo ────────────────────────────────────
    private function subirLogo(array $archivo): ?string
    {
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        $extension  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $permitidos)) return null;
        if ($archivo['size'] > 2 * 1024 * 1024) return null;

        $nombre     = 'logo_' . time() . '.' . $extension;
        $directorio = _BASE_PATH_ . 'public/images/';

        if (move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
            return $nombre;
        }

        return null;
    }
}