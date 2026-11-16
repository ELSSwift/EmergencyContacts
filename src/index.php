<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

maybe_run_retention();

$route = get_str('r', 50);

// Sprachwechsel
if ($route === 'lang') {
    $lang = get_str('l', 5);
    if (isset(SUPPORTED_LANGS[$lang])) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        if ($u = current_user()) {
            db_exec('UPDATE users SET lang = ? WHERE id = ?', [$lang, $u['id']]);
        }
    }
    $back = get_str('back', 500);
    redirect($back !== '' && str_starts_with($back, 'index.php') ? $back : url());
}

switch ($route) {
    case '':
        render('home', ['user' => current_user()]);
        break;

    // ---------------------------------------------------------------- Konto
    case 'register':
        if (current_user()) {
            redirect(url('dashboard'));
        }
        $errors = [];
        $form = ['name' => '', 'email' => ''];
        if (is_post()) {
            require_post();
            $form = ['name' => post_str('name', 120), 'email' => post_str('email', 190)];
            $password = (string)($_POST['password'] ?? '');
            if ($form['name'] === '') {
                $errors['name'] = t('validation.required');
            }
            if (!valid_email($form['email'])) {
                $errors['email'] = t('validation.email');
            } elseif (find_user_by_email($form['email'])) {
                $errors['email'] = t('auth.email_taken');
            }
            if ($p = password_problem($password)) {
                $errors['password'] = $p;
            } elseif ($password !== ($_POST['password2'] ?? '')) {
                $errors['password2'] = t('auth.password_mismatch');
            }
            if (!$errors) {
                $id = create_user($form['email'], $form['name'], $password, current_lang());
                login_user(db_one('SELECT * FROM users WHERE id = ?', [$id]));
                flash('success', t('auth.registered'));
                $next = $_SESSION['after_login'] ?? url('dashboard');
                unset($_SESSION['after_login']);
                redirect($next);
            }
        }
        render('auth_register', compact('errors', 'form'));
        break;

    case 'login':
        if (current_user()) {
            redirect(url('dashboard'));
        }
        $error = null;
        $email = '';
        if (is_post()) {
            require_post();
            $email = post_str('email', 190);
            $user = attempt_login($email, (string)($_POST['password'] ?? ''));
            if ($user) {
                login_user($user);
                $next = $_SESSION['after_login'] ?? url('dashboard');
                unset($_SESSION['after_login']);
                redirect(str_starts_with($next, '/') || str_starts_with($next, 'index.php') ? $next : url('dashboard'));
            }
            usleep(800000);
            $error = t('auth.invalid');
        }
        if ($next = get_str('next', 500)) {
            if (str_starts_with($next, 'index.php')) {
                $_SESSION['after_login'] = $next;
            }
        }
        render('auth_login', compact('error', 'email'));
        break;

    case 'logout':
        require_post();
        logout_user();
        flash('success', t('auth.logged_out'));
        redirect(url());

    case 'account':
        $user = require_login();
        $errors = [];
        $profile = load_profile((int)$user['id']) ?? empty_participant();
        if ($profile['email'] === '' && $profile['notify_email'] === '') {
            $profile['email'] = $user['email'];
            $profile['notify_email'] = $user['email'];
        }
        if (is_post()) {
            require_post();
            $action = post_str('action', 20);
            if ($action === 'profile') {
                $profile = participant_from_post();
                $errors = validate_participant($profile);
                if (!$errors) {
                    save_profile((int)$user['id'], $profile);
                    flash('success', t('profile.saved'));
                    redirect(url('account'));
                }
            } elseif ($action === 'password') {
                $full = db_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
                $new = (string)($_POST['new_password'] ?? '');
                if (!password_verify((string)($_POST['current_password'] ?? ''), $full['password_hash'])) {
                    $errors['current_password'] = t('auth.invalid_password');
                } elseif ($p = password_problem($new)) {
                    $errors['new_password'] = $p;
                } else {
                    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                    flash('success', t('auth.password_changed'));
                    redirect(url('account'));
                }
            } elseif ($action === 'delete') {
                $full = db_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
                if (!password_verify((string)($_POST['confirm_password'] ?? ''), $full['password_hash'])) {
                    $errors['confirm_password'] = t('auth.invalid_password');
                } elseif (db_one("SELECT 1 FROM events WHERE owner_id = ? AND purged_at IS NULL", [$user['id']])) {
                    $errors['confirm_password'] = t('account.delete_has_events');
                } else {
                    $pdo = db();
                    $pdo->beginTransaction();
                    // Eigene Anmeldungen an laufenden Events werden anonym (und später automatisch gelöscht),
                    // abgeschlossene werden sofort gelöscht.
                    foreach (db_all('SELECT e.*, r.id AS reg_id FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.user_id = ?', [$user['id']]) as $row) {
                        if (!event_access_active($row)) {
                            db_exec('DELETE FROM access_log WHERE registration_id = ?', [$row['reg_id']]);
                            db_exec('DELETE FROM registrations WHERE id = ?', [$row['reg_id']]);
                        }
                    }
                    // Es sind nur noch abgeschlossene eigene Events vorhanden (siehe Prüfung oben)
                    db_exec('DELETE FROM events WHERE owner_id = ?', [$user['id']]);
                    db_exec('DELETE FROM users WHERE id = ?', [$user['id']]);
                    $pdo->commit();
                    logout_user();
                    flash('success', t('account.deleted'));
                    redirect(url());
                }
            }
        }
        render('account', compact('user', 'profile', 'errors'));
        break;

    // ------------------------------------------------------------ Übersicht
    case 'dashboard':
        $user = require_login();
        $events = db_all(
            'SELECT e.*, t.role, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
             FROM events e JOIN event_team t ON t.event_id = e.id AND t.user_id = ?
             ORDER BY e.starts_on DESC',
            [$user['id']]
        );
        $registrations = db_all(
            'SELECT r.*, e.title, e.starts_on, e.ends_on, e.retention_days, e.purged_at,
                    (SELECT COUNT(*) FROM access_log a WHERE a.registration_id = r.id) AS access_count
             FROM registrations r JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ? ORDER BY e.starts_on DESC',
            [$user['id']]
        );
        render('dashboard', compact('user', 'events', 'registrations'));
        break;

    // --------------------------------------------------------------- Events
    case 'event/new':
        $user = require_login();
        $errors = [];
        $event = ['title' => '', 'description' => '', 'location' => '', 'starts_on' => '', 'ends_on' => '',
                  'retention_days' => (int)config('default_retention_days'), 'registration_open' => true];
        if (is_post()) {
            require_post();
            $event = event_from_post();
            $errors = validate_event($event);
            if (!$errors) {
                $id = create_event((int)$user['id'], $event);
                flash('success', t('event.created'));
                redirect(url('event', ['id' => $id]));
            }
        }
        render('event_form', compact('event', 'errors') + ['isNew' => true]);
        break;

    case 'event/edit':
        $user = require_login();
        $event = find_event((int)get_str('id')) ?? not_found();
        $role = team_role((int)$event['id'], (int)$user['id']) ?? forbidden();
        $errors = [];
        if (is_post()) {
            require_post();
            $data = event_from_post();
            $errors = validate_event($data);
            if (!$errors) {
                db_exec(
                    'UPDATE events SET title = ?, description = ?, location = ?, starts_on = ?, ends_on = ?, retention_days = ?, registration_open = ? WHERE id = ?',
                    [$data['title'], $data['description'], $data['location'], $data['starts_on'], $data['ends_on'], $data['retention_days'], (int)$data['registration_open'], $event['id']]
                );
                flash('success', t('event.saved'));
                redirect(url('event', ['id' => $event['id']]));
            }
            $event = $data + $event;
        }
        render('event_form', compact('event', 'errors') + ['isNew' => false]);
        break;

    case 'event':
        $user = require_login();
        $event = find_event((int)get_str('id')) ?? not_found();
        $role = team_role((int)$event['id'], (int)$user['id']) ?? forbidden();
        $regs = db_all('SELECT * FROM registrations WHERE event_id = ? ORDER BY created_at', [$event['id']]);
        $rows = [];
        foreach ($regs as $r) {
            $d = registration_data($r);
            $rows[] = [
                'id' => $r['id'],
                'label' => participant_label($d),
                'has_account' => $r['user_id'] !== null,
                'has_contact' => (bool)$r['has_contact'],
                'methods' => array_filter(explode(',', $r['contact_methods'])),
                'medical_status' => $r['medical_status'],
                'updated_at' => $r['updated_at'],
            ];
        }
        usort($rows, fn($a, $b) => strcasecmp($a['label'], $b['label']));
        $team = db_all('SELECT u.id, u.name, u.email, t.role FROM event_team t JOIN users u ON u.id = t.user_id WHERE t.event_id = ? ORDER BY t.role DESC, u.name', [$event['id']]);
        $log = db_all('SELECT * FROM access_log WHERE event_id = ? ORDER BY accessed_at DESC LIMIT 200', [$event['id']]);
        render('event_view', compact('user', 'event', 'role', 'rows', 'team', 'log'));
        break;

    case 'event/team':
        $user = require_login();
        require_post();
        $event = find_event((int)post_str('id')) ?? not_found();
        if (team_role((int)$event['id'], (int)$user['id']) !== 'owner') {
            forbidden();
        }
        $action = post_str('action', 10);
        if ($action === 'add') {
            $member = find_user_by_email(post_str('email', 190));
            if (!$member) {
                flash('error', t('team.user_not_found'));
            } elseif (team_role((int)$event['id'], (int)$member['id'])) {
                flash('info', t('team.already_member'));
            } else {
                db_exec('INSERT INTO event_team (event_id, user_id, role, added_at) VALUES (?, ?, ?, ?)', [$event['id'], $member['id'], 'member', now()]);
                send_mail($member['email'], t_lang($member['lang'], 'mail.team.subject', ['event' => $event['title']]),
                    t_lang($member['lang'], 'mail.team.body', ['name' => $member['name'], 'event' => $event['title'], 'by' => $user['name'], 'link' => absolute_url('event', ['id' => $event['id']])]));
                flash('success', t('team.added', ['name' => $member['name']]));
            }
        } elseif ($action === 'remove') {
            $uid = (int)post_str('user_id');
            if ($uid !== (int)$user['id']) {
                db_exec("DELETE FROM event_team WHERE event_id = ? AND user_id = ? AND role <> 'owner'", [$event['id'], $uid]);
                flash('success', t('team.removed'));
            }
        }
        redirect(url('event', ['id' => $event['id']]) . '#team');

    case 'event/delete':
        $user = require_login();
        require_post();
        $event = find_event((int)post_str('id')) ?? not_found();
        if (team_role((int)$event['id'], (int)$user['id']) !== 'owner') {
            forbidden();
        }
        if (post_str('confirm', 200) !== $event['title']) {
            flash('error', t('event.delete_confirm_mismatch'));
            redirect(url('event', ['id' => $event['id']]) . '#danger');
        }
        db_exec('DELETE FROM events WHERE id = ?', [$event['id']]);
        flash('success', t('event.deleted'));
        redirect(url('dashboard'));

    case 'emergency':
        $user = require_login();
        $event = find_event((int)get_str('id')) ?? not_found();
        team_role((int)$event['id'], (int)$user['id']) ?? forbidden();
        if (!event_access_active($event)) {
            http_response_code(403);
            render('error', ['message' => t('emergency.expired', ['date' => format_date(access_until($event))])]);
            break;
        }
        $regId = get_str('reg');
        $targets = $regId === 'all'
            ? db_all('SELECT * FROM registrations WHERE event_id = ? ORDER BY id', [$event['id']])
            : db_all('SELECT * FROM registrations WHERE event_id = ? AND id = ?', [$event['id'], (int)$regId]);
        if (!$targets) {
            not_found();
        }
        $labels = array_map(fn($r) => participant_label(registration_data($r)), $targets);
        $error = null;
        if (is_post()) {
            require_post();
            $reason = post_str('reason', 500);
            if (mb_strlen($reason) < 5) {
                $error = t('emergency.reason_required');
            } elseif (empty($_POST['confirm'])) {
                $error = t('emergency.confirm_required');
            } else {
                $people = [];
                foreach ($targets as $reg) {
                    $people[] = ['reg' => $reg, 'data' => emergency_access($event, $reg, $user, $reason)];
                }
                usort($people, fn($a, $b) => strcasecmp(participant_label($a['data']), participant_label($b['data'])));
                render('emergency_view', compact('event', 'people', 'reason'));
                break;
            }
        }
        render('emergency_confirm', compact('event', 'labels', 'regId', 'error'));
        break;

    // ------------------------------------------------- Anmeldung Teilnehmende
    case 'reg':
        $token = get_str('t');
        $event = find_event_by_token($token) ?? not_found();
        $user = current_user();
        $mode = get_str('mode', 10);
        if (!event_registration_open($event)) {
            render('reg_closed', compact('event'));
            break;
        }
        if ($user) {
            $existing = db_one('SELECT * FROM registrations WHERE event_id = ? AND user_id = ?', [$event['id'], $user['id']]);
            if ($existing && !is_post()) {
                flash('info', t('reg.already_registered'));
                redirect(url('mine', ['k' => $existing['edit_token']]));
            }
        } elseif ($mode !== 'anon' && !is_post()) {
            render('reg_choice', compact('event', 'token'));
            break;
        }
        $errors = [];
        $data = $user ? (load_profile((int)$user['id']) ?? empty_participant()) : empty_participant();
        if ($user && $data['notify_email'] === '') {
            $data['notify_email'] = $user['email'];
        }
        if (is_post()) {
            require_post();
            $data = participant_from_post();
            $errors = validate_participant($data);
            if (empty($_POST['consent'])) {
                $errors['consent'] = t('reg.consent_required');
            }
            if (!$errors) {
                $existing = $user ? db_one('SELECT * FROM registrations WHERE event_id = ? AND user_id = ?', [$event['id'], $user['id']]) : null;
                $reg = registration_save($event, $data, $user ? (int)$user['id'] : null, $existing);
                if ($user && !empty($_POST['save_profile'])) {
                    save_profile((int)$user['id'], $data);
                }
                $link = absolute_url('mine', ['k' => $reg['edit_token']]);
                send_mail($data['notify_email'], t('mail.reg.subject', ['event' => $event['title']]),
                    t('mail.reg.body', ['name' => participant_label($data), 'event' => $event['title'], 'link' => $link,
                        'until' => format_date(access_until($event))]));
                $_SESSION['last_reg'] = $reg['edit_token'];
                redirect(url('reg/done', ['k' => $reg['edit_token']]));
            }
        }
        render('reg_form', compact('event', 'data', 'errors', 'user', 'token') + ['isEdit' => false]);
        break;

    case 'reg/done':
        $reg = find_registration_by_token(get_str('k', 100)) ?? not_found();
        if (($_SESSION['last_reg'] ?? null) !== $reg['edit_token']) {
            redirect(url('mine', ['k' => $reg['edit_token']]));
        }
        $event = find_event((int)$reg['event_id']);
        render('reg_done', compact('reg', 'event'));
        break;

    case 'mine':
        $reg = find_registration_by_token(get_str('k', 100)) ?? not_found();
        $event = find_event((int)$reg['event_id']) ?? not_found();
        $user = current_user();
        if ($reg['user_id'] !== null && (!$user || (int)$user['id'] !== (int)$reg['user_id'])) {
            $_SESSION['after_login'] = url('mine', ['k' => $reg['edit_token']]);
            flash('info', t('reg.login_to_view'));
            redirect(url('login'));
        }
        $data = registration_data($reg);
        $errors = [];
        $canEdit = event_access_active($event);
        if (is_post()) {
            require_post();
            $action = post_str('action', 10);
            if ($action === 'delete') {
                db_exec('DELETE FROM access_log WHERE registration_id = ?', [$reg['id']]);
                db_exec('DELETE FROM registrations WHERE id = ?', [$reg['id']]);
                flash('success', t('reg.deleted'));
                redirect($user ? url('dashboard') : url());
            }
            if ($canEdit) {
                $data = participant_from_post();
                $errors = validate_participant($data);
                if (!$errors) {
                    registration_save($event, $data, $reg['user_id'] !== null ? (int)$reg['user_id'] : null, $reg);
                    if ($user && !empty($_POST['save_profile'])) {
                        save_profile((int)$user['id'], $data);
                    }
                    flash('success', t('reg.updated'));
                    redirect(url('mine', ['k' => $reg['edit_token']]));
                }
            }
        }
        $log = access_log_for_registration((int)$reg['id']);
        render('reg_mine', compact('reg', 'event', 'data', 'errors', 'log', 'user', 'canEdit'));
        break;

    // ------------------------------------------------------------------ DEV
    case 'dev/mail':
        if (config('env') !== 'dev' || config('mail.transport') !== 'file') {
            not_found();
        }
        require_login();
        $mails = [];
        foreach (array_reverse(glob(config('data_dir') . '/mail/*.json') ?: []) as $f) {
            $m = json_decode((string)file_get_contents($f), true);
            if (is_array($m)) {
                $mails[] = $m;
            }
            if (count($mails) >= 50) {
                break;
            }
        }
        render('dev_mail', compact('mails'));
        break;

    default:
        not_found();
}
