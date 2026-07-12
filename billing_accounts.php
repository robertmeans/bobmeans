<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'billAcccounts';

$stmt = $pdo_db->prepare("
  SELECT
    ba.*,
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

    <?php if (isset($_GET['archived']) && $_GET['archived'] === '1'): ?>
      <div class="success" style="display:block;">
        Billing account archived.
      </div>
    <?php endif; ?>
    
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Billing Account</th>
            <th>Cadence</th>
            <th>Amount</th>
            <th>Next Draft</th>
            <th>Paid From</th>
            <th>Active</th>
            <?php /* <th>Website</th> */ ?>
            <th>Manage</th>
          </tr>
        </thead>
        <tbody>

        <?php foreach ($rows as $row): ?>
          <tr class="<?php echo ((int)$row['is_active'] === 1) ? 'active-row' : 'inactive-row'; ?>">
            <td>
              <?php echo htmlspecialchars($row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>


              <?php if (!empty($row['login_url'])): ?>
                <a class="ch-external" href="<?php echo htmlspecialchars((string)$row['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                  <i class="fas fa-external-link-alt"></i>
                </a>
              <?php endif; ?>




              <?php if (!empty($row['vendor_name'])): ?>
                <br><small><?php echo htmlspecialchars($row['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>

            <td><?php echo htmlspecialchars((string)$row['cadence'], ENT_QUOTES, 'UTF-8'); ?></td>

            <td>$<?php echo number_format((float)$row['default_amount'], 2); ?></td>

            <td>
              <?php
                if (!empty($row['actual_due_date']) && $row['actual_due_date'] !== '0000-00-00') {
                  echo date('m.d.y', strtotime($row['actual_due_date']));
                } else {
                  echo '&nbsp;';
                }
              ?>
            </td>

            <td><?php echo htmlspecialchars((string)$row['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?></td>

            <td>
              <?php echo ((int)$row['is_active'] === 1) ? 'Yes' : 'No'; ?>
            </td>





<?php /*
            <td>
              <?php if (!empty($row['login_url'])): ?>
                <a href="<?php echo htmlspecialchars((string)$row['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                  Website
                </a>
              <?php endif; ?>
            </td>
*/ ?>






            <td>
              <a href="bill_details.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Details</a>
              |
              <a href="edit_billing-account.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Edit</a>
              |
              <a href="intake_billing-accounts.php?duplicate_billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Duplicate</a>
              <?php if ((int)$row['is_active'] === 1): ?>
                |
                <a href="close_billing-account.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">Archive</a>
              <?php endif; ?>
            </td>

            
          </tr>
        <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="billing_projection.php">Projection</a> |
      <a href="intake_billing-accounts.php">New Bill</a> | 
      <a href="funding_accounts.php">Funding Accounts</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>