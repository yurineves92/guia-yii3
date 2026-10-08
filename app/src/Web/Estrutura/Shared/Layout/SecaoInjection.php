<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Shared\Layout;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\LayoutParametersInjectionInterface;
use Yiisoft\Yii\View\Renderer\MetaTagsInjectionInterface;

final readonly class SecaoInjection implements LayoutParametersInjectionInterface, MetaTagsInjectionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getLayoutParameters(): array
    {
        return [
            'secao' => 'Estrutura da aplicação',
            'menu' => [
                'Views' => $this->urlGenerator->generate('estrutura/views'),
                'Artigos' => $this->urlGenerator->generate('estrutura/artigos'),
                'Contato' => $this->urlGenerator->generate('estrutura/contato'),
            ],
        ];
    }

    public function getMetaTags(): array
    {
        return [
            'description' => [
                'name' => 'description',
                'content' => 'Exemplos da seção Estrutura da aplicação',
            ],
        ];
    }
}
