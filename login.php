<?php
session_start();
require_once 'db.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome Back — Share Knowledge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="shell">
<header class="top">
<a class="brand" href="index.php"><span class="brand-icon">✦</span>Share Knowledge</a>
<a class="back" href="index.php">← Back to Home</a>
</header>
<main class="layout">
<section class="intro" aria-label="Welcome to Share Knowledge">
<div class="eyebrow">✦ Learn together. Grow together.</div>
<h1>Your knowledge.<br><span>Our community.</span></h1>
<p>Welcome to a space where students share resources, discover new ideas, and make every study session count.</p>
<div class="features">
<div class="feature"><span class="feature-icon">📚</span>Explore study materials</div>
<div class="feature"><span class="feature-icon">💜</span>Save your favorites</div>
<div class="feature"><span class="feature-icon">⭐</span>Discover student reviews</div>
</div>
</section>
<section class="auth-card" aria-labelledby="login-title">
<div class="card-symbol">🔐</div>
<h2 id="login-title">Welcome back!</h2>
<p class="subtitle">Sign in to your account and pick up where you left off.</p>
<?php if ($error !== ''): ?><div class="error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="POST" action="login.php">
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required></div>
<div class="field"><label for="password">Password</label><div class="password-wrap"><input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required><button class="toggle" type="button" id="password-toggle" aria-label="Show password" aria-pressed="false">Show</button></div></div>
<button type="submit" class="submit">Sign In →</button>
</form>
<div class="separator"></div>
<p class="signup">New to Share Knowledge? <a href="signup.php">Create an account ↗</a></p>
</section>
</main>
<footer class="footer">✦ Share Knowledge — Learning is better together.</footer>
</div>
<script>
const toggle = document.getElementById('password-toggle');
const password = document.getElementById('password');
toggle.addEventListener('click', () => {
  const visible = password.type === 'text';
  password.type = visible ? 'password' : 'text';
  toggle.textContent = visible ? 'Show' : 'Hide';
  toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
  toggle.setAttribute('aria-pressed', String(!visible));
});
</script>
</body>
</html>
