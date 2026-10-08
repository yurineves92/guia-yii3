<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

final class Cartao extends Widget
{
    private string $titulo = '';

    public function titulo(string $titulo): self
    {
        $new = clone $this;
        $new->titulo = $titulo;
        return $new;
    }

    public function begin(): ?string
    {
        parent::begin();
        ob_start();

        return null;
    }

    public function render(): string
    {
        $conteudo = (string) ob_get_clean();

        return Html::div(Html::h2($this->titulo) . $conteudo)
            ->class('cartao')
            ->encode(false)
            ->render();
    }
}
