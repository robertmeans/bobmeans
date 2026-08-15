<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'savings_goals';
$source_type = 'pool_deduction';
$errors = [];

$savings_goal_id = isset($_GET['savings_goal_id'])
  ? (int)$_GET['savings_goal_id']
  : 0;

$type = isset($_GET['type'])
  ? trim((string)$_GET['type'])
  : 'contribution';

if (is_post_request()) {
  $savings_goal_id = isset($_POST['savings_goal_id'])
    ? (int)$_POST['savings_goal_id']
    : 0;

  $type = isset($_POST['type'])
    ? trim((string)$_POST['type'])
    : 'contribution';
}

if ($savings_goal_id < 1) {
  header('Location: homepage.php');
  exit();
}

/*
  For now, this page only handles contributions.
  Release and spend will be added after contribution is confirmed.
*/
$allowed_types = ['contribution', 'release', 'spend'];

if (!in_array($type, $allowed_types, true)) {
  header('Location: homepage.php');
  exit();
}

/*
  Confirm savings goal belongs to this user and load funding account context.
*/
$stmt = $pdo_db->prepare("
  SELECT
    sg.savings_goal_id,
    sg.funding_account_id,
    sg.user_id,
    sg.goal_name,
    sg.target_amount,
    sg.note,
    sg.status,
    fa.account_name
  FROM funding_account_savings_goals sg
  INNER JOIN funding_accounts fa
    ON sg.funding_account_id = fa.funding_account_id
  WHERE sg.savings_goal_id = ?
    AND sg.user_id = ?
    AND sg.status = 'active'
  LIMIT 1
");

$stmt->execute([
  $savings_goal_id,
  $user_id
]);

$savings_goal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$savings_goal) {
  header('Location: homepage.php');
  exit();
}

$funding_account_id = (int)$savings_goal['funding_account_id'];

$current_amount = savings_goal_current_balance(
  $pdo_db,
  $user_id,
  $savings_goal_id
);

$target_amount = round((float)$savings_goal['target_amount'], 2);
$remaining_amount = max(0, $target_amount - $current_amount);

$action_title = 'Add Funds to Savings Goal';
$amount_label = 'Amount to Add';
$submit_label = 'Add Funds';

if ($type === 'release') {
  $action_title = 'Release Savings Goal Funds';
  $amount_label = 'Amount to Release';
  $submit_label = 'Release Funds';
} elseif ($type === 'spend') {
  $action_title = 'Mark Savings Goal Spent';
  $amount_label = 'Amount Spent';
  $submit_label = 'Mark Spent';
}

$amount = '';
$note = '';

if (is_post_request()) {
  $amount = trim((string)($_POST['amount'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));

  $source_type = trim((string)($_POST['source_type'] ?? 'pool_deduction'));

  if (!in_array($source_type, ['pool_deduction', 'new_deposit'], true)) {
    $errors[] = 'Invalid contribution source.';
  }


  if ($amount === '' || !is_numeric($amount)) {
    $errors[] = 'Amount is required.';
  } elseif ((float)$amount <= 0) {
    $errors[] = 'Amount must be greater than zero.';
  }

  if (!$errors) {
    $amount_float = round((float)$amount, 2);


    if ($type === 'contribution') {
      $available_for_bills = funding_account_available_for_bills_by_id(
        $pdo_db,
        $user_id,
        $funding_account_id
      );

      if (
        $source_type === 'pool_deduction' &&
        $amount_float > $available_for_bills
      ) {
        $errors[] =
          'Amount cannot exceed the amount currently available for bills. Available: $' .
          number_format($available_for_bills, 2) .
          '.';
      }
    }

    if ($type === 'release') {
      if ($amount_float > $current_amount) {
        $errors[] =
          'Release amount cannot exceed the current savings goal balance. Current balance: $' .
          number_format($current_amount, 2) .
          '.';
      }
    }

    if ($type === 'spend') {
      if ($amount_float > $current_amount) {
        $errors[] =
          'Amount spent cannot exceed the current savings goal balance. Current balance: $' .
          number_format($current_amount, 2) .
          '.';
      }
    }


  }


  if (!$errors) {
    $pdo_db->beginTransaction();

    try {
      $transaction_type = $type;

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
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
      ");

      $default_note = 'Savings goal contribution';

      if ($transaction_type === 'release') {
        $default_note = 'Savings goal funds released';
      } elseif ($transaction_type === 'spend') {
        $default_note = 'Savings goal funds spent';
      }

      $stmt->execute([
        $savings_goal_id,
        $funding_account_id,
        $user_id,
        $transaction_type,
        $source_type,
        $amount_float,
        $note !== '' ? $note : $default_note
      ]);

      if (
        $transaction_type === 'contribution' &&
        $source_type === 'new_deposit'
      ) {
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
          $amount_float,
          'New deposit for savings goal: ' . $savings_goal['goal_name']
        ]);
      }

      if ($transaction_type === 'spend') {
        $stmt = $pdo_db->prepare("
          INSERT INTO funding_account_reserve_transactions (
            funding_account_id,
            user_id,
            billing_account_id,
            transaction_type,
            amount,
            transaction_date,
            note
          ) VALUES (?, ?, NULL, 'deduction', ?, NOW(), ?)
        ");

        $stmt->execute([
          $funding_account_id,
          $user_id,
          $amount_float,
          'Savings goal spent: ' . $savings_goal['goal_name']
        ]);
      }
      

    if (in_array($transaction_type, ['release', 'spend'], true)) {
      $new_goal_balance = round($current_amount - $amount_float, 2);

      if ($new_goal_balance <= 0) {
        if ($transaction_type === 'release') {
          $stmt = $pdo_db->prepare("
            UPDATE funding_account_savings_goals
            SET
              status = 'released',
              released_at = NOW(),
              updated_at = NOW()
            WHERE savings_goal_id = ?
              AND user_id = ?
            LIMIT 1
          ");
        } else {
          $stmt = $pdo_db->prepare("
            UPDATE funding_account_savings_goals
            SET
              status = 'completed',
              completed_at = NOW(),
              updated_at = NOW()
            WHERE savings_goal_id = ?
              AND user_id = ?
            LIMIT 1
          ");
        }

        $stmt->execute([
          $savings_goal_id,
          $user_id
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
        'Savings goal transaction failed for savings_goal_id ' .
        $savings_goal_id .
        ': ' .
        $e->getMessage()
      );

      $errors[] = 'Savings goal transaction could not be added.';
    }
  }


}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="funding-form">

    <h1><?php echo htmlspecialchars($action_title, ENT_QUOTES, 'UTF-8'); ?></h1>

    <p>
      Funding Account:
      <strong>
        <?php echo htmlspecialchars((string)$savings_goal['account_name'], ENT_QUOTES, 'UTF-8'); ?>
      </strong>
    </p>

    <p>
      Savings Goal:
      <strong>
        <?php echo htmlspecialchars((string)$savings_goal['goal_name'], ENT_QUOTES, 'UTF-8'); ?>
      </strong>
    </p>

    <table>
      <tbody>
        <tr>
          <th>Saved</th>
          <td>
            $<?php echo number_format($current_amount, 2); ?>
          </td>
        </tr>

        <tr>
          <th>Target</th>
          <td>
            $<?php echo number_format($target_amount, 2); ?>
          </td>
        </tr>

        <tr>
          <th>Remaining</th>
          <td>
            $<?php echo number_format($remaining_amount, 2); ?>
          </td>
        </tr>
      </tbody>
    </table>

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

    <form action="add_savings_goal_transaction.php" method="post">
      <input
        type="hidden"
        name="savings_goal_id"
        value="<?php echo (int)$savings_goal_id; ?>"
      >

      <input
        type="hidden"
        name="type"
        value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
      >

      <p>
        <label for="amount"><?php echo htmlspecialchars($amount_label, ENT_QUOTES, 'UTF-8'); ?></label><br>
        <input
          type="number"
          name="amount"
          id="amount"
          value="<?php echo htmlspecialchars((string)$amount, ENT_QUOTES, 'UTF-8'); ?>"
          step="0.01"
          min="0.01"
          required
        >
      </p>


<?php if ($type === 'contribution'): ?>
  <p>
    <label for="source_type">Contribution Source</label><br>

    <select name="source_type" id="source_type">
      <option value="pool_deduction" <?php if ($source_type === 'pool_deduction') { echo 'selected'; } ?>>
        Set aside from current balance
      </option>
      <option value="new_deposit" <?php if ($source_type === 'new_deposit') { echo 'selected'; } ?>>
        Add as new deposit to funding account
      </option>
    </select>
  </p>

  <p>
    <small>
      Use “set aside from current balance” if this money is already in the funding account.
      Use “new deposit” if this is new money being added for the savings goal.
    </small>
  </p>

  <p>
    <small>
      If this is set aside from the current balance, the funding account balance will not change and the amount will be excluded from bill projections. If this is a new deposit, the funding account balance and savings goal will both increase, leaving the amount available for bills unchanged.
    </small>
  </p>

<?php elseif ($type === 'release'): ?>
  <input type="hidden" name="source_type" value="pool_deduction">

  <p>
    <small>
      Released funds remain in the funding account and become available for bill projections again.
      The actual funding account balance will not change.
    </small>
  </p>

<?php elseif ($type === 'spend'): ?>
  <input type="hidden" name="source_type" value="pool_deduction">

  <p>
    <small>
      Spent funds will be removed from this savings goal and deducted from the funding account ledger.
      Available-for-bills will not change because this money was already excluded from bill projections.
    </small>
  </p>



<?php endif; ?>




      <p>
        <label for="note">Note</label><br>
        <textarea
          name="note"
          id="note"
          rows="4"
        ><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
      </p>

      <p>
        <button type="submit">
          <?php echo htmlspecialchars($submit_label, ENT_QUOTES, 'UTF-8'); ?>
        </button>
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