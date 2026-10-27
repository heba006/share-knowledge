<?php

session_start();

require_once "db.php";



if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");

    exit;

}



$userId = (int) $_SESSION["user_id"];



function e($value) {

    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");

}



$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");

$stmt->execute([$userId]);

$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {

    header("Location: logout.php");

    exit;

}



// Handle sending before marking any messages as read.

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $receiverId = filter_input(INPUT_POST, "receiver_id", FILTER_VALIDATE_INT);

    $message = trim($_POST["message"] ?? "");

    $token = (string) ($_POST["csrf_token"] ?? "");



    if (!hash_equals($_SESSION["messages_csrf"] ?? "", $token)) {

        http_response_code(403);

        exit("Invalid request. Refresh the page and try again.");

    }



    if (!$receiverId || $receiverId === $userId || $message === "" || mb_strlen($message, "UTF-8") > 2000) {

        $_SESSION["message_error"] = "Invalid message or recipient.";

        header("Location: messages.php");

        exit;

    }



    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");

    $stmt->execute([$receiverId]);

    if (!$stmt->fetch()) {

        $_SESSION["message_error"] = "Student not found.";

        header("Location: messages.php");

        exit;

    }



    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");

    $stmt->execute([$userId, $receiverId, $message]);

    header("Location: messages.php?user=" . $receiverId);

    exit;

}



if (empty($_SESSION["messages_csrf"])) {

    $_SESSION["messages_csrf"] = bin2hex(random_bytes(32));

}



$selectedId = filter_input(INPUT_GET, "user", FILTER_VALIDATE_INT);

$selectedUser = null;

if ($selectedId && $selectedId !== $userId) {

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");

    $stmt->execute([$selectedId]);

    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);

}



// Opening a conversation marks only incoming messages from that student as read.

if ($selectedUser) {

    $stmt = $conn->prepare(

        "UPDATE messages SET is_read = 1

         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"

    );

    $stmt->execute([$selectedId, $userId]);

}



// Count unread messages per sender.

$stmt = $conn->prepare(

    "SELECT sender_id, COUNT(*) AS unread_count

     FROM messages

     WHERE receiver_id = ? AND is_read = 0

     GROUP BY sender_id"

);

$stmt->execute([$userId]);

$unreadBySender = [];

$totalUnread = 0;

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $count = (int) $row["unread_count"];

    $unreadBySender[(int) $row["sender_id"]] = $count;

    $totalUnread += $count;

}



$stmt = $conn->prepare(

    "SELECT id, name, email FROM users WHERE id != ? ORDER BY name ASC"

);

$stmt->execute([$userId]);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);



$messages = [];

if ($selectedUser) {

    $stmt = $conn->prepare(

        "SELECT id, sender_id, receiver_id, message, created_at

         FROM messages

         WHERE (sender_id = ? AND receiver_id = ?)

            OR (sender_id = ? AND receiver_id = ?)

         ORDER BY created_at ASC, id ASC"

    );

    $stmt->execute([$userId, $selectedId, $selectedId, $userId]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

}



$error = $_SESSION["message_error"] ?? "";

unset($_SESSION["message_error"]);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages - Share Knowledge</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/messages.css">

</head>

<body>

<header class="mid-nav">

<a class="mid-brand" href="index.php"><span>✦</span>Share Knowledge</a>

<nav class="mid-links" aria-label="Main navigation">

<a href="index.php">Home</a><a href="browse.php">Explore</a><a href="upload.php">Upload</a>

<a href="messages.php" class="active">Messages<?php if ($totalUnread > 0): ?> <span class="unread-badge nav-unread"><?= $totalUnread ?></span><?php endif; ?></a>

<a href="favorites.php">Favorites</a><a href="profile.php">My Profile</a>

<a href="logout.php" class="mid-logout">Log out</a>

</nav>

</header>

<main class="messages-page">

    <div class="messages-container">

        <div class="mid-hero"><div class="mid-eyebrow">✦ Connect with your community</div>

<h1 class="messages-title">Messages 💬 <?php if ($totalUnread > 0): ?><span class="unread-badge"><?= $totalUnread ?></span><?php endif; ?></h1>

<p>Learn together, ask questions, and share ideas with fellow students.</p></div>

        <?php if ($error): ?><div class="message-error"><?= e($error) ?></div><?php endif; ?>

        <div class="messages-layout">

            <aside class="students-panel">

                <h3 class="panel-heading">Students</h3>

                <div class="student-search-wrap">

                    <input type="text" id="studentSearch" class="student-search" placeholder="🔍 Search students..." oninput="searchStudents()">

                </div>

                <?php if (empty($students)): ?>

                    <p class="mid-empty-students">No other students registered yet.</p>

                <?php else: ?>

                    <?php foreach ($students as $student): ?>

                        <?php $studentId = (int) $student["id"]; ?>

                        <a class="student-link <?= $selectedUser && $selectedId === $studentId ? "active" : "" ?>" href="messages.php?user=<?= $studentId ?>">

                            <span class="student-info"><span class="mid-avatar" aria-hidden="true"><?= e(function_exists('mb_substr') ? mb_substr($student["name"], 0, 1, 'UTF-8') : substr($student["name"], 0, 1)) ?></span><span class="mid-user-meta"><strong><?= e($student["name"]) ?></strong><small><?= e($student["email"]) ?></small></span></span>

                            <?php if (($unreadBySender[$studentId] ?? 0) > 0): ?>

                                <span class="unread-badge"><?= $unreadBySender[$studentId] ?></span>

                            <?php endif; ?>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </aside>

            <section class="chat-panel">

                <?php if (!$selectedUser): ?>

                    <div class="empty-chat">

                        <h2>Welcome to Messages 👋</h2>

                        <p>Select a student to start chatting.</p>

                    </div>

                <?php else: ?>

                    <h3 class="panel-heading mid-chat-heading"><span class="mid-avatar" aria-hidden="true">✦</span> Chat with <?= e($selectedUser["name"]) ?></h3>

                    <div class="chat-messages" id="chatMessages">

                        <?php if (empty($messages)): ?>

                            <p class="empty-chat">No messages yet. Say hello!</p>

                        <?php endif; ?>

                        <?php foreach ($messages as $msg): ?>

                            <div class="chat-bubble <?= (int)$msg["sender_id"] === $userId ? "sent" : "received" ?>">

                                <?= nl2br(e($msg["message"])) ?>

                                <small><?= e($msg["created_at"]) ?></small>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <form method="POST" class="chat-form">

                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["messages_csrf"]) ?>">

                        <input type="hidden" name="receiver_id" value="<?= (int)$selectedUser["id"] ?>">

                        <textarea name="message" maxlength="2000" placeholder="Write your message..." required></textarea>

                        <button type="submit" class="send-btn">Send Message ➤</button>

                    </form>

                <?php endif; ?>

            </section>

        </div>

    </div>

</main>

<script>

function searchStudents() {

    const search = document.getElementById("studentSearch").value.toLowerCase().trim();

    document.querySelectorAll(".student-link").forEach(function(student) {

        student.style.display = student.textContent.toLowerCase().includes(search) ? "flex" : "none";

    });

}

const chatMessages = document.getElementById("chatMessages");

if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

</script>

</body>

</html>

