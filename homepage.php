<?php
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'dashboard';

/*
  make sure past-due actual drafts are reconciled before
  we summarize the state of the system
*/
if (function_exists('reconcile_due_bills_against_reserves')) {
  reconcile_due_bills_against_reserves($pdo_db, $user_id);
}

/*
  load active billing accounts with funding account names and login urls
*/
$stmt = $pdo_db->prepare("
  SELECT
    ba.user_id, 
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.intake_note,
    ba.cadence,
    ba.reserve_style,
    ba.default_amount,
    ba.actual_due_date,
    ba.renewal_term_months,
    ba.due_day_of_month,
    ba.due_month_of_year,
    ba.is_active,
    ba.default_funding_account_id,
    fa.login_url,
    fa.account_name AS paid_from_account
  FROM billing_accounts ba
  LEFT JOIN funding_accounts fa
    ON ba.default_funding_account_id = fa.funding_account_id
  WHERE ba.user_id = ?
    AND ba.is_active = 1
  ORDER BY ba.billing_name ASC
");
$stmt->execute([$user_id]);
$billing_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* get monthly totals */
$monthly_due_totals = dashboard_monthly_due_totals($pdo_db, $billing_rows, 6);

/*
  load funding accounts so we can show zero-balance accounts too
*/
$stmt = $pdo_db->prepare("
  SELECT
    funding_account_id,
    account_name,
    account_type,
    is_active
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
  helpers local to this page
*/
function homepage_base_amount(array $row): float
{
  return round((float)($row['default_amount'] ?? 0), 2);
}

// $reserve_totals = combined_reserve_totals_by_funding_account($pdo_db, $user_id, $billing_rows);
$reserve_totals = funding_account_pool_totals($pdo_db, $user_id);
$recent_bill_activity = recent_bill_activity($pdo_db, $user_id, 5);

/*
  map billing rows by funding account
*/
$rows_by_account = [];
foreach ($billing_rows as $row) {
  $account_name = trim((string)($row['paid_from_account'] ?? ''));
  if ($account_name === '') {
    $account_name = 'Unassigned';
  }

  if (!isset($rows_by_account[$account_name])) {
    $rows_by_account[$account_name] = [];
  }

  $rows_by_account[$account_name][] = $row;
}

/*
  next up per funding account
*/
$account_attention = [];
$total_underfunded = 0.00;
$attention_rows = [];

$account_attention_list = [];

foreach ($account_attention as $account_name => $summary) {
  $account_attention_list[] = [
    'account_name' => $account_name,
    'summary' => $summary
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


$projection_dashboard = projection_summary_for_all_accounts(
  $pdo_db, 
  $rows_by_account,
  $reserve_totals,
  12
);

$account_summaries = $projection_dashboard['account_summaries'] ?? [];
$account_attention_list = $projection_dashboard['account_attention_list'];
$next_uncovered_bill = $projection_dashboard['next_uncovered_bill'];
$next_uncovered_bills_same_date = $projection_dashboard['next_uncovered_bills_same_date'] ?? [];
$exceptions = array_slice($projection_dashboard['exceptions'], 0, 10);
$monthly_needed_totals = $projection_dashboard['monthly_needed_totals'];
$total_underfunded = $projection_dashboard['total_underfunded'];

$next_up_groups = [];

foreach ($next_uncovered_bills_same_date as $item) {
  $funding_account_id = (int)$item['default_funding_account_id'];

  if (!isset($next_up_groups[$funding_account_id])) {
    $next_up_groups[$funding_account_id] = [
      'funding_account_id' => $funding_account_id,
      'funding_account' => (string)$item['funding_account'],
      'total_remaining_due' => 0.00,
      'bills' => []
    ];
  }

  $next_up_groups[$funding_account_id]['total_remaining_due'] +=
    (float)$item['remaining_due'];

  $next_up_groups[$funding_account_id]['bills'][] = $item;
}

/* funding account / reserve activity */
$stmt = $pdo_db->prepare("
  SELECT
    fart.funding_account_reserve_transaction_id,
    fart.transaction_type,
    fart.amount,
    fart.transaction_date,
    fart.note,
    fa.account_name
  FROM funding_account_reserve_transactions fart
  LEFT JOIN funding_accounts fa
    ON fart.funding_account_id = fa.funding_account_id
  WHERE fart.user_id = ?
  ORDER BY fart.transaction_date DESC, fart.funding_account_reserve_transaction_id DESC
  LIMIT 5
");
$stmt->execute([$user_id]);
$recent_account_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
  recent payments
*/
$stmt = $pdo_db->prepare("
  SELECT
    bp.bill_payment_id,
    bp.amount_paid,
    bp.date_paid,
    bp.status,
    bp.confirmation_note,
    ba.billing_name
  FROM bill_payments bp
  LEFT JOIN billing_accounts ba
    ON bp.billing_account_id = ba.billing_account_id
  WHERE bp.user_id = ?
  ORDER BY bp.date_paid DESC, bp.bill_payment_id DESC
  LIMIT 5
");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);









$coverage_horizon = [];

foreach ($rows_by_account as $account_name => $rows_for_account) {
  $pool_amount = isset($reserve_totals[$account_name])
    ? (float)$reserve_totals[$account_name]
    : 0.00;

  $first_row = reset($rows_for_account);

  $coverage_item = coverage_horizon_for_account(
    $pdo_db,
    (string)$account_name,
    $rows_for_account,
    $pool_amount
  );

  $coverage_item['login_url'] = (string)($first_row['login_url'] ?? '');

  $coverage_horizon[] = $coverage_item;
}

/*
 * Earliest concern first.
 * Accounts without active bills fall to the bottom.
 */
usort($coverage_horizon, function ($a, $b) {
  $days_a = $a['coverage_days'] ?? PHP_INT_MAX;
  $days_b = $b['coverage_days'] ?? PHP_INT_MAX;

  if ($days_a === $days_b) {
    return strcmp(
      (string)$a['account_name'],
      (string)$b['account_name']
    );
  }

  return $days_a <=> $days_b;
});

$multiple_coverage_accounts = count($coverage_horizon) > 1;

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="dashboard">
  <div class="billing-schedule">

    <!-- <h1 style="color:#fff;">Dashboard</h1> -->

    <div class="inner-links">
      <a href="billing_projection.php">Projection</a> |
      <a href="reserve_adjustment.php">Reserve Adjustment</a> | 
      <a href="funding_account_ledger.php">Funding Account Ledger</a>
    </div><?php /* .inner-links */ ?>













    <div class="dashboard-grid">

      <div class="hpc">
        <div class="card-title">
          Next Up
        </div>
        <div class="dashboard-card w-title">

          <h2><span style="font-size:0.7em;color:rgba(0,0,0,0.6);margin-right:6px;font-weight:500;">Today is:</span><?php echo date('l, F jS'); ?></h2>

          <?php if ($next_uncovered_bill): ?>
            <?php
            $today = new DateTime('today');
            $today->setTime(0, 0, 0);
            $next_date = new DateTime($next_uncovered_bill['due_date']);
            $next_date->setTime(0, 0, 0);
            $days = (int)$today->diff($next_date)->format('%r%a');
            ?>

            <div class="dashboard-line">
              <span style="font-size:2em;font-weight:700;"><?php echo $days; ?></span>
              &nbsp;day<?php echo ($days === 1) ? '' : 's'; ?>
              until <span style="font-weight:500;"><?php echo date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($next_uncovered_bill['due_date'])); ?></span>, when
            </div>

            <?php $multiple_funding_accounts = count($next_up_groups) > 1; ?>
            <?php foreach (array_values($next_up_groups) as $index => $group): ?>
              <?php
              $total_needed = round((float)$group['total_remaining_due'], 2);

              $bill_names = array_map(
                static fn($bill) => (string)$bill['billing_name'],
                $group['bills']
              );
              ?>

              <div class="next-up-group<?php echo $multiple_funding_accounts ? ' vstd' : ''; ?>">
                <?php echo ($index === 0) ? '' : 'and '; ?>

                <?php echo htmlspecialchars($group['funding_account'], ENT_QUOTES, 'UTF-8'); ?>
                will need $<?php echo number_format($total_needed, 2); ?>

                <ul class="hcul">
                  <?php foreach ($group['bills'] as $bill): ?>
                    <li>
                      $<?php echo number_format((float)$bill['remaining_due'], 2); ?>
                      for
                      <?php echo htmlspecialchars($bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>

                <div class="mct nu">
                  <a href="reserve_adjustment.php?funding_account_id=<?php
                    echo (int)$group['funding_account_id'];
                  ?>&amount=<?php
                    echo urlencode(number_format($total_needed, 2, '.', ''));
                  ?>&bill=<?php
                    echo urlencode(implode(', ', $bill_names));
                  ?>">
                    add $<?php echo number_format($total_needed, 2); ?>
                  </a>

                  |

                  <a href="billing_projection.php?account=<?php
                    echo urlencode($group['funding_account']);
                  ?>">
                    view projection
                  </a>
                </div>
              </div>
            <?php endforeach; ?>

          <?php else: ?>
            <p>No uncovered bills found in the projection window.</p>
          <?php endif; ?>
        </div>
      </div>






      <div class="hpc">
        <div class="card-title">
          Coverage Horizon
        </div>

        <div class="dashboard-card w-title">

          <?php if ($coverage_horizon): ?>

            <?php foreach ($coverage_horizon as $account): ?>
              <?php
              $coverage_days = $account['coverage_days'];
              $login_url = trim((string)($account['login_url'] ?? ''));

              $is_urgent =
                $account['has_shortfall'] &&
                $coverage_days !== null &&
                $coverage_days <= 30;

              $wrapper_classes = [];

              if ($multiple_coverage_accounts) {
                $wrapper_classes[] = 'vstd';
              }

              if ($is_urgent) {
                $wrapper_classes[] = 'coverage-urgent';
              }
              ?>

              <div <?php
                echo $wrapper_classes
                  ? 'class="' . htmlspecialchars(
                      implode(' ', $wrapper_classes),
                      ENT_QUOTES,
                      'UTF-8'
                    ) . '"'
                  : '';
              ?>>

                <div class="coverage-account">
                  <strong>
                    <?php echo htmlspecialchars(
                      $account['account_name'],
                      ENT_QUOTES,
                      'UTF-8'
                    ); ?>:
                  </strong>

                  $<?php echo number_format(
                    (float)$account['pool_amount'],
                    2
                  ); ?>






                <?php if ($login_url !== ''): ?>
                  <a class="ch-external" href="<?php echo htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                <?php endif; ?>








                </div>

                <div class="coverage-days<?php echo $is_urgent ? ' urgent' : ''; ?>">

                  <?php if (!$account['has_active_bills']): ?>

                    No active bills assigned

                  <?php elseif ($coverage_days !== null): ?>

                    <?php echo !empty($account['is_estimate']) ? 'covered for at least' : 'covered for'; ?>
                    <strong>
                      <?php echo number_format($coverage_days); ?>
                      day<?php echo $coverage_days === 1 ? '' : 's'; ?>
                    </strong>

                    <?php if ($is_urgent): ?>
                      <span class="urgency-indicator">
                        Funding needed soon
                      </span>
                    <?php endif; ?>

                  <?php else: ?>

                    No coverage date available

                  <?php endif; ?>

                </div>

                <div class="mct">
                  <a href="billing_projection.php?account=<?php
                    echo urlencode($account['account_name']);
                  ?>">
                    view projection
                  </a>
                </div>

              </div>

            <?php endforeach; ?>

          <?php else: ?>

            <p>No funding accounts found.</p>

          <?php endif; ?>

        </div><?php /* .dashboard-card .w-title */ ?>

      </div><?php /* .hpc */ ?>






      <div class="hpc">
        <div class="card-title">
          Funding Needed by Month
        </div>
        <div class="dashboard-card w-title">
          
          <?php

          $months_to_show = 4;
          $monthly_needed_totals_display = array_slice($monthly_needed_totals, 0, $months_to_show, true);

          if ($monthly_needed_totals): ?>
            <?php foreach ($monthly_needed_totals_display as $month_key => $amount): ?>
              <div class="dashboard-line<?php if (number_format($amount, 2) === '0.00') { echo ' green'; } ?>">
                <strong><?php echo date('F', strtotime($month_key . '-01')); ?>:</strong>
                $<?php echo number_format($amount, 2); ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p>No projected shortfalls found.</p>
          <?php endif; ?>
        </div>
      </div>

    </div><?php /* .dashboard-grid */ ?>















    <div class="dashboard-grid lower-grid">

      <div class="dashboard-card wide-card">
        <h2>Next 10 Bills Due</h2>

        <?php if ($exceptions): ?>
          <table class="full-width">
            <thead>
              <tr>
                <th>Bill</th>
                <th>Funding</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Remaining Due</th>
              </tr>
            </thead>
            <tbody>
              <?php $linked_first_uncovered = false; /* flag first partial or due for hyperlink in "Remaining Due" column */ ?>
              <?php foreach ($exceptions as $event):
              $fund_account = htmlspecialchars((string)$event['funding_account'], ENT_QUOTES, 'UTF-8');
              $fund_account_id = (int)$event['default_funding_account_id']; ?>

                <tr class="<?php echo htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8'); ?>">
                  <td>
                    <a href="bill_details.php?billing_account_id=<?php echo (int)$event['billing_account_id']; ?>">
                      <?php echo htmlspecialchars($event['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </td>

                  <td>
                    <?php if (!empty($event['default_funding_account_id'])): ?>
                      <a href="funding_account_ledger.php?funding_account_id=<?php echo (int)$event['default_funding_account_id']; ?>">
                        <?php echo htmlspecialchars((string)$event['funding_account'], ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                    <?php else: ?>
                      <?php echo htmlspecialchars((string)$event['funding_account'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                  </td>

                  <td>
                    <div class="wday nt">
                      <?php echo date('M', strtotime($event['due_date'])); ?>
                    </div>
                    <div class="wday nt">
                      <?php echo date('j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($event['due_date'])); ?>
                    </div>
                  </td>

                  <td><?php echo htmlspecialchars(ucfirst($event['status']), ENT_QUOTES, 'UTF-8'); ?></td>

                  <td>

                    <?php
                    $is_uncovered = (
                      ((string)$event['status'] === 'partial' || (string)$event['status'] === 'due') &&
                      !empty($event['default_funding_account_id']) &&
                      (float)$event['remaining_due'] > 0
                    );
                    ?>

                    <?php if ($is_uncovered && !$linked_first_uncovered): ?>
                      <a href="reserve_adjustment.php?funding_account_id=<?php echo (int)$event['default_funding_account_id']; ?>&amount=<?php echo urlencode(number_format((float)$event['remaining_due'], 2, '.', '')); ?>&bill=<?php echo urlencode((string)$event['billing_name']); ?>&transaction_type=contribution">
                        $<?php echo number_format((float)$event['remaining_due'], 2); ?>
                      </a>
                      <?php $linked_first_uncovered = true; ?>
                    <?php else: ?>
                      $<?php echo number_format((float)$event['remaining_due'], 2); ?>
                    <?php endif; ?>

                  </td>

                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>Nothing currently partial or due in the next projection window.</p>
        <?php endif; ?>
      </div>

      <div class="dashboard-card wide-card">
        <h2>Recent Payments</h2>

        <?php if ($recent_bill_activity): ?>
          <table class="full-width">
            <thead>
              <tr>
                <th>When</th>
                <th>Bill</th>
                <th>Amount</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_bill_activity as $row): ?>
                <tr>
                  <td>
                    <?php
                    echo !empty($row['event_datetime'])
                      ? date("m.d.y", strtotime($row['event_datetime']))
                      : '';
                    ?>
                  </td>

                  <td>
                    <a href="bill_details.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">
                      <?php echo htmlspecialchars((string)$row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </td>

                  <td>
                    <?php
                    $sign = ((float)$row['signed_amount'] < 0) ? '-' : '+';
                    ?>
                    <a href="adjust_bill_payment.php?bill_payment_id=<?php echo (int)$row['bill_payment_id']; ?>">
                      <?php echo $sign . '$' . number_format(abs((float)$row['signed_amount']), 2); ?>
                    </a>
                  </td>

                  <td><?php echo htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No recent bill activity yet.</p>
        <?php endif; ?>
      </div>

      <div class="dashboard-card wide-card">
        <h2>Recent Account Adjustments</h2>

        <?php if ($recent_account_adjustments): ?>
          <table class="full-width">
            <thead>
              <tr>
                <th>When</th>
                <th>Account</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_account_adjustments as $row): ?>
                <tr>
                  <td>
                    <?php
                    $haystack = htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8');
                    $needle = "Automatic draft deduction";

                    if (!empty($row['transaction_date']) && !str_contains($haystack, $needle)) {
                      echo date("m.d.y \\a\\t H:i", strtotime($row['transaction_date']));
                    } else {
                      echo date("m.d.y", strtotime($row['transaction_date']));
                    }
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars((string)$row['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['transaction_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td <?php if ((string)$row['transaction_type'] === 'deduction') { echo 'class="ded-red"'; } ?>>
                    <?php
                    $sign = ((string)$row['transaction_type'] === 'deduction') ? '-' : '+';
                    echo $sign . '$' . number_format((float)$row['amount'], 2);
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No reserve adjustments recorded yet.</p>
        <?php endif; ?>
      </div>

    </div>

  </div><?php /* .billing-schedule */ ?>
</div><?php /* .dashboard */ ?>

<?php require '_includes/footer.php'; ?>