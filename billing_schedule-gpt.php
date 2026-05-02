<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_reserve_functions.php';
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

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
    ba.is_autopay,
    ba.is_active,
    fa.account_name AS paid_from_account,
    tfa.account_name AS transferred_from_account
  FROM billing_accounts ba
  LEFT JOIN funding_accounts fa
    ON ba.default_funding_account_id = fa.funding_account_id
  LEFT JOIN funding_accounts tfa
    ON ba.transfer_from_funding_account_id = tfa.funding_account_id
  WHERE ba.user_id = ?
  ORDER BY ba.next_due_date ASC, ba.billing_name ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime('today');
$paypal_target_balance = 0.00;

function count_prepaid_cycles(string $cadence, string $next_due_date, DateTime $today): int
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

  if ($cadence === 'monthly') {
    while (true) {
      $cursor->modify('-1 month');

      if ($cursor > $today) {
        $count++;
      } else {
        break;
      }
    }
  } elseif ($cadence === 'annual') {
    while (true) {
      $cursor->modify('-1 year');

      if ($cursor > $today) {
        $count++;
      } else {
        break;
      }
    }
  }

  return $count;
}

foreach ($rows as $row) {
  $paid_from = strtolower(trim((string)($row['paid_from_account'] ?? '')));

  if ($paid_from === 'paypal') {
    $prepaid_cycles = count_prepaid_cycles(
      (string)$row['cadence'],
      (string)$row['next_due_date'],
      $today
    );

    $paypal_target_balance += ((float)$row['default_amount'] * $prepaid_cycles);
  }
}
?>

<div class="intake-form">
  <div class="billing-schedule">

    <h1>Billing Schedule</h1>

    <table>
      <thead>
        <tr>
          <th>Billing Account</th>
          <th>Amount Due</th>
          <th>Next Due Date</th>
          <th>Paid From</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($row['vendor_name'])): ?>
              <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
            <?php if (!empty($row['intake_note'])): ?>
              <br><small><?php echo htmlspecialchars($row['intake_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          </td>

          <td>$<?php echo number_format((float)$row['default_amount'], 2); ?></td>

          <td>
            <?php
            $original = $row['next_due_date'];
            $newDate = date("M d, Y", strtotime($original));
            echo $newDate;
            ?>
          </td>

          <td><?php echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>

    <div class="paypal-running-balance">
      <strong>PayPal Target Balance:</strong>
      $<?php echo number_format($paypal_target_balance, 2); ?>
    </div>

    <div class="inner-links">
      <a href="intake_funding-accounts.php">New Funding Account</a> |
      <a href="intake_billing-accounts-gpt.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>