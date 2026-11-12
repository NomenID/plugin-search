<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

use Nomen\Src\{Cache, Plugins};

final class Search
{
    public const ENGINES = ['db', 'meilisearch', 'opensearch', 'elasticsearch'];

    /**
     * 配置来自 config/plugins/search.json；缺项用数据库引擎，装了就能用，不填任何东西。
     *
     * @return array{engine:string, per_page:int, cache_ttl:int, options:array}
     */
    public static function settings(): array
    {
        $config = Plugins::config('search', []);

        $engine = strtolower(trim((string)($config['engine'] ?? 'db')));
        if (!in_array($engine, self::ENGINES, true)) {
            $engine = 'db';
        }

        return [
            'engine'    => $engine,
            'per_page'  => max(1, min(50, (int)($config['per_page'] ?? 10))),
            'cache_ttl' => max(0, (int)($config['cache_ttl'] ?? 300)),
            'options'   => is_array($config[$engine] ?? null) ? $config[$engine] : [],
        ];
    }

    /**
     * @return array{engine:string, query:string, total:int, page:int, pages:int, per_page:int, items:array, took:int}
     */
    public static function query(string $query, int $page, ?int $perPage = null): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $settings = self::settings();
        $perPage = $perPage !== null ? max(1, min(50, $perPage)) : $settings['per_page'];

        if ($query === '') {
            return self::empty($settings['engine'], $query, $perPage);
        }

        $run = static fn(): array => self::run($query, $page, $perPage, $settings);

        if ($settings['cache_ttl'] === 0) {
            return $run();
        }

        $key = 'search:' . sha1($settings['engine'] . "\0" . $query . "\0" . $page . "\0" . $perPage);

        return Cache::remember($key, $settings['cache_ttl'], $run);
    }

    /** @param array{engine:string, per_page:int, cache_ttl:int, options:array} $settings */
    private static function run(string $query, int $page, int $perPage, array $settings): array
    {
        $started = microtime(true);

        if ($settings['engine'] === 'db') {
            $result = (new Engines\Database())->search($query, $page, $perPage);
        } else {
            try {
                $result = self::engine($settings)->search($query, $page, $perPage);
            } catch (\Throwable $e) {
                // 外部引擎不通时退回数据库：搜索框不该因为索引服务挂了就 500
                error_log('[search] ' . $settings['engine'] . ' 不可用，退回数据库：' . $e->getMessage());
                $result = (new Engines\Database())->search($query, $page, $perPage);
            }
        }

        $result['engine'] = $settings['engine'];
        $result['query'] = $query;
        $result['per_page'] = $perPage;
        $result['page'] = $page;
        $result['took'] = (int)round((microtime(true) - $started) * 1000);

        return $result;
    }

    private static function empty(string $engine, string $query, int $perPage): array
    {
        return [
            'engine'   => $engine,
            'query'    => $query,
            'total'    => 0,
            'page'     => 1,
            'pages'    => 0,
            'per_page' => $perPage,
            'items'    => [],
            'took'     => 0,
        ];
    }

    /** @param array{engine:string, per_page:int, cache_ttl:int, options:array} $settings */
    public static function engine(array $settings): Engines\Engine
    {
        return match ($settings['engine']) {
            'meilisearch'   => new Engines\Meilisearch($settings['options']),
            'opensearch'    => new Engines\OpenSearch($settings['options']),
            'elasticsearch' => new Engines\Elasticsearch($settings['options']),
            default         => new Engines\Database(),
        };
    }

    /** 页面地址；没伪静态时也走 Url::to() 拿到 index.php?page= 形式 */
    public static function pageUrl(string $base, string $query, int $page): string
    {
        $params = [];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }

        return \Nomen\Src\Url::to($base, $params);
    }
}
