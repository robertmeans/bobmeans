<?php

function count_prepaid_monthly_cycles(string $next_due_date, DateTime $today): int
{
  if ($next_due_date === '') {
    return 0;
  }

  try {
    $next_due = new DateTime($next_due_date);
    $next_due->setTime(0, 0, 0);
  } catch (Exception $e) {
    return 0;
  }

  if ($next_due <= $today) {
    return 0;
  }

  $count = 0;
  $cursor = clone $next_due;

  while (true) {
    $cursor->modify('-1 month');

    if ($cursor > $today) {
      $count++;
    } else {
      break;
    }
  }

  return $count;
}

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

// function paypal_reserved_amount_for_bill(array $row, DateTime $today): float
// {
//   $paid_from = strtolower(trim((string)($row['paid_from_account'] ?? '')));
//   if ($paid_from !== 'paypal') {
//     return 0.00;
//   }

//   $cadence = (string)($row['cadence'] ?? '');
//   $reserve_style = (string)($row['reserve_style'] ?? 'sinking_fund');
//   $default_amount = (float)($row['default_amount'] ?? 0);
//   $next_due_date = (string)($row['next_due_date'] ?? '');
//   $reserve_amount = isset($row['reserve_amount']) ? (float)$row['reserve_amount'] : 0.00;

//   if ($reserve_style === 'manual_reserve') {
//     return round($reserve_amount, 2);
//   }

//   if ($cadence === 'monthly' && $reserve_style === 'sinking_fund') {
//     $cycles = count_prepaid_monthly_cycles($next_due_date, $today);
//     return round($default_amount * $cycles, 2);
//   }

//   if ($cadence === 'annual' && $reserve_style === 'prepaid') {
//     return 0.00;
//   }

//   return 0.00;
// }

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
      ba.next_due_date,
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

function amount_due_after_reserve(array $row): float
{
  $base_amount = base_amount_for_bill($row);
  $reserve_balance = isset($row['reserve_balance']) ? (float)$row['reserve_balance'] : 0.00;

  $remaining = $base_amount - $reserve_balance;

  if ($remaining < 0) {
    $remaining = 0.00;
  }

  return round($remaining, 2);
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
      reserve_balance,
      next_due_date,
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

function load_due_autopay_bills(PDO $pdo_db, int $user_id, string $today): array
{
  $stmt = $pdo_db->prepare("
    SELECT
      ba.billing_account_id,
      ba.user_id,
      ba.billing_name,
      ba.cadence,
      ba.reserve_style,
      ba.default_amount,
      ba.annual_cost,
      ba.reserve_balance,
      ba.next_due_date,
      ba.paid_through_date,
      ba.last_paid_date,
      ba.renewal_term_months,
      ba.default_funding_account_id,
      ba.transfer_from_funding_account_id,
      ba.is_autopay,
      ba.auto_advance_on_payment,
      ba.is_active
    FROM billing_accounts ba
    WHERE ba.user_id = ?
      AND ba.is_active = 1
      AND ba.is_autopay = 1
      AND ba.next_due_date <= ?
    ORDER BY ba.next_due_date ASC, ba.billing_account_id ASC
  ");
  $stmt->execute([$user_id, $today]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function process_single_autopay_bill(PDO $pdo_db, array $bill, string $today): bool
{
  $amount_due = payment_amount_for_bill($bill, 1);
  $reserve_balance = (float)($bill['reserve_balance'] ?? 0.00);
  $billing_account_id = (int)$bill['billing_account_id'];
  $user_id = (int)$bill['user_id'];
  $due_date = (string)$bill['next_due_date'];

  if ($amount_due <= 0) {
    return false;
  }

  if ($reserve_balance < $amount_due) {
    return false;
  }

  if (autopay_already_processed($pdo_db, $billing_account_id, $due_date)) {
    return false;
  }

  $new_reserve_balance = round($reserve_balance - $amount_due, 2);
  if ($new_reserve_balance < 0) {
    $new_reserve_balance = 0.00;
  }

  $months_to_advance = months_to_advance_for_bill($bill, 1);
  $new_next_due_date = add_months_to_date($due_date, $months_to_advance);

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
      $amount_due,
      $amount_due,
      $today,
      $bill['default_funding_account_id'] !== null ? (int)$bill['default_funding_account_id'] : null,
      $bill['transfer_from_funding_account_id'] !== null ? (int)$bill['transfer_from_funding_account_id'] : null,
      'paid',
      'Auto-processed on due date'
    ]);

    $stmt = $pdo_db->prepare("
      UPDATE billing_accounts
      SET
        reserve_balance = ?,
        next_due_date = ?,
        paid_through_date = ?,
        last_paid_date = ?,
        updated_at = NOW()
      WHERE billing_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");

    $stmt->execute([
      $new_reserve_balance,
      $new_next_due_date,
      $new_next_due_date,
      $today,
      $billing_account_id,
      $user_id
    ]);

    $pdo_db->commit();
    return true;
  } catch (Exception $e) {
    if ($pdo_db->inTransaction()) {
      $pdo_db->rollBack();
    }

    error_log('Autopay processing failed for billing_account_id ' . $billing_account_id . ': ' . $e->getMessage());
    return false;
  }
}

function process_due_autopay_bills(PDO $pdo_db, int $user_id): array
{
  $today = date('Y-m-d');
  $due_bills = load_due_autopay_bills($pdo_db, $user_id, $today);

  $processed_count = 0;
  $skipped_count = 0;

  foreach ($due_bills as $bill) {
    $processed = process_single_autopay_bill($pdo_db, $bill, $today);

    if ($processed) {
      $processed_count++;
    } else {
      $skipped_count++;
    }
  }

  return [
    'processed_count' => $processed_count,
    'skipped_count' => $skipped_count
  ];
}

function advance_next_due_date_by_cycles(array $bill, string $next_due_date, int $cycles): string
{
  if ($cycles < 1) {
    return $next_due_date;
  }

  $months_to_advance = months_to_advance_for_bill($bill, $cycles);
  return add_months_to_date($next_due_date, $months_to_advance);
}

function covered_cycles_from_reserve(array $bill, float $reserve_balance): int
{
  $base_amount = base_amount_for_bill($bill);

  if ($base_amount <= 0) {
    return 0;
  }

  return (int)floor($reserve_balance / $base_amount);
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





function projected_actual_due_date(array $bill): string
{
  $next_due_date = (string)($bill['next_due_date'] ?? '');

  if ($next_due_date === '') {
    return '';
  }

  $reserve_balance = isset($bill['reserve_balance']) ? (float)$bill['reserve_balance'] : 0.00;
  $covered_cycles = covered_cycles_from_reserve($bill, $reserve_balance);

  if ($covered_cycles < 1) {
    return $next_due_date;
  }

  $months_back = months_to_advance_for_bill($bill, $covered_cycles);

  return add_months_to_date($next_due_date, -$months_back);
}


function base_amount_for_bill(array $bill): float
{
  return round((float)($bill['default_amount'] ?? 0), 2);
}




function pooled_paypal_balance(array $rows): float
{
  $total = 0.00;

  foreach ($rows as $row) {
    $paid_from = strtolower(trim((string)($row['paid_from_account'] ?? '')));

    if ($paid_from === 'paypal') {
      $total += (float)($row['reserve_balance'] ?? 0);
    }
  }

  return round($total, 2);
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
        'paid_from_account' => (string)($row['paid_from_account'] ?? '')
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
  $reserve_balance = round((float)($bill['reserve_balance'] ?? 0), 2);

  if ($amount <= 0) {
    return false;
  }

  if ($reserve_balance < $amount) {
    return false;
  }

  if (payment_already_recorded($pdo_db, $billing_account_id, $user_id, $due_date)) {
    return false;
  }

  $new_reserve_balance = round($reserve_balance - $amount, 2);
  if ($new_reserve_balance < 0) {
    $new_reserve_balance = 0.00;
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
      'Auto-deducted from reserve'
    ]);

    $stmt = $pdo_db->prepare("
      INSERT INTO bill_reserve_transactions (
        billing_account_id,
        user_id,
        transaction_type,
        amount,
        transaction_date,
        note
      ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $billing_account_id,
      $user_id,
      'deduction',
      $amount,
      $due_date . ' 00:00:00',
      'Automatic draft deduction'
    ]);

    $stmt = $pdo_db->prepare("
      UPDATE billing_accounts
      SET
        reserve_balance = ?,
        actual_due_date = ?,
        updated_at = NOW()
      WHERE billing_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $new_reserve_balance,
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





function reserve_totals_by_funding_account(array $rows): array
{
  $totals = [];

  foreach ($rows as $row) {
    $account_name = trim((string)($row['paid_from_account'] ?? ''));
    $reserve_balance = isset($row['reserve_balance']) ? (float)$row['reserve_balance'] : 0.00;

    if ($account_name === '') {
      $account_name = 'Unassigned';
    }

    if (!isset($totals[$account_name])) {
      $totals[$account_name] = 0.00;
    }

    $totals[$account_name] += $reserve_balance;
  }

  foreach ($totals as $account_name => $amount) {
    $totals[$account_name] = round($amount, 2);
  }

  ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);

  return $totals;
}

function reserve_total_for_funding_account(array $rows, string $target_account_name): float
{
  $total = 0.00;
  $target = strtolower(trim($target_account_name));

  foreach ($rows as $row) {
    $account_name = strtolower(trim((string)($row['paid_from_account'] ?? '')));

    if ($account_name === $target) {
      $total += (float)($row['reserve_balance'] ?? 0);
    }
  }

  return round($total, 2);
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