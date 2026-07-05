<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'adjust-bill-payment';

$errors = [];
$bill_payment_id = 0;
$payment = null;
$new_actual_amount = '';
$adjustment_note = '';

if (isset($_GET['bill_payment_id']) && ctype_digit((string)$_GET['bill_payment_id'])) {
  $bill_payment_id = (int)$_GET['bill_payment_id'];
} elseif (isset($_POST['bill_payment_id']) && ctype_digit((string)$_POST['bill_payment_id'])) {
  $bill_payment_id = (int)$_POST['bill_payment_id'];
}

if ($bill_payment_id < 1) {
  $errors[] = 'Payment record not found.';
} else {
  $payment = get_bill_payment_by_id($pdo_db, $user_id, $bill_payment_id);

  if (!$payment) {
    $errors[] = 'Payment record not found.';
  } else {
    $new_actual_amount = number_format((float)$payment['amount_paid'], 2, '.', '');
  }
}

if (is_post_request() && isset($_POST['adjust_bill_payment']) && $payment) {
  $new_actual_amount = str_replace(',', '', trim($_POST['new_actual_amount'] ?? ''));
  $adjustment_note = trim($_POST['adjustment_note'] ?? '');

  if ($new_actual_amount === '' || !is_numeric($new_actual_amount) || (float)$new_actual_amount <= 0) {
    $errors[] = 'New actual amount must be greater than 0.';
  }

  if (!$errors) {
    $adjusted = adjust_bill_payment_amount(
      $pdo_db,
      $user_id,
      $bill_payment_id,
      (float)$new_actual_amount,
      $adjustment_note !== '' ? $adjustment_note : null
    );

    if ($adjusted) {
      header('Location: bill_details.php?billing_account_id=' . (int)$payment['billing_account_id']);
      exit();
    } else {
      $errors[] = 'Unable to adjust payment.';
    }
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Adjust Bill Payment</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($payment): ?>
      <div class="success" style="display:block;">
        <strong><?php echo htmlspecialchars((string)$payment['billing_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        Payment Date: <?php echo !empty($payment['date_paid']) ? date('m.d.y', strtotime((string)$payment['date_paid'])) : ''; ?><br>
        Recorded Amount: $<?php echo number_format((float)$payment['amount_paid'], 2); ?><br>
        Funding Account: <?php echo htmlspecialchars((string)($payment['funding_account_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form method="post">
        <input type="hidden" name="bill_payment_id" value="<?php echo (int)$bill_payment_id; ?>">
        <input type="hidden" name="adjust_bill_payment" value="1">

        <div class="row">
          <label for="new_actual_amount">New Actual Amount</label>
          <input
            type="text"
            id="new_actual_amount"
            name="new_actual_amount"
            value="<?php echo htmlspecialchars($new_actual_amount, ENT_QUOTES, 'UTF-8'); ?>"
            required
          >
        </div>

        <div class="row standalone">
          <label for="adjustment_note">Adjustment Note</label>
          <textarea
            id="adjustment_note"
            name="adjustment_note"
            rows="5"
            style="width: 100%; padding: 0.75em; font-size: 1em;"
          ><?php echo htmlspecialchars($adjustment_note, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Adjustment</button>
      </form>

      <div class="inner-links">
        <a href="bill_details.php?billing_account_id=<?php echo (int)$payment['billing_account_id']; ?>">Back to Bill Details</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>