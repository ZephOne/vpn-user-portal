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
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\TplInterface;

class WgModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;

    /** @var \LC\Common\Config */
    private $config;

    /** @var Wg */
    private $wg;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    public function __construct(Config $config, Wg $wg, Storage $storage, TplInterface $tpl)
    {
        $this->config = $config;
        $this->wg = $wg;
        $this->storage = $storage;
        $this->tpl = $tpl;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/wireguard',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalWg',
                        [
                            'wgPeers' => $this->storage->wgGetPeers($userInfo->getUserId()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/wireguard',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                // XXX verify input
                $displayName = $request->requirePostParameter('DisplayName');

                $privateKey = self::generatePrivateKey();
                $publicKey = self::generatePublicKey($privateKey);
                if (null === $ipInfo = $this->getIpAddress()) {
                    // unable to get new IP address to assign to peer
                    throw new HttpException('unable to get a an IP address', 500);
                }
                list($ipFour, $ipSix) = $ipInfo;

                // store peer in the DB
                $this->storage->wgAddPeer($userInfo->getUserId(), $displayName, $publicKey, $ipFour, $ipSix, $this->dateTime, null);

                // add peer to WG
                if (null === $wgConfig = $this->wg->addPeer($publicKey, $ipFour, $ipSix)) {
                    throw new HttpException('unable to add peer', 500);
                }
                // as we generate a private key on the server, add it to the
                // configuration we got back
                $wgConfig->setPrivateKey($privateKey);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalWgCreate',
                        [
                            'wgConfig' => (string) $wgConfig,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/wireguard_remove_peer',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                // XXX validate input
                $publicKey = $request->requirePostParameter('PublicKey');

                // remove peer from DB
                $this->storage->wgRemovePeer($userInfo->getUserId(), $publicKey);

                // XXX make sure this peer is ours first!
                // remove peer from WG
                $this->wg->removePeer($publicKey);

                return new RedirectResponse($request->getRootUri().'wireguard');
            }
        );
    }

    /**
     * @return string
     */
    private static function generatePrivateKey()
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }

    /**
     * @param string $privateKey
     *
     * @return string
     */
    private static function generatePublicKey($privateKey)
    {
        ob_start();
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
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
