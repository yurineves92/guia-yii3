<?php

declare(strict_types=1);

namespace App\Cache;

use DateTimeImmutable;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\TagDependency;
use Yiisoft\Cache\Ttl;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class ResumoDePaises
{
    private const TAG = 'paises';

    public function __construct(
        private CacheInterface $cache,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return array{total: int, populacao: int, maiores: list<string>, geradoEm: string}
     */
    public function obter(): array
    {
        return $this->cache->getOrSet(
            ['resumo-de-paises'],
            fn () => $this->calcular(),
            Ttl::minutes(10)->toSeconds(),
            new TagDependency(self::TAG),
        );
    }

    public function invalidar(): void
    {
        TagDependency::invalidate($this->cache, self::TAG);
    }

    private function calcular(): array
    {
        $totais = $this->db->select(['total' => 'COUNT(*)', 'populacao' => 'SUM(populacao)'])
            ->from('pais')
            ->one();

        $maiores = $this->db->select('nome')
            ->from('pais')
            ->orderBy(['populacao' => SORT_DESC])
            ->limit(3)
            ->column();

        return [
            'total' => (int) $totais['total'],
            'populacao' => (int) $totais['populacao'],
            'maiores' => $maiores,
            'geradoEm' => (new DateTimeImmutable())->format('H:i:s'),
        ];
    }
}
