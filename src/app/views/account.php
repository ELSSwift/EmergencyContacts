<?php $title = t('account.title'); ?>
<h1><?= h(t('account.title')) ?></h1>
<p class="muted"><?= h(t('account.logged_in_as', ['name' => $user['name'], 'email' => $user['email']])) ?></p>

<section class="card">
  <h2><?= h(t('profile.title')) ?></h2>
  <p class="muted"><?= h(t('profile.intro')) ?></p>
  <form method="post" action="<?= h(url('account')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <?php $data = $profile; require __DIR__ . '/_participant_fields.php'; ?>
    <button type="submit" class="btn btn-primary"><?= h(t('common.save')) ?></button>
  </form>
</section>

<section class="card">
  <h2><?= h(t('account.change_password')) ?></h2>
  <form method="post" action="<?= h(url('account')) ?>" class="narrow-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <?= field('current_password', t('field.current_password'), '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= field('new_password', t('field.new_password'), '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => t('auth.password_hint')]) ?>
    <button type="submit" class="btn"><?= h(t('account.change_password')) ?></button>
  </form>
</section>

<section class="card danger">
  <h2><?= h(t('account.delete_title')) ?></h2>
  <p><?= h(t('account.delete_text')) ?></p>
  <form method="post" action="<?= h(url('account')) ?>" class="narrow-form" data-confirm="<?= h(t('account.delete_confirm')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <?= field('confirm_password', t('field.password'), '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn btn-danger"><?= h(t('account.delete_button')) ?></button>
  </form>
</section>
