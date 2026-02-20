<?php
$title = 'SASP - Catálogos';
ob_start();
?>

<div class="catalogos">
  <header class="header-flex">
    <h2>Catálogos Institucionales</h2>
    <span class="subtitle">Consulta de Entes Estatales y Municipales Registrados</span>
  </header>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" data-tab="entes">Entes Estatales</button>
    <button class="tab" data-tab="municipios">Municipios</button>
  </div>

  <!-- Tab Entes -->
  <div class="tab-content active" id="tab-entes">
    <?php if (!empty($entes)): ?>
      <table class="tabla-resultados">
        <thead>
          <tr>
            <th>NUM</th>
            <th>Nombre</th>
            <th>Siglas</th>
            <th>Clasificación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entes as $ente): ?>
            <?php
              $nombre = (string)($ente['nombre'] ?? '-');
              $siglas = (string)($ente['siglas'] ?? '-');
              $clasificacion = (string)($ente['clasificacion'] ?? '-');
              $nombreMayus = function_exists('mb_strtoupper') ? mb_strtoupper($nombre, 'UTF-8') : strtoupper($nombre);
              $siglasMayus = function_exists('mb_strtoupper') ? mb_strtoupper($siglas, 'UTF-8') : strtoupper($siglas);
              $clasificacionMayus = function_exists('mb_strtoupper') ? mb_strtoupper($clasificacion, 'UTF-8') : strtoupper($clasificacion);
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($ente['num'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($nombreMayus); ?></td>
              <td><?php echo htmlspecialchars($siglasMayus); ?></td>
              <td><?php echo htmlspecialchars($clasificacionMayus); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="msg-vacio">No hay registros en el catálogo de entes.</p>
    <?php endif; ?>
  </div>

  <!-- Tab Municipios -->
  <div class="tab-content" id="tab-municipios">
    <?php if (!empty($municipios)): ?>
      <table class="tabla-resultados">
        <thead>
          <tr>
            <th>NUM</th>
            <th>Nombre</th>
            <th>Siglas</th>
            <th>Clasificación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($municipios as $mun): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($mun['num'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)$mun['nombre']); ?></td>
              <td><?php echo htmlspecialchars((string)($mun['siglas'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars((string)($mun['clasificacion'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="msg-vacio">No hay registros en el catálogo de municipios.</p>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
