<?php

declare(strict_types=1);

namespace App\Formularios\Upload;

use finfo;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Yiisoft\Aliases\Aliases;

final readonly class ArmazenamentoDeFotos
{
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private Aliases $aliases,
    ) {}

    /**
     * Move o arquivo enviado para public/uploads/fotos e devolve a URL pública.
     */
    public function salvar(UploadedFileInterface $arquivo): string
    {
        $caminhoTemporario = (string) $arquivo->getStream()->getMetadata('uri');
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($caminhoTemporario);
        $extensao = self::EXTENSOES[$tipo] ?? throw new RuntimeException("Tipo de arquivo não permitido: $tipo");

        $nome = bin2hex(random_bytes(16)) . '.' . $extensao;
        $diretorio = $this->aliases->get('@public/uploads/fotos');

        if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
            throw new RuntimeException("Não foi possível criar o diretório $diretorio");
        }

        $arquivo->moveTo($diretorio . '/' . $nome);

        return $this->aliases->get('@baseUrl/uploads/fotos/' . $nome);
    }
}
