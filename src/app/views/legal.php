<?php $title = t('legal.title'); ?>
<h1><?= h(t('legal.title')) ?></h1>
<section class="card">
  <h2><?= h(t('legal.imprint')) ?></h2>
  <?php if ($imprint !== ''): ?>
    <p class="legal"><?= h($imprint) ?></p>
  <?php else: ?>
    <p class="legal"><?= h(club_name()) ?></p>
  <?php endif; ?>
  <?php if (setting('contact_email')): ?><p><?= h(t('legal.contact')) ?>: <a href="mailto:<?= h(setting('contact_email')) ?>"><?= h(setting('contact_email')) ?></a></p><?php endif; ?>
  <?php if (setting('website')): ?><p><a href="<?= h(setting('website')) ?>" rel="noopener"><?= h(setting('website')) ?></a></p><?php endif; ?>
</section>
<section class="card" id="privacy">
  <h2><?= h(t('legal.privacy')) ?></h2>
  <?php if ($privacy !== ''): ?>
    <p class="legal"><?= h($privacy) ?></p>
  <?php else: ?>
    <p><?= h(t('legal.privacy_default', ['club' => club_name()])) ?></p>
  <?php endif; ?>
</section>
