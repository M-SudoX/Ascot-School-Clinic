<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once('../includes/db_connect.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $title = $_POST['title'];
    
    $query = "UPDATE college_physician SET name = ?, title = ? WHERE id = 1";
    $stmt = $pdo->prepare($query);
    
    if ($stmt->execute([$name, $title])) {
        $message = "College Physician updated successfully!";
    } else {
        $error = "Error updating College Physician.";
    }
}

// Get current physician data
$query = "SELECT name, title FROM college_physician WHERE id = 1";
$stmt = $pdo->query($query);
$physician = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$physician) {
    // Insert default if not exists
    $query = "INSERT INTO college_physician (id, name, title) VALUES (1, 'MARILYN R. GANTE, MD', 'College Physician')";
    $pdo->exec($query);
    $physician = ['name' => 'MARILYN R. GANTE, MD', 'title' => 'College Physician'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage College Physician</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage College Physician</h1>
        
        <?php if (isset($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="name">Physician Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($physician['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($physician['title']); ?>" required>
            </div>
            
            <button type="submit" class="btn">Update Physician</button>
        </form>
        
        <p style="margin-top: 20px;">
            <a href="view_records.php">← Back to Records</a>
        </p>
    </div>
</body>
</html>