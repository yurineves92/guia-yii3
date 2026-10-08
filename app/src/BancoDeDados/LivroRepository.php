<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\QueryInterface;

final readonly class LivroRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function comAutor(): QueryInterface
    {
        return $this->db
            ->select(['l.id', 'l.titulo', 'l.ano', 'l.preco', 'autor' => 'a.nome'])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id')
            ->orderBy(['a.nome' => SORT_ASC, 'l.ano' => SORT_ASC]);
    }

    public function buscar(?string $termo, ?float $precoMaximo): QueryInterface
    {
        return $this->comAutor()
            ->andFilterWhere(['like', 'l.titulo', $termo])
            ->andFilterWhere(['<=', 'l.preco', $precoMaximo]);
    }

    public function totaisPorAutor(): QueryInterface
    {
        return $this->db
            ->select(['autor' => 'a.nome', 'livros' => 'COUNT(*)', 'preco_medio' => 'AVG(l.preco)'])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id')
            ->groupBy('a.nome')
            ->having(['>', 'COUNT(*)', 2])
            ->orderBy(['preco_medio' => SORT_DESC]);
    }

    public function inserir(int $autorId, string $titulo, string $slug, int $ano, string $preco): int
    {
        $this->db->createCommand()->insert('livro', [
            'autor_id' => $autorId,
            'titulo' => $titulo,
            'slug' => $slug,
            'ano' => $ano,
            'preco' => $preco,
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ])->execute();

        return (int) $this->db->getLastInsertId();
    }

    public function reajustarPrecos(int $autorId, int $percentual): int
    {
        return $this->db->createCommand()->update(
            'livro',
            ['preco' => new Expression('ROUND(preco * :fator, 2)', [':fator' => 1 + $percentual / 100])],
            ['autor_id' => $autorId],
        )->execute();
    }

    public function salvar(int $autorId, string $titulo, string $slug, int $ano, string $preco): void
    {
        $this->db->createCommand()->upsert(
            'livro',
            [
                'autor_id' => $autorId,
                'titulo' => $titulo,
                'slug' => $slug,
                'ano' => $ano,
                'preco' => $preco,
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ],
            ['titulo' => $titulo, 'preco' => $preco],
        )->execute();
    }

    public function apagar(string $slug): int
    {
        return $this->db->createCommand()->delete('livro', ['slug' => $slug])->execute();
    }
}
