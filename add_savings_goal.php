<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'savings_goals';
$starting_source_type = 'pool_deduction';
$errors = [];

$funding_account_id = isset($_GET['funding_account_id'])
  ? (int)$_GET['funding_account_id']
  : 0;

if (is_post_request()) {
  $funding_account_id = isset($_POST['funding_account_id'])
    ? (int)$_POST['funding_account_id']
    : 0;
}

if ($funding_account_id < 1) {
  header('Location: homepage.php');
  exit();
}

/*
  Confirm funding account belongs to this user.
*/
$stmt = $pdo_db->prepare("
  SELECT
    funding_account_id,
    account_name
  FROM funding_accounts
  WHERE funding_account_id = ?
    AND user_id = ?
  LIMIT 1
");

$stmt->execute([
  $funding_account_id,
  $user_id
]);

$funding_account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$funding_account) {
  header('Location: homepage.php');
  exit();
}

$goal_name = '';
$target_amount = '';
$starting_amount = '';
$note = '';

if (is_post_request()) {
  $goal_name = trim((string)($_POST['goal_name'] ?? ''));
  $target_amount = trim((string)($_POST['target_amount'] ?? ''));
  $starting_amount = trim((string)($_POST['starting_amount'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));
  $starting_source_type = trim((string)($_POST['starting_source_type'] ?? 'pool_deduction'));

  if (!in_array($starting_source_type, ['pool_deduction', 'new_deposit'], true)) {
    $errors[] = 'Invalid starting amount source.';
  }

  if ($goal_name === '') {
    $errors[] = 'Goal name is required.';
  }

  if ($target_amount === '' || !is_numeric($target_amount)) {
    $errors[] = 'Target amount is required.';
  } elseif ((float)$target_amount <= 0) {
    $errors[] = 'Target amount must be greater than zero.';
  }

  if ($starting_amount === '') {
    $starting_amount = '0';
  }

  if (!is_numeric($starting_amount)) {
    $errors[] = 'Starting amount must be a valid number.';
  } elseif ((float)$starting_amount < 0) {
    $errors[] = 'Starting amount cannot be negative.';
  }

  if (!$errors) {
    $target_amount_float = round((float)$target_amount, 2);
    $starting_amount_float = round((float)$starting_amount, 2);

    $available_for_bills = funding_account_available_for_bills_by_id(
      $pdo_db,
      $user_id,
      $funding_account_id
    );

    if (
      $starting_source_type === 'pool_deduction' &&
      $starting_amount_float > $available_for_bills
    ) {
      $errors[] =
        'Starting amount cannot exceed the amount currently available for bills. Available: $' .
        number_format($available_for_bills, 2) .
        '.';
    }



  }

  if (!$errors) {
    $pdo_db->beginTransaction();

    try {
      $stmt = $pdo_db->prepare("
        INSERT INTO funding_account_savings_goals (
          funding_account_id,
          user_id,
          goal_name,
          target_amount,
          note,
          status,
          created_at
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
      ");

      $stmt->execute([
        $funding_account_id,
        $user_id,
        $goal_name,
        $target_amount_float,
        $note !== '' ? $note : null
      ]);

      $savings_goal_id = (int)$pdo_db->lastInsertId();

      if ($starting_amount_float > 0) {
        $stmt = $pdo_db->prepare("
        INSERT INTO funding_account_savings_goal_transactions (
          savings_goal_id,
          funding_account_id,
          user_id,
          transaction_type,
          source_type,
          amount,
          transaction_date,
          note
        ) VALUES (?, ?, ?, 'contribution', ?, ?, NOW(), ?)
        ");

        $stmt->execute([
          $savings_goal_id,
          $funding_account_id,
          $user_id,
          $starting_source_type,
          $starting_amount_float,
          'Initial savings goal contribution'
        ]);


        if ($starting_source_type === 'new_deposit') {
          $stmt = $pdo_db->prepare("
            INSERT INTO funding_account_reserve_transactions (
              funding_account_id,
              user_id,
              billing_account_id,
              transaction_type,
              amount,
              transaction_date,
              note
            ) VALUES (?, ?, NULL, 'contribution', ?, NOW(), ?)
          ");

          $stmt->execute([
            $funding_account_id,
            $user_id,
            $starting_amount_float,
            'New deposit for savings goal: ' . $goal_name
          ]);
        }


      }

      $pdo_db->commit();

      header(
        'Location: funding_account_ledger.php?funding_account_id=' .
        $funding_account_id .
        '&saved=1'
      );
      exit();

    } catch (Throwable $e) {
      if ($pdo_db->inTransaction()) {
        $pdo_db->rollBack();
      }

      error_log(
        'Add savings goal failed for funding_account_id ' .
        $funding_account_id .
        ': ' .
        $e->getMessage()
      );

      $errors[] = 'Savings goal could not be added.';
    }
  }
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1>Add Savings Goal</h1>

    <p>
      Funding Account:
      <strong>
        <?php echo htmlspecialchars((string)$funding_account['account_name'], ENT_QUOTES, 'UTF-8'); ?>
      </strong>
    </p>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li>
              <?php echo htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8'); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="add_savings_goal.php" method="post">
      <input
        type="hidden"
        name="funding_account_id"
        value="<?php echo (int)$funding_account_id; ?>"
      >

      <p>
        <label for="goal_name">Goal Name</label><br>
        <input
          type="text"
          name="goal_name"
          id="goal_name"
          value="<?php echo htmlspecialchars($goal_name, ENT_QUOTES, 'UTF-8'); ?>"
          required
        >
      </p>

      <p>
        <label for="target_amount">Target Amount</label><br>
        <input
          type="number"
          name="target_amount"
          id="target_amount"
          value="<?php echo htmlspecialchars((string)$target_amount, ENT_QUOTES, 'UTF-8'); ?>"
          step="0.01"
          min="0.01"
          required
        >
      </p>

      <p>
        <label for="starting_amount">Starting Amount</label><br>
        <input
          type="number"
          name="starting_amount"
          id="starting_amount"
          value="<?php echo htmlspecialchars((string)$starting_amount, ENT_QUOTES, 'UTF-8'); ?>"
          step="0.01"
          min="0"
        >
      </p>








      <p>
        <label for="starting_source_type">Starting Amount Source</label><br>

        <select name="starting_source_type" id="starting_source_type">
          <option value="chooseone">
            Source of contribution
          </option>
          <option value="pool_deduction">
            Set aside from current balance
          </option>
          <option value="new_deposit">
            Add as new deposit to funding account
          </option>
        </select>
      </p>

      <p>
        <small>
          Use “set aside from current balance” if the money is already in this funding account.
          Use “new deposit” if you are adding new money to the funding account for this goal.
        </small>
      </p>




      <p>
        <small>
          The starting amount is set aside immediately. It remains part of the actual funding account balance, but is excluded from bill projections.
        </small>
      </p>

      <p>
        <label for="note">Note</label><br>
        <textarea
          name="note"
          id="note"
          rows="4"
        ><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </p>

      <p>
        <button type="submit">Add Savings Goal</button>
      </p>
    </form>

    <p>
      <a href="funding_account_ledger.php?funding_account_id=<?php echo (int)$funding_account_id; ?>">
        Cancel
      </a>
    </p>

  </div>
</div>

<?php require '_includes/footer.php'; ?>