<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'fundingAcctLedger';

$errors = [];
$funding_account_id = 0;
$funding_account = null;
$ledger_rows = [];
$current_balance = 0.00;

/*
  pick funding account:
  - explicit GET id if present
  - otherwise first active account for this user
*/
if (isset($_GET['funding_account_id']) && ctype_digit((string)$_GET['funding_account_id'])) {
  $funding_account_id = (int)$_GET['funding_account_id'];
} else {
  $stmt = $pdo_db->prepare("
    SELECT funding_account_id
    FROM funding_accounts
    WHERE user_id = ?
      AND is_active = 1
    ORDER BY account_name ASC
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $first_account = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($first_account) {
    $funding_account_id = (int)$first_account['funding_account_id'];
  }
}

/*
  load active funding accounts for selector
*/
$stmt = $pdo_db->prepare("
  SELECT funding_account_id, account_name
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

if ($funding_account) {
  $ledger_rows = funding_account_ledger_with_running_balance($pdo_db, $user_id, $funding_account_id);
  $current_balance = funding_account_current_balance_from_ledger($pdo_db, $user_id, $funding_account_id);
}

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule">

    <h1>Funding Account Ledger</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php 
    /*  $single_fund_acct = determine whether there is more than 1 funding account
        in order to present accordingly (e.g., dropdown or no dropdown,
        the need for the word "selected" or not, etc.) */
    if (count($funding_accounts) === 1) { 
      $single_fund_acct = 'yes'; 
    } else { 
      $single_fund_acct = 'no'; 
    } ?>


    <?php if ($single_fund_acct !== 'yes') { ?>
      <?php if ($funding_accounts): ?>
        <form class="pro-acct" method="get" style="margin-bottom: 1em;"> 
          <label for="funding_account_id"><strong>View Funding Ledger:</strong></label>
          <select id="funding_account_id" name="funding_account_id" onchange="this.form.submit()">
            <?php foreach ($funding_accounts as $account): ?>
              <option value="<?php echo (int)$account['funding_account_id']; ?>" <?php echo ((int)$funding_account_id === (int)$account['funding_account_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($account['account_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit">View</button></noscript>
        </form>
      <?php endif; ?>
    <?php } else { ?>
      You only have 1 funding account.
    <?php } ?>

    <?php if ($funding_account): ?>
      <div class="success" style="display:block;">

        <strong><?php echo htmlspecialchars((string)$funding_account['account_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        Current Ledger Balance: $<?php echo number_format($current_balance, 2); ?>

        <br><a href="reserve_adjustment.php?funding_account_id=<?php echo $funding_account_id; ?>">
          Adjust Balance
        </a>

        <?php if (!empty($funding_account['login_url'])): ?>
          <br><a class="btn-one" href="<?php echo htmlspecialchars((string)$funding_account['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            Login to <?php echo htmlspecialchars((string)$funding_account['account_name'], ENT_QUOTES, 'UTF-8'); ?>
          </a><br>
        <?php endif; ?>
      
      </div>

      <?php if ($ledger_rows): ?>
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Source</th>
              <th>Bill</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Running Balance</th>
              <th>Note</th>
              <th>Edit Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ledger_rows as $row): 
              $haystack = htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8');
              $needle = "Automatic draft deduction"; ?>
              <tr class="<?php echo ((float)$row['signed_amount'] < 0) ? 'due' : 'paid'; ?>">
                <td>
                  <?php if (!empty($row['event_datetime']) && !str_contains($haystack, $needle)) {
                    echo date("m.d.y \\a\\t H:i", strtotime($row['event_datetime']));
                  } else {
                    echo date("m.d.y", strtotime($row['event_datetime']));
                  }
                  ?>
                </td>

                <td>
                  <?php
                  if ($row['event_type'] === 'account_adjustment') {
                    echo 'Account';
                  } elseif ($row['event_type'] === 'bill_reserve') {
                    echo 'Bill Reserve';
                  } elseif ($row['event_type'] === 'bill_payment') {
                    echo 'Payment';
                  } else {
                    echo htmlspecialchars((string)$row['event_type'], ENT_QUOTES, 'UTF-8');
                  }
                  ?>
                </td>

                <td>
                  <?php if (!empty($row['billing_account_id'])): ?>
                    <a href="bill_details.php?billing_account_id=<?php echo (int)$row['billing_account_id']; ?>">
                      <?php echo htmlspecialchars((string)$row['billing_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  <?php else: ?>
                    &nbsp;
                  <?php endif; ?>
                </td>

                <td><?php echo htmlspecialchars((string)$row['sub_type'], ENT_QUOTES, 'UTF-8'); ?></td>

                <td>
                  <?php
                  $sign = ((float)$row['signed_amount'] < 0) ? '-' : '+';
                  echo $sign . '$' . number_format(abs((float)$row['signed_amount']), 2);
                  ?>
                </td>

                <td>$<?php echo number_format((float)$row['running_balance'], 2); ?></td>

                <td><?php echo htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8'); ?></td>

                <td style="text-align: center;">
                  <a href="edit_note.php?source=funding&id=<?php echo (int)$row['id']; ?>">
                    <i class="fas fa-edit"></i>
                  </a>
                </td>


              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No ledger activity recorded yet.</p>
      <?php endif; ?>

      <div class="inner-links">
        <a href="index.php">Dashboard</a> |
        <a href="billing_projection.php?account=<?php echo urlencode((string)$funding_account['account_name']); ?>">Projection</a> | 
        <a href="reserve_adjustment.php">Reserve Adjustment</a> |
        <a href="funding_accounts.php">Funding Accounts</a>
        
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require '_includes/footer.php'; ?>