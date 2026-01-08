<?php

declare(strict_types=1);

namespace Sasp\Core;

use PDO;
use PDOException;

/**
 * DatabaseManager — SCIL 2025
 * Manejador central de base de datos SQLite
 *
 * Port of core/database.py from Python SASP
 */
class DatabaseManager
{
    private string $dbPath;
    private ?PDO $connection = null;

    private function log(string $message): void
    {
        $normalized = rtrim($message, "\r\n");

        // Only output to STDERR in CLI mode, use error_log in web context
        if (php_sapi_name() === 'cli' && defined('STDERR')) {
            error_log($normalized . PHP_EOL);
            return;
        }

        error_log($normalized);
    }

    public function __construct(string $dbPath = "scil.db")
    {
        $this->dbPath = $this->resolveDbPath($dbPath);
        $this->log("📂 Base de datos en uso: {$this->dbPath}");
        $this->initDb();
    }

    /**
     * Resuelve la ruta absoluta de la base de datos respetando SCIL_DB.
     */
    private function resolveDbPath(string $dbPath): string
    {
        error_log("DEBUG resolveDbPath START - input dbPath: " . $dbPath);
        error_log("DEBUG __DIR__: " . __DIR__);

        $envPath = getenv('SCIL_DB');
        error_log("DEBUG getenv('SCIL_DB'): " . ($envPath ?: 'false/empty'));
        if ($envPath !== false && $envPath !== '') {
            $dbPath = $envPath;
            error_log("DEBUG using envPath: " . $dbPath);
        }

        $isAbsolute = str_starts_with($dbPath, DIRECTORY_SEPARATOR)
            || (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbPath);
        error_log("DEBUG isAbsolute: " . ($isAbsolute ? 'true' : 'false'));

        $projectRoot = dirname(__DIR__, 2);
        error_log("DEBUG projectRoot: " . $projectRoot);

        $path = $isAbsolute ? $dbPath : $projectRoot . DIRECTORY_SEPARATOR . $dbPath;
        error_log("DEBUG path before realpath: " . $path);

        $resolved = realpath($path) ?: $path;
        error_log("DEBUG path after realpath: " . $resolved);

        return $resolved;
    }

    /**
     * Establece conexión con la base de datos SQLite
     */
    private function connect(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->connection = new PDO("sqlite:" . $this->dbPath);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this->connection;
    }

    /**
     * Expose the PDO connection for advanced operations (seed/export)
     */
    public function getConnection(): PDO
    {
        return $this->connect();
    }

    /**
     * Inicializa las tablas de la base de datos
     */
    private function initDb(): void
    {
        $conn = $this->connect();

        $conn->exec("
            CREATE TABLE IF NOT EXISTS laboral (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tipo_analisis TEXT NOT NULL,
                rfc TEXT NOT NULL,
                datos TEXT NOT NULL,
                hash_firma TEXT UNIQUE,
                fecha_analisis TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS registros_laborales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rfc TEXT NOT NULL,
                ente TEXT NOT NULL,
                nombre TEXT NOT NULL,
                puesto TEXT,
                fecha_ingreso TEXT,
                fecha_egreso TEXT,
                monto REAL,
                qnas TEXT NOT NULL,
                fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(rfc, ente)
            );

            CREATE TABLE IF NOT EXISTS solventaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rfc TEXT NOT NULL,
                ente TEXT NOT NULL,
                estado TEXT NOT NULL,
                comentario TEXT,
                catalogo TEXT,
                otro_texto TEXT,
                actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(rfc, ente)
            );

            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                usuario TEXT UNIQUE NOT NULL,
                clave TEXT NOT NULL,
                entes TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS entes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                num TEXT NOT NULL,
                clave TEXT UNIQUE NOT NULL,
                nombre TEXT NOT NULL,
                siglas TEXT,
                clasificacion TEXT,
                ambito TEXT,
                activo INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS municipios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                num TEXT NOT NULL,
                clave TEXT UNIQUE NOT NULL,
                nombre TEXT NOT NULL,
                siglas TEXT,
                clasificacion TEXT,
                ambito TEXT DEFAULT 'MUNICIPAL',
                activo INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Migrar columnas nuevas en solventaciones si no existen
        $this->migrateSolventacionesColumns();

        $this->log("✅ Tablas listas en {$this->dbPath}");
    }

    /**
     * Agrega columnas catalogo y otro_texto a solventaciones si no existen
     */
    public function migrateSolventacionesColumns(): void
    {
        $conn = $this->connect();
        $stmt = $conn->query("PRAGMA table_info(solventaciones)");
        $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');

        if (!in_array('catalogo', $columns, true)) {
            $conn->exec("ALTER TABLE solventaciones ADD COLUMN catalogo TEXT");
        }

        if (!in_array('otro_texto', $columns, true)) {
            $conn->exec("ALTER TABLE solventaciones ADD COLUMN otro_texto TEXT");
        }
    }

    /**
     * Poblar datos iniciales (usuarios y entes base)
     */
    public function poblarDatosIniciales(): void
    {
        $conn = $this->connect();

        $stmt = $conn->query("SELECT COUNT(*) FROM usuarios");
        if ($stmt->fetchColumn() == 0) {
            $base = [
                [
                    "C.P. Odilia Cuamatzi Bautista",
                    "odilia",
                    hash('sha256', "odilia2025"),
                    "TODOS"
                ],
                [
                    "C.P. Luis Felipe Camilo Fuentes",
                    "felipe",
                    hash('sha256', "felipe2025"),
                    "TODOS"
                ],
            ];

            $insertStmt = $conn->prepare(
                "INSERT INTO usuarios (nombre, usuario, clave, entes) VALUES (?, ?, ?, ?)"
            );
            foreach ($base as $user) {
                $insertStmt->execute($user);
            }
            $this->log("👥 Usuarios base insertados");
        }

        $stmt = $conn->query("SELECT COUNT(*) FROM entes");
        if ($stmt->fetchColumn() == 0) {
            $entes = [
                ["1.2", "ENTE_1_2", "Secretaría de Gobierno", "SEGOB", "Dependencia", "Estatal"],
                ["1.4", "ENTE_1_4", "Secretaría de Finanzas", "SEFIN", "Dependencia", "Estatal"],
                ["1.8", "ENTE_1_8", "Secretaría de Educación Pública", "SEPE", "Dependencia", "Estatal"],
            ];

            $insertStmt = $conn->prepare(
                "INSERT INTO entes (num, clave, nombre, siglas, clasificacion, ambito) VALUES (?,?,?,?,?,?)"
            );
            foreach ($entes as $ente) {
                $insertStmt->execute($ente);
            }
            $this->log("🏛️ Entes base insertados");
        }
    }

    /**
     * Lista entes ordenados por NUM (respeta el orden institucional jerárquico)
     *
     * @param bool $soloActivos Filtrar solo entes activos
     * @return array<int, array<string, mixed>>
     */
    public function listarEntes(bool $soloActivos = true): array
    {
        $conn = $this->connect();
        $q = "SELECT num, clave, nombre, siglas, clasificacion, ambito FROM entes";
        if ($soloActivos) {
            $q .= " WHERE activo=1";
        }
        $stmt = $conn->query($q);
        $data = $stmt->fetchAll();

        // Ordenamiento jerárquico para números tipo 1.2.3
        usort($data, function ($a, $b) {
            return $this->ordenJerarquico($a['num']) <=> $this->ordenJerarquico($b['num']);
        });

        return $data;
    }

    /**
     * Lista municipios ordenados por NUM
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarMunicipios(): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("
            SELECT num, clave, nombre, siglas, clasificacion, ambito
            FROM municipios
            WHERE activo=1
            ORDER BY CAST(num AS INTEGER), num
        ");
        return $stmt->fetchAll();
    }

    /**
     * Convierte NUM en tupla comparable (ej: "1.2.3" → [1, 2, 3, 0, 0])
     *
     * @param string $num Número jerárquico
     * @return array<int, int>
     */
    private function ordenJerarquico(string $num): array
    {
        $numStr = rtrim(trim($num), '.');
        $partes = [];

        foreach (explode('.', $numStr) as $parte) {
            $partes[] = is_numeric($parte) ? (int)$parte : 0;
        }

        // Rellenar con ceros hasta 5 partes
        while (count($partes) < 5) {
            $partes[] = 0;
        }

        return $partes;
    }

    /**
     * Genera diccionario {SIGLA_NORMALIZADA: CLAVE_ENTE}
     *
     * @return array<string, string>
     */
    public function getMapaSiglas(): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("
            SELECT siglas, clave FROM entes WHERE activo=1
            UNION ALL
            SELECT siglas, clave FROM municipios WHERE activo=1
        ");

        $mapa = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['siglas']) {
                $mapa[$this->sanitize($row['siglas'])] = $row['clave'];
            }
        }

        return $mapa;
    }

    /**
     * Genera diccionario {CLAVE_ENTE: SIGLA_O_NOMBRE}
     *
     * @return array<string, string>
     */
    public function getMapaClavesInverso(): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("
            SELECT clave, siglas, nombre FROM entes WHERE activo=1
            UNION ALL
            SELECT clave, siglas, nombre FROM municipios WHERE activo=1
        ");

        $mapa = [];
        foreach ($stmt->fetchAll() as $row) {
            $display = $row['siglas'] ?: $row['nombre'];
            $mapa[$this->sanitize($row['clave'])] = $this->sanitize($display);
        }

        return $mapa;
    }

    /**
     * Hash SHA256 de un texto
     */
    private function hashText(string $text): string
    {
        return hash('sha256', $text);
    }

    /**
     * Normaliza texto: mayúsculas, sin acentos, sin espacios extra
     * Replica Python: str(s).strip().upper() + reemplazo de acentos
     */
    private function sanitize(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        $s = mb_strtoupper(trim((string)$s), 'UTF-8');

        // Reemplazo de acentos (debe coincidir con Python)
        $acentos = ['Á', 'É', 'Í', 'Ó', 'Ú'];
        $sin_acentos = ['A', 'E', 'I', 'O', 'U'];
        $s = str_replace($acentos, $sin_acentos, $s);

        return $s;
    }

    /**
     * Busca un ente/municipio por sigla, clave o nombre y devuelve el NOMBRE completo
     */
    public function normalizarEnte(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $conn = $this->connect();
        $stmt = $conn->prepare("
            SELECT nombre FROM (
                SELECT nombre, siglas, clave FROM entes WHERE activo=1
                UNION ALL
                SELECT nombre, siglas, clave FROM municipios WHERE activo=1
            )
            WHERE UPPER(siglas)=UPPER(?) OR UPPER(clave)=UPPER(?) OR UPPER(nombre)=UPPER(?)
            LIMIT 1
        ");
        $stmt->execute([$valor, $valor, $valor]);
        $row = $stmt->fetch();

        return $row ? $row['nombre'] : null;
    }

    /**
     * Busca un ente/municipio por sigla, clave o nombre y devuelve la CLAVE única
     */
    public function normalizarEnteClave(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $val = $this->sanitize($valor);
        $conn = $this->connect();
        $stmt = $conn->prepare("
            SELECT clave FROM (
                SELECT clave, siglas, nombre FROM entes WHERE activo=1
                UNION ALL
                SELECT clave, siglas, nombre FROM municipios WHERE activo=1
            )
            WHERE UPPER(siglas)=? OR UPPER(nombre)=? OR UPPER(clave)=?
            LIMIT 1
        ");
        $stmt->execute([$val, $val, $val]);
        $row = $stmt->fetch();

        return $row ? $row['clave'] : null;
    }

    /**
     * Compara registros nuevos con histórico basado en hash
     *
     * @param array<int, array<string, mixed>> $nuevos
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: int}
     */
    public function compararConHistorico(array $nuevos): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("SELECT hash_firma FROM laboral");
        $existentes = array_filter(array_column($stmt->fetchAll(), 'hash_firma'));

        $nuevosValidos = [];
        $repetidos = [];

        foreach ($nuevos as $r) {
            $texto = json_encode($r, JSON_UNESCAPED_UNICODE);
            $h = $this->hashText($texto);

            if (!in_array($h, $existentes, true)) {
                $r['hash_firma'] = $h;
                $nuevosValidos[] = $r;
            } else {
                $repetidos[] = $r;
            }
        }

        return [$nuevosValidos, $repetidos, count($repetidos)];
    }

    /**
     * Guarda o actualiza registros individuales por RFC+ENTE
     *
     * @param array<int, array<string, mixed>> $registros
     * @return array{0: int, 1: int} [insertados, actualizados]
     */
    public function guardarRegistrosIndividuales(array $registros): array
    {
        if (empty($registros)) {
            return [0, 0];
        }

        $conn = $this->connect();
        $insertados = 0;
        $actualizados = 0;

        $stmt = $conn->prepare("
            INSERT INTO registros_laborales
            (rfc, ente, nombre, puesto, fecha_ingreso, fecha_egreso, monto, qnas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(rfc, ente) DO UPDATE SET
                nombre = excluded.nombre,
                puesto = excluded.puesto,
                fecha_ingreso = excluded.fecha_ingreso,
                fecha_egreso = excluded.fecha_egreso,
                monto = excluded.monto,
                qnas = excluded.qnas,
                fecha_actualizacion = CURRENT_TIMESTAMP
        ");

        $checkStmt = $conn->prepare("
            SELECT COUNT(*) FROM registros_laborales
            WHERE rfc=? AND ente=? AND fecha_carga < fecha_actualizacion
        ");

        foreach ($registros as $reg) {
            $rfc = $reg['rfc'] ?? '';
            $ente = $reg['ente'] ?? '';

            if (!$rfc || !$ente) {
                continue;
            }

            $qnasJson = json_encode($reg['qnas'] ?? [], JSON_UNESCAPED_UNICODE);

            try {
                $stmt->execute([
                    $rfc,
                    $ente,
                    $reg['nombre'] ?? '',
                    $reg['puesto'] ?? '',
                    $reg['fecha_ingreso'] ?? null,
                    $reg['fecha_egreso'] ?? null,
                    $reg['monto'] ?? null,
                    $qnasJson
                ]);

                if ($stmt->rowCount() > 0) {
                    $checkStmt->execute([$rfc, $ente]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $actualizados++;
                    } else {
                        $insertados++;
                    }
                }
            } catch (PDOException $e) {
                $this->log("⚠️  Error guardando RFC={$rfc}, ENTE={$ente}: {$e->getMessage()}");
                continue;
            }
        }

        return [$insertados, $actualizados];
    }

    /**
     * Cuenta el total de trabajadores (RFCs únicos) por ente
     *
     * @return array<string, int> {ente_clave: cantidad_de_rfc}
     */
    public function contarTrabajadoresPorEnte(): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("
            SELECT ente, COUNT(DISTINCT rfc) as total
            FROM registros_laborales
            GROUP BY ente
        ");

        $resultado = [];
        foreach ($stmt->fetchAll() as $row) {
            $resultado[$row['ente']] = (int)$row['total'];
        }

        return $resultado;
    }

    /**
     * Detecta empleados que están activos en más de un ente durante la misma QNA
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerCrucesReales(): array
    {
        $conn = $this->connect();
        $stmt = $conn->query("
            SELECT rfc, ente, nombre, puesto, fecha_ingreso, fecha_egreso, monto, qnas
            FROM registros_laborales
            ORDER BY rfc, ente
        ");

        $registros = [];
        foreach ($stmt->fetchAll() as $row) {
            $registros[] = [
                'rfc' => $row['rfc'],
                'ente' => $row['ente'],
                'nombre' => $row['nombre'],
                'puesto' => $row['puesto'],
                'fecha_ingreso' => $row['fecha_ingreso'],
                'fecha_egreso' => $row['fecha_egreso'],
                'monto' => $row['monto'],
                'qnas' => json_decode($row['qnas'], true)
            ];
        }

        // Agrupar por RFC
        $rfcsMap = [];
        foreach ($registros as $reg) {
            $rfcsMap[$reg['rfc']][] = $reg;
        }

        // Detectar cruces reales
        $cruces = [];
        foreach ($rfcsMap as $rfc => $regs) {
            if (count($regs) < 2) {
                continue;
            }

            // Verificar si hay cruces de QNAs entre diferentes entes
            $qnasPorEnte = [];
            foreach ($regs as $reg) {
                $qnasActivas = array_keys($reg['qnas']);
                $qnasPorEnte[$reg['ente']] = $qnasActivas;
            }

            // Buscar intersecciones
            $entesList = array_keys($qnasPorEnte);
            $qnasConCruce = [];
            $entesConCruce = [];

            for ($i = 0; $i < count($entesList); $i++) {
                for ($j = $i + 1; $j < count($entesList); $j++) {
                    $e1 = $entesList[$i];
                    $e2 = $entesList[$j];
                    $interseccion = array_intersect($qnasPorEnte[$e1], $qnasPorEnte[$e2]);

                    if (!empty($interseccion)) {
                        $qnasConCruce = array_merge($qnasConCruce, $interseccion);
                        $entesConCruce[] = $e1;
                        $entesConCruce[] = $e2;
                    }
                }
            }

            // Si hay cruce real, agregarlo
            if (!empty($qnasConCruce)) {
                $qnasConCruce = array_unique($qnasConCruce);
                $entesConCruce = array_unique($entesConCruce);
                sort($entesConCruce);
                sort($qnasConCruce);

                $cruces[] = [
                    'rfc' => $rfc,
                    'nombre' => $regs[0]['nombre'],
                    'entes' => $entesConCruce,
                    'qnas_cruce' => $qnasConCruce,
                    'tipo_patron' => 'CRUCE_ENTRE_ENTES_QNA',
                    'descripcion' => sprintf(
                        "Activo en %d entes durante %d quincena(s) simultáneas.",
                        count($entesConCruce),
                        count($qnasConCruce)
                    ),
                    'registros' => $regs,
                    'estado' => 'Sin valoración',
                    'solventacion' => ''
                ];
            }
        }

        return $cruces;
    }

    /**
     * Guarda resultados en la tabla laboral
     *
     * @param array<int, array<string, mixed>> $resultados
     * @return array{0: int, 1: int} [nuevos, duplicados]
     */
    public function guardarResultados(array $resultados): array
    {
        if (empty($resultados)) {
            return [0, 0];
        }

        $conn = $this->connect();
        $nuevos = 0;
        $duplicados = 0;

        $stmt = $conn->prepare("
            INSERT INTO laboral (tipo_analisis, rfc, datos, hash_firma)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($resultados as $r) {
            $texto = json_encode($r, JSON_UNESCAPED_UNICODE);
            $h = $this->hashText($texto);

            try {
                $stmt->execute([
                    $r['tipo_patron'] ?? 'GENERAL',
                    $r['rfc'] ?? '',
                    $texto,
                    $h
                ]);
                $nuevos++;
            } catch (PDOException $e) {
                $duplicados++;
            }
        }

        return [$nuevos, $duplicados];
    }

    /**
     * Obtiene resultados paginados
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function obtenerResultadosPaginados(
        string $tabla = "laboral",
        ?string $filtro = null,
        int $pagina = 1,
        int $limite = 10000
    ): array {
        $conn = $this->connect();
        $offset = ($pagina - 1) * $limite;

        if ($filtro) {
            $stmt = $conn->prepare(
                "SELECT datos FROM {$tabla} WHERE datos LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute(["%{$filtro}%", $limite, $offset]);
        } else {
            $stmt = $conn->prepare(
                "SELECT datos FROM {$tabla} ORDER BY id DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute([$limite, $offset]);
        }

        $resultados = [];
        foreach ($stmt->fetchAll() as $row) {
            try {
                $resultados[] = json_decode($row['datos'], true);
            } catch (\Exception $e) {
                continue;
            }
        }

        return [$resultados, count($resultados)];
    }

    /**
     * Obtiene todos los registros de un RFC específico
     *
     * @return array<string, mixed>|null
     */
    public function obtenerResultadosPorRfc(string $rfc): ?array
    {
        $conn = $this->connect();
        $stmt = $conn->prepare("
            SELECT rfc, ente, nombre, puesto, fecha_ingreso, fecha_egreso, monto, qnas
            FROM registros_laborales
            WHERE UPPER(rfc) = UPPER(?)
            ORDER BY ente
        ");
        $stmt->execute([$rfc]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $registros = [];
        $entes = [];
        $nombre = '';

        foreach ($rows as $row) {
            $nombre = $row['nombre'];
            $entes[] = $row['ente'];
            $registros[] = [
                'ente' => $row['ente'],
                'puesto' => $row['puesto'],
                'fecha_ingreso' => $row['fecha_ingreso'],
                'fecha_egreso' => $row['fecha_egreso'],
                'monto' => $row['monto'],
                'qnas' => json_decode($row['qnas'], true)
            ];
        }

        $entes = array_unique($entes);
        sort($entes);

        return [
            'rfc' => $rfc,
            'nombre' => $nombre,
            'entes' => $entes,
            'registros' => $registros,
            'estado' => 'Sin valoración',
            'solventacion' => ''
        ];
    }

    /**
     * Obtiene solventaciones por RFC
     *
     * @return array<string, array<string, string>>
     */
    public function getSolventacionesPorRfc(string $rfc): array
    {
        $conn = $this->connect();
        $stmt = $conn->prepare("SELECT ente, estado, comentario FROM solventaciones WHERE rfc=?");
        $stmt->execute([$rfc]);

        $data = [];
        foreach ($stmt->fetchAll() as $row) {
            $data[$row['ente']] = [
                'estado' => $row['estado'],
                'comentario' => $row['comentario']
            ];
        }

        return $data;
    }

    /**
     * Actualiza solventación
     */
    public function actualizarSolventacion(
        string $rfc,
        ?string $estado,
        ?string $comentario,
        ?string $catalogo = null,
        ?string $otroTexto = null,
        string $ente = "GENERAL"
    ): int {
        if (!$ente) {
            $ente = "GENERAL";
        }
        $ente = $this->normalizarEnteClave($ente) ?? $ente;

        if (!$estado) {
            $estado = "Sin valoración";
        }

        $conn = $this->connect();
        $stmt = $conn->prepare("
            INSERT INTO solventaciones (rfc, ente, estado, comentario, catalogo, otro_texto)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(rfc, ente) DO UPDATE SET
                estado=excluded.estado,
                comentario=excluded.comentario,
                catalogo=excluded.catalogo,
                otro_texto=excluded.otro_texto,
                actualizado=CURRENT_TIMESTAMP
        ");
        $stmt->execute([$rfc, $ente, $estado, $comentario, $catalogo, $otroTexto]);

        return $stmt->rowCount();
    }

    /**
     * Obtiene el estado de un RFC en un ente específico
     */
    public function getEstadoRfcEnte(string $rfc, string $enteClave): ?string
    {
        if (!$rfc || !$enteClave) {
            return null;
        }

        $conn = $this->connect();
        $stmt = $conn->prepare("
            SELECT estado FROM solventaciones
            WHERE rfc = ? AND ente = ?
            ORDER BY actualizado DESC
            LIMIT 1
        ");
        $stmt->execute([$rfc, $enteClave]);
        $row = $stmt->fetch();

        return $row ? $row['estado'] : null;
    }

    /**
     * Obtiene un usuario por nombre de usuario y contraseña
     *
     * @return array<string, mixed>|null
     */
    public function getUsuario(string $usuario, string $clave): ?array
    {
        if (!$usuario || !$clave) {
            return null;
        }

        $conn = $this->connect();
        $stmt = $conn->prepare("
            SELECT nombre, usuario, clave, entes
            FROM usuarios
            WHERE LOWER(usuario)=LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$usuario]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $claveHash = hash('sha256', $clave);
        if ($claveHash !== $row['clave']) {
            return null;
        }

        $entes = array_filter(
            array_map(
                fn($e) => strtoupper(trim($e)),
                explode(',', $row['entes'] ?? '')
            ),
            fn($e) => $e !== ''
        );

        return [
            'nombre' => $row['nombre'],
            'usuario' => $row['usuario'],
            'entes' => $entes
        ];
    }
}
