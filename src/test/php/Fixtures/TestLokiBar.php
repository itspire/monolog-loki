<?php

/*
 * Copyright (c) 2016 - 2022 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

namespace Itspire\MonologLoki\Test\Fixtures;

class TestLokiBar
{
    public function __toString(): string
    {
        return 'bar';
    }
}
