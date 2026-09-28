<?php
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login_form.html');
  exit;
}

$userId = $_SESSION['user_id'];

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$db->set_charset('utf8mb4');

$stmt = $db->prepare("
  SELECT notify_email, theme
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

$notifyEmail = (int)$settings['notify_email'];
$theme       = $settings['theme'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edu AI – Settings</title>
  <link rel="stylesheet" href="home_page.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .settings-section {
      max-width: 900px;
      margin: 4rem auto;
      padding: 2rem;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    .settings-section h2 {
      color: #007bff;
      font-size: 1.8rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid #eaeaea;
      padding-bottom: 0.5rem;
    }

    .setting-row {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .setting-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.2rem 1rem;
      background: #f8fbff;
      border-radius: 12px;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .setting-item:hover {
      background-color: #007bff;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0, 123, 255, 0.2);
    }

    .setting-item:hover i,
    .setting-item:hover h4 {
      color: #fff;
    }

    .setting-item i {
      color: #007bff;
      font-size: 1.2rem;
      margin-right: 1rem;
      transition: color 0.3s;
    }

    .setting-item h4 {
      font-size: 1rem;
      font-weight: 600;
      margin: 0;
      flex: 1;
      transition: color 0.3s;
    }

    @media (max-width: 600px) {
      .setting-item {
        flex-direction: column;
        align-items: flex-start;
      }
    }
    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background-color: rgba(0, 0, 0, 0.75);
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
  
    .modal-content {
      background: #fff;
      padding: 2rem;
      border-radius: 16px;
      width: 90%;
      max-width: 400px;
      position: relative;
      text-align: left;
    }
  
    .modal-content h3 {
      margin-top: 0;
      color: #007bff;
    }
  
    .modal-content input,
    .modal-content select {
      width: 100%;
      padding: 0.75rem;
      margin-top: 1rem;
      border: 1px solid #ddd;
      border-radius: 10px;
      font-size: 1rem;
    }
  
    .modal-content button {
      margin-top: 1.5rem;
      width: 100%;
      background: #007bff;
      color: #fff;
      border: none;
      padding: 0.75rem;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
    }
  
    .modal-content button:hover {
      background: #0056b3;
    }
  
    .close {
      position: absolute;
      top: 10px; right: 15px;
      font-size: 1.5rem;
      color: #aaa;
      cursor: pointer;
    }
  
    .close:hover {
      color: #000;
    }
  </style>
</head>

<body data-theme="<?= htmlspecialchars($theme ?? 'light') ?>">
    <header class="navbar">
    <div class="navbar-left">
        <img src="images/Book Logo.png" alt="Edu AI Logo" class="logo">
        <span class="brand-name">Edu AI – Student</span>
    </div>
    
    <nav class="nav-links">
        <a href="student.php">Dashboard</a>

        <div class="dropdown" style="position: relative;">
            <div class="nav-notification" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notif-badge" id="notifBadge" style="display: inline;">3</span>  
            </div>

            <div class="dropdown-content notif-dropdown" id="notifDropdown">
                <p><strong>Notifications</strong></p>
                <a href="#">📌 AI Quiz Updated</a>
                <a href="#">📚 New course: Deep Learning</a>
                <a href="#">⚠️ Your quiz score report is ready</a>
            </div>
        </div>

        <!-- Profile Menu Trigger Button -->
        <div class="avatar-menu-trigger" onclick="toggleProfileMenu()">
            <img src="images/Profile Avatar.png" alt="Profile Icon" class="avatar-icon">
        </div>

        <!-- Profile Menu Panel -->
        <div id="profileMenu" class="profile-menu hidden">
            <!-- User Info -->

            <div class="profile-header">
                <img onclick="document.location='profile.html'" src="images/Profile Avatar.png" alt="User Avatar" class="avatar">
                <div class="username">Saad Abbas</div>
            </div>

            <!-- Menu Buttons -->
            <div class="profile-actions">
                <div onclick="document.location='mylist.html'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
                <div onclick="document.location='bookmark.html'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
                <div onclick="document.location='submissions.html'" class="action-btn"><i class="fas fa-upload"></i> Submissions</div>
                <div onclick="document.location='quiz_history.html'" class="action-btn"><i class="fas fa-chart-line"></i> Progress</div>
            </div>

            <!-- Dropdown Menu -->
            <div class="dropdown-section">
                <ul class="dropdown-list">
                    <li><a href="setting.html">Settings</a></li>
                    <li><a href="#" id="logoutBtn">Log out</a></li>
                </ul>
            </div>
        </div>
    </nav>
  </header>

    <section class="settings-section">
        <h2>Settings</h2>
        <div class="setting-row">

            <!--  `onclick` attributes to each .setting-item -->
            <div class="setting-item" onclick="openModal('notificationModal')">
                <i class="fas fa-bell"></i>
                <h4>Notification Preferences</h4>
            </div>
  
            <div class="setting-item" onclick="openModal('themeModal')">
                <i class="fas fa-paint-brush"></i>
                <h4>Theme & Appearance</h4>
            </div>
  
            <div class="setting-item" onclick="openModal('logoutModal')">
                <i class="fas fa-sign-out-alt"></i>
                <h4 onclick="location.href='logout.php'">Logout</h4>
            </div>

            <div id="notificationModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('notificationModal')">&times;</span>
                    <h3>Notification Preferences</h3>
                    <div class="toggle-setting">
                      <span>Email Notifications</span>
                      <label class="switch">
                        <input type="checkbox" id="notifyEmail" <?= $notifyEmail ? 'checked' : '' ?>>
                        <span class="slider"></span>
                      </label>
                    </div>

                    <button onclick="saveNotificationPreferences()">Save</button>

                </div>
            </div>
  
            <div id="themeModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('themeModal')">&times;</span>
                    <h3>Theme Settings</h3>
                    <select id="themeSelect">
  <option value="light" <?= $theme==='light'?'selected':'' ?>>Light Mode</option>
  <option value="dark" <?= $theme==='dark'?'selected':'' ?>>Dark Mode</option>
</select>

                    <button onclick="saveTheme()">Apply</button>
                </div>
            </div>
  
            <div id="logoutModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('logoutModal')">&times;</span>
                    <h3>Confirm Logout</h3>
                    <p>Are you sure you want to logout?</p>
                    <button>Logout</button>
                </div>
            </div>

            <footer class="eduai-footer">
                <div class="footer-bottom">
                    <p>&copy; 2025 EDU AI – Settings</p>
                </div>
            </footer>

            <div id="successMessage" class="success-toast">Changes saved successfully!</div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
  
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
  
            // Close modal on outside click
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            });
        };

        function showSuccessMessage(message = "Changes saved successfully!") {
          const toast = document.getElementById("successMessage");
          toast.textContent = message;
          toast.classList.add("show");

          setTimeout(() => {
            toast.classList.remove("show");
          }, 3000);
        }

function saveNotificationPreferences() {
  const notify = document.getElementById('notifyEmail').checked;

  fetch('update_settings.php', {
    method: 'POST',
    body: new URLSearchParams({
      notify_email: notify ? 1 : 0
    })
  }).then(() => {
    closeModal('notificationModal');
    showSuccessMessage();
  });
}

function saveTheme() {
  const select = document.querySelector('#themeModal select');
  const theme = select.value.includes('Dark') ? 'dark' : 'light';

  fetch('/edu_ai/save_theme.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'theme=' + theme
  })
  .then(res => res.json())
  .then(data => {
    if (data.ok) {
      document.body.setAttribute('data-theme', theme);
      closeModal('themeModal');
      showSuccessMessage('Theme updated!');
    }
  });
}

        let notificationsRead = false;

    function toggleNotifications() {
        const notif = document.getElementById("notifDropdown");
        const badge = document.getElementById("notifBadge");

        // Toggle notifications
        notif.style.display = notif.style.display === "block" ? "none" : "block";

        // Mark as read
        if (!notificationsRead) {
            notificationsRead = true;
            badge.style.display = "none";
        }
    }

    function toggleProfileMenu() {
        const profileMenu = document.getElementById("profileMenu");
        profileMenu.classList.toggle("hidden");
    }

    // Close modals and dropdowns when clicking outside
    window.onclick = function(event) {
        const notifDropdown = document.getElementById("notifDropdown");
        const profileMenu = document.getElementById("profileMenu");
        const notifTrigger = document.querySelector(".nav-notification");
        const profileTrigger = document.querySelector(".avatar-menu-trigger");

        // Close notifications if clicked outside
        if (notifDropdown && !notifDropdown.contains(event.target) && !notifTrigger.contains(event.target)) {
            notifDropdown.style.display = "none";
        }

        // Close profile menu if clicked outside
        if (profileMenu && !profileMenu.contains(event.target) && !profileTrigger.contains(event.target)) {
            profileMenu.classList.add("hidden");
        }
    };

    document.getElementById("logoutBtn").addEventListener("click", function(event) {
        event.preventDefault(); // Prevent default link behavior

        // Clear the login session data from localStorage
        localStorage.removeItem("isLoggedIn");
        localStorage.removeItem("userRole");

        // Redirect to login page
        window.location.href = "login_form.html";
    });
    </script>
</body>
</html>
