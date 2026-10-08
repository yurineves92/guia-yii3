<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class DbTarefaRepository implements TarefaRepositoryInterface
{
    private const TABELA = 'estrutura_tarefa';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function buscar(int $id): ?Tarefa
    {
        $linha = $this->db->select()->from(self::TABELA)->where(['id' => $id])->one();

        return $linha === null ? null : $this->criar($linha);
    }

    public function todas(): array
    {
        $linhas = $this->db->select()->from(self::TABELA)->orderBy(['id' => SORT_ASC])->all();

        return array_map($this->criar(...), $linhas);
    }

    public function salvar(Tarefa $tarefa): void
    {
        $colunas = [
            'titulo' => $tarefa->getTitulo(),
            'concluida_em' => $tarefa->getConcluidaEm()?->format('Y-m-d H:i:s'),
        ];

        $id = $tarefa->getId();

        if ($id === null) {
            $chaves = $this->db->createCommand()->insertReturningPks(self::TABELA, $colunas);
            $tarefa->definirId((int) $chaves['id']);

            return;
        }

        $this->db->createCommand()->update(self::TABELA, $colunas, ['id' => $id])->execute();
    }

    private function criar(array $linha): Tarefa
    {
        return new Tarefa(
            titulo: (string) $linha['titulo'],
            id: (int) $linha['id'],
            concluidaEm: $linha['concluida_em'] === null ? null : new DateTimeImmutable((string) $linha['concluida_em']),
        );
    }
}
