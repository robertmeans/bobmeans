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











function homepage_account_projection_summary(array $rows_for_account): array
{
  if (!$rows_for_account) {
    return [
      'status' => 'empty',
      'message' => 'No active bills assigned.',
      'due_date' => null
    ];
  }

  $pool_amount = 0.00;

  foreach ($rows_for_account as $row) {
    $pool_amount += (float)($row['reserve_balance'] ?? 0);
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
      'message' => date('m.d.y', strtotime($first_attention['due_date'])) . ' - ' . $first_attention['billing_name'],
      'remaining_due' => (float)$first_attention['remaining_due'],
      'due_date' => $first_attention['due_date']
    ];
  }

  if (!empty($projection['events'])) {
    $last_event = end($projection['events']);

    return [
      'status' => 'covered',
      'message' => 'Covered through ' . date('m.d.y', strtotime($last_event['due_date'])),
      'due_date' => $last_event['due_date']
    ];
  }

  return [
    'status' => 'empty',
    'message' => 'No projected bill events.',
    'due_date' => null
  ];
}











$reserve_totals = homepage_reserve_totals($billing_rows, $funding_accounts);

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

foreach ($rows_by_account as $account_name => $rows_for_account) {
  $projection_summary = homepage_account_projection_summary($rows_for_account);
  $account_attention[$account_name] = $projection_summary;

  $events = generate_projected_bill_events($rows_for_account, 12);
  $projection = apply_pool_to_projected_events($events, array_sum(array_map(function ($row) {
    return (float)($row['reserve_balance'] ?? 0);
  }, $rows_for_account)));

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
  $projection = apply_pool_to_projected_events($events, array_sum(array_map(function ($row) {
    return (float)($row['reserve_balance'] ?? 0);
  }, $rows_for_account)));

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

    <h1 style="color:#fff;">Dashboard</h1>

    <div class="dashboard-grid">

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

      <div class="dashboard-card">
        <h2>Next Round Total</h2>
        <div class="dashboard-big">
          $<?php echo number_format($total_underfunded, 2); ?>
        </div>
      </div>

      <div class="dashboard-card">
        <h2>Next Up Per Account</h2>
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
                <?php echo htmlspecialchars($summary['message'], ENT_QUOTES, 'UTF-8'); ?>
                - needs $<?php echo number_format((float)$summary['remaining_due'], 2); ?>
              <?php else: ?>
                <?php echo htmlspecialchars($summary['message'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </a>
          </div>


          <?php endforeach; ?>
        <?php else: ?>
          <p>No active billing accounts found.</p>
        <?php endif; ?>
      </div>

      <div class="dashboard-card">
        <h2>Quick Links</h2>
        <div class="dashboard-line"><a href="billing_projection.php">Billing Projection</a></div>
        <div class="dashboard-line"><a href="billing_schedule.php">Billing Schedule</a></div>
        <div class="dashboard-line"><a href="billing_accounts.php">Billing Accounts</a></div>
        <div class="dashboard-line"><a href="funding_accounts.php">Funding Accounts</a></div>
        <div class="dashboard-line"><a href="intake_billing-accounts.php">Add Billing Account</a></div>
        <div class="dashboard-line"><a href="intake_funding-accounts.php">Add Funding Account</a></div>
      </div>

    </div>

    <div class="dashboard-grid lower-grid">

      <div class="dashboard-card wide-card">
        <h2>Next 10</h2>

        <?php if ($exceptions): ?>
          <table>
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
                  <td><?php echo htmlspecialchars($event['funding_account'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo date('m.d.y', strtotime($event['due_date'])); ?></td>
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
        <h2>Recent Reserve Activity</h2>

        <?php if ($recent_reserve_activity): ?>
          <table>
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
                    <?php
                    echo !empty($row['transaction_date'])
                      ? date("m.d.y \\a\\t H:i", strtotime($row['transaction_date']))
                      : '';
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars((string)$row['billing_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['transaction_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>$<?php echo number_format((float)$row['amount'], 2); ?></td>
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
        <h2>Recent Payments</h2>

        <?php if ($recent_payments): ?>
          <table>
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

    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>