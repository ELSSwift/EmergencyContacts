<?php $title = t('nav.dashboard'); ?>
<div class="page-head">
  <h1><?= h(t('dashboard.title', ['name' => $user['name']])) ?></h1>
  <a class="btn btn-primary" href="<?= h(url('event/new')) ?>"><?= h(t('dashboard.new_event')) ?></a>
</div>

<section class="card">
  <h2><?= h(t('dashboard.my_events')) ?></h2>
  <?php if (!$events): ?>
    <p class="muted"><?= h(t('dashboard.no_events')) ?></p>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= h(t('event.title')) ?></th><th><?= h(t('event.date')) ?></th><th><?= h(t('event.registrations')) ?></th><th><?= h(t('event.status')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($events as $e): ?>
        <tr>
          <td><a href="<?= h(url('event', ['id' => $e['id']])) ?>"><?= h($e['title']) ?></a><?php if ($e['role'] === 'owner'): ?> <span class="badge"><?= h(t('team.owner')) ?></span><?php endif; ?></td>
          <td><?= h(format_date($e['starts_on'])) ?><?= $e['ends_on'] !== $e['starts_on'] ? ' – ' . h(format_date($e['ends_on'])) : '' ?></td>
          <td><?= (int)$e['reg_count'] ?></td>
          <td><?php require __DIR__ . '/_event_status.php'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<section class="card">
  <h2><?= h(t('dashboard.my_registrations')) ?></h2>
  <?php if (!$registrations): ?>
    <p class="muted"><?= h(t('dashboard.no_registrations')) ?></p>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= h(t('event.title')) ?></th><th><?= h(t('event.date')) ?></th><th><?= h(t('reg.accesses')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($registrations as $r): ?>
        <tr>
          <td><?= h($r['title']) ?></td>
          <td><?= h(format_date($r['starts_on'])) ?></td>
          <td><?= (int)$r['access_count'] > 0 ? '<span class="badge badge-warn">' . (int)$r['access_count'] . '</span>' : '0' ?></td>
          <td><a href="<?= h(url('mine', ['k' => $r['edit_token']])) ?>"><?= h(t('common.open')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <p class="hint"><?= h(t('dashboard.profile_hint')) ?> <a href="<?= h(url('account')) ?>"><?= h(t('nav.account')) ?></a></p>
</section>
