<?php

/*
 * Copyright (c) 2016 - 2022 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Test\Handler;

use Itspire\MonologLoki\Handler\LokiHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class LokiHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new LokiHandler(
            [
                'entrypoint' => getenv('LOKI_ENTRYPOINT'),
                'context' => [],
                'labels' => [],
                'client_name' => 'test',
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        );

        static::assertInstanceOf(expected: LokiHandler::class, actual: $handler);

        try {
            $handler->handle($record);
        } catch (\RuntimeException) {
            static::markTestSkipped('Could not connect to Loki server on ' . getenv('LOKI_ENTRYPOINT'));
        }
    }

    public function testHandleWithTenantId(): void
    {
        $record = $this->getRecord(Level::Warning, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new LokiHandler(
            [
                'entrypoint' => getenv('LOKI_ENTRYPOINT'),
                'context' => [],
                'labels' => [],
                'client_name' => 'test',
                'tenant_id' => 'tenant-id-123',
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        );

        static::assertInstanceOf(expected: LokiHandler::class, actual: $handler);

        try {
            $handler->handle($record);
        } catch (\RuntimeException) {
            static::markTestSkipped('Could not connect to Loki server on ' . getenv('LOKI_ENTRYPOINT'));
        }
    }

    protected function getRecord(
        Level $level = Level::Warning,
        string $message = 'test',
        array $context = []
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: $level,
            message: $message,
            context: $context,
            extra: []
        );
    }
}
