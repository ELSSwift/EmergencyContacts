<?php
/** @var array $e Event */
if (!empty($e['purged_at']) || !event_access_active($e)) {
    echo '<span class="badge badge-muted">' . h(t('event.status_closed')) . '</span>';
} elseif (today() > $e['ends_on']) {
    echo '<span class="badge badge-warn">' . h(t('event.status_retention', ['date' => format_date(access_until($e))])) . '</span>';
} elseif (event_registration_open($e)) {
    echo '<span class="badge badge-ok">' . h(t('event.status_open')) . '</span>';
} else {
    echo '<span class="badge">' . h(t('event.status_reg_closed')) . '</span>';
}
