<?php $title = t('reg.done_title'); $link = absolute_url('mine', ['k' => $reg['edit_token']]); ?>
<div class="card narrow">
  <h1><?= h(t('reg.done_title')) ?></h1>
  <p><?= h(t('reg.done_text', ['event' => $event['title']])) ?></p>
  <?php if ($reg['user_id'] === null): ?>
    <div class="flash flash-warn"><?= h(t('reg.done_anon_hint', ['date' => format_date(access_until($event))])) ?></div>
    <div class="copy-row">
      <input type="text" readonly value="<?= h($link) ?>" id="editlink" aria-label="<?= h(t('reg.edit_link')) ?>">
      <button type="button" class="btn" data-copy="editlink" data-copied="<?= h(t('common.copied')) ?>"><?= h(t('common.copy')) ?></button>
    </div>
    <p class="hint"><?= h(t('reg.done_mail_hint')) ?></p>
  <?php endif; ?>
  <p><a class="btn" href="<?= h(url('mine', ['k' => $reg['edit_token']])) ?>"><?= h(t('reg.view_mine')) ?></a></p>
</div>
