<?php
require_once 'config/initialize.php';
verify_loggedin();

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$funding_account_id = 0;
$funding_account = null;

/* get funding account id */
if (isset($_GET['funding_account_id']) && ctype_digit((string)$_GET['funding_account_id'])) {
  $funding_account_id = (int)$_GET['funding_account_id'];
} elseif (isset($_POST['funding_account_id']) && ctype_digit((string)$_POST['funding_account_id'])) {
  $funding_account_id = (int)$_POST['funding_account_id'];
}

if ($funding_account_id < 1) {
  $errors[] = 'Funding account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT *
    FROM funding_accounts
    WHERE funding_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$funding_account_id, $user_id]);
  $funding_account = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$funding_account) {
    $errors[] = 'Funding account not found.';
  }
}

/* defaults */
$account_name = $funding_account['account_name'] ?? '';
$account_nickname = $funding_account['account_nickname'] ?? '';
$account_type = $funding_account['account_type'] ?? 'other';
$is_active = isset($funding_account['is_active']) ? (int)$funding_account['is_active'] : 1;

/* update */
if (is_post_request() && isset($_POST['update_funding_account']) && $funding_account) {
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
        AND funding_account_id != ?
      LIMIT 1
    ");
    $stmt->execute([$user_id, $account_name, $funding_account_id]);

    if ($stmt->fetch()) {
      $errors[] = 'That funding account already exists.';
    }
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      UPDATE funding_accounts
      SET
        account_name = ?,
        account_nickname = ?,
        account_type = ?,
        is_active = ?,
        updated_at = NOW()
      WHERE funding_account_id = ?
        AND user_id = ?
      LIMIT 1
    ");

    $stmt->execute([
      $account_name,
      $account_nickname !== '' ? $account_nickname : null,
      $account_type,
      $is_active,
      $funding_account_id,
      $user_id
    ]);

    header('Location: funding_accounts.php');
    exit();
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Edit Funding Account</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($funding_account): ?>
      <form method="post">
        <input type="hidden" name="update_funding_account" value="1">
        <input type="hidden" name="funding_account_id" value="<?php echo (int)$funding_account_id; ?>">

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
              value="<?php echo htmlspecialchars((string)$account_nickname, ENT_QUOTES, 'UTF-8'); ?>"
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

        <button type="submit">Save Changes</button>
      </form>
    <?php endif; ?>

    <div class="inner-links">
      <a href="billing_schedule.php">Schedule</a> | 
      <a href="billing_projection.php">Projection</a> | 
      <a href="funding_accounts.php">Funding Accounts</a> |
      <a href="intake_funding-accounts.php">New Funding</a> |
      <a href="billing_accounts.php">Billing Accounts</a>
      
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>