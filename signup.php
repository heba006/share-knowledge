<?php
session_start();
require_once "db.php";

$error = "";
$success = "";
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetch()) {
                $error = "Email already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashedPassword]);
                $success = "Account created successfully! You can now sign in.";
                $name = "";
                $email = "";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Something went wrong. Please try again.";
        }
    }
}
function signupEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Share Knowledge</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/signup.css">
</head>
<body>
<header class="topbar">
<a class="brand" href="index.php"><span class="brand-icon">✦</span>Share Knowledge</a>
<a class="back" href="index.php">← Back to Home</a>
</header>
<main class="layout">
<section class="story" aria-label="Community introduction">
<div class="eyebrow">✦ JOIN OUR LEARNING COMMUNITY</div>
<h1>Start learning.<br><span>Start sharing.</span><br>Grow together.</h1>
<p>Create your free student account and become part of a community where helpful notes, shared resources, and new ideas bring students together.</p>
<div class="perks">
<div class="perk"><span class="perk-icon">📚</span>Explore shared study materials</div>
<div class="perk"><span class="perk-icon">💜</span>Save your favorite resources</div>
<div class="perk"><span class="perk-icon">☁️</span>Upload notes and help others</div>
</div>
</section>
<section class="card" aria-labelledby="signup-heading">
<div class="card-kicker">CREATE YOUR ACCOUNT</div>
<h2 id="signup-heading">Join Share Knowledge ✦</h2>
<p class="subtitle">A few details and you're ready to learn with us.</p>
<?php if ($error): ?><div class="message error" role="alert"><?= signupEscape($error) ?></div><?php endif; ?>
<?php if ($success): ?>
<div class="message success" role="status"><?= signupEscape($success) ?><br><a class="signin-cta" href="login.php">Go to Login →</a></div>
<?php endif; ?>
<form method="POST" action="signup.php">
<div class="field"><label for="name">Full Name *</label><input type="text" name="name" id="name" maxlength="150" autocomplete="name" placeholder="Enter your full name" value="<?= signupEscape($name) ?>" required></div>
<div class="field"><label for="email">Email Address *</label><input type="email" name="email" id="email" maxlength="254" autocomplete="email" placeholder="you@example.com" value="<?= signupEscape($email) ?>" required></div>
<div class="field"><label for="password">Password *</label><div class="password-wrap"><input type="password" name="password" id="password" minlength="8" autocomplete="new-password" placeholder="Create a secure password" required><button type="button" class="toggle-password" id="togglePassword" aria-label="Show password" aria-pressed="false">Show</button></div><div class="hint">Use at least 8 characters.</div></div>
<button class="submit" type="submit">Create My Account →</button>
</form>
<p class="login-line">Already have an account? <a href="login.php">Sign In</a></p>
</section>
</main>
<div class="footer">✦ Share Knowledge — Learning is better together.</div>
<script>
const toggle = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
toggle.addEventListener('click', function () {
 const showing = passwordInput.type === 'password';
 passwordInput.type = showing ? 'text' : 'password';
 toggle.textContent = showing ? 'Hide' : 'Show';
 toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
 toggle.setAttribute('aria-pressed', showing ? 'true' : 'false');
});
</script>
</body>
</html>

