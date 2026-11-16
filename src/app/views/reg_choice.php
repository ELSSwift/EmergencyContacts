<?php $title = $event['title']; $here = url('reg', ['t' => $token]); ?>
<?php require __DIR__ . '/_event_header.php'; ?>
<p><?= h(t('reg.intro')) ?></p>
<div class="grid2">
  <div class="card choice">
    <h2><?= h(t('reg.choice_account')) ?></h2>
    <p><?= h(t('reg.choice_account_text')) ?></p>
    <div class="actions">
      <a class="btn btn-primary" href="<?= h(url('login', ['next' => $here])) ?>"><?= h(t('nav.login')) ?></a>
      <a class="btn" href="<?= h(url('register')) ?>" data-next="<?= h($here) ?>"><?= h(t('nav.register')) ?></a>
    </div>
  </div>
  <div class="card choice">
    <h2><?= h(t('reg.choice_anon')) ?></h2>
    <p><?= h(t('reg.choice_anon_text', ['date' => format_date(access_until($event))])) ?></p>
    <div class="actions">
      <a class="btn btn-primary" href="<?= h(url('reg', ['t' => $token, 'mode' => 'anon'])) ?>"><?= h(t('reg.choice_anon_button')) ?></a>
    </div>
  </div>
</div>
<?php $_SESSION['after_login'] = $here; ?>
