<?php
/**
 * Notifications Management Page
 * Simulates SMS and Email reminders for payment schedules
 * No internet required - all notifications stored locally
 */

// Include authentication
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit();
}

// Set page title
$page_title = 'Notifications & Reminders';

// Handle "Mark as Sent" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_sent'])) {
    $notif_id = (int) $_POST['notif_id'];

    try {
        $pdo->beginTransaction();

        // Update notification status
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET status = 'sent', sent_at = NOW() 
            WHERE notif_id = ?
        ");
        $stmt->execute([$notif_id]);

        // If it's an email, save as .txt file
        $email_stmt = $pdo->prepare("
            SELECT n.*, c.name as client_name, c.email as client_email
            FROM notifications n
            INNER JOIN clients c ON n.client_id = c.client_id
            WHERE n.notif_id = ? AND n.type = 'email'
        ");
        $email_stmt->execute([$notif_id]);
        $notification = $email_stmt->fetch();

        if ($notification) {
            // Create emails directory if it doesn't exist
            $upload_dir = '../uploads/emails/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate email file
            $filename = 'email_' . $notif_id . '_' . date('YmdHis') . '.txt';
            $filepath = $upload_dir . $filename;

            $email_content = "================================================\n";
            $email_content .= "OUTGOING EMAIL NOTIFICATION\n";
            $email_content .= "================================================\n\n";
            $email_content .= "Date: " . date('F d, Y h:i A') . "\n";
            $email_content .= "To: " . $notification['client_name'] . " <" . $notification['client_email'] . ">\n";
            $email_content .= "From: Real Estate Receivable System <noreply@rers.com>\n";
            $email_content .= "Subject: Payment Reminder\n\n";
            $email_content .= "------------------------------------------------\n";
            $email_content .= "MESSAGE:\n";
            $email_content .= "------------------------------------------------\n\n";
            $email_content .= $notification['message'] . "\n\n";
            $email_content .= "------------------------------------------------\n";
            $email_content .= "This is a simulated email for demonstration.\n";
            $email_content .= "No actual email was sent.\n";
            $email_content .= "================================================\n";

            file_put_contents($filepath, $email_content);
        }

        $pdo->commit();
        set_flash_message('success', 'Notification marked as sent successfully!');

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Mark notification sent error: " . $e->getMessage());
        set_flash_message('error', 'Failed to mark notification as sent.');
    }

    header('Location: notifications.php');
    exit();
}

// Handle "Generate Reminders" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_reminders'])) {
    try {
        $pdo->beginTransaction();

        // Find payment schedules that are due or overdue and don't have recent reminders
        $stmt = $pdo->query("
            SELECT 
                ps.schedule_id,
                ps.due_date,
                ps.amount_due,
                ps.status,
                p.property_name,
                p.client_id,
                c.name as client_name,
                c.email,
                c.contact_no,
                DATEDIFF(ps.due_date, CURDATE()) as days_until_due,
                DATEDIFF(CURDATE(), ps.due_date) as days_overdue
            FROM payment_schedules ps
            INNER JOIN properties p ON ps.property_id = p.property_id
            LEFT JOIN clients c ON p.client_id = c.client_id
            WHERE ps.status IN ('pending', 'overdue')
            AND (
                ps.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)  -- Due within 7 days
                OR ps.due_date < CURDATE()  -- Already overdue
            )
            AND NOT EXISTS (
                SELECT 1 FROM notifications n
                WHERE n.client_id = c.client_id
                AND DATE(n.date_created) = CURDATE()
                AND n.message LIKE CONCAT('%', p.property_name, '%')
            )
        ");

        $schedules = $stmt->fetchAll();
        $count_sms = 0;
        $count_email = 0;

        foreach ($schedules as $schedule) {
            // Determine message type
            if ($schedule['days_overdue'] > 0) {
                $message_type = 'OVERDUE';
                $message = "URGENT: Your payment for {$schedule['property_name']} is OVERDUE by {$schedule['days_overdue']} day(s). ";
                $message .= "Amount Due: ₱" . number_format($schedule['amount_due'], 2) . ". ";
                $message .= "Please settle your payment immediately.";
            } else {
                $message_type = 'DUE SOON';
                $message = "Reminder: Your payment for {$schedule['property_name']} is due on " . date('F d, Y', strtotime($schedule['due_date'])) . ". ";
                $message .= "Amount Due: ₱" . number_format($schedule['amount_due'], 2) . ". ";
                $message .= "Thank you for your prompt payment.";
            }

            // Insert SMS notification
            if (!empty($schedule['contact_no'])) {
                $sms_stmt = $pdo->prepare("
                    INSERT INTO notifications (client_id, message, type, status, date_created)
                    VALUES (?, ?, 'sms', 'pending', NOW())
                ");
                $sms_stmt->execute([
                    $schedule['client_id'],
                    $message
                ]);
                $count_sms++;
            }

            // Insert Email notification
            if (!empty($schedule['email'])) {
                $email_stmt = $pdo->prepare("
                    INSERT INTO notifications (client_id, message, type, status, date_created)
                    VALUES (?, ?, 'email', 'pending', NOW())
                ");
                $email_stmt->execute([
                    $schedule['client_id'],
                    $message
                ]);
                $count_email++;
            }
        }

        $pdo->commit();

        if ($count_sms > 0 || $count_email > 0) {
            // Log notification generation
            log_audit($pdo, 'GENERATE_NOTIFICATIONS', 'count:' . ($count_sms + $count_email), "Generated {$count_sms} SMS and {$count_email} email notifications");
            set_flash_message('success', "Generated {$count_sms} SMS and {$count_email} Email reminders successfully!");
        } else {
            set_flash_message('info', 'No new reminders needed at this time.');
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Generate reminders error: " . $e->getMessage());
        set_flash_message('error', 'Failed to generate reminders.');
    }

    header('Location: notifications.php');
    exit();
}

// Fetch pending notifications
try {
    $pending_stmt = $pdo->query("
        SELECT 
            n.*,
            c.name as client_name,
            c.email as client_email,
            c.contact_no as client_phone
        FROM notifications n
        INNER JOIN clients c ON n.client_id = c.client_id
        WHERE n.status = 'pending'
        ORDER BY n.date_created DESC
    ");
    $pending_notifications = $pending_stmt->fetchAll();

    // Fetch sent notifications (recent)
    $sent_stmt = $pdo->query("
        SELECT 
            n.*,
            c.name as client_name,
            c.email as client_email,
            c.contact_no as client_phone
        FROM notifications n
        INNER JOIN clients c ON n.client_id = c.client_id
        WHERE n.status = 'sent'
        ORDER BY n.sent_at DESC
        LIMIT 50
    ");
    $sent_notifications = $sent_stmt->fetchAll();

    // Get statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
            COUNT(CASE WHEN status = 'pending' AND type = 'sms' THEN 1 END) as pending_sms,
            COUNT(CASE WHEN status = 'pending' AND type = 'email' THEN 1 END) as pending_email
        FROM notifications
    ");
    $stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    error_log("Notifications fetch error: " . $e->getMessage());
    $pending_notifications = [];
    $sent_notifications = [];
    $stats = ['pending_count' => 0, 'sent_count' => 0, 'pending_sms' => 0, 'pending_email' => 0];
}

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <!-- Page Header -->
                    <div class="page-header mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-0"><span>🔔</span> Notifications & Reminders</h2>
                                <p class="text-muted mb-0">Simulated SMS and Email payment reminders</p>
                            </div>
                            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                <form method="POST" action="notifications.php" class="d-inline">
                                    <button type="submit" name="generate_reminders" class="btn btn-primary">
                                        <span>⚡</span> Generate New Reminders
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php
                    $flash = get_flash_message();
                    if ($flash):
                        $alert_class = 'alert-info';
                        if ($flash['type'] === 'success')
                            $alert_class = 'alert-success';
                        if ($flash['type'] === 'error')
                            $alert_class = 'alert-danger';
                        if ($flash['type'] === 'warning')
                            $alert_class = 'alert-warning';
                        ?>
                        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                            <strong><?php echo ucfirst($flash['type']); ?>!</strong>
                            <?php echo htmlspecialchars($flash['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Pending Reminders</p>
                                            <h4 class="mb-0 text-warning"><?php echo $stats['pending_count']; ?></h4>
                                            <small class="text-muted">Awaiting Send</small>
                                        </div>
                                        <div class="fs-1 text-warning opacity-25">📤</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Sent Reminders</p>
                                            <h4 class="mb-0 text-success"><?php echo $stats['sent_count']; ?></h4>
                                            <small class="text-muted">Successfully Sent</small>
                                        </div>
                                        <div class="fs-1 text-success opacity-25">✓</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Pending SMS</p>
                                            <h4 class="mb-0 text-info"><?php echo $stats['pending_sms']; ?></h4>
                                            <small class="text-muted">Text Messages</small>
                                        </div>
                                        <div class="fs-1 text-info opacity-25">📱</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1 small">Pending Email</p>
                                            <h4 class="mb-0 text-primary"><?php echo $stats['pending_email']; ?></h4>
                                            <small class="text-muted">Email Messages</small>
                                        </div>
                                        <div class="fs-1 text-primary opacity-25">📧</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outgoing Reminders (Pending) -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning bg-opacity-10">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><span>📤</span> <strong>Outgoing Reminders</strong> (Pending)</span>
                                <span class="badge bg-warning"><?php echo count($pending_notifications); ?>
                                    pending</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($pending_notifications) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60px;">ID</th>
                                                <th style="width: 80px;">Type</th>
                                                <th>Client</th>
                                                <th style="width: 150px;">Contact</th>
                                                <th>Message</th>
                                                <th style="width: 140px;">Created</th>
                                                <th style="width: 120px;" class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_notifications as $notif): ?>
                                                <tr>
                                                    <td><small><?php echo $notif['notif_id']; ?></small></td>
                                                    <td>
                                                        <?php if ($notif['type'] === 'sms'): ?>
                                                            <span class="badge bg-info">📱 SMS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">📧 Email</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($notif['client_name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php
                                                            if ($notif['type'] === 'sms') {
                                                                echo htmlspecialchars($notif['client_phone']);
                                                            } else {
                                                                echo htmlspecialchars($notif['client_email']);
                                                            }
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($notif['message']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small
                                                            class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notif['date_created'])); ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST" action="notifications.php" class="d-inline">
                                                            <input type="hidden" name="notif_id"
                                                                value="<?php echo $notif['notif_id']; ?>">
                                                            <button type="submit" name="mark_sent"
                                                                class="btn btn-success btn-sm"
                                                                onclick="return confirm('Mark this notification as sent?')">
                                                                <span>✓</span> Mark Sent
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="fs-1 text-muted mb-3">📭</div>
                                    <h5 class="text-muted">No Pending Reminders</h5>
                                    <p class="text-muted">
                                        Click "Generate New Reminders" to create notifications for due/overdue payments.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sent Reminders -->
                    <div class="card">
                        <div class="card-header bg-success bg-opacity-10">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><span>✓</span> <strong>Sent Reminders</strong> (Recent)</span>
                                <span class="badge bg-success"><?php echo count($sent_notifications); ?> sent</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($sent_notifications) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60px;">ID</th>
                                                <th style="width: 80px;">Type</th>
                                                <th>Client</th>
                                                <th style="width: 150px;">Contact</th>
                                                <th>Message</th>
                                                <th style="width: 140px;">Sent At</th>
                                                <th style="width: 80px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sent_notifications as $notif): ?>
                                                <tr class="table-success table-success-light">
                                                    <td><small><?php echo $notif['notif_id']; ?></small></td>
                                                    <td>
                                                        <?php if ($notif['type'] === 'sms'): ?>
                                                            <span class="badge bg-info bg-opacity-75">📱 SMS</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary bg-opacity-75">📧 Email</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($notif['client_name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php
                                                            if ($notif['type'] === 'sms') {
                                                                echo htmlspecialchars($notif['client_phone']);
                                                            } else {
                                                                echo htmlspecialchars($notif['client_email']);
                                                            }
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($notif['message']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small
                                                            class="text-muted"><?php echo $notif['sent_at'] ? date('M d, Y h:i A', strtotime($notif['sent_at'])) : 'N/A'; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Sent</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="fs-1 text-muted mb-3">📬</div>
                                    <h5 class="text-muted">No Sent Reminders Yet</h5>
                                    <p class="text-muted">
                                        Sent notifications will appear here.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Information Box -->
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading"><span>ℹ️</span> About Simulated Notifications</h6>
                        <ul class="mb-0">
                            <li><strong>No Internet Required:</strong> All notifications are simulated and stored
                                locally in
                                the database.</li>
                            <li><strong>Email Files:</strong> When you mark an email as "sent", a .txt file is saved in
                                <code>/uploads/emails/</code> for panelists to review.
                            </li>
                            <li><strong>SMS Messages:</strong> SMS notifications are recorded in the database but no
                                actual
                                text messages are sent.</li>
                            <li><strong>Auto-Generation:</strong> Click "Generate New Reminders" to create notifications
                                for
                                payments due within 7 days or already overdue.</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>