<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

use Nomen\Src\{Plugins, Response, Url};

class Controller
{
    public static function page(): void
    {
        $base = Plugin::basePath();
        $query = trim((string)($_GET['q'] ?? ''));
        $requested = max(1, (int)($_GET['page'] ?? 1));

        $result = Search::query($query, $requested);
        $perPage = max(1, (int)$result['per_page']);
        $total = (int)$result['total'];
        $pages = (int)ceil($total / $perPage);
        $current = min(max(1, $requested), max(1, $pages));

        $pagesBase = Plugins::mountUrl('pages') ?? '/w';
        $items = [];
        foreach ($result['items'] as $item) {
            $item['url'] = Url::to(rtrim($pagesBase, '/') . '/' . $item['slug']);
            $items[] = $item;
        }

        $result['items'] = $items;
        $result['page'] = $current;
        $result['pages'] = $pages;

        ob_start();
        require __DIR__ . '/../templates/search.php';
        $body = (string)ob_get_clean();

        Response::view('special', [
            'title'     => t('Search'),
            'hideTitle' => true,
            'pageClass' => 'special-page--search',
            'body'      => $body,
            'extraCss'  => [Plugins::assetUrl('search', 'search.css')],
        ]);
    }
}
