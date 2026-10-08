<?php

declare(strict_types=1);

namespace App\Web\Dados\Filtros;

use App\Dados\Catalogo;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Reader\Filter\AndX;
use Yiisoft\Data\Reader\Filter\Equals;
use Yiisoft\Data\Reader\Filter\LessThanOrEqual;
use Yiisoft\Data\Reader\Filter\Like;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    private const array NASCIMENTOS = [
        ['nome' => 'Machado de Assis', 'cidade' => 'Rio de Janeiro', 'nascimento' => 1839],
        ['nome' => 'Graciliano Ramos', 'cidade' => 'Quebrangulo', 'nascimento' => 1892],
        ['nome' => 'Cecília Meireles', 'cidade' => 'Rio de Janeiro', 'nascimento' => 1901],
        ['nome' => 'Jorge Amado', 'cidade' => 'Itabuna', 'nascimento' => 1912],
        ['nome' => 'Clarice Lispector', 'cidade' => 'Chechelnyk', 'nascimento' => 1920],
    ];

    public function __construct(
        private WebViewRenderer $viewRenderer,
        private Catalogo $catalogo,
    ) {}

    public function __invoke(
        #[Query('busca')] string $busca = '',
        #[Query('autor')] string $autor = '',
        #[Query('preco_max')] string $precoMaximo = '',
    ): ResponseInterface {
        $filtros = [];

        if ($busca !== '') {
            $filtros[] = new Like('titulo', $busca);
        }
        if (ctype_digit($autor)) {
            $filtros[] = new Equals('autorId', (int) $autor);
        }
        if (is_numeric($precoMaximo)) {
            $filtros[] = new LessThanOrEqual('preco', (float) $precoMaximo);
        }

        $livros = $this->catalogo->livros()->withFilter(new AndX(...$filtros));

        $nascimentos = (new IterableDataReader(self::NASCIMENTOS))
            ->withSort(Sort::only(['nascimento', 'nome'])->withOrderString('-nascimento'));

        if ($busca !== '') {
            $nascimentos = $nascimentos->withFilter(new Like('nome', $busca));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'busca' => $busca,
            'autor' => $autor,
            'precoMaximo' => $precoMaximo,
            'autores' => $this->catalogo->autores(),
            'livros' => $livros->read(),
            'total' => $livros->count(),
            'nascimentos' => $nascimentos->read(),
        ]);
    }
}
