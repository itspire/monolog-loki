<?php

/*
 * Copyright (c) 2016 - 2020 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Test\Formatter;

use Itspire\MonologLoki\Formatter\LokiFormatter;
use Itspire\MonologLoki\Test\Fixtures\TestLokiBar;
use Itspire\MonologLoki\Test\Fixtures\TestLokiFoo;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/** @covers \Itspire\MonologLoki\Formatter\LokiFormatter */
class LokiFormatterTest extends TestCase
{
    private ?LokiFormatter $logFormatter = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->logFormatter = new LokiFormatter([], [], 'test');
    }

    public function testSimpleMessageWithEmptyExtraAndNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    public function testSimpleMessageWithNonEmptyExtraButNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['extra'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ip', $values);
        static::assertEquals('127.0.0.1', $values['ip']);
    }

    public function testSimpleMessageWithEmptyExtraAndWithContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_ip', $values);
        static::assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithEmptyExtraWithGlobalContextAndRecordContext(): void
    {
        $this->logFormatter = new LokiFormatter([], ['app' => 'myapp'], 'test');
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1'];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_app', $values);
        static::assertArrayHasKey('ctxt_ip', $values);
        static::assertEquals('myapp', $values['ctxt_app']);
        static::assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithExtraObjectButNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['extra'] = [
            'foo' => new TestLokiFoo(),
            'bar' => new TestLokiBar(),
            'baz' => [],
            'res' => fopen('php://memory', 'rb'),
        ];

        $message = $this->logFormatter->format($logRecord);
        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('foo', $values);
        static::assertArrayHasKey('bar', $values);
        static::assertArrayHasKey('baz', $values);
        static::assertArrayHasKey('res', $values);
        static::assertEquals(
            '[object] (Itspire\MonologLoki\Test\Fixtures\TestLokiFoo: {"foo":"fooValue"})',
            $values['foo']
        );
        static::assertEquals('[object] (Itspire\MonologLoki\Test\Fixtures\TestLokiBar: bar)', $values['bar']);
        static::assertEquals('[]', $values['baz']);
        static::assertEquals('[resource] (stream)', $values['res']);
    }

    public function testSimpleMessageWithEmptyExtraNoGlobalContextNorRecordContextWithGlobalLabels(): void
    {
        $this->logFormatter = new LokiFormatter(['app' => 'myapp'], [], 'test');
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        static::assertArrayHasKey('app', $message['stream']);
        static::assertEquals('myapp', $message['stream']['app']);
    }

    public function testSimpleMessageWithEmptyExtraWithRecordContextLabels(): void
    {
        $this->logFormatter = new LokiFormatter([], [], 'test');
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['ip' => '127.0.0.1', 'labels' => ['app' => 'myapp']];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        static::assertArrayHasKey('app', $message['stream']);
        static::assertEquals('myapp', $message['stream']['app']);

        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_ip', $values);
        static::assertEquals('127.0.0.1', $values['ctxt_ip']);
    }

    public function testSimpleMessageWithContextException(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \RuntimeException('Foo')];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty($arrayException['trace']);
        static::assertSame('RuntimeException', $arrayException['class']);
        static::assertSame('Foo', $arrayException['message']);
        static::assertSame(__FILE__ . ':' . (__LINE__ - 13), $arrayException['file']);
        static::assertArrayNotHasKey('previous', $arrayException);
    }

    public function testSimpleMessageWithContextExceptionWithPrevious(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \RuntimeException('Foo', 0, new \LogicException('Wut?'))];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty($arrayException['trace']);
        static::assertSame('RuntimeException', $arrayException['class']);
        static::assertSame('Foo', $arrayException['message']);
        static::assertSame(__FILE__ . ':' . (__LINE__ - 13), $arrayException['file']);
        static::assertNotEmpty($arrayException['previous']);
        static::assertSame('LogicException', $arrayException['previous']['class']);
        static::assertSame('Wut?', $arrayException['previous']['message']);
    }

    public function testSimpleMessageWithSoapFault(): void
    {
        if (!class_exists('SoapFault')) {
            static::markTestSkipped('Requires the soap extension');
        }

        $logRecord = $this->getLogRecord();
        $logRecord['context'] = ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')];

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('ctxt_exception', $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty($arrayException['trace']);
        static::assertSame('SoapFault', $arrayException['class']);
        static::assertSame('bar', $arrayException['message']);
        static::assertSame(__FILE__ . ':' . (__LINE__ - 13), $arrayException['file']);
        static::assertSame('foo', $arrayException['faultcode']);
        static::assertSame('hello', $arrayException['faultactor']);
        static::assertSame('world', $arrayException['detail']);
    }

    public function testBatchFormat(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord2 = $this->getLogRecord();
        $logRecord2['level_name'] = 'INFO';
        $logRecord2['message'] = 'bar';
        $messages = $this->logFormatter->formatBatch([$logRecord, $logRecord2]);

        static::assertCount(2, $messages);
        $this->validateBaseStructure($messages[0], $logRecord);
        $this->validateBaseStructure($messages[1], $logRecord2);
    }

    public function testLineBreaksNotRemoved(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord['message'] = "foo\nbar";
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    private function getLogRecord($level = Logger::WARNING): array
    {
        return [
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'log',
            'context' => [],
            'message' => 'foo',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];
    }

    private function validateBaseStructure($message, $logRecord): void
    {
        static::assertArrayHasKey('stream', $message);
        static::assertArrayHasKey('values', $message);
        static::assertCount(1, $message['values']);
        static::assertCount(2, $message['values'][0]);

        $labels = $message['stream'];
        static::assertArrayHasKey('host', $labels);
        static::assertArrayHasKey('level_name', $labels);
        static::assertArrayHasKey('channel', $labels);

        static::assertEquals($labels['host'], 'test');
        static::assertEquals($labels['level_name'], $logRecord['level_name']);
        static::assertEquals($labels['channel'], $logRecord['channel']);

        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey('level_name', $values);
        static::assertArrayHasKey('channel', $values);
        static::assertArrayHasKey('message', $values);
        static::assertArrayHasKey('datetime', $values);

        static::assertEquals($values['level_name'], $logRecord['level_name']);
        static::assertEquals($values['channel'], $logRecord['channel']);
        static::assertEquals($values['message'], $logRecord['message']);
        static::assertEquals($values['datetime'], $logRecord['datetime']->format(NormalizerFormatter::SIMPLE_DATE));
    }
}
