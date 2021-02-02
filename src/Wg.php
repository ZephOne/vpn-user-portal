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
     *
     * @return WgConfig|null
     */
    public function addPeer($publicKey)
    {
        $wgInfo = $this->getInfo();
        if (null === $ipInfo = $this->getIpAddress($wgInfo)) {
            // unable to get new IP address to assign to peer
            return null;
        }
        list($ipFour, $ipSix) = $ipInfo;
        $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey), 'AllowedIPs='.urlencode($ipFour.'/32'), 'AllowedIPs='.urlencode($ipSix.'/128')]);
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
        $rawPostData = implode('&', ['PublicKey='.urlencode($publicKey)]);

        // XXX catch errors
        $httpResponse = $this->httpClient->postRaw(
            $this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/remove_peer',
            [],
            $rawPostData
        );
    }

    /**
     * @return string
     */
    public static function generatePrivateKey()
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
    public static function generatePublicKey($privateKey)
    {
        ob_start();
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }

    /**
     * @param mixed $ipAddressPrefix
     *
     * @return array<string>
     */
    private static function getIpInRangeList($ipAddressPrefix)
    {
        list($ipAddress, $ipPrefix) = explode('/', $ipAddressPrefix);
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
    private function getIpAddress(array $wgInfo)
    {
        $allocatedIpList = [];
        if (\array_key_exists('Peers', $wgInfo)) {
            // we have some peer(s)
            foreach ($wgInfo['Peers'] as $peerInfo) {
                foreach ($peerInfo['AllowedIPs'] as $allowedIp) {
                    if (false !== strpos($allowedIp, '.')) {
                        $allocatedIpList[] = $allowedIp;
                    }
                }
            }
        }

        $ipInRangeList = self::getIpInRangeList($this->config->requireString('rangeFour'));
        foreach ($ipInRangeList as $ipInRange) {
            if (!\in_array($ipInRange.'/32', $allocatedIpList, true)) {
                // include this IPv4 address in IPv6 address
                list($ipSixAddress, $ipSixPrefix) = explode('/', $this->config->requireString('rangeSix'));
                $ipFourHex = bin2hex(inet_pton($ipInRange));
                $ipSixHex = bin2hex(inet_pton($ipSixAddress));
                // clear the last $ipSixPrefix/4 elements
                $ipSixHex = substr_replace($ipSixHex, str_repeat('0', $ipSixPrefix / 4), -($ipSixPrefix / 4));
                $ipSixHex = substr_replace($ipSixHex, $ipFourHex, -8);
                $ipSix = inet_ntop(hex2bin($ipSixHex));

                return [$ipInRange, $ipSix];
            }
        }

        return null;
    }

    /**
     * @return array{PublicKey:string,ListenPort:int,Peers:array}
     */
    private function getInfo()
    {
        // XXX catch errors
        // XXX make sure WG "backend" is in sync with local DB (somehow)
        $httpResponse = $this->httpClient->get($this->config->requireString('wgDaemonUrl', 'http://localhost:8080').'/info', [], []);

        return Json::decode($httpResponse->getBody());
    }
}
