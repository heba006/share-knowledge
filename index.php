
<?php
session_start();
require_once 'db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Student';
$isAdmin = false;

if ($isLoggedIn) {
    $userStmt = $conn->prepare(
        'SELECT name, role FROM users WHERE id = ?'
    );

    $userStmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser) {
        $userName = $currentUser['name'];
        $isAdmin = ($currentUser['role'] === 'admin');
    } else {
        $isLoggedIn = false;
    }
}

$totalMaterials = (int) $conn
    ->query('SELECT COUNT(*) FROM materials')
    ->fetchColumn();

$totalUsers = (int) $conn
    ->query('SELECT COUNT(*) FROM users')
    ->fetchColumn();

$totalReviews = (int) $conn
    ->query('SELECT COUNT(*) FROM reviews')
    ->fetchColumn();

$latestStmt = $conn->query(
    'SELECT
        m.id,
        m.subject,
        m.chapter,
        m.academic_year,
        m.created_at,
        u.name AS uploader
     FROM materials m
     LEFT JOIN users u ON u.id = m.user_id
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 6'
);

$latestMaterials = $latestStmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [
    [
        'icon' => '💻',
        'name' => 'Computer Science',
        'label' => 'Coding & technology'
    ],
    [
        'icon' => '📐',
        'name' => 'Mathematics',
        'label' => 'Numbers & logic'
    ],
    [
        'icon' => '⚙️',
        'name' => 'Engineering',
        'label' => 'Design & innovation'
    ],
    [
        'icon' => '🧬',
        'name' => 'Medicine',
        'label' => 'Health & science'
    ],
    [
        'icon' => '🧪',
        'name' => 'Chemistry',
        'label' => 'Elements & reactions'
    ],
    [
        'icon' => '📊',
        'name' => 'Business',
        'label' => 'Strategy & management'
    ],
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Share Knowledge — Learn Together, Grow Together
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/home.css"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        /* Share Knowledge Logo */

        .site-header .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            white-space: nowrap;
        }

        .site-header .brand-logo {
            display: block;
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .site-header .brand-name {
            display: inline-block;
        }

        @media (max-width: 600px) {
            .site-header .brand-logo {
                width: 40px;
                height: 40px;
            }

            .site-header .brand {
                gap: 7px;
            }
        }
    </style>

</head>

<body>

<!-- HEADER -->
<header class="site-header">

    <div class="header-inner">

        <!-- NEW LOGO -->
        <a href="index.php" class="brand">

            <img
                src="/assets/images/logo.png?v=3"
                alt=""
                class="brand-logo"
                width="52"
                height="52"
            >

            <span class="brand-name">
                Share Knowledge
            </span>

        </a>

        <!-- NAVIGATION -->
        <nav
            class="site-nav"
            aria-label="Main navigation"
        >

            <a href="index.php" class="active">
                Home
            </a>

            <a href="browse.php">
                Browse
            </a>

            <a href="upload.php">
                Upload
            </a>

            <?php if ($isLoggedIn): ?>

                <a href="messages.php">
                    Messages
                </a>

                <a href="favorites.php">
                    Favorites
                </a>

                <a href="profile.php">
                    My Profile
                </a>

                <?php if ($isAdmin): ?>
                    <a href="admin.php">
                        Admin 👑
                    </a>
                <?php endif; ?>

            <?php endif; ?>

        </nav>

        <!-- AUTHENTICATION -->
        <div class="auth">

            <?php if ($isLoggedIn): ?>

                <span class="hello">
                    Hi, <?= e($userName) ?> 👋
                </span>

                <a
                    href="logout.php"
                    class="small-btn light"
                >
                    Log out
                </a>

            <?php else: ?>

                <a
                    href="login.php"
                    class="small-btn light"
                >
                    Log in
                </a>

                <a
                    href="signup.php"
                    class="small-btn"
                >
                    Get started
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>


<!-- HERO SECTION -->
<section class="hero-wrap">

    <div class="hero">

        <div class="hero-content">

            <div class="eyebrow">
                ✦ Made for students, by students
            </div>

            <h1>
                Learn Together.
                <br>
                <span>Grow Together.</span>
            </h1>

            <p>
                Discover university notes, slides, and
                study materials shared by fellow students.
                Share what you know and make learning
                better for everyone.
            </p>

            <div class="hero-actions">

                <a
                    href="browse.php"
                    class="hero-btn primary"
                >
                    Explore Materials ↗
                </a>

                <a
                    href="upload.php"
                    class="hero-btn outline"
                >
                    ＋ Share Your Notes
                </a>

            </div>

        </div>

        <div
            class="hero-decoration"
            aria-hidden="true"
        >
            🎓
        </div>

    </div>

</section>


<main class="container">

    <!-- STATISTICS -->
    <section
        class="stats"
        aria-label="Platform statistics"
    >

        <div class="stat">

            <div class="stat-icon">
                📚
            </div>

            <div>
                <strong>
                    <?= $totalMaterials ?>
                </strong>

                <small>
                    Shared Materials
                </small>
            </div>

        </div>

        <div class="stat">

            <div class="stat-icon">
                👥
            </div>

            <div>
                <strong>
                    <?= $totalUsers ?>
                </strong>

                <small>
                    Community Members
                </small>
            </div>

        </div>

        <div class="stat">

            <div class="stat-icon">
                ⭐
            </div>

            <div>
                <strong>
                    <?= $totalReviews ?>
                </strong>

                <small>
                    Student Reviews
                </small>
            </div>

        </div>

    </section>


    <!-- EXPLORE BY SUBJECT -->
    <section class="section">

        <div class="section-heading">

            <div>

                <div class="tag">
                    Find your next resource
                </div>

                <h2>
                    Explore by Subject
                </h2>

                <p>
                    Jump into the topics that matter to you.
                </p>

            </div>

            <a
                class="view-all"
                href="browse.php"
            >
                Browse all subjects ↗
            </a>

        </div>

        <div class="subject-grid">

            <?php foreach ($subjects as $subject): ?>

                <a
                    class="subject-card"
                    href="browse.php?<?= e(http_build_query([
                        'subject' => $subject['name']
                    ])) ?>"
                >

                    <div class="subject-icon">
                        <?= e($subject['icon']) ?>
                    </div>

                    <div>

                        <h3>
                            <?= e($subject['name']) ?>
                        </h3>

                        <p>
                            <?= e($subject['label']) ?>
                        </p>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- RECENTLY SHARED -->
    <section class="section">

        <div class="section-heading">

            <div>

                <div class="tag">
                    Fresh from our community
                </div>

                <h2>
                    Recently Shared
                </h2>

                <p>
                    Check out the latest uploads
                    from fellow students.
                </p>

            </div>

            <a
                class="view-all"
                href="browse.php"
            >
                View all materials ↗
            </a>

        </div>

        <?php if (!$latestMaterials): ?>

            <div class="empty">

                <div style="font-size:36px">
                    📖
                </div>

                <h3>
                    Be the first to share
                </h3>

                <p>
                    No materials have been uploaded yet.
                </p>

                <a href="upload.php">
                    Upload a material ↗
                </a>

            </div>

        <?php else: ?>

            <div class="material-grid">

                <?php foreach ($latestMaterials as $material): ?>

                    <article class="material-card">

                        <div class="card-top">

                            <span class="chip">
                                <?= e($material['subject']) ?>
                            </span>

                            <time>
                                <?= e(date(
                                    'M j, Y',
                                    strtotime($material['created_at'])
                                )) ?>
                            </time>

                        </div>

                        <h3>
                            <?= e($material['chapter']) ?>
                        </h3>

                        <p>
                            🎓 <?= e($material['academic_year']) ?>
                        </p>

                        <p>
                            👤 Shared by
                            <?= e($material['uploader'] ?? 'Unknown') ?>
                        </p>

                        <a
                            class="card-link"
                            href="view_material.php?id=<?= (int) $material['id'] ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            View material ↗
                        </a>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- WHY SHARE KNOWLEDGE -->
    <section class="section">

        <div class="section-heading">

            <div>

                <div class="tag">
                    Why Share Knowledge?
                </div>

                <h2>
                    Everything you need to succeed
                </h2>

                <p>
                    A study community built around
                    helping each other.
                </p>

            </div>

        </div>

        <div class="why-grid">

            <div class="why">

                <div class="icon">
                    📚
                </div>

                <h3>
                    Free Resources
                </h3>

                <p>
                    Find notes and slides without
                    paying for access.
                </p>

            </div>

            <div class="why">

                <div class="icon">
                    🎓
                </div>

                <h3>
                    University Focused
                </h3>

                <p>
                    Study materials organized around
                    student needs.
                </p>

            </div>

            <div class="why">

                <div class="icon">
                    💜
                </div>

                <h3>
                    Save Favorites
                </h3>

                <p>
                    Keep helpful materials ready for
                    your next study session.
                </p>

            </div>

            <div class="why">

                <div class="icon">
                    💬
                </div>

                <h3>
                    Connect & Review
                </h3>

                <p>
                    Message fellow students and
                    share useful feedback.
                </p>

            </div>

        </div>

    </section>


    <!-- CALL TO ACTION -->
    <section class="cta">

        <h2>
            Knowledge grows when we share it. ✨
        </h2>

        <p>
            Join your fellow students and make
            studying a team effort.
        </p>

        <a
            href="<?= $isLoggedIn ? 'upload.php' : 'signup.php' ?>"
            class="hero-btn primary"
        >
            <?= $isLoggedIn
                ? 'Share a Material'
                : 'Join Share Knowledge' ?> ↗
        </a>

    </section>

</main>


<!-- FOOTER -->
<footer class="footer">

    © <?= date('Y') ?>
    Share Knowledge · Learn together, grow together.

</footer>

</body>
</html>
