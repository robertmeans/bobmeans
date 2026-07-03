<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'close-funding-account';

$errors = [];
$success = '';
$closure_note = '';
$funding_account_id = 0;
$funding_account = null;
$archive_status = null;

if (isset($_GET['funding_account_id']) && ctype_digit((string)$_GET['funding_account_id'])) {
  $funding_account_id = (int)$_GET['funding_account_id'];
} elseif (isset($_POST['funding_account_id']) && ctype_digit((string)$_POST['funding_account_id'])) {
  $funding_account_id = (int)$_POST['funding_account_id'];
}

if ($funding_account_id < 1) {
  $errors[] = 'Funding account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT *
    FROM funding_accounts
    WHERE funding_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$funding_account_id, $user_id]);
  $funding_account = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$funding_account) {
    $errors[] = 'Funding account not found.';
  }
}

if ($funding_account) {
  $archive_status = funding_account_archive_status($pdo_db, $user_id, $funding_account_id);
}

if (is_post_request() && isset($_POST['archive_funding_account']) && $funding_account) {
  $closure_note = trim($_POST['closure_note'] ?? '');

  $archive_status = funding_account_archive_status($pdo_db, $user_id, $funding_account_id);

  if (!$archive_status['can_archive']) {
    $errors[] = 'This funding account cannot be archived yet.';
  } else {
    $archived = archive_funding_account($pdo_db, $user_id, $funding_account_id, $closure_note);

    if ($archived) {
      header('Location: funding_accounts.php?archived=1');
      exit();
    } else {
      $errors[] = 'Unable to archive funding account.';
    }
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Archive Funding Account</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($funding_account): ?>
      <div class="success" style="display:block;">
        <strong><?php echo htmlspecialchars((string)$funding_account['account_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        Current Balance: $<?php echo number_format((float)$archive_status['current_balance'], 2); ?><br>
        Active Bills Assigned: <?php echo (int)$archive_status['active_bill_count']; ?><br>
        Historical Activity: <?php echo !empty($archive_status['has_history']) ? 'Yes' : 'No'; ?>
      </div>

      <?php if (!empty($archive_status['active_bills'])): ?>
        <h2>Active Bills Still Assigned</h2>
        <table class="full-width">
          <thead>
            <tr>
              <th>Bill</th>
              <th>Next Draft</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($archive_status['active_bills'] as $bill): ?>
              <tr>
                <td>
                  <a href="edit_billing-account.php?billing_account_id=<?php echo (int)$bill['billing_account_id']; ?>">
                    <?php echo htmlspecialchars((string)$bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </td>
                <td>
                  <?php
                  echo !empty($bill['actual_due_date'])
                    ? date('m.d.y', strtotime($bill['actual_due_date']))
                    : '';
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!empty($archive_status['blocking_reasons'])): ?>
        <h2>What Must Be Resolved First</h2>
        <ul>
          <?php foreach ($archive_status['blocking_reasons'] as $reason): ?>
            <li><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($archive_status['can_archive']): ?>
        <form method="post">
          <input type="hidden" name="funding_account_id" value="<?php echo (int)$funding_account_id; ?>">
          <input type="hidden" name="archive_funding_account" value="1">

          <div class="row standalone">
            <label for="closure_note">Archive Note</label>
            <textarea
              id="closure_note"
              name="closure_note"
              rows="4"
              style="width: 100%; padding: 0.75em; font-size: 1em;"
            ><?php echo htmlspecialchars($closure_note, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <button type="submit">Archive Funding Account</button>
        </form>
      <?php endif; ?>

      <div class="inner-links">
        <a href="funding_accounts.php">Back to Funding Accounts</a> |
        <a href="edit_funding-account.php?funding_account_id=<?php echo (int)$funding_account_id; ?>">Edit Funding Account</a> |
        <a href="funding_account_ledger.php?funding_account_id=<?php echo (int)$funding_account_id; ?>">View Ledger</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>