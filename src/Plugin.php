<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

use Nomen\Src\Plugins;

class Plugin
{
    public static function boot(): void
    {
        $base = self::basePath();

        Plugins::route('GET', $base, [Controller::class, 'page']);

        Api::boot();

        Plugins::command(
            'search:reindex',
            [Indexer::class, 'run'],
            '把已发布的页面推进外部搜索索引：search:reindex [--fresh]'
        );
    }

    /** 搜索页地址。站长在 config/plugins/search.json 里改。 */
    public static function basePath(): string
    {
        $config = Plugins::config('search', []);
        $base = trim((string)($config['base_path'] ?? '/search'));

        if ($base === '' || $base === '/') {
            $base = '/search';
        }

        return '/' . trim($base, '/');
    }
}
