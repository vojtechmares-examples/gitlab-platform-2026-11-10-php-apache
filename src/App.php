<?php

declare(strict_types=1);

namespace Examples;

use Carbon\CarbonImmutable;

/** A tiny application: enough routes to see which build runs where. No framework, no database. */
final class App
{
    public function __construct(
        private readonly string $environment,
        private readonly string $buildFile,
        private readonly ?CarbonImmutable $now = null,
    ) {
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function handle(string $method, string $path): array
    {
        if ($method !== 'GET') {
            return $this->json(405, ['error' => 'method not allowed']);
        }

        return match ($path) {
            '/' => $this->page(),
            '/health' => $this->json(200, ['status' => 'ok']),
            '/info' => $this->json(200, $this->info()),
            default => $this->json(404, ['error' => 'not found', 'path' => $path]),
        };
    }

    /** @return array<string, mixed> */
    public function info(): array
    {
        $build = is_file($this->buildFile)
            ? json_decode((string) file_get_contents($this->buildFile), true, flags: JSON_THROW_ON_ERROR)
            : ['build' => 'local', 'version' => 'dev'];

        return [
            'application' => 'php-apache',
            'version' => $build['version'],
            'build' => $build['build'],
            'environment' => $this->environment,
            'php' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'time' => ($this->now ?? CarbonImmutable::now('UTC'))->toIso8601String(),
        ];
    }

    private function page(): array
    {
        $info = $this->info();
        $html = sprintf(
            '<!doctype html><html lang="en"><meta charset="utf-8"><title>php-apache</title>'
            . '<body style="font:18px system-ui;max-width:40rem;margin:3rem auto"><h1>php-apache</h1>'
            . '<p>Environment <b>%s</b>, build <b>%s</b>, PHP %s.</p><p><a href="/info">/info</a> &middot; <a href="/health">/health</a></p></body></html>',
            htmlspecialchars((string) $info['environment']),
            htmlspecialchars((string) $info['build']),
            htmlspecialchars((string) $info['php']),
        );

        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    private function json(int $status, array $data): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
    }
}
