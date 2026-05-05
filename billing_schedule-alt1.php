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

    <?php /* <h1>Billing Schedule</h1> */ ?>

    <table>
      <thead>
        <tr>
          <th class="pay">&nbsp;</th>
          <th>Base</th>
          <th>Billing Account</th>
          <th>Amount Due</th>
          <th>Next Due</th>
          <th>&nbsp;</th>
          <th>In Reserves</th>
        </tr>
      </thead>
      <tbody>

    <?php if (bob()) { ?>
      <tr class="monthlyFixed">
        <td class="paypal">Fixed</td>
        <td>$23.93</td>
        <td>2367 | Protective Life</td>
        <td>$23.93</td>
        <td>7<sup>th</sup></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr class="monthlyFixed">
        <td class="paypal">Fixed</td>
        <td>$12.00</td>
        <td>2367 | 1st Bank Activity Charge</td>
        <td>$12.00</td>
        <td>22<sup>nd</sup></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
      <tr class="monthlyFixed lot">
        <td class="paypal">Fixed</td>
        <td>$14.00</td>
        <td>4009 | 1st Bank Activity Charge</td>
        <td>$14.00</td>
        <td>28<sup>th</sup></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
      </tr>
    <?php } ?>


      <?php foreach ($rows as $row): ?>
        <tr class="<?php 
            $original = $row['next_due_date']; /* $original reused below */
            $classDate = date("y", strtotime($original));

            if ($classDate === date('y')) { echo 'thisYear'; }
            elseif ($classDate === date('y', strtotime('+1 year'))) { echo 'nextYear'; }
            else { echo 'twoYears'; }

            // if ($classDate <= date('y', strtotime('+3 months'))) { echo 'w-inThreeMonths'; }
            // elseif ($classDate === date('y', strtotime('+1 year'))) { echo 'nextYear'; }
            // else { echo 'twoYears'; }

         ?>">
          <td class="pay"><?php /* Action | Pay | $ */ ?>


            <form class="conicon" method="post" action="contribute_to_reserve.php">
              <input type="hidden" name="billing_account_id" value="<?php echo (int)$row['billing_account_id']; ?>">
              <button type="submit" class="pay"><i class="fas fa-plus-circle"></i></button>
            </form> 

            <?php if ($row['is_autopay'] != 1) { ?>
              <form method="post" action="pay_bill.php">
                <input type="hidden" name="billing_account_id" value="<?php echo (int)$row['billing_account_id']; ?>">
                <button type="submit" class="pay"><i class="fas fa-dollar-sign"></i></button>
              </form>
            <?php } ?>

          </td>


          <td><?php /* base */ ?>
            $<?php echo number_format((float)$row['default_amount'], 2); ?>
          </td>

          <td><?php /* Billing Account */ ?>
            <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>


<?php if (false): /* genius! 05.04.26 09:16 - I just learned how to comment blocks of php! */?>
            <?php if (!empty($row['vendor_name'])): ?>
              <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
            <?php if (!empty($row['intake_note'])): ?>
              <br><small><?php echo htmlspecialchars($row['intake_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
<?php endif; ?>

          </td>



          <td><?php /* Amount Due */ ?>
            $<?php echo number_format(amount_due_after_reserve($row), 2); ?>
          </td>

          <td><?php /* Next Due Date */ ?>
            <?php
            /* $original = $row['next_due_date']; // declared at top of foreach */
            $newDate = date("m.d.y", strtotime($original));
            echo $newDate;
            ?>
          </td>

          <td class="paypal">
            <?php if ($row['paid_from_account'] === 'PayPal') { ?>
              <a href="https://paypal.com" target="_blank"><img class="paypal-icon" src="_images/paypal.webp"></a>
            <?php } else {
              echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8');
            } ?>
          </td><?php /* Paid From */ ?>

          <td><?php /* In Reserves */ ?>
            $<?php echo number_format((float)$row['reserve_balance'], 2); ?>
          </td> 
        </tr>
      <?php endforeach; ?>

      <tr class="tbr"><?php /* target balance row */ ?>
        <td>&nbsp;</td>
        <td colspan="2" style="padding:5px 0px;">PayPal Target Balance</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td><?php /* In Reserves */ ?>
          $<?php echo number_format($paypal_target_balance, 2); ?>  
        </td>
      </tr>


      </tbody>
    </table>

<?php if (false): ?>
    <div class="paypal-running-balance">
      <strong>PayPal Target Balance:</strong>
      $<?php echo number_format($paypal_target_balance, 2); ?>
    </div>
<?php endif; ?>

    <div class="inner-links">
      <a href="intake_funding-accounts.php">New Funding Account</a> |
      <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>