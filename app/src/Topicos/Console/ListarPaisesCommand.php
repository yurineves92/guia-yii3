<?php

declare(strict_types=1);

namespace App\Topicos\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'pais:listar',
    description: 'Lista os países cadastrados',
)]
final class ListarPaisesCommand extends Command
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('busca', InputArgument::OPTIONAL, 'Parte do nome do país')
            ->addOption('limite', 'l', InputOption::VALUE_REQUIRED, 'Quantidade máxima de linhas', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limite = (int) $input->getOption('limite');
        if ($limite < 1) {
            $io->error('O limite precisa ser um número maior que zero.');
            return ExitCode::USAGE;
        }

        $query = $this->db
            ->select(['codigo', 'nome', 'populacao'])
            ->from('pais')
            ->orderBy(['nome' => SORT_ASC])
            ->limit($limite);

        /** @var string|null $busca */
        $busca = $input->getArgument('busca');
        if ($busca !== null) {
            $query->where(['like', 'nome', $busca]);
        }

        /** @var list<array{codigo: string, nome: string, populacao: int|string}> $paises */
        $paises = $query->all();

        if ($paises === []) {
            $io->warning('Nenhum país encontrado.');
            return ExitCode::OK;
        }

        $linhas = [];
        foreach ($paises as $pais) {
            $linhas[] = [
                $pais['codigo'],
                $pais['nome'],
                number_format((int) $pais['populacao'], 0, ',', '.'),
            ];
        }

        $io->table(['Código', 'Nome', 'População'], $linhas);

        return ExitCode::OK;
    }
}
