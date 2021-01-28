<?php $this->layout('base', ['activeItem' => 'wg', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
    <h3>List of Peers</h3>
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

    <h3>Add Peer</h3>
    <form class="frm" method="post" action="wg_add_peer">
        <label>Public Key <input type="text" name="PublicKey"></label>
        <label>IPv4 Address <input type="text" name="IPv4" required></label>
        <label>IPv6 Address <input type="text" name="IPv6" required></label>
        <fieldset>
            <button type="submit">Add</button>
        </fieldset>
    </form>
<?php $this->stop('content'); ?>
