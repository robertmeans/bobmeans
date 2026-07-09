<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'intakeBilling';

$errors = [];

$billing_name = '';
$vendor_name = '';
$login_url = '';
$cadence = 'monthly';
$reserve_style = 'sinking_fund';
$default_amount = '';
// $annual_cost = '';
$due_day_of_month = '';
$due_month_of_year = '';
// $next_due_date = '';
$actual_due_date = '';
$paid_through_date = '';
// $last_paid_date = '';
$renewal_term_months = '12';
$default_funding_account_id = '';
$transfer_from_funding_account_id = '';
$intake_note = '';
$is_autopay = 1;
$auto_advance_on_payment = 1;
$is_active = 1;
$sort_order = 0;

$duplicate_billing_account_id = 0;
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$saved_name = isset($_GET['saved_name']) ? trim($_GET['saved_name']) : '';

/*
  duplicate mode:
  if present in GET, load that record and prefill the form
*/
if (
  isset($_GET['duplicate_billing_account_id']) &&
  ctype_digit((string)$_GET['duplicate_billing_account_id'])
) {
  $duplicate_billing_account_id = (int)$_GET['duplicate_billing_account_id'];

  $stmt = $pdo_db->prepare("
    SELECT *
    FROM billing_accounts
    WHERE billing_account_id = ?
      AND user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$duplicate_billing_account_id, $user_id]);
  $duplicate_bill = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($duplicate_bill) {
    $billing_name = '';
    $vendor_name = (string)($duplicate_bill['vendor_name'] ?? '');
    $intake_note = (string)($duplicate_bill['intake_note'] ?? '');
    $cadence = (string)($duplicate_bill['cadence'] ?? 'monthly');
    $reserve_style = (string)($duplicate_bill['reserve_style'] ?? 'sinking_fund');
    $default_amount = isset($duplicate_bill['default_amount']) ? (string)$duplicate_bill['default_amount'] : '';
    $actual_due_date = (string)($duplicate_bill['actual_due_date'] ?? '');
    $renewal_term_months = isset($duplicate_bill['renewal_term_months']) ? (string)$duplicate_bill['renewal_term_months'] : '1';
    $due_day_of_month = isset($duplicate_bill['due_day_of_month']) ? (string)$duplicate_bill['due_day_of_month'] : '';
    $due_month_of_year = isset($duplicate_bill['due_month_of_year']) ? (string)$duplicate_bill['due_month_of_year'] : '';
    $default_funding_account_id = isset($duplicate_bill['default_funding_account_id']) ? (string)$duplicate_bill['default_funding_account_id'] : '';
    $transfer_from_funding_account_id = isset($duplicate_bill['transfer_from_funding_account_id']) ? (string)$duplicate_bill['transfer_from_funding_account_id'] : '';
    $is_autopay = isset($duplicate_bill['is_autopay']) ? (int)$duplicate_bill['is_autopay'] : 1;
    $auto_advance_on_payment = isset($duplicate_bill['auto_advance_on_payment']) ? (int)$duplicate_bill['auto_advance_on_payment'] : 1;
    $is_active = isset($duplicate_bill['is_active']) ? (int)$duplicate_bill['is_active'] : 1;
    $sort_order = isset($duplicate_bill['sort_order']) ? (string)$duplicate_bill['sort_order'] : '0';
  }
}

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
  $login_url = trim($_POST['login_url'] ?? '');
  $cadence = trim($_POST['cadence'] ?? 'monthly');
  $reserve_style = trim($_POST['reserve_style'] ?? 'sinking_fund');
  $default_amount = trim($_POST['default_amount'] ?? '');
  $due_day_of_month = trim($_POST['due_day_of_month'] ?? '');
  $due_month_of_year = trim($_POST['due_month_of_year'] ?? '');
  $actual_due_date = trim($_POST['actual_due_date'] ?? '');
  $renewal_term_months = trim($_POST['renewal_term_months'] ?? '12');
  $default_funding_account_id = trim($_POST['default_funding_account_id'] ?? '');
  $transfer_from_funding_account_id = trim($_POST['transfer_from_funding_account_id'] ?? '');
  $intake_note = trim($_POST['intake_note'] ?? '');
  $is_autopay = isset($_POST['is_autopay']) ? 1 : 0;
  $auto_advance_on_payment = isset($_POST['auto_advance_on_payment']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $sort_order = trim($_POST['sort_order'] ?? '0');

  $custom_due_dates = $_POST['custom_due_date'] ?? [];
  $custom_due_amounts = $_POST['custom_due_amount'] ?? [];
  $custom_due_notes = $_POST['custom_due_note'] ?? [];

  $custom_due_events = [];



  if ($billing_name === '') {
    $errors[] = 'Billing account name is required.';
  }

  if ($login_url !== '' && !filter_var($login_url, FILTER_VALIDATE_URL)) {
    $errors[] = 'Login URL must be a valid URL.';
  }

  if (!in_array($cadence, ['monthly', 'annual', 'custom'], true)) {
    $errors[] = 'Cadence is invalid.';
  }

  if ($default_funding_account_id === '' || !ctype_digit((string)$default_funding_account_id)) {
    $errors[] = 'Paid From Account is required.';
  }

  if (!in_array($reserve_style, ['sinking_fund', 'prepaid'], true)) {
    $errors[] = 'Reserve Style is invalid.';
  }

  if ($cadence === 'monthly' || $cadence === 'annual') {
    if ($renewal_term_months === '' || !ctype_digit((string)$renewal_term_months) || (int)$renewal_term_months < 1) {
      $errors[] = 'Renewal Term (Months) must be at least 1.';
    }

    if ($actual_due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actual_due_date)) {
      $errors[] = 'Next Calendar Due Date is required and must be in YYYY-MM-DD format.';
    }

    if ($due_day_of_month === '' || !ctype_digit((string)$due_day_of_month) || (int)$due_day_of_month < 1 || (int)$due_day_of_month > 31) {
      $errors[] = 'Due Day of Month must be between 1 and 31.';
    }

    if ($default_amount === '' || !is_numeric($default_amount) || (float)$default_amount <= 0) {
      $errors[] = 'Amount Due must be greater than 0.';
    }

    if ($cadence === 'annual') {
      if ($due_month_of_year === '' || !ctype_digit((string)$due_month_of_year) || (int)$due_month_of_year < 1 || (int)$due_month_of_year > 12) {
        $errors[] = 'Due Month of Year must be between 1 and 12.';
      }
    } else {
      $due_month_of_year = '';
    }
  }

  if ($cadence === 'custom') {
    $renewal_term_months = '';
    $actual_due_date = '';
    $due_day_of_month = '';
    $due_month_of_year = '';
    $default_amount = '';

    for ($i = 0; $i < count($custom_due_dates); $i++) {
      $due_date = trim((string)($custom_due_dates[$i] ?? ''));
      $amount = str_replace(',', '', trim((string)($custom_due_amounts[$i] ?? '')));
      $note = trim((string)($custom_due_notes[$i] ?? ''));

      if ($due_date === '' && $amount === '' && $note === '') {
        continue;
      }

      if ($due_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        $errors[] = 'Each custom due event must include a valid due date.';
        continue;
      }

      if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Each custom due event must include an amount greater than 0.';
        continue;
      }

      $custom_due_events[] = [
        'due_date' => $due_date,
        'amount' => number_format((float)$amount, 2, '.', ''),
        'note' => $note
      ];
    }

    if (empty($custom_due_events)) {
      $errors[] = 'At least one custom due event is required when Cadence is Custom.';
    }
  }


  $default_funding_account_id = ($default_funding_account_id === '') ? null : (int)$default_funding_account_id;
  $transfer_from_funding_account_id = ($transfer_from_funding_account_id === '') ? null : (int)$transfer_from_funding_account_id;
  $due_day_of_month = (int)$due_day_of_month;
  $due_month_of_year = ($due_month_of_year === null || $due_month_of_year === '') ? null : (int)$due_month_of_year;
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
        login_url,
        intake_note,
        cadence,
        reserve_style,
        default_amount,
        actual_due_date,
        renewal_term_months,
        due_day_of_month,
        due_month_of_year,
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
      $login_url !== '' ? $login_url : null,
      $intake_note !== '' ? $intake_note : null,
      $cadence,
      $reserve_style,
      $default_amount !== '' ? (float)$default_amount : null,
      $actual_due_date !== '' ? $actual_due_date : null,
      $renewal_term_months !== '' ? (int)$renewal_term_months : null,
      $due_day_of_month !== '' ? (int)$due_day_of_month : null,
      $due_month_of_year !== '' ? (int)$due_month_of_year : null,
      $default_funding_account_id,
      $transfer_from_funding_account_id,
      $is_autopay,
      $auto_advance_on_payment,
      $is_active,
      $sort_order
    ]);

    $new_billing_account_id = (int)$pdo_db->lastInsertId();


    if ($cadence === 'custom' && !empty($custom_due_events)) {
      foreach ($custom_due_events as $event) {
        $stmt = $pdo_db->prepare("
          INSERT INTO bill_due_schedule (
            billing_account_id,
            user_id,
            due_date,
            amount,
            note,
            updated_at
          ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
          $new_billing_account_id,
          $user_id,
          $event['due_date'],
          (float)$event['amount'],
          $event['note'] !== '' ? $event['note'] : null
        ]);
      }

      sync_custom_bill_actual_due_date($pdo_db, $user_id, $new_billing_account_id);
    }


    $stmt = $pdo_db->prepare("
      INSERT INTO bill_activity_log (
        billing_account_id,
        user_id,
        activity_type,
        note
      ) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
      $new_billing_account_id,
      $user_id,
      'created',
      'Billing account created.'
    ]);

    $redirect_name = urlencode($billing_name);

    if (isset($_POST['save_and_duplicate_again'])) {
      header('Location: intake_billing-accounts.php?duplicate_billing_account_id=' . $new_billing_account_id . '&saved=1&saved_name=' . $redirect_name);
      exit();
    }

    header('Location: intake_billing-accounts.php?saved=1&saved_name=' . $redirect_name);
    exit();
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Add Billing Account</h1>

    <?php if ($duplicate_billing_account_id > 0): ?>
      <div class="success" style="display:block;">
        Billing account added. Form reloaded from your last submission so you can duplicate it again.
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

    <?php if ($saved): ?>
      <div class="success" style="display:block;">
        <?php if ($saved_name !== ''): ?>
          <?php echo htmlspecialchars($saved_name, ENT_QUOTES, 'UTF-8'); ?> was successfully added.
        <?php else: ?>
          Billing account added.
        <?php endif; ?>
      </div>
    <?php endif; ?>

      <form method="post">
        <input type="hidden" name="create_billing_account" value="1">

        <div class="two-col">
          <div class="row">
            <label for="billing_name">Billing Account Name</label>
            <input
              type="text"
              id="billing_name"
              name="billing_name"
              value="<?php echo htmlspecialchars($billing_name, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

          <div class="row">
            <label for="vendor_name">Vendor Name</label>
            <input
              type="text"
              id="vendor_name"
              name="vendor_name"
              value="<?php echo htmlspecialchars($vendor_name, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        </div>

        <div class="row standalone">
          <label for="login_url">Login URL</label>
          <input
            type="url"
            id="login_url"
            name="login_url"
            value="<?php echo htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="https://example.com/login"
          >
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

          <div class="row" id="renewal-term-wrap">
            <label for="renewal_term_months">Renewal Term (Months)</label>
            <input
              type="number"
              id="renewal_term_months"
              name="renewal_term_months"
              min="1"
              value="<?php echo htmlspecialchars((string)$renewal_term_months, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>
        </div>






        <div id="custom-due-events-wrap" style="display:none; margin-top: 1em;">
          <h3>Custom Due Events</h3>
          <p>Enter each due date and amount for this bill. Add as many future events as needed.</p>

          <div id="custom-due-events">
            <?php
            $custom_due_events = $custom_due_events ?? [
              ['due_date' => '', 'amount' => '', 'note' => '']
            ];
            ?>

            <?php foreach ($custom_due_events as $index => $event): ?>
              <div class="custom-due-event two-col" data-index="<?php echo (int)$index; ?>" style="margin-bottom: 1em; padding: 1em; border: 1px solid #ddd;">

                <div class="two-col">
                  <div class="row">
                    <label>Due Date</label>
                    <input
                      type="date"
                      name="custom_due_date[]"
                      value="<?php echo htmlspecialchars((string)$event['due_date'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                  </div>

                  <div class="row">
                    <label>Amount</label>
                    <input
                      type="text"
                      name="custom_due_amount[]"
                      value="<?php echo htmlspecialchars((string)$event['amount'], ENT_QUOTES, 'UTF-8'); ?>"
                      placeholder="0.00"
                    >
                  </div>
                </div>

                <div class="row standalone" style="grid-column: 1 / -1;">
                  <label>Note</label>
                  <input
                    type="text"
                    name="custom_due_note[]"
                    value="<?php echo htmlspecialchars((string)$event['note'], ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Optional note"
                  >
                </div>

                <div class="row standalone" style="grid-column: 1 / -1;">
                  <button type="button" class="remove-custom-due-event">Remove</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <button type="button" id="add-custom-due-event">Add Another Due Event</button>
        </div>
        <template id="custom-due-event-template">
          <div class="custom-due-event two-col" style="margin-bottom: 1em; padding: 1em; border: 1px solid #ddd;">
            <div class="two-col">
              <div class="row">
                <label>Due Date</label>
                <input type="date" name="custom_due_date[]">
              </div>

              <div class="row">
                <label>Amount</label>
                <input type="text" name="custom_due_amount[]" placeholder="0.00">
              </div>
            </div>

            <div class="row standalone" style="grid-column: 1 / -1;">
              <label>Note</label>
              <input type="text" name="custom_due_note[]" placeholder="Optional note">
            </div>

            <div class="row standalone" style="grid-column: 1 / -1;">
              <button type="button" class="remove-custom-due-event">Remove</button>
            </div>
          </div>
        </template>






        <div class="two-col">

          <div class="row" id="actual-due-date-wrap">
            <?php /* Previously 'Actual Due Date' - renamed for clarity.
            <label for="actual_due_date">Actual Due Date</label> 
            */ ?>
             <label for="actual_due_date">Next Calendar Due Date</label>
            <input
              type="date"
              id="actual_due_date" <?php /* using this ID for JS purposes to dynamically reveal Due Month of Year */ ?>
              name="actual_due_date"
              value="<?php echo htmlspecialchars($actual_due_date, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>

        </div>

        <div class="two-col">
          <div class="row" id="due-month-wrap">
            <label for="due_month_of_year">Due Month of Year</label>
            <input
              type="number"
              id="due_month_of_year"
              name="due_month_of_year"
              min="1"
              max="12"
              value="<?php echo htmlspecialchars((string)$due_month_of_year, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>

          <div class="row" id="due-day-wrap">
            <label for="due_day_of_month">Due Day of Month</label>
            <input
              type="number"
              id="due_day_of_month"
              name="due_day_of_month"
              min="1"
              max="31"
              value="<?php echo htmlspecialchars((string)$due_day_of_month, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
          </div>
        </div>

        <div class="two-col">
          <div class="row" id="default-amount-wrap">
            <label for="default_amount">Amount Due</label>
            <input
              type="number"
              step="0.01"
              id="default_amount"
              name="default_amount"
              value="<?php echo htmlspecialchars($default_amount, ENT_QUOTES, 'UTF-8'); ?>"
              required
            >
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
            <label for="reserve_style">Reserve Style</label>
            <select id="reserve_style" name="reserve_style">
              <option value="sinking_fund" <?php echo ($reserve_style === 'sinking_fund') ? 'selected' : ''; ?>>Sinking Fund</option>
              <option value="prepaid" <?php echo ($reserve_style === 'prepaid') ? 'selected' : ''; ?>>Prepaid</option>
            </select>
          </div>
        </div>

<?php if (false): /* not sure how/why/if needed */ ?>
        <div class="two-col">
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

          <div class="row">
            <label for="sort_order">Sort Order</label>
            <input
              type="number"
              id="sort_order"
              name="sort_order"
              min="0"
              value="<?php echo htmlspecialchars((string)$sort_order, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        </div>
<?php endif; ?>        

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

        <div class="form-actions">
          <button type="submit" name="save_billing_account" value="1">Add New Account</button>
          <button type="submit" name="save_and_duplicate_again" value="1">Add + Duplicate</button>
        </div>
      </form>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> | <a href="billing_projection.php">Projection</a> | <a href="intake_funding-accounts.php">New Funding</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>