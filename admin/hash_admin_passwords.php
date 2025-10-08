<?php
require '../includes/db_connect.php';

try {
    $stmt = $pdo->query("SELECT id, username, password FROM admin");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$admins) {
        echo "⚠️ Walang records sa admin table.";
        exit;
    }

    foreach ($admins as $admin) {
        $id = $admin['id'];
        $user = $admin['username'];
        $plain_password = $admin['password'];

        // Check kung hashed na (starts with $2y$)
        if (strpos($plain_password, '$2y$') === 0) {
            echo "✅ Admin ($user) password already hashed.<br>";
            continue;
        }

        // Hash the password
        $hashed = password_hash($plain_password, PASSWORD_DEFAULT);

        // Update sa DB
        $update = $pdo->prepare("UPDATE admin SET password = :password WHERE id = :id");
        $update->execute([
            ':password' => $hashed,
            ':id' => $id
        ]);

        echo "🔑 Admin ($user) password updated to hashed.<br>";
    }

    echo "<br>Done!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
