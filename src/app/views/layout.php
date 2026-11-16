<?php $u = current_user(); $self = $_SERVER['REQUEST_URI'] ?? ''; $back = 'index.php' . (str_contains($self, '?') ? substr($self, strpos($self, '?')) : ''); ?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="assets/style.css?v=1">
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="<?= h(url()) ?>"><img src="assets/icon.svg" alt="" width="28" height="28"> <span><?= h(config('app_name')) ?></span></a>
    <nav class="nav" aria-label="<?= h(t('nav.main')) ?>">
      <?php if ($u): ?>
        <a href="<?= h(url('dashboard')) ?>"><?= h(t('nav.dashboard')) ?></a>
        <a href="<?= h(url('account')) ?>"><?= h(t('nav.account')) ?></a>
        <form method="post" action="<?= h(url('logout')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linklike"><?= h(t('nav.logout')) ?></button></form>
      <?php else: ?>
        <a href="<?= h(url('login')) ?>"><?= h(t('nav.login')) ?></a>
        <a href="<?= h(url('register')) ?>"><?= h(t('nav.register')) ?></a>
      <?php endif; ?>
      <span class="langs">
        <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
          <a href="<?= h(url('lang', ['l' => $code, 'back' => $back])) ?>" lang="<?= h($code) ?>" title="<?= h($name) ?>"<?= $code === current_lang() ? ' aria-current="true" class="active"' : '' ?>><?= strtoupper(h($code)) ?></a>
        <?php endforeach; ?>
      </span>
    </nav>
  </div>
</header>
<?php if (config('env') === 'dev'): ?><div class="devbar">DEV<?php if (config('mail.transport') === 'file' && $u): ?> · <a href="<?= h(url('dev/mail')) ?>"><?= h(t('dev.outbox')) ?></a><?php endif; ?></div><?php endif; ?>
<main class="wrap">
  <?php foreach (take_flashes() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>" role="status"><?= h($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="footer wrap">
  <span><?= h(config('app_name')) ?></span>
  <span><?= h(t('footer.privacy')) ?></span>
</footer>
<script src="assets/app.js?v=1"></script>
</body>
</html>
