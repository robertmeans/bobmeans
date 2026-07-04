<?php

function annual_prepaid_rebuild_amount(
  float $annual_cost,
  ?string $last_paid_date,
  int $renewal_term_months,
  DateTime $today
): float {
  if ($annual_cost <= 0 || !$last_paid_date || $renewal_term_months < 1) {
    return 0.00;
  }

  try {
    $paid_date = new DateTime($last_paid_date);
    $paid_date->setTime(0, 0, 0);
  } catch (Exception $e) {
    return 0.00;
  }

  if ($paid_date >= $today) {
    return 0.00;
  }

  $months_elapsed = ((int)$today->format('Y') - (int)$paid_date->format('Y')) * 12;
  $months_elapsed += (int)$today->format('n') - (int)$paid_date->format('n');

  if ((int)$today->format('j') < (int)$paid_date->format('j')) {
    $months_elapsed--;
  }

  if ($months_elapsed < 0) {
    $months_elapsed = 0;
  }

  if ($months_elapsed > $renewal_term_months) {
    $months_elapsed = $renewal_term_months;
  }

  $monthly_rebuild = $annual_cost / $renewal_term_months;
  $reserve = $monthly_rebuild * $months_elapsed;

  if ($reserve > $annual_cost) {
    $reserve = $annual_cost;
  }

  return round($reserve, 2);
}

function payment_amount_for_bill(array $bill, int $cycles_paid = 1): float
{
  $cadence = (string)($bill['cadence'] ?? '');
  $default_amount = (float)($bill['default_amount'] ?? 0);
  $annual_cost = isset($bill['annual_cost']) && $bill['annual_cost'] !== null
    ? (float)$bill['annual_cost']
    : 0.00;

  if ($cadence === 'annual') {
    $base_amount = $annual_cost > 0 ? $annual_cost : $default_amount;
    return round($base_amount * $cycles_paid, 2);
  }

  return round($default_amount * $cycles_paid, 2);
}

function load_billing_account(PDO $pdo_db, int $user_id, int $billing_account_id): ?array
{
  $stmt = $pdo_db->prepare("
    SELECT
      ba.billing_account_id,
      ba.billing_name,
      ba.vendor_name,
      ba.intake_note,
      ba.cadence,
      ba.reserve_style,
      ba.default_amount,
      ba.annual_cost,
      ba.paid_through_date,
      ba.last_paid_date,
      ba.renewal_term_months,
      ba.default_funding_account_id,
      ba.transfer_from_funding_account_id,
      ba.is_autopay,
      ba.auto_advance_on_payment,
      ba.is_active,
      fa.account_name AS paid_from_account,
      tfa.account_name AS transferred_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    LEFT JOIN funding_accounts tfa
      ON ba.transfer_from_funding_account_id = tfa.funding_account_id
    WHERE ba.user_id = ?
      AND ba.billing_account_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id, $billing_account_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  return $row ?: null;
}

function load_billing_account_contribute(PDO $pdo_db, int $user_id, int $billing_account_id): ?array
{
  $stmt = $pdo_db->prepare("
    SELECT
      billing_account_id,
      billing_name,
      vendor_name,
      cadence,
      default_amount,
      annual_cost,
      default_funding_account_id
    FROM billing_accounts
    WHERE user_id = ?
      AND billing_account_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id, $billing_account_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  return $row ?: null;
}

function autopay_already_processed(PDO $pdo_db, int $billing_account_id, string $due_date): bool
{
  $stmt = $pdo_db->prepare("
    SELECT bill_payment_id
    FROM bill_payments
    WHERE billing_account_id = ?
      AND due_date = ?
      AND status = 'paid'
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $due_date]);

  return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function add_months_to_date(string $date_string, int $months): string
{
  $date = new DateTime($date_string);
  $date->setTime(0, 0, 0);
  $date->modify(($months >= 0 ? '+' : '') . $months . ' months');
  return $date->format('Y-m-d');
}

function months_to_advance_for_bill(array $bill, int $cycles = 1): int
{
  $renewal_term_months = isset($bill['renewal_term_months'])
    ? (int)$bill['renewal_term_months']
    : 1;

  if ($renewal_term_months < 1) {
    $renewal_term_months = 1;
  }

  return $renewal_term_months * $cycles;
}

function base_amount_for_bill(array $bill): float
{
  return round((float)($bill['default_amount'] ?? 0), 2);
}

function safe_day_for_month(int $year, int $month, int $day): int
{
  $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);

  if ($day < 1) {
    return 1;
  }

  if ($day > $last_day) {
    return $last_day;
  }

  return $day;
}

function next_monthly_occurrence_from_anchor(int $due_day_of_month, DateTime $today): ?DateTime
{
  if ($due_day_of_month < 1 || $due_day_of_month > 31) {
    return null;
  }

  $year = (int)$today->format('Y');
  $month = (int)$today->format('n');

  $day_this_month = safe_day_for_month($year, $month, $due_day_of_month);
  $candidate = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day_this_month));
  $candidate->setTime(0, 0, 0);

  if ($candidate < $today) {
    $next_month = clone $today;
    $next_month->modify('first day of next month');

    $year = (int)$next_month->format('Y');
    $month = (int)$next_month->format('n');
    $day_next_month = safe_day_for_month($year, $month, $due_day_of_month);

    $candidate = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day_next_month));
    $candidate->setTime(0, 0, 0);
  }

  return $candidate;
}

function next_annual_occurrence_from_anchor(int $due_month_of_year, int $due_day_of_month, DateTime $today): ?DateTime
{
  if ($due_month_of_year < 1 || $due_month_of_year > 12) {
    return null;
  }

  if ($due_day_of_month < 1 || $due_day_of_month > 31) {
    return null;
  }

  $year = (int)$today->format('Y');
  $day_this_year = safe_day_for_month($year, $due_month_of_year, $due_day_of_month);

  $candidate = new DateTime(sprintf('%04d-%02d-%02d', $year, $due_month_of_year, $day_this_year));
  $candidate->setTime(0, 0, 0);

  if ($candidate < $today) {
    $year++;
    $day_next_year = safe_day_for_month($year, $due_month_of_year, $due_day_of_month);

    $candidate = new DateTime(sprintf('%04d-%02d-%02d', $year, $due_month_of_year, $day_next_year));
    $candidate->setTime(0, 0, 0);
  }

  return $candidate;
}

function first_projected_due_date(array $bill, DateTime $today): ?DateTime
{
  $actual_due_date = trim((string)($bill['actual_due_date'] ?? ''));

  if ($actual_due_date === '') {
    return null;
  }

  try {
    $stored_actual_due = new DateTime($actual_due_date);
    $stored_actual_due->setTime(0, 0, 0);
    return $stored_actual_due;
  } catch (Exception $e) {
    return null;
  }
}

function generate_projected_bill_events(array $rows, int $months_ahead = 12): array
{
  $events = [];
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  $end_date = clone $today;
  $end_date->modify('+' . $months_ahead . ' months');

  foreach ($rows as $row) {
    $base_amount = base_amount_for_bill($row);
    $cycle_months = months_to_advance_for_bill($row, 1);

    if ($base_amount <= 0) {
      continue;
    }

    $cursor = first_projected_due_date($row, $today);

    if (!$cursor) {
      continue;
    }

    while ($cursor <= $end_date) {
      $events[] = [
        'billing_account_id' => (int)$row['billing_account_id'],
        'billing_name' => (string)$row['billing_name'],
        'vendor_name' => (string)($row['vendor_name'] ?? ''),
        'intake_note' => (string)($row['intake_note'] ?? ''),
        'cadence' => (string)($row['cadence'] ?? ''),
        'due_date' => $cursor->format('Y-m-d'),
        'amount' => $base_amount,
        'paid_from_account' => (string)($row['paid_from_account'] ?? ''),
        'default_funding_account_id' => $row['default_funding_account_id'] ?? null
      ];

      $cursor->modify('+' . $cycle_months . ' months');
    }
  }

  usort($events, function ($a, $b) {
    if ($a['due_date'] === $b['due_date']) {
      return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
    }

    return strcmp((string)$a['due_date'], (string)$b['due_date']);
  });

  return $events;
}

function apply_pool_to_projected_events(array $events, float $pool_amount): array
{
  $remaining_pool = round($pool_amount, 2);
  $processed = [];

  foreach ($events as $event) {
    $amount = (float)$event['amount'];
    $covered = min($remaining_pool, $amount);
    $remaining_due = round($amount - $covered, 2);

    if ($remaining_due <= 0) {
      $remaining_due = 0.00;
      $status = 'paid';
    } elseif ($covered > 0) {
      $status = 'partial';
    } else {
      $status = 'due';
    }

    $processed[] = array_merge($event, [
      'covered_by_pool' => round($covered, 2),
      'remaining_due' => $remaining_due,
      'status' => $status,
      'pool_remaining_after' => round($remaining_pool - $covered, 2)
    ]);

    $remaining_pool = round($remaining_pool - $covered, 2);

    if ($remaining_pool < 0) {
      $remaining_pool = 0.00;
    }
  }

  return [
    'events' => $processed,
    'starting_pool' => round($pool_amount, 2),
    'ending_pool' => round($remaining_pool, 2)
  ];
}


function payment_already_recorded(PDO $pdo_db, int $billing_account_id, int $user_id, string $due_date): bool
{
  $stmt = $pdo_db->prepare("
    SELECT bill_payment_id
    FROM bill_payments
    WHERE billing_account_id = ?
      AND user_id = ?
      AND due_date = ?
      AND status = 'paid'
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id, $due_date]);

  return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function process_single_due_draft(PDO $pdo_db, array $bill, string $due_date): bool
{
  $billing_account_id = (int)$bill['billing_account_id'];
  $user_id = (int)$bill['user_id'];
  $amount = base_amount_for_bill($bill);

  if ($amount <= 0) {
    return false;
  }

  $funding_account_id = isset($bill['default_funding_account_id'])
    ? (int)$bill['default_funding_account_id']
    : 0;

  if ($funding_account_id < 1) {
    return false;
  }

  if (payment_already_recorded($pdo_db, $billing_account_id, $user_id, $due_date)) {
    return false;
  }

  $current_pool_balance = funding_account_pool_balance_by_id($pdo_db, $user_id, $funding_account_id);

  if ($current_pool_balance < $amount) {
    return false;
  }

  $months_to_advance = months_to_advance_for_bill($bill, 1);

  $next_actual_due = new DateTime($due_date);
  $next_actual_due->setTime(0, 0, 0);
  $next_actual_due->modify('+' . $months_to_advance . ' months');

  $pdo_db->beginTransaction();

  try {
    $stmt = $pdo_db->prepare("
      INSERT INTO bill_payments (
        billing_account_id,
        user_id,
        due_date,
        amount_due,
        amount_paid,
        date_paid,
        funding_account_id,
        transfer_from_funding_account_id,
        status,
        confirmation_note
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $billing_account_id,
      $user_id,
      $due_date,
      $amount,
      $amount,
      $due_date,
      $bill['default_funding_account_id'] !== null ? (int)$bill['default_funding_account_id'] : null,
      $bill['transfer_from_funding_account_id'] !== null ? (int)$bill['transfer_from_funding_account_id'] : null,
      'paid',
      'Auto-deducted from funding account pool'
    ]);

    $stmt = $pdo_db->prepare("
      INSERT INTO funding_account_reserve_transactions (
        funding_account_id,
        user_id,
        billing_account_id,
        transaction_type,
        amount,
        transaction_date,
        note
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $funding_account_id,
      $user_id,
      $billing_account_id,
      'deduction',
      $amount,
      $due_date . ' 00:00:00',
      'Automatic draft deduction for ' . $bill['billing_name']
    ]);

    $stmt = $pdo_db->prepare("
      UPDATE billing_accounts
      SET
        actual_due_date = ?,
        updated_at = NOW()
      WHERE billing_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $next_actual_due->format('Y-m-d'),
      $billing_account_id,
      $user_id
    ]);

    $pdo_db->commit();
    return true;

  } catch (Exception $e) {
    if ($pdo_db->inTransaction()) {
      $pdo_db->rollBack();
    }

    error_log('Reserve draft reconciliation failed for billing_account_id ' . $billing_account_id . ': ' . $e->getMessage());
    return false;
  }
}

function reconcile_due_bills_against_reserves(PDO $pdo_db, int $user_id): array
{
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  $stmt = $pdo_db->prepare("
    SELECT
      ba.*,
      fa.account_name AS paid_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    WHERE ba.user_id = ?
      AND ba.is_active = 1
      AND ba.actual_due_date IS NOT NULL
    ORDER BY ba.actual_due_date ASC, ba.billing_account_id ASC
  ");
  $stmt->execute([$user_id]);
  $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $processed_count = 0;
  $skipped_count = 0;

  foreach ($bills as $bill) {
    $paid_from = strtolower(trim((string)($bill['paid_from_account'] ?? '')));

    // if ($paid_from !== 'paypal') {
    //   continue;
    // }

    $actual_due = trim((string)$bill['actual_due_date']);
    if ($actual_due === '') {
      continue;
    }

    $cursor = new DateTime($actual_due);
    $cursor->setTime(0, 0, 0);

    while ($cursor < $today) {
      $processed = process_single_due_draft($pdo_db, $bill, $cursor->format('Y-m-d'));

      if ($processed) {
        $processed_count++;

        $stmt = $pdo_db->prepare("
          SELECT *
          FROM billing_accounts
          WHERE billing_account_id = ?
            AND user_id = ?
          LIMIT 1
        ");
        $stmt->execute([(int)$bill['billing_account_id'], $user_id]);
        $refreshed = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refreshed || empty($refreshed['actual_due_date'])) {
          break;
        }

        $bill = array_merge($bill, $refreshed);
        $cursor = new DateTime($bill['actual_due_date']);
        $cursor->setTime(0, 0, 0);
      } else {
        $skipped_count++;
        break;
      }
    }
  }

  return [
    'processed_count' => $processed_count,
    'skipped_count' => $skipped_count
  ];
}

function filter_rows_by_funding_account(array $rows, string $target_account_name): array
{
  $filtered = [];
  $target = strtolower(trim($target_account_name));

  foreach ($rows as $row) {
    $account_name = strtolower(trim((string)($row['paid_from_account'] ?? '')));

    if ($account_name === $target) {
      $filtered[] = $row;
    }
  }

  return $filtered;
}


/*  Sunday, June 28, 2026 - 
    latest update to make billing_schedule.php "the source" - officially moving away from billing_projection.php 
*/

function funding_account_general_reserve_totals(PDO $pdo_db, int $user_id): array
{
  $stmt = $pdo_db->prepare("
    SELECT
      fa.account_name,
      COALESCE(SUM(
        CASE
          WHEN fart.transaction_type = 'deduction' THEN -fart.amount
          ELSE fart.amount
        END
      ), 0) AS total_amount
    FROM funding_accounts fa
    LEFT JOIN funding_account_reserve_transactions fart
      ON fa.funding_account_id = fart.funding_account_id
      AND fart.user_id = fa.user_id
    WHERE fa.user_id = ?
      AND fa.is_active = 1
    GROUP BY fa.funding_account_id, fa.account_name
    ORDER BY fa.account_name ASC
  ");
  $stmt->execute([$user_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $totals = [];

  foreach ($rows as $row) {
    $account_name = trim((string)$row['account_name']);
    if ($account_name === '') {
      continue;
    }

    $totals[$account_name] = round((float)$row['total_amount'], 2);
  }

  return $totals;
}

function funding_account_selector_options(PDO $pdo_db, int $user_id): array
{
  $stmt = $pdo_db->prepare("
    SELECT funding_account_id, account_name
    FROM funding_accounts
    WHERE user_id = ?
      AND is_active = 1
    ORDER BY account_name ASC
  ");
  $stmt->execute([$user_id]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dashboard_monthly_due_totals(array $rows, int $months_ahead = 6): array
{
  $totals = [];
  $today = new DateTime('first day of this month');
  $today->setTime(0, 0, 0);

  for ($i = 0; $i < $months_ahead; $i++) {
    $month = clone $today;
    $month->modify('+' . $i . ' months');
    $key = $month->format('Y-m');
    $totals[$key] = 0.00;
  }

  $events = generate_projected_bill_events($rows, $months_ahead);

  foreach ($events as $event) {
    if (empty($event['due_date'])) {
      continue;
    }

    $month_key = date('Y-m', strtotime($event['due_date']));

    if (isset($totals[$month_key])) {
      $totals[$month_key] += (float)$event['amount'];
    }
  }

  foreach ($totals as $key => $amount) {
    $totals[$key] = round($amount, 2);
  }

  return $totals;
}

/* 0629260931 - number 3 below, '$months_ahead = x' */
function dashboard_monthly_needed_totals(array $rows, float $pool_amount, int $months_ahead = 4): array
{
  $totals = [];
  $today = new DateTime('first day of this month');
  $today->setTime(0, 0, 0);

  for ($i = 0; $i < $months_ahead; $i++) {
    $month = clone $today;
    $month->modify('+' . $i . ' months');
    $key = $month->format('Y-m');
    $totals[$key] = 0.00;
  }

  $events = generate_projected_bill_events($rows, $months_ahead);
  $projection = apply_pool_to_projected_events($events, $pool_amount);

  foreach ($projection['events'] as $event) {
    if (($event['status'] === 'partial' || $event['status'] === 'due') && !empty($event['due_date'])) {
      $month_key = date('Y-m', strtotime($event['due_date']));

      if (isset($totals[$month_key])) {
        $totals[$month_key] += (float)$event['remaining_due'];
      }
    }
  }

  foreach ($totals as $key => $amount) {
    $totals[$key] = round($amount, 2);
  }

  return $totals;
}

function days_until_next_bill_date(array $rows_by_account, array $reserve_totals, int $months_ahead = 12): ?array
{
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  $all_events = [];

  foreach ($rows_by_account as $account_name => $rows_for_account) {
    $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;

    $events = generate_projected_bill_events($rows_for_account, $months_ahead);
    $projection = apply_pool_to_projected_events($events, $pool_amount);

    foreach ($projection['events'] as $event) {
      if (!empty($event['due_date'])) {
        $all_events[] = array_merge($event, [
          'funding_account' => $account_name
        ]);
      }
    }
  }

  if (!$all_events) {
    return null;
  }

  usort($all_events, function ($a, $b) {
    if ($a['due_date'] === $b['due_date']) {
      return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
    }

    return strcmp((string)$a['due_date'], (string)$b['due_date']);
  });

  $next_event = $all_events[0];
  $next_date = new DateTime($next_event['due_date']);
  $next_date->setTime(0, 0, 0);

  $days = (int)$today->diff($next_date)->format('%r%a');

  return [
    'days' => $days,
    'due_date' => $next_event['due_date'],
    'billing_name' => $next_event['billing_name'],
    'funding_account' => $next_event['funding_account'],
    'status' => $next_event['status'] ?? ''
  ];
}

function days_until_next_uncovered_bill_date(array $rows_by_account, array $reserve_totals, int $months_ahead = 12): ?array
{
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  $uncovered_events = [];

  foreach ($rows_by_account as $account_name => $rows_for_account) {
    $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;

    $events = generate_projected_bill_events($rows_for_account, $months_ahead);
    $projection = apply_pool_to_projected_events($events, $pool_amount);

    foreach ($projection['events'] as $event) {
      if (
        !empty($event['due_date']) &&
        ($event['status'] === 'partial' || $event['status'] === 'due')
      ) {
        $uncovered_events[] = array_merge($event, [
          'funding_account' => $account_name
        ]);
      }
    }
  }

  if (!$uncovered_events) {
    return null;
  }

  usort($uncovered_events, function ($a, $b) {
    if ($a['due_date'] === $b['due_date']) {
      return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
    }

    return strcmp((string)$a['due_date'], (string)$b['due_date']);
  });

  $next_event = $uncovered_events[0];
  $next_date = new DateTime($next_event['due_date']);
  $next_date->setTime(0, 0, 0);

  $days = (int)$today->diff($next_date)->format('%r%a');

  return [
    'days' => $days,
    'due_date' => $next_event['due_date'],
    'billing_name' => $next_event['billing_name'],
    'funding_account' => $next_event['funding_account'],
    'status' => $next_event['status'],
    'remaining_due' => (float)$next_event['remaining_due']
  ];
}

function projection_summary_for_account(
  string $account_name,
  array $rows_for_account,
  float $pool_amount,
  int $months_ahead = 12
): array {
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  $events = generate_projected_bill_events($rows_for_account, $months_ahead);
  $projection = apply_pool_to_projected_events($events, $pool_amount);

  $next_uncovered_event = null;
  $monthly_needed_totals = [];

  $month_cursor = new DateTime('first day of this month');
  $month_cursor->setTime(0, 0, 0);

  for ($i = 0; $i < $months_ahead; $i++) {
    $key = $month_cursor->format('Y-m');
    $monthly_needed_totals[$key] = 0.00;
    $month_cursor->modify('+1 month');
  }

  foreach ($projection['events'] as $event) {
    if (
      ($event['status'] === 'partial' || $event['status'] === 'due') &&
      !empty($event['due_date'])
    ) {
      if ($next_uncovered_event === null) {
        $next_uncovered_event = $event;
      }

      $month_key = date('Y-m', strtotime($event['due_date']));
      if (isset($monthly_needed_totals[$month_key])) {
        $monthly_needed_totals[$month_key] += (float)$event['remaining_due'];
      }
    }
  }

  foreach ($monthly_needed_totals as $month_key => $amount) {
    $monthly_needed_totals[$month_key] = round($amount, 2);
  }

  $next_uncovered_days = null;
  $first_problem_summary = [
    'status' => 'empty',
    'message' => 'No active bills assigned.',
    'due_date' => null
  ];

  if (!empty($projection['events'])) {
    $first_problem_summary = [
      'status' => 'covered',
      'message' => 'Covered through ' . date('m.d.y', strtotime(end($projection['events'])['due_date'])),
      'due_date' => end($projection['events'])['due_date']
    ];
  }

  if ($next_uncovered_event !== null) {
    $next_date = new DateTime($next_uncovered_event['due_date']);
    $next_date->setTime(0, 0, 0);
    $next_uncovered_days = (int)$today->diff($next_date)->format('%r%a');

    $first_problem_summary = [
      'status' => $next_uncovered_event['status'],
      // 'message' => date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($next_uncovered_event['due_date'])) . ' <span style="font-size:0.8em;display:inline-block;position:relative;top:-3px;margin:0 5px;">●</span> ' . $next_uncovered_event['billing_name'],
      'message' => date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($next_uncovered_event['due_date'])) . ' <span style="font-size:0.8em;display:inline-block;position:relative;top:-3px;margin:0 5px;">●</span> ',
      'remaining_due' => (float)$next_uncovered_event['remaining_due'],
      'due_date' => $next_uncovered_event['due_date']
    ];
  }

  return [
    'account_name' => $account_name,
    'pool_amount' => round($pool_amount, 2),
    'events' => $events,
    'projection' => $projection,
    'next_uncovered_event' => $next_uncovered_event,
    'next_uncovered_days' => $next_uncovered_days,
    'monthly_needed_totals' => $monthly_needed_totals,
    'first_problem_summary' => $first_problem_summary
  ];
}

function projection_summary_for_all_accounts(
  array $rows_by_account,
  array $reserve_totals,
  int $months_ahead = 12
): array {
  $account_summaries = [];
  $combined_monthly_needed_totals = [];
  $exceptions = [];
  $next_uncovered_candidates = [];
  $total_underfunded = 0.00;

  $month_cursor = new DateTime('first day of this month');
  $month_cursor->setTime(0, 0, 0);

  for ($i = 0; $i < $months_ahead; $i++) {
    $key = $month_cursor->format('Y-m');
    $combined_monthly_needed_totals[$key] = 0.00;
    $month_cursor->modify('+1 month');
  }

  foreach ($rows_by_account as $account_name => $rows_for_account) {
    $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;

    $summary = projection_summary_for_account(
      $account_name,
      $rows_for_account,
      $pool_amount,
      $months_ahead
    );

    $account_summaries[$account_name] = $summary;

    if ($summary['next_uncovered_event'] !== null) {
      $next_uncovered_candidates[] = array_merge(
        $summary['next_uncovered_event'],
        ['funding_account' => $account_name]
      );
    }

    foreach ($summary['projection']['events'] as $event) {
      if ($event['status'] === 'partial' || $event['status'] === 'due') {
        $exceptions[] = array_merge($event, [
          'funding_account' => $account_name
        ]);
        $total_underfunded += (float)$event['remaining_due'];
      }
    }

    foreach ($summary['monthly_needed_totals'] as $month_key => $amount) {
      if (!isset($combined_monthly_needed_totals[$month_key])) {
        $combined_monthly_needed_totals[$month_key] = 0.00;
      }

      $combined_monthly_needed_totals[$month_key] += $amount;
    }
  }

  foreach ($combined_monthly_needed_totals as $month_key => $amount) {
    $combined_monthly_needed_totals[$month_key] = round($amount, 2);
  }

  usort($exceptions, function ($a, $b) {
    if ($a['due_date'] === $b['due_date']) {
      return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
    }

    return strcmp((string)$a['due_date'], (string)$b['due_date']);
  });

  usort($next_uncovered_candidates, function ($a, $b) {
    if ($a['due_date'] === $b['due_date']) {
      return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
    }

    return strcmp((string)$a['due_date'], (string)$b['due_date']);
  });

  $account_attention_list = [];

  foreach ($account_summaries as $account_name => $summary) {
    $account_attention_list[] = [
      'account_name' => $account_name,
      'summary' => $summary['first_problem_summary']
    ];
  }

  usort($account_attention_list, function ($a, $b) {
    $date_a = $a['summary']['due_date'] ?? null;
    $date_b = $b['summary']['due_date'] ?? null;

    if ($date_a === $date_b) {
      return strcmp((string)$a['account_name'], (string)$b['account_name']);
    }

    if ($date_a === null) {
      return 1;
    }

    if ($date_b === null) {
      return -1;
    }

    return strcmp($date_a, $date_b);
  });

  return [
    'account_summaries' => $account_summaries,
    'account_attention_list' => $account_attention_list,
    'next_uncovered_bill' => !empty($next_uncovered_candidates) ? $next_uncovered_candidates[0] : null,
    'exceptions' => $exceptions,
    'monthly_needed_totals' => $combined_monthly_needed_totals,
    'total_underfunded' => round($total_underfunded, 2)
  ];
}

function funding_account_ledger_rows(PDO $pdo_db, int $user_id, int $funding_account_id): array
{
  $ledger = [];

  $stmt = $pdo_db->prepare("
    SELECT
      fart.funding_account_reserve_transaction_id AS id,
      fart.transaction_date AS event_datetime,
      'account_transaction' AS event_type,
      fart.transaction_type AS sub_type,
      fart.billing_account_id,
      ba.billing_name,
      fa.account_name,
      fart.note AS note,
      CASE
        WHEN fart.transaction_type = 'deduction' THEN -fart.amount
        ELSE fart.amount
      END AS signed_amount
    FROM funding_account_reserve_transactions fart
    INNER JOIN funding_accounts fa
      ON fart.funding_account_id = fa.funding_account_id
    LEFT JOIN billing_accounts ba
      ON fart.billing_account_id = ba.billing_account_id
    WHERE fart.user_id = ?
      AND fart.funding_account_id = ?
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    $ledger[] = $row;
  }

  usort($ledger, function ($a, $b) {
    if ($a['event_datetime'] === $b['event_datetime']) {
      return strcmp((string)$a['sub_type'], (string)$b['sub_type']);
    }

    return strcmp((string)$b['event_datetime'], (string)$a['event_datetime']);
  });

  return $ledger;
}

function funding_account_ledger_with_running_balance(PDO $pdo_db, int $user_id, int $funding_account_id): array
{
  $rows = funding_account_ledger_rows($pdo_db, $user_id, $funding_account_id);

  $running_balance = 0.00;

  foreach ($rows as $index => $row) {
    $running_balance += (float)$row['signed_amount'];
    $rows[$index]['running_balance'] = round($running_balance, 2);
  }

  return $rows;
}

function funding_account_current_balance_from_ledger(PDO $pdo_db, int $user_id, int $funding_account_id): float
{
  $rows = funding_account_ledger_with_running_balance($pdo_db, $user_id, $funding_account_id);

  if (!$rows) {
    return 0.00;
  }

  $last = end($rows);
  return round((float)$last['running_balance'], 2);
}

function funding_account_pool_totals(PDO $pdo_db, int $user_id): array
{
  $stmt = $pdo_db->prepare("
    SELECT
      fa.account_name,
      COALESCE(SUM(
        CASE
          WHEN fart.transaction_type = 'deduction' THEN -fart.amount
          ELSE fart.amount
        END
      ), 0) AS total_amount
    FROM funding_accounts fa
    LEFT JOIN funding_account_reserve_transactions fart
      ON fa.funding_account_id = fart.funding_account_id
      AND fart.user_id = fa.user_id
    WHERE fa.user_id = ?
      AND fa.is_active = 1
    GROUP BY fa.funding_account_id, fa.account_name
    ORDER BY fa.account_name ASC
  ");
  $stmt->execute([$user_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $totals = [];

  foreach ($rows as $row) {
    $account_name = trim((string)$row['account_name']);
    if ($account_name === '') {
      continue;
    }

    $totals[$account_name] = round((float)$row['total_amount'], 2);
  }

  return $totals;
}

function recent_bill_activity(PDO $pdo_db, int $user_id, int $limit = 5): array
{
  $stmt = $pdo_db->prepare("
    SELECT
      CONCAT(bp.date_paid, ' 00:00:00') AS event_datetime,
      'bill_payment' AS event_source,
      'payment' AS event_type,
      ba.billing_account_id,
      ba.billing_name,
      -bp.amount_paid AS signed_amount,
      bp.confirmation_note AS note
    FROM bill_payments bp
    INNER JOIN billing_accounts ba
      ON bp.billing_account_id = ba.billing_account_id
    WHERE bp.user_id = ?
      AND bp.status = 'paid'
    ORDER BY bp.date_paid DESC, bp.bill_payment_id DESC
    LIMIT ?
  ");
  $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->execute();

  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function funding_account_pool_balance_by_id(PDO $pdo_db, int $user_id, int $funding_account_id): float
{
  $stmt = $pdo_db->prepare("
    SELECT
      COALESCE(SUM(
        CASE
          WHEN transaction_type = 'deduction' THEN -amount
          ELSE amount
        END
      ), 0) AS balance
    FROM funding_account_reserve_transactions
    WHERE user_id = ?
      AND funding_account_id = ?
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  return round((float)($row['balance'] ?? 0), 2);
}

function next_attention_date_for_bill(
  PDO $pdo_db,
  int $user_id,
  int $billing_account_id,
  int $funding_account_id,
  int $months_ahead = 24
): ?array {
  $stmt = $pdo_db->prepare("
    SELECT
      ba.billing_account_id,
      ba.user_id,
      ba.billing_name,
      ba.vendor_name,
      ba.cadence,
      ba.reserve_style,
      ba.default_amount,
      ba.actual_due_date,
      ba.renewal_term_months,
      ba.due_day_of_month,
      ba.due_month_of_year,
      ba.default_funding_account_id,
      ba.transfer_from_funding_account_id,
      ba.is_active,
      fa.account_name AS paid_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    WHERE ba.user_id = ?
      AND ba.is_active = 1
      AND ba.default_funding_account_id = ?
    ORDER BY ba.billing_name ASC
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $rows_for_account = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$rows_for_account) {
    return null;
  }

  $pool_balance = funding_account_pool_balance_by_id($pdo_db, $user_id, $funding_account_id);

  $events = generate_projected_bill_events($rows_for_account, $months_ahead);
  $projection = apply_pool_to_projected_events($events, $pool_balance);

  foreach ($projection['events'] as $event) {
    if (
      (int)$event['billing_account_id'] === $billing_account_id &&
      ($event['status'] === 'partial' || $event['status'] === 'due')
    ) {
      return [
        'due_date' => $event['due_date'],
        'status' => $event['status'],
        'remaining_due' => (float)$event['remaining_due']
      ];
    }
  }

  return null;
}

function funding_account_archive_status(PDO $pdo_db, int $user_id, int $funding_account_id): array
{
  $status = [
    'funding_account_id' => $funding_account_id,
    'can_archive' => false,
    'is_already_inactive' => false,
    'current_balance' => 0.00,
    'active_bill_count' => 0,
    'active_bills' => [],
    'has_history' => false,
    'blocking_reasons' => []
  ];

  $stmt = $pdo_db->prepare("
    SELECT funding_account_id, account_name, is_active
    FROM funding_accounts
    WHERE funding_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$funding_account_id, $user_id]);
  $account = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$account) {
    $status['blocking_reasons'][] = 'Funding account not found.';
    return $status;
  }

  $status['is_already_inactive'] = ((int)$account['is_active'] !== 1);

  $status['current_balance'] = funding_account_current_balance_from_ledger($pdo_db, $user_id, $funding_account_id);

  $stmt = $pdo_db->prepare("
    SELECT billing_account_id, billing_name, actual_due_date
    FROM billing_accounts
    WHERE user_id = ?
      AND default_funding_account_id = ?
      AND is_active = 1
    ORDER BY billing_name ASC
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $active_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $status['active_bills'] = $active_bills;
  $status['active_bill_count'] = count($active_bills);

  $stmt = $pdo_db->prepare("
    SELECT 1
    FROM funding_account_reserve_transactions
    WHERE user_id = ?
      AND funding_account_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $has_funding_history = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $pdo_db->prepare("
    SELECT 1
    FROM bill_payments
    WHERE user_id = ?
      AND funding_account_id = ?
    LIMIT 1
  ");
  $stmt->execute([$user_id, $funding_account_id]);
  $has_payment_history = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

  $status['has_history'] = ($has_funding_history || $has_payment_history);

  if ($status['is_already_inactive']) {
    $status['blocking_reasons'][] = 'This funding account is already inactive.';
  }

  if (abs($status['current_balance']) > 0.009) {
    $status['blocking_reasons'][] = 'Current balance must be zero before archiving.';
  }

  if ($status['active_bill_count'] > 0) {
    $status['blocking_reasons'][] = 'Active bills are still assigned to this funding account.';
  }

  $status['can_archive'] = empty($status['blocking_reasons']);

  return $status;
}

function archive_funding_account(PDO $pdo_db, int $user_id, int $funding_account_id, ?string $closure_note = null): bool
{
  $status = funding_account_archive_status($pdo_db, $user_id, $funding_account_id);

  if (!$status['can_archive']) {
    return false;
  }

  $stmt = $pdo_db->prepare("
    UPDATE funding_accounts
    SET
      is_active = 0,
      closed_at = NOW(),
      closure_note = ?,
      updated_at = NOW()
    WHERE funding_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");

  return $stmt->execute([
    ($closure_note !== null && trim($closure_note) !== '') ? trim($closure_note) : null,
    $funding_account_id,
    $user_id
  ]);
}

function billing_account_archive_status(PDO $pdo_db, int $user_id, int $billing_account_id): array
{
  $status = [
    'billing_account_id' => $billing_account_id,
    'can_archive' => false,
    'is_already_inactive' => false,
    'has_payment_history' => false,
    'has_notes' => false,
    'blocking_reasons' => []
  ];

  $stmt = $pdo_db->prepare("
    SELECT billing_account_id, billing_name, is_active
    FROM billing_accounts
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $bill = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bill) {
    $status['blocking_reasons'][] = 'Billing account not found.';
    return $status;
  }

  $status['is_already_inactive'] = ((int)$bill['is_active'] !== 1);

  $stmt = $pdo_db->prepare("
    SELECT 1
    FROM bill_payments
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $status['has_payment_history'] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $pdo_db->prepare("
    SELECT 1
    FROM bill_notes
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $status['has_notes'] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

  if ($status['is_already_inactive']) {
    $status['blocking_reasons'][] = 'This billing account is already inactive.';
  }

  $status['can_archive'] = empty($status['blocking_reasons']);

  return $status;
}

function archive_billing_account(PDO $pdo_db, int $user_id, int $billing_account_id, ?string $closure_note = null): bool
{
  $status = billing_account_archive_status($pdo_db, $user_id, $billing_account_id);

  if (!$status['can_archive']) {
    return false;
  }

  $stmt = $pdo_db->prepare("
    UPDATE billing_accounts
    SET
      is_active = 0,
      closed_at = NOW(),
      closure_note = ?,
      updated_at = NOW()
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");

  return $stmt->execute([
    ($closure_note !== null && trim($closure_note) !== '') ? trim($closure_note) : null,
    $billing_account_id,
    $user_id
  ]);
}

function bill_activity_timeline(PDO $pdo_db, int $user_id, int $billing_account_id): array
{
  $timeline = [];

  /*
    activity log entries
  */
  $stmt = $pdo_db->prepare("
    SELECT
      bill_activity_log_id,
      activity_date AS event_datetime,
      'activity' AS event_source,
      activity_type,
      field_name,
      old_value,
      new_value,
      note
    FROM bill_activity_log
    WHERE billing_account_id = ?
      AND user_id = ?
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    $timeline[] = [
      'id' => (int)$row['bill_activity_log_id'],
      'event_datetime' => $row['event_datetime'],
      'event_source' => 'activity',
      'label' => (string)$row['activity_type'],
      'field_name' => $row['field_name'],
      'old_value' => $row['old_value'],
      'new_value' => $row['new_value'],
      'amount' => null,
      'note' => $row['note']
    ];
  }

  /*
    payment entries
  */
  $stmt = $pdo_db->prepare("
    SELECT
      bill_payment_id,
      CONCAT(date_paid, ' 00:00:00') AS event_datetime,
      amount_paid,
      confirmation_note
    FROM bill_payments
    WHERE billing_account_id = ?
      AND user_id = ?
      AND status = 'paid'
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    $timeline[] = [
      'id' => (int)$row['bill_payment_id'],
      'event_datetime' => $row['event_datetime'],
      'event_source' => 'payment',
      'label' => 'payment',
      'field_name' => null,
      'old_value' => null,
      'new_value' => null,
      'amount' => (float)$row['amount_paid'],
      'note' => $row['confirmation_note']
    ];
  }

  usort($timeline, function ($a, $b) {
    if ($a['event_datetime'] === $b['event_datetime']) {
      return strcmp((string)$a['label'], (string)$b['label']);
    }

    return strcmp((string)$b['event_datetime'], (string)$a['event_datetime']);
  });

  return $timeline;
}

function normalize_activity_log_field_value(string $field_name, $value): ?string
{
  if ($value === null) {
    return null;
  }

  if (in_array($field_name, ['default_amount'], true)) {
    return number_format((float)$value, 2, '.', '');
  }

  if (in_array($field_name, [
    'renewal_term_months',
    'due_day_of_month',
    'due_month_of_year',
    'default_funding_account_id',
    'transfer_from_funding_account_id',
    'is_autopay',
    'auto_advance_on_payment',
    'is_active',
    'sort_order'
  ], true)) {
    return (string)(int)$value;
  }

  $value = trim((string)$value);

  if ($value === '') {
    return null;
  }

  return $value;
}