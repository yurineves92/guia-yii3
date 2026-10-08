# Sobre o Yii3

O Yii é um framework PHP para aplicações web, APIs e aplicações de console. O Yii3 é a terceira geração dele, e foi reescrito do zero. Ele não é uma atualização do Yii2: os conceitos principais mudaram, e código do Yii2 não roda no Yii3.

## Um conjunto de pacotes

A maior diferença em relação às versões anteriores é que o Yii3 não é um pacote só. Ele é uma coleção de pacotes pequenos, todos com o prefixo `yiisoft/`, e cada um faz uma coisa:

- `yiisoft/router`: rotas e geração de URLs
- `yiisoft/di`: container de injeção de dependência
- `yiisoft/db`: acesso a banco de dados e query builder
- `yiisoft/validator`: validação de dados
- `yiisoft/view`: renderização de views
- `yiisoft/config`: montagem da configuração

Quem junta tudo isso numa aplicação é o template `yiisoft/app`. Você parte dele e adiciona os pacotes de que precisa com o Composer. Cada pacote também funciona sozinho, fora do Yii, em qualquer projeto PHP.

## Baseado em padrões PSR

O Yii3 segue as recomendações do PHP-FIG (as PSRs) em todos os pontos de integração:

- PSR-7 para requisições e respostas HTTP
- PSR-11 para o container de dependências
- PSR-15 para middlewares e handlers
- PSR-3 para logs, PSR-14 para eventos e PSR-16 para cache

Na prática, isso quer dizer que você pode usar bibliotecas de outros ecossistemas junto com o Yii3, e que o que você aprende aqui vale para outros frameworks que seguem as mesmas PSRs.

## Sem estado global

No Yii2, quase tudo passava por `Yii::$app`: `Yii::$app->db`, `Yii::$app->request`, `Yii::$app->user`. No Yii3 isso não existe. Cada classe recebe pelo construtor tudo de que precisa, e o container de DI monta os objetos.

```php
final readonly class PostRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}
}
```

Isso deixa as dependências de cada classe visíveis e facilita testar. Também permite rodar a aplicação em servidores de longa duração, como RoadRunner, onde os mesmos objetos atendem várias requisições.

## Para que serve

O Yii3 serve para os mesmos tipos de projeto que o Yii2: sites, painéis administrativos, APIs REST e aplicações de console. Por ser modular, ele também funciona bem como base para microsserviços, já que você instala só o que vai usar.

## Requisitos

O template `yiisoft/app` pede PHP 8.2 ou mais novo e o Composer. Os detalhes estão em [Instalando o Yii3](/primeiros-passos/instalando).

## Sobre este guia

Este guia é independente e não é a documentação oficial do Yii. A documentação oficial, em inglês, fica em [yiisoft.github.io/docs](https://yiisoft.github.io/docs/guide/). A estrutura deste guia segue a do [guia do Yii2](https://www.yiiframework.com/doc/guide/2.0/pt-br), para quem já conhece o caminho.

Todo exemplo de código aqui vem do app que está na pasta `app/` do repositório e foi executado antes de ir para a página.
