<?php
require_once 'db.php';

// 1. Set the username and password
$username = 'admin';
$password = 'admin123';

// 2. Make a fresh hash with PHP
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 3. Delete the old admin first, if there is one (avoids a duplicate error)
$stmt = $GLOBALS['connection']->prepare("DELETE FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);

// 4. Insert the new admin account with the correct hash
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