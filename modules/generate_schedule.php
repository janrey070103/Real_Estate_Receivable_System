<?php
/**
 * Generate Payment Schedule
 * Real Estate Receivable System
 * 
 * Generates monthly payment schedules for a property using proper amortization
 * Supports interest rates and down payments
 * Prevents duplicate schedule generation
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';

// Require user to be logged in
require_login();

// Get property ID from request
$property_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0);

if ($property_id <= 0) {
    set_flash_message('error', 'Invalid property ID.');
    header('Location: properties.php');
    exit();
}

// Fetch property details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as client_name
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        WHERE p.property_id = ?
    ");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();

    if (!$property) {
        set_flash_message('error', 'Property not found.');
        header('Location: properties.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Fetch property error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load property data.');
    header('Location: properties.php');
    exit();
}

// Check if schedules already exist (duplicate prevention)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payment_schedules WHERE property_id = ?");
    $stmt->execute([$property_id]);
    $existing_count = $stmt->fetch()['count'];

    if ($existing_count > 0) {
        set_flash_message('warning', "Payment schedule already exists for this property ({$existing_count} schedules found). Delete existing schedules first if you want to regenerate.");
        header('Location: property_edit.php?id=' . $property_id . '#schedules');
        exit();
    }

} catch (PDOException $e) {
    error_log("Check existing schedules error: " . $e->getMessage());
    set_flash_message('error', 'Failed to check existing schedules.');
    header('Location: property_edit.php?id=' . $property_id);
    exit();
}

// Calculate amortization values
$total_price = floatval($property['total_price']);
$security_deposit = floatval($property['security_deposit'] ?? 0);
$interest_rate = floatval($property['interest_rate'] ?? 0);
$term_months = intval($property['term_months']);
$principal = $total_price; // Security deposit does NOT reduce principal

// Generate schedule preview using helper function
$contract_date = new DateTime($property['contract_date']);
$schedule_preview = generate_amortization_schedule($principal, $interest_rate, $term_months, $contract_date);

// Get amortization summary
$amort_summary = calculate_amortization($principal, $interest_rate, $term_months);

// Handle schedule generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_generate'])) {
    // CSRF token verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token. Please try again.');
        header('Location: generate_schedule.php?id=' . $property_id);
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Generate schedules using the helper function
        $schedules = generate_amortization_schedule($principal, $interest_rate, $term_months, $contract_date);
        $schedules_created = 0;
        $today = new DateTime();

        // Insert Security Deposit as schedule_number = 0 (due on contract date)
        if ($security_deposit > 0) {
            $deposit_status = (clone $contract_date < $today) ? 'overdue' : 'pending';
            $dep_stmt = $pdo->prepare("
                INSERT INTO payment_schedules (
                    property_id, schedule_number, due_date, amount_due,
                    principal_amount, interest_amount, penalty_amount, status
                ) VALUES (?, 0, ?, ?, 0, 0, 0, ?)
            ");
            $dep_stmt->execute([
                $property_id,
                $property['contract_date'],
                $security_deposit,
                $deposit_status
            ]);
        }

        foreach ($schedules as $entry) {
            // Determine status based on due date
            $due_date = new DateTime($entry['due_date']);
            $status = ($due_date < $today) ? 'overdue' : 'pending';

            // Insert payment schedule with principal/interest breakdown
            $stmt = $pdo->prepare("
                INSERT INTO payment_schedules (
                    property_id, schedule_number, due_date, amount_due,
                    principal_amount, interest_amount, penalty_amount, status
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ");

            $stmt->execute([
                $property_id,
                $entry['schedule_number'],
                $entry['due_date'],
                $entry['amount_due'],
                $entry['principal_amount'],
                $entry['interest_amount'],
                $status
            ]);

            $schedules_created++;
        }

        // Verify total amount
        $verify_stmt = $pdo->prepare("
            SELECT SUM(amount_due) as total_schedules 
            FROM payment_schedules 
            WHERE property_id = ?
        ");
        $verify_stmt->execute([$property_id]);
        $verification = $verify_stmt->fetch();

        $expected_total = $amort_summary['total_payment'];
        $actual_total = $verification['total_schedules'];

        if (abs($actual_total - $expected_total) > 1.00) {
            // Allow $1 variance for rounding
            throw new Exception('Schedule total mismatch: Expected ₱' .
                number_format($expected_total, 2) . ' but got ₱' .
                number_format($actual_total, 2));
        }

        // Commit transaction
        $pdo->commit();

        // Log the generation
        log_audit(
            $pdo,
            'GENERATE_SCHEDULES',
            'property_id:' . $property_id,
            "Generated {$schedules_created} schedules | Interest: {$interest_rate}% | Monthly: ₱" .
            number_format($amort_summary['monthly_payment'], 2)
        );

        set_flash_message('success', "Successfully generated {$schedules_created} payment schedules for '{$property['property_name']}'!");
        header('Location: property_edit.php?id=' . $property_id . '#schedules');
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Generate schedule error: " . $e->getMessage());
        set_flash_message('error', 'Failed to generate payment schedules. ' . $e->getMessage());
        header('Location: property_edit.php?id=' . $property_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Generate schedule error: " . $e->getMessage());
        set_flash_message('error', 'An error occurred while generating schedules.');
        header('Location: property_edit.php?id=' . $property_id);
        exit();
    }
}

// Set page title
$page_title = 'Generate Payment Schedule - ' . $property['property_name'];

// Include header
include '../templates/header.php';
?>

<!-- Include Navigation -->
<?php include '../templates/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="properties.php">Properties</a></li>
                    <li class="breadcrumb-item"><a
                            href="property_edit.php?id=<?php echo $property_id; ?>"><?php echo htmlspecialchars($property['property_name']); ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Generate Schedule</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <span style="color: var(--primary-blue);">⚡</span> Generate Payment Schedule
                        </h2>
                        <p class="text-muted mb-0">Create monthly payment schedules with interest calculation</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="property_edit.php?id=<?php echo $property_id; ?>" class="btn btn-outline-secondary">
                            <span>◀</span> Back to Property
                        </a>
                    </div>
                </div>
            </div>

            <!-- Confirmation Card -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <!-- Property Summary -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <span>🏘️</span> Property Information
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Property Name</h6>
                                    <p class="mb-0">
                                        <strong><?php echo htmlspecialchars($property['property_name']); ?></strong></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6 class="text-muted mb-1">Client</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($property['client_name']); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Total Price</h6>
                                    <p class="mb-0"><strong
                                            style="color: var(--primary-maroon);"><?php echo format_peso($total_price); ?></strong>
                                    </p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Security Deposit</h6>
                                    <p class="mb-0"><strong><?php echo format_peso($security_deposit); ?></strong></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Principal (Financed)</h6>
                                    <p class="mb-0"><strong
                                            style="color: var(--primary-maroon);"><?php echo format_peso($principal); ?></strong>
                                    </p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Interest Rate</h6>
                                    <p class="mb-0"><strong><?php echo $interest_rate; ?>% per annum</strong></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Payment Term</h6>
                                    <p class="mb-0"><strong><?php echo $term_months; ?> months</strong></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-muted mb-1">Contract Date</h6>
                                    <p class="mb-0"><?php echo date('F d, Y', strtotime($property['contract_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amortization Summary -->
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h6 class="text-muted mb-1">Monthly Payment</h6>
                                    <h3 style="color: var(--primary-maroon);">
                                        <?php echo format_peso($amort_summary['monthly_payment']); ?></h3>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h6 class="text-muted mb-1">Total Interest</h6>
                                    <h4 class="text-warning">
                                        <?php echo format_peso($amort_summary['total_interest']); ?></h4>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="text-muted mb-1">Total Payment</h6>
                                    <h4 class="text-success"><?php echo format_peso($amort_summary['total_payment']); ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Preview -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <span>📅</span> Payment Schedule Preview
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">First 5 payments:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Due Date</th>
                                            <th class="text-end">Payment</th>
                                            <th class="text-end">Principal</th>
                                            <th class="text-end">Interest</th>
                                            <th class="text-end">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $today = new DateTime();
                                        foreach (array_slice($schedule_preview, 0, 5) as $entry):
                                            $due_date = new DateTime($entry['due_date']);
                                            $status = ($due_date < $today) ? 'overdue' : 'pending';
                                            $badge_color = ($status === 'overdue') ? 'danger' : 'warning';
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $entry['schedule_number']; ?></strong></td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($entry['due_date'])); ?>
                                                    <span
                                                        class="badge bg-<?php echo $badge_color; ?> ms-1"><?php echo ucfirst($status); ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <strong><?php echo format_peso($entry['amount_due']); ?></strong></td>
                                                <td class="text-end"><?php echo format_peso($entry['principal_amount']); ?>
                                                </td>
                                                <td class="text-end"><?php echo format_peso($entry['interest_amount']); ?>
                                                </td>
                                                <td class="text-end"><?php echo format_peso($entry['remaining_balance']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($term_months > 5): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">
                                                    <em>... and <?php echo ($term_months - 5); ?> more payment
                                                        schedules</em>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($interest_rate > 0): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <strong>💡 Note:</strong> This uses standard amortization. Monthly payments are fixed,
                                    but the principal/interest ratio changes over time (more interest early, more principal
                                    later).
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <strong>✓ Interest-Free:</strong> Each monthly payment is equally divided (no interest
                                    charges).
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Confirmation Form -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <span>⚠️</span> Confirm Generation
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                <strong>Are you sure you want to generate <?php echo $term_months; ?> payment
                                    schedules?</strong>
                            </p>
                            <p class="text-muted mb-4">
                                This action will create all monthly payment schedules at once.
                                Make sure the property details are correct before proceeding.
                            </p>

                            <form method="POST" action="generate_schedule.php?id=<?php echo $property_id; ?>">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">

                                <div class="d-flex justify-content-between">
                                    <a href="property_edit.php?id=<?php echo $property_id; ?>"
                                        class="btn btn-outline-secondary">
                                        <span>✖</span> Cancel
                                    </a>
                                    <button type="submit" name="confirm_generate" class="btn btn-primary btn-lg">
                                        <span>⚡</span> Generate <?php echo $term_months; ?> Schedules
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php
    // Include footer
    include '../templates/footer.php';
    ?>