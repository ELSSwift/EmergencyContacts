<?php $title = t('auth.login_title'); ?>
<div class="card narrow">
  <h1><?= h(t('auth.login_title')) ?></h1>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url('login')) ?>">
    <?= csrf_field() ?>
    <?= field('email', t('field.email'), $email, [], ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    <?= field('password', t('field.password'), '', [], ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn btn-primary"><?= h(t('auth.login_button')) ?></button>
  </form>
  <p class="muted"><?= h(t('auth.no_account')) ?> <a href="<?= h(url('register')) ?>"><?= h(t('nav.register')) ?></a></p>
</div>
