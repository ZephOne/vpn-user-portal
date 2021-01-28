<?php $this->layout('base', ['activeItem' => 'wg', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
    <h3>New Peer</h3>
    <p>Copy &amp; paste this configuration in your WireGuard client and click
    the <em>Register Me!</em> button to "claim" this configuration.
    </p>

<pre>
[Peer]
PublicKey = <?=$this->e($wgInfo['PublicKey']); ?>

AllowedIPs = 0.0.0.0/32, ::/0
Endpoint = <?=$this->e($wgHost); ?>:<?=$this->e($wgInfo['ListenPort']); ?>


[Interface]
PrivateKey = <?=$this->e($secKey); ?>

Address = <?=$this->e($ipFour); ?>/24, <?=$this->e($ipSix); ?>/64
DNS = 9.9.9.9, 2620:fe::fe
</pre>

<form class="frm" method="post" action="wg_add_peer">
    <input type="hidden" name="PublicKey" value="<?=$this->e($pubKey); ?>">
    <input type="hidden" name="IPv4" value="<?=$this->e($ipFour); ?>">
    <input type="hidden" name="IPv6" value="<?=$this->e($ipSix); ?>">
    <fieldset>
        <button type="submit">Register Me!</button>
    </fieldset>
</form>



<?php if (array_key_exists('Peers', $wgInfo)): ?>
    <h3>List of Peers</h3>
    <table class="tbl">
        <thead>
            <tr>
                <th><?= $this->t('Public Key'); ?></th>
                <th><?= $this->t('IP Address'); ?></th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($wgInfo['Peers'] as $peerInfo): ?>
        <tr>
            <td><?= $this->e($peerInfo['PublicKey']); ?></td>
            <td>
                <ul>
<?php foreach ($peerInfo['AllowedIPs'] as $allowedIp): ?>
                    <li><?= $this->e($allowedIp); ?></li>
<?php endforeach; ?>
                </ul>
            </td>
        </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php $this->stop('content'); ?>
