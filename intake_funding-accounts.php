<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$success = '';

$account_name = '';
$account_nickname = '';
$account_type = 'other';
$is_active = 1;

if (is_post_request() && isset($_POST['create_funding_account'])) {
  $account_name = trim($_POST['account_name'] ?? '');
  $account_nickname = trim($_POST['account_nickname'] ?? '');
  $account_type = trim($_POST['account_type'] ?? 'other');
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($account_name === '') {
    $errors[] = 'Account name is required.';
  }

  if (!in_array($account_type, ['checking', 'credit_card', 'paypal', 'savings', 'other'], true)) {
    $errors[] = 'Account type is invalid.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      SELECT funding_account_id
      FROM funding_accounts
      WHERE user_id = ?
        AND LOWER(account_name) = LOWER(?)
      LIMIT 1
    ");
    $stmt->execute([$user_id, $account_name]);

    if ($stmt->fetch()) {
      $errors[] = 'That funding account already exists.';
    }
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO funding_accounts (
        user_id,
        account_name,
        account_nickname,
        account_type,
        is_active
      ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
      $user_id,
      $account_name,
      $account_nickname !== '' ? $account_nickname : null,
      $account_type,
      $is_active
    ]);

    $success = 'Funding account added.';

    $account_name = '';
    $account_nickname = '';
    $account_type = 'other';
    $is_active = 1;
  }
}
?>

<div class="intake-form">
  <div class="funding-form">

  <h1>Add Funding Account</h1>

  <?php if ($errors): ?>
    <div class="errors">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success !== ''): ?>
    <div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>


  <form method="post">
    <input type="hidden" name="create_funding_account" value="1">

    <div class="two-col">
      <div class="row">
        <label for="account_name">Account Name</label>
        <input
          type="text"
          id="account_name"
          name="account_name"
          value="<?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>"
          required
        >
      </div>

      <div class="row">
        <label for="account_nickname">Nickname</label>
        <input
          type="text"
          id="account_nickname"
          name="account_nickname"
          value="<?php echo htmlspecialchars($account_nickname, ENT_QUOTES, 'UTF-8'); ?>"
        >
      </div>
    </div>

    <div class="two-col">
      <div class="row">
        <label for="account_type">Account Type</label>
        <select id="account_type" name="account_type">
          <option value="checking" <?php echo ($account_type === 'checking') ? 'selected' : ''; ?>>Checking</option>
          <option value="credit_card" <?php echo ($account_type === 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
          <option value="paypal" <?php echo ($account_type === 'paypal') ? 'selected' : ''; ?>>PayPal</option>
          <option value="savings" <?php echo ($account_type === 'savings') ? 'selected' : ''; ?>>Savings</option>
          <option value="other" <?php echo ($account_type === 'other') ? 'selected' : ''; ?>>Other</option>
        </select>
      </div>

      <div class="row">
        <label>&nbsp;</label>
        <div class="checks">
          <label>
            <input type="checkbox" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
            Active
          </label>
        </div>
      </div>
    </div>

    <button type="submit">Add Funding Account</button>
  </form>


    <div class="inner-links">
      <a href="billing_schedule.php">Billing Schedule</a> | <a href="intake_billing-accounts.php">New Bill</a>
    </div>

  </div>
</div>



<?php require '_includes/footer.php'; ?>