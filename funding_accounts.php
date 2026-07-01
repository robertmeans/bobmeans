<?php
require_once 'config/initialize.php';
verify_loggedin();

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'fundingAccts';

$stmt = $pdo_db->prepare("
  SELECT
    funding_account_id,
    account_name,
    account_nickname,
    account_type,
    is_active,
    created_at
  FROM funding_accounts
  WHERE user_id = ?
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule">

    <h1>Funding Accounts</h1>

    <table>
      <thead>
        <tr>
          <th>Ledger</th>
          <th>Account Name</th>
          <th>Nickname</th>
          <th>Type</th>
          <th>Active</th>
          <th>Created</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($rows as $row): ?>

        <tr class="<?php echo ((int)$row['is_active'] === 1) ? 'active-row' : 'inactive-row'; ?>">
          <td>
            <a href="funding_account_ledger.php?funding_account_id=<?php echo (int)$row['funding_account_id']; ?>">Ledger</a>
          </td>
          <td><?php echo htmlspecialchars($row['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>

          <td><?php echo htmlspecialchars((string)$row['account_nickname'], ENT_QUOTES, 'UTF-8'); ?></td>

          <td><?php echo htmlspecialchars((string)$row['account_type'], ENT_QUOTES, 'UTF-8'); ?></td>

          <td><?php echo ((int)$row['is_active'] === 1) ? 'Yes' : 'No'; ?></td>

          <td><?php echo date("m.d.y \\a\\t H:i", strtotime(htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'))); ?></td>

          <td>
            <a href="edit_funding-account.php?funding_account_id=<?php echo (int)$row['funding_account_id']; ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>

    <div class="inner-links">
      <a href="billing_schedule.php">Schedule</a> | 
      <a href="billing_projection.php">Projection</a> | 
      <a href="intake_funding-accounts.php">New Funding</a> |
      <a href="billing_accounts.php">Billing Accounts</a>

    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>