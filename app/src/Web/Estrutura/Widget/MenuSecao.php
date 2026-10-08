<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Widget\Widget;

final class MenuSecao extends Widget
{
    /**
     * @var array<string, string> Rótulo => nome da rota.
     */
    private array $itens = [];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CurrentRoute $currentRoute,
    ) {}

    /**
     * @param array<string, string> $itens Rótulo => nome da rota.
     */
    public function itens(array $itens): self
    {
        $new = clone $this;
        $new->itens = $itens;
        return $new;
    }

    public function render(): string
    {
        $links = [];

        foreach ($this->itens as $rotulo => $rota) {
            $links[] = Html::a($rotulo, $this->urlGenerator->generate($rota))
                ->addClass($rota === $this->currentRoute->getName() ? 'ativo' : null)
                ->render();
        }

        return Html::nav(implode(' ', $links))->encode(false)->render();
    }
}
