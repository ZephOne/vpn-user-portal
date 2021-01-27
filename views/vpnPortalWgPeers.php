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
            <td><?= $this->e($peerItem['IPv4']); ?></td>
        </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
<?php $this->stop('content'); ?>
