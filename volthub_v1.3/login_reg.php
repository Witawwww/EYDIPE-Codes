<?php

/**
 * VoltHub — login_reg.php
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
  if (current_admin()) redirect('admin.php');
  else redirect('index.php');
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['login'])) {
    $emailUser = trim($_POST['email_User']);
    $password = trim($_POST['password']);

    if ($emailUser === "" || $password === "") {
      $errorMessage = "Please enter your Email/Username and Password.";
    } else {
      $statement = $GLOBALS['connection']->prepare(
        "SELECT * FROM users WHERE email = :email OR username = :username"
      );
      $statement->execute([
        ':email' => $emailUser,
        ':username' => $emailUser
      ]);
      $userData = $statement->fetch();

      if ($userData && password_verify($password, $userData['password'])) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['role'] = $userData['role'];

        if ($userData['role'] === 'admin') {
          header("Location: admin.php");
        } else {
          header("Location: index.php");
        }
        exit;
      } else {
        $errorMessage = "Incorrect email/username or password.";
      }
    }
  }

  if (isset($_POST['register'])) {
    $tab = 'register';
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($name === "" || $email === "" || $username === "" || $password === "") {
      $errorMessage = "Please fill in all fields.";
    } else {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

      try {
        $stmt = $GLOBALS['connection']->prepare(
          "INSERT INTO users (name, email, username, password, role) VALUES (:name, :email, :username, :password, 'user')"
        );
        $stmt->execute([
          ':name' => $name,
          ':email' => $email,
          ':username' => $username,
          ':password' => $hashedPassword
        ]);

        flash('Account created successfully! Please log in.', 'success');
        redirect('login_reg.php');
      } catch (PDOException $e) {
        $errorMessage = "Email or Username already exists.";
      }
    }
  }
}

page_head('Log In — VoltHub');
?>

<div class="auth">
  <aside class="auth__brand">
    <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
    <div>
      <h1>Buy your wants and needs of gadgets at our store!</h1>
      <p>The most trusted store for gadgets!</p>
    </div>
    <small>© <?= date('Y') ?> VoltHub</small>
  </aside>

  <main class="auth__panel">
    <div class="auth__card">
      <a href="index.php" class="brand auth__mobile-brand"><span class="brand__mark">⚡</span>VoltHub</a>

      <div>
        <h2><?= $tab === 'login' ? 'Welcome back' : 'Create your account' ?></h2>
        <p class="muted"><?= $tab === 'login' ? 'Sign in to check the store.' : 'Set up an account to get started.' ?></p>
      </div>

      <div class="tabs">
        <a href="login_reg.php?tab=login" class="tab <?= $tab === 'login' ? 'active' : '' ?>">Log in</a>
        <a href="login_reg.php?tab=register" class="tab <?= $tab === 'register' ? 'active' : '' ?>">Register</a>
      </div>

      <?php if ($errorMessage !== ''): ?>
        <div class="form-error"><?= e($errorMessage) ?></div>
      <?php endif; ?>

      <?php if ($tab === 'login'): ?>
        <form method="post" action="login_reg.php">
          <div class="field">
            <label>Email or Username</label>
            <input class="input" type="text" name="email_User" required>
          </div>
          <div class="field">
            <label>Password</label>
            <input class="input" type="password" name="password" required>
          </div>
          <button class="btn btn--primary btn--block" type="submit" name="login">Log in</button>
        </form>
      <?php else: ?>
        <form method="post" action="login_reg.php">
          <div class="field">
            <label>Full Name</label>
            <input class="input" type="text" name="name" required>
          </div>
          <div class="field">
            <label>Email</label>
            <input class="input" type="email" name="email" required>
          </div>
          <div class="field">
            <label>Username</label>
            <input class="input" type="text" name="username" required>
          </div>
          <div class="field">
            <label>Password</label>
            <input class="input" type="password" name="password" required>
          </div>
          <button class="btn btn--primary btn--block" type="submit" name="register">Create account</button>
        </form>
      <?php endif; ?>

      <p class="back-link"><a href="index.php">← Back to store</a></p>
    </div>
  </main>
</div>

<?php page_end(); ?>