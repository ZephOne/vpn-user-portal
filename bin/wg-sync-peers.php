<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Portal\Storage;
use LC\Portal\Tpl;
use LC\Portal\Wg;
use LC\Portal\WgModule;

// XXX move this script to libexec, only to be executed from cron, not
// by admin...

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateInterval('P90D')    // XXX code smell, not needed here!
    );

    // XXX we should not be needing Tpl here
    $wgModule = new WgModule(
        $config->s('WgConfig'),
        new Wg($config->s('WgConfig'), new CurlHttpClient()),
        $storage,
        new Tpl([], [], '')
    );

    $wgModule->syncPeers();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
