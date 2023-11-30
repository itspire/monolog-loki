<?php

/*
 * Copyright (c) 2016 - 2022 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

class LokiFormatter extends NormalizerFormatter
{
    /** a prefix for 'extra' fields from the Monolog record (optional) */
    protected string $extraPrefix = '';

    /** a prefix for 'context' fields from the Monolog record (optional) */
    protected string $contextPrefix = '';

    /** labels that will be used for all messages (optional) */
    protected array $labels = [];

    /** Base context to be used for all messages (optional) */
    protected array $context = [];

    public function __construct(
        array $labels = [],
        array $context = [],
        ?string $systemName = null,
        string $extraPrefix = '',
        string $contextPrefix = 'ctxt_'
    ) {
        parent::__construct();

        $this->labels = $labels;
        $this->context = $context;
        $this->labels['host'] = $systemName ?? gethostname();
        $this->extraPrefix = $extraPrefix;
        $this->contextPrefix = $contextPrefix;
    }

    public function format(LogRecord $record): array
    {
        $formattedRecord = parent::format($record);

        $customLabels = $formattedRecord['context']['labels'] ?? [];
        unset($formattedRecord['context']['labels']);
        $formattedRecord['context'] = array_merge($this->context, $formattedRecord['context']);
        $preparedRecord = $this->prepareRecord($formattedRecord);

        return [
            'stream' => array_merge($this->labels, $customLabels, $this->getMonologLabels($preparedRecord)),
            'values' => [
                [
                    // use format() instead of getTimestamp() to get microsecond precision
                    $record->datetime->format('Uu') . '000',
                    $this->toJson($this->normalize($preparedRecord)),
                ],
            ],
        ];
    }

    /**
     * @param array{
     *     message: string,
     *     context: mixed[],
     *     level: int,
     *     level_name: string,
     *     channel: string,
     *     datetime: \DateTimeImmutable,
     *     extra: mixed[]
     * } $formattedRecord
     */
    public function prepareRecord(array $formattedRecord): array
    {
        $preparedRecord = $formattedRecord;
        if (!empty($formattedRecord['context'])) {
            $preparedRecord = array_merge(
                $preparedRecord,
                $this->prepareRecordList($formattedRecord['context'], $this->contextPrefix)
            );
            unset($preparedRecord['context']);
        }

        if (!empty($formattedRecord['extra'])) {
            $preparedRecord = array_merge(
                $preparedRecord,
                $this->prepareRecordList($formattedRecord['extra'], $this->extraPrefix, ['line', 'file'])
            );
            unset($preparedRecord['extra']);
        }

        if (
            !isset($preparedRecord['file'])
            && isset($preparedRecord[$this->contextPrefix . 'exception']['file'])
            && preg_match("/^(.+):([\d]+)$/", $preparedRecord[$this->contextPrefix . 'exception']['file'], $matches)
        ) {
            $preparedRecord['file'] = (string) $matches[1];
            $preparedRecord['line'] = (string) $matches[2];
        }

        return $preparedRecord;
    }

    private function prepareRecordList(array $list = [], string $prefixKey = '', array $fieldNotPrefixed = []): array
    {
        foreach ($list as $label => $value) {
            $key = (in_array($label, $fieldNotPrefixed, true)) ? $label : $prefixKey . $label;
            $finalValue = $value;

            $list[$key] = (null !== $finalValue && !is_scalar($finalValue))
                ? $this->toJson($finalValue)
                : (string) $finalValue;

            if ($key !== $label) {
                unset($list[$label]);
            }
        }

        return $list;
    }

    private function getMonologLabels(array $record): array
    {
        $keepAsLabels = ['channel'];

        return array_filter(
            $record,
            fn($key): bool => in_array($key, $keepAsLabels, true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
