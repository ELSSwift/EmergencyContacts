<?php $title = $isNew ? t('event.new_title') : t('event.edit_title'); ?>
<div class="card">
  <h1><?= h($title) ?></h1>
  <form method="post" action="<?= h($isNew ? url('event/new') : url('event/edit', ['id' => $event['id']])) ?>">
    <?= csrf_field() ?>
    <?= field('title', t('event.title'), $event['title'], $errors, ['required' => true]) ?>
    <?= field('location', t('event.location'), $event['location'], $errors) ?>
    <div class="row2">
      <?= field('starts_on', t('event.starts_on'), $event['starts_on'], $errors, ['type' => 'date', 'required' => true]) ?>
      <?= field('ends_on', t('event.ends_on'), $event['ends_on'], $errors, ['type' => 'date', 'required' => true]) ?>
    </div>
    <?= field('description', t('event.description'), $event['description'], $errors, ['type' => 'textarea', 'rows' => 4, 'hint' => t('event.description_hint')]) ?>
    <?= field('retention_days', t('event.retention_days'), $event['retention_days'], $errors, ['type' => 'number', 'min' => 0, 'max' => 90, 'required' => true, 'hint' => t('event.retention_hint')]) ?>
    <label class="check"><input type="checkbox" name="registration_open" value="1"<?= !empty($event['registration_open']) ? ' checked' : '' ?>> <?= h(t('event.registration_open')) ?></label>
    <div class="actions">
      <button type="submit" class="btn btn-primary"><?= h($isNew ? t('event.create_button') : t('common.save')) ?></button>
      <a class="btn" href="<?= h($isNew ? url('dashboard') : url('event', ['id' => $event['id']])) ?>"><?= h(t('common.cancel')) ?></a>
    </div>
  </form>
</div>
