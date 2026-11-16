<?php $title = t('admin.users_title'); ?>
<div class="page-head">
  <h1><?= h(t('admin.users_title')) ?></h1>
  <a class="btn" href="<?= h(url('admin')) ?>"><?= h(t('admin.title')) ?></a>
</div>
<section class="card">
  <p class="muted"><?= h(t('admin.users_intro')) ?></p>
  <form method="get" action="index.php" class="search-row">
    <input type="hidden" name="r" value="admin/users">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="<?= h(t('admin.users_search')) ?>" aria-label="<?= h(t('admin.users_search')) ?>">
    <button type="submit" class="btn"><?= h(t('admin.search')) ?></button>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th><?= h(t('field.name')) ?></th><th><?= h(t('field.email')) ?></th><th><?= h(t('dashboard.my_events')) ?></th><th><?= h(t('admin.role')) ?></th><th><span class="sr-only"><?= h(t('admin.delete_user')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= h($u['name']) ?></td>
        <td><?= h($u['email']) ?></td>
        <td><?= (int)$u['events'] ?></td>
        <td>
          <form method="post" action="<?= h(url('admin/users', ['q' => $q])) ?>" class="copy-row">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <select name="role" aria-label="<?= h(t('admin.role')) ?>"<?= (int)$u['id'] === (int)$user['id'] ? ' disabled' : '' ?>>
              <?php foreach (ROLES as $r): ?><option value="<?= h($r) ?>"<?= $r === $u['role'] ? ' selected' : '' ?>><?= h(t('role.' . $r)) ?></option><?php endforeach; ?>
            </select>
            <?php if ((int)$u['id'] !== (int)$user['id']): ?><button type="submit" class="btn btn-small"><?= h(t('common.save')) ?></button><?php endif; ?>
          </form>
        </td>
        <td>
          <?php if ((int)$u['id'] !== (int)$user['id']): ?>
          <form method="post" action="<?= h(url('admin/users', ['q' => $q])) ?>" data-confirm="<?= h(t('admin.delete_confirm', ['name' => $u['name'], 'email' => $u['email']]) . ((int)$u['open_events'] > 0 ? ' ' . t('admin.delete_confirm_events', ['count' => (int)$u['open_events']]) : '')) ?>">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-small btn-danger-outline"><?= h(t('admin.delete_user')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint"><?= h(t('admin.roles_hint')) ?></p>
  <p class="hint"><?= h(t('admin.delete_hint')) ?></p>
</section>
