<?php

declare(strict_types=1);

namespace App\Tests\Unit\Topicos\Console;

use App\Topicos\Console\ListarPaisesCommand;
use Codeception\Test\Unit;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

final class ListarPaisesCommandTest extends Unit
{
    private ConnectionInterface $db;

    protected function _before(): void
    {
        // Banco SQLite em memória: some quando o teste termina
        $this->db = new Connection(
            new Driver('sqlite::memory:'),
            new SchemaCache(new ArrayCache()),
        );

        $this->db->createCommand(
            'CREATE TABLE pais (codigo CHAR(2), nome VARCHAR(52), populacao INTEGER)',
        )->execute();

        $this->db->createCommand()->insertBatch('pais', [
            ['AR', 'Argentina', 46_000_000],
            ['BR', 'Brasil', 212_000_000],
            ['UY', 'Uruguai', 3_400_000],
        ], ['codigo', 'nome', 'populacao'])->execute();
    }

    public function testRespeitaOLimite(): void
    {
        $tester = new CommandTester(new ListarPaisesCommand($this->db));

        $tester->execute(['--limite' => '2']);

        $tester->assertCommandIsSuccessful();
        $saida = $tester->getDisplay();
        assertStringContainsString('Argentina', $saida);
        assertStringContainsString('212.000.000', $saida);
        assertStringNotContainsString('Uruguai', $saida);
    }

    public function testAvisaQuandoNaoEncontraNada(): void
    {
        $tester = new CommandTester(new ListarPaisesCommand($this->db));

        $codigo = $tester->execute(['busca' => 'Chile']);

        assertSame(0, $codigo);
        assertStringContainsString('Nenhum país encontrado.', $tester->getDisplay());
    }
}
