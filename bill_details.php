<?php
require_once 'config/initialize.php';
verify_loggedin();
require '_functions/billing_functions.php';

$pdo_db = pdo_connect();
$user_id = $_SESSION['id'] ?? 1;

$errors = [];
$billing_account_id = 0;
$bill = null;
$payments = [];
$notes = [];
$new_note_body = '';
$vendor_name = '';

if (isset($_GET['billing_account_id']) && ctype_digit((string)$_GET['billing_account_id'])) {
  $billing_account_id = (int)$_GET['billing_account_id'];
} elseif (isset($_POST['billing_account_id']) && ctype_digit((string)$_POST['billing_account_id'])) {
  $billing_account_id = (int)$_POST['billing_account_id'];
}

if ($billing_account_id < 1) {
  $errors[] = 'Billing account not found.';
} else {
  $stmt = $pdo_db->prepare("
    SELECT
      ba.*,
      fa.login_url AS fund_url,
      fa.account_name AS paid_from_account,
      tfa.account_name AS transferred_from_account
    FROM billing_accounts ba
    LEFT JOIN funding_accounts fa
      ON ba.default_funding_account_id = fa.funding_account_id
    LEFT JOIN funding_accounts tfa
      ON ba.transfer_from_funding_account_id = tfa.funding_account_id
    WHERE ba.billing_account_id = ?
      AND ba.user_id = ?
    LIMIT 1
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $bill = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bill) {
    $errors[] = 'Billing account not found.';
  }
}

if (is_post_request() && isset($_POST['add_bill_note']) && $bill) {
  $new_note_body = trim($_POST['note_body'] ?? '');

  if ($new_note_body === '') {
    $errors[] = 'Note cannot be empty.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      INSERT INTO bill_notes (
        billing_account_id,
        user_id,
        note_body,
        note_date
      ) VALUES (?, ?, ?, NOW())
    ");

    $stmt->execute([
      $billing_account_id,
      $user_id,
      $new_note_body
    ]);

    header('Location: bill_details.php?billing_account_id=' . $billing_account_id);
    exit();
  }
}

/* deleting a future scheduled override */
if (is_post_request() && isset($_POST['delete_amount_schedule']) && $bill) {
  $bill_amount_schedule_id = isset($_POST['bill_amount_schedule_id'])
    ? (int)$_POST['bill_amount_schedule_id']
    : 0;

  if ($bill_amount_schedule_id < 1) {
    $errors[] = 'Scheduled amount rule not found.';
  }

  if (!$errors) {
    $stmt = $pdo_db->prepare("
      SELECT
        bas.bill_amount_schedule_id,
        bas.billing_account_id,
        bas.effective_due_date,
        bas.amount,
        bas.adjustment_type,
        bas.note
      FROM bill_amount_schedule bas
      INNER JOIN billing_accounts ba
        ON bas.billing_account_id = ba.billing_account_id
      WHERE bas.bill_amount_schedule_id = ?
        AND bas.billing_account_id = ?
        AND ba.user_id = ?
      LIMIT 1
    ");

    $stmt->execute([
      $bill_amount_schedule_id,
      $billing_account_id,
      $user_id
    ]);

    $schedule_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule_row) {
      $errors[] = 'Scheduled amount rule not found.';
    } elseif ((string)$schedule_row['effective_due_date'] <= date('Y-m-d')) {
      $errors[] = 'Only future scheduled amount rules can be deleted.';
    }
  }

  if (!$errors) {
    $pdo_db->beginTransaction();

    try {
      $activity_details = [
        'effective_due_date' => (string)$schedule_row['effective_due_date'],
        'amount' => round((float)$schedule_row['amount'], 2),
        'adjustment_type' => (string)($schedule_row['adjustment_type'] ?? 'single')
      ];

      $stmt = $pdo_db->prepare("
        DELETE FROM bill_amount_schedule
        WHERE bill_amount_schedule_id = ?
          AND billing_account_id = ?
        LIMIT 1
      ");

      $stmt->execute([
        $bill_amount_schedule_id,
        $billing_account_id
      ]);

      $stmt = $pdo_db->prepare("
        INSERT INTO bill_activity_log (
          billing_account_id,
          user_id,
          activity_type,
          field_name,
          old_value,
          new_value,
          note,
          activity_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
      ");

      $stmt->execute([
        $billing_account_id,
        $user_id,
        'amount_schedule_deleted',
        'amount_schedule',
        json_encode($activity_details),
        null,
        !empty($schedule_row['note']) ? $schedule_row['note'] : 'Scheduled amount rule deleted.'
      ]);

      $pdo_db->commit();

      header('Location: bill_details.php?billing_account_id=' . $billing_account_id);
      exit();

    } catch (Throwable $e) {
      if ($pdo_db->inTransaction()) {
        $pdo_db->rollBack();
      }

      error_log(
        'Scheduled amount deletion failed for bill_amount_schedule_id ' .
        $bill_amount_schedule_id .
        ': ' .
        $e->getMessage()
      );

      $errors[] = 'The scheduled amount rule could not be deleted.';
    }
  }
}


if ($bill) {

  $stmt = $pdo_db->prepare("
    SELECT
      bill_payment_id,
      due_date,
      amount_due,
      amount_paid,
      date_paid,
      status,
      confirmation_note,
      created_at
    FROM bill_payments
    WHERE billing_account_id = ?
      AND user_id = ?
    ORDER BY date_paid DESC, bill_payment_id DESC
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo_db->prepare("
    SELECT
      bill_note_id,
      note_body,
      note_date
    FROM bill_notes
    WHERE billing_account_id = ?
      AND user_id = ?
    ORDER BY note_date DESC, bill_note_id DESC
  ");
  $stmt->execute([$billing_account_id, $user_id]);
  $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$next_attention = null;

if (!empty($bill['default_funding_account_id'])) {
  $next_attention = next_attention_date_for_bill(
    $pdo_db,
    $user_id,
    (int)$bill['billing_account_id'],
    (int)$bill['default_funding_account_id'],
    24
  );
}

$custom_due_events = [];

if (!empty($bill['cadence']) && $bill['cadence'] === 'custom') {
  $custom_due_events = upcoming_bill_due_schedule($pdo_db, $billing_account_id, $user_id, 24);
}

$bill_activity = bill_activity_timeline($pdo_db, $user_id, $billing_account_id);


// usort($bill_activity, function ($a, $b) {
//   if ($a['event_datetime'] === $b['event_datetime']) {
//     return strcmp((string)$a['label'], (string)$b['label']);
//   }

//   return strcmp((string)$b['event_datetime'], (string)$a['event_datetime']);
// });



$scheduled_amounts = upcoming_bill_amount_schedule($pdo_db, $billing_account_id, $user_id, 12);

require '_includes/header.php';
require '_includes/nav.php';
?>


<div class="intake-form">
  <div class="billing-schedule">

      <div class="inner-links">
        <a href="index.php">Dashboard</a> |
        <a href="billing_projection.php">Projection</a> |
        <a href="reserve_adjustment.php">Reserve Adjustment</a>
      </div>


    <h1>Bill Details</h1>

    <?php if ($errors): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($bill):
    $bill_acct_name = htmlspecialchars($bill['billing_name'], ENT_QUOTES, 'UTF-8');
    if ($bill['vendor_name']) { $vendor_name = htmlspecialchars($bill['vendor_name'], ENT_QUOTES, 'UTF-8'); }
    ?>

      <div class="success" style="display:block;">


        <strong><?= $bill_acct_name; ?></strong> <a class="bd-smtxt" href="edit_billing-account.php?billing_account_id=<?php echo $bill['billing_account_id']; ?>">Edit</a>
        <?php if (empty($bill['vendor_name']) && !empty($bill['login_url'])): ?>
          <a class="bd-smtxt" href="<?php echo htmlspecialchars((string)$bill['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            Website
          </a>
        <?php endif; ?>


        <br>

        <?php if (!empty($bill['vendor_name'])): ?>
          Vendor: <?php echo htmlspecialchars($bill['vendor_name'], ENT_QUOTES, 'UTF-8'); ?>


          <?php if (!empty($bill['login_url'])): ?>
            <a class="bd-smtxt" href="<?php echo htmlspecialchars((string)$bill['login_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
              Website
            </a>
          <?php endif; ?>


          <br>
        <?php endif; ?>

        Base Amount: $<?php echo number_format((float)$bill['default_amount'], 2); ?><br>


        <?php if (!empty($bill['actual_due_date'])): ?>
          <strong>Next Scheduled Draft:</strong>
          <?php echo date('m.d.y', strtotime($bill['actual_due_date'])); ?><br>
        <?php endif; ?>

        <strong>Next Attention Date:</strong>
        <?php if ($next_attention && !empty($next_attention['due_date'])): ?>
          <?php echo date('m.d.y', strtotime($next_attention['due_date'])); ?>
          <?php if (!empty($next_attention['remaining_due'])): ?>
            - needs $<?php echo number_format((float)$next_attention['remaining_due'], 2); ?><br>
          <?php endif; ?>
        <?php else: ?>
          Fully covered in current projection window<br>
        <?php endif; ?>

        Cadence: <?php echo htmlspecialchars((string)$bill['cadence'], ENT_QUOTES, 'UTF-8'); ?><br>


        Paid From:
        <?php echo htmlspecialchars((string)$bill['paid_from_account'], ENT_QUOTES, 'UTF-8'); ?>


        <a class="bd-smtxt" href="billing_projection.php?account=<?php echo urlencode((string)$bill['paid_from_account']); ?>">
          Projection
        </a>

        <?php if (!empty($bill['default_funding_account_id']) && !empty($bill['paid_from_account'])): ?>
          <a class="bd-smtxt" href="funding_account_ledger.php?funding_account_id=<?php echo (int)$bill['default_funding_account_id']; ?>">
            Ledger
          </a>
        <?php endif; ?>

        <?php if (!empty($bill['fund_url'])): ?>
          <a class="bd-smtxt" href="<?php echo $bill['fund_url']; ?>" target="_blank">
            Website
          </a>
        <?php endif; ?>



      </div>

      <h2>Bill Activity</h2>

      <?php if ($bill_activity): ?>
        <table class="full-width">
          <thead>
            <tr>
              <th>When</th>
              <th>Type</th>
              <th>Details</th>
              <th>Amount</th>
              <th>Note</th>
              <th>Edit Note</th>
            </tr>
          </thead>
          <tbody>


          <?php foreach ($bill_activity as $row): ?>
            <?php
            $event_type = $row['event_type'];
            if ($event_type === 'amount_schedule_deleted') {
              $event_type = 'Amount Rule Deleted';
            } else {
              $event_type = (string)($row['event_type'] ?? '');
            }

            $type_label = 'Activity';
            $details = '';
            ?>

              <tr>
                <?php /* When */ ?>
                <td>
                  <?php
                  echo !empty($row['event_datetime'])
                    ? date("m.d.y", strtotime($row['event_datetime']))
                    : '';
                  ?>
                </td>


                <?php /* Type */ ?>
                <td>
                  <?php
                  if ($event_type === 'payment') {
                    echo 'Payment';
                  } elseif ($event_type === 'payment_adjusted') {
                    echo 'Payment Adjustment';
                  } elseif ($event_type === 'created') {
                    echo 'Created';
                  } elseif ($event_type === 'updated') {
                    echo 'Edit';
                  } elseif ($event_type === 'archived') {
                    echo 'Archived';
                  } elseif ($event_type === 'amount_schedule_added') {
                    echo 'Amount Rule Added';
                  } else {
                    echo 'Activity';
                  }
                  ?>
                </td>


                <?php /* Details */ ?>
                <td>
                  <?php
                  if ($event_type === 'amount_schedule_deleted') {
                    $details_data = json_decode(
                      (string)($row['old_value'] ?? ''),
                      true
                    );

                    if (is_array($details_data)) {
                      $effective_due_date = (string)($details_data['effective_due_date'] ?? '');
                      $scheduled_amount = (float)($details_data['amount'] ?? 0);
                      $adjustment_type = (string)($details_data['adjustment_type'] ?? 'single');

                      echo '$' . number_format($scheduled_amount, 2);

                      if ($effective_due_date !== '') {
                        if ($adjustment_type === 'ongoing') {
                          echo ' beginning ' .
                            date('m.d.y', strtotime($effective_due_date)) .
                            ' and continuing forward';
                        } else {
                          echo ' for ' .
                            date('m.d.y', strtotime($effective_due_date)) .
                            ' only';
                        }
                      }
                    } else {
                      echo 'Scheduled amount rule deleted';
                    }

                  } elseif ($event_type === 'amount_schedule_added') {

                    $details_data = json_decode(
                      (string)($row['new_value'] ?? ''),
                      true
                    );

                    if (is_array($details_data)) {
                      $effective_due_date = (string)(
                        $details_data['effective_due_date'] ?? ''
                      );

                      $scheduled_amount = (float)(
                        $details_data['amount'] ?? 0
                      );

                      $adjustment_type = (string)(
                        $details_data['adjustment_type'] ?? 'single'
                      );

                      echo '$' . number_format($scheduled_amount, 2);

                      if ($effective_due_date !== '') {
                        if ($adjustment_type === 'ongoing') {
                          echo ' beginning ' .
                            date('m.d.y', strtotime($effective_due_date)) .
                            ' and continuing forward';
                        } else {
                          echo ' for ' .
                            date('m.d.y', strtotime($effective_due_date)) .
                            ' only';
                        }
                      }
                    } else {
                      echo 'Scheduled amount rule added';
                    }

                  } elseif ($event_type === 'payment_adjusted') {
                    echo '$' . number_format((float)$row['old_value'], 2);
                    echo ' &rarr; ';
                    echo '$' . number_format((float)$row['new_value'], 2);

                  } elseif ($event_type === 'created') {
                    echo 'Billing account created';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'default_amount'
                  ) {
                    echo 'Amount due: $' .
                      number_format((float)$row['old_value'], 2) .
                      ' &rarr; $' .
                      number_format((float)$row['new_value'], 2);

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'actual_due_date'
                  ) {
                    echo 'Next calendar due date: ';

                    echo !empty($row['old_value'])
                      ? date('m.d.y', strtotime((string)$row['old_value']))
                      : '—';

                    echo ' &rarr; ';

                    echo !empty($row['new_value'])
                      ? date('m.d.y', strtotime((string)$row['new_value']))
                      : '—';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'default_funding_account_id'
                  ) {
                    echo 'Paid From Account';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'login_url'
                  ) {
                    echo 'Login URL';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'vendor_name'
                  ) {
                    echo 'Vendor name';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'billing_name'
                  ) {
                    echo 'Billing account name';

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'cadence'
                  ) {
                    echo 'Cadence: ';
                    echo htmlspecialchars(
                      (string)$row['old_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    echo ' &rarr; ';
                    echo htmlspecialchars(
                      (string)$row['new_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'due_day_of_month'
                  ) {
                    echo 'Due day: ';
                    echo htmlspecialchars(
                      (string)$row['old_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    echo ' &rarr; ';
                    echo htmlspecialchars(
                      (string)$row['new_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'due_month_of_year'
                  ) {
                    echo 'Due month: ';
                    echo htmlspecialchars(
                      (string)$row['old_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    echo ' &rarr; ';
                    echo htmlspecialchars(
                      (string)$row['new_value'],
                      ENT_QUOTES,
                      'UTF-8'
                    );

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'reserve_style'
                  ) {
                    echo 'Reserve style: ';

                    echo htmlspecialchars(
                      ucwords(str_replace('_', ' ', (string)$row['old_value'])),
                      ENT_QUOTES,
                      'UTF-8'
                    );

                    echo ' &rarr; ';

                    echo htmlspecialchars(
                      ucwords(str_replace('_', ' ', (string)$row['new_value'])),
                      ENT_QUOTES,
                      'UTF-8'
                    );

                  } elseif (
                    $event_type === 'updated' &&
                    (string)$row['field_name'] === 'is_active'
                  ) {
                    echo 'Status: ';
                    echo ((string)$row['new_value'] === '1')
                      ? 'Active'
                      : 'Archived';

                  } elseif (
                    $event_type === 'updated' &&
                    !empty($row['field_name'])
                  ) {
                    echo htmlspecialchars(
                      ucwords(str_replace('_', ' ', (string)$row['field_name'])),
                      ENT_QUOTES,
                      'UTF-8'
                    );

                  } elseif ($event_type === 'payment') {
                    echo 'Payment recorded';

                  } else {
                    echo 'Activity recorded';
                  }
                  ?>
                </td>


                <td>
                  <?php if ($row['amount'] !== null): ?>
                    <?php if ((string)$row['event_source'] === 'payment'): ?>
                      <a href="adjust_bill_payment.php?bill_payment_id=<?php echo (int)$row['id']; ?>">
                        $<?php echo number_format((float)$row['amount'], 2); ?>
                      </a>
                    <?php else: ?>
                      $<?php echo number_format((float)$row['amount'], 2); ?>
                    <?php endif; ?>
                  <?php else: ?>
                    &nbsp;
                  <?php endif; ?>
                </td>







<td>
  <?php
  $note = (string)($row['note'] ?? '');
  $paid_from_account = trim((string)($row['paid_from_account'] ?? ''));

  if (
    $paid_from_account !== '' &&
    strpos($note, $paid_from_account) !== false
  ) {
    $parts = explode($paid_from_account, $note, 2);

    echo htmlspecialchars($parts[0], ENT_QUOTES, 'UTF-8');
    ?>

    <a href="billing_projection.php?account=<?php echo urlencode($paid_from_account); ?>">
      <?php echo htmlspecialchars($paid_from_account, ENT_QUOTES, 'UTF-8'); ?>
    </a>

    <?php
    echo htmlspecialchars($parts[1] ?? '', ENT_QUOTES, 'UTF-8');

  } else {
    echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
  }
  ?>
</td>










                <td style="text-align: center;">
                  <a href="edit_note.php?source=<?php echo urlencode((string)$row['event_source']); ?>&id=<?php echo (int)$row['id']; ?>">
                    <i class="fas fa-edit"></i>
                  </a>
                </td>

              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No activity recorded yet.</p>
      <?php endif; ?>






<div class="sepsec">
  <h2>Override | Exception | Scheduled Amount</h2>

  <p>
    Sometimes you discover that a future bill amount will differ from the regular base amount.
    Schedule one-off exceptions or ongoing amount changes here.
  </p>

  <div class="inner-links">
    <a
      class="btn-one"
      href="add_bill_amount_schedule.php?billing_account_id=<?php echo (int)$billing_account_id; ?>"
    >
      Add
    </a>
  </div>

  <?php if ($scheduled_amounts): ?>

    <table class="full-width">
      <thead>
        <tr>
          <th>Effective Due Date</th>
          <th>Amount</th>
          <th>Applies To</th>
          <th>Note</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($scheduled_amounts as $row): ?>
          <?php
          $adjustment_type = (string)($row['adjustment_type'] ?? 'single');

          $type_label = $adjustment_type === 'ongoing'
            ? 'This and future occurrences'
            : 'This occurrence only';

          $is_future_schedule = (string)$row['effective_due_date'] > date('Y-m-d');
          ?>

          <tr>
            <td>
              <?php echo date(
                'm.d.y',
                strtotime((string)$row['effective_due_date'])
              ); ?>
            </td>

            <td>
              $<?php echo number_format((float)$row['amount'], 2); ?>
            </td>

            <td>
              <?php echo htmlspecialchars(
                $type_label,
                ENT_QUOTES,
                'UTF-8'
              ); ?>
            </td>

            <td>
              <?php echo htmlspecialchars(
                (string)($row['note'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
              ); ?>
            </td>


            <td>
              <?php if ($is_future_schedule): ?>
                <button
                  type="button"
                  class="delete-amount-schedule-trigger postnow-btn"
                  data-schedule-id="<?php echo (int)$row['bill_amount_schedule_id']; ?>"
                  data-effective-date="<?php echo htmlspecialchars(date('m.d.y', strtotime((string)$row['effective_due_date'])), ENT_QUOTES, 'UTF-8'); ?>"
                  data-amount="<?php echo htmlspecialchars(number_format((float)$row['amount'], 2), ENT_QUOTES, 'UTF-8'); ?>"
                  data-applies-to="<?php echo htmlspecialchars($type_label, ENT_QUOTES, 'UTF-8'); ?>"
                  data-note="<?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                >
                  Delete
                </button>
              <?php else: ?>
                Already effective
              <?php endif; ?>
            </td>




          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No scheduled amount overrides yet.</p>
  <?php endif; ?>

</div>










<?php if ((string)$bill['cadence'] === 'custom'): ?>
  <div class="diff-bgc">

      <h2>Scheduled Due Events</h2>

      <div class="inner-links">
        <a href="add_bill_due_schedule.php?billing_account_id=<?php echo (int)$billing_account_id; ?>">
          Add Due Event
        </a>
      </div>

      <?php if ($custom_due_events): ?>
        <table class="full-width">
          <thead>
            <tr>
              <th>Due Date</th>
              <th>Amount</th>
              <th>Note</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($custom_due_events as $row): ?>
              <tr>
                <td>
                  <?php echo date(
                    'm.d.y',
                    strtotime((string)$row['due_date'])
                  ); ?>
                </td>

                <td>
                  $<?php echo number_format((float)$row['amount'], 2); ?>
                </td>

                <td>
                  <?php echo htmlspecialchars(
                    (string)$row['note'],
                    ENT_QUOTES,
                    'UTF-8'
                  ); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No scheduled due events yet.</p>
      <?php endif; ?>

  </div>
<?php endif; ?>









      <h2>General Notes</h2>

      <form method="post" style="margin-bottom: 1.5em;">
        <input type="hidden" name="add_bill_note" value="1">
        <input type="hidden" name="billing_account_id" value="<?php echo (int)$bill['billing_account_id']; ?>">

        <div class="row standalone">
          <label for="note_body">Add Note</label>
          <textarea
            id="note_body"
            name="note_body"
            rows="4"
            style="width: 100%; padding: 0.75em; font-size: 1em;"
          ><?php echo htmlspecialchars($new_note_body, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit">Save Note</button>
      </form>

      <?php if ($notes): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($notes as $note): ?>
              <tr>

                <td>
                  <?php
                  echo !empty($note['note_date'])
                    ? date("m.d.y \\a\\t H:i", strtotime($note['note_date']))
                    : '';
                  ?>
                </td>

                <td><?php echo nl2br(htmlspecialchars((string)$note['note_body'], ENT_QUOTES, 'UTF-8')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No general notes yet.</p>
      <?php endif; ?>

      <div class="inner-links">
        <a href="index.php">Dashboard</a> |
        <a href="billing_projection.php">Projection</a> |
        <a href="reserve_adjustment.php">Reserve Adjustment</a>
      </div>
    <?php endif; ?>

  </div>
</div>



<?php /* modal for deleting scheduled override */ ?>
<div class="confirm-modal" id="delete-amount-schedule-modal" aria-hidden="true">
  <div class="confirm-modal__backdrop"></div>

  <div class="confirm-modal__panel" role="dialog" aria-modal="true" aria-labelledby="delete-amount-schedule-title">
    <button type="button" class="confirm-modal__close" id="delete-amount-schedule-close">
      &times;
    </button>

    <h2 id="delete-amount-schedule-title">
      Delete Scheduled Amount?
    </h2>

    <p>
      This will remove the future scheduled amount rule. This does not affect past payments.
    </p>

    <div class="confirm-modal__details">
      <div>
        <strong>Effective Date:</strong>
        <span id="delete-schedule-effective-date"></span>
      </div>

      <div>
        <strong>Amount:</strong>
        $<span id="delete-schedule-amount"></span>
      </div>

      <div>
        <strong>Applies To:</strong>
        <span id="delete-schedule-applies-to"></span>
      </div>

      <div id="delete-schedule-note-wrap" style="display:none;">
        <strong>Note:</strong>
        <span id="delete-schedule-note"></span>
      </div>
    </div>

    <form method="post" id="delete-amount-schedule-form">
      <input type="hidden" name="delete_amount_schedule" value="1">
      <input type="hidden" name="billing_account_id" value="<?php echo (int)$billing_account_id; ?>">
      <input type="hidden" name="bill_amount_schedule_id" id="delete-bill-amount-schedule-id" value="">

      <div class="confirm-modal__actions">
        <button type="button" class="btn-two" id="delete-amount-schedule-cancel">
          Cancel
        </button>

        <button type="submit" class="btn-three">
          Yes, Delete It
        </button>
      </div>
    </form>
  </div>
</div>








<?php require '_includes/footer.php'; ?>