<?php

namespace GestaoInterativa\Tests\Database;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Database\Database;
use GestaoInterativa\Exceptions\DatabaseException;
use PDO;
use PDOStatement;

class DatabaseTest extends TestCase
{
    private $database;
    private $pdo;
    private $config;

    protected function setUp(): void
    {
        $this->config = [
            'host' => 'localhost',
            'port' => 3307,
            'database' => 'sistema_frotas',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];

        $this->pdo = $this->createMock(PDO::class);
        $this->database = new Database($this->config);
    }

    public function testDatabaseCanBeCreated()
    {
        $this->assertInstanceOf(Database::class, $this->database);
    }

    public function testGetConnectionReturnsPdoInstance()
    {
        $connection = $this->database->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testQueryReturnsStatement()
    {
        $sql = 'SELECT * FROM pneus';
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('query')
            ->with($sql)
            ->willReturn($stmt);

        $result = $this->database->query($sql);
        $this->assertInstanceOf(PDOStatement::class, $result);
    }

    public function testPrepareReturnsStatement()
    {
        $sql = 'SELECT * FROM pneus WHERE id = ?';
        $stmt = $this->createMock(PDOStatement::class);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt);

        $result = $this->database->prepare($sql);
        $this->assertInstanceOf(PDOStatement::class, $result);
    }

    public function testInsertReturnsLastInsertId()
    {
        $table = 'pneus';
        $data = [
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_values($data));

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO pneus (numero_serie, marca, modelo, status_id) VALUES (?, ?, ?, ?)')
            ->willReturn($stmt);

        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $result = $this->database->insert($table, $data);
        $this->assertEquals(1, $result);
    }

    public function testUpdateReturnsAffectedRows()
    {
        $table = 'pneus';
        $data = [
            'marca' => 'Teste2',
            'modelo' => 'Teste2'
        ];
        $where = ['id' => 1];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_merge(array_values($data), array_values($where)));

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE pneus SET marca = ?, modelo = ? WHERE id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->database->update($table, $data, $where);
        $this->assertEquals(1, $result);
    }

    public function testDeleteReturnsAffectedRows()
    {
        $table = 'pneus';
        $where = ['id' => 1];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_values($where));

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM pneus WHERE id = ?')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->database->delete($table, $where);
        $this->assertEquals(1, $result);
    }

    public function testSelectReturnsArray()
    {
        $table = 'pneus';
        $where = ['status_id' => 1];
        $expectedResult = [
            [
                'id' => 1,
                'numero_serie' => '123456',
                'marca' => 'Teste',
                'modelo' => 'Teste',
                'status_id' => 1
            ]
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_values($where));

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResult);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM pneus WHERE status_id = ?')
            ->willReturn($stmt);

        $result = $this->database->select($table, $where);
        $this->assertEquals($expectedResult, $result);
    }

    public function testSelectOneReturnsSingleRow()
    {
        $table = 'pneus';
        $where = ['id' => 1];
        $expectedResult = [
            'id' => 1,
            'numero_serie' => '123456',
            'marca' => 'Teste',
            'modelo' => 'Teste',
            'status_id' => 1
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_values($where));

        $stmt->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedResult);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM pneus WHERE id = ? LIMIT 1')
            ->willReturn($stmt);

        $result = $this->database->selectOne($table, $where);
        $this->assertEquals($expectedResult, $result);
    }

    public function testCountReturnsInteger()
    {
        $table = 'pneus';
        $where = ['status_id' => 1];
        $expectedResult = 1;

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(array_values($where));

        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn($expectedResult);

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT COUNT(*) FROM pneus WHERE status_id = ?')
            ->willReturn($stmt);

        $result = $this->database->count($table, $where);
        $this->assertEquals($expectedResult, $result);
    }

    public function testBeginTransaction()
    {
        $this->pdo->expects($this->once())
            ->method('beginTransaction');

        $this->database->beginTransaction();
    }

    public function testCommit()
    {
        $this->pdo->expects($this->once())
            ->method('commit');

        $this->database->commit();
    }

    public function testRollback()
    {
        $this->pdo->expects($this->once())
            ->method('rollBack');

        $this->database->rollback();
    }

    public function testInTransaction()
    {
        $this->pdo->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $result = $this->database->inTransaction();
        $this->assertTrue($result);
    }

    public function testLastInsertId()
    {
        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(1);

        $result = $this->database->lastInsertId();
        $this->assertEquals(1, $result);
    }

    public function testQuote()
    {
        $value = 'test';
        $this->pdo->expects($this->once())
            ->method('quote')
            ->with($value)
            ->willReturn("'test'");

        $result = $this->database->quote($value);
        $this->assertEquals("'test'", $result);
    }
} 