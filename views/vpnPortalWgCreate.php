<?php $this->layout('base', ['activeItem' => 'wireguard', 'pageTitle' => $this->t('WireGuard')]); ?>
<?php $this->start('content'); ?>
    <h2>Configuration</h2>
    <p class="plain">
Scan the QR code on your mobile device, or copy&amp;paste this configuration to
your WireGuard client.
    </p>
    <p>
        <img src="qr?qr_text=<?= urlencode($this->e($wgConfig)); ?>">
    </p>
    <blockquote>
        <pre><?= $this->e($wgConfig); ?></pre>
    </blockquote>

    <p>
Try to connect after importing the configuration file, or scanning the QR code.
    </p>
    <a href="wireguard">All Done!</a>
<?php $this->stop('content'); ?>
