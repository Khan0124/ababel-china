<?php
// create-admin.php - Run this from command line to create first admin user
require_once __DIR__ . '/app/Core/Database.php';

// Create admin user
$db = \App\Core\Database::getInstance();

echo "Creating admin user...\n";

// Check if users table exists
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "Users already exist in the database.\n";
        echo "Do you want to create another admin user? (y/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) !== 'y') {
            exit("Cancelled.\n");
        }
    }
} catch (Exception $e) {
    die("Error: Make sure the database is properly set up.\n");
}

// Get admin details
echo "Enter username: ";
$username = trim(fgets(STDIN));

echo "Enter password: ";
$password = trim(fgets(STDIN));

echo "Enter full name: ";
$fullName = trim(fgets(STDIN));

echo "Enter email: ";
$email = trim(fgets(STDIN));

// Create user
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, password, full_name, email, role, is_active) 
        VALUES (?, ?, ?, ?, 'admin', 1)";

try {
    $stmt = $db->query($sql, [$username, $hashedPassword, $fullName, $email]);
    echo "\nAdmin user created successfully!\n";
    echo "You can now login with username: $username\n";
} catch (Exception $e) {
    die("\nError creating user: " . $e->getMessage() . "\n");
}