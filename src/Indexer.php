<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

use Nomen\Src\Database;

/**
 * `php bin/nomen plugins search:reindex`：把已发布的页面推给外部引擎。
 * 数据库引擎不需要这一步 —— 它直接查 pages 表。
 */
final class Indexer
{
    public static function run(): int
    {
        $args = [];
        foreach ($_SERVER['argv'] ?? [] as $i => $value) {
            if ($value === 'search:reindex') {
                $args = array_slice($_SERVER['argv'], $i + 1);
                break;
            }
        }

        $settings = Search::settings();

        if ($settings['engine'] === 'db') {
            fwrite(STDOUT, '当前引擎是数据库（engine=db），不需要建索引。要换引擎改 config/plugins/search.json。' . PHP_EOL);
            return 0;
        }

        $fresh = in_array('--fresh', $args, true);
        $docs = self::documents();

        try {
            $count = Search::engine($settings)->reindex($docs, $fresh);
        } catch (\Throwable $e) {
            fwrite(STDERR, $settings['engine'] . ' 连不上或报错：' . $e->getMessage() . PHP_EOL);
            fwrite(STDERR, '先确认服务在跑、host / key 填对，再重试。' . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, sprintf(
            '已推送 %d 篇到 %s（index=%s）%s',
            $count,
            $settings['engine'],
            (string)($settings['options']['index'] ?? 'nomen_pages'),
            PHP_EOL
        ));

        return 0;
    }

    /** @return \Generator<array<string, string>> */
    private static function documents(): \Generator
    {
        $db = Database::getInstance();

        try {
            $stmt = $db->query('SELECT slug, title, summary, body FROM pages WHERE status = 1 ORDER BY id');
        } catch (\Throwable $e) {
            fwrite(STDERR, '读不到 pages 表：Pages 插件没启用？' . PHP_EOL);
            return;
        }

        foreach ($stmt->fetchAll() as $row) {
            yield [
                'slug'    => (string)$row['slug'],
                'title'   => (string)$row['title'],
                'summary' => (string)$row['summary'],
                'content' => mb_substr(Engines\Database::plain((string)$row['body']), 0, 20000),
            ];
        }
    }
}
