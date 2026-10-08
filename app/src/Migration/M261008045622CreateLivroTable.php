<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Constant\ReferentialAction;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Handles the creation of table `livro`.
 * Has foreign keys to the tables:
 *
 * - `{{%autor}}`
 */
final class M261008045622CreateLivroTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('livro', [
            'id' => $columnBuilder::primaryKey(),
            'autor_id' => $columnBuilder::integer()->notNull()->reference(new ForeignKey(
                foreignTableName: 'autor',
                foreignColumnNames: ['id'],
                onDelete: ReferentialAction::CASCADE,
            )),
            'titulo' => $columnBuilder::string(150)->notNull(),
            'slug' => $columnBuilder::string(100)->notNull()->unique(),
            'ano' => $columnBuilder::integer()->notNull(),
            'preco' => $columnBuilder::decimal(10, 2)->notNull(),
            'cadastrado_em' => $columnBuilder::datetime()->notNull(),
        ]);

        // creates index for column `autor_id`
        $b->createIndex(
            'livro',
            'idx-livro-autor_id',
            'autor_id'
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('livro');
    }
}
