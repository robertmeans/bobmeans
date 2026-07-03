<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'close-billing-account';

$errors = [];
$closure_note = '';
$billing_account_id = 0;
$billing_account = null;
$archive_status = null;

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
      fa.account_name AS paid_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    WHERE ba.billing_account_id = ?
      AND ba.user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $billing_account = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$billing_account) {
    $errors[] = 'Billing account not found.';
  }
}

if ($billing_account) {
  $archive_status = billing_account_archive_status($pdo_db, $user_id, $billing_account_id);
}

if (is_post_request() && isset($_POST['archive_billing_account']) && $billing_account) {
  $closure_note = trim($_POST['closure_note'] ?? '');

  $archive_status = billing_account_archive_status($pdo_db, $user_id, $billing_account_id);

  if (!$archive_status['can_archive']) {
    $errors[] = 'This billing account cannot be archived.';
  } else {
    $archived = archive_billing_account($pdo_db, $user_id, $billing_account_id, $closure_note);

    if ($archived) {
      header('Location: billing_accounts.php?archived=1');
      exit();
    } else {
      $errors[] = 'Unable to archive billing account.';
    }
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">
    <h1>Archive Billing Account</h1>

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
      <div class="success" style="display:block;">
        <strong><?php echo htmlspecialchars((string)$billing_account['billing_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        <?php if (!empty($billing_account['paid_from_account'])): ?>
          Paid From: <?php echo htmlspecialchars((string)$billing_account['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?><br>
        <?php endif; ?>
        Payment History: <?php echo !empty($archive_status['has_payment_history']) ? 'Yes' : 'No'; ?><br>
        Notes: <?php echo !empty($archive_status['has_notes']) ? 'Yes' : 'No'; ?>
      </div>

      <?php if (!empty($archive_status['blocking_reasons'])): ?>
        <h2>Archive Status</h2>
        <ul>
          <?php foreach ($archive_status['blocking_reasons'] as $reason): ?>
            <li><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($archive_status['can_archive']): ?>
        <form method="post">
          <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account_id; ?>">
          <input type="hidden" name="archive_billing_account" value="1">

          <div class="row standalone">
            <label for="closure_note">Archive Note</label>
            <textarea
              id="closure_note"
              name="closure_note"
              rows="4"
              style="width: 100%; padding: 0.75em; font-size: 1em;"
            ><?php echo htmlspecialchars($closure_note, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <button type="submit">Archive Billing Account</button>
        </form>
      <?php endif; ?>

      <div class="inner-links">
        <a href="billing_accounts.php">Back to Billing Accounts</a> |
        <a href="edit_billing-account.php?billing_account_id=<?php echo (int)$billing_account_id; ?>">Edit Billing Account</a> |
        <a href="bill_details.php?billing_account_id=<?php echo (int)$billing_account_id; ?>">Bill Details</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require '_includes/footer.php'; ?>