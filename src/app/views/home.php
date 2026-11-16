<?php
$title = '';
$homeTitle = setting_text('home_title') ?: t('home.title');
$homeText = setting_text('home_text');
?>
<section class="hero">
  <p class="eyebrow"><?= h(club_name()) ?></p>
  <h1><?= h($homeTitle) ?></h1>
  <?php if ($homeText !== ''): ?>
    <p class="lead home-text"><?= h($homeText) ?></p>
  <?php else: ?>
    <p class="lead"><?= h(t('home.lead')) ?></p>
  <?php endif; ?>
  <div class="actions">
    <?php if ($user): ?>
      <?php if (can_organize($user)): ?><a class="btn btn-primary" href="<?= h(url('event/new')) ?>"><?= h(t('dashboard.new_event')) ?></a><?php endif; ?>
      <a class="btn<?= can_organize($user) ? '' : ' btn-primary' ?>" href="<?= h(url('dashboard')) ?>"><?= h(t('nav.dashboard')) ?></a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= h(url('login')) ?>"><?= h(t('nav.login')) ?></a>
      <a class="btn" href="<?= h(url('register')) ?>"><?= h(t('nav.register')) ?></a>
    <?php endif; ?>
  </div>
</section>

<section class="grid3">
  <div class="card">
    <h2><?= h(t('home.f1.title')) ?></h2>
    <p><?= h(t('home.f1.text')) ?></p>
  </div>
  <div class="card">
    <h2><?= h(t('home.f2.title')) ?></h2>
    <p><?= h(t('home.f2.text')) ?></p>
  </div>
  <div class="card">
    <h2><?= h(t('home.f3.title')) ?></h2>
    <p><?= h(t('home.f3.text')) ?></p>
  </div>
</section>
<p class="muted"><?= h(t('home.participant_hint')) ?></p>
