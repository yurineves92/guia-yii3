<?php

declare(strict_types=1);

namespace App\Topicos\Console;

use App\Topicos\Email\BoasVindasEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'email:boas-vindas',
    description: 'Envia o e-mail de boas-vindas',
)]
final class EnviarBoasVindasCommand extends Command
{
    public function __construct(
        private readonly BoasVindasEmail $boasVindas,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail do destinatário')
            ->addArgument('nome', InputArgument::REQUIRED, 'Nome do destinatário');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $nome */
        $nome = $input->getArgument('nome');

        $this->boasVindas->enviar($nome, $email);

        $output->writeln("E-mail enviado para $email.");

        return ExitCode::OK;
    }
}
