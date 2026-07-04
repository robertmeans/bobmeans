<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$source = trim($_GET['source'] ?? $_POST['source'] ?? '');
$id = trim($_GET['id'] ?? $_POST['id'] ?? '');
$note = '';
$return_url = trim($_GET['return_url'] ?? $_POST['return_url'] ?? '');
$record_label = '';

if (!in_array($source, ['activity', 'payment', 'funding'], true)) {
  $errors[] = 'Invalid note source.';
}

if ($id === '' || !ctype_digit((string)$id)) {
  $errors[] = 'Invalid note id.';
}

if ($return_url === '') {
  $return_url = 'homepage.php';
}

if (!$errors) {
  if ($source === 'activity') {
    $stmt = $pdo_db->prepare("
      SELECT
        bill_activity_log_id,
        billing_account_id,
        activity_type,
        field_name,
        note
      FROM bill_activity_log
      WHERE bill_activity_log_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $errors[] = 'Activity record not found.';
    } else {
      $note = (string)($row['note'] ?? '');
      $record_label = 'Bill Activity: ' . (string)$row['activity_type'];
      if (!empty($row['field_name'])) {
        $record_label .= ' (' . (string)$row['field_name'] . ')';
      }
      $return_url = 'bill_details.php?billing_account_id=' . (int)$row['billing_account_id'];
    }
  }

  if ($source === 'payment') {
    $stmt = $pdo_db->prepare("
      SELECT
        bill_payment_id,
        billing_account_id,
        amount_paid,
        date_paid,
        confirmation_note
      FROM bill_payments
      WHERE bill_payment_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $errors[] = 'Payment record not found.';
    } else {
      $note = (string)($row['confirmation_note'] ?? '');
      $record_label = 'Payment: $' . number_format((float)$row['amount_paid'], 2) . ' on ' . date('m.d.y', strtotime((string)$row['date_paid']));
      $return_url = 'bill_details.php?billing_account_id=' . (int)$row['billing_account_id'];
    }
  }

  if ($source === 'funding') {
    $stmt = $pdo_db->prepare("
      SELECT
        funding_account_reserve_transaction_id,
        funding_account_id,
        transaction_type,
        amount,
        transaction_date,
        note
      FROM funding_account_reserve_transactions
      WHERE funding_account_reserve_transaction_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $errors[] = 'Funding account transaction not found.';
    } else {
      $note = (string)($row['note'] ?? '');
      $record_label = ucfirst((string)$row['transaction_type']) . ': $' . number_format((float)$row['amount'], 2) . ' on ' . date('m.d.y', strtotime((string)$row['transaction_date']));
      $return_url = 'funding_account_ledger.php?funding_account_id=' . (int)$row['funding_account_id'];
    }
  }
}

if (is_post_request() && !$errors) {
  $note = trim($_POST['note'] ?? '');

  if ($source === 'activity') {
    $stmt = $pdo_db->prepare("
      UPDATE bill_activity_log
      SET note = ?
      WHERE bill_activity_log_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $note !== '' ? $note : null,
      (int)$id,
      $user_id
    ]);
  }

  if ($source === 'payment') {
    $stmt = $pdo_db->prepare("
      UPDATE bill_payments
      SET confirmation_note = ?
      WHERE bill_payment_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $note !== '' ? $note : null,
      (int)$id,
      $user_id
    ]);
  }

  if ($source === 'funding') {
    $stmt = $pdo_db->prepare("
      UPDATE funding_account_reserve_transactions
      SET note = ?
      WHERE funding_account_reserve_transaction_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([
      $note !== '' ? $note : null,
      (int)$id,
      $user_id
    ]);
  }

  header('Location: ' . $return_url);
  exit();
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Edit Note</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$errors): ?>
      <div class="success" style="display:block;">
        <?php echo htmlspecialchars($record_label, ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <form method="post">
        <input type="hidden" name="source" value="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="row standalone">
          <label for="note">Note</label>
          <textarea
            id="note"
            name="note"
            rows="6"
            style="width: 100%; padding: 0.75em; font-size: 1em;"
          ><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Note</button>
      </form>

      <div class="inner-links">
        <a href="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">Back</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>