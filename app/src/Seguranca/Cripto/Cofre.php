<?php

declare(strict_types=1);

namespace App\Seguranca\Cripto;

use InvalidArgumentException;
use SensitiveParameter;
use Yiisoft\Security\AuthenticationException;
use Yiisoft\Security\Crypt;

/**
 * Cifra textos para guardar no banco e decifra quando precisar usar.
 */
final readonly class Cofre
{
    private Crypt $crypt;

    public function __construct(
        #[SensitiveParameter]
        private string $chaveSecreta,
    ) {
        if ($chaveSecreta === '') {
            throw new InvalidArgumentException('A chave secreta não foi configurada.');
        }

        $this->crypt = new Crypt('AES-256-CBC');
    }

    public function cifrar(#[SensitiveParameter] string $texto): string
    {
        return base64_encode($this->crypt->encryptByKey($texto, $this->chaveSecreta, 'cofre'));
    }

    /**
     * @throws AuthenticationException se o conteúdo foi alterado ou a chave é outra
     */
    public function decifrar(string $cifrado): string
    {
        return $this->crypt->decryptByKey((string) base64_decode($cifrado, true), $this->chaveSecreta, 'cofre');
    }
}
