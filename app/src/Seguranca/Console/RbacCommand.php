<?php

declare(strict_types=1);

namespace App\Seguranca\Console;

use App\Seguranca\Rbac\Permissao;
use App\Seguranca\Rbac\ProprioPerfilRule;
use App\Seguranca\UsuarioRepositorio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Yii\Console\ExitCode;

#[AsCommand(
    name: 'seguranca:rbac',
    description: 'Recria os papéis e as permissões de exemplo',
)]
final class RbacCommand extends Command
{
    private const PAPEIS_DOS_USUARIOS = [
        'ana@exemplo.com' => 'admin',
        'bruno@exemplo.com' => 'membro',
        'carla@exemplo.com' => 'membro',
    ];

    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly ItemsStorageInterface $itens,
        private readonly AssignmentsStorageInterface $atribuicoes,
        private readonly UsuarioRepositorio $usuarios,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Começa do zero, para o comando poder rodar mais de uma vez
        $this->atribuicoes->clear();
        $this->itens->clear();

        $this->manager
            ->addPermission(
                (new Permission(Permissao::VerRelatorios->value))->withDescription('Ver os relatórios'),
            )
            ->addPermission(
                (new Permission(Permissao::EditarPerfil->value))->withDescription('Editar qualquer perfil'),
            )
            ->addPermission(
                (new Permission(Permissao::EditarProprioPerfil->value))
                    ->withDescription('Editar o próprio perfil')
                    ->withRuleName(ProprioPerfilRule::class),
            )
            ->addRole((new Role('membro'))->withDescription('Usuário comum'))
            ->addRole((new Role('admin'))->withDescription('Administrador'))
            // Quem pode editar o próprio perfil pode "editar perfil", desde que a regra passe
            ->addChild(Permissao::EditarProprioPerfil->value, Permissao::EditarPerfil->value)
            ->addChild('membro', Permissao::EditarProprioPerfil->value)
            // O admin tem tudo o que o membro tem, e mais
            ->addChild('admin', 'membro')
            ->addChild('admin', Permissao::EditarPerfil->value)
            ->addChild('admin', Permissao::VerRelatorios->value);

        foreach (self::PAPEIS_DOS_USUARIOS as $email => $papel) {
            $usuario = $this->usuarios->porEmail($email);
            if ($usuario !== null) {
                $this->manager->assign($papel, $usuario->id);
                $output->writeln("$email: $papel");
            }
        }

        return ExitCode::OK;
    }
}
