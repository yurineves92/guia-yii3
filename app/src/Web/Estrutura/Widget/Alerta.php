<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

final class Alerta extends Widget
{
    private string $tipo = 'info';
    private string $mensagem = '';

    public function tipo(string $tipo): self
    {
        $new = clone $this;
        $new->tipo = $tipo;
        return $new;
    }

    public function mensagem(string $mensagem): self
    {
        $new = clone $this;
        $new->mensagem = $mensagem;
        return $new;
    }

    public function render(): string
    {
        if ($this->mensagem === '') {
            return '';
        }

        return Html::div($this->mensagem)
            ->class('alerta', 'alerta-' . $this->tipo)
            ->render();
    }
}
