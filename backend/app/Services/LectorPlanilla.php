<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as OpcionesCsv;
use OpenSpout\Reader\CSV\Reader as LectorCsv;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;
use OpenSpout\Writer\XLSX\Writer as EscritorXlsx;
use Throwable;

// HU-07: lee la planilla de la nómina (Excel o CSV) y la deja como filas con claves fijas
class LectorPlanilla
{
    // Columnas de la plantilla; cada una acepta algunos nombres alternativos
    public const COLUMNAS = [
        'rut' => ['rut'],
        'nombre' => ['nombre', 'nombre completo'],
        'correo' => ['correo', 'correo institucional'],
        'anio_ingreso' => ['ano de ingreso', 'ano ingreso'],
        'practica' => ['practica', 'tipo de practica'],
        'cumple' => ['cumple prerrequisito', 'prerrequisito'],
    ];

    public const ENCABEZADOS = ['RUT', 'Nombre', 'Correo', 'Año de ingreso', 'Práctica', 'Cumple prerrequisito'];

    public const MAXIMO_FILAS = 1000;

    /**
     * @return list<array{fila: int, rut: string, nombre: string, correo: string, anio_ingreso: string, practica: string, cumple: string}>
     */
    public function leer(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $lector = $extension === 'xlsx' ? new LectorXlsx : new LectorCsv($this->opcionesCsv($archivo->getRealPath()));

        try {
            $lector->open($archivo->getRealPath());
            $filas = $this->recorrer($lector);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer la planilla. Revisa que sea un Excel o CSV válido.']);
        } finally {
            $lector->close();
        }

        return $filas;
    }

    // Plantilla vacía con los encabezados, para que la Secretaria la complete
    public function crearPlantilla(): string
    {
        return $this->crearPlanilla([]);
    }

    /**
     * Excel con las columnas de la plantilla; cada fila va en el mismo orden que ENCABEZADOS
     *
     * @param  list<list<string|int>>  $filas
     */
    public function crearPlanilla(array $filas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'planilla').'.xlsx';
        $escritor = new EscritorXlsx;
        $escritor->openToFile($ruta);
        $escritor->addRow(Row::fromValues(self::ENCABEZADOS, (new Style)->setFontBold()));
        foreach ($filas as $fila) {
            $escritor->addRow(Row::fromValues($fila));
        }
        $escritor->close();

        return $ruta;
    }

    private function recorrer(LectorCsv|LectorXlsx $lector): array
    {
        $indices = null;
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $numero => $fila) {
                $valores = array_map(fn ($valor) => $this->texto($valor), $fila->toArray());

                if ($indices === null) {
                    $indices = $this->ubicarColumnas($valores);

                    continue;
                }

                if (implode('', $valores) === '') {
                    continue; // fila vacía
                }

                $datos = ['fila' => $numero];
                foreach ($indices as $clave => $indice) {
                    $datos[$clave] = $valores[$indice] ?? '';
                }
                $filas[] = $datos;

                if (count($filas) > self::MAXIMO_FILAS) {
                    throw ValidationException::withMessages(['archivo' => 'La planilla tiene más de '.self::MAXIMO_FILAS.' filas.']);
                }
            }

            break; // solo la primera hoja
        }

        if ($indices === null || $filas === []) {
            throw ValidationException::withMessages(['archivo' => 'La planilla no tiene estudiantes.']);
        }

        return $filas;
    }

    /**
     * @param  list<string>  $encabezados
     * @return array<string, int>
     */
    private function ubicarColumnas(array $encabezados): array
    {
        $normalizados = array_map(fn ($e) => Str::of($e)->ascii()->lower()->squish()->toString(), $encabezados);
        $indices = [];

        foreach (self::COLUMNAS as $clave => $nombres) {
            $indice = collect($nombres)->map(fn ($n) => array_search($n, $normalizados, true))->first(fn ($i) => $i !== false);

            if ($indice === null) {
                $nombre = self::ENCABEZADOS[array_search($clave, array_keys(self::COLUMNAS), true)];
                throw ValidationException::withMessages(['archivo' => "La planilla no tiene la columna \"{$nombre}\". Usa la plantilla."]);
            }

            $indices[$clave] = $indice;
        }

        return $indices;
    }

    // Excel guarda números como 2025.0; en la planilla todo se trata como texto
    private function texto(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }

        return trim((string) ($valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : $valor));
    }

    // Excel en español guarda los CSV separados por punto y coma y en Windows-1252
    private function opcionesCsv(string $ruta): OpcionesCsv
    {
        $contenido = (string) file_get_contents($ruta);
        $primeraLinea = strtok($contenido, "\n") ?: '';

        $opciones = new OpcionesCsv;
        $opciones->FIELD_DELIMITER = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $opciones->ENCODING = 'Windows-1252';
        }

        return $opciones;
    }
}
