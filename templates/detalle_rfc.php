<?php
$title = 'SASP - Detalle del RFC';
ob_start();
?>

<div class="detalle-container">
  <div class="detalle-header">
    <p><strong>RFC:</strong> <?php echo htmlspecialchars((string)$rfc); ?></p>
    <p><strong>Nombre:</strong> <?php echo htmlspecialchars((string)($info['nombre'] ?? 'Sin nombre')); ?></p>
  </div>

  <?php if (!empty($info['registros'])): ?>
    <table class="tabla-detalle">
      <thead>
        <tr>
          <th>Ente Incompatibilidad</th>
          <th>Puesto</th>
          <th>Total Percepciones</th>
          <th>Quincenas</th>
          <th>Fecha Alta</th>
          <th>Fecha Baja</th>
          <th>Estado</th>
          <th>Valoración</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($info['registros'] as $reg): ?>
          <?php
            $qnas = array_keys($reg['qnas'] ?? []);
            $qnasLabel = empty($qnas) ? '-' : implode(', ', $qnas);
            $estadoEnte = $reg['estado_ente'] ?? ($info['estado'] ?? 'Sin valoración');
          ?>
          <tr>
            <td><?php echo htmlspecialchars($ente_display($reg['ente'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars((string)($reg['puesto'] ?? 'Sin puesto')); ?></td>
            <td>
              <?php if (!empty($reg['monto'])): ?>
                MXN <?php echo number_format((float)$reg['monto'], 2); ?>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($qnasLabel); ?></td>
            <td><?php echo htmlspecialchars((string)($reg['fecha_ingreso'] ?? '-')); ?></td>
            <td><?php echo htmlspecialchars((string)($reg['fecha_egreso'] ?? '-')); ?></td>
            <td><?php echo htmlspecialchars((string)$estadoEnte); ?></td>
            <td class="accion">
              <?php if (!empty($es_luis)): ?>
                <a class="btn btn-primary" href="/solventacion/<?php echo urlencode((string)$rfc); ?>?ente=<?php echo urlencode((string)($reg['ente'] ?? '')); ?>">
                  Editar
                </a>
              <?php else: ?>
                <span class="badge badge-neutral">Solo lectura</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No se encontraron registros asociados.</p>
  <?php endif; ?>

  <div class="btn-area">
    <a href="/resultados" class="btn btn-secondary">← Regresar</a>
  </div>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
