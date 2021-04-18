<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config;
use LC\Portal\Storage;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        $baseDir.'/schema'
    );
    $storage->cleanConnectionLog(new DateTimeImmutable('now -32 days'));
    $storage->cleanExpiredCertificates(new DateTimeImmutable('now -7 days'));
    $storage->cleanUserLog(new DateTimeImmutable('now -32 days'));
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}