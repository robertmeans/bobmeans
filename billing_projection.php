<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

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
    ba.reserve_balance,
    ba.next_due_date,
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
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pool_amount = pooled_paypal_balance($rows);
$months_ahead = 12;

$events = generate_projected_bill_events($rows, $months_ahead);
$projection = apply_pool_to_projected_events($events, $pool_amount);

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule">

    <?php /* <h1>Billing Projection</h1> */ ?>

    <div class="paypal-running-balance">
      <strong>Pooled PayPal Reserve:</strong>
      $<?php echo number_format($projection['starting_pool'], 2); ?>
    </div>

    <table>
      <thead>
        <tr>
          <th>Billing Account</th>
          <th>Due Date</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Covered</th>
          <th>Remaining Due</th>
          <th>Pool Left</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($projection['events'] as $event): ?>
        <tr class="<?php echo htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8'); ?>">
          <td>
            <?php echo htmlspecialchars($event['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($event['vendor_name'] !== ''): ?>
              <br><small><?php echo htmlspecialchars($event['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
            <?php if ($event['intake_note'] !== ''): ?>
              <br><small><?php echo htmlspecialchars($event['intake_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          </td>

          <td><?php echo date('m.d.y', strtotime($event['due_date'])); ?></td>

          <td>$<?php echo number_format((float)$event['amount'], 2); ?></td>

          <td>
            <?php
            if ($event['status'] === 'paid') {
              echo 'Paid';
            } elseif ($event['status'] === 'partial') {
              echo 'Partial';
            } else {
              echo 'Due';
            }
            ?>
          </td>

          <td>$<?php echo number_format((float)$event['covered_by_pool'], 2); ?></td>

          <td>$<?php echo number_format((float)$event['remaining_due'], 2); ?></td>

          <td>$<?php echo number_format((float)$event['pool_remaining_after'], 2); ?></td>
        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>

    <div class="inner-links">
      <a href="billing_schedule.php">Standard Billing Schedule</a> |
      <a href="intake_funding-accounts.php">New Funding Account</a> |
      <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>