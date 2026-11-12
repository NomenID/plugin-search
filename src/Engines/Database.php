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
 * 默认引擎：直接对 pages 表做 LIKE。没有索引服务也能搜，小站够用。
 */
final class Database implements Engine
{
    public function search(string $query, int $page, int $perPage): array
    {
        $db = \Nomen\Src\Database::getInstance();

        if (!$this->hasPagesTable($db)) {
            return ['total' => 0, 'items' => []];
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $where = "status = 1 AND (title LIKE :like ESCAPE '\\' OR summary LIKE :like ESCAPE '\\' OR body LIKE :like ESCAPE '\\')";

        $count = $db->prepare('SELECT COUNT(*) FROM pages WHERE ' . $where);
        $count->execute([':like' => $like]);
        $total = (int)$count->fetchColumn();

        if ($total === 0) {
            return ['total' => 0, 'items' => []];
        }

        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare(
            'SELECT slug, title, summary, body FROM pages WHERE ' . $where
            . ' ORDER BY CASE WHEN title LIKE :like ESCAPE \'\\\' THEN 0 ELSE 1 END, sort_order, title'
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute([':like' => $like]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'slug'    => (string)$row['slug'],
                'title'   => (string)$row['title'],
                'summary' => (string)$row['summary'],
                'excerpt' => self::excerpt((string)$row['body'], $query),
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    private function hasPagesTable(\PDO $db): bool
    {
        try {
            $db->query('SELECT 1 FROM pages LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /** 正文是 Markdown，摘要先把标记去掉，再取关键词附近的一段。 */
    public static function excerpt(string $body, string $query, int $radius = 90): string
    {
        $text = self::plain($body);

        if ($text === '') {
            return '';
        }

        $at = mb_stripos($text, $query);
        if ($at === false) {
            return mb_strlen($text) > $radius * 2 ? mb_substr($text, 0, $radius * 2) . '…' : $text;
        }

        $start = max(0, $at - $radius);
        $length = mb_strlen($query) + $radius * 2;
        $excerpt = mb_substr($text, $start, $length);

        return ($start > 0 ? '…' : '') . $excerpt . ($start + $length < mb_strlen($text) ? '…' : '');
    }

    /** Markdown 去标记，只留可读文本（外部引擎索引的 content 也用它）。 */
    public static function plain(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/[*_>`~]+/', ' ', $text) ?? $text;

        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }
}
