<?php
$title = 'SASP - Solventación';
ob_start();

$enteSel = $ente_sel ?? '';
$catalogoOptions = [
    '' => 'Selecciona una opción...',
    'Presentan horarios laborales autorizados y actualizados que acreditan la compatibilidad.' => 'Presentan horarios laborales autorizados y actualizados que acreditan la compatibilidad.',
    'Remiten documentación que acredita el reintegro de los recursos observados.' => 'Remiten documentación que acredita el reintegro de los recursos observados.',
    'Presentan la cancelación de cheques que no fueron cobrados por el servidor público observado.' => 'Presentan la cancelación de cheques que no fueron cobrados por el servidor público observado.',
    'Documentan que el servidor público no laboró en el periodo observado, solo tuvo pagos por liquidación o indemnización.' => 'Documentan que el servidor público no laboró en el periodo observado, solo tuvo pagos por liquidación o indemnización.',
    'Presentan permiso de horario convenido, estableciendo el horario en que se reponen las horas.' => 'Presentan permiso de horario convenido, estableciendo el horario en que se reponen las horas.',
    'Remiten oficios de licencia con goce de sueldo.' => 'Remiten oficios de licencia con goce de sueldo.',
    'Otro' => 'Otro'
];
?>

<div class="solventacion-container">
  <header class="header-flex">
    <h2>Solventación de Posible Duplicidad</h2>
    <span class="subtitle">Actualiza el estado de la revisión</span>
  </header>

  <div class="info-rfc">
    <p><strong>RFC:</strong> <?php echo htmlspecialchars((string)$rfc); ?></p>
    <p><strong>Nombre:</strong> <?php echo htmlspecialchars((string)($info['nombre'] ?? 'Sin nombre')); ?></p>
    <?php if ($enteSel): ?>
    <p><strong>Ente:</strong> <?php echo htmlspecialchars($enteSel); ?></p>
    <?php endif; ?>
  </div>

  <form id="solventacionForm"
        method="post"
        action="/solventacion/<?php echo urlencode((string)$rfc); ?>"
        data-rfc="<?php echo htmlspecialchars((string)$rfc); ?>">

    <!-- Captura el ente pasado en la URL -->
    <input type="hidden" name="ente" value="<?php echo htmlspecialchars((string)$enteSel); ?>">

    <!-- Estado de valoración -->
    <div class="form-group">
      <label for="estado">Estado de valoración:</label>
      <select id="estado" name="estado" required>
        <?php
        $estadoActual = $estado_prev ?? '';
        $isDisabled = ($estadoActual === 'Sin valoración' || $estadoActual === '') ? 'disabled' : '';
        $isSelected = ($estadoActual === 'Sin valoración' || $estadoActual === '') ? 'selected' : '';
        ?>
        <option value="Sin valoración" <?php echo $isSelected; ?> <?php echo $isDisabled; ?>>
          Sin valoración
        </option>
        <option value="Solventado" <?php echo ($estadoActual === 'Solventado') ? 'selected' : ''; ?>>Solventado</option>
        <option value="No Solventado" <?php echo ($estadoActual === 'No Solventado') ? 'selected' : ''; ?>>No Solventado</option>
      </select>
    </div>

    <!-- Catálogo de Soluciones (visible solo si el estado es Solventado o No Solventado) -->
    <div class="form-group" id="catalogoContainer" style="display: none;">
      <label for="catalogo">Motivo de la Solventación:</label>
      <select id="catalogo" name="catalogo" required>
        <?php foreach ($catalogoOptions as $val => $label): ?>
          <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ((string)($catalogo_prev ?? '') === $val) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Campo Especifique Solución (solo si el catálogo = Otro) -->
    <div class="form-group" id="otroContainer" style="display: none;">
      <label for="otro_texto">Especifique solución:</label>
      <textarea id="otro_texto" name="otro_texto" rows="3"
        placeholder="Describa la solución aplicada..."><?php echo htmlspecialchars((string)($otro_texto_prev ?? '')); ?></textarea>
    </div>

    <!-- Botones -->
    <div class="btn-area">
      <button type="submit" class="btn btn-primary">💾 Guardar cambios</button>
      <a href="/resultados/<?php echo urlencode((string)$rfc); ?>" class="btn btn-secondary">← Regresar</a>
    </div>

    <p id="confirmacion" class="confirmacion"></p>
  </form>
</div>

<!-- Lógica dinámica -->
<script>
document.addEventListener("DOMContentLoaded", function() {
  const estadoSelect = document.getElementById("estado");
  const catalogoContainer = document.getElementById("catalogoContainer");
  const catalogoSelect = document.getElementById("catalogo");
  const otroContainer = document.getElementById("otroContainer");

  function actualizarVisibilidad() {
    const estado = estadoSelect.value;
    if (estado === "Solventado" || estado === "No Solventado") {
      catalogoContainer.style.display = "block";
    } else {
      catalogoContainer.style.display = "none";
      otroContainer.style.display = "none";
      catalogoSelect.value = "";
    }
  }

  function verificarOtro() {
    otroContainer.style.display = catalogoSelect.value === "Otro" ? "block" : "none";
  }

  estadoSelect.addEventListener("change", actualizarVisibilidad);
  catalogoSelect.addEventListener("change", verificarOtro);

  // Inicializar visibilidad correcta al cargar la página
  actualizarVisibilidad();
  verificarOtro();
});
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>
