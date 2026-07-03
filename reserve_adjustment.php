<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'adjustments';
$errors = [];
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

$funding_account_id = '';
$adjustment_amount = '';
$adjustment_note = '';

if (isset($_GET['funding_account_id']) && ctype_digit((string)$_GET['funding_account_id'])) {
  $funding_account_id = (string)(int)$_GET['funding_account_id'];
}

if (isset($_GET['amount']) && is_numeric($_GET['amount']) && (float)$_GET['amount'] > 0) {
  $adjustment_amount = number_format((float)$_GET['amount'], 2, '.', '');
}

if (isset($_GET['transaction_type']) && in_array($_GET['transaction_type'], ['contribution', 'deduction', 'adjustment'], true)) {
  $transaction_type = $_GET['transaction_type'];
} else {
  $transaction_type = 'contribution';
}

if (isset($_GET['bill']) && trim($_GET['bill']) !== '' && $adjustment_note === '') {
  $adjustment_note = 'Added from homepage for upcoming bill: ' . trim($_GET['bill']);
}

$funding_accounts = funding_account_selector_options($pdo_db, $user_id);

if (is_post_request() && isset($_POST['submit_reserve_adjustment'])) {
  $funding_account_id = trim($_POST['funding_account_id'] ?? '');
  $transaction_type = trim($_POST['transaction_type'] ?? 'contribution');
  $adjustment_amount = str_replace(',', '', trim($_POST['adjustment_amount'] ?? ''));
  $adjustment_note = trim($_POST['adjustment_note'] ?? '');

  if ($funding_account_id === '' || !ctype_digit((string)$funding_account_id)) {
    $errors[] = 'Please select a funding account.';
  }

  if (!in_array($transaction_type, ['contribution', 'deduction', 'adjustment'], true)) {
    $errors[] = 'Transaction type is invalid.';
  }

  if ($adjustment_amount === '' || !is_numeric($adjustment_amount) || (float)$adjustment_amount <= 0) {
    $errors[] = 'Amount must be greater than 0.';
  }

  $funding_account = null;

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      SELECT funding_account_id, account_name
      FROM funding_accounts
      WHERE funding_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$funding_account_id, $user_id]);
    $funding_account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$funding_account) {
      $errors[] = 'Funding account not found.';
    }
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO funding_account_reserve_transactions (
        funding_account_id,
        user_id,
        billing_account_id,
        transaction_type,
        amount,
        transaction_date,
        note
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
      (int)$funding_account_id,
      $user_id,
      null,
      $transaction_type,
      (float)$adjustment_amount,
      date('Y-m-d H:i:s'),
      $adjustment_note !== '' ? $adjustment_note : null
    ]);

    $saved_name = urlencode((string)$funding_account['account_name']);
    header('Location: reserve_adjustment.php?saved=1&account=' . $saved_name . '&type=' . urlencode($transaction_type));
    exit();
  }
}

$saved_account = isset($_GET['account']) ? trim($_GET['account']) : '';
$saved_type = isset($_GET['type']) ? trim($_GET['type']) : '';

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Reserve Adjustment</h1>

    <?php if ($saved): ?>
      <div class="success" style="display:block;">
        <?php if ($saved_account !== '' && $saved_type !== ''): ?>
          <?php echo htmlspecialchars(ucfirst($saved_type), ENT_QUOTES, 'UTF-8'); ?> recorded for
          <?php echo htmlspecialchars($saved_account, ENT_QUOTES, 'UTF-8'); ?>.
        <?php else: ?>
          Reserve adjustment recorded.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>


<?php if (isset($_GET['bill']) && trim($_GET['bill']) !== '' && isset($_GET['amount']) && is_numeric($_GET['amount'])): ?>
  <div class="success" style="display:block;">
    Suggested contribution for <?php echo htmlspecialchars(trim((string)$_GET['bill']), ENT_QUOTES, 'UTF-8'); ?>:
    $<?php echo number_format((float)$_GET['amount'], 2); ?>
  </div>
<?php endif; ?>




    <form method="post">
      <input type="hidden" name="submit_reserve_adjustment" value="1">

      <div class="row">
        <label for="funding_account_id">Funding Account</label>

        <?php if (count($funding_accounts) === 1): ?>
          <?php
          $only_account = $funding_accounts[0];
          $funding_account_id = (string)$only_account['funding_account_id'];
          ?>
          <div class="static-field">
            <?php echo htmlspecialchars($only_account['account_name'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <input
            type="hidden"
            id="funding_account_id"
            name="funding_account_id"
            value="<?php echo (int)$only_account['funding_account_id']; ?>"
          >
        <?php else: ?>
          <select id="funding_account_id" name="funding_account_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($funding_accounts as $account): ?>
              <option value="<?php echo (int)$account['funding_account_id']; ?>" <?php echo ((string)$funding_account_id === (string)$account['funding_account_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($account['account_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <div class="row">
        <label for="transaction_type">Transaction Type</label>
        <select id="transaction_type" name="transaction_type" required>
          <option value="contribution" <?php echo ($transaction_type === 'contribution') ? 'selected' : ''; ?>>Contribution</option>
          <option value="deduction" <?php echo ($transaction_type === 'deduction') ? 'selected' : ''; ?>>Deduction</option>

          <?php /* commenting this out for now since 'Adjustment' is really the same as 'Contribution' right now and it's really just confusing...

          <option value="adjustment" <?php echo ($transaction_type === 'adjustment') ? 'selected' : ''; ?>>Adjustment</option>

          */ ?>

        </select>
      </div>

      <div class="row">
        <label for="adjustment_amount">Amount</label>
        <input
          type="text"
          id="adjustment_amount"
          name="adjustment_amount"
          value="<?php echo htmlspecialchars($adjustment_amount, ENT_QUOTES, 'UTF-8'); ?>"
          required
        >
      </div>

      <div class="row standalone">
        <label for="adjustment_note">Note</label>
        <textarea
          id="adjustment_note"
          name="adjustment_note"
          rows="4"
          style="width: 100%; padding: 0.75em; font-size: 1em;"
        ><?php echo htmlspecialchars($adjustment_note, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <button type="submit">Record Transaction</button>
    </form>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="billing_projection.php">Projection</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>