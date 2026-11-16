<?php
$title = $event['title'];
$e = $event;
$active = event_access_active($event);
$regLink = absolute_url('reg', ['t' => $event['reg_token']]);
$total = count($rows);
$withContact = count(array_filter($rows, fn($r) => $r['has_contact']));
$withMedical = count(array_filter($rows, fn($r) => $r['medical_status'] !== 'missing'));
?>
<div class="page-head">
  <div>
    <h1><?= h($event['title']) ?></h1>
    <p class="muted">
      <?= h(format_date($event['starts_on'])) ?><?= $event['ends_on'] !== $event['starts_on'] ? ' – ' . h(format_date($event['ends_on'])) : '' ?>
      <?= $event['location'] !== '' ? ' · ' . h($event['location']) : '' ?>
      · <?php require __DIR__ . '/_event_status.php'; ?>
    </p>
  </div>
  <a class="btn" href="<?= h(url('event/edit', ['id' => $event['id']])) ?>"><?= h(t('common.edit')) ?></a>
</div>

<?php if ($active): ?>
<section class="card emergency-card">
  <div>
    <h2><?= h(t('emergency.title')) ?></h2>
    <p><?= h(t('emergency.intro', ['date' => format_date(access_until($event))])) ?></p>
  </div>
  <?php if ($total > 0): ?>
    <a class="btn btn-danger" href="<?= h(url('emergency', ['id' => $event['id'], 'reg' => 'all'])) ?>"><?= h(t('emergency.all_button')) ?></a>
  <?php endif; ?>
</section>
<?php else: ?>
<div class="flash flash-info"><?= h(t('event.access_ended', ['date' => format_date(access_until($event))])) ?></div>
<?php endif; ?>

<?php if (event_registration_open($event)): ?>
<section class="card">
  <h2><?= h(t('event.reg_link')) ?></h2>
  <p class="muted"><?= h(t('event.reg_link_hint')) ?></p>
  <div class="copy-row">
    <input type="text" readonly value="<?= h($regLink) ?>" id="reglink" aria-label="<?= h(t('event.reg_link')) ?>">
    <button type="button" class="btn" data-copy="reglink" data-copied="<?= h(t('common.copied')) ?>"><?= h(t('common.copy')) ?></button>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <h2><?= h(t('overview.title')) ?></h2>
  <div class="stats">
    <div class="stat"><strong><?= $total ?></strong><span><?= h(t('overview.total')) ?></span></div>
    <div class="stat"><strong><?= $withContact ?>/<?= $total ?></strong><span><?= h(t('overview.with_contact')) ?></span></div>
    <div class="stat"><strong><?= $withMedical ?>/<?= $total ?></strong><span><?= h(t('overview.with_medical')) ?></span></div>
  </div>
  <?php if (!$rows): ?>
    <p class="muted"><?= h(t('overview.empty')) ?></p>
  <?php else: ?>
  <p class="hint"><?= h(t('overview.hint')) ?></p>
  <div class="table-wrap"><table class="overview">
    <thead><tr>
      <th><?= h(t('overview.name')) ?></th>
      <th><?= h(t('overview.contact')) ?></th>
      <th><?= h(t('overview.methods')) ?></th>
      <th><?= h(t('overview.medical')) ?></th>
      <th><?= h(t('overview.updated')) ?></th>
      <?php if ($active): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['label']) ?><?= $r['has_account'] ? ' <span class="badge badge-muted" title="' . h(t('overview.account_hint')) . '">' . h(t('overview.account')) . '</span>' : '' ?></td>
        <td><?= $r['has_contact'] ? '<span class="ok">✔ ' . h(t('common.yes')) . '</span>' : '<span class="bad">✖ ' . h(t('common.no')) . '</span>' ?></td>
        <td><?php if (!$r['methods']): ?><span class="bad">–</span><?php else: ?><?php foreach ($r['methods'] as $m): ?><span class="badge"><?= h(t('method.' . $m)) ?></span> <?php endforeach; ?><?php endif; ?></td>
        <td><?php
          echo match ($r['medical_status']) {
              'provided' => '<span class="ok">✔ ' . h(t('overview.medical_provided')) . '</span>',
              'none' => '<span class="ok">○ ' . h(t('overview.medical_none')) . '</span>',
              default => '<span class="warn">! ' . h(t('overview.medical_missing')) . '</span>',
          };
        ?></td>
        <td><?= h(format_datetime($r['updated_at'])) ?></td>
        <?php if ($active): ?><td><a class="btn btn-small btn-danger-outline" href="<?= h(url('emergency', ['id' => $event['id'], 'reg' => $r['id']])) ?>"><?= h(t('emergency.open_button')) ?></a></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="card" id="team">
  <h2><?= h(t('team.title')) ?></h2>
  <ul class="team-list">
    <?php foreach ($team as $m): ?>
      <li>
        <span><?= h($m['name']) ?> <small class="muted">&lt;<?= h($m['email']) ?>&gt;</small> <?= $m['role'] === 'owner' ? '<span class="badge">' . h(t('team.owner')) . '</span>' : '' ?></span>
        <?php if ($role === 'owner' && $m['role'] !== 'owner'): ?>
          <form method="post" action="<?= h(url('event/team')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn btn-small"><?= h(t('team.remove')) ?></button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($role === 'owner'): ?>
    <form method="post" action="<?= h(url('event/team')) ?>" class="copy-row">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="action" value="add">
      <input type="email" name="email" required placeholder="<?= h(t('team.add_placeholder')) ?>" aria-label="<?= h(t('team.add_placeholder')) ?>">
      <button type="submit" class="btn"><?= h(t('team.add')) ?></button>
    </form>
    <p class="hint"><?= h(t('team.add_hint')) ?></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2><?= h(t('log.title')) ?></h2>
  <?php if (!$log): ?>
    <p class="muted"><?= h(t('log.empty')) ?></p>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= h(t('log.when')) ?></th><th><?= h(t('log.who')) ?></th><th><?= h(t('log.person')) ?></th><th><?= h(t('log.reason')) ?></th><th><?= h(t('log.notified')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($log as $l): ?>
        <tr><td><?= h(format_datetime($l['accessed_at'])) ?></td><td><?= h($l['accessor_name']) ?></td><td><?= h($l['participant_label']) ?></td><td><?= h($l['reason']) ?></td><td><?= $l['notified'] ? '✔' : '✖' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<?php if ($role === 'owner'): ?>
<section class="card danger" id="danger">
  <h2><?= h(t('event.delete_title')) ?></h2>
  <p><?= h(t('event.delete_text')) ?></p>
  <form method="post" action="<?= h(url('event/delete')) ?>" class="copy-row">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
    <input type="text" name="confirm" required placeholder="<?= h($event['title']) ?>" aria-label="<?= h(t('event.delete_confirm_label')) ?>">
    <button type="submit" class="btn btn-danger"><?= h(t('event.delete_button')) ?></button>
  </form>
  <p class="hint"><?= h(t('event.delete_confirm_label')) ?></p>
</section>
<?php endif; ?>
