<?php

declare(strict_types=1);

namespace Sasp\Cli;

use Sasp\Core\DatabaseManager;

/**
 * Main — CLI Entry Point for SASP
 *
 * Handles CLI commands like serve, db:init, db:seed
 */
class Main
{
    /**
     * Run the CLI application
     *
     * @param array<int, string> $argv Command line arguments
     * @return int Exit code
     */
    public static function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        return match ($command) {
            'serve' => self::serve($argv),
            'db:init' => self::dbInit(),
            'db:seed' => self::dbSeed(),
            'db:add-test-user' => self::addTestUser(),
            'help', '--help', '-h' => self::help(),
            default => self::unknownCommand($command),
        };
    }

    /**
     * Start the development server
     *
     * @param array<int, string> $argv
     */
    private static function serve(array $argv): int
    {
        $port = (int)($_SERVER['PORT'] ?? getenv('PORT') ?: 5006);

        // Check if a port was specified as argument
        if (isset($argv[2]) && is_numeric($argv[2])) {
            $port = (int)$argv[2];
        }

        $host = '0.0.0.0';
        $docRoot = __DIR__ . '/../../public';

        // Create public directory if it doesn't exist
        if (!is_dir($docRoot)) {
            mkdir($docRoot, 0755, true);
        }

        // Create index.php router if it doesn't exist
        $indexPath = $docRoot . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, <<<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new \Sasp\Web\Application();
$app->run();
PHP
            );
        }

        fwrite(STDERR, "🚀 Iniciando servidor en http://{$host}:{$port}\n");
        fwrite(STDERR, "📂 Document root: {$docRoot}\n");
        fwrite(STDERR, "⏹️  Presiona Ctrl+C para detener\n\n");

        // Start PHP built-in server
        $cmd = sprintf(
            'php -S %s:%d -t %s %s',
            $host,
            $port,
            escapeshellarg($docRoot),
            escapeshellarg($indexPath)
        );

        passthru($cmd, $exitCode);

        return $exitCode;
    }

    /**
     * Initialize the database
     */
    private static function dbInit(): int
    {
        fwrite(STDOUT, "🔧 Inicializando base de datos...\n");

        $dbPath = $_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: 'scil.db';

        // Create DatabaseManager (this will initialize tables)
        new DatabaseManager($dbPath);

        fwrite(STDOUT, "✅ Base de datos inicializada correctamente\n");

        return 0;
    }

    /**
     * Seed the database with initial data
     */
    private static function dbSeed(): int
    {
        fwrite(STDOUT, "🌱 Poblando datos iniciales...\n");

        $dbPath = $_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: 'scil.db';
        $db = new DatabaseManager($dbPath);

        // Load data from Excel catalogs
        self::loadCatalogData($db);

        // Populate base users
        $db->poblarDatosIniciales();

        fwrite(STDOUT, "✅ Datos iniciales cargados correctamente\n");

        return 0;
    }

    /**
     * Load catalog data from Excel files
     */
    private static function loadCatalogData(DatabaseManager $db): void
    {
        $catalogosPath = __DIR__ . '/../../catalogos';

        // Load Entes (State entities)
        $entesFile = $catalogosPath . '/Estatales.xlsx';
        if (file_exists($entesFile)) {
            self::loadEntesFromExcel($db, $entesFile, 'entes');
            fwrite(STDOUT, "✅ Entes estatales cargados\n");
        }

        // Load Municipios
        $munisFile = $catalogosPath . '/Municipales.xlsx';
        if (file_exists($munisFile)) {
            self::loadEntesFromExcel($db, $munisFile, 'municipios');
            fwrite(STDOUT, "✅ Municipios cargados\n");
        }

        // Load Users
        $usersFile = $catalogosPath . '/Usuarios_SASP_2025.xlsx';
        if (file_exists($usersFile)) {
            self::loadUsersFromExcel($db, $usersFile);
            fwrite(STDOUT, "✅ Usuarios cargados\n");
        }
    }

    /**
     * Create an idempotent test user (usuario: test / clave: test1)
     */
    private static function addTestUser(): int
    {
        fwrite(STDOUT, "👤 Creando usuario de prueba...\n");

        $dbPath = $_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: 'scil.db';

        // DatabaseManager initializes the PDO SQLite connection and tables
        $db = new DatabaseManager($dbPath);
        $conn = $db->getConnection();

        $usuario = 'test';
        $clavePlano = 'test1';
        $claveHash = hash('sha256', $clavePlano); // reuse existing hashing used by getUsuario()

        $check = $conn->prepare("
            SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) LIMIT 1
        ");
        $check->execute([$usuario]);
        if ($check->fetchColumn()) {
            fwrite(STDOUT, "ℹ️  El usuario '{$usuario}' ya existe; no se hicieron cambios\n");
            return 0;
        }

        $insert = $conn->prepare("
            INSERT INTO usuarios (nombre, usuario, clave, entes)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute(['Usuario de prueba', $usuario, $claveHash, 'NINGUNO']);

        fwrite(STDOUT, "✅ Usuario '{$usuario}' creado con contraseña '{$clavePlano}'\n");

        return 0;
    }

    /**
     * Load entes or municipios from Excel file
     */
    private static function loadEntesFromExcel(DatabaseManager $db, string $file, string $table): void
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) {
                return;
            }

            // First row = headers
            $headers = array_map(
                fn($h) => strtoupper(str_replace(' ', '_', trim((string)$h))),
                $data[0]
            );

            $conn = $db->getConnection();

            $stmt = $conn->prepare("
                INSERT OR IGNORE INTO {$table} (num, clave, nombre, siglas, clasificacion, ambito)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $inserted = 0;
            for ($i = 1; $i < count($data); $i++) {
                $row = array_combine($headers, $data[$i]) ?: [];

                $num = trim((string)($row['NUM'] ?? $row['NUMERO'] ?? ''));
                $clave = trim((string)($row['CLAVE'] ?? ''));
                $nombre = trim((string)($row['NOMBRE'] ?? ''));
                $siglas = trim((string)($row['SIGLAS'] ?? ''));
                $clasificacion = trim((string)($row['CLASIFICACION'] ?? ''));
                $ambito = $table === 'municipios' ? 'MUNICIPAL' : 'ESTATAL';

                if (!$clave || !$nombre) {
                    continue;
                }

                $stmt->execute([$num, $clave, $nombre, $siglas, $clasificacion, $ambito]);
                if ($stmt->rowCount() > 0) {
                    $inserted++;
                }
            }

            fwrite(STDOUT, "  ↳ {$inserted} registros insertados en {$table}\n");
        } catch (\Exception $e) {
            fwrite(STDERR, "⚠️  Error cargando {$file}: {$e->getMessage()}\n");
        }
    }

    /**
     * Load users from Excel file
     */
    private static function loadUsersFromExcel(DatabaseManager $db, string $file): void
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) {
                return;
            }

            $headers = array_map(
                fn($h) => strtoupper(str_replace(' ', '_', trim((string)$h))),
                $data[0]
            );

            $conn = $db->getConnection();

            $stmt = $conn->prepare("
                INSERT OR IGNORE INTO usuarios (nombre, usuario, clave, entes)
                VALUES (?, ?, ?, ?)
            ");

            $inserted = 0;
            for ($i = 1; $i < count($data); $i++) {
                $row = array_combine($headers, $data[$i]) ?: [];

                $nombre = trim((string)($row['NOMBRE'] ?? ''));
                $usuario = trim((string)($row['USUARIO'] ?? ''));
                $clave = trim((string)($row['CLAVE'] ?? ''));
                $entes = trim((string)($row['ENTES'] ?? ''));

                if (!$usuario || !$clave) {
                    continue;
                }

                // Hash password with SHA256 to match Python
                $claveHash = hash('sha256', $clave);

                $stmt->execute([$nombre, $usuario, $claveHash, $entes]);
                if ($stmt->rowCount() > 0) {
                    $inserted++;
                }
            }

            fwrite(STDOUT, "  ↳ {$inserted} usuarios insertados\n");
        } catch (\Exception $e) {
            fwrite(STDERR, "⚠️  Error cargando {$file}: {$e->getMessage()}\n");
        }
    }

    /**
     * Display help information
     */
    private static function help(): int
    {
        echo <<<HELP
SASP — Sistema de Auditoría de Servicios Personales

Uso:
  php bin/sasp <comando> [argumentos]

Comandos disponibles:
  serve [puerto]     Inicia el servidor de desarrollo (puerto por defecto: 5006)
  db:init            Inicializa la base de datos y crea las tablas
  db:seed            Puebla la base de datos con datos iniciales
  db:add-test-user   Crea el usuario de prueba (usuario: test / clave: test1)
  help               Muestra esta ayuda

Ejemplos:
  php bin/sasp serve
  php bin/sasp serve 8080
  php bin/sasp db:init
  php bin/sasp db:seed

Variables de entorno:
  SCIL_DB            Ruta a la base de datos (por defecto: scil.db)
  PORT               Puerto del servidor (por defecto: 5006)

HELP;

        return 0;
    }

    /**
     * Handle unknown command
     */
    private static function unknownCommand(string $command): int
    {
        fwrite(STDERR, "❌ Comando desconocido: {$command}\n");
        fwrite(STDERR, "Use 'php bin/sasp help' para ver los comandos disponibles\n");
        return 1;
    }
}
