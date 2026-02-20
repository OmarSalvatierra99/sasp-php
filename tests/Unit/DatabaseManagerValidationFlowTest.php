<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sasp\Core\DatabaseManager;

class DatabaseManagerValidationFlowTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbPath = sys_get_temp_dir() . '/sasp_validation_' . uniqid('', true) . '.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        parent::tearDown();
    }

    public function testResultadosValidadosStartsAsFalse(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $this->assertFalse($db->resultadosValidados());
    }

    public function testMarcarResultadosValidadosSetsTrue(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->marcarResultadosValidados('luis');

        $this->assertTrue($db->resultadosValidados());
    }

    public function testDesmarcarResultadosValidadosSetsFalseAgain(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->marcarResultadosValidados('luis');
        $db->desmarcarResultadosValidados('luis');

        $this->assertFalse($db->resultadosValidados());
    }

    public function testGuardarPrevalidacionDuplicadoIsReturnedByGetPrevalidaciones(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->guardarPrevalidacionDuplicado(
            'ABC123456XYZ',
            'GENERAL',
            'Solventado',
            'Revisión preliminar correcta',
            'Remiten documentación que acredita el reintegro de los recursos observados.',
            '',
            'luis'
        );

        $pre = $db->getPrevalidacionesPorRfc('ABC123456XYZ');
        $this->assertArrayHasKey('GENERAL', $pre);
        $this->assertSame('Solventado', $pre['GENERAL']['estado']);
        $this->assertSame('Revisión preliminar correcta', $pre['GENERAL']['comentario']);
    }

    public function testDesmarcarResultadosValidadosKeepsExistingPrevalidaciones(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->guardarPrevalidacionDuplicado(
            'XYZ123456ABC',
            'GENERAL',
            'Solventado',
            'Se solventó',
            'Remiten oficios de licencia con goce de sueldo.',
            '',
            'luis'
        );

        $db->marcarResultadosValidados('luis');
        $db->desmarcarResultadosValidados('luis');

        $pre = $db->getPrevalidacionesPorRfc('XYZ123456ABC');
        $this->assertArrayHasKey('GENERAL', $pre);
        $this->assertSame('Solventado', $pre['GENERAL']['estado']);
        $this->assertSame('Se solventó', $pre['GENERAL']['comentario']);
        $this->assertSame('Remiten oficios de licencia con goce de sueldo.', $pre['GENERAL']['catalogo']);
        $this->assertSame('', $pre['GENERAL']['otro_texto']);
    }

    public function testGetPrevalidacionesPorRfcsReturnsGroupedData(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->guardarPrevalidacionDuplicado(
            'AAA010101AAA',
            'GENERAL',
            'Solventado',
            'ok',
            'Remiten oficios de licencia con goce de sueldo.',
            '',
            'luis'
        );
        $db->guardarPrevalidacionDuplicado(
            'BBB010101BBB',
            'GENERAL',
            'Sin valoración',
            '',
            '',
            '',
            'luis'
        );

        $pre = $db->getPrevalidacionesPorRfcs(['AAA010101AAA', 'BBB010101BBB']);

        $this->assertArrayHasKey('AAA010101AAA', $pre);
        $this->assertArrayHasKey('GENERAL', $pre['AAA010101AAA']);
        $this->assertSame('Solventado', $pre['AAA010101AAA']['GENERAL']['estado']);
        $this->assertArrayHasKey('BBB010101BBB', $pre);
    }

    public function testGetSolventacionesPorRfcsReturnsGroupedData(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->actualizarSolventacion(
            'CCC010101CCC',
            'Solventado',
            'validado',
            '',
            '',
            'GENERAL'
        );

        $solv = $db->getSolventacionesPorRfcs(['CCC010101CCC']);

        $this->assertArrayHasKey('CCC010101CCC', $solv);
        $this->assertArrayHasKey('GENERAL', $solv['CCC010101CCC']);
        $this->assertSame('Solventado', $solv['CCC010101CCC']['GENERAL']['estado']);
    }

    public function testGuardarPrevalidacionDuplicadoStoresHistoryForCancelAction(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $db->guardarPrevalidacionDuplicado(
            'DDD010101DDD',
            'GENERAL',
            'Solventado',
            'estado inicial',
            'Remiten oficios de licencia con goce de sueldo.',
            '',
            'luis'
        );
        $db->guardarPrevalidacionDuplicado(
            'DDD010101DDD',
            'GENERAL',
            'Sin valoración',
            '',
            '',
            '',
            'luis'
        );

        $stmt = $db->getConnection()->prepare("
            SELECT accion, estado_anterior, estado_nuevo
            FROM prevalidaciones_historial
            WHERE rfc = ? AND ente = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['DDD010101DDD', 'GENERAL']);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('cancelar_solventacion', $row['accion']);
        $this->assertSame('Solventado', $row['estado_anterior']);
        $this->assertSame('Sin valoración', $row['estado_nuevo']);
    }

    public function testObtenerEntesConCrucePorRfcReturnsOnlyEntesWithOverlap(): void
    {
        $db = new DatabaseManager($this->dbPath);
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO registros_laborales
                (rfc, ente, nombre, puesto, fecha_ingreso, fecha_egreso, monto, qnas)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(['RFC000000AAA', 'ENTE_A', 'Trabajador', 'Puesto', '', '', 1000, json_encode(['Q01' => 1, 'Q02' => 1])]);
        $stmt->execute(['RFC000000AAA', 'ENTE_B', 'Trabajador', 'Puesto', '', '', 1000, json_encode(['Q02' => 1, 'Q03' => 1])]);
        $stmt->execute(['RFC000000AAA', 'ENTE_C', 'Trabajador', 'Puesto', '', '', 1000, json_encode(['Q10' => 1])]);

        $entes = $db->obtenerEntesConCrucePorRfc('RFC000000AAA');
        $this->assertSame(['ENTE_A', 'ENTE_B'], $entes);
    }

}
