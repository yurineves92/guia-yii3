<?php

declare(strict_types=1);

use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Cache\Ttl;

return [
    CacheInterface::class => static fn (FileCache $fileCache) => new Cache($fileCache, Ttl::hours(1)),
];
