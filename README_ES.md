# SASP (PHP) — Auditoria de Servicios Personales

SASP PHP ofrece el mismo flujo de auditoria para servicios personales y cumplimiento.

🔗 **En vivo:** https://sasp-php.omar-xyz.shop
🧪 **Demo local:** https://sasp-php.omar-xyz.shop
🌐 **Idioma:** Espanol

---

## Que puedes hacer
- Registrar auditorias y dar seguimiento
- Organizar evidencias y observaciones
- Exportar resumenes para reportes

---

© 2026 Omar Gabriel Salvatierra Garcia

## Instalacion automatica en nuevo servidor
Para no configurar desde cero, usa el instalador incluido:

```bash
bash scripts/install_report_service.sh
```

Este comando crea:
- `/etc/systemd/system/sasp-report.service`
- `/etc/systemd/system/sasp-report.timer`
- `/etc/sasp-report.env` (si no existe)

Despues, solo edita `EMAIL_PASS` en `/etc/sasp-report.env` y prueba:

```bash
sudo systemctl start sasp-report.service
sudo systemctl status sasp-report.service --no-pager
```

## Windows (Task Scheduler)
Si el servidor es Windows, usa estas utilidades:

1. Copia variables de entorno de ejemplo:
```powershell
Copy-Item deploy\sasp-report.windows.env.example deploy\sasp-report.windows.env
```
Edita `deploy\sasp-report.windows.env` y define `EMAIL_PASS`.

2. Instala tarea programada (cada hora):
```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_report_task.ps1 -Schedule Hourly
```

3. Ejecuta una prueba manual:
```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_report.ps1
```

Instalador rapido (ejecutable en Windows):
```bat
scripts\install_report_windows.bat
```
