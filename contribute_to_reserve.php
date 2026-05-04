<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$billing_account = null;

$billing_account_id = 0;

if (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
} elseif (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $billing_account = load_billing_account_contribute($pdo_db, $user_id, $billing_account_id);

  if (!$billing_account) {
    $errors[] = 'Billing account not found.';
  }
}

$contribution_amount = '';
$contribution_note = '';

if (is_post_request() && isset($_POST['submit_reserve_contribution']) && $billing_account) {
  $contribution_amount = trim($_POST['contribution_amount'] ?? '');
  $contribution_note = trim($_POST['contribution_note'] ?? '');

  if ($contribution_amount === '' || !is_numeric($contribution_amount) || (float)$contribution_amount <= 0) {
    $errors[] = 'Contribution amount must be greater than 0.';
  }

  if (!$errors) {
    $new_reserve_balance = (float)$billing_account['reserve_balance'] + (float)$contribution_amount;

    $stmt = $pdo_db->prepare("
      UPDATE billing_accounts
      SET
        reserve_balance = ?,
        updated_at = NOW()
      WHERE billing_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      round($new_reserve_balance, 2),
      $billing_account['billing_account_id'],
      $user_id
    ]);

    redirect_to('billing_schedule.php');
  }
}
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Contribute to Reserve</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($billing_account): ?>
      <div class="success" style="display: block;">
        <strong><?php echo htmlspecialchars($billing_account['billing_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        Current Reserve: $<?php echo number_format((float)$billing_account['reserve_balance'], 2); ?><br>
        Next Due Date: <?php echo htmlspecialchars($billing_account['next_due_date'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form method="post">
        <input type="hidden" name="submit_reserve_contribution" value="1">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account['billing_account_id']; ?>">

        <div class="two-col">
          <div class="row">
            <label for="contribution_amount">Contribution Amount</label>
            <input
              type="number"
              step="0.01"
              id="contribution_amount"
              name="contribution_amount"
              value="<?php echo htmlspecialchars($contribution_amount, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="row">
            <label for="contribution_note">Note</label>
            <input
              type="text"
              id="contribution_note"
              name="contribution_note"
              value="<?php echo htmlspecialchars($contribution_note, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        </div>

        <button type="submit">Add to Reserve</button>
      </form>
    <?php endif; ?>

    <div class="inner-links">
      <a href="billing_schedule.php">Billing Schedule</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>