<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

namespace Nomen\Plugin\Search;

/**
 * 外部引擎只有几个请求，不引依赖；连不上就抛异常，由调用方退回数据库。
 */
final class Http
{
    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:mixed, raw:string}
     */
    public static function request(string $method, string $url, mixed $body = null, array $headers = [], int $timeout = 5): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('没有 curl 扩展');
        }

        $ch = curl_init($url);
        $headers[] = 'Accept: application/json';
        if ($body !== null && !isset($headers['Content-Type'])) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_values($headers),
            CURLOPT_USERAGENT      => 'Nomen-Search/1.0',
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($message !== '' ? $message : '请求失败');
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode((string)$raw, true), 'raw' => (string)$raw];
    }

    /** @param array<string, string> $headers */
    public static function json(string $method, string $url, mixed $body = null, array $headers = [], int $timeout = 5): array
    {
        $response = self::request($method, $url, $body, $headers, $timeout);
        if ($response['status'] >= 400) {
            throw new \RuntimeException($url . ' 回了 ' . $response['status'] . '：' . mb_substr($response['raw'], 0, 200));
        }

        return is_array($response['body']) ? $response['body'] : ['status' => $response['status']];
    }

    /** 去掉引擎返回的高亮标签，只留纯文本。 */
    public static function plain(string $html): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', strip_tags($html)));
    }
}
