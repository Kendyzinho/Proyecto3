<?php

namespace App\Support;

// RUT chileno: limpia, valida el dígito verificador (módulo 11) y lo deja como 12.345.678-9
class Rut
{
    // Devuelve el RUT con formato, o null si no es válido
    public static function normalizar(string $valor): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $valor));

        if (! preg_match('/^(\d{7,8})([0-9K])$/', $limpio, $partes)) {
            return null;
        }

        [, $cuerpo, $dv] = $partes;

        if (self::digitoVerificador($cuerpo) !== $dv) {
            return null;
        }

        return number_format((int) $cuerpo, 0, '', '.').'-'.$dv;
    }

    public static function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $factor = 2;

        foreach (array_reverse(str_split($cuerpo)) as $digito) {
            $suma += (int) $digito * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        return match ($resto = 11 - $suma % 11) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
