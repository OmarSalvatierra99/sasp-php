<?php

declare(strict_types=1);

namespace Sasp\Core;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use DateTimeImmutable;

/**
 * DataProcessor — SCIL / SASP 2025
 * Procesamiento de archivos laborales y cruces quincenales
 *
 * Port of core/data_processor.py from Python SASP
 */
class DataProcessor
{
    private DatabaseManager $db;
    /** @var array<string, string> */
    private array $mapaSiglas;
    /** @var array<string, string> */
    private array $mapaInverso;

    public function __construct(?DatabaseManager $db = null, ?string $dbPath = null)
    {
        $dbPath ??= (string)($_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: 'scil.db');
        $this->db = $db ?? new DatabaseManager($dbPath);
        $this->refreshCatalogMaps();
    }

    /**
     * Reload catalog maps (siglas/clave) from DB
     */
    private function refreshCatalogMaps(): void
    {
        $this->mapaSiglas = $this->db->getMapaSiglas();
        $this->mapaInverso = $this->db->getMapaClavesInverso();
    }

    /**
     * Limpia y valida RFC (10-13 caracteres alfanuméricos)
     */
    private function limpiarRfc(?string $rfc): ?string
    {
        if ($rfc === null || $rfc === '') {
            return null;
        }

        $s = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($rfc)));

        if ($s === null) {
            return null;
        }

        $len = strlen($s);
        return ($len >= 10 && $len <= 13) ? $s : null;
    }

    /**
     * Limpia y normaliza fecha a formato YYYY-MM-DD
     */
    private function limpiarFecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        // Si es un número (fecha de Excel)
        if (is_numeric($fecha)) {
            try {
                $dateObj = Date::excelToDateTimeObject($fecha);
                return $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Si es una cadena
        $s = trim((string)$fecha);
        $lower = strtolower($s);

        if (in_array($lower, ['', 'nan', 'nat', 'none', 'null'], true)) {
            return null;
        }

        // Intentar parsear fecha
        try {
            // Probar varios formatos comunes
            $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
            foreach ($formats as $format) {
                $date = DateTimeImmutable::createFromFormat($format, $s);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // Último intento con strtotime
            $timestamp = strtotime($s);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Normaliza etiqueta de ente a clave oficial
     */
    private function normalizarEnteClave(?string $etiqueta): ?string
    {
        if (!$etiqueta) {
            return null;
        }

        $val = strtoupper(trim($etiqueta));

        if (isset($this->mapaSiglas[$val])) {
            return $this->mapaSiglas[$val];
        }

        return $this->db->normalizarEnteClave($val);
    }

    /**
     * Verifica si un valor de QNA se considera "activo"
     * Valores inactivos: "", "0", "0.0", "NO", "N/A", "NA", "NONE"
     */
    private function esActivo(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        $s = strtoupper(trim((string)$valor));
        return !in_array($s, ['', '0', '0.0', 'NO', 'N/A', 'NA', 'NONE'], true);
    }

    /**
     * Extrae TODOS los registros individuales (RFC+ENTE) sin procesar cruces
     *
     * @param array<int, mixed> $archivos Array de archivos subidos
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public function extraerRegistrosIndividuales(array $archivos): array
    {
        $this->refreshCatalogMaps();
        fwrite(STDERR, sprintf("📊 Procesando %d archivo(s) laborales...\n", count($archivos)));

        $registros = [];
        $alertas = [];

        foreach ($archivos as $f) {
            $nombreArchivo = $this->getNombreArchivo($f);
            $filePath = null;

            // Validar estructura y extensión
            if (!is_array($f) || !isset($f['tmp_name'])) {
                $alertas[] = [
                    'tipo' => 'archivo_invalido',
                    'mensaje' => "Estructura de archivo inválida",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            $filePath = $this->getFilePath($f);
            if (!file_exists($filePath)) {
                $alertas[] = [
                    'tipo' => 'archivo_no_encontrado',
                    'mensaje' => "Archivo temporal no encontrado",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                $alertas[] = [
                    'tipo' => 'extension_invalida',
                    'mensaje' => "Extensión no permitida. Solo se aceptan archivos .xlsx y .xls",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            $maxSize = 50 * 1024 * 1024; // 50 MB
            if (filesize($filePath) > $maxSize) {
                $alertas[] = [
                    'tipo' => 'archivo_muy_grande',
                    'mensaje' => "Archivo excede el tamaño máximo permitido (50MB)",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            fwrite(STDERR, "📘 Leyendo archivo: {$nombreArchivo}\n");

            // Cargar el archivo Excel
            try {
                $spreadsheet = IOFactory::load($filePath);
            } catch (\Exception $e) {
                $alertas[] = [
                    'tipo' => 'error_lectura',
                    'mensaje' => "Error al leer archivo: {$e->getMessage()}",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            // Procesar cada hoja
            foreach ($spreadsheet->getSheetNames() as $hoja) {
                $enteLabel = strtoupper(trim($hoja));
                $claveEnte = $this->normalizarEnteClave($enteLabel);

                if (!$claveEnte) {
                    $alerta = "⚠️ Hoja '{$hoja}' no encontrada en catálogo de entes. Verifique el nombre.";
                    fwrite(STDERR, $alerta . "\n");
                    $alertas[] = [
                        'tipo' => 'ente_no_encontrado',
                        'mensaje' => $alerta,
                        'hoja' => $hoja,
                        'archivo' => $nombreArchivo
                    ];
                    continue;
                }

                $worksheet = $spreadsheet->getSheetByName($hoja);
                if (!$worksheet) {
                    continue;
                }

                // Leer datos de la hoja
                $data = $worksheet->toArray();

                if (empty($data)) {
                    continue;
                }

                // Primera fila = encabezados
                $headers = array_map(
                    fn($h) => strtoupper(str_replace(' ', '_', trim((string)$h))),
                    $data[0]
                );

                // Verificar columnas requeridas
                $columnasBase = ['RFC', 'NOMBRE', 'PUESTO', 'FECHA_ALTA', 'FECHA_BAJA'];
                $faltantes = array_diff($columnasBase, $headers);

                if (!empty($faltantes)) {
                    $alerta = "⚠️ Hoja '{$hoja}' omitida: faltan columnas requeridas.";
                    fwrite(STDERR, $alerta . "\n");
                    $alertas[] = [
                        'tipo' => 'columnas_faltantes',
                        'mensaje' => $alerta,
                        'hoja' => $hoja,
                        'archivo' => $nombreArchivo
                    ];
                    continue;
                }

                // Identificar columnas QNA
                $qnas = array_filter($headers, fn($h) => preg_match('/^QNA([1-9]|1[0-9]|2[0-4])$/', $h));
                $registrosValidos = 0;

                // Procesar filas
                for ($i = 1; $i < count($data); $i++) {
                    $row = array_combine($headers, $data[$i]) ?: [];

                    $rfc = $this->limpiarRfc($row['RFC'] ?? null);
                    if (!$rfc) {
                        continue;
                    }

                    // Extraer QNAs activas
                    $qnasActivas = [];
                    foreach ($qnas as $q) {
                        $valor = $row[$q] ?? null;
                        if ($this->esActivo($valor)) {
                            $qnasActivas[$q] = $valor;
                        }
                    }

                    // Agregar registro individual
                    $registros[] = [
                        'rfc' => $rfc,
                        'ente' => $claveEnte,
                        'nombre' => trim((string)($row['NOMBRE'] ?? '')),
                        'puesto' => trim((string)($row['PUESTO'] ?? '')),
                        'fecha_ingreso' => $this->limpiarFecha($row['FECHA_ALTA'] ?? null),
                        'fecha_egreso' => $this->limpiarFecha($row['FECHA_BAJA'] ?? null),
                        'qnas' => $qnasActivas,
                        'monto' => $row['TOT_PERC'] ?? null,
                    ];
                    $registrosValidos++;
                }

                fwrite(STDERR, "✅ Hoja '{$hoja}': {$registrosValidos} registros procesados.\n");
            }
        }

        fwrite(STDERR, sprintf("📈 %d registros individuales extraídos.\n", count($registros)));

        return [$registros, $alertas];
    }

    /**
     * Procesa archivos y detecta cruces quincenales
     *
     * @param array<int, mixed> $archivos
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public function procesarArchivos(array $archivos): array
    {
        fwrite(STDERR, sprintf("📊 Procesando %d archivo(s) laborales...\n", count($archivos)));

        $entesRfc = [];
        $alertas = [];

        foreach ($archivos as $f) {
            $nombreArchivo = $this->getNombreArchivo($f);
            fwrite(STDERR, "📘 Leyendo archivo: {$nombreArchivo}\n");

            try {
                $filePath = $this->getFilePath($f);
                $spreadsheet = IOFactory::load($filePath);
            } catch (\Exception $e) {
                $alertas[] = [
                    'tipo' => 'error_lectura',
                    'mensaje' => "Error al leer archivo: {$e->getMessage()}",
                    'archivo' => $nombreArchivo
                ];
                continue;
            }

            foreach ($spreadsheet->getSheetNames() as $hoja) {
                $enteLabel = strtoupper(trim($hoja));
                $claveEnte = $this->normalizarEnteClave($enteLabel);

                if (!$claveEnte) {
                    $alerta = "⚠️ Hoja '{$hoja}' no encontrada en catálogo de entes. Verifique el nombre.";
                    fwrite(STDERR, $alerta . "\n");
                    $alertas[] = [
                        'tipo' => 'ente_no_encontrado',
                        'mensaje' => $alerta,
                        'hoja' => $hoja,
                        'archivo' => $nombreArchivo
                    ];
                    continue;
                }

                $worksheet = $spreadsheet->getSheetByName($hoja);
                if (!$worksheet) {
                    continue;
                }

                $data = $worksheet->toArray();

                if (empty($data)) {
                    continue;
                }

                $headers = array_map(
                    fn($h) => strtoupper(str_replace(' ', '_', trim((string)$h))),
                    $data[0]
                );

                $columnasBase = ['RFC', 'NOMBRE', 'PUESTO', 'FECHA_ALTA', 'FECHA_BAJA'];
                $faltantes = array_diff($columnasBase, $headers);

                if (!empty($faltantes)) {
                    $alerta = "⚠️ Hoja '{$hoja}' omitida: faltan columnas requeridas.";
                    fwrite(STDERR, $alerta . "\n");
                    $alertas[] = [
                        'tipo' => 'columnas_faltantes',
                        'mensaje' => $alerta,
                        'hoja' => $hoja,
                        'archivo' => $nombreArchivo
                    ];
                    continue;
                }

                $qnas = array_filter($headers, fn($h) => preg_match('/^QNA([1-9]|1[0-9]|2[0-4])$/', $h));
                $registrosValidos = 0;

                for ($i = 1; $i < count($data); $i++) {
                    $row = array_combine($headers, $data[$i]) ?: [];

                    $rfc = $this->limpiarRfc($row['RFC'] ?? null);
                    if (!$rfc) {
                        continue;
                    }

                    $qnasActivas = [];
                    foreach ($qnas as $q) {
                        $valor = $row[$q] ?? null;
                        if ($this->esActivo($valor)) {
                            $qnasActivas[$q] = $valor;
                        }
                    }

                    $entesRfc[$rfc][] = [
                        'ente' => $claveEnte,
                        'nombre' => trim((string)($row['NOMBRE'] ?? '')),
                        'puesto' => trim((string)($row['PUESTO'] ?? '')),
                        'fecha_ingreso' => $this->limpiarFecha($row['FECHA_ALTA'] ?? null),
                        'fecha_egreso' => $this->limpiarFecha($row['FECHA_BAJA'] ?? null),
                        'qnas' => $qnasActivas,
                        'monto' => $row['TOT_PERC'] ?? null,
                    ];
                    $registrosValidos++;
                }

                fwrite(STDERR, "✅ Hoja '{$hoja}': {$registrosValidos} registros procesados.\n");
            }
        }

        $resultados = $this->crucesQuincenales($entesRfc);
        $sinCruce = $this->empleadosSinCruce($entesRfc, $resultados);
        $resultados = array_merge($resultados, $sinCruce);

        fwrite(STDERR, sprintf("📈 %d registros totales (incluye no duplicados).\n", count($resultados)));

        return [$resultados, $alertas];
    }

    /**
     * Identifica empleados sin cruce
     *
     * @param array<string, array<int, array<string, mixed>>> $entesRfc
     * @param array<int, array<string, mixed>> $hallazgos
     * @return array<int, array<string, mixed>>
     */
    private function empleadosSinCruce(array $entesRfc, array $hallazgos): array
    {
        $hallados = array_map(fn($h) => $h['rfc'], $hallazgos);
        $faltantes = [];

        foreach ($entesRfc as $rfc => $registros) {
            if (in_array($rfc, $hallados, true)) {
                continue;
            }

            $entes = array_unique(array_map(fn($r) => $r['ente'], $registros));
            sort($entes);

            $faltantes[] = [
                'rfc' => $rfc,
                'nombre' => $registros[0]['nombre'] ?? '',
                'entes' => $entes,
                'tipo_patron' => 'SIN_DUPLICIDAD',
                'descripcion' => 'Empleado sin cruce detectado',
                'registros' => $registros,
                'estado' => 'Sin valoración',
                'solventacion' => ''
            ];
        }

        return $faltantes;
    }

    /**
     * Detecta cruces quincenales (duplicidades)
     *
     * @param array<string, array<int, array<string, mixed>>> $entesRfc
     * @return array<int, array<string, mixed>>
     */
    private function crucesQuincenales(array $entesRfc): array
    {
        $hallazgos = [];

        foreach ($entesRfc as $rfc => $registros) {
            // Verificar si hay al menos 2 registros (diferentes entes)
            if (count($registros) < 2) {
                continue;
            }

            // Mapear QNAs por ente para detectar cruces
            $qnaMap = [];

            foreach ($registros as $reg) {
                foreach ($reg['qnas'] as $qna => $valor) {
                    if ($this->esActivo($valor)) {
                        $qnaMap[$qna][] = $reg;
                    }
                }
            }

            // Verificar si hay al menos una QNA con cruce real (2+ entes)
            $qnasConCruce = [];
            $entesInvolucrados = [];

            foreach ($qnaMap as $qna => $regsActivos) {
                $entesEnQna = array_unique(array_map(fn($r) => $r['ente'], $regsActivos));
                if (count($entesEnQna) > 1) {
                    $qnasConCruce[] = $qna;
                    $entesInvolucrados = array_merge($entesInvolucrados, $entesEnQna);
                }
            }

            // Si NO hay cruces reales, saltar este RFC
            if (empty($qnasConCruce)) {
                continue;
            }

            // Crear UN SOLO hallazgo consolidado para este RFC
            $entesInvolucrados = array_unique($entesInvolucrados);
            sort($entesInvolucrados);
            sort($qnasConCruce);

            $hallazgos[] = [
                'rfc' => $rfc,
                'nombre' => $registros[0]['nombre'] ?? '',
                'entes' => $entesInvolucrados,
                'qnas_cruce' => $qnasConCruce,
                'tipo_patron' => 'CRUCE_ENTRE_ENTES_QNA',
                'descripcion' => sprintf(
                    "Activo en %d entes durante %d quincena(s) simultáneas.",
                    count($entesInvolucrados),
                    count($qnasConCruce)
                ),
                'registros' => $registros,
                'estado' => 'Sin valoración',
                'solventacion' => ''
            ];
        }

        return $hallazgos;
    }

    /**
     * Obtiene el nombre del archivo desde diferentes tipos de entrada
     */
    private function getNombreArchivo(mixed $f): string
    {
        if (is_array($f) && isset($f['name'])) {
            return $f['name'];
        }
        if (is_object($f) && isset($f->filename)) {
            return $f->filename;
        }
        if (is_string($f)) {
            return basename($f);
        }
        return 'archivo.xlsx';
    }

    /**
     * Obtiene la ruta del archivo desde diferentes tipos de entrada
     */
    private function getFilePath(mixed $f): string
    {
        if (is_array($f)) {
            $tmp = $f['tmp_name'] ?? '';
            if ($tmp !== '') {
                return (string)$tmp;
            }
        }
        if (is_string($f)) {
            return $f;
        }
        throw new \RuntimeException("No se pudo determinar la ruta del archivo");
    }
}
