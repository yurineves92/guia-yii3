<?php

declare(strict_types=1);

use App\Web\Estrutura\Assets\AlpineAsset;
use App\Web\Estrutura\Assets\EstruturaAsset;
use App\Web\Estrutura\Widget\Alerta;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Assets\AssetManager $assetManager
 */

$assetManager->register(EstruturaAsset::class);
$assetManager->register(AlpineAsset::class);

$this->setTitle('Assets');
?>

<h1>Assets</h1>

<?= Alerta::widget()->tipo('sucesso')->mensagem('Clique para fechar este alerta.') ?>

<div class="cartao" x-data="{ aberto: false }">
    <button type="button" @click="aberto = !aberto">Mostrar detalhes</button>
    <p x-show="aberto">Este trecho usa o Alpine.js, carregado de uma CDN.</p>
</div>
