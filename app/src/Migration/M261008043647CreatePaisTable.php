<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `pais` com alguns registros de exemplo.
 */
final class M261008043647CreatePaisTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('pais', [
            'id' => $columnBuilder::primaryKey(),
            'codigo' => $columnBuilder::char(2)->notNull()->unique(),
            'nome' => $columnBuilder::string(52)->notNull(),
            'populacao' => $columnBuilder::integer()->notNull(),
        ]);

        $b->insertBatch('pais', [
            ['AU', 'Austrália', 26_000_000],
            ['BR', 'Brasil', 212_000_000],
            ['CA', 'Canadá', 40_000_000],
            ['CN', 'China', 1_410_000_000],
            ['DE', 'Alemanha', 83_000_000],
            ['FR', 'França', 68_000_000],
            ['GB', 'Reino Unido', 68_000_000],
            ['IN', 'Índia', 1_440_000_000],
            ['PT', 'Portugal', 10_600_000],
            ['US', 'Estados Unidos', 335_000_000],
        ], ['codigo', 'nome', 'populacao']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('pais');
    }
}
