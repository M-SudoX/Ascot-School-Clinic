// Aurora State College Student Consultation System
// JavaScript for handling consultation form and schedule

// Global variables
let consultations = [
    {
        id: 1,
        date: '2024-09-15',
        time: '10:30',
        concern: 'Medicine',
        notes: 'Need pain medication for headache',
        status: 'Approved'
    },
    {
        id: 2,
        date: '2024-09-20',
        time: '08:00',
        concern: 'Medical Clearance',
        notes: 'Required for enrollment',
        status: 'Pending'
    }
];

let nextId = 3;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Student Consultation System Initialized');
    initializeForm();
    loadConsultations();
    setupEventListeners();
});

// Initialize form settings
function initializeForm() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('consultationDate');
    if (dateInput) {
        dateInput.min = today;
        
        // Set default date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        dateInput.value = tomorrow.toISOString().split('T')[0];
    }
    
    // Load saved form data if exists
    loadSavedFormData();
}

// Setup event listeners
function setupEventListeners() {
    // Form submission
    const form = document.getElementById('consultationForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    // Auto-save form data as user types
    const formInputs = document.querySelectorAll('.form-control');
    formInputs.forEach(input => {
        input.addEventListener('change', autoSaveFormData);
        input.addEventListener('input', debounce(autoSaveFormData, 500));
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('consultationModal');
        if (event.target === modal) {
            closeModal();
        }
    });
}

// Handle form submission
function handleFormSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const consultationData = {
        id: nextId++,
        date: formData.get('date'),
        time: formData.get('time'),
        concern: formData.get('concern'),
        notes: formData.get('notes') || '',
        status: 'Pending'
    };
    
    // Validate form data
    if (!validateFormData(consultationData)) {
        return;
    }
    
    // Check for conflicts
    if (hasTimeConflict(consultationData)) {
        showAlert('This time slot is already taken. Please choose another time.', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = event.target.querySelector('.btn-request');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> REQUESTING...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Add consultation to list
        consultations.push(consultationData);
        
        // Update table
        loadConsultations();
        
        // Clear form
        event.target.reset();
        clearSavedFormData();
        
        // Reset date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('consultationDate').value = tomorrow.toISOString().split('T')[0];
        
        // Show success message
        showAlert('Consultation request submitted successfully! You will be notified once approved.', 'success');
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Scroll to schedule section
        document.querySelector('.consultation-schedule').scrollIntoView({ 
            behavior: 'smooth' 
        });
        
    }, 2000); // 2 second delay to simulate server processing
}

// Validate form data
function validateFormData(data) {
    if (!data.date || !data.time || !data.concern) {
        showAlert('Please fill in all required fields.', 'error');
        return false;
    }
    
    // Check if date is in the future
    const selectedDate = new Date(data.date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        showAlert('Please select a future date.', 'error');
        return false;
    }
    
    // Check if it's not a weekend (optional business rule)
    const dayOfWeek = selectedDate.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        if (!confirm('Selected date is a weekend. Clinic may be closed. Continue anyway?')) {
            return false;
        }
    }
    
    return true;
}

// Check for time conflicts
function hasTimeConflict(newConsultation) {
    return consultations.some(consultation => 
        consultation.date === newConsultation.date && 
        consultation.time === newConsultation.time && 
        consultation.status !== 'Cancelled'
    );
}

// Load consultations into table
function loadConsultations() {
    const tableBody = document.getElementById('scheduleTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    if (consultations.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 30px; color: #6c757d;">
                    <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 10px;"></i>
                    <br>No consultations scheduled
                </td>
            </tr>
        `;
        return;
    }
    
    // Sort consultations by date and time (newest first)
    const sortedConsultations = [...consultations].sort((a, b) => {
        const dateA = new Date(a.date + ' ' + a.time);
        const dateB = new Date(b.date + ' ' + b.time);
        return dateB - dateA;
    });
    
    sortedConsultations.forEach(consultation => {
        const row = createConsultationRow(consultation);
        tableBody.appendChild(row);
    });
}

// Create consultation row
function createConsultationRow(consultation) {
    const row = document.createElement('tr');
    const formattedDate = formatDate(consultation.date);
    const formattedTime = formatTime(consultation.time);
    
    row.innerHTML = `
        <td>${formattedDate}</td>
        <td>${formattedTime}</td>
        <td>${consultation.concern}</td>
        <td><span class="status-${consultation.status.toLowerCase()}">${consultation.status}</span></td>
        <td>${createActionButtons(consultation)}</td>
    `;
    
    return row;
}

// Create action buttons based on status
function createActionButtons(consultation) {
    let buttons = `
        <button class="btn-action btn-view" onclick="viewConsultation(${consultation.id})" title="View Details">
            <i class="fas fa-eye"></i>
        </button>
    `;
    
    if (consultation.status === 'Pending') {
        buttons += `
            <button class="btn-action btn-edit" onclick="editConsultation(${consultation.id})" title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn-action btn-cancel" onclick="cancelConsultation(${consultation.id})" title="Cancel">
                <i class="fas fa-times"></i>
            </button>
        `;
    }
    
    return buttons;
}

// Format date for display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

// Format time for display
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// View consultation details
function viewConsultation(id) {
    const consultation = consultations.find(c => c.id === id);
    if (!consultation) return;
    
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div style="margin-bottom: 15px;">
            <strong>Date:</strong> ${formatDate(consultation.date)}
        </div>
        <div style="margin-bottom: 15px;">
            <strong>Time:</strong> ${formatTime(consultation.time)}
        </div>
        <div style="margin-bottom: 15px;">
            <strong>Concern:</strong> ${consultation.concern}
        </div>
        <div style="margin-bottom: 15px;">
            <strong>Status:</strong> 
            <span class="status-${consultation.status.toLowerCase()}">${consultation.status}</span>
        </div>
        ${consultation.notes ? `
        <div style="margin-bottom: 15px;">
            <strong>Additional Notes:</strong>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px;">
                ${consultation.notes}
            </div>
        </div>
        ` : ''}
        <div style="margin-bottom: 15px;">
            <strong>Request ID:</strong> #${consultation.id.toString().padStart(4, '0')}
        </div>
    `;
    
    document.getElementById('consultationModal').style.display = 'block';
}

// Edit consultation
function editConsultation(id) {
    const consultation = consultations.find(c => c.id === id);
    if (!consultation || consultation.status !== 'Pending') {
        showAlert('Only pending consultations can be edited.', 'error');
        return;
    }
    
    // Populate form with existing data
    document.getElementById('consultationDate').value = consultation.date;
    document.getElementById('consultationTime').value = consultation.time;
    document.getElementById('reasonConcern').value = consultation.concern;
    document.getElementById('optionalNote').value = consultation.notes;
    
    // Remove the consultation from the list (it will be re-added when form is submitted)
    consultations = consultations.filter(c => c.id !== id);
    loadConsultations();
    
    // Scroll to form
    document.querySelector('.consultation-form-container').scrollIntoView({ 
        behavior: 'smooth' 
    });
    
    showAlert('Consultation loaded for editing. Make your changes and submit again.', 'success');
}

// Cancel consultation
function cancelConsultation(id) {
    const consultation = consultations.find(c => c.id === id);
    if (!consultation) return;
    
    if (consultation.status !== 'Pending') {
        showAlert('Only pending consultations can be cancelled.', 'error');
        return;
    }
    
    const confirmMessage = `Are you sure you want to cancel your consultation on ${formatDate(consultation.date)} at ${formatTime(consultation.time)}?`;
    
    if (confirm(confirmMessage)) {
        // Update status to cancelled
        consultation.status = 'Cancelled';
        loadConsultations();
        showAlert('Consultation has been cancelled successfully.', 'success');
    }
}

// Show alert messages
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    alertDiv.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
    
    // Scroll to alert
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Close modal
function closeModal() {
    document.getElementById('consultationModal').style.display = 'none';
}

// Handle logout
function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear saved data
        clearSavedFormData();
        localStorage.removeItem('consultations');
        
        // Show logout message
        showAlert('Logged out successfully! Redirecting...', 'success');
        
        // Redirect after 2 seconds
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
    }
}

// Mobile sidebar functions
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
}

// Auto-save form data
function autoSaveFormData() {
    const formData = {
        date: document.getElementById('consultationDate').value,
        time: document.getElementById('consultationTime').value,
        concern: document.getElementById('reasonConcern').value,
        notes: document.getElementById('optionalNote').value
    };
    
    localStorage.setItem('consultationFormData', JSON.stringify(formData));
}

// Load saved form data
function loadSavedFormData() {
    const savedData = localStorage.getItem('consultationFormData');
    if (savedData) {
        try {
            const formData = JSON.parse(savedData);
            
            if (formData.date) document.getElementById('consultationDate').value = formData.date;
            if (formData.time) document.getElementById('consultationTime').value = formData.time;
            if (formData.concern) document.getElementById('reasonConcern').value = formData.concern;
            if (formData.notes) document.getElementById('optionalNote').value = formData.notes;
        } catch (e) {
            console.error('Error loading saved form data:', e);
        }
    }
}

// Clear saved form data
function clearSavedFormData() {
    localStorage.removeItem('consultationFormData');
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Save consultations to localStorage (for demo purposes)
function saveConsultationsToStorage() {
    localStorage.setItem('consultations', JSON.stringify(consultations));
}

// Load consultations from localStorage
function loadConsultationsFromStorage() {
    const saved = localStorage.getItem('consultations');
    if (saved) {
        try {
            consultations = JSON.parse(saved);
        } catch (e) {
            console.error('Error loading consultations from storage:', e);
        }
    }
}

// Auto-save consultations when they change
function saveConsultations() {
    saveConsultationsToStorage();
}

// Initialize consultations from storage
loadConsultationsFromStorage();

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Escape key to close modal
    if (event.key === 'Escape') {
        closeModal();
        closeSidebar();
    }
    
    // Ctrl/Cmd + S to save form (prevent default save dialog)
    if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        event.preventDefault();
        autoSaveFormData();
        showAlert('Form data saved locally!', 'success');
    }
});

// Auto-refresh status every 30 seconds (simulate real-time updates)
setInterval(() => {
    // In a real application, this would fetch data from the server
    // For demo, we'll just simulate random status updates
    const pendingConsultations = consultations.filter(c => c.status === 'Pending');
    
    if (pendingConsultations.length > 0 && Math.random() < 0.1) { // 10% chance
        const randomConsultation = pendingConsultations[Math.floor(Math.random() * pendingConsultations.length)];
        randomConsultation.status = Math.random() < 0.8 ? 'Approved' : 'Cancelled';
        
        loadConsultations();
        showAlert(`Your consultation on ${formatDate(randomConsultation.date)} has been ${randomConsultation.status.toLowerCase()}!`, 'success');
    }
}, 30000);

// Page visibility change handler
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Page became visible, reload consultations
        loadConsultations();
    }
});

console.log('Student Consultation System JavaScript loaded successfully!');