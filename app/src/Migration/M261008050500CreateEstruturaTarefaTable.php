<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `estrutura_tarefa`, usada no capítulo "Domínio: entidades e serviços".
 */
final class M261008050500CreateEstruturaTarefaTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('estrutura_tarefa', [
            'id' => $columnBuilder::primaryKey(),
            'titulo' => $columnBuilder::string(100)->notNull(),
            'concluida_em' => $columnBuilder::datetime()->null(),
        ]);

        $b->insertBatch('estrutura_tarefa', [
            ['Escrever o capítulo de domínio'],
            ['Revisar os exemplos'],
            ['Publicar o guia'],
        ], ['titulo']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('estrutura_tarefa');
    }
}
