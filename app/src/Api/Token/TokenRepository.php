<?php

declare(strict_types=1);

namespace App\Api\Token;

use DateTimeImmutable;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Security\Random;

final readonly class TokenRepository implements IdentityWithTokenRepositoryInterface
{
    private const TABELA = 'api_token';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findIdentityByToken(string $token, ?string $type = null): ?ClienteDaApi
    {
        $linha = $this->db->select(['id', 'cliente'])
            ->from(self::TABELA)
            ->where(['token_hash' => hash('sha256', $token)])
            ->andWhere([
                'or',
                ['expira_em' => null],
                ['>', 'expira_em', (new DateTimeImmutable())->format('Y-m-d H:i:s')],
            ])
            ->one();

        return $linha === null ? null : new ClienteDaApi((int) $linha['id'], $linha['cliente']);
    }

    /**
     * Cria um token para o cliente e devolve o valor em texto puro.
     *
     * O valor não fica salvo em lugar nenhum: mostre-o uma única vez a quem pediu.
     */
    public function emitir(string $cliente, ?DateTimeImmutable $expiraEm = null): string
    {
        $token = Random::string(40);

        $this->db->createCommand()->insert(self::TABELA, [
            'cliente' => $cliente,
            'token_hash' => hash('sha256', $token),
            'expira_em' => $expiraEm?->format('Y-m-d H:i:s'),
        ])->execute();

        return $token;
    }
}
