<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

use Nomen\Src\Api as CoreApi;
use Nomen\Src\Plugins;
use Nomen\Src\Url;

/**
 * 接到 /nomen-api：list=search。返回和页面一样的顺序，地址给站点内路径。
 */
final class Api
{
    public static function boot(): void
    {
        CoreApi::list('search', [self::class, 'run'], 'public', '搜索已发布的页面（q / page / limit）');
    }

    public static function run(array $params): array
    {
        $query = trim((string)($params['q'] ?? ''));
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(50, (int)($params['limit'] ?? 10)));

        $result = Search::query($query, $page, $limit);
        // 文档地址走 Pages 的挂载点，站长改路径后这里跟着变
        $pagesBase = rtrim((string)Plugins::mountUrl('pages'), '/');

        $items = [];
        foreach ($result['items'] as $item) {
            $items[] = [
                'slug'    => $item['slug'],
                'title'   => $item['title'],
                'summary' => $item['summary'],
                'excerpt' => $item['excerpt'],
                'url'     => Url::to($pagesBase . '/' . $item['slug']),
            ];
        }

        $total = (int)$result['total'];
        $pages = (int)ceil($total / $limit);

        $data = [
            'search' => [
                'query'  => $query,
                'engine' => $result['engine'],
                'total'  => $total,
                'results' => $items,
            ],
        ];

        if ($page < $pages) {
            $data['continue'] = ['page' => $page + 1];
        }

        return $data;
    }
}
