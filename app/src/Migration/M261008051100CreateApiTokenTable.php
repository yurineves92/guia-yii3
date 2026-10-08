<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `api_token`, com dois tokens de exemplo.
 *
 * O banco guarda só o hash SHA-256 de cada token.
 */
final class M261008051100CreateApiTokenTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('api_token', [
            'id' => $columnBuilder::primaryKey(),
            'cliente' => $columnBuilder::string(100)->notNull(),
            'token_hash' => $columnBuilder::char(64)->notNull()->unique(),
            'expira_em' => $columnBuilder::datetime()->null(),
        ]);

        $b->insertBatch('api_token', [
            ['Cliente de exemplo', hash('sha256', 'token-de-exemplo-nao-use-em-producao'), null],
            ['Cliente antigo', hash('sha256', 'token-de-exemplo-expirado'), '2026-01-01 00:00:00'],
        ], ['cliente', 'token_hash', 'expira_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('api_token');
    }
}
