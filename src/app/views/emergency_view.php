<?php $title = t('emergency.title'); ?>
<div class="page-head no-print">
  <h1><?= h(t('emergency.title')) ?> – <?= h($event['title']) ?></h1>
  <div class="actions">
    <button type="button" class="btn" data-print><?= h(t('common.print')) ?></button>
    <a class="btn" href="<?= h(url('event', ['id' => $event['id']])) ?>"><?= h(t('common.back')) ?></a>
  </div>
</div>
<p class="muted"><?= h(t('emergency.logged', ['reason' => $reason])) ?></p>

<?php foreach ($people as $p): $d = $p['data']; ?>
<article class="card person">
  <h2><?= h(participant_label($d)) ?><?= $d['birthdate'] ? ' <small class="muted">(' . h(format_date($d['birthdate'])) . ')</small>' : '' ?></h2>
  <div class="cols">
    <div>
      <h3><?= h(t('form.contacts')) ?></h3>
      <?php $any = false; foreach ($d['contacts'] as $c): if ($c['name'] === '') continue; $any = true; ?>
        <div class="contact">
          <strong><?= h($c['name']) ?></strong><?= $c['relation'] !== '' ? ' · ' . h($c['relation']) : '' ?><br>
          <?php if ($c['phone'] !== ''): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>">📞 <?= h($c['phone']) ?></a><br><?php endif; ?>
          <?php if ($c['email'] !== ''): ?><a href="mailto:<?= h($c['email']) ?>">✉ <?= h($c['email']) ?></a><?php endif; ?>
        </div>
      <?php endforeach; if (!$any): ?><p class="bad"><?= h(t('emergency.no_contacts')) ?></p><?php endif; ?>
      <h3><?= h(t('form.person')) ?></h3>
      <dl>
        <?php if ($d['phone'] !== ''): ?><dt><?= h(t('field.phone')) ?></dt><dd><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $d['phone'])) ?>"><?= h($d['phone']) ?></a></dd><?php endif; ?>
        <?php if ($d['email'] !== ''): ?><dt><?= h(t('field.email')) ?></dt><dd><?= h($d['email']) ?></dd><?php endif; ?>
        <?php if ($d['address'] !== ''): ?><dt><?= h(t('field.address')) ?></dt><dd class="pre"><?= h($d['address']) ?></dd><?php endif; ?>
      </dl>
    </div>
    <div>
      <h3><?= h(t('form.medical')) ?></h3>
      <?php if ($p['reg']['medical_status'] === 'none'): ?>
        <p><?= h(t('overview.medical_none')) ?></p>
      <?php elseif ($p['reg']['medical_status'] === 'missing'): ?>
        <p class="warn"><?= h(t('overview.medical_missing')) ?></p>
      <?php else: ?>
      <dl>
        <?php foreach (MEDICAL_FIELDS as $f): if (trim($d[$f]) === '') continue; ?>
          <dt><?= h(t('field.' . $f)) ?></dt><dd class="pre<?= in_array($f, ['allergies', 'conditions'], true) ? ' highlight' : '' ?>"><?= h($d[$f]) ?></dd>
        <?php endforeach; ?>
      </dl>
      <?php endif; ?>
    </div>
  </div>
</article>
<?php endforeach; ?>
