<?php
/**
 * Invoice Creation Page
 * Auto-generate invoices per payment schedule or property
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
$page_title = 'Create Invoice';

$errors = [];
$success = false;

// Fetch properties for selection
try {
    $properties_stmt = $pdo->query("
        SELECT p.property_id, p.property_name, c.name as client_name 
        FROM properties p
        LEFT JOIN clients c ON p.client_id = c.client_id
        ORDER BY p.property_name ASC
    ");
    $properties = $properties_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch properties error: " . $e->getMessage());
    $properties = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_type = trim($_POST['invoice_type'] ?? '');
    $property_id = trim($_POST['property_id'] ?? '');
    $schedule_id = trim($_POST['schedule_id'] ?? '');
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($invoice_type) || !in_array($invoice_type, ['schedule', 'property'])) {
        $errors['invoice_type'] = 'Please select a valid invoice type.';
    }
    
    if ($invoice_type === 'schedule' && empty($schedule_id)) {
        $errors['schedule_id'] = 'Please select a payment schedule.';
    }
    
    if ($invoice_type === 'property' && empty($property_id)) {
        $errors['property_id'] = 'Please select a property.';
    }
    
    if (empty($invoice_date)) {
        $errors['invoice_date'] = 'Invoice date is required.';
    }
    
    if (empty($due_date)) {
        $errors['due_date'] = 'Due date is required.';
    } elseif ($due_date < $invoice_date) {
        $errors['due_date'] = 'Due date cannot be before invoice date.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($invoice_type === 'schedule') {
                // Create invoice for specific payment schedule
                $schedule_stmt = $pdo->prepare("
                    SELECT ps.*, p.property_name, c.name as client_name, c.address, c.email, c.contact_no
                    FROM payment_schedules ps
                    INNER JOIN properties p ON ps.property_id = p.property_id
                    LEFT JOIN clients c ON p.client_id = c.client_id
                    WHERE ps.schedule_id = ?
                ");
                $schedule_stmt->execute([$schedule_id]);
                $schedule = $schedule_stmt->fetch();
                
                if (!$schedule) {
                    throw new Exception('Payment schedule not found.');
                }
                
                // Generate invoice number
                $invoice_no = 'INV-' . date('Ymd') . '-' . str_pad($schedule_id, 6, '0', STR_PAD_LEFT);
                
                // Insert invoice
                $insert_stmt = $pdo->prepare("
                    INSERT INTO invoices (invoice_no, schedule_id, invoice_date, due_date, total_amount, status, notes)
                    VALUES (?, ?, ?, ?, ?, 'unpaid', ?)
                ");
                $insert_stmt->execute([
                    $invoice_no,
                    $schedule_id,
                    $invoice_date,
                    $due_date,
                    $schedule['amount_due'],
                    $notes
                ]);
                
                $invoice_id = $pdo->lastInsertId();
                
            } else {
                // Create invoice for entire property
                $property_stmt = $pdo->prepare("
                    SELECT p.*, c.name as client_name, c.address, c.email, c.contact_no,
                           COALESCE(SUM(ps.amount_due), 0) as total_due
                    FROM properties p
                    LEFT JOIN clients c ON p.client_id = c.client_id
                    LEFT JOIN payment_schedules ps ON p.property_id = ps.property_id AND ps.status != 'paid'
                    WHERE p.property_id = ?
                    GROUP BY p.property_id
                ");
                $property_stmt->execute([$property_id]);
                $property = $property_stmt->fetch();
                
                if (!$property) {
                    throw new Exception('Property not found.');
                }
                
                // Generate invoice number
                $invoice_no = 'INV-' . date('Ymd') . '-P' . str_pad($property_id, 6, '0', STR_PAD_LEFT);
                
                // Insert invoice
                $insert_stmt = $pdo->prepare("
                    INSERT INTO invoices (invoice_no, property_id, invoice_date, due_date, total_amount, status, notes)
                    VALUES (?, ?, ?, ?, ?, 'unpaid', ?)
                ");
                $insert_stmt->execute([
                    $invoice_no,
                    $property_id,
                    $invoice_date,
                    $due_date,
                    $property['total_due'],
                    $notes
                ]);
                
                $invoice_id = $pdo->lastInsertId();
            }
            
            $pdo->commit();
            
            // Log invoice creation
            log_audit($pdo, 'CREATE_INVOICE', 'invoice_id:' . $invoice_id, 'Created invoice: ' . $invoice_no);
            
            set_flash_message('success', "Invoice {$invoice_no} created successfully!");
            header('Location: invoice_view.php?id=' . $invoice_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Create invoice error: " . $e->getMessage());
            $errors['general'] = 'Failed to create invoice: ' . $e->getMessage();
        }
    }
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
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Page Header -->
            <div class="page-header mb-4">
                <div class="d-flex align-items-center">
                    <a href="invoices.php" class="btn btn-outline-secondary me-3">
                        <span>←</span> Back
                    </a>
                    <div>
                        <h2 class="mb-0"><span>📄</span> Create New Invoice</h2>
                        <p class="text-muted mb-0">Generate invoice for payment schedule or property</p>
                    </div>
                </div>
            </div>

            <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errors['general']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Invoice Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><span>📝</span> Invoice Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="invoice_create.php" id="invoiceForm">
                        
                        <!-- Invoice Type Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Invoice Type <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-check-inline p-3 border rounded w-100 <?php echo isset($errors['invoice_type']) ? 'border-danger' : ''; ?>">
                                        <input class="form-check-input" type="radio" name="invoice_type" 
                                               id="typeSchedule" value="schedule" 
                                               <?php echo (isset($_POST['invoice_type']) && $_POST['invoice_type'] === 'schedule') ? 'checked' : ''; ?>
                                               onchange="toggleInvoiceType()">
                                        <label class="form-check-label w-100" for="typeSchedule">
                                            <strong>📅 Payment Schedule</strong>
                                            <p class="text-muted small mb-0">Create invoice for a specific payment installment</p>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-check-inline p-3 border rounded w-100">
                                        <input class="form-check-input" type="radio" name="invoice_type" 
                                               id="typeProperty" value="property"
                                               <?php echo (isset($_POST['invoice_type']) && $_POST['invoice_type'] === 'property') ? 'checked' : ''; ?>
                                               onchange="toggleInvoiceType()">
                                        <label class="form-check-label w-100" for="typeProperty">
                                            <strong>🏠 Entire Property</strong>
                                            <p class="text-muted small mb-0">Create invoice for all unpaid schedules</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($errors['invoice_type'])): ?>
                            <div class="text-danger small mt-1"><?php echo $errors['invoice_type']; ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Property Selection (for property type) -->
                        <div class="mb-3" id="propertySelection" style="display: none;">
                            <label for="property_id" class="form-label fw-bold">Select Property <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['property_id']) ? 'is-invalid' : ''; ?>" 
                                    id="property_id" name="property_id" onchange="loadPropertyInfo()">
                                <option value="">-- Select Property --</option>
                                <?php foreach ($properties as $prop): ?>
                                <option value="<?php echo $prop['property_id']; ?>" 
                                        <?php echo (isset($_POST['property_id']) && $_POST['property_id'] == $prop['property_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prop['property_name']); ?> (<?php echo htmlspecialchars($prop['client_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['property_id'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['property_id']; ?></div>
                            <?php endif; ?>
                            <div id="propertyInfo" class="mt-2"></div>
                        </div>

                        <!-- Schedule Selection (for schedule type) -->
                        <div class="mb-3" id="scheduleSelection" style="display: none;">
                            <label for="schedule_property" class="form-label fw-bold">Select Property First <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="schedule_property" onchange="loadSchedules()">
                                <option value="">-- Select Property --</option>
                                <?php foreach ($properties as $prop): ?>
                                <option value="<?php echo $prop['property_id']; ?>">
                                    <?php echo htmlspecialchars($prop['property_name']); ?> (<?php echo htmlspecialchars($prop['client_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="schedule_id" class="form-label fw-bold">Select Payment Schedule <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['schedule_id']) ? 'is-invalid' : ''; ?>" 
                                    id="schedule_id" name="schedule_id" disabled>
                                <option value="">-- Select property first --</option>
                            </select>
                            <?php if (isset($errors['schedule_id'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['schedule_id']; ?></div>
                            <?php endif; ?>
                            <div id="scheduleInfo" class="mt-2"></div>
                        </div>

                        <hr>

                        <!-- Invoice Dates -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="invoice_date" class="form-label fw-bold">Invoice Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?php echo isset($errors['invoice_date']) ? 'is-invalid' : ''; ?>" 
                                       id="invoice_date" name="invoice_date" 
                                       value="<?php echo $_POST['invoice_date'] ?? date('Y-m-d'); ?>" required>
                                <?php if (isset($errors['invoice_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['invoice_date']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="due_date" class="form-label fw-bold">Due Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?php echo isset($errors['due_date']) ? 'is-invalid' : ''; ?>" 
                                       id="due_date" name="due_date" 
                                       value="<?php echo $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days')); ?>" required>
                                <?php if (isset($errors['due_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['due_date']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label for="notes" class="form-label fw-bold">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Add any additional notes or instructions..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="invoices.php" class="btn btn-outline-secondary">
                                <span>✖</span> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <span>✓</span> Create Invoice
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
</div>

<script>
// Toggle invoice type visibility
function toggleInvoiceType() {
    const scheduleRadio = document.getElementById('typeSchedule');
    const propertyRadio = document.getElementById('typeProperty');
    const scheduleSelection = document.getElementById('scheduleSelection');
    const propertySelection = document.getElementById('propertySelection');
    
    if (scheduleRadio.checked) {
        scheduleSelection.style.display = 'block';
        propertySelection.style.display = 'none';
        document.getElementById('property_id').value = '';
    } else if (propertyRadio.checked) {
        propertySelection.style.display = 'block';
        scheduleSelection.style.display = 'none';
        document.getElementById('schedule_id').value = '';
    }
}

// Load schedules for selected property
function loadSchedules() {
    const propertyId = document.getElementById('schedule_property').value;
    const scheduleSelect = document.getElementById('schedule_id');
    const scheduleInfo = document.getElementById('scheduleInfo');
    
    if (!propertyId) {
        scheduleSelect.disabled = true;
        scheduleSelect.innerHTML = '<option value="">-- Select property first --</option>';
        scheduleInfo.innerHTML = '';
        return;
    }
    
    // Fetch schedules via AJAX
    fetch(`../api/get_schedules.php?property_id=${propertyId}`)
        .then(response => response.json())
        .then(data => {
            scheduleSelect.disabled = false;
            scheduleSelect.innerHTML = '<option value="">-- Select Payment Schedule --</option>';
            
            data.forEach(schedule => {
                const option = document.createElement('option');
                option.value = schedule.schedule_id;
                option.textContent = `Schedule ${schedule.schedule_number} of ${schedule.term_months} - Due: ${schedule.due_date} - ₱${parseFloat(schedule.amount_due).toLocaleString('en-PH', {minimumFractionDigits: 2})} (${schedule.status})`;
                option.dataset.amount = schedule.amount_due;
                option.dataset.dueDate = schedule.due_date;
                scheduleSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading schedules:', error);
            scheduleInfo.innerHTML = '<div class="alert alert-danger small">Failed to load schedules</div>';
        });
}

// Load property info for property-type invoice
function loadPropertyInfo() {
    const propertyId = document.getElementById('property_id').value;
    const propertyInfo = document.getElementById('propertyInfo');
    
    if (!propertyId) {
        propertyInfo.innerHTML = '';
        return;
    }
    
    // Fetch property info via AJAX
    fetch(`../api/get_property_info.php?property_id=${propertyId}`)
        .then(response => response.json())
        .then(data => {
            propertyInfo.innerHTML = `
                <div class="alert alert-info small">
                    <strong>Total Unpaid Amount:</strong> ₱${parseFloat(data.total_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}<br>
                    <strong>Unpaid Schedules:</strong> ${data.unpaid_count}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error loading property info:', error);
        });
}

// Initialize form on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleInvoiceType();
});
</script>

<?php
// Include footer
include '../templates/footer.php';
?>
