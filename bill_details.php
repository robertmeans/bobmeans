<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$billing_account_id = 0;
$bill = null;
$reserve_transactions = [];
$payments = [];
$notes = [];
$new_note_body = '';

if (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
} elseif (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT
      ba.*,
      fa.account_name AS paid_from_account,
      tfa.account_name AS transferred_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    LEFT JOIN funding_accounts tfa
      ON ba.transfer_from_funding_account_id = tfa.funding_account_id
    WHERE ba.billing_account_id = ?
      AND ba.user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $bill = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bill) {
    $errors[] = 'Billing account not found.';
  }
}




if (is_post_request() && isset($_POST['add_bill_note']) && $bill) {
  $new_note_body = trim($_POST['note_body'] ?? '');

  if ($new_note_body === '') {
    $errors[] = 'Note cannot be empty.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO bill_notes (
        billing_account_id,
        user_id,
        note_body,
        note_date
      ) VALUES (?, ?, ?, NOW())
    ");

    $stmt->execute([
      $billing_account_id,
      $user_id,
      $new_note_body
    ]);

    header('Location: bill_details.php?billing_account_id=' . $billing_account_id);
    exit();
  }
}




if ($bill) {
  $stmt = $pdo_db->prepare("
    SELECT
      bill_reserve_transaction_id,
      transaction_type,
      amount,
      transaction_date,
      note,
      created_at
    FROM bill_reserve_transactions
    WHERE billing_account_id = ?
      AND user_id = ?
    ORDER BY transaction_date DESC, bill_reserve_transaction_id DESC
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $reserve_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo_db->prepare("
    SELECT
      bill_payment_id,
      due_date,
      amount_due,
      amount_paid,
      date_paid,
      status,
      confirmation_note,
      created_at
    FROM bill_payments
    WHERE billing_account_id = ?
      AND user_id = ?
    ORDER BY date_paid DESC, bill_payment_id DESC
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo_db->prepare("
    SELECT
      bill_note_id,
      note_body,
      note_date
    FROM bill_notes
    WHERE billing_account_id = ?
      AND user_id = ?
    ORDER BY note_date DESC, bill_note_id DESC
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule">

    <h1>Bill Details</h1>

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
        <strong><?php echo htmlspecialchars($bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        <?php if (!empty($bill['vendor_name'])): ?>
          Vendor: <?php echo htmlspecialchars($bill['vendor_name'], ENT_QUOTES, 'UTF-8'); ?><br>
        <?php endif; ?>
        Base Amount: $<?php echo number_format((float)$bill['default_amount'], 2); ?><br>
        In Reserves: $<?php echo number_format((float)$bill['reserve_balance'], 2); ?><br>
        Next Due Date: 

        <?php echo date("m.d.y", strtotime($bill['next_due_date']));?><br>

        <?php // echo htmlspecialchars((string)$bill['next_due_date'], ENT_QUOTES, 'UTF-8'); ?>

        Cadence: <?php echo htmlspecialchars((string)$bill['cadence'], ENT_QUOTES, 'UTF-8'); ?><br>
        Paid From: <?php echo htmlspecialchars((string)$bill['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <h2>Reserve History</h2>

      <?php if ($reserve_transactions): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reserve_transactions as $tx): ?>
              <tr>

                <td>
                  <?php
                  echo !empty($tx['transaction_date'])
                    ? date("m.d.y \\a\\t H:i", strtotime($tx['transaction_date']))
                    : '';
                  ?>
                </td>

                <td><?php echo htmlspecialchars((string)$tx['transaction_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>$<?php echo number_format((float)$tx['amount'], 2); ?></td>
                <td><?php echo nl2br(htmlspecialchars((string)$tx['note'], ENT_QUOTES, 'UTF-8')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No reserve transactions yet.</p>
      <?php endif; ?>

      <h2>Payment History</h2>

      <?php if ($payments): ?>
        <table>
          <thead>
            <tr>
              <th>Due Date</th>
              <th>Date Paid</th>
              <th>Status</th>
              <th>Amount Due</th>
              <th>Amount Paid</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <tr>
                <td><?php echo htmlspecialchars((string)$payment['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>

                <td>
                  <?php
                  echo !empty($payment['date_paid'])
                    ? date("m.d.y", strtotime($payment['date_paid']))
                    : '';
                  ?>
                </td>

                <td><?php echo htmlspecialchars((string)$payment['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>$<?php echo number_format((float)$payment['amount_due'], 2); ?></td>
                <td>$<?php echo number_format((float)$payment['amount_paid'], 2); ?></td>
                <td><?php echo nl2br(htmlspecialchars((string)$payment['confirmation_note'], ENT_QUOTES, 'UTF-8')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No payments recorded yet.</p>
      <?php endif; ?>

      <h2>General Notes</h2>

      <form method="post" style="margin-bottom: 1.5em;">
        <input type="hidden" name="add_bill_note" value="1">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$bill['billing_account_id']; ?>">

        <div class="row standalone">
          <label for="note_body">Add Note</label>
          <textarea
            id="note_body"
            name="note_body"
            rows="4"
            style="width: 100%; padding: 0.75em; font-size: 1em;"
          ><?php echo htmlspecialchars($new_note_body, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Note</button>
      </form>

      <?php if ($notes): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notes as $note): ?>
              <tr>

                <td>
                  <?php
                  echo !empty($note['note_date'])
                    ? date("m.d.y \\a\\t H:i", strtotime($note['note_date']))
                    : '';
                  ?>
                </td>

                <td><?php echo nl2br(htmlspecialchars((string)$note['note_body'], ENT_QUOTES, 'UTF-8')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No general notes yet.</p>
      <?php endif; ?>

      <div class="inner-links">
        <a href="billing_schedule.php">Billing Schedule</a> |
        <a href="billing_projection.php">Billing Projection</a> |
        <a href="billing_accounts.php">All Billing Accounts</a> |
        <a href="edit_billing-account.php?billing_account_id=<?php echo (int)$bill['billing_account_id']; ?>">Edit This Bill</a> |
        <a href="contribute_to_reserve.php?billing_account_id=<?php echo (int)$bill['billing_account_id']; ?>">Contribute</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require '_includes/footer.php'; ?>