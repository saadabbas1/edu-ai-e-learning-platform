<?php
require __DIR__ . '/auth_student.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – EDU AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="home_page.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
    <style>
        .dashboard {
            padding: 40px;
        }
        .section-box {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0, 198, 255, 0.08);
        }
        .section-box h2 {
            margin-bottom: 16px;
            color: #007bff;
        }
        .section-box p {
            color: #555;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar-left">
            <img src="images/Book Logo.png" alt="Edu AI Logo" class="logo">
            <span class="brand-name">Edu AI – Admin</span>
        </div>
        <nav class="nav-links">
            <div class="dropdown" style="position: relative;">      
                <div class="nav-settings" onclick="toggleSettings()">
                    <i class="fas fa-bars"></i>
                </div>

                <div class="dropdown-content" id="settingsDropdown">
                    <a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="dashboard">
        <a href="admin_user_management.html" style="text-decoration: none;">
            <div class="section-box" id="users">
                <h2>User Management</h2>
                <p>View, edit or remove registered users. Integration with database required for dynamic content.</p>
            </div>
        </a>
        
        <a href="admin_course_management.html" style="text-decoration: none;">
            <div class="section-box" id="courses">
                <h2>Course Management</h2>
                <p>Add, update or delete course content and recommendations here.</p>
            </div>
        </a>

        <a href="admin_feedback.html" style="text-decoration: none;">
            <div class="section-box" onclick="openFeedbackModal()">
                <h2>View Feedback</h2>
                <p>See all platform feedback submitted by users.</p>
            </div>
        </a>
    </main>

    <footer class="eduai-footer">
        <div class="footer-bottom">
            <p>&copy; 2025 EDU AI – Admin Portal</p>
        </div>
    </footer>

    <script>
function toggleSettings() {
    const settings = document.getElementById("settingsDropdown");

    // Toggle settings dropdown
    settings.style.display =
        settings.style.display === "block" ? "none" : "block";
}

// Close dropdown when clicking outside
document.addEventListener("click", function (event) {
    const settings = document.getElementById("settingsDropdown");
    const settingsTrigger = document.querySelector(".nav-settings");

    if (!settings.contains(event.target) && !settingsTrigger.contains(event.target)) {
        settings.style.display = "none";
    }
});
</script>
</body>
</html>
