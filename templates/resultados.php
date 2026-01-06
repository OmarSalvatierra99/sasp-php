<?php
$title = 'SASP - Resultados';
ob_start();
?>

<div class="report-container">
  <header class="header-flex">
    <div>
      <h2>Resultados Agrupados por Ente</h2>
      <span class="subtitle">Cruces y duplicidades detectadas por ente</span>
    </div>
    <div class="export-actions" style="display: flex; gap: 0.5rem; align-items: center;">
      <a class="btn btn-primary" href="/exportar_general">Exportar General</a>
      <?php if (!empty($filtro_ente)): ?>
        <a class="btn btn-secondary" href="/exportar_por_ente?ente=<?php echo urlencode($filtro_ente); ?>">Exportar <?php echo htmlspecialchars($filtro_ente); ?></a>
      <?php endif; ?>
    </div>
  </header>

  <div class="export-bar">
    <form id="filterForm" method="get" class="form-inline" action="/resultados">
      <label for="selectEnte">Filtrar por ente:</label>
      <select name="ente" id="selectEnte">
        <option value="">Todos los entes</option>
        <?php foreach ($entes_info as $enteNombre => $info): ?>
          <option value="<?php echo htmlspecialchars($enteNombre); ?>" <?php echo ($filtro_ente ?? '') === $enteNombre ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($enteNombre); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-secondary">Aplicar</button>
    </form>
    <div class="search-area">
      <input type="text" id="searchInput" placeholder="Buscar por RFC o nombre..." />
    </div>
  </div>

  <?php if (!empty($entes_info)): ?>
    <?php foreach ($entes_info as $enteNombre => $info): ?>
      <?php
      if (!empty($filtro_ente) && $filtro_ente !== $enteNombre) {
          continue;
      }
      $lista = $resultados[$enteNombre] ?? [];
      $tieneDuplicados = count($lista) > 0;
      ?>
      <section class="ente-bloque acordeon" data-tipo="<?php echo htmlspecialchars((string)$info['tipo']); ?>">
        <div class="acordeon-header">
          <div class="ente-nombre">
            <span class="acordeon-icono">⌄</span>
            <strong><?php echo htmlspecialchars((string)$info['num']); ?>. <?php echo htmlspecialchars($enteNombre); ?></strong>
          </div>
          <div style="display: flex; gap: var(--space-4); align-items: center;">
            <span class="badge badge-info">
              <?php echo (int)($info['total'] ?? 0); ?> trabajador<?php echo ((int)($info['total'] ?? 0) !== 1) ? 'es' : ''; ?>
            </span>
            <?php if ($tieneDuplicados): ?>
              <span class="badge badge-warning">
                <?php echo count($lista); ?> duplicado<?php echo count($lista) !== 1 ? 's' : ''; ?>
              </span>
            <?php else: ?>
              <span class="badge badge-success">
                ✓ Sin duplicados
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="acordeon-contenido" style="display: none;">
          <?php if ($tieneDuplicados): ?>
            <table class="tabla-resultados">
              <thead>
                <tr>
                  <th>RFC</th>
                  <th>Nombre</th>
                  <th>Puesto</th>
                  <th>Entes Duplicados</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lista as $r): ?>
                  <tr class="fila-result">
                    <td>
                      <?php if (!empty($r['rfc'])): ?>
                        <a class="link-rfc" href="/resultados/<?php echo urlencode((string)$r['rfc']); ?>"><?php echo htmlspecialchars((string)$r['rfc']); ?></a>
                      <?php else: ?>
                        Sin RFC
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)($r['nombre'] ?? 'Sin nombre')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['puesto'] ?? 'Sin puesto')); ?></td>
                    <td>
                      <?php if (!empty($r['entes'])): ?>
                        <?php foreach ($r['entes'] as $enteDup): ?>
                          <?php
                            $estadoTag = $r['estado_entes'][$enteDup] ?? $r['estado'] ?? 'Sin valoración';
                            $badgeClass = ($estadoTag === 'Solventado') ? 'badge-success' : (($estadoTag === 'No Solventado') ? 'badge-danger' : 'badge-neutral');
                            $icono = ($estadoTag === 'Solventado') ? '✓' : (($estadoTag === 'No Solventado') ? '✗' : '○');
                          ?>
                          <span class="badge <?php echo $badgeClass; ?>"><?php echo $icono; ?> <?php echo htmlspecialchars((string)$enteDup); ?></span>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span class="badge badge-neutral">○ Sin entes duplicados</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="badge badge-success" style="display: block; padding: var(--space-6); margin: var(--space-4);">
              <strong>✓ Este ente no presenta duplicidades</strong>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="msg-vacio">No hay datos cargados en el sistema.</p>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
