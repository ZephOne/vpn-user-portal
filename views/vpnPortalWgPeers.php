<?php $this->layout('base', ['activeItem' => 'wg', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
    <h3>New Connection</h3>
    <p>
Copy &amp; paste this configuration in your WireGuard client or scan the QR
code with your mobile device and click the <strong>Register Me!</strong> button
to "claim" this configuration.
    </p>
<blockquote>
<pre><?=$this->e($wgConfig); ?></pre>
</blockquote>

<p>
    <img src="qr?qr_text=<?=urlencode($this->e($wgConfig)); ?>">
</p>

<form class="frm" method="post" action="wg_add_peer">
    <input type="hidden" name="PublicKey" value="<?=$this->e($pubKey); ?>">
    <input type="hidden" name="IPv4" value="<?=$this->e($ipFour); ?>">
    <input type="hidden" name="IPv6" value="<?=$this->e($ipSix); ?>">
    <fieldset>
        <button type="submit">Register Me!</button>
    </fieldset>
</form>
<!--
<?php if (0 !== count($wgPeers)): ?>
    <h3>List of Peers</h3>
    <table class="tbl">
        <thead>
            <tr>
                <th><?= $this->t('Public Key'); ?></th>
                <th><?= $this->t('IP Address'); ?></th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($wgPeers as $peerInfo): ?>
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
-->
<?php $this->stop('content'); ?>
