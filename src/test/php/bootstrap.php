<?php

/*
 * Copyright (c) 2016 - 2022 Itspire.
 * This software is licensed under the BSD-3-Clause license. (see LICENSE.md for full license)
 * All Right Reserved.
 */

declare(strict_types=1);

if (!is_file($autoloadFile = __DIR__ . '/../../../vendor/autoload.php')) {
    throw new RuntimeException('Did not find vendor/autoload.php. Did you run "composer install --dev"?');
}

require $autoloadFile;
