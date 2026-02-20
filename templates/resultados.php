<?php
$title = 'SASP - Resultados';
ob_start();
?>

<div class="report-container">
  <?php
    $preCatalogoOpciones = [
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
  <header class="header-flex">
    <div>
      <h2>Resultados Agrupados por Ente</h2>
      <span class="subtitle">
        <?php if (!empty($mostrar_duplicados)): ?>
          Cruces y duplicidades detectadas por ente
        <?php else: ?>
          Vista previa: solo trabajadores procesados (duplicados ocultos hasta validación)
        <?php endif; ?>
      </span>
    </div>
    <?php if (!empty($mostrar_duplicados)): ?>
      <div class="export-actions" style="display: flex; gap: 0.5rem; align-items: center;">
        <a class="btn btn-primary" href="/exportar_general">Exportar General</a>
        <?php if (!empty($filtro_ente)): ?>
          <a class="btn btn-secondary" href="/exportar_por_ente?ente=<?php echo urlencode($filtro_ente); ?>">Exportar <?php echo htmlspecialchars($filtro_ente); ?></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </header>

  <?php if (!empty($validacion_error)): ?>
    <div class="badge badge-danger" style="display: block; padding: var(--space-6); margin-bottom: var(--space-6);">
      <?php echo htmlspecialchars((string)($validacion_error_msg ?? 'No fue posible validar los datos.')); ?>
    </div>
  <?php endif; ?>

  <section style="margin-bottom:var(--space-6);">
    <h3 style="margin:0 0 var(--space-4);">Resumen de Auditoría</h3>
    <table class="tabla-resultados">
      <thead>
        <tr>
          <th>Métrica</th>
          <th>Valor</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($resumen_auditoria ?? []) as $fila): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars((string)($fila['m'] ?? '')); ?></strong></td>
            <td><?php echo htmlspecialchars((string)($fila['v'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <?php if (!empty($es_luis)): ?>
    <div class="export-bar" style="margin-bottom: 1rem;">
      <?php if (!empty($resultados_validados)): ?>
        <span class="badge badge-success">✓ Datos validados y visibles para todos</span>
        <form method="post" action="/cancelar_validacion" class="form-inline" onsubmit="return confirm('¿Cancelar validación y regresar a borrador?');">
          <button type="submit" class="btn btn-secondary">Cancelar validación</button>
        </form>
      <?php else: ?>
        <form method="post" action="/validar_datos" class="form-inline">
          <button type="submit" class="btn btn-primary">Validar datos</button>
          <span class="subtitle">Al validar, los demás usuarios verán duplicados.</span>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="doble-tab-bar" style="margin-bottom: var(--space-6);">
    <a class="tab-link <?php echo (($ambito_sel ?? 'estatales') === 'estatales') ? 'active' : ''; ?>" href="/resultados?ambito=estatales">
      Estatales
    </a>
    <a class="tab-link <?php echo (($ambito_sel ?? 'estatales') === 'municipios') ? 'active' : ''; ?>" href="/resultados?ambito=municipios">
      Municipios
    </a>
  </div>

  <div class="export-bar">
    <form id="filterForm" method="get" class="form-inline" action="/resultados">
      <input type="hidden" name="ambito" value="<?php echo htmlspecialchars((string)($ambito_sel ?? 'estatales')); ?>">
      <label for="selectEnte">
        Filtrar por <?php echo (($ambito_sel ?? 'estatales') === 'municipios') ? 'municipio' : 'ente estatal'; ?>:
      </label>
      <select name="ente" id="selectEnte">
        <option value="">
          <?php echo (($ambito_sel ?? 'estatales') === 'municipios') ? 'Todos los municipios' : 'Todos los entes estatales'; ?>
        </option>
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
      $listaTrabajadores = $trabajadores_por_ente[$enteNombre] ?? [];
      $tieneDuplicados = !empty($mostrar_duplicados) && count($lista) > 0;
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
                <?php if (!empty($mostrar_duplicados)): ?>
                  ✓ Sin duplicados
                <?php else: ?>
                  ✓ Solo trabajadores procesados
                <?php endif; ?>
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
                  <?php if (!empty($es_luis)): ?>
                    <th>Pre-validación</th>
                  <?php endif; ?>
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
                    <?php if (!empty($es_luis)): ?>
                      <?php $preEstado = (string)($r['pre_estado'] ?? 'Sin valoración'); ?>
                      <td>
                        <form class="prevalidacion-form" data-rfc="<?php echo htmlspecialchars((string)$r['rfc']); ?>" data-ente="<?php echo htmlspecialchars((string)($r['ente_origen'] ?? $enteNombre)); ?>">
                          <label style="display:block; font-size:.85rem;">Estado</label>
                          <select name="pre_estado" class="pre-estado" style="width:100%;">
                            <option value="Sin valoración" <?php echo ($preEstado === 'Sin valoración') ? 'selected' : ''; ?>>Sin valoración</option>
                            <option value="Solventado" <?php echo ($preEstado === 'Solventado') ? 'selected' : ''; ?>>Solventado</option>
                          </select>

                          <div class="pre-catalogo-wrap" style="<?php echo ($preEstado === 'Solventado') ? '' : 'display:none;'; ?>">
                            <label style="display:block; font-size:.85rem; margin-top:.35rem;">Solventación</label>
                            <select name="pre_catalogo" class="pre-catalogo" style="width:100%;">
                              <?php foreach ($preCatalogoOpciones as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ((string)($r['pre_catalogo'] ?? '') === $val) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($label); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="pre-otro-wrap" style="<?php echo ((string)($r['pre_catalogo'] ?? '') === 'Otro') ? '' : 'display:none;'; ?>">
                            <label style="display:block; font-size:.85rem; margin-top:.35rem;">Otro</label>
                            <textarea name="pre_otro_texto" class="pre-otro-texto" rows="2" style="width:100%;"><?php echo htmlspecialchars((string)($r['pre_otro_texto'] ?? '')); ?></textarea>
                          </div>

                          <button type="submit" class="btn btn-secondary" style="margin-top:.35rem;">Guardar</button>
                          <small class="prevalidacion-msg" style="display:block;"></small>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <div style="margin: var(--space-5) 0 var(--space-3); font-weight: 600;">
            Trabajadores cargados en este ente
          </div>
          <?php if (!empty($listaTrabajadores)): ?>
            <table class="tabla-resultados">
              <thead>
                <tr>
                  <th>RFC</th>
                  <th>Nombre</th>
                  <th>Puesto</th>
                  <th>Total Percepciones</th>
                  <th>Quincenas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($listaTrabajadores as $trab): ?>
                  <?php
                    $qnasTrab = array_keys((array)($trab['qnas'] ?? []));
                    $qnasLabel = empty($qnasTrab) ? '-' : implode(', ', $qnasTrab);
                  ?>
                  <tr>
                    <td>
                      <?php if (!empty($trab['rfc'])): ?>
                        <a class="link-rfc" href="/resultados/<?php echo urlencode((string)$trab['rfc']); ?>">
                          <?php echo htmlspecialchars((string)$trab['rfc']); ?>
                        </a>
                      <?php else: ?>
                        Sin RFC
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)($trab['nombre'] ?? 'Sin nombre')); ?></td>
                    <td><?php echo htmlspecialchars((string)($trab['puesto'] ?? 'Sin puesto')); ?></td>
                    <td>
                      <?php if (!empty($trab['monto'])): ?>
                        MXN <?php echo number_format((float)$trab['monto'], 2); ?>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($qnasLabel); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="badge badge-neutral" style="display:block; padding:var(--space-6); margin:var(--space-4);">
              Sin trabajadores cargados para este ente.
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
