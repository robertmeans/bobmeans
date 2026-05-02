<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

$stmt = $pdo_db->prepare("
  SELECT
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.cadence,
    ba.default_amount,
    ba.annual_cost,
    ba.next_due_date,
    ba.paid_through_date,
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
?>

<div class="intake-form">
  <div class="billing-schedule">

  <h1>Billing Schedule</h1>

    <table>
      <thead>
        <tr>
          <th>Billing Account</th>
          <!-- <th>Cadence</th> -->
          <th>Amount Due</th>
          <!-- <th>Annual Cost</th> -->
          <th>Next Due Date</th>
          <!-- <th>Paid Through</th> -->
          <th>Paid From</th>
          <!--
          <th>Transferred From</th>
          <th>Auto Pay</th>
          <th>Active</th>
          -->

        </tr>
      </thead>
      <tbody>

      <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
          <td>
            <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($row['vendor_name'])): ?>
              <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          </td>
          <?php /* <td><?php echo htmlspecialchars($row['cadence'], ENT_QUOTES, 'UTF-8'); ?></td> */ ?>
          <td>$<?php echo number_format((float)$row['default_amount'], 2); ?></td>
          <?php /* <td>
            <?php echo ($row['annual_cost'] !== null) ? '$' . number_format((float)$row['annual_cost'], 2) : ''; ?>
          </td> */ ?>
          <td><?php 
          $original = $row['next_due_date'];
          $newDate = date("M d, Y", strtotime($original));

          echo $newDate;
          // echo htmlspecialchars((string)$row['next_due_date'], ENT_QUOTES, 'UTF-8'); 

        ?></td>
          <?php /* <td><?php echo htmlspecialchars((string)$row['paid_through_date'], ENT_QUOTES, 'UTF-8'); ?></td> */ ?>
          <td><?php echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?></td>
          
          <?php /* <td><?php echo htmlspecialchars((string)$row['transferred_from_account'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="<?php echo ((int)$row['is_autopay'] === 1) ? 'yes' : 'no'; ?>">
            <?php echo ((int)$row['is_autopay'] === 1) ? 'Yes' : 'No'; ?>
          </td>
          <td class="<?php echo ((int)$row['is_active'] === 1) ? 'yes' : 'no'; ?>">
             <?php echo ((int)$row['is_active'] === 1) ? 'Yes' : 'No'; ?>
          </td> */ ?>

        </tr>
      <?php endwhile; ?>

      </tbody>
    </table>

    <!-- put PayPal current running balance here -->

    <div class="inner-links">
      <a href="intake_funding-accounts.php">New Funding Account</a> | <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>