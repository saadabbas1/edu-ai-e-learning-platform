<?php
require __DIR__ . '/auth_student.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Learner';
$userLevelLower = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $db->set_charset('utf8mb4');

  // Get the latest name & study_level for this user
  $stmt = $db->prepare("SELECT name, study_level FROM users WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  if ($u = $stmt->get_result()->fetch_assoc()) {
    $userName = $u['name'] ?: $userName;
  }
} catch (Throwable $e){
  $recommended = [];
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

$uid  = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, interest, descp, study_level, profile_pic FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?? [
  'name'=>'','email'=>'','interest'=>'','descp'=>'','study_level'=>'','profile_pic'=>null
];

$avatarRel = $user['profile_pic'] ?: '';
$avatarUrl = $avatarRel ? '/edu_ai/' . ltrim($avatarRel, '/') : 'https://images.unsplash.com/photo-1511367461989-f85a21fda167?auto=format&fit=crop&w=80&q=80';

/* ===== Profile Statistics ===== */

// Quizzes Completed
$stmt = $conn->prepare("
  SELECT COUNT(*) 
  FROM quiz_attempts 
  WHERE user_id = ?
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($quizzesCompleted);
$stmt->fetch();
$stmt->close();

// Completed Courses
$stmt = $conn->prepare("
  SELECT COUNT(*) FROM (
    SELECT c.id
    FROM courses c
    JOIN lessons l ON l.course_id = c.id
    LEFT JOIN lesson_progress lp 
      ON lp.lesson_id = l.id AND lp.user_id = ?
    GROUP BY c.id
    HAVING COUNT(l.id) = COUNT(lp.completed_at)
  ) AS completed
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($completedCourses);
$stmt->fetch();
$stmt->close();

// Active Courses
$stmt = $conn->prepare("
  SELECT COUNT(*) FROM (
    SELECT c.id
    FROM courses c
    JOIN lessons l ON l.course_id = c.id
    LEFT JOIN lesson_progress lp 
      ON lp.lesson_id = l.id AND lp.user_id = ?
    GROUP BY c.id
    HAVING COUNT(lp.completed_at) > 0
       AND COUNT(lp.completed_at) < COUNT(l.id)
  ) AS active
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($activeCourses);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edu AI – Profile</title>

  <link rel="stylesheet" href="profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Navbar -->
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
                <span class="notif-badge" id="notifBadge" style="display:none;"></span>
            </div>

            <div class="dropdown-content notif-dropdown" id="notifDropdown">
            <p><strong>Notifications</strong></p>
            <div id="notifList"></div>
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
                <span class="user-name" style="cursor:pointer;" onclick="document.location='profile.php'">
                  <?php echo htmlspecialchars($userName); ?>
                </span>
            </div>

            <!-- Menu Buttons -->
            <div class="profile-actions">
                <div onclick="document.location='mylist.php'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
                <div onclick="document.location='bookmark.php'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
                <div onclick="document.location='submissions.php'" class="action-btn"><i class="fas fa-upload"></i> Submissions</div>
                <div onclick="document.location='quiz_history.php'" class="action-btn"><i class="fas fa-chart-line"></i> Progress</div>
            </div>

            <!-- Dropdown Menu -->
            <div class="dropdown-section">
                <ul class="dropdown-list">
                    <li><a href="setting.html">Settings</a></li>
                    <li><a href="logout.php" id="logoutBtn">Log out</a></li>
                </ul>
            </div>
        </div>
    </nav>
  </header>

  <!-- Profile Section -->
  <div class="profile-container" role="main">
    <!-- Left Column -->
    <div class="profile-card" aria-label="User profile card">
      <img id="avatarImg"
       src="<?php echo htmlspecialchars($avatarUrl); ?>"
       alt="Profile"
       class="profile-pic" />
      <h2><?php echo htmlspecialchars($user['name']); ?></h2>
  <p><?php echo htmlspecialchars($user['email']); ?></p>
  <p><strong>Interest:</strong> <?php echo htmlspecialchars($user['interest']); ?></p>
  <p><strong>Description:</strong> <?php echo htmlspecialchars($user['descp']); ?></p>
  <p><strong>Study Level:</strong> <?php echo htmlspecialchars($user['study_level']); ?></p>
    </div>

    <!-- Right Column -->
    <div>
      <div class="stats-card" aria-label="User statistics">
        <div class="stat-item">
  <h3><?= (int)$activeCourses ?></h3>
  <p>Active Courses</p>
</div>

<div class="stat-item">
  <h3><?= (int)$completedCourses ?></h3>
  <p>Completed Courses</p>
</div>

<div class="stat-item">
  <h3><?= (int)$quizzesCompleted ?></h3>
  <p>Quizzes Completed</p>
</div>

      </div>

      <div class="quick-settings" aria-label="Quick profile settings">
        <div class="setting-box" tabindex="0" role="button" aria-pressed="false" onclick="openModal('editProfileModal')">
          <h4>Edit Profile</h4>
          <p>Update your personal details and profile picture.</p>
        </div>
        <div class="setting-box" tabindex="0" role="button" aria-pressed="false" onclick="openModal('changePasswordModal')">
          <h4>Change Password</h4>
          <p>Keep your account secure by updating your password regularly.</p>
        </div>
        <div class="setting-box" tabindex="0" role="button" aria-pressed="false" onclick="openModal('uploadPictureModal')">
          <h4>Upload Picture</h4>
          <p>Update your profile image to personalize your account.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Profile Modal -->
  <div class="modal" id="editProfileModal" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle" tabindex="-1">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal('editProfileModal')" role="button" aria-label="Close">&times;</span>
      <h2 id="editProfileTitle">Edit Profile</h2>
      <form id="editProfileForm" novalidate>
  <label>Full Name</label>
  <input type="text" id="username" name="username"
         value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">

  <label>Email</label>
  <input type="email" id="email" name="email" readonly
         value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">

  <label>Interest</label>
  <select id="interest" name="interest" required>
    <option value="" disabled>Select Interest</option>
    <option value="programming_fundamentals">Programming Fundamentals</option>
    <option value="oop">Object Oriented Programming</option>
    <option value="data_structures">Data Structures and Analysis</option>
    <option value="web">Web Development</option>
    <option value="mobile">Mobile Application Development</option>
    <option value="ai">Artificial Intelligence</option>
  </select>

  <label>Description</label>
  <select id="description" name="description" required>
    <option value="" disabled>Select a Description</option>
  </select>

  <label>Study Level</label>
  <select id="studyLevel" name="studyLevel" required>
    <option value="beginner"     <?php if(($user['study_level']??'')==='beginner') echo 'selected'; ?>>Beginner</option>
    <option value="intermediate" <?php if(($user['study_level']??'')==='intermediate') echo 'selected'; ?>>Intermediate</option>
    <option value="advanced"     <?php if(($user['study_level']??'')==='advanced') echo 'selected'; ?>>Advanced</option>
  </select>

  <p id="profileMessage" class="error-message"></p>
  <button type="submit" class="btn1">Save Changes</button>
</form>

    </div>
  </div>

<!-- Change Password Modal -->
<div class="modal" id="changePasswordModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal('changePasswordModal')" role="button" aria-label="Close">&times;</span>
    <h2 id="changePasswordTitle">Change Password</h2>

    <form id="changePasswordForm" novalidate>
  <div class="form-group">
  <label for="oldPassword">Old Password</label>
  <div class="password-container">
    <input type="password" id="oldPassword" name="oldPassword" placeholder="Enter old password" required>
    <button type="button" class="toggle-password" id="toggleOldPassword" aria-label="Show/Hide old password">
      <i class="fa-solid fa-eye"></i>
    </button>
  </div>
</div>

<div class="form-group">
  <label for="newPassword">New Password</label>
  <div class="password-container">
    <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" required>
    <button type="button" class="toggle-password" id="toggleNewPassword" aria-label="Show/Hide new password">
      <i class="fa-solid fa-eye"></i>
    </button>
  </div>
<!-- Light professional checklist -->
      <ul class="pw-checklist" aria-live="polite" aria-atomic="true">
        <li id="rule-capital" class="rule">
          <i class="fa-regular fa-circle"></i>
          <span>Minimum 8 characters</span>
        </li>
        <li id="rule-number" class="rule">
          <i class="fa-regular fa-circle"></i>
          <span>Minimum one capital letter (A–Z)</span>
        </li>
        <li id="rule-length" class="rule">
          <i class="fa-regular fa-circle"></i>
          <span>Minimum one numeric digit (0–9)</span>
        </li>
      </ul>
</div>

<div class="form-group">
  <label for="confirmPassword">Confirm Password</label>
  <div class="password-container">
    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" required>
    <button type="button" class="toggle-password" id="toggleConfirmPassword" aria-label="Show/Hide confirm password">
      <i class="fa-solid fa-eye"></i>
    </button>
  </div>
  <small id="matchMsg" class="pw-hint"></small>
</div>

  <p id="passwordMessage" class="error-message"></p>
  <button type="submit" class="btn1">Change Password</button>
</form>
  </div>
</div>

<!-- Toast Notification -->
<div id="toast" role="alert" aria-live="assertive"></div>

  <!-- Upload Picture Modal -->
  <div class="modal" id="uploadPictureModal" role="dialog" aria-modal="true" aria-labelledby="uploadPictureTitle" tabindex="-1">
    <div class="modal-content">
      <span class="close-btn" onclick="closeModal('uploadPictureModal')" role="button" aria-label="Close">&times;</span>
      <h2 id="uploadPictureTitle">Upload Picture</h2>
      <form id="uploadPictureForm" novalidate>
        <div class="profile-picture-container">
        <img id="profilePicturePreview" class="profile-picture-preview"
        src="https://images.unsplash.com/photo-1511367461989-f85a21fda167?auto=format&fit=crop&w=80&q=80"
        alt="Profile Picture Preview" />
        <input type="file" id="profilePicture" name="profilePicture"
        accept="image/*" aria-label="Choose profile picture" />
      </div>

        <button type="button" class="btn1" id="uploadAvatarBtn">Upload</button>
      </form>
    </div>
  </div>

  <script>
  function openModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    modal.focus();
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  const editProfileForm = document.getElementById('editProfileForm');
  editProfileForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const interestInput = document.getElementById('interest');
    const descriptionInput = document.getElementById('description');
    const levelInput = document.getElementById('level');

    const interestText = interestInput.selectedOptions[0]?.text || "";
    const descriptionText = descriptionInput.options[descriptionInput.selectedIndex]?.text || "";
    const levelText = levelInput.selectedOptions[0]?.text || "";

    document.getElementById('profileInterest').innerHTML = `<strong>Interest:</strong> ${interestText}`;
    document.getElementById('profileDescription').innerHTML = `<strong>Description:</strong> ${descriptionText}`;
    document.getElementById('profileLevel').innerHTML = `<strong>Study Level:</strong> ${levelText}`;

    const fullNameError = document.getElementById('fullNameError');
    const emailError = document.getElementById('emailError');
    fullNameError.textContent = '';
    emailError.textContent = '';
    trackError.textContent = '';
    const nameRegex = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]+$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let isValid = true;

    if (!fullNameInput.value.trim()) {
      fullNameError.textContent = 'Full name is required.';
      isValid = false;
    } else if (!nameRegex.test(fullNameInput.value.trim())) {
      fullNameError.textContent = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
      isValid = false;
    }

    if (!emailInput.value.trim()) {
      emailError.textContent = 'Email is required.';
      isValid = false;
    } else if (!emailRegex.test(emailInput.value.trim())) {
      emailError.textContent = 'Please enter a valid email address.';
      isValid = false;
    }

    if (isValid) {
      const trackInput = document.getElementById('track'); // Moved up here BEFORE usage
      document.getElementById('profileFullName').textContent = fullNameInput.value.trim();
      document.getElementById('profileEmail').textContent = emailInput.value.trim();
      document.getElementById('profileTrack').textContent = trackInput.value.trim();

      showToast('All changes saved in the profile!');
      closeModal('editProfileModal');
    }
  });

  const changePasswordForm = document.getElementById('changePasswordForm');
  changePasswordForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const oldPassword = document.getElementById('oldPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmNewPassword = document.getElementById('confirmPassword');
    const oldPasswordError = document.getElementById('oldPasswordError');
    const newPasswordError = document.getElementById('newPasswordError');
    const confirmNewPasswordError = document.getElementById('confirmNewPasswordError');
    oldPasswordError.textContent = '';
    newPasswordError.textContent = '';
    confirmNewPasswordError.textContent = '';
    let isValid = true;

    if (!oldPassword.value.trim()) {
      oldPasswordError.textContent = 'Old password is required.';
      isValid = false;
    }
    if (!newPassword.value.trim()) {
      newPasswordError.textContent = 'New password is required.';
      isValid = false;
    }
    if (newPassword.value !== confirmNewPassword.value) {
      confirmNewPasswordError.textContent = 'Passwords do not match.';
      isValid = false;
    }

    if (isValid) {
      showToast('Password changed successfully!');
      closeModal('changePasswordModal');
      changePasswordForm.reset();
    }
  });

  const uploadPictureForm = document.getElementById('uploadPictureForm');
  const profilePictureInput = document.getElementById('profilePicture');
  const profilePicturePreview = document.getElementById('profilePicturePreview');

  profilePictureInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(event) {
        profilePicturePreview.src = event.target.result;
        document.getElementById('profileImage').src = event.target.result;
      };
      reader.readAsDataURL(file);
    }
  });

  uploadPictureForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (profilePictureInput.files.length === 0) {
      showToast('Please select a picture to upload.');
      return;
    }
    showToast('Profile picture updated successfully!');
    closeModal('uploadPictureModal');
    uploadPictureForm.reset();
  });

  window.addEventListener("click", function(event) {
    const modals = ['editProfileModal', 'changePasswordModal', 'uploadPictureModal'];
    modals.forEach(id => {
      const modal = document.getElementById(id);
      if (event.target === modal) {
        closeModal(id);
      }
    });

    // Notification dropdown
    const notifDropdown = document.getElementById("notifDropdown");
    const notifTrigger = document.querySelector(".nav-notification");
    if (notifDropdown && !notifDropdown.contains(event.target) && !notifTrigger.contains(event.target)) {
      notifDropdown.style.display = "none";
    }

    // Profile menu
    const profileMenu = document.getElementById("profileMenu");
    const profileTrigger = document.querySelector(".avatar-menu-trigger");
    if (profileMenu && !profileMenu.contains(event.target) && !profileTrigger.contains(event.target)) {
      profileMenu.classList.add("hidden");
    }
  });

  window.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') {
      const modals = ['editProfileModal', 'changePasswordModal', 'uploadPictureModal'];
      modals.forEach(id => {
        const modal = document.getElementById(id);
        if (modal.style.display === 'flex') {
          closeModal(id);
        }
      });
    }
  });

  const newPasswordInput = document.getElementById('newPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const passwordStrengthMsg = document.getElementById('passwordStrength');
  const passwordMatchMsg = document.getElementById('passwordMatch');

  newPasswordInput.addEventListener('input', () => {
    const val = newPasswordInput.value;

    if (val.trim() === '') {
      passwordStrengthMsg.textContent = '';
      return;
    }

    let msg = '';
    let color = '';

    if (val.length < 8) {
      msg = 'Weak (min 8 characters)';
      color = '#d93025'; // Red
    } else if (!/[A-Z]/.test(val) || !/[a-z]/.test(val) || !/[0-9]/.test(val)) {
      msg = 'Moderate (use uppercase, lowercase, numbers)';
      color = '#ff9800'; // Orange
    } else {
      msg = 'Strong';
      color = '#28a745'; // Green
    }

    passwordStrengthMsg.textContent = 'Strength: ' + msg;
    passwordStrengthMsg.style.color = color;
  });

  confirmPasswordInput.addEventListener('input', () => {
    const confirmVal = confirmPasswordInput.value;
    const newVal = newPasswordInput.value;

    if (confirmVal.trim() === '') {
      passwordMatchMsg.textContent = '';
      return;
    }

    if (confirmVal !== newVal) {
      passwordMatchMsg.textContent = 'Passwords do not match';
      passwordMatchMsg.style.color = '#d93025'; // Red
    } else {
      passwordMatchMsg.textContent = 'Passwords match';
      passwordMatchMsg.style.color = '#28a745'; // Green
    }
  });

  const ctx = document.getElementById('quizProgressChart').getContext('2d');

  const quizzes = ['Quiz 1', 'Quiz 2', 'Quiz 3', 'Quiz 4'];
  const scores = [75, 88, 92, 60];

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: quizzes,
      datasets: [{
        data: scores,
        backgroundColor: '#00c6ff',
        borderRadius: 10,
        maxBarThickness: 30,
        hoverBackgroundColor: '#007bff',
        borderSkipped: false,
      }]
    },
    options: {
      responsive: false,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 10, bottom: 10 }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            stepSize: 20,
            color: '#007bff',
            font: { weight: '600', size: 12 },
            callback: val => val + '%'
          },
          grid: {
            color: '#e0f0ff',
            borderColor: '#c0d8ff',
            lineWidth: 1
          }
        },
        x: {
          ticks: {
            color: '#007bff',
            font: { weight: '600', size: 12 },
          },
          grid: {
            display: false
          }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#007bff',
          titleColor: '#ffffff',
          bodyColor: '#e0f0ff',
          padding: 8,
          cornerRadius: 6,
          callbacks: {
            label: ctx => `Score: ${ctx.parsed.y}%`
          }
        }
      },
      aspectRatio: 1.7,
    },
  });

  // Optional: Show total quizzes count (add this if you want)
  const container = document.querySelector('.progress-chart-container');
  const totalQuizzesText = document.createElement('p');
  totalQuizzesText.style.cssText = 'text-align:center; font-weight:600; color:#007bff; margin-top:0.5rem; font-size: 0.95rem;';
  totalQuizzesText.textContent = `Total quizzes taken: ${quizzes.length}`;
  container.appendChild(totalQuizzesText);

  async function loadNotifications() {
  const res = await fetch('/edu_ai/get_notifications.php', {
    credentials: 'same-origin'
  });
  const data = await res.json();
  if (!data.ok) return;

  const list = document.getElementById('notifList');
  const badge = document.getElementById('notifBadge');

  list.innerHTML = '';

  data.notifications.forEach(n => {
    const a = document.createElement('a');
    a.href = n.link || '#';
    a.textContent = `🔔 ${n.title}`;
    a.onclick = () => markNotificationRead(n.id);
    list.appendChild(a);
  });

  badge.style.display = data.unread_count ? 'inline' : 'none';
  badge.textContent = data.unread_count;
}

function markNotificationRead(id) {
  fetch('/edu_ai/mark_notification_read.php', {
    method: 'POST',
    body: new URLSearchParams({ id }),
    credentials: 'same-origin'
  }).then(() => loadNotifications());
}

loadNotifications();

    function toggleNotifications() {
        const notif = document.getElementById("notifDropdown");
        const badge = document.getElementById("notifBadge");

        // Toggle notifications
        notif.style.display = notif.style.display === "block" ? "none" : "block";
    }

  function toggleProfileMenu() {
    const profileMenu = document.getElementById("profileMenu");
    profileMenu.classList.toggle("hidden");
  }

  // Close modals and dropdowns when clicking outside
  window.onclick = function(event) {
    const courseModal = document.getElementById("courseModal");
    const feedbackModal = document.getElementById("feedbackModal");
    const notifDropdown = document.getElementById("notifDropdown");
    const profileMenu = document.getElementById("profileMenu");
    const notifTrigger = document.querySelector(".nav-notification");
    const profileTrigger = document.querySelector(".avatar-menu-trigger");

    if (event.target === courseModal) {
      courseModal.style.display = "none";
    }
    if (event.target === feedbackModal) {
      feedbackModal.style.display = "none";
    }

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

<script>
(async function loadProfile(){
  try {
    const res = await fetch('/edu_ai/profile_get.php', {cache:'no-store'});
    const data = await res.json();
    if (!data.ok) {
      // show a general message if you have a placeholder
      console.error(data);
      return;
    }
    // Fill your inputs (adjust IDs to match your page)
    document.getElementById('username').value    = data.user.name || '';
    document.getElementById('email').value       = data.user.email || ''; // keep read-only
    document.getElementById('interest').value    = data.user.interest || '';
    document.getElementById('description').value = data.user.description || '';
    document.getElementById('studyLevel').value  = data.user.studyLevel || '';
  } catch (e) {
    console.error(e);
  }
})();
</script>

<script>
document.getElementById('profile_form').addEventListener('submit', async function(e){
  e.preventDefault();
  // Clear field errors if you have spans like in signup
  const msg = document.getElementById('profileMessage'); // optional message area
  if (msg) { msg.textContent=''; msg.style.color=''; }

  const fd = new FormData();
  fd.append('username',   document.getElementById('username').value.trim());
  fd.append('interest',   document.getElementById('interest').value);
  fd.append('description',document.getElementById('description').value);
  fd.append('studyLevel', document.getElementById('studyLevel').value);

  try {
    const res = await fetch('/edu_ai/profile_update.php', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
      // If you have inline spans, fill them here:
      // document.getElementById('usernameError').textContent = data.errors?.username || '';
      // ...
      if (msg) { msg.textContent = data.errors?._form || 'Fix the highlighted errors.'; msg.style.color='red'; }
      return;
    }
    if (msg) { msg.textContent = data.message; msg.style.color='green'; }
  } catch (err) {
    if (msg) { msg.textContent = 'Network error.'; msg.style.color='red'; }
  }
});
</script>

<script>
document.getElementById('password_form').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData();
  fd.append('currentPassword', document.getElementById('currentPassword').value);
  fd.append('newPassword',     document.getElementById('newPassword').value);
  fd.append('confirmPassword', document.getElementById('confirmPassword').value);

  const msg = document.getElementById('passwordMessage'); // optional message area
  if (msg) { msg.textContent=''; msg.style.color=''; }

  try {
    const res = await fetch('/edu_ai/profile_change_password.php', { method:'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
      // Fill field errors near inputs if you have spans
      if (msg) { msg.textContent = data.errors?._form || 'Please fix the errors.'; msg.style.color='red'; }
      return;
    }
    if (msg) { msg.textContent = data.message; msg.style.color='green'; }
    // Optional: clear fields
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
  } catch (err) {
    if (msg) { msg.textContent = 'Network error.'; msg.style.color='red'; }
  }
});
</script>

<script>
// Elements (your existing IDs)
const fileInput = document.getElementById('profilePicture');
const preview   = document.getElementById('profilePicturePreview');
const uploadBtn = document.getElementById('uploadAvatarBtn');

// 1) Live preview when a file is chosen (no upload yet)
fileInput?.addEventListener('change', () => {
  const file = fileInput.files?.[0];
  if (!file) return;

  if (!/^image\//.test(file.type)) {
    alert('Please select an image file.');
    fileInput.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) { // 2MB
    alert('File too large (max 2MB).');
    fileInput.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => { if (preview) preview.src = e.target.result; };
  reader.readAsDataURL(file);
});

// 2) Upload only when clicking the Upload button
uploadBtn?.addEventListener('click', async () => {
  const file = fileInput?.files?.[0];
  if (!file) {
    alert('Please choose a picture first.');
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    alert('File too large (max 2MB).');
    return;
  }

  const fd = new FormData();
  fd.append('avatar', file); // backend expects 'avatar'

  uploadBtn.disabled = true;

  try {
    const res  = await fetch('/edu_ai/profile_upload_avatar.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.ok) {
      alert(data.errors?.avatar || data.errors?._form || 'Upload failed.');
      uploadBtn.disabled = false;
      return;
    }

    // Success: refresh preview with server URL (avoid cached old image)
    if (data.url && preview) {
      preview.src = data.url + '?t=' + Date.now();
    }
    alert(data.message || 'Profile picture updated.');
    fileInput.value = '';

  } catch (e) {
    alert('Network error. Please try again.');
  } finally {
    uploadBtn.disabled = false;
  }
});
</script>

<script>
  const interestEl    = document.getElementById('interest');
  const descriptionEl = document.getElementById('description');

  // same as signup
  const interestDescriptions = {
    programming_fundamentals: [
      { value: "Variables", text: "Variables" },
      { value: "Functions", text: "Functions" },
      { value: "Data Types", text: "Data Types" },
      { value: "Data Structure", text: "Data Structure" },
      { value: "Syntax", text: "Syntax" },
      { value: "Arrays", text: "Arrays" },
      { value: "Comment", text: "Comment" },
      { value: "Loops", text: "Loops" },
      { value: "Control Flow", text: "Control Flow" }
    ],
    oop: [
      { value: "OOP Concepts", text: "OOP Concepts (Encapsulation, Inheritance)" },
      { value: "Design Patterns", text: "Design Patterns" },
      { value: "Project-based Learning", text: "Project-based Learning" }
    ],
    data_structures: [
      { value: "Algorithm Analysis", text: "Algorithm Analysis" },
      { value: "Data Types and Structures", text: "Data Types and Structures" },
      { value: "Real-life Applications", text: "Real-life Applications" }
    ],
    web: [
      { value: "Frontend Development (HTML, CSS, JS)", text: "Frontend Development (HTML, CSS, JS)" },
      { value: "Backend Logic & Databases", text: "Backend Logic & Databases" },
      { value: "Full-Stack Projects", text: "Full-Stack Projects" }
    ],
    mobile: [
      { value: "Android App Development", text: "Android App Development" },
      { value: "iOS App Development", text: "iOS App Development" },
      { value: "Cross-platform with Flutter", text: "Cross-platform with Flutter" }
    ],
    ai: [
      { value: "Introduction to AI", text: "Introduction to AI" },
      { value: "Machine Learning Basics", text: "Machine Learning Basics" },
      { value: "Deep Learning and Neural Networks", text: "Deep Learning and Neural Networks" }
    ]
  };

  // server-provided current values
  const savedInterest = <?php echo json_encode($user['interest'] ?? ''); ?>;
  const savedDesc     = <?php echo json_encode($user['descp'] ?? ''); ?>;

  function populateDescriptions(intKey, selectedVal) {
    descriptionEl.innerHTML = '<option value="" disabled>Select a Description</option>';
    const list = interestDescriptions[intKey] || [];
    list.forEach(({value, text}) => {
      const opt = document.createElement('option');
      opt.value = value; opt.textContent = text;
      if (value === selectedVal) opt.selected = true;
      descriptionEl.appendChild(opt);
    });
    descriptionEl.disabled = false; // ensure it submits
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (savedInterest) interestEl.value = savedInterest;
    populateDescriptions(interestEl.value || savedInterest || '', savedDesc || '');
  });

  interestEl.addEventListener('change', () => populateDescriptions(interestEl.value, ''));
</script>

<script>
  const form = document.getElementById('editProfileForm');
  const msg  = document.getElementById('profileMessage');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // safety: ensure description isn’t disabled
    if (document.getElementById('description').disabled) {
      document.getElementById('description').disabled = false;
    }

    const fd = new FormData(form);

    try {
      const res  = await fetch('/edu_ai/profile_update.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (!data.ok) {
  msg.textContent = data.errors?._form || 'Please fix the highlighted errors.';
  msg.style.color = 'red';
  return;
}

// ✅ Success: show message, close modal, and reload page
msg.textContent = data.message || 'Profile updated successfully.';
msg.style.color = 'green';

// Optional short delay so user sees the message briefly
setTimeout(() => {
  // Close the modal (adjust selector to your modal’s ID)
  const modal = document.querySelector('#editProfileModal, .modal');
  if (modal) modal.style.display = 'none';

  // Reload to reflect updated info
  location.reload();
}, 800);

    } catch (err) {
      msg.textContent = 'Network error. Please try again.';
      msg.style.color = 'red';
    }
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  function wireToggle(toggleId, inputId) {
    const btn = document.getElementById(toggleId);
    const inp = document.getElementById(inputId);
    if (!btn || !inp) return;

    const icon = btn.querySelector('i');
    btn.addEventListener('click', () => {
      const isHidden = inp.type === 'password';
      inp.type = isHidden ? 'text' : 'password';
      icon.classList.toggle('fa-eye', !isHidden);
      icon.classList.toggle('fa-eye-slash', isHidden);
      btn.classList.toggle('active', isHidden);
    });
  }

  wireToggle('toggleOldPassword', 'oldPassword');
  wireToggle('toggleNewPassword', 'newPassword');
  wireToggle('toggleConfirmPassword', 'confirmPassword');
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form    = document.getElementById('changePasswordForm');
  const oldPw   = document.getElementById('oldPassword');
  const newPw   = document.getElementById('newPassword');
  const cfmPw   = document.getElementById('confirmPassword');
  const ruleLen   = document.getElementById('ruleLen');
  const ruleUpper = document.getElementById('ruleUpper');
  const ruleDigit = document.getElementById('ruleDigit');
  const matchMsg  = document.getElementById('matchMsg');

  const passLen   = p => p.length >= 8;
  const passUpper = p => /[A-Z]/.test(p);
  const passDigit = p => /\d/.test(p);

  function updateRules() {
    const v = newPw.value;
    ruleLen.classList.toggle('ok',   passLen(v));
    ruleUpper.classList.toggle('ok', passUpper(v));
    ruleDigit.classList.toggle('ok', passDigit(v));
    updateMatch();
  }

  function updateMatch() {
    const ok = cfmPw.value.length > 0 && newPw.value === cfmPw.value;
    matchMsg.textContent = ok ? 'Passwords match' : (cfmPw.value ? 'Passwords do not match' : '');
    matchMsg.classList.toggle('ok', ok);
    matchMsg.classList.toggle('error', !ok && cfmPw.value);
  }

  newPw.addEventListener('input', updateRules);
  cfmPw.addEventListener('input', updateMatch);

  // Submit: validate, post, close modal & reload
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const v = newPw.value;
    const allOk = passLen(v) && passUpper(v) && passDigit(v) && newPw.value === cfmPw.value;

    if (!allOk) {
      updateRules(); // reflect current state
      if (!cfmPw.value || newPw.value !== cfmPw.value) updateMatch();
      return; // block submit until valid
    }

    const fd = new FormData(form);
    try {
      const res = await fetch('/edu_ai/profile_change_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (!data.ok) {
        // show inline form message if you have one
        alert(data.errors?._form || 'Please check your old password and requirements.');
        return;
      }

      // Success: close modal, reload
      // If your modal has a specific ID, replace selector below
      setTimeout(() => {
        const modal = document.querySelector('#changePasswordModal, .modal');
        if (modal) modal.style.display = 'none';
        location.reload();
      }, 400);

    } catch (err) {
      alert('Network error. Please try again.');
    }
  });

  // initialize indicators
  updateRules();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- Find the new-password input robustly (keeps your UI) ---
  const newPw =
    document.getElementById('newPassword') ||
    document.getElementById('new_password') ||
    document.querySelector('input[name="newPassword"], input[name="new_password"], input#new-pass');

  if (!newPw) return; // nothing to do if field not found

  // --- Find the 3 rule rows: prefer explicit IDs; else fallback to first 3 children in your rules box ---
  let ruleLen   = document.getElementById('ruleLen');
  let ruleUpper = document.getElementById('ruleUpper');
  let ruleDigit = document.getElementById('ruleDigit');

  if (!ruleLen || !ruleUpper || !ruleDigit) {
    const rulesBox =
      document.getElementById('pwReq') ||
      document.getElementById('pwRules') ||
      document.querySelector('.pw-rules, .pw-checklist');

    if (rulesBox) {
      const rows = Array.from(rulesBox.children).filter(el => el.nodeType === 1);
      // Fallback order: 0 = length, 1 = uppercase, 2 = digit
      ruleLen   = ruleLen   || rows[0];
      ruleUpper = ruleUpper || rows[1];
      ruleDigit = ruleDigit || rows[2];
    }
  }

  // --- Helpers ---
  const hasLen   = s => (s || '').length >= 8;
  const hasUpper = s => /[A-Z]/.test(s || '');
  const hasDigit = s => /\d/.test(s || '');

  // Swap only the glyph while preserving FA prefix classes (fa, fas, far, fa-solid, etc.)
  function setIcon(iconEl, ok) {
    if (!iconEl) return;
    // Keep all fa* prefix classes except the glyph itself
    const keep = Array.from(iconEl.classList).filter(c =>
      /^(fa|fas|far|fal|fad|fat|fa-solid|fa-regular|fa-light|fa-thin)$/.test(c)
    );
    // Add the target glyph
    keep.push(ok ? 'fa-check' : 'fa-circle');
    iconEl.className = keep.join(' ');
  }

  function updateRule(rule, ok) {
    if (!rule) return;
    rule.classList.toggle('ok', ok); // your .ok already styles green
    const icon = rule.querySelector('i');
    if (icon) {
      icon.classList.remove('fa-circle', 'fa-check-circle', 'fa-times', 'fa-xmark', 'fa-circle-xmark');
      // ✓ uses filled circle (fa-check-circle) instead of check alone
      icon.classList.add(ok ? 'fa-check-circle' : 'fa-circle');
    }
  }

  function updateRules() {
    const v = newPw.value;
    updateRule(ruleLen,   hasLen(v));
    updateRule(ruleUpper, hasUpper(v));
    updateRule(ruleDigit, hasDigit(v));
  }

  newPw.addEventListener('input', updateRules);
  updateRules();

});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const newPw = document.getElementById('newPassword');
  const cfmPw = document.getElementById('confirmPassword');
  const matchMsg = document.getElementById('matchMsg'); // your existing <small id="matchMsg">

  if (!newPw || !cfmPw || !matchMsg) return;

  // Hide the message at start
  matchMsg.style.display = 'none';

  function checkMatch() {
    // Only show once user starts typing in confirm password
    if (cfmPw.value.length === 0) {
      matchMsg.style.display = 'none';
      return;
    }

    // Show the message box when typing in confirm password
    matchMsg.style.display = 'block';

    if (cfmPw.value === newPw.value) {
      matchMsg.textContent = 'Passwords match';
      matchMsg.style.color = '#22c55e'; // ✅ green
    } else {
      matchMsg.textContent = 'Passwords do not match';
      matchMsg.style.color = '#dc2626'; // ❌ red
    }
  }

  // Only check when user types in confirm password or new password
  cfmPw.addEventListener('input', checkMatch);
  newPw.addEventListener('input', checkMatch);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form   = document.getElementById('changePasswordForm');
  const newPw  = document.getElementById('newPassword');
  const cfmPw  = document.getElementById('confirmPassword');
  const msgEl  = document.getElementById('passwordMessage') || document.getElementById('matchMsg');

  if (!form) return;

  // helper to find your modal element robustly
  const findModal = () =>
    document.getElementById('changePasswordModal') ||
    form.closest('.modal') ||
    document.querySelector('#changePasswordModal, .modal');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // optional: block if not matching (keeps UX tight)
    if (newPw && cfmPw && newPw.value !== cfmPw.value) {
      if (msgEl) { msgEl.textContent = 'Passwords do not match'; msgEl.style.color = '#dc2626'; }
      return;
    }

    const fd  = new FormData(form);
    const btn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn) btn.disabled = true;

    try {
      const res  = await fetch('/edu_ai/profile_change_password.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (!data.ok) {
        if (msgEl) { msgEl.textContent = (data.errors?._form || 'Please check your old password and requirements.'); msgEl.style.color = '#dc2626'; }
        if (btn) btn.disabled = false;
        return;
      }

      // ✅ success message in green
      if (msgEl) { msgEl.textContent = (data.message || 'Password changed successfully. This window will close in 5 seconds…'); msgEl.style.color = '#22c55e'; }

      // lock inputs while waiting
      form.querySelectorAll('input, button, select').forEach(el => el.disabled = true);

      // ⏳ close modal + reload after 5s
      setTimeout(() => {
        const modal = findModal();
        if (modal) modal.style.display = 'none';
        location.reload();
      }, 5000);

    } catch (err) {
      if (msgEl) { msgEl.textContent = 'Network error. Please try again.'; msgEl.style.color = '#dc2626'; }
      if (btn) btn.disabled = false;
    }
  });
});
</script>

</body>
</html>
