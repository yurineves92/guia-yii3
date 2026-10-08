<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Preenche as tabelas `autor` e `livro` com dados de exemplo.
 */
final class M261008045704PopulaLivros implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->insertBatch('autor', [
            [1, 'Machado de Assis'],
            [2, 'Clarice Lispector'],
            [3, 'Jorge Amado'],
            [4, 'Graciliano Ramos'],
        ], ['id', 'nome']);

        $b->insertBatch('livro', [
            [1, 1, 'Memórias Póstumas de Brás Cubas', 'memorias-postumas', 1881, 34.90, '2026-01-05 09:30:00'],
            [2, 1, 'Quincas Borba', 'quincas-borba', 1891, 39.90, '2026-01-12 14:10:00'],
            [3, 1, 'Dom Casmurro', 'dom-casmurro', 1899, 29.90, '2026-02-02 10:00:00'],
            [4, 2, 'Perto do Coração Selvagem', 'perto-do-coracao-selvagem', 1943, 49.90, '2026-02-20 16:45:00'],
            [5, 2, 'A Paixão segundo G.H.', 'a-paixao-segundo-gh', 1964, 54.90, '2026-03-03 11:20:00'],
            [6, 2, 'A Hora da Estrela', 'a-hora-da-estrela', 1977, 44.90, '2026-03-18 08:05:00'],
            [7, 3, 'Capitães da Areia', 'capitaes-da-areia', 1937, 59.90, '2026-04-07 13:30:00'],
            [8, 3, 'Gabriela, Cravo e Canela', 'gabriela-cravo-e-canela', 1958, 69.90, '2026-04-22 17:00:00'],
            [9, 3, 'Dona Flor e Seus Dois Maridos', 'dona-flor', 1966, 74.90, '2026-05-14 09:15:00'],
            [10, 4, 'São Bernardo', 'sao-bernardo', 1934, 42.90, '2026-06-01 10:40:00'],
            [11, 4, 'Angústia', 'angustia', 1936, 47.90, '2026-06-19 15:25:00'],
            [12, 4, 'Vidas Secas', 'vidas-secas', 1938, 39.90, '2026-07-08 12:00:00'],
        ], ['id', 'autor_id', 'titulo', 'slug', 'ano', 'preco', 'cadastrado_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->delete('livro');
        $b->delete('autor');
    }
}
