<?php $this->layout('base', ['activeItem' => 'wg', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
    <table class="tbl">
        <thead>
            <tr>
                <th><?= $this->t('Public Key'); ?></th>
                <th><?= $this->t('IP Address'); ?></th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($peerList as $peerItem): ?>
        <tr>
            <td><?= $this->e($peerItem['PublicKey']); ?></td>
            <td>
                <ul>
<?php foreach ($peerItem['AllowedIPs'] as $allowedIp): ?>
                    <li><?= $this->e($allowedIp); ?></li>
<?php endforeach; ?>
                </ul>
            </td>
        </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
<?php $this->stop('content'); ?>
