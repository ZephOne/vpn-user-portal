<?php $this->layout('base', ['activeItem' => 'wireguard', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
<h2>Create</h2>
<p>
<?=$this->t('Manually create and download a WireGuard configuration file for use in your WireGuard client.'); ?>
<?=$this->t('Choose a name, e.g. "Phone".'); ?>
</p>

<form method="post" class="frm">
    <fieldset>
        <label for="displayName"><?=$this->t('Name'); ?></label>
        <input type="text" name="DisplayName" id="displayName" size="32" maxlength="64" placeholder="<?=$this->t('Name'); ?>" autofocus required>
    </fieldset>
    <fieldset>
        <button type="submit"><?=$this->t('Create'); ?></button>
    </fieldset>
</form>

<?php if (0 !== count($wgPeers)): ?>
<h2>Existing</h2>
<table class="tbl">
    <thead>
        <tr>
            <th></th>
            <th><?= $this->t('Name'); ?></th>
            <th><?= $this->t('IP Address'); ?></th>
            <th><?= $this->t('Created At'); ?> (<?=$this->e(date('T')); ?>)</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($wgPeers as $peerInfo): ?>
    <tr>
<?php if ($peerInfo['is_online']): ?>
        <td title="<?=$this->t('Online'); ?>">🟢</td>
<?php else: ?>
        <td title="<?=$this->t('Offline'); ?>">🔴</td>
<?php endif; ?>
        <td><span title="<?= $this->e($peerInfo['public_key']); ?>"><?= $this->e($peerInfo['display_name']); ?></span></td>
        <td>
            <ul>
                <li><?= $this->e($peerInfo['ip_four']); ?></li>
                <li><?= $this->e($peerInfo['ip_six']); ?></li>
            </ul>
        </td>
        <td>
            <?= $this->d($peerInfo['created_at']->format(DateTime::ATOM)); ?>
        </td>
        <td>
            <form class="frm" method="post" action="wireguard_remove_peer">
                <input type="hidden" name="PublicKey" value="<?= $this->e($peerInfo['public_key']); ?>">
                <button type="submit">Remove</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $this->stop('content'); ?>
