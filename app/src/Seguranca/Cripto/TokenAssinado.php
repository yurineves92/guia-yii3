<?php

declare(strict_types=1);

namespace App\Seguranca\Cripto;

use InvalidArgumentException;
use SensitiveParameter;
use Yiisoft\Security\DataIsTamperedException;
use Yiisoft\Security\Mac;
use Yiisoft\Strings\StringHelper;

/**
 * Gera tokens com dados e prazo de validade, assinados com HMAC.
 * Quem recebe o token consegue ler os dados, mas não consegue alterá-los.
 */
final readonly class TokenAssinado
{
    private string $chave;
    private Mac $mac;

    public function __construct(#[SensitiveParameter] string $chaveSecreta)
    {
        if ($chaveSecreta === '') {
            throw new InvalidArgumentException('A chave secreta não foi configurada.');
        }

        // Uma subchave só para esta finalidade, derivada da chave principal
        $this->chave = hash_hkdf('sha256', $chaveSecreta, 32, 'token-assinado');
        $this->mac = new Mac();
    }

    public function gerar(array $dados, int $validadeEmSegundos): string
    {
        $dados['expira'] = time() + $validadeEmSegundos;
        $assinado = $this->mac->sign(json_encode($dados, JSON_THROW_ON_ERROR), $this->chave);

        return StringHelper::base64UrlEncode($assinado);
    }

    /**
     * Devolve os dados, ou null se o token foi alterado ou expirou.
     */
    public function ler(string $token): ?array
    {
        try {
            $json = $this->mac->getMessage(StringHelper::base64UrlDecode($token), $this->chave);
        } catch (DataIsTamperedException) {
            return null;
        }

        $dados = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $dados['expira'] >= time() ? $dados : null;
    }
}
