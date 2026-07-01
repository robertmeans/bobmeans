<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$success = '';

$billing_account_id = 0;
$billing_account = null;

/* get billing account id */
if (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
} elseif (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT *
    FROM billing_accounts
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $billing_account = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$billing_account) {
    $errors[] = 'Billing account not found.';
  }
}

/* load funding accounts */
$stmt = $pdo_db->prepare("
  SELECT funding_account_id, account_name
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* defaults from current row */
$billing_name = $billing_account['billing_name'] ?? '';
$vendor_name = $billing_account['vendor_name'] ?? '';
$intake_note = $billing_account['intake_note'] ?? '';
$cadence = $billing_account['cadence'] ?? 'monthly';
$reserve_style = $billing_account['reserve_style'] ?? 'sinking_fund';
$default_amount = isset($billing_account['default_amount']) ? (string)$billing_account['default_amount'] : '';
// $reserve_balance = isset($billing_account['reserve_balance']) ? (string)$billing_account['reserve_balance'] : '0.00';
$next_due_date = $billing_account['next_due_date'] ?? '';
$actual_due_date = $billing_account['actual_due_date'] ?? '';
$renewal_term_months = isset($billing_account['renewal_term_months']) ? (string)$billing_account['renewal_term_months'] : '1';
$due_day_of_month = isset($billing_account['due_day_of_month']) ? (string)$billing_account['due_day_of_month'] : '';
$due_month_of_year = isset($billing_account['due_month_of_year']) ? (string)$billing_account['due_month_of_year'] : '';
$default_funding_account_id = isset($billing_account['default_funding_account_id']) ? (string)$billing_account['default_funding_account_id'] : '';
$transfer_from_funding_account_id = isset($billing_account['transfer_from_funding_account_id']) ? (string)$billing_account['transfer_from_funding_account_id'] : '';
$is_autopay = isset($billing_account['is_autopay']) ? (int)$billing_account['is_autopay'] : 1;
$auto_advance_on_payment = isset($billing_account['auto_advance_on_payment']) ? (int)$billing_account['auto_advance_on_payment'] : 1;
$is_active = isset($billing_account['is_active']) ? (int)$billing_account['is_active'] : 1;
$sort_order = isset($billing_account['sort_order']) ? (string)$billing_account['sort_order'] : '0';

/* handle update */
if (is_post_request() && isset($_POST['update_billing_account']) && $billing_account) {
  $billing_name = trim($_POST['billing_name'] ?? '');
  $vendor_name = trim($_POST['vendor_name'] ?? '');
  $intake_note = trim($_POST['intake_note'] ?? '');
  $cadence = trim($_POST['cadence'] ?? 'monthly');
  $reserve_style = trim($_POST['reserve_style'] ?? 'sinking_fund');
  $default_amount = trim($_POST['default_amount'] ?? '');
  // $reserve_balance = trim($_POST['reserve_balance'] ?? '0.00');
  $next_due_date = trim($_POST['next_due_date'] ?? '');
  $actual_due_date = trim($_POST['actual_due_date'] ?? '');
  $renewal_term_months = trim($_POST['renewal_term_months'] ?? '1');
  $due_day_of_month = trim($_POST['due_day_of_month'] ?? '');
  $due_month_of_year = trim($_POST['due_month_of_year'] ?? '');
  $default_funding_account_id = trim($_POST['default_funding_account_id'] ?? '');
  $transfer_from_funding_account_id = trim($_POST['transfer_from_funding_account_id'] ?? '');
  $is_autopay = isset($_POST['is_autopay']) ? 1 : 0;
  $auto_advance_on_payment = isset($_POST['auto_advance_on_payment']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $sort_order = trim($_POST['sort_order'] ?? '0');

  if ($billing_name === '') {
    $errors[] = 'Billing account name is required.';
  }

  if (!in_array($cadence, ['monthly', 'annual', 'custom'], true)) {
    $errors[] = 'Cadence is invalid.';
  }

  if (!in_array($reserve_style, ['sinking_fund', 'prepaid'], true)) {
    $errors[] = 'Reserve style is invalid.';
  }

  if ($default_amount === '' || !is_numeric($default_amount) || (float)$default_amount < 0) {
    $errors[] = 'Amount due must be numeric.';
  }

  // if ($reserve_balance === '' || !is_numeric($reserve_balance) || (float)$reserve_balance < 0) {
  //   $errors[] = 'Reserve balance must be numeric.';
  // }

  if ($next_due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due_date)) {
    $errors[] = 'Next due date must be in YYYY-MM-DD format.';
  }

  if ($actual_due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actual_due_date)) {
    $errors[] = 'Actual due date must be in YYYY-MM-DD format.';
  }

  if ($renewal_term_months === '' || !ctype_digit((string)$renewal_term_months) || (int)$renewal_term_months < 1) {
    $errors[] = 'Renewal term must be a whole number greater than 0.';
  }

  if ($due_day_of_month === '' || !ctype_digit((string)$due_day_of_month) || (int)$due_day_of_month < 1 || (int)$due_day_of_month > 31) {
    $errors[] = 'Due day of month must be between 1 and 31.';
  }

  if ($cadence === 'annual') {
    if ($due_month_of_year === '' || !ctype_digit((string)$due_month_of_year) || (int)$due_month_of_year < 1 || (int)$due_month_of_year > 12) {
      $errors[] = 'Due month of year must be between 1 and 12 for annual bills.';
    }
  } else {
    $due_month_of_year = null;
  }

  if ($sort_order !== '' && !ctype_digit((string)$sort_order)) {
    $errors[] = 'Sort order must be a whole number.';
  }

  $default_funding_account_id = ($default_funding_account_id === '') ? null : (int)$default_funding_account_id;
  $transfer_from_funding_account_id = ($transfer_from_funding_account_id === '') ? null : (int)$transfer_from_funding_account_id;
  $renewal_term_months = (int)$renewal_term_months;
  $due_day_of_month = (int)$due_day_of_month;
  $due_month_of_year = ($due_month_of_year === null || $due_month_of_year === '') ? null : (int)$due_month_of_year;
  $sort_order = ($sort_order === '') ? 0 : (int)$sort_order;

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      SELECT billing_account_id
      FROM billing_accounts
      WHERE user_id = ?
        AND LOWER(billing_name) = LOWER(?)
        AND billing_account_id != ?
      LIMIT 1
    ");
    $stmt->execute([$user_id, $billing_name, $billing_account_id]);

    if ($stmt->fetch()) {
      $errors[] = $billing_name . ' already exists.';
    }
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
    UPDATE billing_accounts
    SET
      billing_name = ?,
      vendor_name = ?,
      intake_note = ?,
      cadence = ?,
      reserve_style = ?,
      default_amount = ?,
      -- reserve_balance = ?,
      next_due_date = ?,
      actual_due_date = ?,
      renewal_term_months = ?,
      due_day_of_month = ?,
      due_month_of_year = ?,
      default_funding_account_id = ?,
      transfer_from_funding_account_id = ?,
      is_autopay = ?,
      auto_advance_on_payment = ?,
      is_active = ?,
      sort_order = ?,
      updated_at = NOW()
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
    ");

    $stmt->execute([
      $billing_name,
      $vendor_name !== '' ? $vendor_name : null,
      $intake_note !== '' ? $intake_note : null,
      $cadence,
      $reserve_style,
      $default_amount,
      // $reserve_balance,
      $next_due_date,
      $actual_due_date,
      $renewal_term_months,
      $due_day_of_month,
      $due_month_of_year,
      $default_funding_account_id,
      $transfer_from_funding_account_id,
      $is_autopay,
      $auto_advance_on_payment,
      $is_active,
      $sort_order,
      $billing_account_id,
      $user_id
    ]);

    header('Location: billing_projection.php');
    exit();
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Edit Billing Account</h1>

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
      <form method="post">
        <input type="hidden" name="update_billing_account" value="1">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account_id; ?>">

        <div class="two-col">
          <div class="row">
            <label for="billing_name">Billing Account Name</label>
            <input type="text" id="billing_name" name="billing_name" value="<?php echo htmlspecialchars($billing_name, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="row">
            <label for="vendor_name">Vendor Name</label>
            <input type="text" id="vendor_name" name="vendor_name" value="<?php echo htmlspecialchars($vendor_name, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="cadence">Cadence</label>
            <select id="cadence" name="cadence">
              <option value="monthly" <?php echo ($cadence === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
              <option value="annual" <?php echo ($cadence === 'annual') ? 'selected' : ''; ?>>Annual</option>
              <option value="custom" <?php echo ($cadence === 'custom') ? 'selected' : ''; ?>>Custom</option>
            </select>
          </div>

          <div class="row">
            <label for="reserve_style">Reserve Style</label>
            <select id="reserve_style" name="reserve_style">
              <option value="sinking_fund" <?php echo ($reserve_style === 'sinking_fund') ? 'selected' : ''; ?>>Sinking Fund</option>
              <option value="prepaid" <?php echo ($reserve_style === 'prepaid') ? 'selected' : ''; ?>>Prepaid</option>
            </select>
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="default_amount">Amount Due</label>
            <input type="number" step="0.01" id="default_amount" name="default_amount" value="<?php echo htmlspecialchars($default_amount, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>


<?php /* 
          <div class="row">
            <label for="reserve_balance">In Reserves</label>
            <input type="number" step="0.01" id="reserve_balance" name="reserve_balance" value="<?php echo htmlspecialchars($reserve_balance, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
*/ ?>

        </div>

        <div class="two-col">
          <div class="row">
            <label for="next_due_date">Next Due Date</label>
            <input
              type="date"
              id="next_due_date"
              name="next_due_date"
              value="<?php echo htmlspecialchars($next_due_date, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="row">
            <label for="actual_due_date">Actual Due Date</label>
            <input
              type="date"
              id="actual_due_date"
              name="actual_due_date"
              value="<?php echo htmlspecialchars($actual_due_date, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="renewal_term_months">Renewal Term (Months)</label>
            <input type="number" id="renewal_term_months" name="renewal_term_months" min="1" value="<?php echo htmlspecialchars((string)$renewal_term_months, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="row">
            &nbsp;
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="due_day_of_month">Due Day of Month</label>
            <input type="number" id="due_day_of_month" name="due_day_of_month" min="1" max="31" value="<?php echo htmlspecialchars((string)$due_day_of_month, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="row">
            <label for="due_month_of_year">Due Month of Year</label>
            <input type="number" id="due_month_of_year" name="due_month_of_year" min="1" max="12" value="<?php echo htmlspecialchars((string)$due_month_of_year, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>

        <div class="two-col">
          <div class="row">
            <label for="default_funding_account_id">Paid From Account</label>
            <select id="default_funding_account_id" name="default_funding_account_id">
              <option value="">-- Select --</option>
              <?php foreach ($funding_accounts as $funding): ?>
                <option value="<?php echo (int)$funding['funding_account_id']; ?>" <?php echo ((string)$default_funding_account_id === (string)$funding['funding_account_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($funding['account_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

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
        </div>

        <div class="row standalone">
          <label for="intake_note">Billing Memo</label>
          <input type="text" id="intake_note" class="memo" name="intake_note" value="<?php echo htmlspecialchars($intake_note, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="checks">
          <label>
            <input type="checkbox" name="is_autopay" value="1" <?php echo $is_autopay ? 'checked' : ''; ?>>
            Auto Pay
          </label>

          <label>
            <input type="checkbox" name="auto_advance_on_payment" value="1" <?php echo $auto_advance_on_payment ? 'checked' : ''; ?>>
            Auto Advance on Payment
          </label>

          <label>
            <input type="checkbox" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
            Active
          </label>
        </div>

        <button type="submit">Save Changes</button>
      </form>
    <?php endif; ?>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="reserve_adjustment.php">Reserve Adjustment</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>