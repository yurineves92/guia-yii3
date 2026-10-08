<?php

declare(strict_types=1);

namespace App\Web\Topicos\I18n;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

use function in_array;

final readonly class Action
{
    private const IDIOMAS = ['en-US', 'pt-BR', 'es'];

    public function __construct(
        private WebViewRenderer $viewRenderer,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(#[Query('idioma')] ?string $idioma = null): ResponseInterface
    {
        $translator = $this->translator->withDefaultCategory('topicos');

        if ($idioma !== null && in_array($idioma, self::IDIOMAS, true)) {
            $translator = $translator->withLocale($idioma);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            't' => $translator,
            'idiomas' => self::IDIOMAS,
            'hoje' => new DateTimeImmutable(),
        ]);
    }
}
