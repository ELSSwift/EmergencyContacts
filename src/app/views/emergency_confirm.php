<?php $title = t('emergency.title'); ?>
<div class="card narrow emergency-confirm">
  <h1><?= h(t('emergency.title')) ?></h1>
  <p><strong><?= h($event['title']) ?></strong></p>
  <p><?= h($regId === 'all' ? t('emergency.confirm_all', ['n' => count($labels)]) : t('emergency.confirm_one', ['name' => $labels[0]])) ?></p>
  <div class="flash flash-warn"><?= h(t('emergency.warning')) ?></div>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url('emergency', ['id' => $event['id'], 'reg' => $regId])) ?>">
    <?= csrf_field() ?>
    <?= field('reason', t('emergency.reason'), $_POST['reason'] ?? '', [], ['type' => 'textarea', 'rows' => 3, 'required' => true, 'hint' => t('emergency.reason_hint')]) ?>
    <label class="check"><input type="checkbox" name="confirm" value="1" required> <?= h(t('emergency.confirm_check')) ?></label>
    <div class="actions">
      <button type="submit" class="btn btn-danger"><?= h(t('emergency.show_button')) ?></button>
      <a class="btn" href="<?= h(url('event', ['id' => $event['id']])) ?>"><?= h(t('common.cancel')) ?></a>
    </div>
  </form>
</div>
