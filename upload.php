<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION["upload_csrf"])) {
    $_SESSION["upload_csrf"] = bin2hex(random_bytes(32));
}

$userName = $_SESSION["user_name"] ?? "Student";

$error = $_SESSION["upload_error"] ?? "";
$success = $_SESSION["upload_success"] ?? "";

unset($_SESSION["upload_error"], $_SESSION["upload_success"]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Upload Material - Share Knowledge</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/upload.css">
</head>

<body>

    <!-- Navbar -->
    <header class="navbar">

        <div class="logo">
            <span>🎓</span>
            <h2>Share Knowledge</h2>
        </div>

        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="browse.php">Browse</a>
            <a href="upload.php">Upload</a>
            <a href="messages.php">Messages</a>
        </nav>

        <div class="nav-buttons">

            <span class="user-name">
                Welcome,
                <?= htmlspecialchars(
                    $userName,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </span>

            <a href="logout.php" class="logout-btn">
                Logout
            </a>

        </div>

    </header>

    <!-- Upload Page -->
    <div class="upload-container">

        <!-- Sidebar -->
        <aside class="upload-sidebar">

            <h3>Dashboard</h3>

            <ul>
                <li>
                    <a href="upload.php" class="active">
                        📤 Upload Material
                    </a>
                </li>

                <li>
                    <a href="browse.php">
                        📚 Browse Materials
                    </a>
                </li>

                <li>
                    <a href="messages.php">
                        💬 Messages
                    </a>
                </li>

                <li>
                    <a href="profile.php">
                        👤 Profile
                    </a>
                </li>
            </ul>

        </aside>

        <!-- Main Content -->
        <main class="upload-content">

            <div class="upload-card">

                <h1>Upload a Material</h1>

                <p class="upload-description">
                    Share your slides, notes or study
                    materials with other students.
                </p>

                <?php if ($error): ?>
                    <div class="upload-message upload-error">
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="upload-message upload-success">
                        <?= htmlspecialchars(
                            $success,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <form action="save_material.php"
                      method="POST"
                      enctype="multipart/form-data">

                    <!-- CSRF Protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION["upload_csrf"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <!-- Subject -->
                    <div class="form-group">

                        <label for="subject">
                            Subject *
                        </label>

                        <select id="subject"
                                name="subject"
                                required>

                            <option value="">
                                Select subject
                            </option>

                            <option value="Mathematics">
                                Mathematics
                            </option>

                            <option value="Computer Science">
                                Computer Science
                            </option>

                            <option value="Engineering">
                                Engineering
                            </option>

                            <option value="Business">
                                Business
                            </option>

                            <option value="Medicine">
                                Medicine
                            </option>

                            <option value="Pharmacy">
                                Pharmacy
                            </option>

                            <option value="Physics">
                                Physics
                            </option>

                            <option value="Chemistry">
                                Chemistry
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>

                    <!-- Chapter / Topic -->
                    <div class="form-group">

                        <label for="chapter">
                            Chapter / Topic *
                        </label>

                        <input type="text"
                               id="chapter"
                               name="chapter"
                               maxlength="200"
                               placeholder="e.g. Chapter 3 - Thermodynamics"
                               required>

                    </div>

                    <!-- Academic Year -->
                    <div class="form-group">

                        <label for="academic_year">
                            Academic Year / Level *
                        </label>

                        <input type="text"
                               id="academic_year"
                               name="academic_year"
                               maxlength="100"
                               placeholder="e.g. 2nd Year - Engineering"
                               required>

                    </div>

                    <!-- Description -->
                    <div class="form-group">

                        <label for="description">
                            Description *
                        </label>

                        <textarea id="description"
                                  name="description"
                                  rows="5"
                                  maxlength="5000"
                                  placeholder="Add a short description about the material..."
                                  required></textarea>

                    </div>

                    <!-- Upload File -->
                    <div class="form-group">

                        <label for="material_file">
                            Upload File *
                        </label>

                        <div class="file-upload-box">

                            <div class="upload-icon">
                                ☁️
                            </div>

                            <p>
                                Click to upload your material
                            </p>

                            <small>
                                Supported formats: PDF, JPG, PNG
                                (Max 10 MB)
                            </small>

                            <input type="file"
                                   id="material_file"
                                   name="material_file"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   required>

                        </div>

                    </div>

                    <!-- Publish Button -->
                    <button type="submit"
                            class="publish-btn">
                        Publish Material
                    </button>

                </form>

            </div>

        </main>

    </div>

</body>
</html>


