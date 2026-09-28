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
  <title>My Favorite Courses</title>
  <link rel="stylesheet" href="home_page.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
      max-width: 700px;
      margin: 1rem auto;
    }

    .bookmark-item {
      display: flex;
      justify-content: space-between;
      flex-direction: column;
      border: 1px solid #00c6ff;
      border-radius: 8px;
      padding: 1rem;
      background: #f0faff;
      margin-bottom: 1rem;
      box-shadow: 0 2px 5px rgba(0, 198, 255, 0.2);
    }

    .bookmark-item h3 {
      margin: 0 0 0.4rem;
      color: #007bff;
    }

    .bookmark-item p {
      margin: 0.2rem 0 0.8rem;
      color: #444;
    }

    .remove-btn {
      align-self: flex-end;
      background-color: #e74c3c;
      border: none;
      color: white;
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 600;
      transition: background-color 0.3s ease;
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
      <img src="images/Book Logo.png" alt="Edu AI Logo" class="logo" />
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

      <div class="avatar-menu-trigger" onclick="toggleProfileMenu()">
        <img src="images/Profile Avatar.png" alt="Profile Icon" class="avatar-icon" />
      </div>
      <div id="profileMenu" class="profile-menu hidden">
        <div class="profile-header">
          <img onclick="document.location='profile.html'" src="images/Profile Avatar.png" alt="User Avatar" class="avatar" />
          <span class="user-name" style="cursor:pointer;" onclick="document.location='profile.php'">
                  <?php echo htmlspecialchars($userName); ?>
                </span>
        </div>
        <div class="profile-actions">
          <div onclick="document.location='mylist.php'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
          <div onclick="document.location='bookmark.php'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
          <div onclick="document.location='submissions.php'" class="action-btn"><i class="fas fa-upload"></i> Submissions</div>
          <div onclick="document.location='quiz_history.php'" class="action-btn"><i class="fas fa-chart-line"></i> Progress</div>
        </div>
        <div class="dropdown-section">
          <ul class="dropdown-list">
            <li><a href="logout.php">Log out</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <h1>My Favorite Courses</h1>
  <ul class="bookmark-list" id="favoriteCourses"></ul>

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

// --- helper: normalize any favorite object to { id, title, description } ---
function normalizeCourse(raw) {
  const title =
    raw.title ??
    raw.name ??
    raw.courseName ??
    raw.course_title ??
    '(Untitled Course)';

  const id =
    raw.id ??
    raw.course_id ??
    raw.courseId ??
    raw.courseID ??
    null;

  const description =
    raw.description ??
    raw.desc ??
    raw.courseDescription ??
    '';

  return { id, title, description, _raw: raw };
}

function getFavorites() {
  const fav = localStorage.getItem('favorites');
  return fav ? JSON.parse(fav) : [];
}
function saveFavorites(favs) {
  localStorage.setItem('favorites', JSON.stringify(favs));
}

function migrateOldFavorites() {
  const raw = localStorage.getItem('favorites');
  if (!raw) return;
  let favs;
  try { favs = JSON.parse(raw); } catch { return; }

  let changed = false;
  const fixed = favs.map(item => {
    // if someone saved just a string before
    if (typeof item === 'string') { changed = true; return { title: item, id: null, description: '' }; }
    // ensure shape
    const title = item.title ?? item.name ?? item.courseName ?? item.course_title ?? '(Untitled Course)';
    const id    = item.id ?? item.course_id ?? item.courseId ?? item.courseID ?? null;
    const desc  = item.description ?? item.desc ?? item.courseDescription ?? '';
    if (title !== item.title || id !== item.id || desc !== item.description) changed = true;
    return { id, title, description: desc };
  });

  if (changed) localStorage.setItem('favorites', JSON.stringify(fixed));
}
migrateOldFavorites();

function renderFavorites() {
  const list = document.getElementById('favoriteCourses');
  const favorites = getFavorites();
  list.innerHTML = '';

  if (!favorites.length) {
    list.innerHTML = '<li class="no-bookmarks">You haven’t added any favorite courses yet.</li>';
    return;
  }

  favorites.map(normalizeCourse).forEach(course => {
    const item = document.createElement('li');
    item.className = 'bookmark-item';

    // clickable title
    const titleLink = document.createElement('a');
    titleLink.textContent = course.title;  // <-- now always has a value
    const href = course.id != null
      ? `course.php?id=${encodeURIComponent(course.id)}`
      : 'course.php';
    titleLink.href = href;
    titleLink.style.textDecoration = 'none';
    titleLink.style.color = '#007bff';
    titleLink.style.fontSize = '1.2rem';
    titleLink.style.fontWeight = '600';

    // also stash the selected course id/title as a fallback
    titleLink.addEventListener('click', () => {
      try {
        if (course.id != null) {
          sessionStorage.setItem('selectedCourseId', String(course.id));
        } else {
          sessionStorage.setItem('selectedCourseTitle', course.title);
        }
      } catch {}
    });

    const desc = document.createElement('p');
    desc.textContent = course.description || 'No description provided.';

    const removeBtn = document.createElement('button');
    removeBtn.className = 'remove-btn';
    removeBtn.textContent = 'Remove';
    removeBtn.onclick = () => {
      const updated = getFavorites().filter(f => {
        const n = normalizeCourse(f);
        if (course.id != null) return n.id !== course.id;
        return n.title !== course.title;
      });
      saveFavorites(updated);
      renderFavorites();
    };

    item.appendChild(titleLink);
    item.appendChild(desc);
    item.appendChild(removeBtn);
    list.appendChild(item);
  });
}

// call once at load
renderFavorites();

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
    
    function toggleProfileMenu() {
      const menu = document.getElementById("profileMenu");
      menu.classList.toggle("hidden");
    }

    function getCourseId() {
  const p = new URLSearchParams(location.search);
  let id = parseInt(p.get('id') || '0', 10);
  if (!id) {
    try {
      const fromSession = sessionStorage.getItem('selectedCourseId');
      if (fromSession) id = parseInt(fromSession, 10);
    } catch {}
  }
  return id;
}

// Live-update My List if favorites change (from student.php or all_courses.html)
window.addEventListener('storage', (ev) => {
  if (ev.key === 'favorites') renderFavorites();
});

  </script>

</body>
</html>
