<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

process_due_autopay_bills($pdo_db, $user_id);

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
    ba.reserve_balance,
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
    AND ba.is_active = 1
  ORDER BY ba.next_due_date ASC, ba.billing_name ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


$paypal_target_balance = 0.00;

foreach ($rows as $row) {
  $paid_from = strtolower(trim((string)($row['paid_from_account'] ?? '')));

  if ($paid_from === 'paypal') {
    $paypal_target_balance += (float)$row['reserve_balance'];
  }
}

?>


<div class="intake-form">
  <div class="billing-schedule">

    <h1>Billing Schedule</h1>

    <table>
      <thead>
        <tr>
          <th class="pay">&nbsp;</th>
          
          <th>Billing Account</th>
          <th>Amount Due</th>
          <th>Next Due</th>
          <th>&nbsp;</th>
          <th>In Reserves</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="pay"><?php /* Action | Pay | $ */ ?>


            <form class="conicon" method="post" action="contribute_to_reserve.php">
              <input type="hidden" name="billing_account_id" value="<?php echo (int)$row['billing_account_id']; ?>">
              <button type="submit" class="pay"><i class="fas fa-plus-circle"></i></button>
            </form> 

            
            <form method="post" action="pay_bill.php">
              <input type="hidden" name="billing_account_id" value="<?php echo (int)$row['billing_account_id']; ?>">
              <button type="submit" class="pay"><i class="fas fa-dollar-sign"></i></button>
            </form>

          </td>

           

          <td><?php /* Billing Account */ ?>
            <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($row['vendor_name'])): ?>
              <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
            <?php if (!empty($row['intake_note'])): ?>
              <br><small><?php echo htmlspecialchars($row['intake_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          </td>

          <td><?php /* Amount Due */ ?>
            $<?php echo number_format(amount_due_after_reserve($row), 2); ?>
          </td>

          <td><?php /* Next Due Date */ ?>
            <?php
            $original = $row['next_due_date'];
            $newDate = date("m.d.y", strtotime($original));
            echo $newDate;
            ?>
          </td>

          <td>
            <?php if ($row['paid_from_account'] === 'PayPal') { ?>
              <img class="paypal-icon" src="_images/paypal.webp">
            <?php } else {
              echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8');
            } ?>
          </td><?php /* Paid From */ ?>

          <td><?php /* In Reserves */ ?>
            $<?php echo number_format((float)$row['reserve_balance'], 2); ?>
          </td> 
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
      <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>