<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$billing_account = null;
$funding_accounts = [];

/* get billing account id from either POST or GET */
$billing_account_id = 0;

if (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
} elseif (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $billing_account = load_billing_account($pdo_db, $user_id, $billing_account_id);

  if (!$billing_account) {
    $errors[] = 'Billing account not found.';
  }
}

/* load funding accounts for override dropdown */
$stmt = $pdo_db->prepare("
  SELECT funding_account_id, account_name
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cycles_paid = '1';
$date_paid = date('Y-m-d');
$amount_paid = '';
$funding_account_id = '';
$transfer_from_funding_account_id = '';
$confirmation_note = '';

if ($billing_account) {
  $funding_account_id = (string)($billing_account['default_funding_account_id'] ?? '');
  $transfer_from_funding_account_id = (string)($billing_account['transfer_from_funding_account_id'] ?? '');
  $amount_paid = number_format(payment_amount_for_bill($billing_account, 1), 2, '.', '');
}

if (is_post_request() && isset($_POST['submit_bill_payment']) && $billing_account) {
  $cycles_paid = trim($_POST['cycles_paid'] ?? '1');
  $date_paid = trim($_POST['date_paid'] ?? date('Y-m-d'));
  $amount_paid = trim($_POST['amount_paid'] ?? '');
  $funding_account_id = trim($_POST['funding_account_id'] ?? '');
  $transfer_from_funding_account_id = trim($_POST['transfer_from_funding_account_id'] ?? '');
  $confirmation_note = trim($_POST['confirmation_note'] ?? '');

  if ($cycles_paid === '' || !ctype_digit((string)$cycles_paid) || (int)$cycles_paid < 1) {
    $errors[] = 'Cycles paid must be a whole number greater than 0.';
  }

  if ($date_paid === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_paid)) {
    $errors[] = 'Date paid must be in YYYY-MM-DD format.';
  }

  if ($amount_paid === '' || !is_numeric($amount_paid)) {
    $errors[] = 'Amount paid is required and must be numeric.';
  }

  $funding_account_id = ($funding_account_id === '') ? null : (int)$funding_account_id;
  $transfer_from_funding_account_id = ($transfer_from_funding_account_id === '') ? null : (int)$transfer_from_funding_account_id;
  $cycles_paid_int = (int)$cycles_paid;

  $amount_due = payment_amount_for_bill($billing_account, $cycles_paid_int);

  if (!$errors) {
    try {
      $pdo_db->beginTransaction();

      $stmt = $pdo_db->prepare("
        INSERT INTO bill_payments (
          billing_account_id,
          user_id,
          due_date,
          amount_due,
          amount_paid,
          date_paid,
          funding_account_id,
          transfer_from_funding_account_id,
          status,
          confirmation_note
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");

      $stmt->execute([
        $billing_account['billing_account_id'],
        $user_id,
        $billing_account['next_due_date'],
        $amount_due,
        $amount_paid,
        $date_paid,
        $funding_account_id,
        $transfer_from_funding_account_id,
        'paid',
        $confirmation_note !== '' ? $confirmation_note : null
      ]);

      if ((int)$billing_account['auto_advance_on_payment'] === 1) {
        $months_to_advance = months_to_advance_for_bill($billing_account, $cycles_paid_int);
        $new_next_due_date = add_months_to_date($billing_account['next_due_date'], $months_to_advance);

        $stmt = $pdo_db->prepare("
          UPDATE billing_accounts
          SET
            next_due_date = ?,
            paid_through_date = ?,
            last_paid_date = ?,
            reserve_amount = 0.00,
            default_funding_account_id = ?,
            transfer_from_funding_account_id = ?,
            updated_at = NOW()
          WHERE billing_account_id = ?
            AND user_id = ?
          LIMIT 1
        ");

        $stmt->execute([
          $new_next_due_date,
          $new_next_due_date,
          $date_paid,
          $funding_account_id,
          $transfer_from_funding_account_id,
          $billing_account['billing_account_id'],
          $user_id
        ]);
      } else {
        $stmt = $pdo_db->prepare("
          UPDATE billing_accounts
          SET
            last_paid_date = ?,
            default_funding_account_id = ?,
            transfer_from_funding_account_id = ?,
            updated_at = NOW()
          WHERE billing_account_id = ?
            AND user_id = ?
          LIMIT 1
        ");

        $stmt->execute([
          $date_paid,
          $funding_account_id,
          $transfer_from_funding_account_id,
          $billing_account['billing_account_id'],
          $user_id
        ]);
      }

      $pdo_db->commit();

      // $_SESSION['login-message'] = $billing_account['billing_name'] . ' marked as paid.';
      // $_SESSION['alert-class'] = 'green';

      redirect_to('billing_schedule.php');
    } catch (Exception $e) {
      if ($pdo_db->inTransaction()) {
        $pdo_db->rollBack();
      }

      $errors[] = 'There was a problem recording the payment.';
    }
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Pay Bill</h1>

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
        Due: <?php echo htmlspecialchars($billing_account['next_due_date'], ENT_QUOTES, 'UTF-8'); ?><br>
        Cadence: <?php echo htmlspecialchars($billing_account['cadence'], ENT_QUOTES, 'UTF-8'); ?><br>
        Reserve Style: <?php echo htmlspecialchars($billing_account['reserve_style'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form method="post">
        <input type="hidden" name="submit_bill_payment" value="1">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account['billing_account_id']; ?>">

        <div class="two-col">
          <div class="row">
            <label for="cycles_paid">Cycles Paid</label>
            <input
              type="number"
              id="cycles_paid"
              name="cycles_paid"
              min="1"
              value="<?php echo htmlspecialchars((string)$cycles_paid, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="row">
            <label for="date_paid">Date Paid</label>
            <input
              type="date"
              id="date_paid"
              name="date_paid"
              value="<?php echo htmlspecialchars($date_paid, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="amount_paid">Amount Paid</label>
            <input
              type="number"
              step="0.01"
              id="amount_paid"
              name="amount_paid"
              value="<?php echo htmlspecialchars((string)$amount_paid, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="row">
            <label for="funding_account_id">Paid From Account</label>
            <select id="funding_account_id" name="funding_account_id">
              <option value="">-- Select --</option>
              <?php foreach ($funding_accounts as $funding): ?>
                <option value="<?php echo (int)$funding['funding_account_id']; ?>" <?php echo ((string)$funding_account_id === (string)$funding['funding_account_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($funding['account_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="transfer_from_funding_account_id">Transferred From Account</label>
            <select id="transfer_from_funding_account_id" name="transfer_from_funding_account_id">
              <option value="">-- Select --</option>
              <?php foreach ($funding_accounts as $funding): ?>
                <option value="<?php echo (int)$funding['funding_account_id']; ?>" <?php echo ((string)$transfer_from_funding_account_id === (string)$funding['funding_account_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($funding['account_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row">
            <label for="confirmation_note">Confirmation Note</label>
            <input
              type="text"
              id="confirmation_note"
              name="confirmation_note"
              value="<?php echo htmlspecialchars($confirmation_note, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        </div>

        <button type="submit">Record Payment</button>
      </form>
    <?php endif; ?>

    <div class="inner-links">
      <a href="billing_schedule.php">Billing Schedule</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>