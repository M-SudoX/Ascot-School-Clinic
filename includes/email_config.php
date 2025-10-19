<?php
// Email Configuration for ASCOT Online Clinic
define('EMAIL_HOST', 'smtp.gmail.com'); // Your SMTP host
define('EMAIL_USERNAME', 'cachemeifucan05@gmail.com'); // Your email
define('EMAIL_PASSWORD', 'zusittxqokhgzotm'); // Your app password
define('EMAIL_PORT', 587); // SMTP port
define('EMAIL_ENCRYPTION', 'tls'); // Encryption type
define('EMAIL_FROM', 'noreply@ascot.edu.ph'); // From email address
define('EMAIL_FROM_NAME', 'ASCOT Online Clinic');

// Email Settings
define('EMAIL_TIMEOUT', 30); // Timeout in seconds
define('EMAIL_DEBUG', false); // Set to true for debugging

// Batch sending settings
define('EMAIL_BATCH_SIZE', 50); // Number of emails to send per batch
define('EMAIL_BATCH_DELAY', 2); // Delay between batches in seconds
?>