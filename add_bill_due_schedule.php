<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'add-bill-due-schedule';

$errors = [];
$billing_account_id = 0;
$bill = null;
$due_date = '';
$amount = '';
$note = '';

if (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
} elseif (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT *
    FROM billing_accounts
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $bill = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bill) {
    $errors[] = 'Billing account not found.';
  }
}

if ($bill && $due_date === '' && !empty($bill['actual_due_date']) && $bill['actual_due_date'] !== '0000-00-00') {
  $due_date = $bill['actual_due_date'];
}

if (is_post_request() && isset($_POST['save_bill_due_schedule']) && $bill) {
  $due_date = trim($_POST['due_date'] ?? '');
  $amount = str_replace(',', '', trim($_POST['amount'] ?? ''));
  $note = trim($_POST['note'] ?? '');

  if ($due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
    $errors[] = 'Due date must be in YYYY-MM-DD format.';
  }

  if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    $errors[] = 'Amount must be greater than 0.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO bill_due_schedule (
        billing_account_id,
        user_id,
        due_date,
        amount,
        note,
        updated_at
      ) VALUES (?, ?, ?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        amount = VALUES(amount),
        note = VALUES(note),
        is_active = 1,
        updated_at = NOW()
    ");
    $stmt->execute([
      $billing_account_id,
      $user_id,
      $due_date,
      (float)$amount,
      $note !== '' ? $note : null
    ]);

    sync_custom_bill_actual_due_date($pdo_db, $user_id, $billing_account_id);

    header('Location: bill_details.php?billing_account_id=' . $billing_account_id);
    exit();
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Scheduled Due Event</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($bill): ?>
      <div class="success" style="display:block;">
        <strong><?php echo htmlspecialchars((string)$bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        Cadence: <?php echo htmlspecialchars((string)$bill['cadence'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form method="post">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account_id; ?>">
        <input type="hidden" name="save_bill_due_schedule" value="1">

        <div class="row">
          <label for="due_date">Due Date</label>
          <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row">
          <label for="amount">Amount</label>
          <input type="text" id="amount" name="amount" value="<?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row standalone">
          <label for="note">Note</label>
          <textarea id="note" name="note" rows="4" style="width: 100%; padding: 0.75em; font-size: 1em;"><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Due Event</button>
      </form>

      <div class="inner-links">
        <a href="bill_details.php?billing_account_id=<?php echo (int)$billing_account_id; ?>">Back to Bill Details</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>