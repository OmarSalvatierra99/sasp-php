<?php
$title = 'SASP - Catálogos';
ob_start();
?>

<div class="catalogos">
  <header class="page-header">
    <h2>Catálogos</h2>
    <p class="subtitle">Entes estatales y municipios registrados en el sistema.</p>
  </header>

  <section class="catalogo-section">
    <h3>Entes Estatales</h3>
    <div class="tabla-wrapper">
      <table class="tabla-resultados">
        <thead>
          <tr>
            <th>NUM</th>
            <th>Clave</th>
            <th>Siglas</th>
            <th>Nombre</th>
            <th>Clasificación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entes as $ente): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$ente['num']); ?></td>
              <td><?php echo htmlspecialchars((string)$ente['clave']); ?></td>
              <td><?php echo htmlspecialchars((string)($ente['siglas'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)$ente['nombre']); ?></td>
              <td><?php echo htmlspecialchars((string)($ente['clasificacion'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="catalogo-section">
    <h3>Municipios</h3>
    <div class="tabla-wrapper">
      <table class="tabla-resultados">
        <thead>
          <tr>
            <th>NUM</th>
            <th>Clave</th>
            <th>Siglas</th>
            <th>Nombre</th>
            <th>Clasificación</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($municipios as $mun): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$mun['num']); ?></td>
              <td><?php echo htmlspecialchars((string)$mun['clave']); ?></td>
              <td><?php echo htmlspecialchars((string)($mun['siglas'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)$mun['nombre']); ?></td>
              <td><?php echo htmlspecialchars((string)($mun['clasificacion'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
