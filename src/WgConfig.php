<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class WgConfig
{
    /** @var string */
    private $publicKey;

    /** @var string */
    private $ipFour;

    /** @var string */
    private $ipSix;

    /** @var string */
    private $serverPublicKey;

    /** @var string */
    private $hostName;

    /** @var int */
    private $listenPort;

    /** @var array<string> */
    private $dnsServerList;

    /** @var string|null */
    private $privateKey;

    /**
     * @param string        $publicKey
     * @param string        $ipFour
     * @param string        $ipSix
     * @param string        $serverPublicKey
     * @param string        $hostName
     * @param int           $listenPort
     * @param array<string> $dnsServerList
     * @param string|null   $privateKey
     */
    public function __construct($publicKey, $ipFour, $ipSix, $serverPublicKey, $hostName, $listenPort, array $dnsServerList, $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->ipFour = $ipFour;
        $this->ipSix = $ipSix;
        $this->serverPublicKey = $serverPublicKey;
        $this->hostName = $hostName;
        $this->listenPort = $listenPort;
        $this->dnsServerList = $dnsServerList;
        $this->privateKey = $privateKey;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $output = [];
        $output[] = '[Interface]';
        if (null !== $this->privateKey) {
            $output[] = 'PrivateKey = '.$this->privateKey;
        }
        $output[] = 'Address = '.$this->ipFour.'/24, '.$this->ipSix.'/64';
        if (0 !== \count($this->dnsServerList)) {
            $output[] = 'DNS = '.implode(', ', $this->dnsServerList);
        }
        $output[] = '';
        $output[] = '[Peer]';
        $output[] = 'PublicKey = '.$this->serverPublicKey;
        $output[] = 'AllowedIPs = 0.0.0.0/0, ::/0';
        $output[] = 'Endpoint = '.$this->hostName.':'.(string) $this->listenPort;
        // client is probably behind NAT, so try to keep the connection alive
        $output[] = 'PersistentKeepalive = 25';

        return implode(PHP_EOL, $output);
    }

    /**
     * @param string $privateKey
     *
     * @return void
     */
    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * @return string
     */
    public function getIpFour()
    {
        return $this->ipFour;
    }

    /**
     * @return string
     */
    public function getIpSix()
    {
        return $this->ipSix;
    }
}
