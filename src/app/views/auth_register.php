<?php $title = t('auth.register_title'); ?>
<div class="card narrow">
  <h1><?= h(t('auth.register_title')) ?></h1>
  <p class="muted"><?= h(t('auth.register_intro')) ?></p>
  <form method="post" action="<?= h(url('register')) ?>">
    <?= csrf_field() ?>
    <?= field('name', t('field.name'), $form['name'], $errors, ['required' => true, 'autocomplete' => 'name']) ?>
    <?= field('email', t('field.email'), $form['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?= field('password', t('field.password'), '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => t('auth.password_hint')]) ?>
    <?= field('password2', t('field.password_repeat'), '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn-primary"><?= h(t('auth.register_button')) ?></button>
  </form>
  <p class="muted"><?= h(t('auth.have_account')) ?> <a href="<?= h(url('login')) ?>"><?= h(t('nav.login')) ?></a></p>
</div>
