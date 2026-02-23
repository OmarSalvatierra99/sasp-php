<?php

declare(strict_types=1);

namespace Sasp\Web;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Sasp\Core\DataProcessor;
use Sasp\Core\DatabaseManager;

/**
 * Application — SASP / SCIL 2025
 * Front controller and HTTP dispatcher
 */
class Application
{
    private DatabaseManager $dbManager;
    private DataProcessor $dataProcessor;
    private string $templatesPath;
    private string $assetsPath;
    private string $downloadsPath;

    public function __construct(?DatabaseManager $dbManager = null, ?DataProcessor $dataProcessor = null)
    {
        $projectRoot = dirname(__DIR__, 2);
        $defaultDbPath = $projectRoot . '/scil.db';
        $configuredPath = (string)($_SERVER['SCIL_DB'] ?? getenv('SCIL_DB') ?: $defaultDbPath);
        $dbPath = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? $configuredPath
            : $projectRoot . '/' . ltrim($configuredPath, '/');
        $this->dbManager = $dbManager ?? new DatabaseManager($dbPath);
        $this->dataProcessor = $dataProcessor ?? new DataProcessor($this->dbManager, $dbPath);
        $this->templatesPath = __DIR__ . '/../../templates';
        $this->assetsPath = dirname(__DIR__, 2);
        $this->downloadsPath = $this->assetsPath . '/uploads';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        $request = Request::fromGlobals($_SESSION ?? []);

        if (!$this->verificarAutenticacion($request)) {
            return;
        }

        $this->dispatch($request);
    }

    private function verificarAutenticacion(Request $request): bool
    {
        $path = $request->path();

        $libres = ['/', '/login'];
        foreach ($libres as $libre) {
            if ($path === $libre) {
                return true;
            }
        }

        if ($this->isAssetPath($path)) {
            return true;
        }

        if (!isset($_SESSION['autenticado']) || !$_SESSION['autenticado']) {
            if ($request->isAjax()) {
                $this->jsonResponse(['error' => 'Sesión expirada o no autorizada'], 403);
            } else {
                $this->redirect('/');
            }
            return false;
        }

        return true;
    }

    private function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        if ($path === '/' || $path === '/login') {
            $this->login($request);
            return;
        }

        if ($path === '/logout') {
            $this->logout();
            return;
        }

        if ($path === '/dashboard') {
            $this->dashboard();
            return;
        }

        if ($path === '/upload_laboral' && $method === 'POST') {
            $this->uploadLaboral($request);
            return;
        }

        if ($path === '/resultados') {
            $this->reportePorEnte($request);
            return;
        }

        if ($path === '/prevalidar_duplicado' && $method === 'POST') {
            $this->prevalidarDuplicado($request);
            return;
        }

        if ($path === '/validar_datos' && $method === 'POST') {
            $this->validarDatos($request);
            return;
        }

        if ($path === '/cancelar_validacion' && $method === 'POST') {
            $this->cancelarValidacion($request);
            return;
        }

        if (preg_match('#^/resultados/([A-Z0-9]+)$#', $path, $matches)) {
            $this->resultadosPorRfc($matches[1]);
            return;
        }

        if (preg_match('#^/solventacion/([A-Z0-9]+)$#', $path, $matches)) {
            $this->solventacionDetalle($request, $matches[1]);
            return;
        }

        if ($path === '/actualizar_estado' && $method === 'POST') {
            $this->actualizarEstado($request);
            return;
        }

        if ($path === '/exportar_por_ente') {
            $this->exportarPorEnte($request);
            return;
        }

        if ($path === '/exportar_general') {
            $this->exportarExcelGeneral();
            return;
        }

        if ($path === '/exportar_solventados') {
            $this->exportarSolventados($request);
            return;
        }

        if ($path === '/catalogos') {
            $this->catalogosHome();
            return;
        }

        if ($path === '/descargar-plantilla') {
            $this->descargarPlantilla();
            return;
        }

        if ($this->isAssetPath($path)) {
            $this->serveStatic($path);
            return;
        }

        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    // ===================================================================
    // ROUTES
    // ===================================================================

    private function login(Request $request): void
    {
        if ($request->method() === 'POST') {
            $usuario = trim((string)$request->input('usuario', ''));
            $clave = trim((string)$request->input('clave', ''));
            $user = $this->dbManager->getUsuario($usuario, $clave);

            if (!$user) {
                $this->render('login.php', ['error' => 'Credenciales inválidas']);
                return;
            }

            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['autenticado'] = true;
            $_SESSION['entes'] = $user['entes'];

            $this->redirect('/dashboard');
            return;
        }

        if (!empty($_SESSION['autenticado'])) {
            $this->redirect('/dashboard');
            return;
        }

        $this->render('login.php');
    }

    private function logout(): void
    {
        session_destroy();
        $this->redirect('/');
    }

    private function dashboard(): void
    {
        $this->render('dashboard.php', [
            'nombre' => $_SESSION['nombre'] ?? ''
        ]);
    }

    private function uploadLaboral(Request $request): void
    {
        if (empty($_FILES['files'])) {
            $this->jsonResponse(['error' => 'No se enviaron archivos'], 400);
            return;
        }

        try {
            $files = $this->normalizeFilesArray($_FILES['files']);
            $nombres = array_map(fn($f) => $f['name'], $files);
            error_log(sprintf("Upload recibido: %s", implode(', ', $nombres)));

            [$registrosIndividuales, $alertas] = $this->dataProcessor->extraerRegistrosIndividuales($files);

            [$nInsertados, $nActualizados] = $this->dbManager->guardarRegistrosIndividuales($registrosIndividuales);

            $response = [
                'mensaje' => "Procesamiento completado. {$nInsertados} nuevos registros, {$nActualizados} actualizados.",
                'total_procesados' => count($registrosIndividuales),
                'insertados' => $nInsertados,
                'actualizados' => $nActualizados,
                'alertas' => $alertas
            ];

            $this->jsonResponse($response);
        } catch (\Throwable $e) {
            error_log("Error en upload_laboral: {$e->getMessage()}");
            $this->jsonResponse(['error' => "Error al procesar archivos: {$e->getMessage()}"], 500);
        }
    }

    private function reportePorEnte(Request $request): void
    {
        $filtroEnte = trim((string)$request->query('ente', ''));
        $ambitoSel = strtolower(trim((string)$request->query('ambito', 'estatales')));
        if (!in_array($ambitoSel, ['estatales', 'municipios'], true)) {
            $ambitoSel = 'estatales';
        }
        $validacionError = (string)$request->query('validacion_error', '') === '1';
        $validacionErrorMsg = '';
        if ($validacionError) {
            $validacionErrorMsg = (string)($_SESSION['validacion_error_msg'] ?? 'No fue posible validar los datos.');
            unset($_SESSION['validacion_error_msg']);
        }
        $entesUsuario = $_SESSION['entes'] ?? [];
        $esLuis = $this->esUsuarioLuis();
        $resultadosValidados = $this->dbManager->resultadosValidados();
        $mostrarDuplicados = $esLuis || $resultadosValidados;
        $modoPermiso = $esLuis ? "ALL" : $this->allowedAll($entesUsuario);
        $resumenPrevalidacion = [
            'rfc_solventados' => 0,
            'registros_solventados' => 0
        ];
        $detalleSolventados = [];

        $resultadosBase = $this->dbManager->obtenerCrucesReales();
        $resultados = $esLuis
            ? $this->filtrarDuplicadosReales($resultadosBase)
            : $this->filtrarDuplicadosConVisibilidad($resultadosBase);
        $trabajadoresPorEnte = $this->dbManager->contarTrabajadoresPorEnte();
        $trabajadoresDetallados = $this->dbManager->obtenerTrabajadoresPorEnte();
        $catalogo = array_merge($this->dbManager->listarEntes(), $this->dbManager->listarMunicipios());

        $agrupado = [];
        $entesInfo = [];

        // Pre-cargar info de catálogo
        foreach ($catalogo as $ente) {
            $display = $ente['siglas'] ?: $ente['nombre'];
            $clave = $ente['clave'];
            $tipo = strtoupper($ente['ambito'] ?? 'ENTE');

            if (!$this->puedeVerEnte($clave, $entesUsuario, $modoPermiso)) {
                continue;
            }
            if (!$this->coincideAmbitoSeleccionado($ambitoSel, $tipo)) {
                continue;
            }

            $agrupado[$display] = [];
            $entesInfo[$display] = [
                'num' => $ente['num'],
                'siglas' => $ente['siglas'],
                'nombre_completo' => $ente['nombre'],
                'total' => $trabajadoresPorEnte[$clave] ?? 0,
                'duplicados' => 0,
                'tipo' => $tipo
            ];
        }

        if ($mostrarDuplicados) {
            $solventadosData = $this->construirDetalleSolventados(
                $resultados,
                $filtroEnte,
                $ambitoSel,
                $entesUsuario,
                $modoPermiso
            );
            $resumenPrevalidacion = $solventadosData['resumen'];
            $detalleSolventados = $solventadosData['detalle'];

            $rfcsResultados = array_values(array_unique(array_map(
                static fn(array $row): string => strtoupper(trim((string)($row['rfc'] ?? ''))),
                $resultados
            )));
            $solventacionesPorRfc = $this->dbManager->getSolventacionesPorRfcs($rfcsResultados);
            $prevalidacionesPorRfc = $this->dbManager->getPrevalidacionesPorRfcs($rfcsResultados);

            // Agrupar duplicados por ente
            foreach ($resultados as $r) {
                $rfcActual = strtoupper(trim((string)($r['rfc'] ?? '')));
                $mapaSolvs = $solventacionesPorRfc[$rfcActual] ?? [];
                $mapaPre = $prevalidacionesPorRfc[$rfcActual] ?? [];

                foreach ($r['entes'] as $enteClave) {
                    if (!$esLuis && $this->esPrevalidadoOculto($mapaPre, $enteClave)) {
                        continue;
                    }

                    if (!$this->puedeVerEnte($enteClave, $entesUsuario, $modoPermiso)) {
                        continue;
                    }

                    $display = $this->enteDisplay($enteClave);
                    if ($filtroEnte && $display !== $filtroEnte) {
                        continue;
                    }
                    if (!isset($entesInfo[$display])) {
                        continue;
                    }

                    $otrosEntes = array_filter(
                        array_map(fn($e) => $this->enteSigla($e), $r['entes']),
                        fn($e) => $this->sanitizeText($e) !== $this->sanitizeText($enteClave)
                    );

                    $estadoDefault = $r['estado'] ?? 'Sin valoración';
                    $estadoEntes = [];
                    foreach ($r['entes'] as $en) {
                        $claveNorm = $this->dbManager->normalizarEnteClave($en);
                        $estadoEntes[$this->enteSigla($en)] = $mapaSolvs[$claveNorm]['estado'] ?? $estadoDefault;
                    }

                    $claveEnteActual = $this->dbManager->normalizarEnteClave($enteClave) ?? $enteClave;
                    $pre = $mapaPre[$claveEnteActual] ?? [];
                    $preEstado = (string)($pre['estado'] ?? 'Sin valoración');

                    $agrupado[$display][] = [
                        'rfc' => $r['rfc'],
                        'nombre' => $r['nombre'],
                        'puesto' => $this->primerPuesto($r['registros'] ?? []),
                        'entes' => array_values(array_unique($otrosEntes)),
                        'estado' => $estadoDefault,
                        'estado_entes' => $estadoEntes,
                        'ente_origen' => $enteClave,
                        'pre_estado' => $preEstado,
                        'pre_valoracion' => $pre['comentario'] ?? '',
                        'pre_catalogo' => $pre['catalogo'] ?? '',
                        'pre_otro_texto' => $pre['otro_texto'] ?? ''
                    ];
                }
            }
        }

        // Calcular duplicados por ente y ordenar
        foreach ($entesInfo as $display => &$info) {
            $info['duplicados'] = count($agrupado[$display] ?? []);
        }
        unset($info);

        uasort($entesInfo, fn($a, $b) => $this->ordenPorNum($a, $b));

        // Normalizar arrays para template
        $agrupadoFinal = [];
        foreach ($agrupado as $k => $v) {
            if ($filtroEnte && $k !== $filtroEnte) {
                continue;
            }
            $agrupadoFinal[$k] = array_values($v);
        }

        $trabajadoresPorEnteFinal = [];
        $rfcProcesados = [];
        $registrosCargados = 0;
        foreach ($trabajadoresDetallados as $enteClave => $trabajadores) {
            if (!$this->puedeVerEnte((string)$enteClave, $entesUsuario, $modoPermiso)) {
                continue;
            }
            if (!$this->coincideAmbitoSeleccionado($ambitoSel, $this->tipoEnte((string)$enteClave))) {
                continue;
            }

            $display = $this->enteDisplay((string)$enteClave);
            if ($filtroEnte && $display !== $filtroEnte) {
                continue;
            }

            foreach ($trabajadores as $trab) {
                $trabajadoresPorEnteFinal[$display][] = $trab;
                $rfc = strtoupper(trim((string)($trab['rfc'] ?? '')));
                if ($rfc !== '') {
                    $rfcProcesados[$rfc] = true;
                }
                $registrosCargados++;
            }
        }

        $rfcDuplicados = [];
        foreach ($resultados as $r) {
            $entesVisibles = [];
            foreach (($r['entes'] ?? []) as $enteCruce) {
                if ($this->puedeVerEnte((string)$enteCruce, $entesUsuario, $modoPermiso)) {
                    if (!$this->coincideAmbitoSeleccionado($ambitoSel, $this->tipoEnte((string)$enteCruce))) {
                        continue;
                    }
                    $entesVisibles[] = (string)$enteCruce;
                }
            }
            $entesVisibles = array_values(array_unique($entesVisibles));
            if (count($entesVisibles) < 2) {
                continue;
            }

            if ($filtroEnte !== '') {
                $incluyeFiltro = false;
                foreach ($entesVisibles as $enteCruce) {
                    if ($this->enteDisplay($enteCruce) === $filtroEnte) {
                        $incluyeFiltro = true;
                        break;
                    }
                }
                if (!$incluyeFiltro) {
                    continue;
                }
            }

            $rfc = strtoupper(trim((string)($r['rfc'] ?? '')));
            if ($rfc !== '') {
                $rfcDuplicados[$rfc] = true;
            }
        }

        $entesVisibles = 0;
        foreach ($entesInfo as $nombreEnte => $_info) {
            if ($filtroEnte && $nombreEnte !== $filtroEnte) {
                continue;
            }
            $entesVisibles++;
        }

        $trabajadoresProcesados = count($rfcProcesados);
        $duplicadosDetectados = count($rfcDuplicados);
        $indiceDuplicidad = $trabajadoresProcesados > 0
            ? round(($duplicadosDetectados / $trabajadoresProcesados) * 100, 2)
            : 0.0;
        $promedioRegistrosPorEnte = $entesVisibles > 0
            ? round($registrosCargados / $entesVisibles, 2)
            : 0.0;

        $entesConDuplicidad = 0;
        foreach ($entesInfo as $nombreEnte => $infoEnte) {
            if ($filtroEnte && $nombreEnte !== $filtroEnte) {
                continue;
            }
            if ((int)($infoEnte['duplicados'] ?? 0) > 0) {
                $entesConDuplicidad++;
            }
        }

        $resumenAuditoria = [
            ['m' => 'Entes analizados', 'v' => (string)$entesVisibles],
            ['m' => 'Trabajadores analizados (RFC únicos)', 'v' => number_format($trabajadoresProcesados, 0, '.', ',')],
            ['m' => 'Casos de duplicidad (RFC únicos)', 'v' => (string)$duplicadosDetectados],
            ['m' => 'Entes con duplicidad', 'v' => (string)$entesConDuplicidad],
            ['m' => 'Índice de trabajadores duplicados', 'v' => number_format($indiceDuplicidad, 2) . '%']
        ];

        $this->render('resultados.php', [
            'resultados' => $agrupadoFinal,
            'trabajadores_por_ente' => $trabajadoresPorEnteFinal,
            'entes_info' => $entesInfo,
            'filtro_ente' => $filtroEnte,
            'ambito_sel' => $ambitoSel,
            'es_luis' => $esLuis,
            'resultados_validados' => $resultadosValidados,
            'mostrar_duplicados' => $mostrarDuplicados,
            'validacion_error' => $validacionError,
            'validacion_error_msg' => $validacionErrorMsg,
            'resumen_auditoria' => $resumenAuditoria,
            'resumen_prevalidacion' => $resumenPrevalidacion,
            'detalle_solventados' => $detalleSolventados,
            'resumen' => [
                'entes_visibles' => $entesVisibles,
                'registros_cargados' => $registrosCargados,
                'trabajadores_procesados' => count($rfcProcesados),
                'duplicados_detectados' => count($rfcDuplicados)
            ]
        ]);
    }

    private function resultadosPorRfc(string $rfc): void
    {
        $esLuis = $this->esUsuarioLuis();

        if (!$esLuis && !$this->dbManager->resultadosValidados()) {
            $this->redirect('/resultados');
            return;
        }

        $info = $this->dbManager->obtenerResultadosPorRfc($rfc);

        if (!$info) {
            $this->render('empty.php', ['mensaje' => 'No hay registros del trabajador.']);
            return;
        }

        if (!$esLuis) {
            $mapaPre = $this->dbManager->getPrevalidacionesPorRfc($rfc);
            if (!empty($mapaPre)) {
                $registrosVisibles = [];
                $entesVisibles = [];

                foreach (($info['registros'] ?? []) as $reg) {
                    $enteReg = (string)($reg['ente'] ?? '');
                    if ($enteReg !== '' && $this->esPrevalidadoOculto($mapaPre, $enteReg)) {
                        continue;
                    }

                    $registrosVisibles[] = $reg;
                    if ($enteReg !== '') {
                        $entesVisibles[$enteReg] = true;
                    }
                }

                $info['registros'] = $registrosVisibles;
                $info['entes'] = array_keys($entesVisibles);
                sort($info['entes']);
            }

            if (count(($info['entes'] ?? [])) < 2) {
                $this->render('empty.php', ['mensaje' => 'Este RFC no presenta ninguna incompatibilidad']);
                return;
            }
        }

        $mapaSolvs = $this->dbManager->getSolventacionesPorRfc($rfc);
        if ($mapaSolvs && isset($info['registros'])) {
            foreach ($info['registros'] as &$reg) {
                $enteClave = $this->dbManager->normalizarEnteClave($reg['ente'] ?? '');
                if (isset($mapaSolvs[$enteClave])) {
                    $reg['estado_ente'] = $mapaSolvs[$enteClave]['estado'];
                    $reg['comentario_ente'] = $mapaSolvs[$enteClave]['comentario'] ?? '';
                }
            }
            unset($reg);
        }

        $this->render('detalle_rfc.php', [
            'rfc' => $rfc,
            'info' => $info,
            'es_luis' => $esLuis
        ]);
    }

    private function solventacionDetalle(Request $request, string $rfc): void
    {
        if (!$this->esUsuarioLuis()) {
            $this->redirect('/resultados');
            return;
        }

        $enteSel = (string)$request->query('ente', '');

        if ($request->method() === 'POST') {
            $estado = (string)$request->input('estado', '');
            $comentario = (string)$request->input('valoracion', $request->input('solventacion', ''));
            $catalogo = (string)$request->input('catalogo', '');
            $otroTexto = (string)$request->input('otro_texto', '');
            $entePost = (string)$request->input('ente', $enteSel);

            $this->dbManager->actualizarSolventacion(
                $rfc,
                $estado,
                $comentario,
                $catalogo,
                $otroTexto,
                $entePost
            );

            $this->redirect("/resultados/{$rfc}");
            return;
        }

        $info = $this->dbManager->obtenerResultadosPorRfc($rfc);
        if (!$info) {
            $this->render('empty.php', ['mensaje' => 'No hay registros para este RFC.']);
            return;
        }

        $conn = $this->dbManager->getConnection();
        $stmt = $conn->prepare("
            SELECT estado, comentario, catalogo, otro_texto
            FROM solventaciones
            WHERE rfc=? AND ente=?
        ");
        $stmt->execute([$rfc, $this->dbManager->normalizarEnteClave($enteSel) ?: "GENERAL"]);
        $row = $stmt->fetch();

        $this->render('solventacion.php', [
            'rfc' => $rfc,
            'info' => $info,
            'ente_sel' => $enteSel,
            'estado_prev' => $row['estado'] ?? ($info['estado'] ?? 'Sin valoración'),
            'valoracion_prev' => $row['comentario'] ?? ($info['solventacion'] ?? ''),
            'catalogo_prev' => $row['catalogo'] ?? '',
            'otro_texto_prev' => $row['otro_texto'] ?? ''
        ]);
    }

    private function actualizarEstado(Request $request): void
    {
        if (!$this->esUsuarioLuis()) {
            $this->jsonResponse(['error' => 'No autorizado'], 403);
            return;
        }

        $data = $request->jsonBody();
        $rfc = (string)($data['rfc'] ?? '');
        $estado = (string)($data['estado'] ?? '');
        $comentario = (string)($data['valoracion'] ?? $data['solventacion'] ?? '');
        $catalogo = (string)($data['catalogo'] ?? '');
        $otroTexto = (string)($data['otro_texto'] ?? '');
        $ente = (string)($data['ente'] ?? '');

        if (!$rfc) {
            $this->jsonResponse(['error' => 'Falta el RFC'], 400);
            return;
        }

        try {
            $filas = $this->dbManager->actualizarSolventacion(
                $rfc,
                $estado,
                $comentario,
                $catalogo,
                $otroTexto,
                $ente
            );

            $this->jsonResponse([
                'mensaje' => "Registro actualizado ({$filas} filas)",
                'estatus' => $estado
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function prevalidarDuplicado(Request $request): void
    {
        if (!$this->esUsuarioLuis()) {
            $this->jsonResponse(['error' => 'No autorizado'], 403);
            return;
        }

        $data = $request->jsonBody();
        $rfc = trim((string)($data['rfc'] ?? ''));
        $ente = trim((string)($data['ente'] ?? ''));
        $estado = trim((string)($data['estado'] ?? 'Sin valoración'));
        $comentario = trim((string)($data['valoracion'] ?? ''));
        $catalogo = trim((string)($data['catalogo'] ?? ''));
        $otroTexto = trim((string)($data['otro_texto'] ?? ''));

        if ($rfc === '' || $ente === '') {
            $this->jsonResponse(['error' => 'Faltan RFC o ente'], 400);
            return;
        }

        if (!in_array($estado, ['Sin valoración', 'Solventado'], true)) {
            $this->jsonResponse(['error' => 'Estado de pre-validación no permitido'], 400);
            return;
        }

        if ($estado === 'Solventado' && $catalogo === '') {
            $this->jsonResponse(['error' => 'Selecciona una opción de catálogo'], 400);
            return;
        }

        if ($catalogo === 'Otro' && $otroTexto === '') {
            $this->jsonResponse(['error' => 'Debes especificar texto para opción Otro'], 400);
            return;
        }

        if ($estado === 'Sin valoración') {
            $comentario = '';
            $catalogo = '';
            $otroTexto = '';
        }

        try {
            $usuario = (string)($_SESSION['usuario'] ?? 'luis');
            $entesCruce = $this->dbManager->obtenerEntesConCrucePorRfc($rfc);
            if (empty($entesCruce)) {
                $entesCruce = [$ente];
            }
            $entesCruce = array_values(array_unique(array_filter(array_map(
                fn(string $enteClave): string => $this->dbManager->normalizarEnteClave($enteClave) ?? $enteClave,
                $entesCruce
            ))));

            $filas = 0;
            foreach ($entesCruce as $enteObjetivo) {
                $filas += $this->dbManager->guardarPrevalidacionDuplicado(
                    $rfc,
                    $enteObjetivo,
                    $estado,
                    $comentario,
                    $catalogo,
                    $otroTexto,
                    $usuario
                );
            }

            $this->jsonResponse([
                'mensaje' => "Pre-validación aplicada en " . count($entesCruce) . " ente(s)",
                'filas' => $filas,
                'entes_afectados' => $entesCruce
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function validarDatos(Request $request): void
    {
        if (!$this->esUsuarioLuis()) {
            $this->jsonResponse(['error' => 'No autorizado'], 403);
            return;
        }

        try {
            $this->dbManager->marcarResultadosValidados((string)($_SESSION['usuario'] ?? 'luis'));
            $this->redirect('/resultados');
        } catch (\Throwable $e) {
            error_log("Error al validar datos: {$e->getMessage()}");
            if ($request->isAjax()) {
                $this->jsonResponse(['error' => 'No fue posible validar los datos.'], 500);
                return;
            }
            $_SESSION['validacion_error_msg'] = $this->buildValidacionErrorMessage($e, 'validar');
            $this->redirect('/resultados?validacion_error=1');
        }
    }

    private function cancelarValidacion(Request $request): void
    {
        if (!$this->esUsuarioLuis()) {
            $this->jsonResponse(['error' => 'No autorizado'], 403);
            return;
        }

        try {
            $this->dbManager->desmarcarResultadosValidados((string)($_SESSION['usuario'] ?? 'luis'));
            $this->redirect('/resultados');
        } catch (\Throwable $e) {
            error_log("Error al cancelar validación: {$e->getMessage()}");
            if ($request->isAjax()) {
                $this->jsonResponse(['error' => 'No fue posible cancelar la validación.'], 500);
                return;
            }
            $_SESSION['validacion_error_msg'] = $this->buildValidacionErrorMessage($e, 'cancelar');
            $this->redirect('/resultados?validacion_error=1');
        }
    }

    private function buildValidacionErrorMessage(\Throwable $e, string $accion): string
    {
        $msg = strtolower($e->getMessage());
        $dbPath = $this->dbManager->getDbPath();
        $base = $accion === 'cancelar'
            ? 'No fue posible cancelar la validación.'
            : 'No fue posible validar los datos.';

        if (str_contains($msg, 'readonly') || str_contains($msg, 'attempt to write a readonly database')) {
            $writable = is_writable($dbPath) ? 'sí' : 'no';
            $dirPath = dirname($dbPath);
            $dirWritable = is_writable($dirPath) ? 'sí' : 'no';
            return "{$base} SQLite está en solo lectura. DB: {$dbPath}. ¿DB escribible?: {$writable}. ¿Carpeta escribible?: {$dirWritable}.";
        }

        return "{$base} Error técnico: {$e->getMessage()}";
    }

    private function exportarPorEnte(Request $request): void
    {
        $enteFiltro = trim((string)$request->query('ente', ''));
        $entesUsuario = $_SESSION['entes'] ?? [];
        $esLuis = $this->esUsuarioLuis();
        $modoPermiso = $esLuis ? "ALL" : $this->allowedAll($entesUsuario);

        if (!$esLuis && !$this->dbManager->resultadosValidados()) {
            $this->redirect('/resultados');
            return;
        }

        if ($enteFiltro === '') {
            $this->redirect('/resultados');
            return;
        }

        $resultadosBase = $this->dbManager->obtenerCrucesReales();
        $resultados = $esLuis
            ? $this->filtrarDuplicadosReales($resultadosBase)
            : $this->filtrarDuplicadosConVisibilidad($resultadosBase);
        $permitidos = array_filter($resultados, function ($r) use ($enteFiltro, $entesUsuario, $modoPermiso) {
            foreach ($r['entes'] as $ente) {
                if ($this->enteDisplay($ente) === $enteFiltro &&
                    $this->puedeVerEnte($ente, $entesUsuario, $modoPermiso)
                ) {
                    return true;
                }
            }
            return false;
        });

        $rows = $this->construirFilasExport($permitidos);
        $this->exportarSpreadsheet($rows, "SASP_Resultados_{$enteFiltro}");
    }

    private function exportarExcelGeneral(): void
    {
        $esLuis = $this->esUsuarioLuis();

        if (!$esLuis && !$this->dbManager->resultadosValidados()) {
            $this->redirect('/resultados');
            return;
        }

        $resultadosBase = $this->dbManager->obtenerCrucesReales();
        $resultados = $esLuis
            ? $this->filtrarDuplicadosReales($resultadosBase)
            : $this->filtrarDuplicadosConVisibilidad($resultadosBase);
        $rows = $this->construirFilasExport($resultados);
        $this->exportarSpreadsheet($rows, 'SASP_Resultados_Generales');
    }

    private function exportarSolventados(Request $request): void
    {
        if (!$this->esUsuarioLuis()) {
            http_response_code(403);
            echo 'No autorizado';
            return;
        }

        $filtroEnte = trim((string)$request->query('ente', ''));
        $ambitoSel = strtolower(trim((string)$request->query('ambito', 'estatales')));
        if (!in_array($ambitoSel, ['estatales', 'municipios'], true)) {
            $ambitoSel = 'estatales';
        }

        $resultadosBase = $this->dbManager->obtenerCrucesReales();
        $resultados = $this->filtrarDuplicadosReales($resultadosBase);
        $detalle = $this->construirDetalleSolventados(
            $resultados,
            $filtroEnte,
            $ambitoSel,
            $_SESSION['entes'] ?? [],
            'ALL'
        )['detalle'];

        $rows = [];
        foreach ($detalle as $item) {
            $rows[] = [
                'RFC' => $item['rfc'],
                'Nombre' => $item['nombre'],
                'Ente Origen' => $item['ente'],
                'Estatus' => 'Solventado',
                'Motivo de Solventación' => $item['motivo'],
                'Observación' => $item['observacion']
            ];
        }

        $this->exportarSpreadsheet(
            $rows,
            'SASP_Solventados',
            ['RFC', 'Nombre', 'Ente Origen', 'Estatus', 'Motivo de Solventación']
        );
    }

    /**
     * Excluye duplicados que ya fueron pre-validados por Luis.
     *
     * @param array<int, array<string, mixed>> $resultados
     * @return array<int, array<string, mixed>>
     */
    private function filtrarDuplicadosConVisibilidad(array $resultados): array
    {
        $filtrados = $this->filtrarDuplicadosReales($resultados);
        $out = [];

        foreach ($filtrados as $r) {
            $mapaPre = $this->dbManager->getPrevalidacionesPorRfc((string)$r['rfc']);
            $entesVisibles = [];
            foreach (($r['entes'] ?? []) as $ente) {
                if (!$this->esPrevalidadoOculto($mapaPre, (string)$ente)) {
                    $entesVisibles[] = $ente;
                }
            }

            $entesVisibles = array_values(array_unique($entesVisibles));
            if (count($entesVisibles) < 2) {
                continue;
            }

            $r['entes'] = $entesVisibles;
            $out[] = $r;
        }

        return $out;
    }

    /**
     * @param array<string, array{estado:string, comentario:string, catalogo:string, otro_texto:string}> $mapaPre
     */
    private function esPrevalidadoOculto(array $mapaPre, string $enteClave): bool
    {
        $clave = $this->dbManager->normalizarEnteClave($enteClave) ?? $enteClave;
        $estado = strtoupper(trim((string)($mapaPre[$clave]['estado'] ?? 'Sin valoración')));
        return $estado === 'SOLVENTADO' || $estado === 'NO SOLVENTADO';
    }

    private function catalogosHome(): void
    {
        $entes = $this->dbManager->listarEntes();
        $municipios = $this->dbManager->listarMunicipios();
        $this->render('catalogos.php', ['entes' => $entes, 'municipios' => $municipios]);
    }

    private function descargarPlantilla(): void
    {
        $filePath = $this->downloadsPath . '/Plantilla.xlsx';
        if (file_exists($filePath)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Plantilla.xlsx"');
            readfile($filePath);
        } else {
            http_response_code(404);
            echo "Plantilla no encontrada";
        }
    }

    private function serveStatic(string $path): void
    {
        $filePath = $this->resolveAssetPath($path);

        if ($filePath === null) {
            http_response_code(404);
            return;
        }

        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            return;
        }

        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header("Content-Type: {$mimeType}");
        readfile($filePath);
    }

    private function isAssetPath(string $path): bool
    {
        return str_starts_with($path, '/css/')
            || str_starts_with($path, '/js/')
            || str_starts_with($path, '/img/');
    }

    private function resolveAssetPath(string $path): ?string
    {
        if (!$this->isAssetPath($path)) {
            return null;
        }

        $root = realpath($this->assetsPath);
        $candidate = realpath($this->assetsPath . '/' . ltrim($path, '/'));

        if ($root === false || $candidate === false) {
            return null;
        }

        if (!str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    // ===================================================================
    // HELPERS
    // ===================================================================

    private function sanitizeText(?string $s): string
    {
        return strtoupper(trim((string)$s));
    }

    private function allowedAll(array $entesUsuario): ?string
    {
        if ($this->esUsuarioLuis()) {
            return "ALL";
        }

        $tieneTodos = false;
        $tieneEntes = false;
        $tieneMunis = false;

        foreach ($entesUsuario as $e) {
            $s = $this->sanitizeText($e);
            if ($s === "TODOS") {
                $tieneTodos = true;
            }
            if (str_contains($s, "TODOS") && str_contains($s, "ENTE")) {
                $tieneEntes = true;
            }
            if (str_contains($s, "TODOS") && str_contains($s, "MUNICIP")) {
                $tieneMunis = true;
            }
        }

        if ($tieneTodos || ($tieneEntes && $tieneMunis)) {
            return "ALL";
        }
        if ($tieneEntes) {
            return "ENTES";
        }
        if ($tieneMunis) {
            return "MUNICIPIOS";
        }
        return null;
    }

    private static ?array $entesCache = null;

    private function entesCache(): array
    {
        if (self::$entesCache !== null) {
            return self::$entesCache;
        }

        $conn = $this->dbManager->getConnection();
        $stmt = $conn->query("
            SELECT clave, siglas, nombre, 'ENTE' AS tipo FROM entes
            UNION ALL
            SELECT clave, siglas, nombre, 'MUNICIPIO' AS tipo FROM municipios
        ");

        $data = [];
        foreach ($stmt->fetchAll() as $r) {
            $clave = strtoupper(trim($r['clave'] ?? ''));
            $data[$clave] = [
                'siglas' => strtoupper(trim($r['siglas'] ?? '')),
                'nombre' => strtoupper(trim($r['nombre'] ?? '')),
                'tipo' => $r['tipo']
            ];
        }

        self::$entesCache = $data;
        return $data;
    }

    private function tipoEnte(string $enteClave): string
    {
        $info = $this->entesCache()[$this->sanitizeText($enteClave)] ?? [];
        return strtoupper((string)($info['tipo'] ?? 'ENTE'));
    }

    private function coincideAmbitoSeleccionado(string $ambitoSel, string $tipoEnte): bool
    {
        $tipo = strtoupper(trim($tipoEnte));
        if ($ambitoSel === 'municipios') {
            return str_contains($tipo, 'MUNIC');
        }
        return !str_contains($tipo, 'MUNIC');
    }

    private function puedeVerEnte(string $enteClave, array $entesUsuario, ?string $modoPermiso = null): bool
    {
        $modoPermiso ??= $this->allowedAll($entesUsuario);
        $info = $this->entesCache()[$this->sanitizeText($enteClave)] ?? [];
        $tipoEnte = $info['tipo'] ?? 'ENTE';

        return match ($modoPermiso) {
            "ALL" => true,
            "ENTES" => ($tipoEnte === "ENTE"),
            "MUNICIPIOS" => ($tipoEnte === "MUNICIPIO"),
            default => $this->anyEnteMatch($entesUsuario, [$enteClave])
        };
    }

    private function anyEnteMatch(array $entesUsuario, array $claves): bool
    {
        foreach ($entesUsuario as $eu) {
            if ($this->enteMatch($eu, $claves)) {
                return true;
            }
        }
        return false;
    }

    private function enteMatch(string $enteUsuario, array $claveLista): bool
    {
        $euser = $this->sanitizeText($enteUsuario);

        foreach ($claveLista as $c) {
            $cNorm = $this->sanitizeText($c);

            foreach ($this->entesCache() as $k => $d) {
                if (in_array($euser, [$d['siglas'], $d['nombre'], $k], true)) {
                    if (in_array($cNorm, [$d['siglas'], $d['nombre'], $k], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function enteSigla(?string $clave): string
    {
        if (!$clave) {
            return '';
        }
        $s = $this->sanitizeText($clave);
        foreach ($this->entesCache() as $k => $d) {
            if (in_array($s, [$k, $d['siglas'], $d['nombre']], true)) {
                return $d['siglas'] ?: $d['nombre'] ?: $s;
            }
        }
        return $s;
    }

    private function enteDisplay(?string $v): string
    {
        if (!$v) {
            return "Sin Ente";
        }
        $s = $this->sanitizeText($v);
        foreach ($this->entesCache() as $k => $d) {
            if (in_array($s, [$k, $d['siglas'], $d['nombre']], true)) {
                return $d['siglas'] ?: $d['nombre'] ?: $v;
            }
        }
        return $v;
    }

    private function filtrarDuplicadosReales(array $resultados): array
    {
        $resultadosFiltrados = [];

        foreach ($resultados as $r) {
            $registrosRfc = $r['registros'] ?? [];
            $qnasPorEnte = [];

            foreach ($registrosRfc as $reg) {
                $ente = $reg['ente'] ?? '';
                $qnas = array_keys($reg['qnas'] ?? []);
                $qnasPorEnte[$ente] = $qnas;
            }

            $duplicidadReal = false;
            $entesCruceReal = [];

            $entesLista = array_keys($qnasPorEnte);
            for ($i = 0; $i < count($entesLista); $i++) {
                for ($j = $i + 1; $j < count($entesLista); $j++) {
                    $e1 = $entesLista[$i];
                    $e2 = $entesLista[$j];
                    $interseccion = array_intersect($qnasPorEnte[$e1], $qnasPorEnte[$e2]);

                    if (!empty($interseccion)) {
                        $duplicidadReal = true;
                        $entesCruceReal[] = $e1;
                        $entesCruceReal[] = $e2;
                    }
                }
            }

            if (!$duplicidadReal) {
                continue;
            }

            $r['entes_cruce_real'] = array_unique($entesCruceReal);
            $resultadosFiltrados[] = $r;
        }

        return $resultadosFiltrados;
    }

    private function primerPuesto(array $registros): string
    {
        foreach ($registros as $reg) {
            $p = trim((string)($reg['puesto'] ?? ''));
            if ($p !== '') {
                return $p;
            }
        }
        return 'Sin puesto';
    }

    private function ordenPorNum(array $a, array $b): int
    {
        $numA = rtrim(trim($a['num'] ?? '999'), '.');
        $numB = rtrim(trim($b['num'] ?? '999'), '.');

        $partesA = [];
        foreach (explode('.', $numA) as $parte) {
            $partesA[] = is_numeric($parte) ? (int)$parte : 999;
        }

        $partesB = [];
        foreach (explode('.', $numB) as $parte) {
            $partesB[] = is_numeric($parte) ? (int)$parte : 999;
        }

        while (count($partesA) < 5) {
            $partesA[] = 0;
        }
        while (count($partesB) < 5) {
            $partesB[] = 0;
        }

        return $partesA <=> $partesB;
    }

    private function construirFilasExport(array $resultados): array
    {
        $filas = [];
        $rfcsResultados = array_values(array_unique(array_map(
            static fn(array $row): string => strtoupper(trim((string)($row['rfc'] ?? ''))),
            $resultados
        )));
        $solventacionesPorRfc = $this->dbManager->getSolventacionesPorRfcs($rfcsResultados);
        $prevalidacionesPorRfc = $this->dbManager->getPrevalidacionesPorRfcs($rfcsResultados);

        foreach ($resultados as $r) {
            $registros = $r['registros'] ?? [];
            $rfcActual = strtoupper(trim((string)($r['rfc'] ?? '')));
            $mapaSolvs = $solventacionesPorRfc[$rfcActual] ?? [];
            $mapaPre = $prevalidacionesPorRfc[$rfcActual] ?? [];

            $qnasPorEnte = [];
            foreach ($registros as $reg) {
                $ente = $reg['ente'] ?? '';
                $qnasPorEnte[$ente] = array_keys($reg['qnas'] ?? []);
            }

            foreach ($registros as $reg) {
                $enteOrigen = $reg['ente'] ?? 'Sin Ente';
                $qnasEnOrigen = $qnasPorEnte[$enteOrigen] ?? [];
                $interEntes = [];
                $qnasCruce = [];

                foreach ($qnasPorEnte as $enteOtro => $qnasOtro) {
                    if ($this->sanitizeText($enteOtro) === $this->sanitizeText($enteOrigen)) {
                        continue;
                    }
                    $inter = array_intersect($qnasEnOrigen, $qnasOtro);
                    if (!empty($inter)) {
                        $interEntes[] = $enteOtro;
                        foreach ($inter as $q) {
                            $qnasCruce[] = $q;
                        }
                    }
                }

                $qnasCruce = array_unique($qnasCruce);
                usort($qnasCruce, function ($a, $b) {
                    return (int)filter_var($a, FILTER_SANITIZE_NUMBER_INT) <=> (int)filter_var($b, FILTER_SANITIZE_NUMBER_INT);
                });

                $qnasLabel = '';
                if (count($qnasCruce) >= 24) {
                    $qnasLabel = "Activo en todo el ejercicio";
                } elseif (!empty($qnasCruce)) {
                    $qnasLabel = implode(', ', $qnasCruce);
                } else {
                    $qnasLabel = 'N/A';
                }

                $enteDisplay = $this->enteDisplay($enteOrigen);
                $claveEnte = $this->dbManager->normalizarEnteClave($enteOrigen) ?? $enteOrigen;
                $pre = $mapaPre[$claveEnte] ?? [];
                $solv = $mapaSolvs[$claveEnte] ?? [];
                $estado = (string)($pre['estado'] ?? $solv['estado'] ?? ($r['estado'] ?? 'Sin valoración'));
                $solventacion = $this->resolverTextoSolventacion($pre, $solv, (string)($r['solventacion'] ?? ''));

                $filas[] = [
                    'RFC' => $r['rfc'],
                    'Nombre' => $r['nombre'],
                    'Puesto' => $reg['puesto'] ?? 'Sin puesto',
                    'Fecha Alta' => $reg['fecha_ingreso'] ?? '',
                    'Fecha Baja' => $reg['fecha_egreso'] ?? '',
                    'Total Percepciones' => $reg['monto'] ?? 0,
                    'Ente Origen' => $enteDisplay,
                    'Entes Incompatibilidad' => implode(', ', array_map(
                        fn($e) => $this->enteSigla($e),
                        array_unique($interEntes)
                    )) ?: 'Sin otros entes',
                    'Quincenas' => $qnasLabel,
                    'Estatus' => $estado,
                    'Solventacion' => $solventacion
                ];
            }
        }

        return $filas;
    }

    /**
     * @param array<string, mixed> $pre
     * @param array<string, mixed> $solv
     */
    private function resolverTextoSolventacion(array $pre, array $solv, string $fallback = ''): string
    {
        $catalogo = trim((string)($pre['catalogo'] ?? ''));
        $otro = trim((string)($pre['otro_texto'] ?? ''));
        $comentario = trim((string)($pre['comentario'] ?? ''));

        $motivo = '';
        if ($catalogo !== '') {
            $motivo = $catalogo === 'Otro' ? ($otro !== '' ? $otro : 'Otro') : $catalogo;
        }

        if ($motivo !== '' && $comentario !== '') {
            return $motivo . ' - ' . $comentario;
        }
        if ($motivo !== '') {
            return $motivo;
        }
        if ($comentario !== '') {
            return $comentario;
        }

        $comentarioSolv = trim((string)($solv['comentario'] ?? ''));
        if ($comentarioSolv !== '') {
            return $comentarioSolv;
        }

        return trim($fallback);
    }

    private function exportarSpreadsheet(array $rows, string $filenameBase, ?array $headers = null): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if ($headers === null) {
            $headers = [
                'RFC',
                'Nombre',
                'Puesto',
                'Ente Origen',
                'Fecha Alta',
                'Fecha Baja',
                'Total Percepciones',
                'Entes Incompatibilidad',
                'Quincenas Cruce',
                'Estatus',
                'Solventacion'
            ];
        }

        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($headers as $header) {
                $key = $header === 'Quincenas Cruce' ? 'Quincenas' : $header;
                $sheet->setCellValueByColumnAndRow($col, $rowNum, (string)($row[$key] ?? ''));
                $col++;
            }
            $rowNum++;
        }

        foreach (range(1, count($headers)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        $filename = $filenameBase . '_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $resultados
     * @param array<int, string> $entesUsuario
     * @return array{resumen: array{rfc_solventados:int, registros_solventados:int}, detalle: array<int, array{rfc:string, nombre:string, ente:string, motivo:string, observacion:string}>}
     */
    private function construirDetalleSolventados(
        array $resultados,
        string $filtroEnte,
        string $ambitoSel,
        array $entesUsuario,
        ?string $modoPermiso
    ): array {
        $rfcSolventados = [];
        $registrosSolventados = [];
        $detalleAgrupado = [];

        $rfcsResultados = array_values(array_unique(array_map(
            static fn(array $row): string => strtoupper(trim((string)($row['rfc'] ?? ''))),
            $resultados
        )));
        $prevalidacionesPorRfc = $this->dbManager->getPrevalidacionesPorRfcs($rfcsResultados);

        foreach ($resultados as $r) {
            $rfcActual = strtoupper(trim((string)($r['rfc'] ?? '')));
            $mapaPre = $prevalidacionesPorRfc[$rfcActual] ?? [];
            foreach (($r['entes'] ?? []) as $enteClave) {
                if (!$this->puedeVerEnte((string)$enteClave, $entesUsuario, $modoPermiso)) {
                    continue;
                }
                if (!$this->coincideAmbitoSeleccionado($ambitoSel, $this->tipoEnte((string)$enteClave))) {
                    continue;
                }

                $display = $this->enteDisplay((string)$enteClave);
                if ($filtroEnte !== '' && $display !== $filtroEnte) {
                    continue;
                }

                $claveEnteActual = $this->dbManager->normalizarEnteClave((string)$enteClave) ?? (string)$enteClave;
                $pre = $mapaPre[$claveEnteActual] ?? [];
                $preEstado = strtoupper(trim((string)($pre['estado'] ?? 'Sin valoración')));
                if ($preEstado !== 'SOLVENTADO') {
                    continue;
                }

                $rfc = (string)($r['rfc'] ?? '');
                $rfcSolventados[$rfc] = true;
                $registrosSolventados[$rfc . '|' . $claveEnteActual] = true;

                $catalogo = trim((string)($pre['catalogo'] ?? ''));
                $otro = trim((string)($pre['otro_texto'] ?? ''));
                $comentario = trim((string)($pre['comentario'] ?? ''));
                $motivo = $catalogo === 'Otro' ? ($otro !== '' ? $otro : 'Otro') : ($catalogo !== '' ? $catalogo : 'Sin motivo');
                $detalleKey = $rfc . '|' . $motivo;
                if (!isset($detalleAgrupado[$detalleKey])) {
                    $detalleAgrupado[$detalleKey] = [
                        'rfc' => $rfc,
                        'nombre' => (string)($r['nombre'] ?? ''),
                        'motivo' => $motivo,
                        'observacion' => $comentario,
                        'entes' => []
                    ];
                }
                if ($detalleAgrupado[$detalleKey]['observacion'] === '' && $comentario !== '') {
                    $detalleAgrupado[$detalleKey]['observacion'] = $comentario;
                }
                $detalleAgrupado[$detalleKey]['entes'][$display] = true;
            }
        }

        $detalle = [];
        foreach ($detalleAgrupado as $item) {
            $entes = array_keys($item['entes']);
            sort($entes);
            $detalle[] = [
                'rfc' => $item['rfc'],
                'nombre' => $item['nombre'],
                'ente' => implode(', ', $entes),
                'motivo' => $item['motivo'],
                'observacion' => $item['observacion']
            ];
        }

        usort($detalle, function (array $a, array $b): int {
            $cmp = strcmp((string)$a['rfc'], (string)$b['rfc']);
            return $cmp !== 0 ? $cmp : strcmp((string)$a['motivo'], (string)$b['motivo']);
        });

        return [
            'resumen' => [
                'rfc_solventados' => count($rfcSolventados),
                'registros_solventados' => count($registrosSolventados)
            ],
            'detalle' => $detalle
        ];
    }

    private function render(string $template, array $data = []): void
    {
        $templatePath = $this->resolveTemplatePath($template);

        if (!$templatePath) {
            http_response_code(500);
            echo "Template not found: {$template}";
            return;
        }

        extract($data);

        $sanitize_text = fn($s) => $this->sanitizeText($s);
        $ente_display = fn($v) => $this->enteDisplay($v);
        $ente_sigla = fn($c) => $this->enteSigla($c);
        $db_manager = $this->dbManager;

        ob_start();
        include $templatePath;
        $output = ob_get_clean();

        echo $output;
    }

    private function resolveTemplatePath(string $template): ?string
    {
        $candidates = [];
        if (str_ends_with($template, '.php') || str_ends_with($template, '.html')) {
            $candidates[] = $template;
        } else {
            $candidates[] = $template . '.php';
            $candidates[] = $template . '.html';
        }

        foreach ($candidates as $candidate) {
            $path = $this->templatesPath . '/' . ltrim($candidate, '/');
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function esUsuarioLuis(): bool
    {
        return strtolower(trim((string)($_SESSION['usuario'] ?? ''))) === 'luis';
    }

    private function redirect(string $path): void
    {
        header("Location: {$path}");
        exit;
    }

    private function normalizeFilesArray(array $files): array
    {
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['tmp_name']);

        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
        }

        return $normalized;
    }
}
