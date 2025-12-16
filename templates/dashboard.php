<?php
$title = 'SASP - Panel de Control';
ob_start();
?>

<div class="dashboard">

  <!-- Encabezado -->
  <header class="page-header">
    <h2>Bienvenido(a), <?php echo htmlspecialchars($nombre ?? 'Usuario'); ?></h2>
    <p class="subtitle">
      Cargue los archivos de nómina por quincena para su validación.<br>
      El sistema detectará automáticamente posibles duplicidades de personal entre entes.
    </p>
  </header>

  <div class="template-download">
    <a href="/descargar-plantilla" class="btn btn-success">
      Descargar plantilla Excel
    </a>
  </div>


  <!-- Área de carga -->
  <section class="upload-section">
    <form id="uploadForm" method="POST" enctype="multipart/form-data" action="/upload_laboral">
      <div class="upload-area" id="uploadArea" tabindex="0" aria-label="Área para subir archivos Excel">
        <div class="upload-text">
          <h3>Seleccionar archivos Excel</h3>
          <p class="hint">Arrastre archivos aquí o haga clic para seleccionar</p>
          <p class="hint">Formatos permitidos: <strong>.xlsx / .xls</strong></p>
        </div>
        <input type="file" id="fileInput" name="files" multiple accept=".xlsx,.xls" hidden>
      </div>
    </form>

    <!-- Estado del proceso -->
    <div id="uploadStatus" class="upload-status" hidden>
      <div class="spinner"></div>
      <p>Analizando archivos, espere por favor...</p>
    </div>

    <!-- Resultado -->
    <div id="uploadResult" class="upload-result" hidden>
      <h4>Resultado del análisis</h4>
      <p id="resultMessage"></p>
      <a href="/resultados" class="btn btn-primary">Ver Reporte General</a>
    </div>
  </section>

</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
