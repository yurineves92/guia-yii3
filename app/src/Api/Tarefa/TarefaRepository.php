<?php

declare(strict_types=1);

namespace App\Api\Tarefa;

use DateTimeImmutable;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class TarefaRepository
{
    private const TABELA = 'api_tarefa';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function leitor(): QueryDataReader
    {
        return new QueryDataReader(
            $this->db->select()->from(self::TABELA)->orderBy(['id' => SORT_ASC]),
        );
    }

    public function buscar(int $id): ?array
    {
        return $this->db->select()->from(self::TABELA)->where(['id' => $id])->one();
    }

    public function criar(string $titulo, bool $concluida): int
    {
        $this->db->createCommand()->insert(self::TABELA, [
            'titulo' => $titulo,
            'concluida' => $concluida,
            'criada_em' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ])->execute();

        return (int) $this->db->getLastInsertId();
    }

    /**
     * @param array{titulo?: string, concluida?: bool} $campos
     */
    public function atualizar(int $id, array $campos): void
    {
        $this->db->createCommand()->update(self::TABELA, $campos, ['id' => $id])->execute();
    }

    public function apagar(int $id): bool
    {
        return $this->db->createCommand()->delete(self::TABELA, ['id' => $id])->execute() > 0;
    }
}
