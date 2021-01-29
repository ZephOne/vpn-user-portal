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
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;

class VpnApiWgModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;

    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    public function __construct(Config $config, Storage $storage, HttpClientInterface $httpClient)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->httpClient = $httpClient;
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
                return new Response(204);
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
                $publicKey = $request->requirePostParameter('publicKey');

                $httpResponse = $this->httpClient->get($this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/info', [], []);
                $wgInfo = Json::decode($httpResponse->getBody());
                if (null === $ipInfo = WgModule::getIpAddress($wgInfo)) {
                    throw new HttpException('IP pool exhausted', 500);
                }
                list($ipFour, $ipSix) = $ipInfo;

                $wgHost = $request->getServerName();
                $serverPublicKey = $wgInfo['PublicKey'];
                $listenPort = $wgInfo['ListenPort'];
                // XXX make DNS optional
                $dnsIpList = implode(', ', $this->config->requireArray('dns', ['9.9.9.9', '2620:fe::fe']));
                $wgConfig = <<< EOF
[Peer]
PublicKey = $serverPublicKey
AllowedIPs = 0.0.0.0/0, ::/0
Endpoint = $wgHost:$listenPort

[Interface]
Address = $ipFour/24, $ipSix/64
DNS = $dnsIpList
EOF;
                // make sure IP is still available
                $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey), 'AllowedIPs='.urlencode($ipFour.'/32'), 'AllowedIPs='.urlencode($ipSix.'/128')]);

                // XXX catch errors
                // XXX make sure WG "backend" is in sync with local DB (somehow)
                $httpResponse = $this->httpClient->postRaw(
                    $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/add_peer',
                    [],
                    $rawPostData
                );

                $this->storage->wgAddPeer($userId, $publicKey, $this->dateTime);

                $response = new Response(200, 'text/plain');
                $response->setBody($wgConfig);

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

                $publicKey = $request->requirePostParameter('publicKey');
                $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey)]);

                // XXX catch errors
                // XXX make sure WG "backend" is in sync with local DB (somehow)
                $httpResponse = $this->httpClient->postRaw(
                    $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/remove_peer',
                    [],
                    $rawPostData
                );

                $this->storage->wgRemovePeer($userId, $publicKey);

                return new Response(204);
            }
        );
    }
}
