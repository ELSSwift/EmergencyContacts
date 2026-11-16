<?php $title = t('admin.title'); ?>
<div class="page-head">
  <h1><?= h(t('admin.title')) ?></h1>
  <a class="btn" href="<?= h(url('admin/users')) ?>"><?= h(t('admin.users_title')) ?></a>
</div>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><?= h(t('validation.check_form')) ?></div><?php endif; ?>

<section class="card" id="logo">
  <h2><?= h(t('admin.logo')) ?></h2>
  <div class="logo-preview">
    <img src="<?= h(logo_url()) ?>" alt="<?= h(t('admin.logo')) ?>">
    <?php if (logo_path()): ?>
      <form method="post" action="<?= h(url('admin')) ?>" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="logo_remove">
        <button type="submit" class="btn btn-small"><?= h(t('admin.logo_remove')) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <form method="post" action="<?= h(url('admin')) ?>" enctype="multipart/form-data" class="copy-row">
    <?= csrf_field() ?><input type="hidden" name="action" value="logo">
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required aria-label="<?= h(t('admin.logo')) ?>">
    <button type="submit" class="btn"><?= h(t('admin.logo_upload')) ?></button>
  </form>
  <p class="hint"><?= h(t('admin.logo_hint')) ?></p>
</section>

<section class="card" id="mail">
  <h2><?= h(t('admin.mail')) ?></h2>
  <p class="hint"><?= h(t('admin.mail_hint')) ?></p>
  <form method="post" action="<?= h(url('admin')) ?>#mail" autocomplete="off">
    <?= csrf_field() ?>
    <?= field('mail_transport', t('admin.mail_transport'), $s['mail_transport'], $errors, ['type' => 'select', 'options' => [
        'smtp' => t('admin.transport_smtp'),
        'mail' => t('admin.transport_mail'),
        'file' => t('admin.transport_file'),
    ]]) ?>
    <?php if (($s['mail_transport'] ?? '') === 'file'): ?>
      <p class="hint"><?= h(t('admin.transport_file_hint')) ?> <a href="<?= h(url('dev/mail')) ?>"><?= h(t('admin.mail_outbox')) ?></a></p>
    <?php endif; ?>
    <div class="row2">
      <?= field('mail_from', t('admin.mail_from'), $s['mail_from'], $errors, ['type' => 'email', 'required' => true, 'placeholder' => 'noreply@meinverein.ch']) ?>
      <?= field('mail_from_name', t('admin.mail_from_name'), $s['mail_from_name'], $errors, ['placeholder' => club_name(), 'hint' => t('admin.mail_from_name_hint')]) ?>
    </div>
    <?= field('mail_reply_to', t('admin.mail_reply_to'), $s['mail_reply_to'], $errors, ['type' => 'email', 'placeholder' => (string)setting('contact_email'), 'hint' => t('admin.mail_reply_to_hint')]) ?>

    <fieldset class="subsection">
      <legend><?= h(t('admin.smtp')) ?></legend>
      <p class="hint"><?= h(t('admin.smtp_hint')) ?></p>
      <div class="row2">
        <?= field('mail_smtp_host', t('admin.smtp_host'), $s['mail_smtp_host'], $errors, ['placeholder' => 'smtp.meinverein.ch']) ?>
        <?= field('mail_smtp_port', t('admin.smtp_port'), $s['mail_smtp_port'], $errors, ['type' => 'number', 'min' => 1, 'max' => 65535]) ?>
      </div>
      <?= field('mail_smtp_secure', t('admin.smtp_secure'), $s['mail_smtp_secure'], $errors, ['type' => 'select', 'options' => [
          'tls' => t('admin.smtp_secure_tls'),
          'ssl' => t('admin.smtp_secure_ssl'),
          '' => t('admin.smtp_secure_none'),
      ]]) ?>
      <div class="row2">
        <?= field('mail_smtp_user', t('admin.smtp_user'), $s['mail_smtp_user'], $errors, ['autocomplete' => 'off']) ?>
        <?= field('mail_smtp_pass', t('admin.smtp_pass'), '', $errors, ['type' => 'password', 'autocomplete' => 'new-password',
            'hint' => has_smtp_password() ? t('admin.smtp_pass_set') : t('admin.smtp_pass_hint')]) ?>
      </div>
      <?php if (has_smtp_password()): ?>
        <label class="check"><input type="checkbox" name="mail_smtp_pass_clear" value="1"> <?= h(t('admin.smtp_pass_clear')) ?></label>
      <?php endif; ?>
    </fieldset>

    <div class="actions">
      <button type="submit" name="action" value="mail" class="btn btn-primary"><?= h(t('common.save')) ?></button>
      <button type="submit" name="action" value="mail_test" class="btn"><?= h(t('admin.mail_save_test')) ?></button>
    </div>
    <p class="hint"><?= h(t('admin.mail_test_hint')) ?></p>
  </form>
</section>

<form method="post" action="<?= h(url('admin')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="settings">

  <section class="card">
    <h2><?= h(t('admin.general')) ?></h2>
    <?= field('club_name', t('admin.club_name'), $s['club_name'], $errors, ['required' => true, 'hint' => t('admin.club_name_hint')]) ?>
    <div class="field<?= isset($errors['primary_color']) ? ' has-error' : '' ?>">
      <label for="f_primary_color"><?= h(t('admin.primary_color')) ?></label>
      <div class="color-row">
        <input type="color" id="f_primary_color" name="primary_color" value="<?= h(valid_hex_color((string)$s['primary_color']) ? $s['primary_color'] : '#b3261e') ?>">
        <span class="muted"><?= h(t('admin.primary_color_hint')) ?></span>
      </div>
      <?php if (isset($errors['primary_color'])): ?><small class="error"><?= h($errors['primary_color']) ?></small><?php endif; ?>
    </div>
    <div class="row2">
      <?= field('contact_email', t('admin.contact_email'), $s['contact_email'], $errors, ['type' => 'email', 'hint' => t('admin.contact_email_hint')]) ?>
      <?= field('website', t('admin.website'), $s['website'], $errors, ['type' => 'url', 'placeholder' => 'https://']) ?>
    </div>
    <?= field('base_url', t('admin.base_url'), $s['base_url'], $errors, ['type' => 'url', 'placeholder' => preg_replace('#/index\.php$#', '', absolute_url()), 'hint' => t('admin.base_url_hint')]) ?>
    <p class="hint"><?= h(t('admin.config_file_hint')) ?></p>
  </section>

  <section class="card">
    <h2><?= h(t('admin.langs_defaults')) ?></h2>
    <div class="field<?= isset($errors['enabled_langs']) ? ' has-error' : '' ?>">
      <label><?= h(t('admin.enabled_langs')) ?></label>
      <div class="check-row">
        <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
          <label class="check"><input type="checkbox" name="enabled_langs[]" value="<?= h($code) ?>"<?= in_array($code, (array)$s['enabled_langs'], true) ? ' checked' : '' ?>> <?= h($name) ?></label>
        <?php endforeach; ?>
      </div>
      <?php if (isset($errors['enabled_langs'])): ?><small class="error"><?= h($errors['enabled_langs']) ?></small><?php endif; ?>
    </div>
    <div class="row2">
      <div class="field<?= isset($errors['default_lang']) ? ' has-error' : '' ?>">
        <label for="f_default_lang"><?= h(t('admin.default_lang')) ?></label>
        <select id="f_default_lang" name="default_lang">
          <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
            <option value="<?= h($code) ?>"<?= $code === $s['default_lang'] ? ' selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['default_lang'])): ?><small class="error"><?= h($errors['default_lang']) ?></small><?php endif; ?>
      </div>
      <?= field('default_retention_days', t('admin.default_retention'), $s['default_retention_days'], $errors, ['type' => 'number', 'min' => 0, 'max' => 90, 'hint' => t('admin.default_retention_hint')]) ?>
    </div>
  </section>

  <section class="card">
    <h2><?= h(t('admin.home')) ?></h2>
    <p class="hint"><?= h(t('admin.home_hint')) ?></p>
    <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
      <div class="lang-block">
        <h3><?= h($name) ?></h3>
        <?= field('home_title_' . $code, t('admin.home_title'), $s['home_title_' . $code] ?? '', $errors, ['placeholder' => t_lang($code, 'home.title')]) ?>
        <?= field('home_text_' . $code, t('admin.home_text'), $s['home_text_' . $code] ?? '', $errors, ['type' => 'textarea', 'rows' => 3, 'placeholder' => t_lang($code, 'home.lead')]) ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2><?= h(t('legal.title')) ?></h2>
    <?= field('imprint', t('legal.imprint'), $s['imprint'], $errors, ['type' => 'textarea', 'rows' => 5, 'hint' => t('admin.imprint_hint')]) ?>
    <p class="hint"><?= h(t('admin.privacy_hint')) ?></p>
    <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
      <?= field('privacy_' . $code, t('legal.privacy') . ' – ' . $name, $s['privacy_' . $code] ?? '', $errors, ['type' => 'textarea', 'rows' => 5]) ?>
    <?php endforeach; ?>
  </section>

  <div class="actions"><button type="submit" class="btn btn-primary"><?= h(t('common.save')) ?></button></div>
</form>
