<?php

declare(strict_types=1);

namespace Examples\Tests;

use Carbon\CarbonImmutable;
use Examples\App;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    private function app(string $environment = 'staging'): App
    {
        $buildFile = sys_get_temp_dir() . '/build-' . uniqid('', true) . '.json';
        file_put_contents($buildFile, '{"build":"1234","version":"1.0.0"}');

        return new App($environment, $buildFile, CarbonImmutable::parse('2026-11-10T12:00:00Z'));
    }

    public function testHealthIsOk(): void
    {
        $response = $this->app()->handle('GET', '/health');

        self::assertSame(200, $response['status']);
        self::assertSame('{"status":"ok"}', $response['body']);
    }

    public function testInfoNamesTheBuildAndTheEnvironment(): void
    {
        $info = json_decode($this->app('production')->handle('GET', '/info')['body'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('1234', $info['build']);
        self::assertSame('production', $info['environment']);
        self::assertSame('2026-11-10T12:00:00+00:00', $info['time']);
    }

    public function testHomePageIsHtml(): void
    {
        $response = $this->app()->handle('GET', '/');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('build <b>1234</b>', $response['body']);
    }

    public function testUnknownPathIs404(): void
    {
        self::assertSame(404, $this->app()->handle('GET', '/nope')['status']);
    }

    public function testOnlyGetIsAllowed(): void
    {
        self::assertSame(405, $this->app()->handle('POST', '/')['status']);
    }
}
