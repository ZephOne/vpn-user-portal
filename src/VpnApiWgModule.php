<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use LC\Common\Config;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;

class VpnApiWgModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;

    /** @var \LC\Common\Config */
    private $config;

    /** @var Wg */
    private $wg;

    /** @var \LC\Portal\Storage */
    private $storage;

    public function __construct(Config $config, Wg $wg, Storage $storage)
    {
        $this->config = $config;
        $this->wg = $wg;
        $this->storage = $storage;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/wg/available',
            /**
             * @return Response
             */
            function (Request $request, array $hookData) {
                $response = new Response(200);
                $response->setBody('y');

                return $response;
            }
        );

        $service->post(
            '/wg/create_config',
            /**
             * @return Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo $accessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                $userId = $accessTokenInfo->getUserId();
                $clientId = $accessTokenInfo->getClientId();

                // XXX validate input
                $publicKey = $request->requirePostParameter('publicKey');

                if (null === $wgConfig = $this->wg->addPeer($publicKey)) {
                    throw new HttpException('IP pool exhausted', 500);
                }
                $this->storage->wgAddPeer($userId, $clientId, $publicKey, $this->dateTime, $clientId);
                $response = new Response(200, 'text/plain');
                $response->setBody((string) $wgConfig);

                return $response;
            }
        );

        $service->post(
            '/wg/disconnect',
            /**
             * @return Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\OAuth\VpnAccessTokenInfo $accessTokenInfo */
                $accessTokenInfo = $hookData['auth'];
                $userId = $accessTokenInfo->getUserId();

                // XXX validate input
                $publicKey = $request->requirePostParameter('publicKey');

                $this->storage->wgRemovePeer($userId, $publicKey);
                // XXX make sure this peer is ours first
                $this->wg->removePeer($publicKey);

                return new Response(204);
            }
        );
    }
}
