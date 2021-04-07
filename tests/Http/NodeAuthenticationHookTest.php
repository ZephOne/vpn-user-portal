<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\NodeAuthenticationHook;
use PHPUnit\Framework\TestCase;

class NodeAuthenticationHookTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testNodeAuthentication(): void
    {
        $nodeAuthentication = new NodeAuthenticationHook('foo');
        $request = new TestRequest(['HTTP_AUTHORIZATION' => 'Bearer foo']);
        $nodeAuthentication->executeBefore($request, []);
    }

    public function testNodeAuthenticationWrongToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('invalid token');
        $nodeAuthentication = new NodeAuthenticationHook('foo');
        $request = new TestRequest(['HTTP_AUTHORIZATION' => 'Bearer bar']);
        $nodeAuthentication->executeBefore($request, []);
    }

    public function testNodeAuthenticationNoToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no token');
        $nodeAuthentication = new NodeAuthenticationHook('foo');
        $request = new TestRequest([]);
        $nodeAuthentication->executeBefore($request, []);
    }
}