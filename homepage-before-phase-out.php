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
  load active billing accounts with funding account names
*/
$stmt = $pdo_db->prepare("
  SELECT
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.intake_note,
    ba.cadence,
    ba.reserve_style,
    ba.default_amount,
    ba.reserve_balance,
    ba.next_due_date,
    ba.actual_due_date,
    ba.renewal_term_months,
    ba.due_day_of_month,
    ba.due_month_of_year,
    ba.is_active,
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
$monthly_due_totals = dashboard_monthly_due_totals($billing_rows, 6);

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

function homepage_reserve_totals(array $billing_rows, array $funding_accounts): array
{
  $totals = [];

  foreach ($funding_accounts as $account) {
    $name = trim((string)$account['account_name']);
    if ($name !== '') {
      $totals[$name] = 0.00;
    }
  }

  foreach ($billing_rows as $row) {
    $account_name = trim((string)($row['paid_from_account'] ?? ''));
    if ($account_name === '') {
      $account_name = 'Unassigned';
    }

    if (!isset($totals[$account_name])) {
      $totals[$account_name] = 0.00;
    }

    $totals[$account_name] += (float)($row['reserve_balance'] ?? 0);
  }

  foreach ($totals as $account_name => $amount) {
    $totals[$account_name] = round($amount, 2);
  }

  ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);

  return $totals;
}

function homepage_amount_needed(array $row): float
{
  $base_amount = homepage_base_amount($row);
  $reserve_balance = (float)($row['reserve_balance'] ?? 0.00);
  $remaining = $base_amount - $reserve_balance;

  if ($remaining < 0) {
    $remaining = 0.00;
  }

  return round($remaining, 2);
}

function homepage_account_projection_summary(array $rows_for_account, float $pool_amount): array
{
  if (!$rows_for_account) {
    return [
      'status' => 'empty',
      'message' => 'No active bills assigned.',
      'due_date' => null
    ];
  }

  $pool_amount = round($pool_amount, 2);

  $events = generate_projected_bill_events($rows_for_account, 12);
  $projection = apply_pool_to_projected_events($events, $pool_amount);

  $first_attention = null;

  foreach ($projection['events'] as $event) {
    if ($event['status'] === 'partial' || $event['status'] === 'due') {
      $first_attention = $event;
      break;
    }
  }

  if ($first_attention) {
    return [
      'status' => $first_attention['status'],
      // 'message' => date('m.d.y', strtotime($first_attention['due_date'])) . ' - ' . $first_attention['billing_name'],
      'message' => date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($first_attention['due_date'])) . ' <span style="font-size:0.8em;display:inline-block;position:relative;top:-3px;margin:0 5px;">●</span> ',
      'remaining_due' => (float)$first_attention['remaining_due'],
      'due_date' => $first_attention['due_date']
    ];
  }

  if (!empty($projection['events'])) {
    $last_event = end($projection['events']);

    return [
      'status' => 'covered',
      'message' => 'Covered through ' . date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($last_event['due_date'])),
      'due_date' => $last_event['due_date']
    ];
  }

  return [
    'status' => 'empty',
    'message' => 'No projected bill events.',
    'due_date' => null
  ];
}

$reserve_totals = combined_reserve_totals_by_funding_account($pdo_db, $user_id, $billing_rows);

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


/* this gives you the next bill due regardless of whether covered or not. see: 0630261135 */
// $next_bill_countdown = days_until_next_bill_date($rows_by_account, $reserve_totals, 12);

/* this gives you the next uncovered bill due date */
$next_uncovered_bill = days_until_next_uncovered_bill_date($rows_by_account, $reserve_totals, 12);

/* 
  left, unpaid, per month
*/
$monthly_needed_totals = [];
$today = new DateTime('first day of this month');
$today->setTime(0, 0, 0);

/* to change the # of months shown, change 3 values. below is #1 (now search: 0629260931 for #2) */
for ($i = 0; $i < 4; $i++) {
  $month = clone $today;
  $month->modify('+' . $i . ' months');
  $key = $month->format('Y-m');
  $monthly_needed_totals[$key] = 0.00;
}

foreach ($rows_by_account as $account_name => $rows_for_account) {
  $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;
  /* 0629260931 - #2 at end of line below. search same string in billing_functions.php for #3. */
  $account_monthly_needed = dashboard_monthly_needed_totals($rows_for_account, $pool_amount, 4);

  foreach ($account_monthly_needed as $month_key => $amount) {
    if (!isset($monthly_needed_totals[$month_key])) {
      $monthly_needed_totals[$month_key] = 0.00;
    }

    $monthly_needed_totals[$month_key] += $amount;
  }
}

foreach ($monthly_needed_totals as $month_key => $amount) {
  $monthly_needed_totals[$month_key] = round($amount, 2);
}


/*
  next up per funding account
*/
$account_attention = [];
$total_underfunded = 0.00;
$attention_rows = [];

foreach ($rows_by_account as $account_name => $rows_for_account) {
  $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;

  $projection_summary = homepage_account_projection_summary($rows_for_account, $pool_amount);
  $account_attention[$account_name] = $projection_summary;

  $events = generate_projected_bill_events($rows_for_account, 12);
  $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;
  $projection = apply_pool_to_projected_events($events, $pool_amount);

  foreach ($projection['events'] as $event) {
    if ($event['status'] === 'partial' || $event['status'] === 'due') {
      $attention_rows[] = array_merge($event, [
        'funding_account' => $account_name
      ]);
      $total_underfunded += (float)$event['remaining_due'];
      break;
    }
  }
}

$total_underfunded = round($total_underfunded, 2);

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

/*
  uncovered / partial list across all accounts
*/
$exceptions = [];

foreach ($rows_by_account as $account_name => $rows_for_account) {
  $events = generate_projected_bill_events($rows_for_account, 12);
  $pool_amount = isset($reserve_totals[$account_name]) ? (float)$reserve_totals[$account_name] : 0.00;
  $projection = apply_pool_to_projected_events($events, $pool_amount);

  foreach ($projection['events'] as $event) {
    if ($event['status'] === 'partial' || $event['status'] === 'due') {
      $exceptions[] = array_merge($event, [
        'funding_account' => $account_name
      ]);
    }
  }
}

usort($exceptions, function ($a, $b) {
  if ($a['due_date'] === $b['due_date']) {
    return strcmp((string)$a['billing_name'], (string)$b['billing_name']);
  }

  return strcmp((string)$a['due_date'], (string)$b['due_date']);
});

$exceptions = array_slice($exceptions, 0, 10);


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
  recent reserve activity
*/
$stmt = $pdo_db->prepare("
  SELECT
    brt.bill_reserve_transaction_id,
    brt.transaction_type,
    brt.amount,
    brt.transaction_date,
    brt.note,
    ba.billing_name
  FROM bill_reserve_transactions brt
  LEFT JOIN billing_accounts ba
    ON brt.billing_account_id = ba.billing_account_id
  WHERE brt.user_id = ?
  ORDER BY brt.transaction_date DESC, brt.bill_reserve_transaction_id DESC
  LIMIT 5
");
$stmt->execute([$user_id]);
$recent_reserve_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="dashboard">
  <div class="billing-schedule">

    <!-- <h1 style="color:#fff;">Dashboard</h1> -->

    <div class="inner-links">
      <a href="billing_projection.php">Projection</a> |
      <a href="reserve_adjustment.php">Reserve Adjustment</a>
    </div>

    <div class="dashboard-grid">

<?php /*
      <div class="dashboard-card">
        <h2>Reserve Totals</h2>
        <?php if ($reserve_totals): ?>
          <?php foreach ($reserve_totals as $account_name => $amount): ?>
            <div class="dashboard-line">
              <strong><?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>:</strong>
              $<?php echo number_format($amount, 2); ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No funding accounts found.</p>
        <?php endif; ?>
      </div>
*/ ?>

<?php /* this gives you the next bill due date regardless of covered or not. see: 0630261135 

<div class="dashboard-card">
  <h2>Next Bill Countdown</h2>

  <?php if ($next_bill_countdown): ?>
    <div class="dashboard-big">
      <?php echo (int)$next_bill_countdown['days']; ?>
    </div>
    <div class="dashboard-line">
      day<?php echo ((int)$next_bill_countdown['days'] === 1) ? '' : 's'; ?> until
      <?php echo htmlspecialchars($next_bill_countdown['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <div class="dashboard-line">
      <?php echo date('m.d.y', strtotime($next_bill_countdown['due_date'])); ?>
      <?php if ($next_bill_countdown['funding_account'] !== ''): ?>
        — <?php echo htmlspecialchars($next_bill_countdown['funding_account'], ENT_QUOTES, 'UTF-8'); ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p>No upcoming bills found.</p>
  <?php endif; ?>
</div>

*/ ?>

      <div class="dashboard-card">
         <h2><span style="font-size:0.7em;color:rgba(0,0,0,0.6);">Today is: </span> <?php echo date('l, F jS'); ?></h2>

        <?php if ($next_uncovered_bill): ?>
          <div class="dashboard-line">
            <span style="font-size:2em;font-weight:700;"><?php echo (int)$next_uncovered_bill['days']; ?></span> &nbsp;day<?php echo ((int)$next_uncovered_bill['days'] === 1) ? '' : 's'; ?> 'til
          </div>
          <?php /* 
          <div class="dashboard-line">
            day<?php echo ((int)$next_uncovered_bill['days'] === 1) ? '' : 's'; ?> until
            <?php echo htmlspecialchars($next_uncovered_bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          */ ?>
          <div class="dashboard-line">
            <?php echo date('M j\<\s\u\p\>S\<\/\s\u\p\>', strtotime($next_uncovered_bill['due_date'])); ?>
            when <?php echo htmlspecialchars($next_uncovered_bill['funding_account'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <div class="dashboard-line">
            Needs $<?php echo number_format((float)$next_uncovered_bill['remaining_due'], 2); ?>
          </div>
        <?php else: ?>
          <p>No uncovered bills found in the projection window.</p>
        <?php endif; ?>
      </div>



      <div class="dashboard-card">
        <h2>Next Due Per Funding Account</h2>
        <?php if ($account_attention_list): ?>
          <?php foreach ($account_attention_list as $item): ?>
            <?php
            $account_name = $item['account_name'];
            $summary = $item['summary'];
            ?>


          <div class="dashboard-line <?php echo htmlspecialchars((string)$summary['status'], ENT_QUOTES, 'UTF-8'); ?>">


            <strong><?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>:</strong>

            <a class="account-jump" href="billing_projection.php?account=<?php echo urlencode($account_name); ?>">
              <?php if ($summary['status'] === 'partial' || $summary['status'] === 'due'): ?>
                <?php echo $summary['message']; ?>
                 $<?php echo number_format((float)$summary['remaining_due'], 2); ?>
              <?php else: ?>
                <?php echo $summary['message']; ?>
              <?php endif; ?>
            </a>


          </div>




          <?php endforeach; ?>
        <?php else: ?>
          <p>No active billing accounts found.</p>
        <?php endif; ?>
      </div>


<?php /* this is total remaining per month - it does not account for what is already covered, just what is left to pay -

      <div class="dashboard-card">
        <h2>Due by Month</h2>

        <?php if ($monthly_due_totals): ?>
          <?php foreach ($monthly_due_totals as $month_key => $amount): ?>
            <div class="dashboard-line">
              <strong><?php echo date('M Y', strtotime($month_key . '-01')); ?>:</strong>
              $<?php echo number_format($amount, 2); ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No projected bills found.</p>
        <?php endif; ?>
      </div>
*/ ?>

<?php /*  this is what remains unpaid per month. 
          if you want to change # of months shown, search: 0629260931 */ ?>
      <div class="dashboard-card">
        <h2>Due by Month</h2>

        <?php if ($monthly_needed_totals): ?>
          <?php foreach ($monthly_needed_totals as $month_key => $amount): ?>
            <div class="dashboard-line<?php if (number_format($amount, 2) === '0.00') { echo ' green'; } ?>">
              <strong><?php echo date('F', strtotime($month_key . '-01')); ?>:</strong>
              $<?php echo number_format($amount, 2); ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No projected shortfalls found.</p>
        <?php endif; ?>
      </div>


<?php /*
      <div class="dashboard-card">
        <h2>Quick Links</h2>
        <!-- <div class="dashboard-line"><a href="billing_schedule.php">Billing Schedule</a></div> -->
        <div class="dashboard-line"><a href="billing_projection.php">Billing Projection</a></div>
        <div class="dashboard-line"><a href="reserve_adjustment.php">Reserve Adjustment</a></div>



        <div class="dashboard-line"><a href="billing_accounts.php">Billing Accounts</a></div>
        <div class="dashboard-line"><a href="funding_accounts.php">Funding Accounts</a></div>
        <div class="dashboard-line"><a href="intake_billing-accounts.php">Add Billing Account</a></div>
        <div class="dashboard-line"><a href="intake_funding-accounts.php">Add Funding Account</a></div>


      </div>
*/ ?>





    </div>

    <div class="dashboard-grid lower-grid">

      <div class="dashboard-card wide-card">
        <h2>Next 10</h2>

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
              <?php foreach ($exceptions as $event): ?>
                <tr class="<?php echo htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8'); ?>">
                  <td>
                    <a href="bill_details.php?billing_account_id=<?php echo (int)$event['billing_account_id']; ?>">
                      <?php echo htmlspecialchars($event['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </td>
                  <td>
                    <?php if ($event['funding_account'] === 'PayPal') { ?>
                      <a href="https://paypal.com" target="_blank"><img class="paypal-icon" src="_images/paypal.webp"></a>
                    <?php } else {
                      echo htmlspecialchars((string)$event['funding_account'], ENT_QUOTES, 'UTF-8');
                    } ?>
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
                  <td>$<?php echo number_format((float)$event['remaining_due'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>Nothing currently partial or due in the next projection window.</p>
        <?php endif; ?>
      </div>

      <div class="dashboard-card wide-card">
        <h2>Recent Bill Activity</h2>

        <?php if ($recent_reserve_activity): ?>
          <table class="full-width">
            <thead>
              <tr>
                <th>When</th>
                <th>Bill</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_reserve_activity as $row): ?>
                <tr>
                  <td>
                    <div class="wday">
                      <?php
                      echo !empty($row['transaction_date'])
                        ? date("D,", strtotime($row['transaction_date']))
                        : '';
                      ?>
                    </div>
                    <div class="wday">
                      <?php
                      echo !empty($row['transaction_date'])
                        ? date("m/d", strtotime($row['transaction_date']))
                        : '';
                      ?> 
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars((string)$row['billing_name'], ENT_QUOTES, 'UTF-8'); ?></td>
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
          <p>No reserve activity yet.</p>
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
                    echo !empty($row['transaction_date'])
                      ? date("D, m/d \\a\\t H:i", strtotime($row['transaction_date']))
                      : '';
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

      <?php /* 
      <div class="dashboard-card wide-card">
        <h2>Recent Payments</h2>

        <?php if ($recent_payments): ?>
          <table class="full-width">
            <thead>
              <tr>
                <th>When</th>
                <th>Bill</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_payments as $row): ?>
                <tr>
                  <td>
                    <?php
                    echo !empty($row['date_paid'])
                      ? date("m.d.y", strtotime($row['date_paid']))
                      : '';
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars((string)$row['billing_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>$<?php echo number_format((float)$row['amount_paid'], 2); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['confirmation_note'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No payments recorded yet.</p>
        <?php endif; ?>
      </div>
      <?php */ ?>

    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>