<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Empleado, Asistencia, Usuario};
use App\Services\AuditoriaService;
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Eloquent\Collection;

class EmpleadoRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Empleado();
    }

    // ── Listar ────────────────────────────────────────────────
    public function listar(int $pagina = 1, int $limite = 15, ?string $turno = null, ?string $busqueda = null): array
    {
        try {
            $query = $this->model
                ->with(['usuario', 'usuario.rol'])
                ->activos();

            if ($turno) {
                $query->where('turno', $turno);
            }

            if ($busqueda) {
                $query->whereHas('usuario', function ($q) use ($busqueda) {
                    $q->where('nombre',   'like', "%$busqueda%")
                        ->orWhere('apellido', 'like', "%$busqueda%")
                        ->orWhere('email',    'like', "%$busqueda%");
                });
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $datos = $query
                ->orderBy('id')
                ->skip($offset)
                ->take($limite)
                ->get();

            return [
                'datos'       => $datos,
                'total'       => $total,
                'pagina'      => $pagina,
                'limite'      => $limite,
                'total_pages' => (int)ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'limite' => $limite, 'total_pages' => 1];
        }
    }

    // ── Obtener uno ───────────────────────────────────────────
    public function obtener(int $id): Empleado
    {
        try {
            return $this->model->with(['usuario', 'usuario.rol'])->findOrFail($id);
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            return new Empleado();
        }
    }

    // ── Totales para dashboard ────────────────────────────────
    public function totalActivos(): int
    {
        try {
            return $this->model->activos()->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function total(): int
    {
        try {
            return $this->model->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    // ── Guardar (crea usuario + empleado juntos) ──────────────
    public function guardar(array $data): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $esNuevo = empty($data['empleado_id']);

            if ($esNuevo) {
                // Crear usuario del sistema
                $usuario = new Usuario();
                $usuario->rol_id   = $data['rol_id'];
                $usuario->nombre   = $data['nombre'];
                $usuario->apellido = $data['apellido'];
                $usuario->email    = $data['email'];
                $usuario->password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $usuario->activo   = 1;
                $usuario->save();

                // Crear empleado
                $empleado = new Empleado();
                $empleado->usuario_id    = $usuario->id;
                $empleado->puesto        = $data['puesto'];
                $empleado->salario       = $data['salario'];
                $empleado->fecha_ingreso = $data['fecha_ingreso'];
                $empleado->turno         = $data['turno'];
                $empleado->activo        = 1;
                $empleado->save();

            } else {
                // Actualizar empleado existente
                $empleado = $this->model->findOrFail($data['empleado_id']);
                $empleado->puesto        = $data['puesto'];
                $empleado->salario       = $data['salario'];
                $empleado->fecha_ingreso = $data['fecha_ingreso'];
                $empleado->turno         = $data['turno'];
                $empleado->exists        = true;
                $empleado->save();

                // Actualizar usuario
                $usuario = Usuario::findOrFail($empleado->usuario_id);
                $usuario->rol_id   = $data['rol_id'];
                $usuario->nombre   = $data['nombre'];
                $usuario->apellido = $data['apellido'];
                $usuario->email    = $data['email'];
                $usuario->exists   = true;

                if (!empty($data['password'])) {
                    $usuario->password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                }

                $usuario->save();
            }

            $rh->setResponse(true, 'Empleado guardado correctamente');
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el empleado');
        }

        if ($rh->response) {
            $accion = empty($data['empleado_id']) ? 'crear' : 'editar';
            AuditoriaService::registrar('empleados', $accion, "Empleado '{$data['nombre']} {$data['apellido']}'");
        }

        return $rh;
    }

    // ── Desactivar ────────────────────────────────────────────
    public function desactivar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $empleado = $this->model->findOrFail($id);
            $empleado->activo = 0;
            $empleado->exists = true;
            $empleado->save();

            // Desactivar también el usuario del sistema
            $usuario = Usuario::findOrFail($empleado->usuario_id);
            $usuario->activo = 0;
            $usuario->exists = true;
            $usuario->save();

            $rh->setResponse(true, 'Empleado desactivado correctamente');
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar el empleado');
        }

        if ($rh->response) {
            AuditoriaService::registrar('empleados', 'desactivar', "Empleado #$id desactivado", $id);
        }

        return $rh;
    }

    // ── Asistencia ────────────────────────────────────────────
    public function registrarAsistencia(array $data): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $asistencia = Asistencia::firstOrNew([
                'empleado_id' => $data['empleado_id'],
                'fecha'       => $data['fecha'],
            ]);

            $asistencia->hora_entrada    = $data['hora_entrada'] ?: null;
            $asistencia->hora_salida     = $data['hora_salida'] ?: null;
            $asistencia->observacion     = $data['observacion'] ?: null;
            $asistencia->registrado_por  = Auth::getCurrentUser()->id;
            $asistencia->save();

            $rh->setResponse(true, 'Asistencia registrada');
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo registrar la asistencia');
        }

        return $rh;
    }

    public function historialAsistencia(int $empleado_id, string $mes): Collection
    {
        try {
            return Asistencia::where('empleado_id', $empleado_id)
                ->whereYear('fecha',  substr($mes, 0, 4))
                ->whereMonth('fecha', substr($mes, 5, 2))
                ->orderBy('fecha')
                ->get();
        } catch (Exception $e) {
            Log::error(EmpleadoRepository::class, $e->getMessage());
            return collect();
        }
    }


    public function listarRepartidores(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return $this->model
                ->with('usuario')
                ->whereHas('usuario', function ($q) {
                    $q->whereIn('rol_id', [1, 4]); // Admin y Repartidor
                })
                ->activos()
                ->get();
        } catch (\Exception $e) {
            \Core\Log::error(EmpleadoRepository::class, $e->getMessage());
            return collect();
        }
    }
}