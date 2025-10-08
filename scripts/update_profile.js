// Show requirements when password field is focused
function showPasswordRequirements() {
    const requirements = document.getElementById('password-requirements');
    if (requirements) {
        requirements.style.display = 'block';
    }
}

// Check password strength as user types
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const requirements = document.getElementById('password-requirements');
    
    // Check if password meets requirements
    const hasLength = password.length >= 6;
    const hasSpecial = /[!@#$%^&*]/.test(password);
    
    // Update requirements display
    if (requirements) {
        if (password.length === 0) {
            requirements.innerHTML = '<small>Must contain special character: !@#$%^&*</small>';
            requirements.style.color = '#6c757d';
        } else if (hasLength && hasSpecial) {
            requirements.innerHTML = '<small>✓ Password meets requirements</small>';
            requirements.style.color = 'green';
        } else if (!hasSpecial) {
            requirements.innerHTML = '<small>✗ Missing special character: !@#$%^&*</small>';
            requirements.style.color = 'red';
        } else if (!hasLength) {
            requirements.innerHTML = '<small>✗ Too short (min. 6 characters)</small>';
            requirements.style.color = 'red';
        }
    }
    
    // Visual feedback on password field
    const passwordField = document.getElementById('password');
    if (password.length > 0) {
        if (hasLength && hasSpecial) {
            passwordField.style.borderColor = 'green';
        } else {
            passwordField.style.borderColor = 'red';
        }
    } else {
        passwordField.style.borderColor = '';
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchElement = document.getElementById('password-match');
    
    if (confirmPassword === '') {
        matchElement.innerHTML = '';
        matchElement.style.color = '';
    } else if (password === confirmPassword) {
        matchElement.innerHTML = '✓ Passwords match';
        matchElement.style.color = 'green';
    } else {
        matchElement.innerHTML = '✗ Passwords do not match';
        matchElement.style.color = 'red';
    }
}

function validatePassword() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check if passwords match
    if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return false;
    }
    
    // Check password strength
    const hasLength = password.length >= 6;
    const hasSpecial = /[!@#$%^&*]/.test(password);
    
    if (!hasLength || !hasSpecial) {
        alert('Password must be at least 6 characters long and contain at least one special character (!@#$%^&*)');
        return false;
    }
    
    return true;
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (passwordField) {
        passwordField.addEventListener('input', checkPasswordStrength);
    }
    
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', checkPasswordMatch);
    }
});