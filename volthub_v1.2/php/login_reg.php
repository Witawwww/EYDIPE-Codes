<?php
/**
 * VoltHub — php/login_reg.php (Login + Register)
 * Features: register account (validated), log in with hashed password, role-based redirect.
 */
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/layout.php';

if ($cu = current_user()) redirect($cu['role'] === 'admin' ? 'admin.php' : 'index.php');

$tab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$loginError = $regError = '';
$old = ['name' => '', 'user' => ''];

function enter(array $user): never {
    session_regenerate_id(true);
    $role = $user['role'] === 'admin' ? 'admin' : 'user';
    $_SESSION['user'] = $user['username'];
    redirect($role === 'admin' ? 'admin.php' : 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if ($act === 'login') {
        $tab = 'login';
        $u = strtolower(trim((string)($_POST['username'] ?? '')));
        $p = (string)($_POST['password'] ?? '');
        if ($u === '' || $p === '') {
            $loginError = 'Please enter your username and password.';
        } else {
            $user = find_user($u);
            if (!$user || !password_verify($p, $user['password_hash'])) $loginError = 'Incorrect username or password.';
            else enter($user);
        }
    } elseif ($act === 'register') {
        $tab = 'register';
        $name = trim((string)($_POST['name'] ?? ''));
        $u    = strtolower(trim((string)($_POST['username'] ?? '')));
        $p    = (string)($_POST['password'] ?? '');
        $p2   = (string)($_POST['password2'] ?? '');
        $old  = ['name' => $name, 'user' => $u];

        if ($name === '' || $u === '' || $p === '')            $regError = 'Please fill in all fields.';
        elseif (!preg_match('/^[a-z0-9_.-]{3,}$/', $u))        $regError = 'Username needs 3+ letters, numbers, dot, dash or underscore.';
        elseif (strlen($p) < 6)                                $regError = 'Password must be at least 6 characters.';
        elseif ($p !== $p2)                                    $regError = 'Passwords do not match.';
        elseif (find_user($u))                         $regError = 'That username is already taken.';
        else {
            add_user($name, $u, password_hash($p, PASSWORD_DEFAULT));
            enter(find_user($u));
        }
    }
}

$copy = [
    'login'    => ['Welcome back', 'Sign in to check the store.'],
    'register' => ['Create your account', 'Set up an account to get started.'],
];

page_head('Log In — VoltHub');
?>
  <div class="auth">
    <aside class="auth__brand">
      <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
      <div>
        <h1>Buy your wants and needs of gadgets at our store!</h1>
        <p>The most trusted store for gadgets!</p>
        <ul class="auth__points">
          <li>🛒 <span>Add to cart</span></li>
          <li>📦 <span>We process</span></li>
          <li>🚚 <span>And ship it to you!</span></li>
        </ul>
      </div>
      <small>© <?= date('Y') ?> VoltHub</small>
    </aside>

    <main class="auth__panel">
      <div class="auth__card">
        <a href="index.php" class="brand auth__mobile-brand"><span class="brand__mark">⚡</span>VoltHub</a>

        <div>
          <h2 id="title"><?= e($copy[$tab][0]) ?></h2>
          <p id="subtitle" class="muted"><?= e($copy[$tab][1]) ?></p>
        </div>

        <form method="get" class="tabs" role="tablist">
          <button class="tab<?= $tab === 'login' ? ' active' : '' ?>" name="tab" value="login" role="tab" type="submit">Log in</button>
          <button class="tab<?= $tab === 'register' ? ' active' : '' ?>" name="tab" value="register" role="tab" type="submit">Register</button>
        </form>

        <?php if ($tab === 'login'): ?>
        <form method="post" action="login_reg.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="login">
          <?php if ($loginError): ?><div class="form-error"><?= e($loginError) ?></div><?php endif; ?>
          <div class="field">
            <label for="lUser">Username</label>
            <input id="lUser" class="input" type="text" name="username" autocomplete="username" placeholder="Username" required>
          </div>
          <div class="field">
            <label for="lPass">Password</label>
            <div class="input-wrap">
              <input id="lPass" class="input" type="password" name="password" autocomplete="current-password" placeholder="********" required>
              <button type="button" class="toggle-pass" aria-label="Show password">👁</button>
            </div>
          </div>
          <button class="btn btn--primary btn--block" type="submit">Log in</button>
          <div class="hint">Demo account: <strong>admin</strong> / <strong>admin123</strong></div>
          <div class="hint">To check user UI: <strong>Register your account</strong></div>
        </form>
        <?php else: ?>
        <form method="post" action="login_reg.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="register">
          <?php if ($regError): ?><div class="form-error"><?= e($regError) ?></div><?php endif; ?>
          <div class="field">
            <label for="rName">Full name</label>
            <input id="rName" class="input" type="text" name="name" value="<?= e($old['name']) ?>" autocomplete="name" placeholder="Juan Dela Cruz" required>
          </div>
          <div class="field">
            <label for="rUser">Username</label>
            <input id="rUser" class="input" type="text" name="username" value="<?= e($old['user']) ?>" autocomplete="username" placeholder="juan" required>
          </div>
          <div class="field">
            <label for="rPass">Password</label>
            <div class="input-wrap">
              <input id="rPass" class="input" type="password" name="password" autocomplete="new-password" placeholder="At least 6 characters" required>
              <button type="button" class="toggle-pass" aria-label="Show password">👁</button>
            </div>
          </div>
          <div class="field">
            <label for="rPass2">Confirm password</label>
            <input id="rPass2" class="input" type="password" name="password2" autocomplete="new-password" placeholder="Repeat password" required>
          </div>
          <button class="btn btn--primary btn--block" type="submit">Create account</button>
        </form>
        <?php endif; ?>

        <p class="back-link"><a href="index.php">← Back to store</a></p>
      </div>
    </main>
  </div>
<?php page_end(); ?>