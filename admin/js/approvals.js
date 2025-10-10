// approvals.js - Specific JavaScript for approvals page

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the approvals page functionality
    initApprovalsPage();
});

function initApprovalsPage() {
    // Initialize sidebar dropdown functionality
    initSidebarDropdowns();
    
    // Initialize notification dropdown
    initNotificationDropdown();
    
    // Initialize approval buttons functionality
    initApprovalButtons();
}

// Sidebar dropdown functionality
function initSidebarDropdowns() {
    const dropdownButtons = document.querySelectorAll('.dropdown-btn');
    
    dropdownButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);
            const arrow = this.querySelector('.arrow');

            // Close other open submenus
            document.querySelectorAll('.submenu').forEach(menu => {
                if (menu.id !== targetId && menu.classList.contains('show')) {
                    menu.classList.remove('show');
                    const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                    if (otherBtn) {
                        otherBtn.querySelector('.arrow').classList.remove('rotate');
                    }
                }
            });

            // Toggle current submenu
            submenu.classList.toggle('show');
            arrow.classList.toggle('rotate');
        });
    });
}

// Notification dropdown functionality
function initNotificationDropdown() {
    const notifBtn = document.getElementById('notifBtn');
    const notifMenu = document.getElementById('notifMenu');

    if (notifBtn && notifMenu) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!notifMenu.contains(e.target) && !notifBtn.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });
    }
}

// Approval buttons functionality
function initApprovalButtons() {
    // Approve button functionality
    document.querySelectorAll('.btn-approve').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const name = row.cells[0].textContent.trim();
            const reason = row.cells[1].textContent;
            const requested = row.cells[2].textContent;
            
            if (confirm(`Approve appointment for ${name}?\nReason: ${reason}\nRequested: ${requested}`)) {
                // In a real application, you would send an AJAX request here
                processApproval(row, 'approved');
            }
        });
    });

    // Reschedule button functionality
    document.querySelectorAll('.btn-reschedule').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const name = row.cells[0].textContent.trim();
            
            if (confirm(`Reschedule appointment for ${name}?`)) {
                // In a real application, you would open a rescheduling modal here
                openRescheduleModal(row);
            }
        });
    });

    // Decline button functionality
    document.querySelectorAll('.btn-decline').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const name = row.cells[0].textContent.trim();
            const reason = row.cells[1].textContent;
            
            if (confirm(`Decline appointment for ${name}?\nReason: ${reason}`)) {
                // In a real application, you would send an AJAX request here
                processApproval(row, 'declined');
            }
        });
    });
}

// Process approval action
function processApproval(row, action) {
    const name = row.cells[0].textContent.trim();
    
    // Show loading state
    const actionButtons = row.querySelector('.action-buttons');
    const originalContent = actionButtons.innerHTML;
    actionButtons.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Simulate API call delay
    setTimeout(() => {
        // Remove the row from the table
        row.remove();
        
        // Update pending count
        updatePendingCount();
        
        // Show success message
        showNotification(`${name}'s appointment has been ${action}.`, 'success');
    }, 1000);
}

// Open reschedule modal (placeholder function)
function openRescheduleModal(row) {
    const name = row.cells[0].textContent.trim();
    const requested = row.cells[2].textContent;
    
    // In a real application, this would open a modal for rescheduling
    alert(`Rescheduling functionality for ${name}\nOriginal time: ${requested}\n\nThis would open a rescheduling modal in a complete implementation.`);
}

// Update pending requests count
function updatePendingCount() {
    const tableRows = document.querySelectorAll('.approvals-table tbody tr');
    const pendingCount = tableRows.length;
    
    // Update the badge in the header
    const pendingBadge = document.querySelector('.pending-badge');
    if (pendingBadge) {
        pendingBadge.textContent = pendingCount;
    }
    
    // Update the notification count
    const notifCount = document.getElementById('notifCount');
    if (notifCount) {
        notifCount.textContent = pendingCount;
    }
    
    // Show empty state if no pending requests
    if (pendingCount === 0) {
        showEmptyState();
    }
}

// Show empty state when no pending requests
function showEmptyState() {
    const tableBody = document.querySelector('.approvals-table tbody');
    tableBody.innerHTML = `
        <tr>
            <td colspan="4" class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Pending Requests</h3>
                <p>All appointment requests have been processed.</p>
            </td>
        </tr>
    `;
}

// Show notification message
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 120px;
        right: 20px;
        z-index: 1000;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Export functions for potential use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initApprovalsPage,
        processApproval,
        updatePendingCount
    };
}