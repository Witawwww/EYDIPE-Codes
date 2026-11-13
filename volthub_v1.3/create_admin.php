<?php
require_once 'db.php';

// 1. I-set ang username at password
$username = 'admin';
$password = 'admin123';

// 2. Gagawa tayo ng fresh hash gamit ang PHP
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 3. Burahin muna ang lumang admin kung meron man (para iwas duplicate error)
$stmt = $GLOBALS['connection']->prepare("DELETE FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);

// 4. I-insert ang bagong admin account na may tamang hash
$stmt = $GLOBALS['connection']->prepare(
    "INSERT INTO users (name, username, email, password, role) 
     VALUES ('Store Admin', :username, 'admin@volthub.com', :password, 'admin')"
);

$stmt->execute([
    ':username' => $username,
    ':password' => $hashed_password
]);

echo "<h1>Success!</h1>";
echo "<p>Admin account has been created/updated.</p>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<p><a href='login_reg.php'>Go to Login Page</a></p>";
?>