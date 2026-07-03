<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;
$layout_context = 'projection';
$selected_account = isset($_GET['account']) ? trim($_GET['account']) : 'PayPal';

// $reconciliation = reconcile_due_bills_against_reserves($pdo_db, $user_id);
reconcile_due_bills_against_reserves($pdo_db, $user_id);

$stmt = $pdo_db->prepare("
  SELECT
    ba.billing_account_id,
    ba.billing_name,
    ba.vendor_name,
    ba.intake_note,
    ba.cadence,
    ba.reserve_style,
    ba.default_amount,
    ba.next_due_date,
    ba.actual_due_date,
    ba.renewal_term_months,
    ba.due_day_of_month,
    ba.due_month_of_year,
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

// $reserve_totals = combined_reserve_totals_by_funding_account($pdo_db, $user_id, $rows);
$reserve_totals = funding_account_pool_totals($pdo_db, $user_id);

if (!isset($reserve_totals[$selected_account])) {
  $account_names = array_keys($reserve_totals);
  $selected_account = !empty($account_names) ? $account_names[0] : 'PayPal';
}

$projection_rows = filter_rows_by_funding_account($rows, $selected_account);
// $pool_amount = combined_reserve_total_for_funding_account($pdo_db, $user_id, $rows, $selected_account);
$pool_amount = isset($reserve_totals[$selected_account]) ? (float)$reserve_totals[$selected_account] : 0.00;

$months_ahead = 12;
$events = generate_projected_bill_events($projection_rows, $months_ahead);
$projection = apply_pool_to_projected_events($events, $pool_amount);

require '_includes/header.php';
require '_includes/nav.php';
?>

<div class="intake-form">
  <div class="billing-schedule projection">

    <?php /* <h1>Billing Projection</h1> */ ?>


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
      <form class="pro-acct" method="get" style="margin-bottom: 1em;">
        <label for="account"><strong>Switch Projection Account:</strong></label>
        <select id="account" name="account" onchange="this.form.submit()">
          <?php foreach ($reserve_totals as $account_name => $amount): ?>
            <option value="<?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($selected_account === $account_name) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit">View</button></noscript>
      </form>
    <?php } ?>


<?php /*
      <?php if (!empty($reconciliation['processed_count']) || !empty($reconciliation['skipped_count'])): ?>
        <div class="success" style="display:block;">
          Processed: <?php echo (int)$reconciliation['processed_count']; ?>
          <?php if (!empty($reconciliation['skipped_count'])): ?>
            | Skipped: <?php echo (int)$reconciliation['skipped_count']; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
*/ ?>


      <div>

        <?php if ($single_fund_acct !== 'yes') { ?>

        <?php foreach ($reserve_totals as $account_name => $amount): ?>
          
            <?php if ($selected_account === $account_name) { ?>
              <div class="selected-fund">
                <i class="fas fa-star"></i>&nbsp; [selected]
                <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?> 
                $<?php echo number_format($amount, 2); ?>
                &nbsp;<i class="fas fa-star"></i>
              </div>
            <?php } else { ?>
              <div>
                <?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?> 
                $<?php echo number_format($amount, 2); ?>
              </div>
           <?php } ?>

  
        <?php endforeach; ?>

        <?php } else { ?>

              <div>
                You only have 1 funding account.
              </div>

        <?php } ?>



        <div style="display: flex;margin: 0.5em 0 0.5em;">
          <strong><?php echo htmlspecialchars((string)$selected_account, ENT_QUOTES, 'UTF-8'); ?> Reserve Used In Projection:</strong>&nbsp; $<?php echo number_format($projection['starting_pool'], 2); ?>
        </div>



      </div>



    <div class="table-container">
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

        foreach ($projection['events'] as $index => $event): 

        $row_classes = [$event['status']];

        if ($attention_index !== null && $index === $attention_index) {
          $row_classes[] = $attention_class;
        }
        /* this class goes in the next tr directly below... */
        ?>
        <tr class="<?php echo htmlspecialchars(implode(' ', $row_classes), ENT_QUOTES, 'UTF-8'); ?>">


          <td>
            <a class="editAcctLink" href="bill_details.php?billing_account_id=<?php echo (int)$event['billing_account_id']; ?>"><?php echo htmlspecialchars($event['billing_name'], ENT_QUOTES, 'UTF-8'); ?></a>

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

          <td <?php if ($event['status'] === 'partial') { echo 'class="fpdue"'; } ?>>$<?php echo number_format((float)$event['remaining_due'], 2); ?></td>

          <td>$<?php echo number_format((float)$event['pool_remaining_after'], 2); ?></td>
        </tr>
      <?php endforeach; ?>

      </tbody>
    </table>
    </div>

    <div class="inner-links">
      <a href="index.php">Dashboard</a> |
      <a href="reserve_adjustment.php">Reserve Adjustment</a> | 
      <a href="intake_funding-accounts.php">Add New Funding</a>
    </div>

  </div>
</div>

<?php require '_includes/footer.php'; ?>