# SASP-PHP Project Catalog

**Version**: PHP 8.2+ Port
**Original**: Python/Flask SASP System
**Purpose**: Employee Duplication Detection System for Tlaxcala State Superior Audit Office

---

## Directory Structure

```
06-sasp-php/
├── bin/                    # CLI executables
├── catalogos/              # Excel catalog files (entes, municipios, usuarios)
├── css/                    # Stylesheets
├── img/                    # Images and assets
├── js/                     # JavaScript files
├── src/                    # PHP source code (PSR-4 autoloaded)
├── templates/              # PHP view templates
├── tests/                  # PHPUnit test suite
├── uploads/                # User-uploaded files storage
├── index.php              # Web application entry point
├── config.php             # Runtime configuration
├── composer.json          # PHP dependencies
└── scil.db               # SQLite database file
```

---

## Source Code Structure (`src/`)

### Namespace: `Sasp`

All PHP classes use PSR-4 autoloading with the base namespace `Sasp\`.

```
src/
├── Cli/                    # Command-line interface components
│   └── Main.php           # CLI command dispatcher
├── Core/                   # Business logic and data layer
│   ├── DatabaseManager.php    # SQLite database abstraction
│   └── DataProcessor.php      # Excel processing & duplication detection
└── Web/                    # HTTP/Web layer components
    ├── Application.php    # Front controller & HTTP dispatcher
    └── Request.php        # HTTP request abstraction
```

---

## Class Catalog

### `Sasp\Cli\Main`
**File**: `src/Cli/Main.php`
**Purpose**: CLI command handler and dispatcher
**Entry Point**: `bin/sasp`

**Commands**:
- `serve [port]` - Start development web server (default: 5006)
- `db:init` - Initialize database tables
- `db:seed` - Load catalog data from Excel files
- `db:add-test-user` - Create test user (test/test1)
- `help` - Display help message

**Dependencies**:
- `DatabaseManager` - Database operations
- `PhpSpreadsheet` - Excel file reading

**Key Methods**:
- `run(array $argv): void` - Main entry point, routes commands via `match` expression
- `serve(int $port): void` - Starts PHP built-in web server
- `seedDatabase(): void` - Loads catalogs from `catalogos/` directory

---

### `Sasp\Core\DatabaseManager`
**File**: `src/Core/DatabaseManager.php`
**Purpose**: SQLite database abstraction and business logic
**Pattern**: Active Record / Repository

**Tables Managed**:
- `registros_laborales` - Individual employee records (RFC + ente pairs)
- `solventaciones` - Audit resolution records
- `usuarios` - User accounts and permissions
- `entes` - State-level entity catalog
- `municipios` - Municipal entity catalog
- `laboral` - Legacy analysis results table

**Key Methods**:

**Initialization**:
- `__construct(?string $dbPath = null)` - Creates/connects to database
- `initDb(): void` - Creates all table schemas
- `resolveDbPath(string $dbPath): string` - Resolves database file path (respects `SCIL_DB` env var)

**Entity Resolution**:
- `normalizarEnteClave(string $input, ?array $entesById = null): ?string` - Resolves sigla/nombre/clave to canonical clave
- `obtenerTodosEntesIndexados(): array` - Returns all entes indexed by clave
- `obtenerTodosMunicipiosIndexados(): array` - Returns all municipios indexed by clave

**Data Operations**:
- `guardarRegistrosIndividuales(array $registros): void` - UPSERT employee records
- `obtenerCrucesReales(): array` - Detects RFC duplications across entities
- `guardarSolventacion(array $data): void` - Saves audit resolution
- `obtenerSolventacion(string $rfc, string $ente): ?array` - Retrieves resolution record

**User Management**:
- `validarUsuario(string $usuario, string $clave): ?array` - Authenticates user (SHA256)
- `crearUsuario(string $usuario, string $clave, string $entes, string $nombreCompleto): void` - Creates user account

**Catalog Loading**:
- `cargarCatalogoEntes(string $excelPath): int` - Loads entes from Excel
- `cargarCatalogoMunicipios(string $excelPath): int` - Loads municipios from Excel
- `cargarUsuarios(string $excelPath): int` - Loads users from Excel

---

### `Sasp\Core\DataProcessor`
**File**: `src/Core/DataProcessor.php`
**Purpose**: Excel file processing and duplication detection
**Dependencies**: PhpSpreadsheet, DatabaseManager

**Key Methods**:

**Excel Processing**:
- `procesarArchivos(array $archivos, DatabaseManager $db): array` - Processes uploaded Excel files, detects cruces
- `extraerRegistrosIndividuales(array $archivos, DatabaseManager $db): array` - Extracts RFC+ENTE records for database storage

**Data Extraction**:
- `extraerDatosDeHoja(Worksheet $hoja): array` - Extracts employee data from Excel sheet
- `detectarColumnasQna(array $encabezados): array` - Identifies QNA columns (QNA1-QNA24)
- `obtenerIndicesColumnas(array $encabezados): array` - Maps column headers to indices

**Data Normalization**:
- `normalizarRFC(string $rfc): string` - Cleans and validates RFC format
- `normalizarFecha(mixed $fecha): ?string` - Converts Excel dates to YYYY-MM-DD
- `normalizarQnaValue(mixed $valor): bool` - Determines if QNA value indicates active employment

**Duplication Detection**:
- `calcularCruces(array $empleados): array` - Identifies temporal intersections (same RFC, different entes, overlapping QNAs)
- `agruparPorRFC(array $empleados): array` - Groups employee records by RFC
- `tieneQnasEnComun(array $qnas1, array $qnas2): array` - Finds QNA intersections between two records

**Constants**:
- `QNA_COLUMNS` - Array of valid QNA column names (QNA1 through QNA24)
- `INACTIVE_VALUES` - Values considered "inactive" for QNA detection

---

### `Sasp\Web\Application`
**File**: `src/Web/Application.php`
**Purpose**: Front controller, HTTP dispatcher, authentication, templating
**Pattern**: Front Controller

**Key Methods**:

**Core Lifecycle**:
- `run(): void` - Main application entry point
- `dispatch(Request $request): void` - Route dispatcher (manual routing via `match`)
- `serveStatic(string $path): void` - Static file serving (/css/, /js/, /img/)

**Authentication**:
- `verificarAutenticacion(Request $request): bool` - Session-based auth check
- `login(Request $request): void` - `POST /login` handler
- `logout(Request $request): void` - `POST /logout` handler

**Permission System**:
- `allowedAll(): ?string` - Returns permission mode: `ALL`, `ENTES`, `MUNICIPIOS`, or null
- `puedeVerEnte(string $enteClave): bool` - Checks if user can view specific entity
- `entesCache(): array` - Static cache of all entities (entes + municipios) indexed by clave

**Entity Display**:
- `enteDisplay(string $clave): string` - Converts clave to display name
- `enteSigla(string $clave): string` - Converts clave to sigla/short name

**Route Handlers**:
- `dashboard(Request $request): void` - `GET /dashboard` - Main dashboard
- `uploadLaboral(Request $request): void` - `POST /upload_laboral` - Excel file upload
- `reportePorEnte(Request $request): void` - `GET /resultados` - Duplication results
- `catalogos(Request $request): void` - `GET /catalogos` - Entity catalogs view
- `detalleRFC(Request $request): void` - `GET /detalle/{rfc}` - RFC detail view
- `solventacion(Request $request): void` - `GET/POST /solventacion/{rfc}` - Audit resolution form
- `actualizarEstado(Request $request): void` - `POST /actualizar_estado` - AJAX status update

**Templating**:
- `render(string $template, array $data = []): void` - Renders PHP template with `extract()`
- `redirect(string $url, int $code = 302): void` - HTTP redirect

**Utility**:
- `formatearQnas(string $qnasJson): string` - Formats QNA JSON for display
- `filtrarDuplicadosReales(array $cruces): array` - Eliminates false positives (no QNA intersection)

**Routes Map**:
```
GET  /                    → login page (if not authenticated) / redirect to dashboard
GET  /login              → login page
POST /login              → authenticate user
POST /logout             → logout user
GET  /dashboard          → main dashboard
POST /upload_laboral     → upload Excel files
GET  /resultados         → duplication detection results
GET  /catalogos          → view entity catalogs
GET  /detalle/{rfc}      → RFC detail view
GET  /solventacion/{rfc} → audit resolution form (query param: ente)
POST /solventacion/{rfc} → save audit resolution
POST /actualizar_estado  → AJAX status update
GET  /css/*              → static CSS files
GET  /js/*               → static JavaScript files
GET  /img/*              → static image files
```

---

### `Sasp\Web\Request`
**File**: `src/Web/Request.php`
**Purpose**: HTTP request abstraction (wrapper for PHP superglobals)

**Key Methods**:
- `method(): string` - HTTP method (supports `_method` override in POST)
- `path(): string` - Request URI path
- `query(string $key, $default = null): mixed` - GET parameter
- `input(string $key, $default = null): mixed` - POST parameter
- `jsonBody(): ?array` - Parses JSON request body
- `isAjax(): bool` - Checks if request is AJAX
- `session(string $key, $default = null): mixed` - Session value getter
- `setSession(string $key, $value): void` - Session value setter
- `destroySession(): void` - Destroys session

---

## Templates Catalog (`templates/`)

All templates are plain PHP files. Data is passed via `extract($data)`.

### `base.php`
**Purpose**: Master layout template
**Includes**: HTML structure, CSS/JS includes, navigation
**Blocks**: `$title`, `$contenido` (main content)

### `login.php`
**Route**: `GET /login`
**Form**: `POST /login`
**Fields**: `usuario`, `clave`

### `dashboard.php`
**Route**: `GET /dashboard`
**Purpose**: Main application dashboard
**Features**: File upload form, quick stats, navigation

### `resultados.php`
**Route**: `GET /resultados`
**Purpose**: Duplication detection results
**Data**: `$porEnte` (grouped by entity), `$totalEmpleados`, `$totalDuplicados`

### `detalle_rfc.php`
**Route**: `GET /detalle/{rfc}`
**Purpose**: Detailed view of specific RFC across all entities
**Data**: `$rfc`, `$registros`, `$solventaciones`

### `solventacion.php`
**Route**: `GET /solventacion/{rfc}?ente={ente}`
**Purpose**: Audit resolution form
**Form**: `POST /solventacion/{rfc}`
**Fields**: `rfc`, `ente`, `tipo_inconsistencia`, `observaciones`, `estado`, `fecha_resolucion`

### `catalogos.php`
**Route**: `GET /catalogos`
**Purpose**: View entity catalogs (entes + municipios)
**Data**: `$entes`, `$municipios`

### `empty.php`
**Purpose**: Blank template for custom layouts

---

## Test Catalog (`tests/`)

### Structure
```
tests/
├── fixtures/              # Test data files
├── Integration/           # Integration tests (database, HTTP)
└── Unit/                  # Unit tests (isolated logic)
    └── ApplicationRequestMethodTest.php
```

### `ApplicationRequestMethodTest`
**File**: `tests/Unit/ApplicationRequestMethodTest.php`
**Tests**: HTTP method override via `_method` parameter
**Coverage**: `Request::method()`, `Application` routing

---

## Database Schema

### `registros_laborales`
**Purpose**: Individual employee records (RFC + ente pairs)

```sql
CREATE TABLE registros_laborales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rfc TEXT NOT NULL,
    ente TEXT NOT NULL,
    nombre_completo TEXT,
    fecha_inicio TEXT,
    fecha_fin TEXT,
    qnas TEXT,  -- JSON: {"QNA1": true, "QNA5": true, ...}
    archivo_origen TEXT,
    fecha_carga TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(rfc, ente)
)
```

### `solventaciones`
**Purpose**: Audit resolutions per RFC-ente pair

```sql
CREATE TABLE solventaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rfc TEXT NOT NULL,
    ente TEXT NOT NULL,
    tipo_inconsistencia TEXT,
    observaciones TEXT,
    estado TEXT DEFAULT 'Pendiente',
    fecha_resolucion TEXT,
    responsable TEXT,
    fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(rfc, ente)
)
```

### `usuarios`
**Purpose**: User accounts and permissions

```sql
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT UNIQUE NOT NULL,
    clave TEXT NOT NULL,  -- SHA256 hash
    entes TEXT,           -- Comma-separated claves or keywords
    nombre_completo TEXT,
    activo INTEGER DEFAULT 1
)
```

**Special `entes` values**:
- `TODOS` - Access to all entities (entes + municipios)
- `TODOS LOS ENTES` - Access to all state entities only
- `TODOS LOS MUNICIPIOS` - Access to all municipal entities only
- Comma-separated claves - Access to specific entities

### `entes`
**Purpose**: State-level entity catalog

```sql
CREATE TABLE entes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    clave TEXT UNIQUE NOT NULL,
    siglas TEXT,
    nombre TEXT,
    tipo TEXT DEFAULT 'ENTE'
)
```

### `municipios`
**Purpose**: Municipal entity catalog

```sql
CREATE TABLE municipios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    clave TEXT UNIQUE NOT NULL,
    siglas TEXT,
    nombre TEXT,
    tipo TEXT DEFAULT 'MUNICIPIO'
)
```

### `laboral`
**Purpose**: Legacy table for analysis results (compatibility)

```sql
CREATE TABLE laboral (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data TEXT,  -- JSON serialized analysis results
    fecha TEXT DEFAULT CURRENT_TIMESTAMP
)
```

---

## Catalog Files (`catalogos/`)

### `Estatales.xlsx`
**Table**: `entes`
**Required Columns**: `CLAVE`, `SIGLAS`, `NOMBRE`
**Type**: State-level government entities

### `Municipales.xlsx`
**Table**: `municipios`
**Required Columns**: `CLAVE`, `SIGLAS`, `NOMBRE`
**Type**: Municipal government entities

### `Usuarios_SASP_2025.xlsx`
**Table**: `usuarios`
**Required Columns**: `USUARIO`, `CLAVE`, `ENTES`, `NOMBRE_COMPLETO`
**Password**: Stored as SHA256 hash

**Loading**: Run `php bin/sasp db:seed` to load all catalog files.

---

## Static Assets

### CSS (`css/`)
- `style.css` - Main stylesheet (minimalist design, responsive)

### JavaScript (`js/`)
- `main.js` - Client-side interactions, AJAX handlers, form validation

### Images (`img/`)
- `ofs_logo.png` - Tlaxcala State Superior Audit Office logo

---

## Configuration Files

### `config.php`
**Purpose**: Runtime configuration defaults
**Used by**: `index.php` (web entry point)
**Contents**: Environment-specific settings, paths, constants

### `composer.json`
**Purpose**: PHP dependency management
**Key Dependencies**:
- `phpoffice/phpspreadsheet` - Excel file processing
- `phpunit/phpunit` - Testing framework (dev)

**Autoloading**: PSR-4 standard, `Sasp\` namespace → `src/` directory

### `.htaccess`
**Purpose**: Apache rewrite rules
**Function**: Routes all requests to `index.php` (front controller pattern)

---

## Entry Points

### Web Application: `index.php`
```php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$app = new \Sasp\Web\Application();
$app->run();
```

### CLI Tool: `bin/sasp`
```php
#!/usr/bin/env php
require_once __DIR__ . '/../vendor/autoload.php';

\Sasp\Cli\Main::run($argv);
```

---

## Data Flow Diagrams

### File Upload Flow
```
User uploads Excel → POST /upload_laboral
    ↓
Application::uploadLaboral()
    ↓
DataProcessor::extraerRegistrosIndividuales()
    ↓
DatabaseManager::guardarRegistrosIndividuales()
    ↓
Database: registros_laborales (UPSERT)
```

### Duplication Detection Flow
```
User views results → GET /resultados
    ↓
Application::reportePorEnte()
    ↓
DatabaseManager::obtenerCrucesReales()
    ↓
Groups by RFC, detects QNA intersections
    ↓
Application::filtrarDuplicadosReales()
    ↓
Template: resultados.php
```

### Audit Resolution Flow
```
User submits resolution → POST /solventacion/{rfc}
    ↓
Application::solventacion()
    ↓
DatabaseManager::guardarSolventacion()
    ↓
Database: solventaciones (UPSERT)
    ↓
Redirect to /detalle/{rfc}
```

---

## Permission System

### Modes
1. **ALL** (`TODOS`) - Full access to all entities
2. **ENTES** (`TODOS LOS ENTES`) - Access to state entities only
3. **MUNICIPIOS** (`TODOS LOS MUNICIPIOS`) - Access to municipal entities only
4. **SPECIFIC** (comma-separated claves) - Access to listed entities only

### Implementation
- **Session Storage**: User's permission mode stored in `$_SESSION['allowed_all']`
- **Entity Filter**: `puedeVerEnte()` checks if user can view specific entity
- **Data Filtering**: Results filtered based on user permissions in all views

---

## Key Design Patterns

### Front Controller
**Class**: `Application`
**Entry**: `index.php`
**Pattern**: All HTTP requests routed through single entry point

### Repository Pattern
**Class**: `DatabaseManager`
**Purpose**: Abstracts database operations from business logic

### Template View
**Pattern**: Plain PHP templates with `extract()`
**Location**: `templates/` directory

### Service Layer
**Class**: `DataProcessor`
**Purpose**: Encapsulates Excel processing and duplication detection logic

### Request-Response
**Class**: `Request`
**Purpose**: Abstracts HTTP request/response handling

---

## Excel File Format

### Employee Data Sheets
**Structure**: Multiple sheets per file, each sheet = one entity
**Sheet Name**: Entity sigla/nombre (resolved via catalog lookup)

**Required Columns**:
- `RFC` - Employee tax ID
- `NOMBRE COMPLETO` / `NOMBRE` - Full name
- `FECHA INICIO` / `FECHA_INICIO` - Start date
- `FECHA FIN` / `FECHA_FIN` - End date
- `QNA1` through `QNA24` - Pay period activity flags

**QNA Values** (case-insensitive):
- **Active**: Any value except inactive values
- **Inactive**: `""`, `"0"`, `"0.0"`, `"NO"`, `"N/A"`, `"NA"`, `"NONE"`

**Date Formats**:
- Excel numeric dates (converted automatically)
- String dates: `YYYY-MM-DD`, `DD/MM/YYYY`, `MM/DD/YYYY`

---

## Environment Variables

### `SCIL_DB`
**Purpose**: Override SQLite database file path
**Default**: `scil.db` in project root
**Values**: Absolute path or relative to project root

### `PORT`
**Purpose**: Development server port
**Default**: `5006`
**Used by**: `php bin/sasp serve [PORT]`

---

## Development Workflow

### Initial Setup
```bash
composer install
php bin/sasp db:init
php bin/sasp db:seed
php bin/sasp db:add-test-user
```

### Run Development Server
```bash
php bin/sasp serve
# Visit: http://localhost:5006
```

### Run Tests
```bash
vendor/bin/phpunit
```

### Upload Data
1. Visit `/dashboard`
2. Upload Excel files with employee data
3. View results at `/resultados`

### Manage Resolutions
1. Click on RFC in results
2. Fill out resolution form
3. Save and track status

---

## Deployment Checklist

- [ ] Set `SCIL_DB` environment variable to production database path
- [ ] Ensure SQLite file has write permissions for web server user
- [ ] Configure Apache `.htaccess` or Nginx rewrite rules
- [ ] Run `php bin/sasp db:init` to create tables
- [ ] Run `php bin/sasp db:seed` to load catalogs
- [ ] Create production users (remove test users)
- [ ] Set secure file upload limits in `php.ini`
- [ ] Enable error logging (disable display in production)
- [ ] Set proper permissions on `uploads/` directory

---

## Comparison: Python vs PHP

### Architecture Changes
| Aspect | Python (Flask) | PHP Port |
|--------|----------------|----------|
| **Structure** | Single `app.py` file | Modular PSR-4 namespaces |
| **Routing** | Flask decorators | Manual `match` expressions |
| **Templating** | Jinja2 | Plain PHP with `extract()` |
| **CLI** | Separate scripts | Integrated CLI tool |
| **Autoloading** | Python imports | Composer PSR-4 |
| **Sessions** | Flask sessions | Native PHP sessions |

### Behavioral Parity
- **Password Hashing**: SHA256 (maintained for compatibility)
- **Database Schema**: Identical table structures
- **QNA Logic**: Same detection algorithm
- **Permission System**: Identical access control rules
- **Excel Processing**: Same normalization and validation

---

## Future Enhancements

### Security
- Migrate from SHA256 to `password_hash()` (bcrypt/argon2)
- Add CSRF protection
- Implement rate limiting for login attempts

### Features
- Export results to Excel/PDF
- Advanced search and filtering
- Audit trail logging
- Email notifications for resolutions

### Performance
- Add database indices for frequently queried columns
- Implement result caching
- Optimize duplication detection query

### Testing
- Expand integration test coverage
- Add end-to-end tests
- Performance benchmarks

---

## Support & Documentation

**Main Documentation**: `CLAUDE.md` - AI assistant instructions and architectural details
**User Guide**: `README.md` - End-user setup and usage instructions
**This File**: `CATALOG.md` - Comprehensive codebase reference

---

**Document Version**: 1.0
**Last Updated**: 2026-01-06
**Maintainer**: SASP-PHP Development Team
