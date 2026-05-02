<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_includes/header.php';
require '_includes/nav.php';

$pdo_db = pdo_connect();

$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$success = '';

$billing_name = '';
$vendor_name = '';
$cadence = 'monthly';
$reserve_style = 'sinking_fund';
$default_amount = '';
$annual_cost = '';
$next_due_date = '';
$paid_through_date = '';
$last_paid_date = '';
$renewal_term_months = '12';
$default_funding_account_id = '';
$transfer_from_funding_account_id = '';
$intake_note = '';
$is_autopay = 1;
$auto_advance_on_payment = 1;
$is_active = 1;
$sort_order = 0;

/* load funding accounts for dropdowns */
$stmt = $pdo_db->prepare("
  SELECT funding_account_id, account_name
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll();

/* handle form submit */
if (is_post_request() && isset($_POST['create_billing_account'])) {
  $billing_name = trim($_POST['billing_name'] ?? '');
  $vendor_name = trim($_POST['vendor_name'] ?? '');
  $cadence = trim($_POST['cadence'] ?? 'monthly');
  $reserve_style = trim($_POST['reserve_style'] ?? 'sinking_fund');
  $default_amount = trim($_POST['default_amount'] ?? '');
  $annual_cost = trim($_POST['annual_cost'] ?? '');
  $next_due_date = trim($_POST['next_due_date'] ?? '');
  $paid_through_date = trim($_POST['paid_through_date'] ?? '');
  $last_paid_date = trim($_POST['last_paid_date'] ?? '');
  $renewal_term_months = trim($_POST['renewal_term_months'] ?? '12');
  $default_funding_account_id = trim($_POST['default_funding_account_id'] ?? '');
  $transfer_from_funding_account_id = trim($_POST['transfer_from_funding_account_id'] ?? '');
  $intake_note = trim($_POST['intake_note'] ?? '');
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

  if ($default_amount === '' || !is_numeric($default_amount)) {
    $errors[] = 'Amount due is required and must be numeric.';
  }

  if ($annual_cost !== '' && !is_numeric($annual_cost)) {
    $errors[] = 'Annual cost must be numeric if provided.';
  }

  if ($next_due_date === '') {
    $errors[] = 'Next due date is required.';
  }

  if ($next_due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due_date)) {
    $errors[] = 'Next due date must be in YYYY-MM-DD format.';
  }

  if ($paid_through_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_through_date)) {
    $errors[] = 'Paid through date must be in YYYY-MM-DD format.';
  }

  if ($last_paid_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last_paid_date)) {
    $errors[] = 'Last paid date must be in YYYY-MM-DD format.';
  }

  if ($renewal_term_months === '' || !ctype_digit((string)$renewal_term_months) || (int)$renewal_term_months < 1) {
    $errors[] = 'Renewal term must be a whole number greater than 0.';
  }

  if ($sort_order !== '' && !ctype_digit((string)$sort_order)) {
    $errors[] = 'Sort order must be a whole number.';
  }

  $default_funding_account_id = ($default_funding_account_id === '') ? null : (int)$default_funding_account_id;
  $transfer_from_funding_account_id = ($transfer_from_funding_account_id === '') ? null : (int)$transfer_from_funding_account_id;
  $annual_cost = ($annual_cost === '') ? null : $annual_cost;
  $paid_through_date = ($paid_through_date === '') ? null : $paid_through_date;
  $last_paid_date = ($last_paid_date === '') ? null : $last_paid_date;
  $renewal_term_months = (int)$renewal_term_months;
  $sort_order = ($sort_order === '') ? 0 : (int)$sort_order;

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      SELECT billing_account_id
      FROM billing_accounts
      WHERE user_id = ?
        AND LOWER(billing_name) = LOWER(?)
      LIMIT 1
    ");
    $stmt->execute([$user_id, $billing_name]);

    if ($stmt->fetch()) {
      $errors[] = $billing_name . ' already exists.';
    }
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO billing_accounts (
        user_id,
        billing_name,
        vendor_name,
        intake_note,
        cadence,
        reserve_style,
        default_amount,
        annual_cost,
        next_due_date,
        paid_through_date,
        last_paid_date,
        renewal_term_months,
        default_funding_account_id,
        transfer_from_funding_account_id,
        is_autopay,
        auto_advance_on_payment,
        is_active,
        sort_order
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
      $user_id,
      $billing_name,
      $vendor_name !== '' ? $vendor_name : null,
      $intake_note !== '' ? $intake_note : null,
      $cadence,
      $reserve_style,
      $default_amount,
      $annual_cost,
      $next_due_date,
      $paid_through_date,
      $last_paid_date,
      $renewal_term_months,
      $default_funding_account_id,
      $transfer_from_funding_account_id,
      $is_autopay,
      $auto_advance_on_payment,
      $is_active,
      $sort_order
    ]);

    $success = $billing_name . ' added.';

    /* clear form */
    $billing_name = '';
    $vendor_name = '';
    $cadence = 'monthly';
    $reserve_style = 'sinking_fund';
    $default_amount = '';
    $annual_cost = '';
    $next_due_date = '';
    $paid_through_date = '';
    $last_paid_date = '';
    $renewal_term_months = '12';
    $default_funding_account_id = '';
    $transfer_from_funding_account_id = '';
    $intake_note = '';
    $is_autopay = 1;
    $auto_advance_on_payment = 1;
    $is_active = 1;
    $sort_order = 0;
  }
}
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Add Billing Account</h1>

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
      <input type="hidden" name="create_billing_account" value="1">

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

        <div class="row">
          <label for="annual_cost">Annual Cost</label>
          <input type="number" step="0.01" id="annual_cost" name="annual_cost" value="<?php echo htmlspecialchars((string)$annual_cost, ENT_QUOTES, 'UTF-8'); ?>">
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

      <div class="two-col">
        <div class="row">
          <label for="next_due_date">Next Due Date</label>
          <input type="date" id="next_due_date" name="next_due_date" value="<?php echo htmlspecialchars($next_due_date, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="row">
          <label for="paid_through_date">Paid Through Date</label>
          <input type="date" id="paid_through_date" name="paid_through_date" value="<?php echo htmlspecialchars((string)$paid_through_date, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>

      <div class="two-col">
        <div class="row">
          <label for="last_paid_date">Last Paid Date</label>
          <input type="date" id="last_paid_date" name="last_paid_date" value="<?php echo htmlspecialchars((string)$last_paid_date, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="row">
          <label for="renewal_term_months">Renewal Term (Months)</label>
          <input type="number" id="renewal_term_months" name="renewal_term_months" min="1" value="<?php echo htmlspecialchars((string)$renewal_term_months, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>

      <div class="row standalone">
        <label for="intake_note">Billing Memo</label>
        <input
          type="text"
          id="intake_note"
          class="memo"
          name="intake_note"
          value="<?php echo htmlspecialchars($intake_note, ENT_QUOTES, 'UTF-8'); ?>"
        >
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

      <button type="submit">Add Billing Account</button>
    </form>

    <div class="inner-links">
      <a href="billing_schedule-gpt.php">Billing Schedule</a> | <a href="intake_funding-accounts.php">New Funding Account</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>