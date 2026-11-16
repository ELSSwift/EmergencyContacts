<?php $title = t('dev.outbox'); ?>
<h1><?= h(t('dev.outbox')) ?></h1>
<p class="muted"><?= h(t('dev.outbox_hint')) ?></p>
<?php if (!$mails): ?><p class="muted">–</p><?php endif; ?>
<?php foreach ($mails as $m): ?>
  <article class="card mail">
    <p class="muted"><?= h(format_datetime($m['date'] ?? '')) ?> · <?= h($m['to'] ?? '') ?></p>
    <h2><?= h($m['subject'] ?? '') ?></h2>
    <pre><?= h($m['body'] ?? '') ?></pre>
  </article>
<?php endforeach; ?>
