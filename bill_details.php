<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$billing_account_id = 0;
$bill = null;
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

$next_attention = null;

if (!empty($bill['default_funding_account_id'])) {
  $next_attention = next_attention_date_for_bill(
    $pdo_db,
    $user_id,
    (int)$bill['billing_account_id'],
    (int)$bill['default_funding_account_id'],
    24
  );
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

    <?php if ($bill):
    $bill_acct_name = htmlspecialchars($bill['billing_name'], ENT_QUOTES, 'UTF-8'); ?>

      <div class="success" style="display:block;">

        <strong><?= $bill_acct_name; ?></strong> <a href="edit_billing-account.php?billing_account_id=<?php echo $bill['billing_account_id']; ?>"><i class="fas fa-edit"></i></a><br>

        <?php if (!empty($bill['vendor_name'])): ?>
          Vendor: <?php echo htmlspecialchars($bill['vendor_name'], ENT_QUOTES, 'UTF-8'); ?><br>
        <?php endif; ?>
        Base Amount: $<?php echo number_format((float)$bill['default_amount'], 2); ?><br>


<?php /*
        Next Due Date: 
        <?php if (date('y') === date('y', strtotime($bill['next_due_date']))) {
          echo date("F j\<\s\u\p\>S\<\/\s\u\p\>", strtotime($bill['next_due_date']));?><br>
        <?php } else {
          echo date("F j\<\s\u\p\>S\<\/\s\u\p\>, Y", strtotime($bill['next_due_date']));?><br>
        <?php } ?>
*/ ?>

        <?php if (!empty($bill['actual_due_date'])): ?>
          <strong>Next Scheduled Draft:</strong>
          <?php echo date('m.d.y', strtotime($bill['actual_due_date'])); ?><br>
        <?php endif; ?>

        <strong>Next Attention Date:</strong>
        <?php if ($next_attention && !empty($next_attention['due_date'])): ?>
          <?php echo date('m.d.y', strtotime($next_attention['due_date'])); ?>
          <?php if (!empty($next_attention['remaining_due'])): ?>
            - needs $<?php echo number_format((float)$next_attention['remaining_due'], 2); ?><br>
          <?php endif; ?>
        <?php else: ?>
          Fully covered in current projection window<br>
        <?php endif; ?>


        Cadence: <?php echo htmlspecialchars((string)$bill['cadence'], ENT_QUOTES, 'UTF-8'); ?><br>

        Paid From:
        <?php if (!empty($bill['default_funding_account_id']) && !empty($bill['paid_from_account'])): ?>
          <a href="funding_account_ledger.php?funding_account_id=<?php echo (int)$bill['default_funding_account_id']; ?>">
            <?php echo htmlspecialchars((string)$bill['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
        <?php else: ?>
          <?php echo htmlspecialchars((string)$bill['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>

      </div>

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
                <td>
                  <?php /* was...
                  <?php echo htmlspecialchars((string)$payment['due_date'], ENT_QUOTES, 'UTF-8'); ?>
                  */ ?>
                  <?php
                    $dueDate = $payment['due_date'];
                    if ($dueDate) {
                      $dateObj = new DateTime($dueDate);
                      echo htmlspecialchars($dateObj->format('m.d.y'), ENT_QUOTES, 'UTF-8');
                    }
                  ?>
                </td>

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
        <a href="index.php">Dashboard</a> |
        <a href="billing_projection.php">Projection</a> |
        <a href="reserve_adjustment.php">Reserve Adjustment</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require '_includes/footer.php'; ?>