<?php
require __DIR__ . '/auth_student.php';

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
  ?>
  
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edu AI – Bookmarked Lessons</title>
  <link rel="stylesheet" href="home_page.css" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
  />
  <style>
    h1 {
        text-align: center;
        font-size: 2rem;
        color: #007bff;
        margin-top: 1%;
    }
    .bookmark-list {
    margin-left: 3%;
    list-style: none;
    padding-left: 0;
    max-width: 600px;
    margin: 1rem auto;
  }
  .bookmark-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 1rem;
    border: 1px solid #00c6ff;
    border-radius: 8px;
    margin-bottom: 0.7rem;
    background: #f0faff;
    box-shadow: 0 2px 5px rgba(0,198,255,0.2);
    transition: background-color 0.2s ease;
  }
  .bookmark-item:hover {
    background-color: #d6f0ff;
  }
  .bookmark-link {
    color: #007bff;
    font-weight: 600;
    text-decoration: none;
    flex-grow: 1;
  }
  .bookmark-link:hover {
    text-decoration: underline;
  }
  .remove-btn {
    background-color: #e74c3c;
    border: none;
    color: white;
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: background-color 0.3s ease;
    margin-left: 1rem;
  }
  .remove-btn:hover {
    background-color: #c0392b;
  }
  .no-bookmarks {
    font-style: italic;
    color: #666;
    text-align: center;
    margin-top: 2rem;
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
                    <li><a href="logout.php" id="logoutBtn">Log out</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

  <h1>Bookmarked Lessons</h1>
  <ul class="bookmark-list" id="bookmarkList"></ul>
  <script>
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
  </script>

  <script>
function getBookmarks() {
  try { return JSON.parse(localStorage.getItem('lessonBookmarks')) || []; }
  catch { return []; }
}
function saveBookmarks(bm) {
  localStorage.setItem('lessonBookmarks', JSON.stringify(bm));
}

const listEl = document.getElementById('bookmarkList');

function renderBookmarks() {
  const bookmarks = getBookmarks();
  listEl.innerHTML = '';

  if (!bookmarks.length) {
    listEl.innerHTML = '<li class="no-bookmarks">You have no bookmarked lessons.</li>';
    return;
  }

  bookmarks.forEach((bmk, idx) => {
    const li = document.createElement('li');
    li.className = 'bookmark-item';

    const a = document.createElement('a');
    a.className = 'bookmark-link';

    const courseTitle = bmk.course_title || 'Course';
    const lessonTitle = bmk.lesson_title || `Lesson ${ (bmk.lesson_idx ?? 0) + 1 }`;

    a.textContent = `${courseTitle} — ${lessonTitle}`;
    a.href = `course.php?id=${encodeURIComponent(bmk.course_id)}&lesson=${encodeURIComponent(bmk.lesson_idx ?? 0)}`;

    // also set fallbacks for the next page
    a.addEventListener('click', () => {
      try {
        sessionStorage.setItem('selectedCourseId', String(bmk.course_id));
        sessionStorage.setItem('selectedLessonIndex', String(bmk.lesson_idx ?? 0));
      } catch {}
    });

    const removeBtn = document.createElement('button');
    removeBtn.className = 'remove-btn';
    removeBtn.textContent = 'Remove';
    removeBtn.title = 'Remove this bookmark';
    removeBtn.onclick = () => {
      const current = getBookmarks();
      current.splice(idx, 1);
      saveBookmarks(current);
      renderBookmarks();
    };

    li.appendChild(a);
    li.appendChild(removeBtn);
    listEl.appendChild(li);
  });
}

renderBookmarks();

window.addEventListener('storage', (ev) => {
  if (ev.key === 'lessonBookmarks') {
    renderBookmarks();
  }
});

</script>

</body>
</html>
