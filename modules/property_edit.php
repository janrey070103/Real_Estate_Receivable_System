<?php
/**
 * Edit Property Page
 * Real Estate Receivable System
 * 
 * Edit property with interest/down payment fields and image management
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

// Set page title
$page_title = 'Edit Property';

// Get property ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid property ID.');
    header('Location: properties.php');
    exit();
}

$property_id = (int)$_GET['id'];
$errors = [];

/**
 * Handle property image upload
 */
function handle_property_image_upload($file, $index) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        return null;
    }
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return null;
    }
    
    $upload_dir = "../uploads/properties/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $unique_name = 'prop_' . time() . '_' . $index . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $unique_name;
    $relative_path = "uploads/properties/" . $unique_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return $relative_path;
    }
    
    return null;
}

// Handle Delete Payment Schedule
if (isset($_GET['delete_schedule']) && is_numeric($_GET['delete_schedule'])) {
    $schedule_id = (int)$_GET['delete_schedule'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM payment_schedules WHERE schedule_id = ? AND property_id = ?");
        $stmt->execute([$schedule_id, $property_id]);
        set_flash_message('success', 'Payment schedule deleted successfully!');
    } catch (PDOException $e) {
        error_log("Delete schedule error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete payment schedule.');
    }
    
    header('Location: property_edit.php?id=' . $property_id . '#schedules');
    exit();
}

// Handle Delete All Payment Schedules
if (isset($_GET['delete_all_schedules']) && $_GET['delete_all_schedules'] === 'confirm') {
    try {
        $pdo->beginTransaction();
        
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payment_schedules WHERE property_id = ?");
        $count_stmt->execute([$property_id]);
        $schedule_count = $count_stmt->fetch()['total'];
        
        $delete_stmt = $pdo->prepare("DELETE FROM payment_schedules WHERE property_id = ?");
        $delete_stmt->execute([$property_id]);
        
        $pdo->commit();
        set_flash_message('success', "Successfully deleted all {$schedule_count} payment schedule(s)!");
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete all schedules error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete payment schedules.');
    }
    
    header('Location: property_edit.php?id=' . $property_id . '#schedules');
    exit();
}

// Handle Delete Image
if (isset($_GET['delete_image']) && in_array($_GET['delete_image'], ['1', '2', '3', '4'])) {
    $img_index = $_GET['delete_image'];
    $img_column = "image_$img_index";
    
    try {
        // Get current image path
        $stmt = $pdo->prepare("SELECT $img_column FROM properties WHERE property_id = ?");
        $stmt->execute([$property_id]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result[$img_column])) {
            // Delete file
            $file_path = '../' . $result[$img_column];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Clear database column
            $stmt = $pdo->prepare("UPDATE properties SET $img_column = NULL WHERE property_id = ?");
            $stmt->execute([$property_id]);
            
            set_flash_message('success', "Image $img_index deleted successfully!");
        }
    } catch (PDOException $e) {
        error_log("Delete image error: " . $e->getMessage());
        set_flash_message('error', 'Failed to delete image.');
    }
    
    header('Location: property_edit.php?id=' . $property_id . '#images');
    exit();
}

// Fetch property data
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
    
    // Fetch payment schedules
    $stmt = $pdo->prepare("
        SELECT * FROM payment_schedules 
        WHERE property_id = ? 
        ORDER BY due_date ASC
    ");
    $stmt->execute([$property_id]);
    $schedules = $stmt->fetchAll();
    
    // Calculate schedule statistics
    $total_paid = $total_pending = $total_overdue = 0;
    foreach ($schedules as $schedule) {
        if ($schedule['status'] === 'paid') $total_paid += $schedule['amount_due'];
        elseif ($schedule['status'] === 'pending') $total_pending += $schedule['amount_due'];
        elseif ($schedule['status'] === 'overdue') $total_overdue += $schedule['amount_due'];
    }
    
} catch (PDOException $e) {
    error_log("Fetch property error: " . $e->getMessage());
    set_flash_message('error', 'Failed to load property data.');
    header('Location: properties.php');
    exit();
}

// Calculate financial summary
$total_price = floatval($property['total_price']);
$security_deposit = floatval($property['security_deposit'] ?? 0);
$interest_rate = floatval($property['interest_rate'] ?? 0);
$term_months = intval($property['term_months']);
$principal = $total_price; // Security deposit does NOT reduce principal
$amort = calculate_amortization($principal, $interest_rate, $term_months);

// Fetch all clients for dropdown
try {
    $clients_stmt = $pdo->query("SELECT client_id, name FROM clients ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch clients error: " . $e->getMessage());
    $clients = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);
    $property_name = trim($_POST['property_name'] ?? '');
    $square_meters = trim($_POST['square_meters'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $total_price_input = filter_input(INPUT_POST, 'total_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $security_deposit_input = filter_input(INPUT_POST, 'security_deposit', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 0;
    $interest_rate_input = filter_input(INPUT_POST, 'interest_rate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 0;
    $contract_date = $_POST['contract_date'] ?? '';
    $term_months_input = filter_input(INPUT_POST, 'term_months', FILTER_SANITIZE_NUMBER_INT);
    
    // Validation - Client is now OPTIONAL
    // If client_id is provided, verify it's valid
    if (!empty($client_id) && is_numeric($client_id)) {
        // Verify client exists
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
    } elseif (strlen($property_name) < 3 || strlen($property_name) > 150) {
        $errors['property_name'] = 'Property name must be 3-150 characters.';
    }
    
    if (empty($total_price_input) || $total_price_input <= 0) {
        $errors['total_price'] = 'Total price must be greater than zero.';
    }
    
    if ($security_deposit_input < 0) {
        $errors['security_deposit'] = 'Security deposit cannot be negative.';
    }
    
    if ($interest_rate_input < 0 || $interest_rate_input > 50) {
        $errors['interest_rate'] = 'Interest rate must be between 0 and 50%.';
    }
    
    if (empty($contract_date)) {
        $errors['contract_date'] = 'Contract date is required.';
    }
    
    if (empty($term_months_input) || $term_months_input < 1 || $term_months_input > 360) {
        $errors['term_months'] = 'Payment term must be between 1 and 360 months.';
    }
    
    // Handle image uploads
    $image_updates = [];
    for ($i = 1; $i <= 4; $i++) {
        $field_name = "image_$i";
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
            $uploaded_path = handle_property_image_upload($_FILES[$field_name], $i);
            if ($uploaded_path) {
                // Delete old image if exists
                $old_path = $property[$field_name] ?? null;
                if ($old_path && file_exists('../' . $old_path)) {
                    unlink('../' . $old_path);
                }
                $image_updates[$field_name] = $uploaded_path;
            }
        }
    }
    
    // If no errors, update property
    if (empty($errors)) {
        try {
            // Determine status based on client assignment CHANGE
            $final_client_id = (!empty($client_id) && is_numeric($client_id)) ? $client_id : null;
            $original_client_id = $property['client_id'] ?? null;
            
            // Only change status if client assignment is changing
            $new_status = $property['status']; // Default: preserve current status
            
            if ($final_client_id !== $original_client_id) {
                // Client assignment is changing
                if ($final_client_id && !$original_client_id) {
                    // Client being assigned (Null -> ID)
                    $new_status = 'Sold';
                } elseif (!$final_client_id && $original_client_id) {
                    // Client being removed (ID -> Null)
                    $new_status = 'Available';
                }
                // If swapping clients (ID -> Different ID), keep as Sold
            }
            
            $sql = "UPDATE properties SET 
                    client_id = ?, property_name = ?, square_meters = ?, location = ?, description = ?,
                    total_price = ?, security_deposit = ?, interest_rate = ?,
                    contract_date = ?, term_months = ?, status = ?";
            $params = [
                $final_client_id, $property_name,
                !empty($square_meters) && is_numeric($square_meters) ? $square_meters : null,
                !empty($location) ? $location : null,
                !empty($description) ? $description : null,
                $total_price_input, $security_deposit_input, $interest_rate_input,
                $contract_date, $term_months_input, $new_status
            ];
            
            // Add image updates if any
            foreach ($image_updates as $col => $path) {
                $sql .= ", $col = ?";
                $params[] = $path;
            }
            
            $sql .= " WHERE property_id = ?";
            $params[] = $property_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            log_audit($pdo, 'UPDATE_PROPERTY', 'property_id:' . $property_id, 
                     'Updated property: ' . $property_name . (count($image_updates) > 0 ? ' (with images)' : ''));
            
            // Check for term changes that affect schedules
            $terms_changed = (
                floatval($property['total_price']) != floatval($total_price_input) ||
                floatval($property['security_deposit'] ?? 0) != floatval($security_deposit_input) ||
                floatval($property['interest_rate'] ?? 0) != floatval($interest_rate_input) ||
                intval($property['term_months']) != intval($term_months_input)
            );
            
            if ($terms_changed && count($schedules) > 0) {
                set_flash_message('warning', "Property '{$property_name}' updated! ⚠️ Financial terms changed but payment schedules exist. Please regenerate schedules to match new terms.");
            } else {
                set_flash_message('success', "Property '{$property_name}' updated successfully!");
            }
            
            header('Location: property_edit.php?id=' . $property_id);
            exit();
            
        } catch (PDOException $e) {
            error_log("Update property error: " . $e->getMessage());
            $errors['general'] = 'Failed to update property. Please try again.';
        }
    } else {
        // Update property array with submitted values for form display
        $property['client_id'] = $client_id;
        $property['property_name'] = $property_name;
        $property['square_meters'] = $square_meters;
        $property['location'] = $location;
        $property['description'] = $description;
        $property['total_price'] = $total_price_input;
        $property['security_deposit'] = $security_deposit_input;
        $property['interest_rate'] = $interest_rate_input;
        $property['contract_date'] = $contract_date;
        $property['term_months'] = $term_months_input;
    }
}

// Collect property images
$images = [];
for ($i = 1; $i <= 4; $i++) {
    $img = $property["image_$i"] ?? null;
    if ($img && file_exists('../' . $img)) {
        $images[$i] = $img;
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
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($property['property_name']); ?></li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2>
                        <span style="color: var(--primary-maroon);">✏️</span> Edit Property
                    </h2>
                    <p class="text-muted mb-0">Update property information, images, and manage payment schedules</p>
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
        
        <?php 
        $flash = get_flash_message();
        if ($flash):
            $alert_class = 'alert-info';
            if ($flash['type'] === 'success') $alert_class = 'alert-success';
            if ($flash['type'] === 'error') $alert_class = 'alert-danger';
            if ($flash['type'] === 'warning') $alert_class = 'alert-warning';
        ?>
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
            <strong><?php echo ucfirst($flash['type']); ?>!</strong> <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Property Statistics -->
            <div class="col-lg-4 mb-4">
                <!-- Financial Summary -->
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <span>🧮</span> Financial Summary
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">Total Price</small>
                            <h5 class="mb-0"><?php echo format_peso($total_price); ?></h5>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Security Deposit</small>
                            <h6 class="mb-0 text-info"><?php echo format_peso($security_deposit); ?></h6>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Principal</small>
                            <h5 class="mb-0" style="color: var(--primary-maroon);"><?php echo format_peso($principal); ?></h5>
                        </div>
                        <hr>
                        <div class="mb-2">
                            <small class="text-muted">Interest Rate</small>
                            <h6 class="mb-0"><?php echo $interest_rate; ?>% per annum</h6>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Monthly Payment</small>
                            <h4 class="mb-0" style="color: var(--primary-maroon);"><?php echo format_peso($amort['monthly_payment']); ?></h4>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Total Interest</small>
                                <h6 class="text-warning mb-0"><?php echo format_peso($amort['total_interest']); ?></h6>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total Payment <small class="text-muted">(incl. deposit)</small></small>
                                <h6 class="text-success mb-0"><?php echo format_peso($security_deposit + $amort['total_payment']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Progress -->
                <div class="card mb-3">
                    <div class="card-header">
                        <span>📊</span> Payment Progress
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small>🟢 Paid: <strong class="text-success"><?php echo format_peso($total_paid); ?></strong></small>
                        </div>
                        <div class="mb-2">
                            <small>🟡 Pending: <strong class="text-warning"><?php echo format_peso($total_pending); ?></strong></small>
                        </div>
                        <div class="mb-2">
                            <small>🔴 Overdue: <strong class="text-danger"><?php echo format_peso($total_overdue); ?></strong></small>
                        </div>
                        <?php 
                        $total_scheduled = $total_paid + $total_pending + $total_overdue;
                        $progress = $total_scheduled > 0 ? ($total_paid / $total_scheduled * 100) : 0;
                        ?>
                        <div class="progress mt-3" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $progress; ?>%">
                                <?php echo number_format($progress, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <span>⚡</span> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="client_edit.php?id=<?php echo $property['client_id']; ?>" class="btn btn-outline-primary">
                                <span>👤</span> View Client
                            </a>
                            <a href="#schedules" class="btn btn-outline-info">
                                <span>📅</span> View Schedules
                            </a>
                            <a href="#images" class="btn btn-outline-secondary">
                                <span>📷</span> Manage Images
                            </a>
                            <hr class="my-2">
                            <a href="properties.php?delete=<?php echo $property_id; ?>" 
                               class="btn btn-outline-danger"
                               onclick="return confirm('⚠️ Delete this property and all schedules?');">
                                <span>🗑️</span> Delete Property
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form -->
            <div class="col-lg-8">
                <form method="POST" action="property_edit.php?id=<?php echo $property_id; ?>" enctype="multipart/form-data" novalidate>
                    <div class="card">
                        <div class="card-header">
                            <span>📝</span> Property Information
                        </div>
                        <div class="card-body">
                            <!-- Client Selection -->
                            <div class="mb-3">
                                <label for="client_id" class="form-label">
                                    Client <span class="text-danger">*</span>
                                </label>
                                <select class="form-select <?php echo isset($errors['client_id']) ? 'is-invalid' : ''; ?>" 
                                        id="client_id" name="client_id" required>
                                    <option value="">-- Select Client --</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['client_id']; ?>" 
                                            <?php echo ($property['client_id'] == $client['client_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['client_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['client_id']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Property Name -->
                            <div class="mb-3">
                                <label for="property_name" class="form-label">
                                    Property Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">🏘️</span>
                                    <input type="text" class="form-control <?php echo isset($errors['property_name']) ? 'is-invalid' : ''; ?>" 
                                           id="property_name" name="property_name" 
                                           value="<?php echo htmlspecialchars($property['property_name']); ?>" required>
                                    <?php if (isset($errors['property_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['property_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Square Meters -->
                                <div class="col-md-6 mb-3">
                                    <label for="square_meters" class="form-label">Square Meters</label>
                                    <div class="input-group">
                                        <span class="input-group-text">📏</span>
                                        <input type="number" class="form-control" id="square_meters" 
                                               name="square_meters" 
                                               value="<?php echo htmlspecialchars($property['square_meters'] ?? ''); ?>"
                                               placeholder="e.g., 150.50" step="0.01" min="0.01">
                                    </div>
                                    <small class="form-text text-muted">Property area in square meters (optional)</small>
                                </div>

                                <!-- Location -->
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text">📍</span>
                                        <input type="text" class="form-control" id="location" 
                                               name="location" 
                                               value="<?php echo htmlspecialchars($property['location'] ?? ''); ?>"
                                               placeholder="e.g., Quezon City, Metro Manila" maxlength="255">
                                    </div>
                                    <small class="form-text text-muted">City or area location (optional)</small>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Property Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"
                                          placeholder="Describe the property features, amenities, condition, etc."><?php echo htmlspecialchars($property['description'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Detailed description of the property (optional)</small>
                            </div>
                            
                            <div class="row">
                                <!-- Total Price -->
                                <div class="col-md-6 mb-3">
                                    <label for="total_price" class="form-label">Total Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control <?php echo isset($errors['total_price']) ? 'is-invalid' : ''; ?>" 
                                               id="total_price" name="total_price" 
                                               value="<?php echo htmlspecialchars($property['total_price']); ?>" step="0.01" required>
                                        <?php if (isset($errors['total_price'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['total_price']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Security Deposit -->
                                <div class="col-md-6 mb-3">
                                    <label for="security_deposit" class="form-label">Security Deposit</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control <?php echo isset($errors['security_deposit']) ? 'is-invalid' : ''; ?>" 
                                               id="security_deposit" name="security_deposit" 
                                               value="<?php echo htmlspecialchars($property['security_deposit'] ?? 0); ?>" step="0.01" min="0">
                                        <?php if (isset($errors['security_deposit'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['security_deposit']; ?></div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Interest Rate -->
                                <div class="col-md-6 mb-3">
                                    <label for="interest_rate" class="form-label">Annual Interest Rate</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control <?php echo isset($errors['interest_rate']) ? 'is-invalid' : ''; ?>" 
                                               id="interest_rate" name="interest_rate" 
                                               value="<?php echo htmlspecialchars($property['interest_rate'] ?? 0); ?>" step="0.01" min="0" max="50">
                                        <span class="input-group-text">%</span>
                                        <?php if (isset($errors['interest_rate'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['interest_rate']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">0% for interest-free</small>
                                </div>
                                
                                <!-- Payment Term -->
                                <div class="col-md-6 mb-3">
                                    <label for="term_months" class="form-label">Payment Term <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">📆</span>
                                        <input type="number" class="form-control <?php echo isset($errors['term_months']) ? 'is-invalid' : ''; ?>" 
                                               id="term_months" name="term_months" 
                                               value="<?php echo htmlspecialchars($property['term_months']); ?>" min="1" max="360" required>
                                        <span class="input-group-text">months</span>
                                        <?php if (isset($errors['term_months'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['term_months']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contract Date -->
                            <div class="mb-3">
                                <label for="contract_date" class="form-label">Contract Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">📅</span>
                                    <input type="date" class="form-control <?php echo isset($errors['contract_date']) ? 'is-invalid' : ''; ?>" 
                                           id="contract_date" name="contract_date" 
                                           value="<?php echo htmlspecialchars($property['contract_date']); ?>" required>
                                    <?php if (isset($errors['contract_date'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['contract_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Property Images Section -->
                    <div class="card mt-4" id="images">
                        <div class="card-header">
                            <span>📷</span> Property Images
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Upload or replace property images (max 2MB each, JPG/PNG/WebP)</p>
                            
                            <?php if (!empty($images)): ?>
                            <div class="row mb-4">
                                <?php foreach ($images as $idx => $img_path): ?>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="position-relative">
                                        <img src="../<?php echo htmlspecialchars($img_path); ?>" 
                                             class="img-fluid rounded shadow-sm" 
                                             style="max-height: 150px; width: 100%; object-fit: cover;">
                                        <a href="property_edit.php?id=<?php echo $property_id; ?>&delete_image=<?php echo $idx; ?>" 
                                           class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                           onclick="return confirm('Delete this image?');"
                                           title="Delete Image">🗑️</a>
                                    </div>
                                    <small class="text-muted">Image <?php echo $idx; ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="col-md-6 mb-3">
                                    <label for="image_<?php echo $i; ?>" class="form-label">
                                        <?php echo isset($images[$i]) ? "Replace Image $i" : "Upload Image $i"; ?>
                                    </label>
                                    <input type="file" class="form-control" id="image_<?php echo $i; ?>" 
                                           name="image_<?php echo $i; ?>" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between mt-4 mb-4">
                        <a href="properties.php" class="btn btn-outline-secondary">
                            ✖ Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            ✓ Update Property
                        </button>
                    </div>
                </form>
                
                <!-- Payment Schedules Section -->
                <div class="card mt-4" id="schedules">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><span>📅</span> Payment Schedules</span>
                        <span class="badge bg-light text-dark"><?php echo count($schedules); ?> Schedules</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($schedules) === 0): ?>
                        <!-- Generate Schedule Button -->
                        <div class="text-center py-4">
                            <div style="font-size: 3rem; opacity: 0.3;">📅</div>
                            <h5 class="text-muted mt-3">No Payment Schedules Yet</h5>
                            <p class="text-muted mb-4">Generate schedules based on the loan terms.</p>
                            
                            <a href="generate_schedule.php?id=<?php echo $property_id; ?>" class="btn btn-primary btn-lg">
                                <span>⚡</span> Generate Payment Schedule
                            </a>
                            
                            <div class="alert alert-info mt-4 text-start">
                                <strong>ℹ️ Preview:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong><?php echo $term_months; ?></strong> monthly payments</li>
                                    <li><strong><?php echo format_peso($amort['monthly_payment']); ?></strong> per month</li>
                                    <li>Interest: <strong><?php echo $interest_rate; ?>%</strong> per annum</li>
                                </ul>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Schedule List -->
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-muted">
                                <strong><?php echo count($schedules); ?></strong> Payment Schedule(s)
                            </h6>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteAllSchedules()">
                                <span>🗑️</span> Delete All Schedules
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Principal</th>
                                        <th class="text-end">Interest</th>
                                        <th style="width: 90px;">Status</th>
                                        <th style="width: 60px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): 
                                        $status_badge = 'secondary';
                                        if ($schedule['status'] === 'paid') $status_badge = 'success';
                                        elseif ($schedule['status'] === 'overdue') $status_badge = 'danger';
                                        elseif ($schedule['status'] === 'pending') $status_badge = 'warning';
                                    ?>
                                    <tr class="<?php echo $schedule['schedule_number'] == 0 ? 'table-info' : ($schedule['status'] === 'paid' ? 'table-success' : ''); ?>">
                                        <td>
                                            <?php if ($schedule['schedule_number'] == 0): ?>
                                                <span class="badge bg-info text-dark">🔐 Deposit</span>
                                            <?php else: ?>
                                                <strong><?php echo $schedule['schedule_number']; ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($schedule['due_date'])); ?></td>
                                        <td class="text-end"><strong><?php echo format_peso($schedule['amount_due']); ?></strong></td>
                                        <td class="text-end"><?php echo format_peso($schedule['principal_amount'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo format_peso($schedule['interest_amount'] ?? 0); ?></td>
                                        <td><span class="badge bg-<?php echo $status_badge; ?>"><?php echo ucfirst($schedule['status']); ?></span></td>
                                        <td>
                                            <a href="property_edit.php?id=<?php echo $property_id; ?>&delete_schedule=<?php echo $schedule['schedule_id']; ?>" 
                                               class="btn btn-outline-danger btn-sm" 
                                               onclick="return confirm('Delete this schedule?');">🗑️</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="2">Total</th>
                                        <th class="text-end"><?php echo format_peso($total_paid + $total_pending + $total_overdue); ?></th>
                                        <th colspan="4"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>⚠️ Note:</strong> To regenerate schedules with new terms, delete all existing schedules first.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Delete All Schedules Confirmation Script -->
<script>
function confirmDeleteAllSchedules() {
    const scheduleCount = <?php echo count($schedules); ?>;
    const propertyName = '<?php echo addslashes($property['property_name']); ?>';
    
    const message = '⚠️ DANGER: Delete ALL Payment Schedules?\n\n' +
                    'Property: ' + propertyName + '\n' +
                    'Total Schedules: ' + scheduleCount + '\n\n' +
                    'This action CANNOT be undone!\n\n' +
                    'Type YES to confirm:';
    
    const userConfirm = prompt(message);
    
    if (userConfirm === 'YES') {
        window.location.href = 'property_edit.php?id=<?php echo $property_id; ?>&delete_all_schedules=confirm';
    } else if (userConfirm !== null) {
        alert('❌ Deletion cancelled. Type YES exactly to confirm.');
    }
}

// Initialize Select2 for client search
$(document).ready(function() {
    $('#client_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search for a client --',
        allowClear: false,
        width: '100%'
    });
});
</script>

<?php
// Include footer
include '../templates/footer.php';
?>
