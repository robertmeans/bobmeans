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
$default_amount = '';
$annual_cost = '';
$next_due_date = '';
$paid_through_date = '';
$default_funding_account_id = '';
$transfer_from_funding_account_id = '';
$intake_note = '';
$is_autopay = 1;
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
    $default_amount = trim($_POST['default_amount'] ?? '');
    $annual_cost = trim($_POST['annual_cost'] ?? '');
    $next_due_date = trim($_POST['next_due_date'] ?? '');
    $paid_through_date = trim($_POST['paid_through_date'] ?? '');
    $default_funding_account_id = trim($_POST['default_funding_account_id'] ?? '');
    $transfer_from_funding_account_id = trim($_POST['transfer_from_funding_account_id'] ?? '');
    $intake_note = $_POST['intake_note'] ?? '';
    $is_autopay = isset($_POST['is_autopay']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = trim($_POST['sort_order'] ?? '0');

    if ($billing_name === '') {
        $errors[] = 'Billing account name is required.';
    }

    if (!in_array($cadence, ['monthly', 'annual', 'custom'], true)) {
        $errors[] = 'Cadence is invalid.';
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

    if ($paid_through_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_through_date)) {
        $errors[] = 'Paid through date must be in YYYY-MM-DD format.';
    }

    if ($next_due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_due_date)) {
        $errors[] = 'Next due date must be in YYYY-MM-DD format.';
    }

    if ($sort_order !== '' && !ctype_digit((string)$sort_order)) {
        $errors[] = 'Sort order must be a whole number.';
    }

    $default_funding_account_id = ($default_funding_account_id === '') ? null : (int)$default_funding_account_id;
    $transfer_from_funding_account_id = ($transfer_from_funding_account_id === '') ? null : (int)$transfer_from_funding_account_id;
    $annual_cost = ($annual_cost === '') ? null : $annual_cost;
    $paid_through_date = ($paid_through_date === '') ? null : $paid_through_date;
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
                default_amount,
                annual_cost,
                next_due_date,
                paid_through_date,
                default_funding_account_id,
                transfer_from_funding_account_id,
                is_autopay,
                is_active,
                sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $billing_name,
            $vendor_name !== '' ? $vendor_name : null,
            $intake_note,
            $cadence,
            $default_amount,
            $annual_cost,
            $next_due_date,
            $paid_through_date,
            $default_funding_account_id,
            $transfer_from_funding_account_id,
            $is_autopay,
            $is_active,
            $sort_order
        ]);

        $success = $billing_name . ' added.';

        /* clear form */
        $billing_name = '';
        $vendor_name = '';
        $cadence = 'monthly';
        $default_amount = '';
        $annual_cost = '';
        $next_due_date = '';
        $paid_through_date = '';
        $default_funding_account_id = '';
        $transfer_from_funding_account_id = '';
        $intake_note = '';
        $is_autopay = 1;
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

        <?php /*
        <div class="row">
          <label for="vendor_name">Vendor Name</label>
          <input type="text" id="vendor_name" name="vendor_name" value="<?php echo htmlspecialchars($vendor_name, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        */ ?>

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
          <label for="default_amount">Amount Due</label>
          <input type="number" step="0.01" id="default_amount" name="default_amount" value="<?php echo htmlspecialchars($default_amount, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
      </div>

      <?php /*
      <div class="two-col">
        <div class="row">
          <label for="annual_cost">Annual Cost</label>
          <input type="number" step="0.01" id="annual_cost" name="annual_cost" value="<?php echo htmlspecialchars($annual_cost, ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="row">
          <label for="sort_order">Sort Order</label>
          <input type="number" id="sort_order" name="sort_order" value="<?php echo htmlspecialchars((string)$sort_order, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
      */ ?>

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

        <?php /*
        <div class="row">
          <label for="paid_through_date">Paid Through Date</label>
          <input type="date" id="paid_through_date" name="paid_through_date" value="<?php echo htmlspecialchars($paid_through_date, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        */ ?>
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
          <input type="checkbox" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
          Active
        </label>
      </div>

      <button type="submit">Add Billing Account</button>
    </form>


    <div class="inner-links">
      <a href="billing_schedule.php">Billing Schedule</a> | <a href="intake_funding-accounts.php">New Funding Account</a>
    </div>

  </div>
</div>



<?php require '_includes/footer.php'; ?>