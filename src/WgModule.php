<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

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
    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    public function __construct(TplInterface $tpl, HttpClientInterface $httpClient)
    {
        $this->tpl = $tpl;
        $this->httpClient = $httpClient;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/wg',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $httpResponse = $this->httpClient->get('http://localhost:8080/info', [], []);

                $wgInfo = Json::decode($httpResponse->getBody());

                // figure out if we have any peers configured, find a free IP
                // address...

                $minIndex = 1;
                if (\array_key_exists('Peers', $wgInfo)) {
                    // we have some peer(s)
                    foreach ($wgInfo['Peers'] as $peerInfo) {
                        foreach ($peerInfo['AllowedIPs'] as $allowedIp) {
                            if (false !== strpos($allowedIp, '.')) {
                                // IPv4
                                list(, , , $i) = explode('.', $allowedIp);
                                if ($i > $minIndex) {
                                    ++$minIndex;
                                }
                            }
                        }
                    }
                }

                $ipFour = '10.10.10.'.($i + 1);
                $ipSix = 'fd00:1234:1234:1234::'.($i + 1);

                $privateKey = self::generatePrivateKey();
                $publicKey = self::getPublicKey($privateKey);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalWgPeers',
                        [
                            'pubKey' => $publicKey,
                            'secKey' => $privateKey,
                            'ipFour' => $ipFour,
                            'ipSix' => $ipSix,
                            'wgHost' => $request->getServerName(),
                            'wgInfo' => $wgInfo,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/wg_add_peer',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $publicKey = $request->requirePostParameter('PublicKey');
                $ipFour = $request->requirePostParameter('IPv4').'/32';
                $ipSix = $request->requirePostParameter('IPv6').'/128';

                $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey), 'AllowedIPs='.urlencode($ipFour), 'AllowedIPs='.urlencode($ipSix)]);

                // parse the form fields
                // XXX make sure content-type is correct
                $httpResponse = $this->httpClient->postRaw(
                    'http://localhost:8080/add_peer',
                    [],
                    $rawPostData
                );

                return new RedirectResponse($request->getRootUri().'wg');
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
        passthru("echo $privateKey | /usr/bin/wg genkey");

        return trim(ob_get_clean());
    }
}
