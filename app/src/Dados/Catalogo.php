<?php

declare(strict_types=1);

namespace App\Dados;

use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class Catalogo
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function livros(): QueryDataReader
    {
        $query = $this->db
            ->select([
                'id' => 'l.id',
                'titulo' => 'l.titulo',
                'autor' => 'a.nome',
                'ano' => 'l.ano',
                'preco' => 'l.preco',
            ])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id');

        return new QueryDataReader(
            query: $query,
            sort: Sort::only(['titulo', 'autor', 'ano', 'preco']),
            fieldMapper: [
                'id' => 'l.id',
                'titulo' => 'l.titulo',
                'autor' => 'a.nome',
                'autorId' => 'l.autor_id',
                'ano' => 'l.ano',
                'preco' => 'l.preco',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function autores(): array
    {
        return $this->db
            ->select(['nome', 'id'])
            ->from('autor')
            ->orderBy(['nome' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }
}
