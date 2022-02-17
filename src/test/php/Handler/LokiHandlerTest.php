<?php

/*
 * Copyright (c) 2016 - 2020 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Test\Handler;

use Itspire\MonologLoki\Handler\LokiHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class LokiHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $record = $this->getRecord(Logger::WARNING, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new LokiHandler(
            [
                'entrypoint' => getenv('LOKI_ENTRYPOINT'),
                'context' => [],
                'labels' => [],
                'client_name' => 'test',
                'is_sending_enabled' => true,
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        );

        static::assertInstanceOf(LokiHandler::class, $handler);
        static::assertTrue($handler->isHandling($record));

        try {
            $handler->handle($record);
        } catch (\RuntimeException $e) {
            static::markTestSkipped('Could not connect to Loki server on ' . getenv('LOKI_ENTRYPOINT'));
        }
    }

    public function testIsHandlingReturnsFalseWhenSendingIsDisabled(): void
    {
        $record = $this->getRecord(Logger::WARNING, 'test', ['data' => new \stdClass(), 'foo' => 34]);

        $handler = new LokiHandler(
            [
                'entrypoint' => getenv('LOKI_ENTRYPOINT'),
                'context' => [],
                'labels' => [],
                'client_name' => 'test',
                'is_sending_enabled' => false,
                'auth' => [
                    'basic' => ['user', 'password'],
                ],
            ]
        );

        static::assertFalse($handler->isHandling($record));
    }

    protected function getRecord($level = Logger::WARNING, $message = 'test', array $context = []): array
    {
        return [
            'message' => (string) $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];
    }
}
