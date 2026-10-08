<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `api_tarefa`, usada pelos exemplos de API REST.
 */
final class M261008051000CreateApiTarefaTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('api_tarefa', [
            'id' => $columnBuilder::primaryKey(),
            'titulo' => $columnBuilder::string(200)->notNull(),
            'concluida' => $columnBuilder::boolean()->notNull()->defaultValue(false),
            'criada_em' => $columnBuilder::datetime()->notNull(),
        ]);

        $b->insertBatch('api_tarefa', [
            ['Comprar café', true, '2026-10-01 09:00:00'],
            ['Revisar o capítulo de cache', true, '2026-10-01 10:30:00'],
            ['Responder os e-mails', false, '2026-10-02 08:15:00'],
            ['Atualizar as dependências', false, '2026-10-02 14:00:00'],
            ['Escrever os testes da API', false, '2026-10-03 11:45:00'],
            ['Marcar a reunião de planejamento', true, '2026-10-04 16:20:00'],
            ['Trocar a senha do banco de homologação', false, '2026-10-05 09:10:00'],
            ['Documentar os endpoints', false, '2026-10-06 13:00:00'],
        ], ['titulo', 'concluida', 'criada_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('api_tarefa');
    }
}
