<?php $title = $event['title']; ?>
<?php require __DIR__ . '/_event_header.php'; ?>
<div class="card">
  <?php if ($user): ?>
    <p class="muted"><?= h(t('reg.as_user', ['name' => $user['name']])) ?></p>
  <?php else: ?>
    <p class="muted"><?= h(t('reg.as_anon', ['date' => format_date(access_until($event))])) ?></p>
  <?php endif; ?>
  <?php if ($errors): ?><div class="flash flash-error" role="alert"><?= h(t('validation.check_form')) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url('reg', ['t' => $token, 'mode' => $user ? null : 'anon'])) ?>" novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_participant_fields.php'; ?>
    <fieldset>
      <legend><?= h(t('reg.consent_title')) ?></legend>
      <p class="hint"><?= h(t('reg.consent_text', ['date' => format_date(access_until($event))])) ?></p>
      <label class="check<?= isset($errors['consent']) ? ' has-error' : '' ?>"><input type="checkbox" name="consent" value="1"<?= !empty($_POST['consent']) ? ' checked' : '' ?>> <?= h(t('reg.consent_check')) ?></label>
      <?php if (isset($errors['consent'])): ?><small class="error"><?= h($errors['consent']) ?></small><?php endif; ?>
      <?php if ($user): ?>
        <label class="check"><input type="checkbox" name="save_profile" value="1" checked> <?= h(t('reg.save_profile')) ?></label>
      <?php endif; ?>
    </fieldset>
    <button type="submit" class="btn btn-primary"><?= h(t('reg.submit')) ?></button>
  </form>
</div>
