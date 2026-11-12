<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search\Engines;

interface Engine
{
    /**
     * @return array{total:int, items:array<int, array{slug:string, title:string, summary:string, excerpt:string}>}
     */
    public function search(string $query, int $page, int $perPage): array;
}
