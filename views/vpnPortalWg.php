<?php $this->layout('base', ['activeItem' => 'wireguard', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>

<h2>Create</h2>
<form class="frm" method="post">
   <button type="submit">Create</button>
</form>

<?php if (0 !== count($wgPeers)): ?>
<h2>Existing</h2>
<p class="warning">
Currently this lists all configurations, also ones that do not belong to you.
Sorry about that :-)
</p>
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
<?php $this->stop('content'); ?>
