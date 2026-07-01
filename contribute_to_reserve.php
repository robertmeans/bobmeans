<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

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

  $funding_account_id = isset($billing_account['default_funding_account_id'])
    ? (int)$billing_account['default_funding_account_id']
    : 0;

  if ($funding_account_id < 1) {
    $errors[] = 'This bill does not have a funding account assigned.';
  }

  if (!$errors) {
    $contribution = round((float)$contribution_amount, 2);

    $note_parts = [];
    $note_parts[] = 'Contribution made from bill page for ' . $billing_account['billing_name'] . '.';

    if ($contribution_note !== '') {
      $note_parts[] = $contribution_note;
    }

    $full_note = implode(' ', $note_parts);

    $stmt = $pdo_db->prepare("
      INSERT INTO funding_account_reserve_transactions (
        funding_account_id,
        user_id,
        transaction_type,
        amount,
        transaction_date,
        note
      ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
      $funding_account_id,
      $user_id,
      'contribution',
      $contribution,
      date('Y-m-d H:i:s'),
      $full_note
    ]);

    redirect_to('bill_details.php?billing_account_id=' . (int)$billing_account['billing_account_id']);
  }
}

require '_includes/header.php';
require '_includes/nav.php';
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
        Default Amount: $<?php echo number_format((float)$billing_account['default_amount'], 2); ?><br>
        Current Reserve: $<?php echo number_format((float)$billing_account['reserve_balance'], 2); ?><br>
        Next Due Date: <?php 
            $original = $billing_account['next_due_date'];
            $newDate = date("m.d.y", strtotime($original));
            echo $newDate; ?>
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
            &nbsp;
          </div>
        </div>

        <div class="row standalone">
          <label for="contribution_note">Note</label>
          <textarea
            rows="5" 
            cols="40" 
            wrap="soft" 
            id="contribution_note" 
            class="memo" 
            name="contribution_note"
            value="<?php echo htmlspecialchars($contribution_note, ENT_QUOTES, 'UTF-8'); ?>"
          ></textarea>

        </div>

        <button type="submit">Add to Reserve</button>
      </form>
    <?php endif; ?>

    <div class="inner-links">
      <a href="billing_schedule.php">Schedule</a> | 
      <a href="billing_projection.php">Projection</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>