<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'billAcccounts';

$stmt = $pdo_db->prepare("
  SELECT
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.cadence,
    ba.reserve_style,
    ba.default_amount,
    ba.reserve_balance,
    ba.next_due_date,
    ba.renewal_term_months,
    ba.due_day_of_month,
    ba.due_month_of_year,
    ba.is_autopay,
    ba.is_active,
    fa.account_name AS paid_from_account
  FROM billing_accounts ba
  LEFT JOIN funding_accounts fa
    ON ba.default_funding_account_id = fa.funding_account_id
  WHERE ba.user_id = ?
  ORDER BY ba.billing_name ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule">

    <h1>Billing Accounts</h1>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Billing Account</th>
            <th>Cadence</th>
            <th>Amount</th>
            <th>In Reserves</th>
            <th>Next Due Date</th>
            <th>Paid From</th>
            <th>Active</th>
            <th>Edit</th>
            <th>Duplicate</th>
          </tr>
        </thead>
        <tbody>

        <?php foreach ($rows as $row): ?>
          <tr class="<?php echo ((int)$row['is_active'] === 1) ? 'active-row' : 'inactive-row'; ?>">
            <td>
              <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
              <?php if (!empty($row['vendor_name'])): ?>
                <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>

            <td><?php echo htmlspecialchars((string)$row['cadence'], ENT_QUOTES, 'UTF-8'); ?></td>

            <td>$<?php echo number_format((float)$row['default_amount'], 2); ?></td>

            <td>$<?php echo number_format((float)$row['reserve_balance'], 2); ?></td>

            <td>
              <?php
              if (!empty($row['next_due_date'])) {
                echo date('m.d.y', strtotime($row['next_due_date']));
              } else {
                echo '&nbsp;';
              }
              ?>
            </td>

            <td><?php echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?></td>

            <td>
              <?php echo ((int)$row['is_active'] === 1) ? 'Yes' : 'No'; ?>
            </td>

            <td>
              <a href="edit_billing-account.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Edit</a>
            </td>

            <td>
              <a href="intake_billing-accounts.php?duplicate_billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Duplicate</a>
            </td>

            
          </tr>
        <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="billing_projection.php">Billing Projection</a> |
      <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>