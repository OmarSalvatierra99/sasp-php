<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $title ?? 'SASP - Sistema de Auditoría de Servicios Personales'; ?></title>

  <!-- Recursos globales -->
  <link rel="icon" href="/img/ofs_logo.png" type="image/png">
  <link rel="stylesheet" href="/css/style.css">
  <script defer src="/js/main.js"></script>
</head>

<body>
  <!-- ==========================================
       BARRA DE NAVEGACIÓN
  =========================================== -->
  <nav class="navbar">
    <div class="nav-container">
      <div class="nav-brand">
        <img src="/img/ofs_logo.png" alt="OFS" class="nav-logo">
        <div class="nav-title">
          <h1 class="nav-system">SASP</h1>
          <span class="nav-subtitle">Sistema de Auditoría de Servicios Personales</span>
          <small class="nav-org">Órgano de Fiscalización Superior del Estado de Tlaxcala</small>
        </div>
      </div>

      <div class="nav-links">
        <a href="/dashboard" class="nav-link">Inicio</a>
        <a href="/resultados" class="nav-link">Resultados</a>
        <a href="/catalogos" class="nav-link">Catálogos</a>
        <a href="/logout" class="nav-link">Salir</a>
      </div>
    </div>
  </nav>

  <!-- ==========================================
       CONTENIDO PRINCIPAL
  =========================================== -->
  <main class="main-content">
    <?php echo $content ?? ''; ?>
  </main>

  <!-- ==========================================
       PIE DE PÁGINA
  =========================================== -->
  <footer>
    <p>© 2025 Órgano de Fiscalización Superior del Estado de Tlaxcala — SASP</p>
  </footer>
</body>
</html>
