<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search\Engines;

use Nomen\Plugin\Search\Http;

/**
 * Meilisearch。站长要起一个 Meili 实例，把 host / api_key / index 填进配置。
 */
final class Meilisearch implements Engine
{
    /** @param array<string, mixed> $options */
    public function __construct(private array $options)
    {
    }

    public function search(string $query, int $page, int $perPage): array
    {
        $data = Http::json('POST', $this->url('/indexes/' . rawurlencode($this->index()) . '/search'), [
            'q'                   => $query,
            'limit'               => $perPage,
            'offset'              => ($page - 1) * $perPage,
            'attributesToRetrieve' => ['slug', 'title', 'summary', 'content'],
            'attributesToHighlight' => ['content'],
        ], $this->headers());

        $items = [];
        foreach ((array)($data['hits'] ?? []) as $hit) {
            $excerpt = (string)($hit['_formatted']['content'] ?? ($hit['content'] ?? ''));
            $items[] = [
                'slug'    => (string)($hit['slug'] ?? ''),
                'title'   => (string)($hit['title'] ?? ''),
                'summary' => (string)($hit['summary'] ?? ''),
                'excerpt' => Http::plain($excerpt),
            ];
        }

        return [
            'total' => (int)($data['estimatedTotalHits'] ?? $data['totalHits'] ?? count($items)),
            'items' => $items,
        ];
    }

    /** @param iterable<array<string, mixed>> $docs */
    public function reindex(iterable $docs, bool $fresh): int
    {
        $index = $this->index();

        // 先确保索引在（已存在时 Meili 回 409，忽略）
        try {
            Http::json('PUT', $this->url('/indexes/' . rawurlencode($index)), [
                'uid'        => $index,
                'primaryKey' => 'slug',
            ], $this->headers(), 10);
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), '409')) {
                throw $e;
            }
        }

        if ($fresh) {
            Http::json('DELETE', $this->url('/indexes/' . rawurlencode($index) . '/documents'), null, $this->headers(), 10);
        }

        $batch = [];
        $count = 0;
        foreach ($docs as $doc) {
            $batch[] = $doc;
            if (count($batch) >= 200) {
                $count += $this->push($index, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $count += $this->push($index, $batch);
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $batch */
    private function push(string $index, array $batch): int
    {
        Http::json(
            'POST',
            $this->url('/indexes/' . rawurlencode($index) . '/documents?primaryKey=slug'),
            $batch,
            $this->headers(),
            30
        );

        return count($batch);
    }

    private function index(): string
    {
        $index = trim((string)($this->options['index'] ?? 'nomen_pages'));

        return $index !== '' ? $index : 'nomen_pages';
    }

    private function url(string $path): string
    {
        return rtrim((string)($this->options['host'] ?? 'http://127.0.0.1:7700'), '/') . $path;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $key = trim((string)($this->options['api_key'] ?? ''));

        return $key === '' ? [] : ['Authorization: Bearer ' . $key];
    }
}
