<?php

/*
 * Copyright (c) 2016 - 2022 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Test\Formatter;

use Itspire\MonologLoki\Formatter\LokiFormatter;
use Itspire\MonologLoki\Test\Fixtures\TestLokiBar;
use Itspire\MonologLoki\Test\Fixtures\TestLokiFoo;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/** @covers \Itspire\MonologLoki\Formatter\LokiFormatter */
class LokiFormatterTest extends TestCase
{
    private ?LokiFormatter $logFormatter = null;

    public function setUp(): void
    {
        $this->logFormatter = new LokiFormatter(labels: [], context: [], systemName: 'test');
    }

    public function testSimpleMessageWithEmptyExtraAndNoContext(): void
    {
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    public function testSimpleMessageWithNonEmptyExtraButNoContext(): void
    {
        $logRecord = $this->getLogRecord(extra: ['ip' => '127.0.0.1']);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ip', array: $values);
        static::assertEquals(expected: '127.0.0.1', actual: $values['ip']);
    }

    public function testSimpleMessageWithEmptyExtraAndWithContext(): void
    {
        $logRecord = $this->getLogRecord(context: ['ip' => '127.0.0.1']);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ctxt_ip', array: $values);
        static::assertEquals(expected: '127.0.0.1', actual: $values['ctxt_ip']);
    }

    public function testSimpleMessageWithEmptyExtraWithGlobalContextAndRecordContext(): void
    {
        $this->logFormatter = new LokiFormatter(labels: [], context: ['app' => 'myapp'], systemName: 'test');
        $logRecord = $this->getLogRecord(context: ['ip' => '127.0.0.1']);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ctxt_app', array: $values);
        static::assertArrayHasKey(key: 'ctxt_ip', array: $values);
        static::assertEquals(expected: 'myapp', actual: $values['ctxt_app']);
        static::assertEquals(expected: '127.0.0.1', actual: $values['ctxt_ip']);
    }

    public function testSimpleMessageWithExtraObjectButNoContext(): void
    {
        $logRecord = $this->getLogRecord(extra: [
            'foo' => new TestLokiFoo(),
            'bar' => new TestLokiBar(),
            'baz' => [],
            'res' => fopen('php://memory', 'rb'),
        ]);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'foo', array: $values);
        static::assertArrayHasKey(key: 'bar', array: $values);
        static::assertArrayHasKey(key: 'baz', array: $values);
        static::assertArrayHasKey(key: 'res', array: $values);
        static::assertEquals(
            expected: '{"Itspire\\\\MonologLoki\\\\Test\\\\Fixtures\\\\TestLokiFoo":{"foo":"fooValue"}}',
            actual: $values['foo']
        );
        static::assertEquals(
            expected: '{"Itspire\\\\MonologLoki\\\\Test\\\\Fixtures\\\\TestLokiBar":"bar"}',
            actual: $values['bar']
        );
        static::assertEquals(expected: '[]', actual: $values['baz']);
        static::assertEquals(expected: '[resource(stream)]', actual: $values['res']);
    }

    public function testSimpleMessageWithEmptyExtraNoGlobalContextNorRecordContextWithGlobalLabels(): void
    {
        $this->logFormatter = new LokiFormatter(labels: ['app' => 'myapp'], context: [], systemName: 'test');
        $logRecord = $this->getLogRecord();
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        static::assertArrayHasKey(key: 'app', array: $message['stream']);
        static::assertEquals(expected: 'myapp', actual: $message['stream']['app']);
    }

    public function testSimpleMessageWithEmptyExtraWithRecordContextLabels(): void
    {
        $this->logFormatter = new LokiFormatter(labels: [], context: [], systemName: 'test');
        $logRecord = $this->getLogRecord(context: ['ip' => '127.0.0.1', 'labels' => ['app' => 'myapp']]);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        static::assertArrayHasKey(key: 'app', array: $message['stream']);
        static::assertEquals(expected: 'myapp', actual: $message['stream']['app']);

        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey(key: 'ctxt_ip', array: $values);
        static::assertEquals(expected: '127.0.0.1', actual: $values['ctxt_ip']);
    }

    public function testSimpleMessageWithContextException(): void
    {
        $logRecord = $this->getLogRecord(context: ['exception' => new \RuntimeException('Foo')]);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ctxt_exception', array: $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty(actual: $arrayException['trace']);
        static::assertSame(expected: 'RuntimeException', actual: $arrayException['class']);
        static::assertSame(expected: 'Foo', actual: $arrayException['message']);
        static::assertSame(expected: __FILE__ . ':' . (__LINE__ - 15), actual: $arrayException['file']);
        static::assertArrayNotHasKey(key: 'previous', array: $arrayException);
    }

    public function testSimpleMessageWithContextExceptionWithPrevious(): void
    {
        $logRecord = $this->getLogRecord(context: [
            'exception' => new \RuntimeException('Foo', 0, new \LogicException('Wut?')),
        ]);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);

        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ctxt_exception', array: $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty(actual: $arrayException['trace']);
        static::assertSame(expected: 'RuntimeException', actual: $arrayException['class']);
        static::assertSame(expected: 'Foo', actual: $arrayException['message']);
        static::assertSame(expected: __FILE__ . ':' . (__LINE__ - 16), actual: $arrayException['file']);
        static::assertNotEmpty(actual: $arrayException['previous']);
        static::assertSame(expected: 'LogicException', actual: $arrayException['previous']['class']);
        static::assertSame(expected: 'Wut?', actual: $arrayException['previous']['message']);
    }

    public function testSimpleMessageWithSoapFault(): void
    {
        if (!class_exists('SoapFault')) {
            static::markTestSkipped('Requires the soap extension');
        }

        $logRecord = $this->getLogRecord(context: ['exception' => new \SoapFault('foo', 'bar', 'hello', 'world')]);

        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
        $values = json_decode($message['values'][0][1], true);

        static::assertArrayHasKey(key: 'ctxt_exception', array: $values);

        $arrayException = json_decode($values['ctxt_exception'], true);

        static::assertNotEmpty(actual: $arrayException['trace']);
        static::assertSame(expected: 'SoapFault', actual: $arrayException['class']);
        static::assertSame(expected: 'bar', actual: $arrayException['message']);
        static::assertSame(expected: __FILE__ . ':' . (__LINE__ - 13), actual: $arrayException['file']);
        static::assertSame(expected: 'foo', actual: $arrayException['faultcode']);
        static::assertSame(expected: 'hello', actual: $arrayException['faultactor']);
        static::assertSame(expected: 'world', actual: $arrayException['detail']);
    }

    public function testBatchFormat(): void
    {
        $logRecord = $this->getLogRecord();
        $logRecord2 = $this->getLogRecord(level: Level::Info, message: 'bar');
        $messages = $this->logFormatter->formatBatch([$logRecord, $logRecord2]);

        static::assertCount(expectedCount: 2, haystack: $messages);
        $this->validateBaseStructure($messages[0], $logRecord);
        $this->validateBaseStructure($messages[1], $logRecord2);
    }

    public function testLineBreaksNotRemoved(): void
    {
        $logRecord = $this->getLogRecord(message: "foo\nbar");
        $message = $this->logFormatter->format($logRecord);

        $this->validateBaseStructure($message, $logRecord);
    }

    private function getLogRecord(
        Level $level = Level::Warning,
        string $message = 'foo',
        array $context = [],
        array $extra = []
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'log',
            level: $level,
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    private function validateBaseStructure(array $message, LogRecord $logRecord): void
    {
        static::assertArrayHasKey(key: 'stream', array: $message);
        static::assertArrayHasKey(key: 'values', array: $message);
        static::assertCount(expectedCount: 1, haystack: $message['values']);
        static::assertCount(expectedCount: 2, haystack: $message['values'][0]);

        $labels = $message['stream'];
        static::assertArrayHasKey(key: 'host', array: $labels);
        static::assertArrayHasKey(key: 'channel', array: $labels);

        static::assertEquals(expected: 'test', actual: $labels['host']);
        static::assertEquals(expected: $labels['channel'], actual: $logRecord['channel']);

        $values = json_decode($message['values'][0][1], true);
        static::assertArrayHasKey(key: 'level_name', array: $values);
        static::assertArrayHasKey(key: 'channel', array: $values);
        static::assertArrayHasKey(key: 'message', array: $values);
        static::assertArrayHasKey(key: 'datetime', array: $values);

        static::assertEquals(expected: $values['level_name'], actual: $logRecord->level->getName());
        static::assertEquals(expected: $values['channel'], actual: $logRecord->channel);
        static::assertEquals(expected: $values['message'], actual: $logRecord->message);
        static::assertEquals(
            expected: $values['datetime'],
            actual: $logRecord->datetime->format(NormalizerFormatter::SIMPLE_DATE)
        );
    }
}
