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
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;
use LC\Common\TplInterface;

class WgModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    public function __construct(Config $config, Storage $storage, TplInterface $tpl, HttpClientInterface $httpClient)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
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

                // XXX catch errors
                // XXX make sure WG "backend" is in sync with local DB (somehow)
                $httpResponse = $this->httpClient->get($this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/info', [], []);
                $wgInfo = Json::decode($httpResponse->getBody());

                $wgPeers = \array_key_exists('Peers', $wgInfo) ? $wgInfo['Peers'] : [];
                $userPeers = $this->storage->wgGetPeers($userInfo->getUserId());
                // only include my peers in the list shown

                $myPeers = [];
                foreach ($userPeers as $userPeer) {
                    $myPeers[$userPeer['public_key']] = [
                        'CreatedAt' => $userPeer['created_at'],
                        'PublicKey' => $userPeer['public_key'],
                    ];
                }

                foreach ($wgPeers as $wgPeer) {
                    if (\array_key_exists($wgPeer['PublicKey'], $myPeers)) {
                        $myPeers[$wgPeer['PublicKey']]['AllowedIPs'] = $wgPeer['AllowedIPs'];
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalWg',
                        [
                            'wgPeers' => array_values($myPeers),
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

                $httpResponse = $this->httpClient->get($this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/info', [], []);
                $wgInfo = Json::decode($httpResponse->getBody());

                $minIndex = 1;
                if (\array_key_exists('Peers', $wgInfo)) {
                    // we have some peer(s)
                    foreach ($wgInfo['Peers'] as $peerInfo) {
                        foreach ($peerInfo['AllowedIPs'] as $allowedIp) {
                            if (false !== strpos($allowedIp, '.')) {
                                // IPv4
                                list(, , , $i) = explode('.', $allowedIp);
                                $i = (int) $i;
                                if ($i > $minIndex) {
                                    $minIndex = $i;
                                }
                            }
                        }
                    }
                }

                // XXX do not overflow, max 254
                $ipFour = '10.10.10.'.($minIndex + 1);
                // XXX convert to hex
                $ipSix = 'fd00:1234:1234:1234::'.dechex($minIndex + 1);

                $privateKey = self::generatePrivateKey();
                $publicKey = self::getPublicKey($privateKey);
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
PrivateKey = $privateKey
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

                $this->storage->wgAddPeer($userInfo->getUserId(), $publicKey, $this->dateTime);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalWgCreate',
                        [
                            'wgConfig' => $wgConfig,
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

                $publicKey = $request->requirePostParameter('PublicKey');
                $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey)]);

                // XXX catch errors
                // XXX make sure WG "backend" is in sync with local DB (somehow)
                $httpResponse = $this->httpClient->postRaw(
                    $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/remove_peer',
                    [],
                    $rawPostData
                );

                $this->storage->wgRemovePeer($userInfo->getUserId(), $publicKey);

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
    private static function getPublicKey($privateKey)
    {
        ob_start();
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
