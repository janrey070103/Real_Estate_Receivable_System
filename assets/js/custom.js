/**
 * Custom JavaScript - Real Estate Receivable System
 * Common functions and utilities
 */

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('RERS System Loaded');
    
    // Initialize tooltips if Bootstrap tooltips are used
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers if Bootstrap popovers are used
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

/**
 * Format currency to Philippine Peso
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Confirm delete action
 */
function confirmDelete(message) {
    message = message || 'Are you sure you want to delete this item? This action cannot be undone.';
    return confirm(message);
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    if (element) {
        element.innerHTML = '<div class="spinner-border spinner-orange text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
    }
}

/**
 * Hide loading spinner
 */
function hideLoading(element, content) {
    if (element) {
        element.innerHTML = content || '';
    }
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number (Philippine format)
 */
function isValidPhone(phone) {
    const re = /^(09|\+639)\d{9}$/;
    return re.test(phone.replace(/[\s-]/g, ''));
}

/**
 * Auto-hide alerts after specified time
 */
function autoHideAlert(alertElement, delay) {
    delay = delay || 5000;
    setTimeout(function() {
        if (alertElement && bootstrap.Alert) {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }
    }, delay);
}

/**
 * Print current page
 */
function printPage() {
    window.print();
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename) {
    filename = filename || 'export.csv';
    const table = document.getElementById(tableId);
    
    if (!table) {
        alert('Table not found');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Download CSV file
 */
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Copied to clipboard!');
    }, function() {
        alert('Failed to copy');
    });
}

/**
 * Scroll to top smoothly
 */
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add scroll-to-top button functionality
window.addEventListener('scroll', function() {
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        if (window.pageYOffset > 300) {
            scrollBtn.style.display = 'block';
        } else {
            scrollBtn.style.display = 'none';
        }
    }
});

/**
 * Session Keep-Alive
 * Ping the server every 5 minutes to keep session active
 * This prevents automatic logout due to inactivity
 */
function keepSessionAlive() {
    // Only run if user is logged in (check if we're not on login page)
    if (!window.location.href.includes('/auth/login.php')) {
        fetch(window.location.href, {
            method: 'HEAD',
            cache: 'no-cache'
        }).catch(function(error) {
            // Silently fail - don't alert user
            console.log('Session keep-alive ping failed:', error);
        });
    }
}

// Run keep-alive every 5 minutes (300000 ms)
setInterval(keepSessionAlive, 300000);

// Run initial keep-alive after 1 minute
setTimeout(keepSessionAlive, 60000);
