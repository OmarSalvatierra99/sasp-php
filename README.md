# SASP-PHP

PHP port of SASP (Sistema de Auditoría de Servicios Personales) — Employee Duplication Detection System for the Tlaxcala State Superior Audit Office.

## Overview

SASP is a web application that:
- Uploads Excel files with employee data per government entity
- Detects duplications: employees working in multiple entities during the same pay period (quincena/QNA)
- Manages audit resolutions ("solventaciones") and status tracking per employee-entity pair
- Provides role-based access control for different government entities

This is a **byte-for-byte port** from the original Flask (Python) application to PHP 8.2+.

## Requirements

- PHP 8.2 or higher
- PHP Extensions:
  - `pdo_sqlite`
  - `gd`
  - `iconv`
  - `json`
  - `mbstring`
- Composer (for dependency management)
- Web server (Apache/Nginx) or PHP built-in server

## Installation

### 1. Install Dependencies

```bash
composer install
```

### 2. Initialize Database

```bash
php bin/sasp db:init
```

### 3. Seed Database with Initial Data

```bash
php bin/sasp db:seed
```

This will:
- Create initial users (odilia, felipe)
- Load entes (state entities) from `catalogos/Estatales.xlsx`
- Load municipios from `catalogos/Municipales.xlsx`
- Load users from `catalogos/Usuarios_SASP_2025.xlsx`

## Usage

### Development Server

Start the built-in PHP development server:

```bash
php bin/sasp serve
# Server starts on http://0.0.0.0:5006

# Or specify a custom port:
php bin/sasp serve 8080
```

### Production Deployment

#### Apache

1. Point your virtual host document root to the project root
2. Ensure `.htaccess` is enabled (`AllowOverride All`)
3. Restart Apache

Example Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName sasp.local
    DocumentRoot /path/to/sasp-php

    <Directory /path/to/sasp-php>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sasp_error.log
    CustomLog ${APACHE_LOG_DIR}/sasp_access.log combined
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name sasp.local;
    root /path/to/sasp-php;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

## CLI Commands

SASP includes a command-line interface for common operations:

```bash
# Show help
php bin/sasp help

# Start development server
php bin/sasp serve [port]

# Initialize database (create tables)
php bin/sasp db:init

# Seed database with initial data
php bin/sasp db:seed
```

## Environment Variables

- `SCIL_DB`: Path to SQLite database file (default: `scil.db`)
- `PORT`: Server port for development server (default: `5006`)

Example:

```bash
export SCIL_DB=/var/data/sasp.db
export PORT=8080
php bin/sasp serve
```

## Project Structure

```
sasp-php/
├── bin/
│   └── sasp              # CLI entry point
├── catalogos/            # Excel seed data files
│   ├── Estatales.xlsx    # State entities catalog
│   ├── Municipales.xlsx  # Municipal entities catalog
│   └── Usuarios_SASP_2025.xlsx  # Users catalog
├── composer              # Local Composer PHAR
├── composer.json         # Dependencies and autoload configuration
├── composer.lock         # Locked dependency versions
├── config.php            # Runtime defaults
├── css/                  # Stylesheets
├── img/                  # Images and icons
├── index.php             # Web entry point
├── js/                   # Front-end scripts
├── scil.db               # SQLite database (dev default)
├── src/
│   ├── Cli/
│   │   └── Main.php      # CLI command handler
│   ├── Core/
│   │   ├── DatabaseManager.php   # SQLite database operations
│   │   └── DataProcessor.php     # Excel processing and duplication detection
│   └── Web/
│       └── Application.php       # Web application and routes
├── templates/            # Jinja2/PHP templates
├── tests/                # PHPUnit tests
├── uploads/              # Downloadable templates
├── vendor/               # Composer dependencies
└── README.md             # This file
```

## Default Users

After running `db:seed`, the following default users are available:

| Username | Password | Access |
|----------|----------|--------|
| odilia   | odilia2025 | All entities (superuser) |
| felipe   | felipe2025 | All entities (superuser) |

Additional users can be loaded from `catalogos/Usuarios_SASP_2025.xlsx`.

## Application Features

### 1. File Upload
- Upload Excel files (`.xlsx`, `.xls`) with employee data
- Each sheet in the file represents a different government entity
- Required columns: `RFC`, `NOMBRE`, `PUESTO`, `FECHA_ALTA`, `FECHA_BAJA`, `QNA1-QNA24`, `TOT_PERC`

### 2. Duplication Detection
- Detects employees (RFCs) working in multiple entities
- Identifies **temporal intersection** — same employee active in multiple entities during the same pay period (quincena)
- Groups duplications by entity and employee

### 3. Audit Workflow
- Review duplications by entity
- Mark each duplication as "Solventado" (Resolved) or "No Solventado" (Unresolved)
- Add comments and categorize resolutions
- Track audit status per employee-entity pair

### 4. Export Reports
- Export duplications by entity (Excel)
- Export general report with all duplications (Excel + summary)
- JSON API endpoints for programmatic access

### 5. Role-Based Access
- Users can only see entities they're authorized for
- Special permissions:
  - `TODOS` = All entities
  - `TODOS LOS ENTES` = State entities only
  - `TODOS LOS MUNICIPIOS` = Municipal entities only

## Development

### Code Quality

```bash
# Run tests
composer test

# PHP linting and formatting (PSR-12)
composer lint

# Static analysis with PHPStan
composer analyse
```

### Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/DatabaseTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## Database Schema

### Tables

- **registros_laborales**: Individual employee records (RFC + ente pairs)
- **solventaciones**: Audit resolutions per RFC-ente pair
- **usuarios**: User accounts with entity access permissions
- **entes**: State-level entities catalog
- **municipios**: Municipal entities catalog
- **laboral**: Legacy table for analysis results

### Key Logic

- **QNAs (Quincenas)**: Pay periods 1-24, stored as JSON: `{"QNA1": value, "QNA5": value, ...}`
- **Duplication**: Same RFC in multiple entities with intersecting QNAs
- **Normalization**: Entity references can be siglas, nombre, or clave — all resolve to clave internally

## API Endpoints

- `POST /upload_laboral` — Upload Excel files
- `GET /resultados` — View duplications grouped by entity
- `GET /resultados/{rfc}` — View details for a specific RFC
- `POST /actualizar_estado` — Update audit status (AJAX)
- `GET /exportar_por_ente?ente={ente}` — Export by entity
- `GET /exportar_general` — Export general report
- `GET /catalogos` — View entity catalogs

## Security Notes

- Passwords are hashed with **SHA256** (matching Python implementation for parity)
  - **Note**: For production, consider migrating to `password_hash()` with bcrypt
- Session-based authentication
- CSRF protection recommended for production
- Input validation on file uploads
- SQL injection protected via PDO prepared statements

## Known Limitations

1. **Template Conversion**: Not all Jinja2 templates have been converted to PHP yet
   - Base templates (login, dashboard, empty) are converted
   - Complex templates (resultados, detalle_rfc, solventacion) remain in Jinja2 format
   - Workaround: Update Application.php render() method to handle Jinja2 syntax or complete conversion

2. **Excel Export**: Export functionality requires PhpSpreadsheet to be fully implemented

3. **Password Hashing**: Uses SHA256 for Python parity, not bcrypt (for new deployments, migrate to `password_hash()`)

## Troubleshooting

### Database Not Found
```bash
# Ensure database is initialized
php bin/sasp db:init
```

### Permission Denied
```bash
# Ensure database file is writable
chmod 664 scil.db
chmod 775 .
```

### Composer Not Found
```bash
# Install Composer globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Port Already in Use
```bash
# Use a different port
php bin/sasp serve 8080
```

## Contributing

This is a port of an existing Python application. Changes should maintain parity with the original implementation unless explicitly documented in `PARITY.md`.

## License

Proprietary — Órgano de Fiscalización Superior del Estado de Tlaxcala

## Support

For issues or questions, consult:
- `PARITY.md` for feature differences
- `MIGRATION_NOTES.md` for porting details
- `CLAUDE.md` for development guidelines
