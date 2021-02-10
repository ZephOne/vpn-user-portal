<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;

/**
 * Connect to the wg-daemon.
 */
class Wg
{
    /** @var \LC\Common\HttpClient\HttpClientInterface */
    private $httpClient;

    /** @var \LC\Common\Config */
    private $config;

    public function __construct(Config $config, HttpClientInterface $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
    }

    /**
     * @return array
     */
    public function getPeers()
    {
        $wgInfo = $this->getInfo();

        return \array_key_exists('Peers', $wgInfo) ? $wgInfo['Peers'] : [];
    }

    /**
     * @param string $publicKey
     * @param string $ipFour
     * @param string $ipSix
     *
     * @return WgConfig|null
     */
    public function addPeer($publicKey, $ipFour, $ipSix)
    {
        $wgInfo = $this->getInfo();
        $rawPostData = implode(
            '&',
            [
                'Device='.$this->config->requireString('wgDevice'),
                'PublicKey='.urlencode($publicKey),
                'AllowedIPs='.urlencode($ipFour.'/32'),
                'AllowedIPs='.urlencode($ipSix.'/128'),
            ]
        );

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/add_peer',
            [],
            $rawPostData
        );

        return new WgConfig(
            $publicKey,
            $ipFour,
            $ipSix,
            $wgInfo['PublicKey'],
            $this->config->requireString('hostName'),
            $wgInfo['ListenPort'],
            $this->config->requireArray('dns', ['9.9.9.9', '2620:fe::fe']),
            null
        );
    }

    /**
     * @param string $publicKey
     *
     * @return void
     */
    public function removePeer($publicKey)
    {
        $rawPostData = implode('&', ['Device='.$this->config->requireString('wgDevice'), 'PublicKey='.urlencode($publicKey)]);

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/remove_peer',
            [],
            $rawPostData
        );
    }

    /**
     * @return array{PublicKey:string,ListenPort:int,Peers:array}
     */
    private function getInfo()
    {
        // XXX catch errors
        // XXX make sure WG "backend" is in sync with local DB (somehow)
        $httpResponse = $this->httpClient->get($this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/info', ['Device' => $this->config->requireString('wgDevice')], []);

        return Json::decode($httpResponse->getBody());
    }
}
