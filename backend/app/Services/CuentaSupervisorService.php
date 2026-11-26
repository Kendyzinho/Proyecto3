<?php

namespace App\Services;

use App\Mail\InvitacionSupervisorMail;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// HU-19: el supervisor(a) es un usuario externo con cuenta propia (pedido del profe).
// La cuenta se crea o se vincula al activarlo en un centro y se desactiva cuando ya no supervisa nada.
// Se llama de forma explícita (no con un observer) porque los seeders siembran sin eventos de modelo.
class CuentaSupervisorService
{
    /**
     * Vincula el registro con su cuenta, creándola si no existe. Debe llamarse dentro de una transacción.
     * Devuelve la cuenta si hay que invitarla (nueva o reactivada); null si ya estaba activa.
     */
    public function asegurar(Supervisor $supervisor): ?User
    {
        $correo = Str::lower(trim($supervisor->correo));
        $cuenta = User::whereRaw('LOWER(email) = ?', [$correo])->lockForUpdate()->first();

        if ($cuenta && ! $cuenta->esSupervisor()) {
            throw ValidationException::withMessages([
                'correo' => 'El correo del supervisor(a) pertenece a una cuenta interna de la universidad. Usa su correo del centro.',
            ]);
        }

        $invitar = false;
        if (! $cuenta) {
            $cuenta = User::create([
                'name' => $supervisor->nombre, 'email' => $correo, 'rol' => 'supervisor',
                'password' => Hash::make(Str::random(40)), // la define él con el enlace de la invitación
            ]);
            $invitar = true;
        } elseif ($cuenta->fecha_baja !== null) {
            $cuenta->update(['fecha_baja' => null, 'motivo_baja' => null]);
            $invitar = true;
        }

        if ($supervisor->user_id !== $cuenta->id) {
            $supervisor->update(['user_id' => $cuenta->id]);
        }

        return $invitar ? $cuenta : null;
    }

    // Se envía después del commit. Si el correo falla, la cuenta ya existe y puede pedir su clave con "¿Olvidaste tu contraseña?"
    public function invitar(User $cuenta): bool
    {
        try {
            $token = Password::broker()->createToken($cuenta);
            $enlace = rtrim(config('app.frontend_url'), '/').'/auth/restablecer?'.http_build_query(['token' => $token, 'email' => $cuenta->email]);
            Mail::to($cuenta)->send(new InvitacionSupervisorMail($cuenta, $enlace));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar la invitación al supervisor', ['user_id' => $cuenta->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    // Tras dar de baja un registro: la cuenta sigue si supervisa en otro centro o tiene algo pendiente
    public function alDarDeBaja(Supervisor $supervisor): void
    {
        $cuenta = $supervisor->usuario;
        if (! $cuenta || $cuenta->fecha_baja !== null) {
            return;
        }

        $registros = $cuenta->supervisores()->get();
        $otroActivo = $registros->contains(fn (Supervisor $registro) => $registro->id !== $supervisor->id && $registro->estaActivo());
        $ids = $registros->pluck('id');
        $practicasVigentes = Practica::conEstado(['confirmada', 'en_curso'])->whereIn('supervisor_id', $ids)->exists();
        $informesPendientes = DB::table('respuestas_instrumento as respuesta')
            ->join('practicas', 'practicas.id', '=', 'respuesta.practica_id')
            ->join('plantillas_instrumento as plantilla', 'plantilla.id', '=', 'respuesta.plantilla_id')
            ->whereIn('practicas.supervisor_id', $ids)
            ->where('plantilla.instrumento', 'informe')
            ->whereNull('respuesta.fecha_envio')
            ->exists();

        if ($otroActivo || $practicasVigentes || $informesPendientes) {
            return;
        }

        $cuenta->update(['fecha_baja' => today()->toDateString(), 'motivo_baja' => 'Ya no supervisa prácticas']);
        $cuenta->tokens()->delete();
    }
}
