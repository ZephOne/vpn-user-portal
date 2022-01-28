<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Closure;

interface ServiceInterface
{
    /**
     * @param Closure(UserInfo,Request):Response $closure
     */
    public function get(string $pathInfo, Closure $closure): void;

    /**
     * @param Closure(UserInfo,Request):Response $closure
     */
    public function post(string $pathInfo, Closure $closure): void;

    /**
     * @param Closure(Request):Response $closure
     */
    public function postBeforeAuth(string $pathInfo, Closure $closure): void;
}
