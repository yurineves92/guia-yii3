<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Assets;

use App\Web\Shared\Layout\Main\MainAsset;
use Yiisoft\Assets\AssetBundle;

final class EstruturaAsset extends AssetBundle
{
    public ?string $basePath = '@assets/estrutura';
    public ?string $baseUrl = '@assetsUrl/estrutura';
    public ?string $sourcePath = '@assetsSource/estrutura';

    public array $css = [
        'estrutura.css',
    ];

    public array $js = [
        'estrutura.js',
    ];

    public array $depends = [
        MainAsset::class,
    ];
}
