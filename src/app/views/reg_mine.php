<?php $title = t('reg.mine_title'); $token = $event['reg_token']; ?>
<?php require __DIR__ . '/_event_header.php'; ?>

<section class="card">
  <h2><?= h(t('reg.access_title')) ?></h2>
  <?php if (!$log): ?>
    <p class="ok"><?= h(t('reg.no_access')) ?></p>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= h(t('log.when')) ?></th><th><?= h(t('log.who')) ?></th><th><?= h(t('log.reason')) ?></th></tr></thead>
      <tbody><?php foreach ($log as $l): ?><tr><td><?= h(format_datetime($l['accessed_at'])) ?></td><td><?= h($l['accessor_name']) ?></td><td><?= h($l['reason']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
  <p class="hint"><?= h($reg['user_id'] === null ? t('reg.retention_anon', ['date' => format_date(access_until($event))]) : t('reg.retention_account', ['date' => format_date(access_until($event))])) ?></p>
</section>

<section class="card">
  <h2><?= h(t('reg.mine_title')) ?></h2>
  <?php if (!$canEdit): ?>
    <p class="muted"><?= h(t('reg.edit_closed')) ?></p>
  <?php else: ?>
    <?php if ($errors): ?><div class="flash flash-error" role="alert"><?= h(t('validation.check_form')) ?></div><?php endif; ?>
    <form method="post" action="<?= h(url('mine', ['k' => $reg['edit_token']])) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php require __DIR__ . '/_participant_fields.php'; ?>
      <?php if ($user && $reg['user_id'] !== null): ?>
        <label class="check"><input type="checkbox" name="save_profile" value="1"> <?= h(t('reg.save_profile')) ?></label>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= h(t('common.save')) ?></button>
    </form>
  <?php endif; ?>
</section>

<section class="card danger">
  <h2><?= h(t('reg.delete_title')) ?></h2>
  <p><?= h(t('reg.delete_text')) ?></p>
  <form method="post" action="<?= h(url('mine', ['k' => $reg['edit_token']])) ?>" data-confirm="<?= h(t('reg.delete_confirm')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button type="submit" class="btn btn-danger"><?= h(t('reg.delete_button')) ?></button>
  </form>
</section>
