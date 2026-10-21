<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// HU-05: la Secretaria crea, edita, desactiva y reactiva cuentas.
// La carga por lote de estudiantes es la nómina (HU-07). Desactivar no borra nada: solo llena fecha_baja.
class UsuarioService
{
    // Primero las cuentas activas y después las desactivadas, cada grupo por nombre
    public function listar(): Collection
    {
        return User::with('estudiante')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (User $u) => $u->fecha_baja !== null)
            ->values();
    }

    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'rut' => $datos['rut'] ?? null,
                'name' => Str::squish($datos['name']),
                'email' => $datos['email'],
                'rol' => $this->rolEnBase($datos['rol']),
                // Contraseña al azar: la persona define la suya con "Recuperar contraseña" (HU-02)
                'password' => Str::password(32),
            ]);

            if ($usuario->rol === null) {
                Estudiante::create(['user_id' => $usuario->id, 'anio_ingreso' => $datos['anio_ingreso']]);
            }

            return $usuario->load('estudiante');
        });
    }

    public function editar(User $usuario, array $datos, User $secretaria): User
    {
        $esEstudiante = $usuario->rol === null;

        // El estudiante tiene datos propios (nómina, prácticas); no se convierte en personal ni al revés
        if ($esEstudiante !== ($datos['rol'] === 'estudiante')) {
            throw ValidationException::withMessages(['rol' => 'Un estudiante no puede pasar a ser personal ni al revés.']);
        }
        if ($usuario->is($secretaria) && $datos['rol'] !== 'secretaria') {
            throw ValidationException::withMessages(['rol' => 'No puedes quitarte el rol de secretaria.']);
        }

        DB::transaction(function () use ($usuario, $datos, $esEstudiante) {
            $usuario->update([
                'rut' => $datos['rut'] ?? null,
                'name' => Str::squish($datos['name']),
                'email' => $datos['email'],
                'rol' => $this->rolEnBase($datos['rol']),
            ]);

            if ($esEstudiante) {
                $usuario->estudiante()->updateOrCreate([], ['anio_ingreso' => $datos['anio_ingreso']]);
            }
        });

        return $usuario->load('estudiante');
    }

    // Para egresados, retirados o personal que ya no está en la carrera
    public function desactivar(User $usuario, string $motivo, User $secretaria): User
    {
        if ($usuario->is($secretaria)) {
            throw ValidationException::withMessages(['motivo' => 'No puedes desactivar tu propia cuenta.']);
        }

        $usuario->update(['fecha_baja' => now()->toDateString(), 'motivo_baja' => $motivo]);
        // El login ya rechaza la cuenta, pero un token abierto seguiría sirviendo: se cierran sus sesiones
        $usuario->tokens()->delete();

        return $usuario->load('estudiante');
    }

    public function reactivar(User $usuario): User
    {
        $usuario->update(['fecha_baja' => null, 'motivo_baja' => null]);

        return $usuario->load('estudiante');
    }

    // En la base el estudiante no tiene rol (columna vacía)
    private function rolEnBase(string $rol): ?string
    {
        return $rol === 'estudiante' ? null : $rol;
    }
}
