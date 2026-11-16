<?php $title = t('error.title'); ?>
<div class="card narrow">
  <h1><?= h(t('error.title')) ?></h1>
  <p><?= h($message) ?></p>
  <p><a class="btn" href="<?= h(url()) ?>"><?= h(t('common.home')) ?></a></p>
</div>
