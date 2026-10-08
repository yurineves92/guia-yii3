<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Assets;

use Yiisoft\Assets\AssetBundle;

final class AlpineAsset extends AssetBundle
{
    public bool $cdn = true;

    public array $js = [
        ['https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js', 'defer' => true],
    ];
}
