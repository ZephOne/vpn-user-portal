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

                // get all WG peers
                $wgPeers = $this->wg->getPeers();
                // get *my* WG peers
                $userPeers = $this->storage->wgGetPeers($userInfo->getUserId());

                // filter out *my* WG peers from the list of all WG peers
                $myPeers = [];
                foreach ($userPeers as $userPeer) {
                    $myPeers[$userPeer['public_key']] = [
                        'CreatedAt' => $userPeer['created_at'],
                        'DisplayName' => $userPeer['display_name'],
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
                // XXX verify input
                $displayName = $request->requirePostParameter('DisplayName');

                $privateKey = Wg::generatePrivateKey();
                $publicKey = Wg::generatePublicKey($privateKey);
                if (null === $wgConfig = $this->wg->addPeer($publicKey)) {
                    throw new HttpException('unable to get a an IP address', 500);
                }
                // as we generate a private key on the server, add it to the
                // configuration we got back
                $wgConfig->setPrivateKey($privateKey);
                $this->storage->wgAddPeer($userInfo->getUserId(), $displayName, $publicKey, $this->dateTime, null);

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

                $this->storage->wgRemovePeer($userInfo->getUserId(), $publicKey);
                // XXX make sure this peer is ours first
                $this->wg->removePeer($publicKey);

                return new RedirectResponse($request->getRootUri().'wireguard');
            }
        );
    }
}
