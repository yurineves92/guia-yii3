<?php

declare(strict_types=1);

namespace App\Web\Formularios\Upload;

use Psr\Http\Message\UploadedFileInterface;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\File;
use Yiisoft\Validator\Rule\Image\Image;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class FotoForm extends FormModel
{
    #[Required(message: 'Escreva uma legenda.')]
    #[Length(max: 100, greaterThanMaxMessage: 'Use no máximo {max} caracteres.')]
    public string $legenda = '';

    #[File(
        extensions: ['jpg', 'jpeg', 'png'],
        mimeTypes: ['image/jpeg', 'image/png'],
        maxSize: 1024 * 1024,
        uploadRequiredMessage: 'Escolha uma foto.',
        uploadFailedMessage: 'O envio da foto falhou (código {error}).',
        wrongExtensionMessage: 'Envie um arquivo .jpg ou .png.',
        wrongMimeTypeMessage: 'O conteúdo do arquivo não é JPEG nem PNG.',
        tooBigMessage: 'A foto pode ter no máximo 1 MB.',
    )]
    #[Image(
        maxWidth: 4000,
        maxHeight: 4000,
        tooLargeWidthMessage: 'A foto pode ter no máximo {limit} pixels de largura.',
        tooLargeHeightMessage: 'A foto pode ter no máximo {limit} pixels de altura.',
        skipOnError: true,
    )]
    public ?UploadedFileInterface $foto = null;
}
