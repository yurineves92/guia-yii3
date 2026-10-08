<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class CadastroDeAutor
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * Grava o autor e os livros dele numa transação só.
     *
     * @param list<array{titulo: string, slug: string, ano: int, preco: string}> $livros
     */
    public function cadastrar(string $nome, array $livros): int
    {
        return $this->db->transaction(function (ConnectionInterface $db) use ($nome, $livros): int {
            $db->createCommand()->insert('autor', ['nome' => $nome])->execute();
            $autorId = (int) $db->getLastInsertId();

            foreach ($livros as $livro) {
                $db->createCommand()->insert('livro', [
                    ...$livro,
                    'autor_id' => $autorId,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ])->execute();
            }

            return $autorId;
        });
    }

    /**
     * O mesmo que cadastrar(), controlando a transação na mão.
     *
     * @param list<array{titulo: string, slug: string, ano: int, preco: string}> $livros
     */
    public function cadastrarSemClosure(string $nome, array $livros): int
    {
        $transaction = $this->db->beginTransaction();

        try {
            $this->db->createCommand()->insert('autor', ['nome' => $nome])->execute();
            $autorId = (int) $this->db->getLastInsertId();

            foreach ($livros as $livro) {
                $this->db->createCommand()->insert('livro', [
                    ...$livro,
                    'autor_id' => $autorId,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ])->execute();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $autorId;
    }
}
