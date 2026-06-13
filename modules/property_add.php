<?php
/**
 * Add New Property Page
 * Real Estate Receivable System
 * 
 * Form to add a new property with validation, interest rate, down payment, and images
 */

// Define page constants
define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

// Include required files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/validation_helpers.php';
require_once '../includes/financial_helpers.php';

// Require user to be logged in
require_login();

// Set page title
$page_title = 'Add New Property';

// Initialize variables
$client_id = $property_name = $total_price = $contract_date = $term_months = '';
$square_meters = $location = $description = '';
$security_deposit = 0;
$interest_rate = 0;
$errors = [];

// Fetch all clients for dropdown
try {
    $clients_stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch clients error: " . $e->getMessage());
    $clients = [];
}

/**
 * Handle property image upload
 * @param array $file $_FILES array element
 * @param int $index Image index (1-4)
 * @return string|null File path on success, null on failure
 */
function handle_property_image_upload($file, $index)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null; // No file uploaded or error
    }

    // Check file size (2MB max for images)
    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return null;
    }

    // Check file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        return null;
    }

    // Create upload directory if not exists
    $upload_dir = "../uploads/properties/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $unique_name = 'prop_' . time() . '_' . $index . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $unique_name;
    $relative_path = "uploads/properties/" . $unique_name;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return $relative_path;
    }

    return null;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Get and sanitize input
        $client_id = sanitize_input($_POST['client_id'] ?? '');
        $property_name = sanitize_input($_POST['property_name'] ?? '');
        $square_meters = sanitize_input($_POST['square_meters'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $total_price = sanitize_input($_POST['total_price'] ?? '');
        $security_deposit = sanitize_input($_POST['security_deposit'] ?? '0');
        $interest_rate = sanitize_input($_POST['interest_rate'] ?? '0');
        $contract_date = sanitize_input($_POST['contract_date'] ?? '');
        $term_months = sanitize_input($_POST['term_months'] ?? '');

        // Validation - Client is now OPTIONAL
        // If client_id is provided, verify it exists
        if (!empty($client_id) && is_numeric($client_id)) {
            try {
                $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE client_id = ?");
                $stmt->execute([$client_id]);
                if (!$stmt->fetch()) {
                    $errors['client_id'] = 'Selected client does not exist.';
                }
            } catch (PDOException $e) {
                error_log("Client verification error: " . $e->getMessage());
            }
        }

        if (empty($property_name)) {
            $errors['property_name'] = 'Property name is required.';
        } elseif (strlen($property_name) < 3) {
            $errors['property_name'] = 'Property name must be at least 3 characters.';
        } elseif (strlen($property_name) > 150) {
            $errors['property_name'] = 'Property name cannot exceed 150 characters.';
        }

        if (empty($total_price)) {
            $errors['total_price'] = 'Total price is required.';
        } elseif (!is_numeric($total_price)) {
            $errors['total_price'] = 'Total price must be a valid number.';
        } elseif ($total_price <= 0) {
            $errors['total_price'] = 'Total price must be greater than zero.';
        } elseif ($total_price > 999999999.99) {
            $errors['total_price'] = 'Total price is too large.';
        }

        // Security deposit validation (must be non-negative; no upper limit relative to price)
        if (!is_numeric($security_deposit) || $security_deposit < 0) {
            $errors['security_deposit'] = 'Security deposit must be a valid non-negative number.';
        }

        // Interest rate validation
        if (!is_numeric($interest_rate) || $interest_rate < 0) {
            $errors['interest_rate'] = 'Interest rate must be a valid non-negative number.';
        } elseif ($interest_rate > 50) {
            $errors['interest_rate'] = 'Interest rate cannot exceed 50%.';
        }

        if (empty($contract_date)) {
            $errors['contract_date'] = 'Contract date is required.';
        } else {
            // Validate date is not in the future
            $date_validation = validate_date_not_future($contract_date, 'Contract date');
            if (!$date_validation['valid']) {
                $errors['contract_date'] = $date_validation['error'];
            }
        }

        if (empty($term_months)) {
            $errors['term_months'] = 'Payment term is required.';
        } elseif (!is_numeric($term_months)) {
            $errors['term_months'] = 'Payment term must be a valid number.';
        } elseif ($term_months < 1 || $term_months > 360) {
            $errors['term_months'] = 'Payment term must be between 1 and 360 months.';
        }

        // Handle image uploads
        $image_paths = [null, null, null, null];
        for ($i = 1; $i <= 4; $i++) {
            $field_name = "image_$i";
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                $uploaded_path = handle_property_image_upload($_FILES[$field_name], $i);
                if ($uploaded_path) {
                    $image_paths[$i - 1] = $uploaded_path;
                }
            }
        }

        // If no errors, insert property
        if (empty($errors)) {
            try {
                // Determine status based on client assignment
                $property_status = (!empty($client_id) && is_numeric($client_id)) ? 'sold' : 'available';
                $final_client_id = (!empty($client_id) && is_numeric($client_id)) ? $client_id : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO properties (
                        client_id, property_name, square_meters, location, description,
                        total_price, contract_date, term_months,
                        interest_rate, security_deposit, status, image_1, image_2, image_3, image_4
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $final_client_id,
                    $property_name,
                    !empty($square_meters) && is_numeric($square_meters) ? $square_meters : null,
                    !empty($location) ? $location : null,
                    !empty($description) ? $description : null,
                    $total_price,
                    $contract_date,
                    $term_months,
                    $interest_rate,
                    $security_deposit,
                    $property_status,
                    $image_paths[0],
                    $image_paths[1],
                    $image_paths[2],
                    $image_paths[3]
                ]);

                $new_property_id = $pdo->lastInsertId();

                // Log property creation
                log_audit(
                    $pdo,
                    'ADD_PROPERTY',
                    'property_id:' . $new_property_id,
                    'Added property: ' . $property_name . ' | Price: ₱' . number_format($total_price, 2) .
                    ' | Security Deposit: ₱' . number_format($security_deposit, 2) . ' | Rate: ' . $interest_rate . '%'
                );

                set_flash_message('success', "Property '{$property_name}' added successfully!");
                header('Location: property_edit.php?id=' . $new_property_id);
                exit();

            } catch (PDOException $e) {
                error_log("Add property error: " . $e->getMessage());
                $errors['general'] = 'Failed to add property. Please try again.';
            }
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
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="properties.php">Properties</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add New Property</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>
                        <span style="color: var(--primary-blue);">➕</span> Add New Property
                    </h2>
                    <p class="text-muted mb-0">Enter property information to create a new record</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="properties.php" class="btn btn-outline-secondary">
                        <span>◀</span> Back to Properties
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> <?php echo htmlspecialchars($errors['general']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add Property Form -->
        <form method="POST" action="property_add.php" enctype="multipart/form-data" novalidate>
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="row">
                <!-- Left Column: Property Details -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <span>📝</span> Property Information
                        </div>
                        <div class="card-body">
                            <!-- Client Selection -->
                            <div class="mb-3">
                                <label for="client_id" class="form-label">
                                    Client <span class="text-muted">(Optional)</span>
                                </label>
                                <select 
                                    class="form-select <?php echo isset($errors['client_id']) ? 'is-invalid' : ''; ?>" 
                                    id="client_id" 
                                    name="client_id"
                                    autofocus>
                                    <option value="">-- No Client (Available Property) --</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['client_id']; ?>" 
                                                <?php echo ($client_id == $client['client_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['client_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['client_id']); ?>
                                    </div>
                                <?php endif; ?>
                                <small class="form-text text-muted">Optional - Leave blank to create an available property for catalog</small>
                            </div>
                            
                            <!-- Property Name -->
                            <div class="mb-3">
                                <label for="property_name" class="form-label">
                                    Property Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">🏘️</span>
                                    <input 
                                        type="text" 
                                        class="form-control <?php echo isset($errors['property_name']) ? 'is-invalid' : ''; ?>" 
                                        id="property_name" 
                                        name="property_name" 
                                        value="<?php echo htmlspecialchars($property_name); ?>"
                                        placeholder="e.g., Sunrise Residences Unit 101"
                                        required>
                                    <?php if (isset($errors['property_name'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['property_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                               <small class="form-text text-muted">Descriptive name of the property (3-150 characters)</small>
                            </div>
                            
                            <div class="row">
                                <!-- Square Meters -->
                                <div class="col-md-6 mb-3">
                                    <label for="square_meters" class="form-label">
                                        Square Meters
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">📏</span>
                                        <input 
                                            type="number" 
                                            class="form-control" 
                                            id="square_meters" 
                                            name="square_meters" 
                                            value="<?php echo htmlspecialchars($square_meters); ?>"
                                            placeholder="e.g., 150.50"
                                            step="0.01"
                                            min="0.01">
                                    </div>
                                    <small class="form-text text-muted">Property area in square meters (optional)</small>
                                </div>

                                <!-- Location -->
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">
                                        Location
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">📍</span>
                                        <input 
                                            type="text" 
                                            class="form-control" 
                                            id="location" 
                                            name="location" 
                                            value="<?php echo htmlspecialchars($location); ?>"
                                            placeholder="e.g., Quezon City, Metro Manila"
                                            maxlength="255">
                                    </div>
                                    <small class="form-text text-muted">City or area location (optional)</small>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    Property Description
                                </label>
                                <textarea 
                                    class="form-control" 
                                    id="description" 
                                    name="description" 
                                    rows="4"
                                    placeholder="Describe the property features, amenities, condition, etc."><?php echo htmlspecialchars($description); ?></textarea>
                                <small class="form-text text-muted">Detailed description of the property (optional)</small>
                            </div>
                            
                            <div class="row">
                                <!-- Total Price -->
                                <div class="col-md-6 mb-3">
                                    <label for="total_price" class="form-label">
                                        Total Price <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input 
                                            type="number" 
                                            class="form-control <?php echo isset($errors['total_price']) ? 'is-invalid' : ''; ?>" 
                                            id="total_price" 
                                            name="total_price" 
                                            value="<?php echo htmlspecialchars($total_price); ?>"
                                            placeholder="2500000.00"
                                            step="0.01"
                                            min="0.01"
                                            required>
                                        <?php if (isset($errors['total_price'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['total_price']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Security Deposit -->
                                <div class="col-md-6 mb-3">
                                    <label for="security_deposit" class="form-label">Security Deposit</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input 
                                            type="number" 
                                            class="form-control <?php echo isset($errors['security_deposit']) ? 'is-invalid' : ''; ?>" 
                                            id="security_deposit" 
                                            name="security_deposit" 
                                            value="<?php echo htmlspecialchars($security_deposit ?: '0'); ?>"
                                            placeholder="0.00"
                                            step="0.01"
                                            min="0">
                                        <?php if (isset($errors['security_deposit'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['security_deposit']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Interest Rate -->
                                <div class="col-md-6 mb-3">
                                    <label for="interest_rate" class="form-label">
                                        Annual Interest Rate
                                    </label>
                                    <div class="input-group">
                                        <input 
                                            type="number" 
                                            class="form-control <?php echo isset($errors['interest_rate']) ? 'is-invalid' : ''; ?>" 
                                            id="interest_rate" 
                                            name="interest_rate" 
                                            value="<?php echo htmlspecialchars($interest_rate ?: '0'); ?>"
                                            placeholder="12"
                                            step="0.01"
                                            min="0"
                                            max="50">
                                        <span class="input-group-text">%</span>
                                        <?php if (isset($errors['interest_rate'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['interest_rate']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">0% for interest-free installment</small>
                                </div>
                                
                                <!-- Payment Term -->
                                <div class="col-md-6 mb-3">
                                    <label for="term_months" class="form-label">
                                        Payment Term <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">📆</span>
                                        <input 
                                            type="number" 
                                            class="form-control <?php echo isset($errors['term_months']) ? 'is-invalid' : ''; ?>" 
                                            id="term_months" 
                                            name="term_months" 
                                            value="<?php echo htmlspecialchars($term_months); ?>"
                                            placeholder="60"
                                            min="1"
                                            max="360"
                                            required>
                                        <span class="input-group-text">months</span>
                                        <?php if (isset($errors['term_months'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['term_months']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contract Date -->
                            <div class="mb-3">
                                <label for="contract_date" class="form-label">
                                    Contract Date <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">📅</span>
                                    <input 
                                        type="date" 
                                        class="form-control <?php echo isset($errors['contract_date']) ? 'is-invalid' : ''; ?>" 
                                        id="contract_date" 
                                        name="contract_date" 
                                        value="<?php echo htmlspecialchars($contract_date); ?>"
                                        max="<?php echo date('Y-m-d'); ?>"
                                        required>
                                    <?php if (isset($errors['contract_date'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['contract_date']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <small class="form-text text-muted">Date when the contract was signed</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Property Images -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <span>📷</span> Property Images (Optional)
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Upload up to 4 images of the property. Max 2MB each. (JPG, PNG, WebP)</p>
                            <div class="row">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="image_<?php echo $i; ?>" class="form-label">Image <?php echo $i; ?></label>
                                        <input 
                                            type="file" 
                                            class="form-control" 
                                            id="image_<?php echo $i; ?>" 
                                            name="image_<?php echo $i; ?>"
                                            accept=".jpg,.jpeg,.png,.webp">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Calculator Preview -->
                <div class="col-lg-5">
                    <!-- Payment Calculator -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <span>🧮</span> Mortgage Calculator Preview
                        </div>
                        <div class="card-body">
                            <div class="calculator-result">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Property Amount</h6>
                                    <h4 id="calc_principal" style="color: var(--primary-maroon);">₱0.00</h4>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Monthly Payment</h6>
                                    <h3 id="calc_monthly" style="color: var(--primary-maroon);">₱0.00</h3>
                                    <small class="text-muted" id="calc_breakdown"></small>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Total Interest</small>
                                        <h5 id="calc_interest" class="text-warning mb-0">₱0.00</h5>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Total Payment</small>
                                        <h5 id="calc_total" class="text-success mb-0">₱0.00</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Help Text -->
                    <div class="alert alert-info mt-3" role="alert">
                        <strong>ℹ️ Quick Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Select an existing client or <a href="client_add.php">create a new client</a></li>
                            <li>Security deposit is collected separately on the contract date</li>
                            <li>Set interest rate to 0% for no-interest plans</li>
                            <li>Payment schedules are generated after saving</li>
                        </ul>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            ✓ Save Property
                        </button>
                        <a href="properties.php" class="btn btn-outline-secondary">
                            ✖ Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
        
    </div>
</div>

<!-- Mortgage Calculator Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const totalPriceInput = document.getElementById('total_price');
    const securityDepositInput = document.getElementById('security_deposit');
    const interestRateInput = document.getElementById('interest_rate');
    const termMonthsInput = document.getElementById('term_months');
    
    const calcPrincipal = document.getElementById('calc_principal');
    const calcMonthly = document.getElementById('calc_monthly');
    const calcInterest = document.getElementById('calc_interest');
    const calcTotal = document.getElementById('calc_total');
    const calcBreakdown = document.getElementById('calc_breakdown');
    
    function formatCurrency(amount) {
        return '₱' + amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function calculateMortgage() {
        const totalPrice = parseFloat(totalPriceInput.value) || 0;
        const securityDeposit = parseFloat(document.getElementById('security_deposit').value) || 0;
        const annualRate = parseFloat(interestRateInput.value) || 0;
        const termMonths = parseInt(termMonthsInput.value) || 0;
        
        // Security deposit is separate — principal = full total price
        const principal = totalPrice;
        
        if (principal <= 0 || termMonths <= 0) {
            calcPrincipal.textContent = '₱0.00';
            calcMonthly.textContent = '₱0.00';
            calcInterest.textContent = '₱0.00';
            calcTotal.textContent = '₱0.00';
            calcBreakdown.textContent = '';
            return;
        }
        
        let monthlyPayment, totalInterest, totalPayment;
        
        if (annualRate <= 0) {
            // Zero interest
            monthlyPayment = principal / termMonths;
            totalInterest = 0;
            totalPayment = securityDeposit + principal;
            calcBreakdown.textContent = 'Interest-free installment';
        } else {
            // Calculate with interest (amortization formula)
            const monthlyRate = (annualRate / 100) / 12;
            const numerator = monthlyRate * Math.pow(1 + monthlyRate, termMonths);
            const denominator = Math.pow(1 + monthlyRate, termMonths) - 1;
            monthlyPayment = principal * (numerator / denominator);
            totalInterest = (monthlyPayment * termMonths) - principal;
            totalPayment = securityDeposit + (monthlyPayment * termMonths);
            calcBreakdown.textContent = `${annualRate}% annual interest over ${termMonths} months`;
        }
        
        calcPrincipal.textContent = formatCurrency(principal);
        calcMonthly.textContent = formatCurrency(monthlyPayment);
        calcInterest.textContent = formatCurrency(totalInterest);
        calcTotal.textContent = formatCurrency(totalPayment);
    }
    
    totalPriceInput.addEventListener('input', calculateMortgage);
    document.getElementById('security_deposit').addEventListener('input', calculateMortgage);
    interestRateInput.addEventListener('input', calculateMortgage);
    termMonthsInput.addEventListener('input', calculateMortgage);
    
    // Calculate on page load if values exist
    calculateMortgage();
    
    // Initialize Select2 for client search
    $('#client_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search for a client or leave blank --',
        allowClear: true,
        width: '100%',
        matcher: function(params, data) {
            // If there are no search terms, return all data
            if ($.trim(params.term) === '') {
                return data;
            }
            
            // Do not display the item if there is no 'text' property
            if (typeof data.text === 'undefined') {
                return null;
            }
            
            // Search case-insensitive
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return data;
            }
            
            return null;
        }
    });
});
</script>

<?php
// Include footer
include '../templates/footer.php';
?>
