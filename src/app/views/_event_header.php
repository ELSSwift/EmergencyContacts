<div class="event-header">
  <p class="eyebrow"><?= h(t('reg.eyebrow')) ?></p>
  <h1><?= h($event['title']) ?></h1>
  <p class="muted">
    <?= h(format_date($event['starts_on'])) ?><?= $event['ends_on'] !== $event['starts_on'] ? ' – ' . h(format_date($event['ends_on'])) : '' ?>
    <?= $event['location'] !== '' ? ' · ' . h($event['location']) : '' ?>
  </p>
  <?php if ($event['description'] !== ''): ?><p class="pre"><?= h($event['description']) ?></p><?php endif; ?>
</div>
