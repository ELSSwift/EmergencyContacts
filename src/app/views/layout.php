<?php
$u = current_user();
$self = $_SERVER['REQUEST_URI'] ?? '';
$back = 'index.php' . (str_contains($self, '?') ? substr($self, strpos($self, '?')) : '');
$club = club_name();
$langs = enabled_langs();
?>
<!DOCTYPE html>
<html lang="<?= h(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= h($club) ?></title>
<link rel="stylesheet" href="assets/style.css?v=5">
<link rel="stylesheet" href="<?= h(url('theme.css', ['v' => substr(md5((string)setting('primary_color')), 0, 8)])) ?>">
<link rel="icon" href="<?= h(logo_url()) ?>">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="<?= h(url()) ?>"><img src="<?= h(logo_url()) ?>" alt=""> <span><?= h($club) ?></span></a>
    <nav class="nav" aria-label="<?= h(t('nav.main')) ?>">
      <?php if ($u): ?>
        <a href="<?= h(url('dashboard')) ?>"><?= h(t('nav.dashboard')) ?></a>
        <a href="<?= h(url('account')) ?>"><?= h(t('nav.account')) ?></a>
        <?php if (is_admin($u)): ?><a href="<?= h(url('admin')) ?>"><?= h(t('nav.admin')) ?></a><?php endif; ?>
        <form method="post" action="<?= h(url('logout')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="linklike"><?= h(t('nav.logout')) ?></button></form>
      <?php else: ?>
        <a href="<?= h(url('login')) ?>"><?= h(t('nav.login')) ?></a>
        <a href="<?= h(url('register')) ?>"><?= h(t('nav.register')) ?></a>
      <?php endif; ?>
      <?php if (count($langs) > 1): ?>
      <span class="langs">
        <?php foreach ($langs as $code): ?>
          <a href="<?= h(url('lang', ['l' => $code, 'back' => $back])) ?>" lang="<?= h($code) ?>" title="<?= h(SUPPORTED_LANGS[$code]) ?>"<?= $code === current_lang() ? ' aria-current="true" class="active"' : '' ?>><?= strtoupper(h($code)) ?></a>
        <?php endforeach; ?>
      </span>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if (config('env') === 'dev'): ?><div class="devbar">DEV<?php if (mail_transport() === 'file' && $u): ?> · <a href="<?= h(url('dev/mail')) ?>"><?= h(t('dev.outbox')) ?></a><?php endif; ?></div><?php endif; ?>
<main class="wrap">
  <?php foreach (take_flashes() as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>" role="status"><?= h($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="footer wrap">
  <span>© <?= date('Y') ?> <?= h($club) ?><?php if (setting('website')): ?> · <a href="<?= h(setting('website')) ?>" rel="noopener"><?= h(preg_replace('#^https?://#', '', rtrim((string)setting('website'), '/'))) ?></a><?php endif; ?></span>
  <span class="footer-links">
    <?php if (setting('contact_email')): ?><a href="mailto:<?= h(setting('contact_email')) ?>"><?= h(t('legal.contact')) ?></a><?php endif; ?>
    <a href="<?= h(url('legal')) ?>"><?= h(t('legal.imprint')) ?></a>
    <a href="<?= h(url('legal')) ?>#privacy"><?= h(t('legal.privacy')) ?></a>
  </span>
</footer>
<script src="assets/app.js?v=1"></script>
</body>
</html>
