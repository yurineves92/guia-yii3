<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Security\PasswordHasher;

/**
 * Handles the creation of table `usuario_exemplo`.
 */
final class M261008050722CreateUsuarioExemploTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('usuario_exemplo', [
            'id' => $columnBuilder::primaryKey(),
            'email' => $columnBuilder::string(255)->notNull()->unique(),
            'nome' => $columnBuilder::string(100)->notNull(),
            'senha_hash' => $columnBuilder::string(255)->notNull(),
        ]);

        // Usuários de exemplo. Todos com a senha "senha-de-exemplo".
        $hash = (new PasswordHasher())->hash('senha-de-exemplo');

        $b->insertBatch('usuario_exemplo', [
            ['ana@exemplo.com', 'Ana', $hash],
            ['bruno@exemplo.com', 'Bruno', $hash],
            ['carla@exemplo.com', 'Carla', $hash],
        ], ['email', 'nome', 'senha_hash']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('usuario_exemplo');
    }
}
