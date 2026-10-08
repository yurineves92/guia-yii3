<?php

declare(strict_types=1);

namespace App\Seguranca;

use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class UsuarioRepositorio implements IdentityRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findIdentity(string $id): ?Usuario
    {
        return $this->buscar(['id' => (int) $id]);
    }

    public function porEmail(string $email): ?Usuario
    {
        return $this->buscar(['email' => mb_strtolower(trim($email))]);
    }

    public function alterarSenha(int $id, string $senhaHash): void
    {
        $this->db->createCommand()
            ->update('usuario_exemplo', ['senha_hash' => $senhaHash], ['id' => $id])
            ->execute();
    }

    public function alterarNome(int $id, string $nome): void
    {
        $this->db->createCommand()
            ->update('usuario_exemplo', ['nome' => $nome], ['id' => $id])
            ->execute();
    }

    private function buscar(array $condicao): ?Usuario
    {
        $linha = $this->db->select()->from('usuario_exemplo')->where($condicao)->one();

        if ($linha === null) {
            return null;
        }

        return new Usuario(
            id: (int) $linha['id'],
            email: $linha['email'],
            nome: $linha['nome'],
            senhaHash: $linha['senha_hash'],
        );
    }
}
