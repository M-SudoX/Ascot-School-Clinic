// Add Consultation JavaScript
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

    // Form validation
    const form = document.querySelector('.consultation-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        const symptoms = document.getElementById('symptoms').value.trim();
        const diagnosis = document.getElementById('diagnosis').value.trim();
        
        if (!symptoms || !diagnosis) {
            alert('Please fill in at least Symptoms and Diagnosis fields.');
            return;
        }
        
        // If validation passes, submit the form
        this.submit();
    });

    // Auto-format inputs
    const temperatureInput = document.getElementById('temperature');
    const bloodPressureInput = document.getElementById('blood_pressure');
    const heartRateInput = document.getElementById('heart_rate');

    temperatureInput.addEventListener('blur', function() {
        if (this.value && !this.value.includes('°C')) {
            this.value = this.value + '°C';
        }
    });

    bloodPressureInput.addEventListener('blur', function() {
        if (this.value && !this.value.includes('mmHg')) {
            this.value = this.value + ' mmHg';
        }
    });

    heartRateInput.addEventListener('blur', function() {
        if (this.value && !this.value.includes('bpm')) {
            this.value = this.value + ' bpm';
        }
    });
});