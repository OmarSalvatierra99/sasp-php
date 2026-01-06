<?php
$title = 'SASP - Solventación';
ob_start();

$enteSel = $ente_sel ?? '';
$catalogoOptions = [
    '' => 'Seleccione una opción',
    'Compatibilidad acreditada' => 'Compatibilidad acreditada',
    'Convenio laboral' => 'Convenio laboral',
    'Otro' => 'Otro'
];
?>

<div class="solventacion-container">
  <header class="page-header">
    <h2>Valoración de <?php echo htmlspecialchars((string)$rfc); ?></h2>
    <p class="subtitle">Actualice el estado y comentarios para el ente seleccionado.</p>
  </header>

  <section class="detail-section">
    <p><strong>Nombre:</strong> <?php echo htmlspecialchars((string)($info['nombre'] ?? '')); ?></p>
    <p><strong>Entes involucrados:</strong> <?php echo htmlspecialchars(implode(', ', $info['entes'] ?? [])); ?></p>
  </section>

  <form id="solventacionForm" data-rfc="<?php echo htmlspecialchars((string)$rfc); ?>" method="post" action="/solventacion/<?php echo urlencode((string)$rfc); ?>">
    <input type="hidden" name="ente" value="<?php echo htmlspecialchars((string)$enteSel); ?>">

    <div class="form-grid">
      <label for="estado">Estado</label>
      <select name="estado" id="estado" required>
        <?php
        $estados = ['Sin valoración', 'Solventado', 'No Solventado'];
        foreach ($estados as $estado):
          $selected = ((string)($estado_prev ?? '') === $estado) ? 'selected' : '';
        ?>
          <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo $selected; ?>>
            <?php echo htmlspecialchars($estado); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="catalogo">Catálogo de soluciones</label>
      <select name="catalogo" id="catalogo">
        <?php foreach ($catalogoOptions as $val => $label): ?>
          <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ((string)($catalogo_prev ?? '') === $val) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($label); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="otro_texto">Otro (especifique)</label>
      <input type="text" name="otro_texto" id="otro_texto" value="<?php echo htmlspecialchars((string)($otro_texto_prev ?? '')); ?>" placeholder="Detalle adicional">

      <label for="valoracion">Valoración / Comentarios</label>
      <textarea name="valoracion" id="valoracion" rows="4" placeholder="Describa la justificación o hallazgo"><?php echo htmlspecialchars((string)($valoracion_prev ?? '')); ?></textarea>
    </div>

    <div class="btn-area" style="margin-top: 1rem;">
      <button type="submit" class="btn btn-primary">Guardar</button>
      <a href="/resultados/<?php echo urlencode((string)$rfc); ?>" class="btn btn-secondary">Cancelar</a>
    </div>

    <div id="confirmacion" class="alert-success" hidden></div>
  </form>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
