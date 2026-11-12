<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search\Engines;

/**
 * Elasticsearch 7+。搜索、批量写入和建索引的接口和 OpenSearch 相同，这里只换默认端口。
 */
final class Elasticsearch extends OpenSearch
{
    protected function url(string $path): string
    {
        return rtrim((string)($this->options['host'] ?? 'http://127.0.0.1:9200'), '/') . $path;
    }
}
