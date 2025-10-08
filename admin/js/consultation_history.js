// Consultation History JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar collapse functionality
    const collapseLinks = document.querySelectorAll('[data-bs-toggle="collapse"]');
    collapseLinks.forEach(link => {
        link.addEventListener('click', function() {
            const icon = this.querySelector('.rotate-icon');
            icon.style.transition = 'transform 0.3s ease';
            icon.style.transform = icon.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    });

    // Consultation details modal
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Set modal content
            document.getElementById('modalDate').textContent = this.getAttribute('data-date');
            document.getElementById('modalDiagnosis').textContent = this.getAttribute('data-diagnosis') || 'No diagnosis provided';
            document.getElementById('modalSymptoms').textContent = this.getAttribute('data-symptoms') || 'No symptoms recorded';
            document.getElementById('modalTemperature').textContent = this.getAttribute('data-temperature') || 'Not recorded';
            document.getElementById('modalBloodPressure').textContent = this.getAttribute('data-blood-pressure') || 'Not recorded';
            document.getElementById('modalTreatment').textContent = this.getAttribute('data-treatment') || 'No treatment provided';
            document.getElementById('modalHeartRate').textContent = this.getAttribute('data-heart-rate') || 'Not recorded';
            document.getElementById('modalStaff').textContent = this.getAttribute('data-staff') || 'Not specified';
            document.getElementById('modalNotes').textContent = this.getAttribute('data-notes') || 'No additional notes';
            
            // Update modal title with date
            const modalTitle = document.getElementById('consultationModalLabel');
            modalTitle.textContent = `Consultation Details - ${this.getAttribute('data-date')}`;
        });
    });

    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.consultation-table tbody tr');
    tableRows.forEach(row => {
        if (row.cells[0].textContent.trim() !== '') {
            row.style.cursor = 'pointer';
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#e3f2fd';
            });
            
            row.addEventListener('mouseleave', function() {
                if (!this.classList.contains('table-active')) {
                    this.style.backgroundColor = '';
                }
            });
        }
    });
});