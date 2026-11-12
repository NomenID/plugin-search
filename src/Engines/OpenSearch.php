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
 * OpenSearch。Elasticsearch 7+ 的搜索 / 批量接口和它一致，所以直接复用这一个实现。
 */
class OpenSearch implements Engine
{
    /** @param array<string, mixed> $options */
    public function __construct(protected array $options)
    {
    }

    public function search(string $query, int $page, int $perPage): array
    {
        $data = Http::json('POST', $this->url('/' . rawurlencode($this->index()) . '/_search'), [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
            'query' => [
                'multi_match' => [
                    'query'  => $query,
                    'fields' => ['title^3', 'summary^2', 'content'],
                ],
            ],
            'highlight' => [
                'fields' => ['content' => ['fragment_size' => 160, 'number_of_fragments' => 1]],
            ],
        ], $this->headers());

        $items = [];
        foreach ((array)($data['hits']['hits'] ?? []) as $hit) {
            $source = (array)($hit['_source'] ?? []);
            $fragment = (string)($hit['highlight']['content'][0] ?? ($source['content'] ?? ''));
            $items[] = [
                'slug'    => (string)($source['slug'] ?? ($hit['_id'] ?? '')),
                'title'   => (string)($source['title'] ?? ''),
                'summary' => (string)($source['summary'] ?? ''),
                'excerpt' => Http::plain($fragment),
            ];
        }

        $total = $data['hits']['total'] ?? count($items);
        if (is_array($total)) {
            $total = $total['value'] ?? count($items);
        }

        return ['total' => (int)$total, 'items' => $items];
    }

    /** @param iterable<array<string, mixed>> $docs */
    public function reindex(iterable $docs, bool $fresh): int
    {
        $index = $this->index();

        if ($fresh) {
            try {
                Http::json('DELETE', $this->url('/' . rawurlencode($index)), null, $this->headers(), 10);
            } catch (\Throwable $e) {
                // 索引本来就不存在
            }
        }

        try {
            Http::json('PUT', $this->url('/' . rawurlencode($index)), [
                'mappings' => [
                    'properties' => [
                        'slug'    => ['type' => 'keyword'],
                        'title'   => ['type' => 'text'],
                        'summary' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                    ],
                ],
            ], $this->headers(), 10);
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'resource_already_exists')) {
                throw $e;
            }
        }

        $lines = [];
        $count = 0;
        foreach ($docs as $doc) {
            $lines[] = (string)json_encode(['index' => ['_index' => $index, '_id' => (string)($doc['slug'] ?? '')]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lines[] = (string)json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $count++;

            if (count($lines) >= 1000) {
                $this->bulk($lines);
                $lines = [];
            }
        }
        if ($lines !== []) {
            $this->bulk($lines);
        }

        return $count;
    }

    /** @param array<int, string> $lines */
    private function bulk(array $lines): void
    {
        Http::json('POST', $this->url('/_bulk'), implode("\n", $lines) . "\n", array_merge($this->headers(), [
            'Content-Type: application/x-ndjson',
        ]), 30);
    }

    protected function index(): string
    {
        $index = trim((string)($this->options['index'] ?? 'nomen_pages'));

        return $index !== '' ? $index : 'nomen_pages';
    }

    protected function url(string $path): string
    {
        return rtrim((string)($this->options['host'] ?? 'http://127.0.0.1:9200'), '/') . $path;
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $user = trim((string)($this->options['username'] ?? ''));
        $pass = (string)($this->options['password'] ?? '');

        return $user === '' ? [] : ['Authorization: Basic ' . base64_encode($user . ':' . $pass)];
    }
}
