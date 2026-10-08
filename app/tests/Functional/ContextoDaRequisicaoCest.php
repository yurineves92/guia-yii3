<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Environment;
use App\Topicos\LongaDuracao\ContextoDaRequisicao;
use Yiisoft\Di\StateResetter;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function dirname;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class ContextoDaRequisicaoCest
{
    public function resetLimpaOEstado(): void
    {
        $runner = new HttpApplicationRunner(
            rootPath: dirname(__DIR__, 2),
            environment: Environment::appEnv(),
        );
        $container = $runner->getContainer();

        $contexto = $container->get(ContextoDaRequisicao::class);
        $contexto->definirId('req-1');

        // O mesmo objeto volta do container: o estado sobrevive entre requisições
        assertSame('req-1', $container->get(ContextoDaRequisicao::class)->id());

        // É isto que os runners de longa duração chamam ao fim de cada requisição
        $container->get(StateResetter::class)->reset();

        assertNull($contexto->id());
    }
}
