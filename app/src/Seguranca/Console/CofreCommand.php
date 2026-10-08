<?php

declare(strict_types=1);

namespace App\Seguranca\Console;

use App\Seguranca\Cripto\Cofre;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Security\AuthenticationException;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'seguranca:cofre',
    description: 'Cifra um texto e decifra de volta, para testar o Cofre',
)]
final class CofreCommand extends Command
{
    public function __construct(
        private readonly Cofre $cofre,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('texto', InputArgument::REQUIRED, 'Texto a cifrar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cifrado = $this->cofre->cifrar($input->getArgument('texto'));
        $output->writeln("Cifrado:  $cifrado");
        $output->writeln('Decifrado: ' . $this->cofre->decifrar($cifrado));

        // Trocando um caractere no meio, a verificação falha
        $adulterado = substr_replace($cifrado, $cifrado[20] === 'A' ? 'B' : 'A', 20, 1);
        try {
            $this->cofre->decifrar($adulterado);
        } catch (AuthenticationException $e) {
            $output->writeln('Adulterado: ' . $e->getMessage());
        }

        return ExitCode::OK;
    }
}
