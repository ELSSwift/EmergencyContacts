<?php /** @var array $data @var array $errors */ ?>
<fieldset>
  <legend><?= h(t('form.person')) ?></legend>
  <div class="row2">
    <?= field('first_name', t('field.first_name'), $data['first_name'], $errors, ['required' => true, 'autocomplete' => 'given-name']) ?>
    <?= field('last_name', t('field.last_name'), $data['last_name'], $errors, ['required' => true, 'autocomplete' => 'family-name']) ?>
  </div>
  <div class="row2">
    <?= field('birthdate', t('field.birthdate'), $data['birthdate'], $errors, ['type' => 'date', 'autocomplete' => 'bday', 'max' => today()]) ?>
    <?= field('phone', t('field.phone'), $data['phone'], $errors, ['type' => 'tel', 'autocomplete' => 'tel']) ?>
  </div>
  <?= field('email', t('field.email'), $data['email'], $errors, ['type' => 'email', 'autocomplete' => 'email']) ?>
  <?= field('address', t('field.address'), $data['address'], $errors, ['type' => 'textarea', 'rows' => 2, 'autocomplete' => 'street-address']) ?>
  <?= field('notify_email', t('field.notify_email'), $data['notify_email'], $errors, ['type' => 'email', 'required' => true, 'hint' => t('field.notify_email_hint')]) ?>
</fieldset>

<fieldset<?= isset($errors['contacts']) ? ' class="has-error"' : '' ?>>
  <legend><?= h(t('form.contacts')) ?></legend>
  <p class="hint"><?= h(t('form.contacts_hint')) ?></p>
  <?php if (isset($errors['contacts'])): ?><p class="error" role="alert"><?= h($errors['contacts']) ?></p><?php endif; ?>
  <?php foreach ($data['contacts'] as $i => $c): ?>
    <div class="contact-block">
      <h3><?= h(t('form.contact_n', ['n' => $i + 1])) ?><?= $i === 0 ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></h3>
      <div class="row2">
        <?= field("contacts[$i][name]", t('field.contact_name'), $c['name'], $errors, ['error_key' => "contacts.$i.name"]) ?>
        <?= field("contacts[$i][relation]", t('field.relation'), $c['relation'], $errors, ['placeholder' => t('field.relation_placeholder'), 'error_key' => "contacts.$i.relation"]) ?>
      </div>
      <div class="row2">
        <?= field("contacts[$i][phone]", t('field.phone'), $c['phone'], $errors, ['type' => 'tel', 'error_key' => "contacts.$i.phone"]) ?>
        <?= field("contacts[$i][email]", t('field.email'), $c['email'], $errors, ['type' => 'email', 'error_key' => "contacts.$i.email"]) ?>
      </div>
    </div>
  <?php endforeach; ?>
</fieldset>

<fieldset>
  <legend><?= h(t('form.medical')) ?></legend>
  <p class="hint"><?= h(t('form.medical_hint')) ?></p>
  <div class="row2">
    <?= field('blood_type', t('field.blood_type'), $data['blood_type'], $errors, ['placeholder' => t('field.blood_type_placeholder'), 'maxlength' => 10]) ?>
    <?= field('insurance', t('field.insurance'), $data['insurance'], $errors) ?>
  </div>
  <?= field('allergies', t('field.allergies'), $data['allergies'], $errors, ['type' => 'textarea', 'rows' => 2]) ?>
  <?= field('medications', t('field.medications'), $data['medications'], $errors, ['type' => 'textarea', 'rows' => 2]) ?>
  <?= field('conditions', t('field.conditions'), $data['conditions'], $errors, ['type' => 'textarea', 'rows' => 2]) ?>
  <?= field('doctor', t('field.doctor'), $data['doctor'], $errors) ?>
  <?= field('medical_notes', t('field.medical_notes'), $data['medical_notes'], $errors, ['type' => 'textarea', 'rows' => 3]) ?>
  <label class="check"><input type="checkbox" name="no_medical" value="1"<?= $data['no_medical'] ? ' checked' : '' ?>> <?= h(t('field.no_medical')) ?></label>
</fieldset>
