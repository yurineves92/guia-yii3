<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Autor extends ActiveRecord
{
    public int $id;
    public string $nome;

    public function tableName(): string
    {
        return 'autor';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'livros' => $this->getLivrosQuery(),
            default => parent::relationQuery($name),
        };
    }

    /**
     * @return Livro[]
     */
    public function getLivros(): array
    {
        return $this->relation('livros');
    }

    public function getLivrosQuery(): ActiveQuery
    {
        return $this->hasMany(Livro::class, ['autor_id' => 'id'])->orderBy(['ano' => SORT_ASC]);
    }
}
