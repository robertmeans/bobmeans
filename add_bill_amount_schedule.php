<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'add-bill-amount-schedule';

$errors = [];
$billing_account_id = 0;
$bill = null;
$effective_due_date = '';
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

if ($bill && $effective_due_date === '' && !empty($bill['actual_due_date']) && $bill['actual_due_date'] !== '0000-00-00') {
  $effective_due_date = $bill['actual_due_date'];
}

if (is_post_request() && isset($_POST['save_bill_amount_schedule']) && $bill) {
  $effective_due_date = trim($_POST['effective_due_date'] ?? '');
  $amount = str_replace(',', '', trim($_POST['amount'] ?? ''));
  $note = trim($_POST['note'] ?? '');

  if ($effective_due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_due_date)) {
    $errors[] = 'Effective due date must be in YYYY-MM-DD format.';
  }


  if (!$errors && !bill_schedule_date_matches_bill($bill, $effective_due_date)) {
    if ((string)$bill['cadence'] === 'monthly') {
      $errors[] = 'Effective due date must match this bill’s scheduled draft day.';
    } elseif ((string)$bill['cadence'] === 'annual') {
      $errors[] = 'Effective due date must match this bill’s scheduled renewal month and day.';
    } else {
      $errors[] = 'Effective due date does not match this bill’s expected schedule.';
    }
  }

  if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    $errors[] = 'Amount must be greater than 0.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO bill_amount_schedule (
        billing_account_id,
        user_id,
        effective_due_date,
        amount,
        note,
        updated_at
      ) VALUES (?, ?, ?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        amount = VALUES(amount),
        note = VALUES(note),
        updated_at = NOW()
    ");
    $stmt->execute([
      $billing_account_id,
      $user_id,
      $effective_due_date,
      (float)$amount,
      $note !== '' ? $note : null
    ]);

    header('Location: bill_details.php?billing_account_id=' . $billing_account_id);
    exit();
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Scheduled Bill Amount</h1>

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

        <?php if ((string)$bill['cadence'] === 'monthly' && !empty($bill['due_day_of_month'])): ?>
          Scheduled draft day: <?php echo (int)$bill['due_day_of_month']; ?> of each month
        <?php elseif ((string)$bill['cadence'] === 'annual' && !empty($bill['due_day_of_month']) && !empty($bill['due_month_of_year'])): ?>
          Scheduled renewal: <?php echo (int)$bill['due_month_of_year']; ?>/<?php echo (int)$bill['due_day_of_month']; ?> each year
        <?php endif; ?>

      </div>

      <form method="post">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account_id; ?>">
        <input type="hidden" name="save_bill_amount_schedule" value="1">

        <div class="row">
          <label for="effective_due_date">Effective Due Date</label>
          <input type="date" id="effective_due_date" name="effective_due_date" value="<?php echo htmlspecialchars($effective_due_date, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row">
          <label for="amount">Amount</label>
          <input type="text" id="amount" name="amount" value="<?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row standalone">
          <label for="note">Note</label>
          <textarea id="note" name="note" rows="4" style="width: 100%; padding: 0.75em; font-size: 1em;"><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Scheduled Amount</button>
      </form>

      <div class="inner-links">
        <a href="bill_details.php?billing_account_id=<?php echo (int)$billing_account_id; ?>">Back to Bill Details</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>