# Instalando o Yii3

O jeito mais rápido de começar é criar um projeto a partir do template oficial, o `yiisoft/app`. Ele já vem com roteamento, views, sessão, proteção CSRF, tratamento de erros e uma estrutura de pastas pronta.

## Requisitos

- PHP 8.2 ou mais novo (o template aceita de 8.2 a 8.5)
- [Composer](https://getcomposer.org/)
- As extensões `mbstring`, `intl` e `pdo`. A `pdo_sqlite` também, se você for seguir os exemplos de banco deste guia.

Para conferir sua versão e as extensões:

```bash
php -v
php -m
```

## Criando o projeto

```bash
composer create-project yiisoft/app meu-app
cd meu-app
cp .env.example .env
```

O arquivo `.env` define o ambiente. O que vem no exemplo deixa o app em modo de desenvolvimento:

```ini
APP_ENV=dev
APP_DEBUG=true
```

Sem o `.env`, o app assume o ambiente de produção e mostra uma página de erro genérica no lugar dos detalhes. Em produção, defina essas variáveis no servidor ou no container, não num arquivo.

## Subindo o servidor

```bash
./yii serve
```

Abra http://127.0.0.1:8080 e você vai ver a página de boas-vindas do template. No Windows, use `yii.bat serve`.

O `serve` usa o servidor embutido do PHP. Ele é ótimo para desenvolver, mas não serve para produção. Para mudar a porta, use `./yii serve --port=8081`.

## Usando Docker

O template também traz uma configuração Docker em `docker/` e um `Makefile` com os comandos mais comuns. Num sistema com `make` (Linux, macOS ou WSL):

```bash
make build
make up
```

O app sobe em http://localhost. Para rodar comandos dentro do container, use `make yii` ou `make shell`. Os detalhes estão em [Docker](/topicos/docker).

## Conferindo a instalação

Se a página de boas-vindas apareceu, está tudo certo. Para ver os comandos de console disponíveis:

```bash
./yii list
```

No próximo capítulo, vamos olhar o que o template criou e como uma requisição chega até o código.
