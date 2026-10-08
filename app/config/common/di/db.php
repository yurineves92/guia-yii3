<?php

declare(strict_types=1);

use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

return [
    SchemaCache::class => [
        '__construct()' => [new ArrayCache()],
    ],

    ConnectionInterface::class => static fn (Aliases $aliases, SchemaCache $schemaCache) => new Connection(
        new Driver('sqlite:' . $aliases->get('@runtime/database.sqlite')),
        $schemaCache,
    ),
];
