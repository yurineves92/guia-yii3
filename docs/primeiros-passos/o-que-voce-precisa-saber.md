# O que você precisa saber

O Yii3 é simples de começar, mas ele assume que você já está confortável com PHP moderno. Antes de seguir, vale conferir se estes assuntos estão tranquilos para você.

## PHP orientado a objetos

Classes, interfaces, namespaces e `use`. Quase todo arquivo de um app Yii3 é uma classe, e as dependências entre elas são declaradas por interfaces.

Alguns recursos do PHP 8 aparecem o tempo todo:

- **Promoção de propriedades no construtor**: `public function __construct(private ConnectionInterface $db) {}`
- **Classes `readonly`**: muitos serviços são `final readonly class`
- **Atributos**: as regras de validação são escritas como `#[Required]` e `#[Length(max: 200)]`
- **Argumentos nomeados**: `new Length(min: 3, max: 200)`

Se algum desses parece estranho, a [documentação do PHP](https://www.php.net/manual/pt_BR/language.oop5.php) cobre todos.

## Composer

Você instala o Yii3 e todos os pacotes dele com o [Composer](https://getcomposer.org/). Vai usar basicamente três comandos:

```bash
composer create-project yiisoft/app meu-app   # cria um projeto novo
composer require yiisoft/db-sqlite            # adiciona um pacote
composer install                              # instala o que está no composer.lock
```

O autoload também é do Composer: a pasta `src/` corresponde ao namespace `App\`, seguindo a PSR-4.

## HTTP

Ajuda saber o básico de HTTP: métodos (GET, POST), status (200, 302, 404, 422), headers e corpo. No Yii3 você trabalha direto com objetos de requisição e resposta (PSR-7), sem uma camada que esconda isso.

## Injeção de dependência

Se você nunca usou um container de DI, a ideia é: em vez de a classe criar ou buscar o que precisa, ela recebe pronto no construtor. Quem monta tudo é o container. O capítulo [Container de DI](/conceitos/container-di) aprofunda, mas para os primeiros passos isso já basta.

## Terminal

Os comandos do Yii3 rodam no terminal: `./yii serve` sobe o servidor de desenvolvimento, e `./yii migrate:up` aplica as migrations. No Windows, use `yii.bat` no lugar de `./yii`.
