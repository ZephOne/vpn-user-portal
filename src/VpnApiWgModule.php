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
                if (null === $ipInfo = $this->getIpAddress()) {
                    // unable to get new IP address to assign to peer
                    throw new HttpException('unable to get a an IP address', 500);
                }
                list($ipFour, $ipSix) = $ipInfo;
                if (null === $wgConfig = $this->wg->addPeer($publicKey, $ipFour, $ipSix)) {
                    throw new HttpException('unable to add peer', 500);
                }
                $this->storage->wgAddPeer($userId, $clientId, $publicKey, $ipFour, $ipSix, $this->dateTime, $clientId);
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

    /**
     * @param string $ipAddressPrefix
     *
     * @return array<string>
     */
    private static function getIpInRangeList($ipAddressPrefix)
    {
        list($ipAddress, $ipPrefix) = explode('/', $ipAddressPrefix);
        $ipPrefix = (int) $ipPrefix;
        $ipNetmask = long2ip(-1 << (32 - $ipPrefix));
        $ipNetwork = long2ip(ip2long($ipAddress) & ip2long($ipNetmask));
        $numberOfHosts = (int) 2 ** (32 - $ipPrefix) - 2;
        if ($ipPrefix > 30) {
            return [];
        }
        $hostList = [];
        for ($i = 2; $i <= $numberOfHosts; ++$i) {
            $hostList[] = long2ip(ip2long($ipNetwork) + $i);
        }

        return $hostList;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function getIpAddress()
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipInRangeList = self::getIpInRangeList($this->config->requireString('rangeFour'));
        foreach ($ipInRangeList as $ipInRange) {
            if (!\in_array($ipInRange, $allocatedIpFourList, true)) {
                // include this IPv4 address in IPv6 address
                list($ipSixAddress, $ipSixPrefix) = explode('/', $this->config->requireString('rangeSix'));
                $ipSixPrefix = (int) $ipSixPrefix;
                $ipFourHex = bin2hex(inet_pton($ipInRange));
                $ipSixHex = bin2hex(inet_pton($ipSixAddress));
                // clear the last $ipSixPrefix/4 elements
                $ipSixHex = substr_replace($ipSixHex, str_repeat('0', (int) ($ipSixPrefix / 4)), -((int) ($ipSixPrefix / 4)));
                $ipSixHex = substr_replace($ipSixHex, $ipFourHex, -8);
                $ipSix = inet_ntop(hex2bin($ipSixHex));

                return [$ipInRange, $ipSix];
            }
        }

        return null;
    }
}
