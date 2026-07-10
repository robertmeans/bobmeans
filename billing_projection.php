<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'projection';
$selected_account = isset($_GET['account']) ? trim($_GET['account']) : 'PayPal';

if (is_post_request() && isset($_POST['process_now'])) {
  $process_billing_account_id = isset($_POST['billing_account_id']) ? (int)$_POST['billing_account_id'] : 0;
  $process_due_date = trim($_POST['due_date'] ?? '');

  $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

  $response = [
    'success' => false,
    'message' => 'Unable to process this bill.'
  ];

  if (
    $process_billing_account_id > 0 &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $process_due_date)
  ) {
    $result = process_bill_now($pdo_db, $user_id, $process_billing_account_id, $process_due_date);

    if (!empty($result['success'])) {
      $response = [
        'success' => true,
        'redirect' => 'billing_projection.php?account=' . urlencode($selected_account)
      ];
    } else {
      if (($result['reason'] ?? '') === 'insufficient_funds') {
        $response['message'] =
          'Not enough funds available. Needed: $' .
          number_format((float)($result['needed'] ?? 0), 2) .
          '. Available: $' .
          number_format((float)($result['available'] ?? 0), 2) . '.';
      } elseif (($result['reason'] ?? '') === 'already_processed') {
        $response['message'] = 'This bill has already been processed for that due date.';
      } elseif (($result['reason'] ?? '') === 'missing_funding_account') {
        $response['message'] = 'No funding account is assigned to this bill.';
      } elseif (($result['reason'] ?? '') === 'bill_not_found') {
        $response['message'] = 'The bill could not be found.';
      } elseif (($result['reason'] ?? '') === 'invalid_amount') {
        $response['message'] = 'This bill has an invalid amount due.';
      }
    }
  } else {
    $response['message'] = 'Invalid request.';
  }

  if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
  }

  if (!empty($response['success']) && !empty($response['redirect'])) {
    redirect_to($response['redirect']);
  }

  redirect_to('billing_projection.php?account=' . urlencode($selected_account));
}


// $reconciliation = reconcile_due_bills_against_reserves($pdo_db, $user_id);
reconcile_due_bills_against_reserves($pdo_db, $user_id);

$stmt = $pdo_db->prepare("
  SELECT
    ba.user_id, 
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.intake_note,
    ba.cadence,
    ba.reserve_style,
    ba.default_amount,
    ba.actual_due_date,
    ba.renewal_term_months,
    ba.due_day_of_month,
    ba.due_month_of_year,
    ba.default_funding_account_id,
    ba.is_active,
    fa.account_name AS paid_from_account
  FROM billing_accounts ba
  LEFT JOIN funding_accounts fa
    ON ba.default_funding_account_id = fa.funding_account_id
  WHERE ba.user_id = ?
    AND ba.is_active = 1
  ORDER BY ba.billing_name ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo_db->prepare("
  SELECT
    funding_account_id,
    account_name,
    login_url
  FROM funding_accounts
  WHERE user_id = ?
    AND is_active = 1
  ORDER BY account_name ASC
");
$stmt->execute([$user_id]);
$funding_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// $reserve_totals = combined_reserve_totals_by_funding_account($pdo_db, $user_id, $rows);
$reserve_totals = funding_account_pool_totals($pdo_db, $user_id);

if (!isset($reserve_totals[$selected_account])) {
  $account_names = array_keys($reserve_totals);
  $selected_account = !empty($account_names) ? $account_names[0] : 'PayPal';
}

$projection_rows = filter_rows_by_funding_account($rows, $selected_account);

$funding_account_meta = [];

foreach ($funding_accounts as $funding) {
  $funding_account_meta[(string)$funding['account_name']] = [
    'funding_account_id' => (int)$funding['funding_account_id'],
    'login_url' => $funding['login_url'] ?? null
  ];
}

$pool_amount = isset($reserve_totals[$selected_account]) ? (float)$reserve_totals[$selected_account] : 0.00;

$months_ahead = 12;
$events = generate_projected_bill_events($pdo_db, $projection_rows, $months_ahead);
$projection = apply_pool_to_projected_events($events, $pool_amount);

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule projection">

   <?php /* <h1><?php echo htmlspecialchars((string)$selected_account, ENT_QUOTES, 'UTF-8'); ?> Billing Projection</h1> */ ?>
   <h1>Billing Projection</h1>

    <?php 
    /*  $single_fund_acct = determine whether there is more than 1 funding account
        in order to present accordingly (e.g., dropdown or no dropdown,
        the need for the word "selected" or not, etc.) */
    if (count($reserve_totals) === 1) { 
      $single_fund_acct = 'yes'; 
    } else { 
      $single_fund_acct = 'no'; 
    } ?>

    <?php if ($single_fund_acct !== 'yes') { ?>

      <?php foreach ($reserve_totals as $account_name => $amount): ?>
        <?php
        $meta = $funding_account_meta[$account_name] ?? null;
        $funding_account_id = $meta['funding_account_id'] ?? null;
        $login_url = $meta['login_url'] ?? null;
        ?>

        <?php if ($selected_account === $account_name) { ?>
          <div class="selected-fund">
            <i class="fas fa-star"></i>&nbsp; [selected]

            <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>
            $<?php echo number_format($amount, 2); ?>

            &nbsp;<i class="fas fa-star"></i>

            <?php if (!empty($funding_account_id)): ?>
              <a class="btn-three" href="funding_account_ledger.php?funding_account_id=<?php echo (int)$funding_account_id; ?>">
                Ledger
              </a>
            <?php endif; ?>

            <?php if (!empty($login_url)): ?>
              <a class="btn-three" href="<?php echo htmlspecialchars((string)$login_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                Login
              </a>
            <?php endif; ?>
          </div>
        <?php } else { ?>
          <div class="other-fund">
            <a class="btn-four" href="billing_projection.php?account=<?php echo urlencode($account_name); ?>">Switch to: <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?> $<?php echo number_format($amount, 2); ?></a>

            <?php if (!empty($funding_account_id)): ?>
              <a class="btn-four" href="funding_account_ledger.php?funding_account_id=<?php echo (int)$funding_account_id; ?>">
                Ledger
              </a>
            <?php endif; ?>

            <?php if (!empty($login_url)): ?>
              <a class="btn-four" href="<?php echo htmlspecialchars((string)$login_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                Login
              </a>
            <?php endif; ?>
          </div>
        <?php } ?>

      <?php endforeach; ?>

    <?php } else { ?>

      <?php
      $only_funding = !empty($funding_accounts) ? $funding_accounts[0] : null;
      ?>

      <div>
        You only have 1 funding account.

        <?php if ($only_funding): ?>
          <?php if (!empty($only_funding['funding_account_id'])): ?>
            <a class="btn-three" href="funding_account_ledger.php?funding_account_id=<?php echo (int)$only_funding['funding_account_id']; ?>">
              Ledger
            </a>
          <?php endif; ?>

          <?php if (!empty($only_funding['login_url'])): ?>
            <a class="btn-three" href="<?php echo htmlspecialchars((string)$only_funding['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
              Login
            </a>
          <?php endif; ?>
        <?php endif; ?>

        - <a class="btn-two" href="intake_funding-accounts.php">Add another</a>
      </div>

    <?php } ?>

    <div class="table-container" style="margin-top: 0.5em;">
    <table>
      <thead>
        <tr>
          <th>Billing Account</th>
          <th>Due Date</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Covered</th>
          <th>Remaining Due</th>
          <th>Pool Left</th>
          <th>Process Now</th>
        </tr>
      </thead>
      <tbody>
      <?php
        /* this is to put a class in the 1st tr that's *either* partial OR due */
        $attention_index = null;
        $attention_class = '';

        foreach ($projection['events'] as $index => $event) {
          if ($event['status'] === 'partial') {
            $attention_index = $index;
            $attention_class = 'first-partial';
            break;
          }
        }

        if ($attention_index === null) {
          foreach ($projection['events'] as $index => $event) {
            if ($event['status'] === 'due') {
              $attention_index = $index;
              $attention_class = 'first-due';
              break;
            }
          }
        }

        $linked_first_uncovered = false; /* flag first partial or due for hyperlink in "Remaining Due" column */
        // foreach ($projection['events'] as $index => $event): 
        if (!empty($projection['events'])): ?>
          <tr class="opening-balance-row">
            <td colspan="6"><strong>Current <?php echo htmlspecialchars($selected_account, ENT_QUOTES, 'UTF-8'); ?> Balance</strong></td>
            <td>$<?php echo number_format($pool_amount, 2); ?></td>
            <td>&nbsp;</td>
          </tr>
        <?php endif; ?>

        <?php 
        $seen_bill_ids = [];
        foreach ($projection['events'] as $index => $event): 

        $row_classes = [$event['status']];

        $billing_account_id = (int)$event['billing_account_id'];
        $is_first_occurrence_for_bill = !in_array($billing_account_id, $seen_bill_ids, true);

        if ($is_first_occurrence_for_bill) {
          $seen_bill_ids[] = $billing_account_id;
        }


        if ($attention_index !== null && $index === $attention_index) {
          $row_classes[] = $attention_class;
        }
        /* this class goes in the next tr directly below... */
        ?>
        <tr class="<?php echo htmlspecialchars(implode(' ', $row_classes), ENT_QUOTES, 'UTF-8'); ?>">


          <td>
            <a href="bill_details.php?billing_account_id=<?php echo (int)$event['billing_account_id']; ?>"><?php echo htmlspecialchars($event['billing_name'], ENT_QUOTES, 'UTF-8'); ?></a>

            <?php if (false): ?>
                <?php if ($event['vendor_name'] !== ''): ?>
                  <br><small><?php echo htmlspecialchars($event['vendor_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if ($event['intake_note'] !== ''): ?>
                  <br><small><?php echo htmlspecialchars($event['intake_note'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            <?php endif; ?>
          </td>

          <td <?php if ($event['status'] === 'partial') { echo 'class="fpdue"'; } ?>><?php echo date('m.d.y', strtotime($event['due_date'])); ?></td>

          <td>$<?php echo number_format((float)$event['amount'], 2); ?></td>

          <td>
            <?php
            if ($event['status'] === 'paid') {
              echo 'Paid';
            } elseif ($event['status'] === 'partial') {
              echo 'Partial';
            } else {
              echo 'Due';
            }
            ?>
          </td>

          <td>$<?php echo number_format((float)$event['covered_by_pool'], 2); ?></td>

          <td <?php if ($event['status'] === 'partial') { echo 'class="fpdue"'; } ?>>

            <?php
            $is_uncovered = (
              ((string)$event['status'] === 'partial' || (string)$event['status'] === 'due') &&
              !empty($event['default_funding_account_id']) &&
              (float)$event['remaining_due'] > 0
            );
            ?>

            <?php if ($is_uncovered && !$linked_first_uncovered): ?>
              <a href="reserve_adjustment.php?funding_account_id=<?php echo (int)$event['default_funding_account_id']; ?>&amount=<?php echo urlencode(number_format((float)$event['remaining_due'], 2, '.', '')); ?>&bill=<?php echo urlencode((string)$event['billing_name']); ?>&transaction_type=contribution">
                $<?php echo number_format((float)$event['remaining_due'], 2); ?>
              </a>
              <?php $linked_first_uncovered = true; ?>
            <?php else: ?>
              $<?php echo number_format((float)$event['remaining_due'], 2); ?>
            <?php endif; ?>

          </td>

          <td>$<?php echo number_format((float)$event['pool_remaining_after'], 2); ?></td>

          <td>
            <?php
            $can_process_now =
              ((string)$event['status'] === 'covered' || (string)$event['status'] === 'partial' || (string)$event['status'] === 'due') &&
              !empty($event['billing_account_id']) &&
              !empty($event['due_date']) &&
              empty($event['already_paid']);
            ?>

            <?php if ($is_first_occurrence_for_bill): ?>

            <?php
            $today_for_compare = new DateTime('today');
            $event_due_date = new DateTime((string)$event['due_date']);
            $event_due_date->setTime(0, 0, 0);
            $is_early_process = ($event_due_date > $today_for_compare);
            ?>
            <form method="post" class="process-now-form" style="margin:0;">
              <input type="hidden" name="process_now" value="1">
              <input type="hidden" name="ajax" value="1">
              <input type="hidden" name="billing_account_id" value="<?php echo (int)$event['billing_account_id']; ?>">
              <input type="hidden" name="due_date" value="<?php echo htmlspecialchars((string)$event['due_date'], ENT_QUOTES, 'UTF-8'); ?>">

              <button
                type="button"
                class="postnow-btn process-now-trigger"
                data-bill-name="<?php echo htmlspecialchars((string)$event['billing_name'], ENT_QUOTES, 'UTF-8'); ?>"
                data-due-date="<?php echo htmlspecialchars(date('m.d.y', strtotime((string)$event['due_date'])), ENT_QUOTES, 'UTF-8'); ?>"
                data-amount="<?php echo htmlspecialchars(number_format((float)$event['amount'], 2), ENT_QUOTES, 'UTF-8'); ?>"
                data-account="<?php echo htmlspecialchars((string)$selected_account, ENT_QUOTES, 'UTF-8'); ?>"
                data-is-early="<?php echo $is_early_process ? '1' : '0'; ?>"
              >
                <?php echo $is_early_process ? 'Process Early' : 'Process Now'; ?>
              </button>
            </form>
          <?php else: ?>
            &nbsp;
          <?php endif; ?>

          </td>

        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>
    </div>

    <?php /* grab the currently selected funding accounts ID to use in Reserve Adjustment link below */
    $current_funding_account_id = null;

    if (!empty($funding_account_meta[$selected_account])) {
      $current_funding_account_id = (int)$funding_account_meta[$selected_account]['funding_account_id'];
    }
    ?>
    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="reserve_adjustment.php<?php echo !empty($current_funding_account_id) ? '?funding_account_id=' . (int)$current_funding_account_id : ''; ?>">Reserve Adjustment</a> | 
      <a href="intake_funding-accounts.php">Add New Funding</a>
    </div>

  </div>
</div>

<div id="process-now-modal" class="confirm-modal" aria-hidden="true">
  <div class="confirm-modal__backdrop"></div>

  <div class="confirm-modal__panel" role="dialog" aria-modal="true" aria-labelledby="process-now-title">
    <button type="button" class="confirm-modal__close" id="process-now-close" aria-label="Close">
      &times;
    </button>

    <div class="confirm-modal__icon">!</div>

    <h2 id="process-now-title">Process Bill Now?</h2>

    <p class="confirm-modal__lead">
      You’re about to manually process <strong id="modal-bill-name">this bill</strong>.
    </p>

    <div class="confirm-modal__details">
      <div><span>Funding Account:</span> <strong id="modal-account-name"></strong></div>
      <div><span>Amount:</span> <strong>$<span id="modal-amount"></span></strong></div>
      <div><span>Scheduled Due Date:</span> <strong id="modal-due-date"></strong></div>
    </div>

    <p class="confirm-modal__warning" id="modal-early-warning" style="display:none;">
      This bill is scheduled for a future due date and will be processed ahead of schedule. The payment and funding deduction will be recorded today, while the scheduled due date remains part of the record.
    </p>

    <p class="confirm-modal__note">
      This will deduct funds now, record a payment, and advance the bill.
    </p>

    <div class="confirm-modal__error" id="process-now-error" style="display:none;"></div>

    <div class="confirm-modal__actions">
      <button type="button" class="btn-two" id="process-now-cancel">Cancel</button>
      <button type="button" class="btn-three confirm-modal__confirm" id="process-now-confirm">Yes, Process Now</button>
    </div>
  </div>
</div>

<?php require '_includes/footer.php'; ?>