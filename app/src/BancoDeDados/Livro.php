<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Livro extends ActiveRecord
{
    public int $id;
    public int $autor_id;
    public string $titulo;
    public string $slug;
    public int $ano;
    public string $preco;
    public DateTimeImmutable $cadastrado_em;

    public function tableName(): string
    {
        return 'livro';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'autor' => $this->getAutorQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getAutor(): Autor
    {
        return $this->relation('autor');
    }

    public function getAutorQuery(): ActiveQuery
    {
        return $this->hasOne(Autor::class, ['id' => 'autor_id']);
    }
}
