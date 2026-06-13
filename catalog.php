<?php
/**
 * Public Property Catalog
 * Real Estate Receivable System - Phase 6
 * 
 * Public listing of available properties (no login required)
 */

define('APP_NAME', 'Real Estate Receivable System');
define('DB_INCLUDE', true);

require_once 'includes/db_connect.php';

// Optional: Include auth for checking if logged in (for nav)
require_once 'includes/auth.php';
require_once 'includes/financial_helpers.php';

// Initialize defaults for inquiry form
$user_name = '';
$user_email = '';
$user_contact = '';

if (is_logged_in()) {
    $user_name = $_SESSION['username'];

    // If user is a client, fetch their specific details
    if (is_client() && isset($_SESSION['client_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT email, contact_no, name FROM clients WHERE client_id = ?");
            $stmt->execute([$_SESSION['client_id']]);
            $client_data = $stmt->fetch();

            if ($client_data) {
                // Use client profile name if available (often better than username)
                $user_name = !empty($client_data['name']) ? $client_data['name'] : $user_name;
                $user_email = $client_data['email'];
                $user_contact = $client_data['contact_no'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching client details for catalog: " . $e->getMessage());
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float) $_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float) $_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

try {
    // Build query for available properties
    $sql = "
        SELECT 
            p.*
        FROM properties p
        WHERE p.status = 'available'
    ";

    $params = [];

    // Apply search filter
    if (!empty($search)) {
        $sql .= " AND p.property_name LIKE ?";
        $search_param = "%{$search}%";
        $params[] = $search_param;
    }

    // Apply price filters
    if ($min_price > 0) {
        $sql .= " AND p.total_price >= ?";
        $params[] = $min_price;
    }
    if ($max_price > 0) {
        $sql .= " AND p.total_price <= ?";
        $params[] = $max_price;
    }

    // Apply sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY p.total_price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.total_price DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY p.created_at ASC";
            break;
        default: // newest
            $sql .= " ORDER BY p.created_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();

    // Get price range for filters
    $range_stmt = $pdo->query("
        SELECT MIN(total_price) as min_price, MAX(total_price) as max_price 
        FROM properties WHERE status = 'available'
    ");
    $price_range = $range_stmt->fetch();

} catch (PDOException $e) {
    error_log("Catalog error: " . $e->getMessage());
    $properties = [];
    $price_range = ['min_price' => 0, 'max_price' => 0];
}

$page_title = 'Property Catalog';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $page_title; ?> -
        <?php echo APP_NAME; ?>
    </title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/bootstrap/bootstrap.min.css">

    <style>
        :root {
            --primary-maroon: #800000;
            --dark-maroon: #5c0000;
            --light-maroon: #a32929;
            --mulled-wine: #4B4359;
            --beige: #F5F5DD;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-maroon) 100%);
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--beige) !important;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-maroon) 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 30px;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--beige);
        }

        .property-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .property-image {
            height: 200px;
            background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--primary-maroon) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.3);
            font-size: 4rem;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-maroon);
        }

        .property-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-maroon);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.3);
            color: white;
        }

        .footer {
            background: var(--mulled-wine);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        .btn-view-details {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .btn-view-details:hover {
            background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
            color: white;
        }

        .property-card .card-footer {
            padding-top: 20px;
        }

        /* Modal Styling */
        .property-modal .modal-body {
            padding: 0;
        }

        .property-modal .detail-section {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .property-modal .detail-section:last-child {
            border-bottom: none;
        }

        .property-modal .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--mulled-wine);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-maroon);
        }

        .property-modal .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .property-modal .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #212529;
            margin-bottom: 0;
        }

        .property-modal .price-highlight {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-maroon);
        }

        .property-modal .badge-custom {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .property-modal .description-text {
            line-height: 1.8;
            color: #495057;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="catalog.php">
                🏢 RERS Property Catalog
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (is_logged_in()): ?>
                        <?php if (is_client()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="client/dashboard.php">My Dashboard</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">Admin Dashboard</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn-primary-custom ms-2" href="auth/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1>🏘️ Available Properties</h1>
            <p class="lead">Find your dream property from our exclusive listings</p>
            <p class="text-light">
                <strong>
                    <?php echo count($properties); ?>
                </strong> properties available
            </p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Property name, location..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Price</label>
                    <input type="number" class="form-control" name="min_price" placeholder="₱0"
                        value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Price</label>
                    <input type="number" class="form-control" name="max_price" placeholder="₱∞"
                        value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to
                            High</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to
                            Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-custom w-100">
                        🔍 Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Properties Grid -->
        <?php if (count($properties) > 0): ?>
            <div class="row">
                <?php foreach ($properties as $property): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card property-card">
                            <div class="property-image position-relative">
                                <?php if (!empty($property['image_1'])): ?>
                                    <img src="<?php echo htmlspecialchars($property['image_1']); ?>"
                                        alt="<?php echo htmlspecialchars($property['property_name']); ?>">
                                <?php else: ?>
                                    🏠
                                <?php endif; ?>
                                <span class="property-badge">Available</span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($property['property_name']); ?>
                                </h5>
                                <?php if (!empty($property['location'])): ?>
                                <p class="text-muted mb-2">
                                    📍 <?php echo htmlspecialchars($property['location']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($property['square_meters'])): ?>
                                <p class="text-muted mb-2">
                                    📏 <?php echo number_format($property['square_meters'], 2); ?> sq.m.
                                </p>
                                <?php endif; ?>
                                <p class="property-price">
                                    <?php echo format_peso($property['total_price']); ?>
                                </p>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Security Deposit</small>
                                        <strong>
                                            <?php echo format_peso($property['security_deposit'] ?? 0); ?>
                                        </strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Term</small>
                                        <strong>
                                            <?php echo $property['term_months']; ?> months
                                        </strong>
                                    </div>
                                </div>

                                <?php
                                // Principal = full total price (security deposit is separate)
                                $principal = $property['total_price'];
                                $monthly = $property['term_months'] > 0 ? $principal / $property['term_months'] : 0;
                                ?>
                                <p class="text-center">
                                    <small class="text-muted">Estimated monthly:</small><br>
                                    <strong class="text-success">
                                        <?php echo format_peso($monthly); ?>/mo
                                    </strong>
                                </p>
                            </div>
                            <div class="card-footer bg-white border-0 text-center pb-3">
                                <!-- View Details Button - Always visible, full width -->
                                <button type="button" 
                                    class="btn btn-view-details"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#propertyModal<?php echo $property['property_id']; ?>">
                                    <i class="bi bi-info-circle"></i> 📋 View Full Details
                                </button>
                                
                                <!-- Primary Action Button - Based on user status -->
                                <?php if (is_logged_in() && is_client()): ?>
                                    <!-- Logged-in clients: View in dashboard -->
                                    <a href="client/dashboard.php" class="btn btn-primary-custom w-100">
                                        📊 View My Dashboard
                                    </a>
                                <?php elseif (is_logged_in()): ?>
                                    <!-- Admin/Finance: Go to properties -->
                                    <a href="modules/properties.php" class="btn btn-primary-custom w-100">
                                        🏢 Manage Properties
                                    </a>
                                <?php else: ?>
                                    <!-- Not logged in: Register to avail property -->
                                    <a href="auth/register.php?property=<?php echo $property['property_id']; ?>" 
                                       class="btn btn-primary-custom w-100 mb-2">
                                        ✨ Register to Avail This Property
                                    </a>
                                    <div class="mt-2">
                                        <small class="text-muted">Already have an account? 
                                            <a href="auth/login.php" style="color: var(--primary-maroon); font-weight: 600;">Login here</a>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Property Details Modals -->
            <?php foreach ($properties as $property): ?>
                <div class="modal fade property-modal" id="propertyModal<?php echo $property['property_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, var(--mulled-wine) 0%, var(--dark-maroon) 100%); color: white;">
                                <div>
                                    <h5 class="modal-title mb-1">🏠 <?php echo htmlspecialchars($property['property_name']); ?></h5>
                                    <?php if (!empty($property['location'])): ?>
                                    <small class="text-light"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($property['location']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- Property Images Section -->
                                <?php if (!empty($property['image_1']) || !empty($property['image_2']) || !empty($property['image_3']) || !empty($property['image_4'])): ?>
                                <div class="detail-section" style="padding: 0; border-bottom: none;">
                                    <div id="carousel<?php echo $property['property_id']; ?>" class="carousel slide" data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            <?php 
                                            $images = array_filter([$property['image_1'], $property['image_2'], $property['image_3'], $property['image_4']]);
                                            foreach ($images as $index => $image): ?>
                                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                    <img src="<?php echo htmlspecialchars($image); ?>" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Property Image">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($images) > 1): ?>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?php echo $property['property_id']; ?>" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#carousel<?php echo $property['property_id']; ?>" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                        <div class="carousel-indicators">
                                            <?php foreach ($images as $index => $image): ?>
                                                <button type="button" data-bs-target="#carousel<?php echo $property['property_id']; ?>" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Pricing Section -->
                                <div class="detail-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                                    <h6 class="section-title">💰 Pricing Information</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="info-label">Total Price</div>
                                            <div class="price-highlight"><?php echo format_peso($property['total_price']); ?></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="info-label">Security Deposit</div>
                                            <div class="info-value text-info"><?php echo format_peso($property['security_deposit'] ?? 0); ?></div>

                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Payment Term</div>
                                            <div class="info-value"><?php echo $property['term_months']; ?> months</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Interest Rate</div>
                                            <div class="info-value"><?php echo number_format($property['interest_rate'], 2); ?>% per annum</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Property Specifications -->
                                <div class="detail-section">
                                    <h6 class="section-title">📋 Property Specifications</h6>
                                    <div class="row">
                                        <?php if (!empty($property['square_meters'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="info-label">📏 Floor Area</div>
                                            <div class="info-value"><?php echo number_format($property['square_meters'], 2); ?> sq.m.</div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($property['location'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="info-label">📍 Location</div>
                                            <div class="info-value"><?php echo htmlspecialchars($property['location']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-12">
                                            <div class="info-label">Status</div>
                                            <span class="badge bg-success badge-custom">✓ Available for Purchase</span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($property['description'])): ?>
                                <!-- Description Section -->
                                <div class="detail-section">
                                    <h6 class="section-title">📝 Property Description</h6>
                                    <div class="description-text"><?php echo nl2br(htmlspecialchars($property['description'])); ?></div>
                                </div>
                                <?php endif; ?>

                                <!-- Payment Estimate -->
                                <div class="detail-section" style="background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);">
                                    <h6 class="section-title">💳 Estimated Monthly Payment</h6>
                                    <?php
                                    // Principal = full total price (security deposit is separate)
                                    $principal = $property['total_price'];
                                    $monthly = $property['term_months'] > 0 ? $principal / $property['term_months'] : 0;
                                    ?>
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="price-highlight text-primary"><?php echo format_peso($monthly); ?><span class="h5 text-muted">/month</span></div>
                                            <small class="text-muted">Based on <?php echo $property['term_months']; ?>-month term without interest</small>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="info-label">Total Payable</div>
                                            <div class="info-value"><?php echo format_peso($property['total_price']); ?></div>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <small><strong>⚠️ Note:</strong> Actual monthly payment may vary depending on interest rate and payment schedule. Contact us for accurate amortization.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer" style="background-color: #f8f9fa;">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">← Back to Catalog</button>
                                <?php if (!is_logged_in()): ?>
                                <a href="auth/register.php?property=<?php echo $property['property_id']; ?>" class="btn btn-primary-custom">
                                    ✨ Register to Avail This Property
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🏘️</div>
                <h3>No Properties Found</h3>
                <?php if (!empty($search) || $min_price > 0 || $max_price > 0): ?>
                    <p class="text-muted">Try adjusting your filters or search criteria.</p>
                    <a href="catalog.php" class="btn btn-primary-custom">Clear Filters</a>
                <?php else: ?>
                    <p class="text-muted">There are currently no available properties. Please check back later.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>



    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p class="mb-2">🏢 Real Estate Receivable System</p>
            <p class="text-light small mb-0">
                ©
                <?php echo date('Y'); ?> RERS. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="assets/bootstrap/bootstrap.bundle.min.js"></script>


</body>

</html>