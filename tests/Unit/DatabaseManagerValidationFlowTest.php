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
}
