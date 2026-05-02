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

function paypal_reserved_amount_for_bill(array $row, DateTime $today): float
{
  $paid_from = strtolower(trim((string)($row['paid_from_account'] ?? '')));
  if ($paid_from !== 'paypal') {
    return 0.00;
  }

  $cadence = (string)($row['cadence'] ?? '');
  $reserve_style = (string)($row['reserve_style'] ?? 'sinking_fund');
  $default_amount = (float)($row['default_amount'] ?? 0);
  $annual_cost = isset($row['annual_cost']) && $row['annual_cost'] !== null
    ? (float)$row['annual_cost']
    : 0.00;
  $next_due_date = (string)($row['next_due_date'] ?? '');
  $last_paid_date = isset($row['last_paid_date']) ? (string)$row['last_paid_date'] : null;
  $renewal_term_months = isset($row['renewal_term_months'])
    ? (int)$row['renewal_term_months']
    : 12;

  if ($cadence === 'monthly' && $reserve_style === 'sinking_fund') {
    $cycles = count_prepaid_monthly_cycles($next_due_date, $today);
    return round($default_amount * $cycles, 2);
  }

  if ($cadence === 'annual' && $reserve_style === 'prepaid') {
    $annual_base = $annual_cost > 0 ? $annual_cost : $default_amount;

    return annual_prepaid_rebuild_amount(
      $annual_base,
      $last_paid_date,
      $renewal_term_months,
      $today
    );
  }

  return 0.00;
}