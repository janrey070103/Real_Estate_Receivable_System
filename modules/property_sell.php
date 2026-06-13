<?php
// Clear OpCache for this file during development
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

/**
 * Property Sale Workflow - Phase 1 Implementation
 * Real Estate Receivable System
 * 
 * Enforces "Property → Compute → Contract" workflow
 * Prevents saving contract without pre-validated calculation
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/financial_helpers.php';
require_once '../includes/validation_helpers.php';

require_login();

$page_title = 'Sell Property';
$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$errors = [];

// Fetch property details
try {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE property_id = ?");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();
    
    if (!$property) {
        set_flash_message('error', 'Property not found.');
        header('Location: properties.php');
        exit();
    }
    
    // Check if property is already sold
    if (!empty($property['client_id'])) {
        set_flash_message('error', 'This property is already sold.');
        header('Location: properties.php');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Fetch property error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load property.');
    header('Location: properties.php');
    exit();
}

// Fetch all clients for dropdown (only approved clients)
try {
    $clients_stmt = $pdo->query("SELECT client_id, name, account_status FROM clients WHERE account_status = 'approved' ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch clients error: " . $e->getMessage());
    $clients = [];
}

// Get pre-selected client from URL (from inquiry workflow)
$preselected_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$inquiry_id = isset($_GET['inquiry_id']) ? (int)$_GET['inquiry_id'] : 0;

// Fetch pre-selected client details if provided
$preselected_client = null;
if ($preselected_client_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT client_id, name, email, contact_no, account_status FROM clients WHERE client_id = ?");
        $stmt->execute([$preselected_client_id]);
        $preselected_client = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Fetch preselected client error: " . $e->getMessage());
    }
}

// Initialize form variables
$client_id = $preselected_client_id > 0 ? $preselected_client_id : '';
$total_price = $property['total_price'];
$security_deposit = 0;
$interest_rate = 0;
$term_months = $property['term_months'];
$contract_date = date('Y-m-d');
$computed = false;

// Handle form submission (after computation is approved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_sale'])) {
    // CSRF verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Get and validate input
        $client_id = sanitize_input($_POST['client_id'] ?? '');
        $total_price = sanitize_input($_POST['total_price'] ?? '');
        $security_deposit = sanitize_input($_POST['security_deposit'] ?? '0');
        $interest_rate = sanitize_input($_POST['interest_rate'] ?? '0');
        $term_months = sanitize_input($_POST['term_months'] ?? '');
        $contract_date = sanitize_input($_POST['contract_date'] ?? '');
        $schedule_computed = isset($_POST['schedule_computed']) && $_POST['schedule_computed'] === '1';
        
        // Validation
        if (empty($client_id) || !is_numeric($client_id)) {
            $errors['client_id'] = 'Please select a client.';
        }
        
        if (empty($total_price) || $total_price <= 0) {
            $errors['total_price'] = 'Total price must be greater than zero.';
        }
        
        if ($security_deposit < 0) {
            $errors['security_deposit'] = 'Security deposit cannot be negative.';
        }
        
        if ($interest_rate < 0 || $interest_rate > 50) {
            $errors['interest_rate'] = 'Interest rate must be between 0 and 50%.';
        }
        
        if (empty($term_months) || $term_months < 1 || $term_months > 360) {
            $errors['term_months'] = 'Term must be between 1 and 360 months.';
        }
        
        if (empty($contract_date)) {
            $errors['contract_date'] = 'Contract date is required.';
        }
        
        // Critical: Ensure schedule was computed before saving
        if (!$schedule_computed) {
            $errors['general'] = 'You must compute and preview the payment schedule before saving.';
        }
        
        // If no errors, finalize the sale
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Update property with client and financial terms
                $update_stmt = $pdo->prepare("
                    UPDATE properties 
                    SET client_id = ?, total_price = ?, security_deposit = ?, 
                        interest_rate = ?, term_months = ?, contract_date = ?, status = 'sold'
                    WHERE property_id = ? AND client_id IS NULL
                ");
                $update_stmt->execute([
                    $client_id, $total_price, $security_deposit,
                    $interest_rate, $term_months, $contract_date, $property_id
                ]);
                
                if ($update_stmt->rowCount() === 0) {
                    throw new Exception('Property update failed or property already sold.');
                }
                
                // Security deposit is separate from principal — full price is financed
                $principal = $total_price; // NOT reduced by security deposit
                $contract_date_obj = new DateTime($contract_date);
                $schedules = generate_amortization_schedule($principal, $interest_rate, $term_months, $contract_date_obj);
                $today = new DateTime();
                
                // Insert Security Deposit as schedule_number = 0 (due on contract date)
                if ($security_deposit > 0) {
                    $deposit_status = (new DateTime($contract_date) < $today) ? 'overdue' : 'pending';
                    $deposit_stmt = $pdo->prepare("
                        INSERT INTO payment_schedules (
                            property_id, schedule_number, due_date, amount_due,
                            principal_amount, interest_amount, penalty_amount, status
                        ) VALUES (?, 0, ?, ?, 0, 0, 0, ?)
                    ");
                    $deposit_stmt->execute([
                        $property_id,
                        $contract_date,
                        $security_deposit,
                        $deposit_status
                    ]);
                }

                // Insert regular amortization schedules (1..N)
                foreach ($schedules as $entry) {
                    $due_date = new DateTime($entry['due_date']);
                    $status = ($due_date < $today) ? 'overdue' : 'pending';
                    
                    $sched_stmt = $pdo->prepare("
                        INSERT INTO payment_schedules (
                            property_id, schedule_number, due_date, amount_due,
                            principal_amount, interest_amount, penalty_amount, status
                        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?)
                    ");
                    $sched_stmt->execute([
                        $property_id,
                        $entry['schedule_number'],
                        $entry['due_date'],
                        $entry['amount_due'],
                        $entry['principal_amount'],
                        $entry['interest_amount'],
                        $status
                    ]);
                }
                
                // Update inquiry status to 'converted' if this sale came from inquiry
                if ($inquiry_id > 0) {
                    $inquiry_stmt = $pdo->prepare("UPDATE inquiries SET status = 'converted' WHERE inquiry_id = ?");
                    $inquiry_stmt->execute([$inquiry_id]);
                }
                
                $pdo->commit();
                
                // Log the sale
                log_audit($pdo, 'PROPERTY_SOLD', "property_id:{$property_id},client_id:{$client_id}",
                         "Property '{$property['property_name']}' sold to client #{$client_id}, Security Deposit: ₱" . number_format($security_deposit, 2));
                
                set_flash_message('success', "Property '{$property['property_name']}' successfully sold!");
                header('Location: property_edit.php?id=' . $property_id);
                exit();
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Property sale error: " . $e->getMessage());
                $errors['general'] = 'Failed to complete sale: ' . $e->getMessage();
            }
        }
    }
}

include '../templates/header.php';
?>

<style>
    /* Force proper layout for property_sell page */
    .main-wrapper {
        width: 100% !important;
        max-width: none !important;
    }
    .main-content {
        width: 100% !important;
        max-width: none !important;
    }
    .container-fluid {
        width: 100% !important;
        max-width: none !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    /* Make form column full width when preview is hidden */
    #formColumn {
        transition: all 0.3s ease;
    }
    /* Override any max-width on cards */
    .card {
        max-width: none !important;
        width: 100% !important;
    }
    /* Ensure row takes full width */
    .row {
        max-width: none !important;
        width: 100% !important;
    }
</style>

<?php include '../templates/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="main-content" style="width: 100%; max-width: none; min-height: calc(100vh - 200px);">
        <div style="width: 100%; max-width: none; padding-left: 30px; padding-right: 30px;">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="properties.php">Properties</a></li>
                    <li class="breadcrumb-item active">Sell Property</li>
                </ol>
            </nav>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>
                            <span style="color: var(--primary-blue);">🏷️</span> Sell Property
                        </h2>
                        <p class="text-muted mb-0">Property → Compute Terms → Preview Schedule → Sign Contract</p>
                        <?php if ($preselected_client): ?>
                            <div class="alert alert-info mt-2 mb-0">
                                <strong>📋 From Inquiry:</strong> Selling to 
                                <strong><?php echo htmlspecialchars($preselected_client['name']); ?></strong>
                                (<?php echo htmlspecialchars($preselected_client['email']); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if ($inquiry_id > 0): ?>
                            <a href="inquiry_process.php?id=<?php echo $inquiry_id; ?>" class="btn btn-outline-secondary me-2">◀ Back to Inquiry</a>
                        <?php endif; ?>
                        <a href="properties.php" class="btn btn-outline-secondary">◀ Cancel</a>
                    </div>
                </div>
            </div>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <?php
            $flash = get_flash_message();
            if ($flash):
                $alert_class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
            ?>
                <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row" style="max-width: none !important; width: 100% !important;">
                <!-- Left Column: Property & Computation Form -->
                <div id="formColumn" class="col-lg-8" style="max-width: none !important;">
                    <!-- Property Info Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <span>🏘️</span> Selected Property
                        </div>
                        <div class="card-body">
                            <h4><?php echo htmlspecialchars($property['property_name']); ?></h4>
                            <p class="text-muted mb-0">Property ID: #<?php echo $property_id; ?></p>
                        </div>
                    </div>
                    
                    <!-- Computation Form -->
                    <form id="saleForm" method="POST" action="property_sell.php?property_id=<?php echo $property_id; ?><?php echo $inquiry_id > 0 ? '&inquiry_id=' . $inquiry_id : ''; ?>" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" id="schedule_computed" name="schedule_computed" value="0">
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <span>📝</span> Sale Terms
                            </div>
                            <div class="card-body">
                                <!-- Client Selection -->
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <?php if ($preselected_client && $preselected_client['account_status'] === 'approved'): ?>
                                        <!-- Pre-selected client from inquiry -->
                                        <input type="hidden" name="client_id" value="<?php echo $preselected_client['client_id']; ?>">
                                        <div class="form-control-plaintext bg-light border rounded p-2">
                                            <strong><?php echo htmlspecialchars($preselected_client['name']); ?></strong>
                                            <span class="badge bg-success ms-2">✓ From Registration</span><br>
                                            <small class="text-muted">
                                                📧 <?php echo htmlspecialchars($preselected_client['email']); ?> • 
                                                📱 <?php echo htmlspecialchars($preselected_client['contact_no']); ?>
                                            </small>
                                        </div>
                                    <?php elseif ($preselected_client && $preselected_client['account_status'] === 'pending'): ?>
                                        <!-- Client pending approval -->
                                        <div class="alert alert-warning">
                                            <strong>⏳ Client Pending Approval</strong><br>
                                            <strong><?php echo htmlspecialchars($preselected_client['name']); ?></strong> is registered but not yet approved.
                                            <a href="client_approvals.php?filter=pending" target="_blank" class="btn btn-sm btn-warning mt-2">
                                                Go to Approval Page
                                            </a>
                                        </div>
                                        <select class="form-select <?php echo isset($errors['client_id']) ? 'is-invalid' : ''; ?>" 
                                                id="client_id" name="client_id" required>
                                            <option value="">-- Or select another approved client --</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?php echo $client['client_id']; ?>">
                                                    <?php echo htmlspecialchars($client['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <!-- Normal client selection -->
                                        <select class="form-select <?php echo isset($errors['client_id']) ? 'is-invalid' : ''; ?>" 
                                                id="client_id" name="client_id" required>
                                            <option value="">-- Select Client --</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?php echo $client['client_id']; ?>"
                                                        <?php echo ($client_id == $client['client_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($client['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <?php if (isset($errors['client_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['client_id']; ?></div>
                                    <?php endif; ?>
                                    <?php if (!$preselected_client): ?>
                                        <small class="text-muted">Or <a href="client_add.php" target="_blank">create new client</a></small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="total_price" class="form-label">Total Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" class="form-control" id="total_price" name="total_price" 
                                                   value="<?php echo $total_price; ?>" step="0.01" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="security_deposit" class="form-label">Security Deposit</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" class="form-control <?php echo isset($errors['security_deposit']) ? 'is-invalid' : ''; ?>" id="security_deposit" name="security_deposit" 
                                                   value="<?php echo $security_deposit; ?>" step="0.01" min="0">
                                        </div>
                                        <?php if (isset($errors['security_deposit'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['security_deposit']; ?></div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="interest_rate" class="form-label">Annual Interest Rate</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="interest_rate" name="interest_rate" 
                                                   value="<?php echo $interest_rate; ?>" step="0.01" min="0" max="50">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="term_months" class="form-label">Payment Term <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="term_months" name="term_months" 
                                                   value="<?php echo $term_months; ?>" min="1" max="360" required>
                                            <span class="input-group-text">months</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contract_date" class="form-label">Contract Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="contract_date" name="contract_date" 
                                           value="<?php echo $contract_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <strong>⚠️ Important:</strong> Click "Calculate Schedule" to preview payment terms before saving.
                                </div>
                                
                                <button type="button" id="btnCalculate" class="btn btn-info btn-lg w-100">
                                    🧮 Calculate Schedule
                                </button>
                            </div>
                        </div>
                        
                        <!-- Save Contract Button (Initially Disabled) -->
                        <button type="submit" name="finalize_sale" id="btnSaveContract" class="btn btn-success btn-lg w-100" disabled>
                            ✓ Save Contract & Generate Schedules
                        </button>
                    </form>
                </div>
                
                <!-- Right Column: Preview & Summary -->
                <div id="previewColumn" class="col-lg-4" style="max-width: none !important;">
                    <!-- Placeholder visible by default -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <span>ℹ️</span> Schedule Preview
                        </div>
                        <div class="card-body">
                            <p class="text-muted text-center py-4">
                                Click "Calculate Schedule" to preview payment terms here.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Actual preview container (shown after calculation) -->
                    <div id="previewContainer" class="card" style="display:none;">
                        <div class="card-header bg-success text-white">
                            <span>✓</span> Schedule Preview
                        </div>
                        <div class="card-body">
                            <h5>Financial Summary</h5>
                            <table class="table table-sm">
                                <tr class="table-info">
                                    <td>🔐 Security Deposit:</td>
                                    <td class="text-end"><strong id="preview_deposit">₱0.00</strong></td>
                                </tr>
                                <tr>
                                    <td>Property Amount:</td>
                                    <td class="text-end"><strong id="preview_principal">₱0.00</strong></td>
                                </tr>
                                <tr>
                                    <td>Monthly Payment:</td>
                                    <td class="text-end"><strong id="preview_monthly">₱0.00</strong></td>
                                </tr>
                                <tr>
                                    <td>Total Interest:</td>
                                    <td class="text-end text-warning"><strong id="preview_interest">₱0.00</strong></td>
                                </tr>
                                <tr class="table-light">
                                    <td><strong>Total Payment (incl. deposit):</strong></td>
                                    <td class="text-end"><strong id="preview_total">₱0.00</strong></td>
                                </tr>
                            </table>
                            
                            <h6 class="mt-3">Schedule Preview</h6>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="schedulePreviewTable">
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-success mt-3 mb-0">
                                <strong>✓ Ready to Save:</strong> Schedule looks good? Click "Save Contract" to finalize.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main-wrapper closed by footer.php -->

<!-- JavaScript for Real-time Calculation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCalculate = document.getElementById('btnCalculate');
    const btnSaveContract = document.getElementById('btnSaveContract');
    const previewContainer = document.getElementById('previewContainer');
    const scheduleComputedInput = document.getElementById('schedule_computed');
    
    btnCalculate.addEventListener('click', function() {
        // Get form values
        const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;
        const securityDeposit = parseFloat(document.getElementById('security_deposit').value) || 0;
        const interestRate = parseFloat(document.getElementById('interest_rate').value) || 0;
        const termMonths = parseInt(document.getElementById('term_months').value) || 0;
        const contractDate = document.getElementById('contract_date').value;
        
        // Validation
        if (totalPrice <= 0 || termMonths <= 0 || !contractDate) {
            alert('Please fill in all required fields (Total Price, Term, Contract Date).');
            return;
        }
        
        // Security deposit does NOT reduce principal — full price is financed
        const principal = totalPrice;
        let monthlyPayment, totalInterest, totalPayment;
        
        if (interestRate <= 0) {
            monthlyPayment = principal / termMonths;
            totalInterest = 0;
            totalPayment = securityDeposit + principal;
        } else {
            const monthlyRate = (interestRate / 100) / 12;
            const numerator = monthlyRate * Math.pow(1 + monthlyRate, termMonths);
            const denominator = Math.pow(1 + monthlyRate, termMonths) - 1;
            monthlyPayment = principal * (numerator / denominator);
            totalInterest = (monthlyPayment * termMonths) - principal;
            totalPayment = securityDeposit + (monthlyPayment * termMonths);
        }
        
        // Display summary
        document.getElementById('preview_deposit').textContent = formatPeso(securityDeposit);
        document.getElementById('preview_principal').textContent = formatPeso(principal);
        document.getElementById('preview_monthly').textContent = formatPeso(monthlyPayment);
        document.getElementById('preview_interest').textContent = formatPeso(totalInterest);
        document.getElementById('preview_total').textContent = formatPeso(totalPayment);
        
        // Generate preview table
        const tbody = document.getElementById('schedulePreviewTable');
        tbody.innerHTML = '';
        
        // Row 0: Security Deposit
        if (securityDeposit > 0) {
            const depositRow = tbody.insertRow();
            depositRow.className = 'table-info';
            const contractDateObj = new Date(contractDate + 'T00:00:00');
            depositRow.innerHTML = `
                <td><span class="badge bg-info text-dark">Deposit</span></td>
                <td>${contractDateObj.toLocaleDateString('en-PH', {year: 'numeric', month: 'short', day: 'numeric'})}</td>
                <td class="text-end"><strong>${formatPeso(securityDeposit)}</strong></td>
            `;
        }

        // Rows 1–N: Regular installments (show first 5)
        const startDate = new Date(contractDate + 'T00:00:00');
        for (let i = 1; i <= Math.min(5, termMonths); i++) {
            const dueDate = new Date(startDate);
            dueDate.setMonth(dueDate.getMonth() + i);
            
            const row = tbody.insertRow();
            row.innerHTML = `
                <td>${i}</td>
                <td>${dueDate.toLocaleDateString('en-PH', {year: 'numeric', month: 'short', day: 'numeric'})}</td>
                <td class="text-end">${formatPeso(monthlyPayment)}</td>
            `;
        }
        
        if (termMonths > 5) {
            const row = tbody.insertRow();
            row.innerHTML = `<td colspan="3" class="text-center text-muted"><em>... and ${termMonths - 5} more payments</em></td>`;
        }
        
        // Show preview and enable save button
        // Hide placeholder, show actual preview
        const placeholder = document.querySelector('#previewColumn .card:not(#previewContainer)');
        if (placeholder) placeholder.style.display = 'none';
        previewContainer.style.display = 'block';
        btnSaveContract.disabled = false;
        scheduleComputedInput.value = '1';
        
        // Scroll to preview
        previewContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    
    // Disable save button if form values change
    const inputs = ['total_price', 'security_deposit', 'interest_rate', 'term_months', 'contract_date'];
    inputs.forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            btnSaveContract.disabled = true;
            scheduleComputedInput.value = '0';
            previewContainer.style.display = 'none';
            // Show placeholder again
            const placeholder = document.querySelector('#previewColumn .card:not(#previewContainer)');
            if (placeholder) placeholder.style.display = 'block';
        });
    });
    
    function formatPeso(amount) {
        return '₱' + amount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Initialize Select2 for client search (if not pre-selected)
    <?php if (!$preselected_client || $preselected_client['account_status'] !== 'approved'): ?>
    $('#client_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search for a client --',
        allowClear: false,
        width: '100%'
    });
    <?php endif; ?>
});
</script>

<?php include '../templates/footer.php'; ?>
