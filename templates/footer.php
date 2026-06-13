<?php
/**
 * Footer Template (Simplified)
 * Real Estate Receivable System - Phase 7
 * 
 * Minimal footer - does NOT close any wrapper divs
 * Modules must close their own container-fluid, main-content, and main-wrapper divs
 */

// Use same asset path detection as header
if (!isset($asset_path)) {
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $dir_name = basename($script_dir);

    if ($dir_name === 'real_estate_receivable_system') {
        $asset_path = '';
    } elseif (in_array($dir_name, ['modules', 'auth', 'reports', 'api', 'client'])) {
        $asset_path = '../';
    } else {
        $asset_path = '';
    }
}
?>

<style>
    /* Simplified Footer Styles */
    .main-footer {
        background-color: var(--mulled-wine, #4B4359);
        color: rgba(255, 255, 255, 0.8);
        padding: 15px 20px;
        margin-top: auto;
        font-size: 0.85rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .main-footer a {
        color: var(--beige, #F5F5DD);
        text-decoration: none;
    }

    .main-footer a:hover {
        text-decoration: underline;
    }

    .footer-version {
        opacity: 0.7;
    }

    @media (max-width: 768px) {
        .main-footer {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<footer class="main-footer">
    <div>
        &copy; <?php echo date('Y'); ?> <a href="#">Real Estate Receivable System</a>. All Rights Reserved.
    </div>
    <div class="footer-version">
        Version 2.0.0 | Powered by <strong>RERS Team</strong>
    </div>
</footer>

</div><!-- /.main-wrapper -->

<!-- Bootstrap JS (Offline) -->
<script src="<?php echo $asset_path; ?>assets/bootstrap/bootstrap.bundle.min.js"></script>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo $asset_path; ?>assets/js/custom.js"></script>

<!-- Additional Scripts -->
<?php if (isset($extra_js))
    echo $extra_js; ?>

<!-- Auto-hide alerts and tooltips -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function (alert) {
            setTimeout(function () {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>

</body>

</html>