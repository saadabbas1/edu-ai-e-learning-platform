<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>All Courses – Edu AI</title>
    <link rel="stylesheet" href="home_page.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

    <!-- Main Section -->
    <section class="section">
        <h2>Available Courses</h2>
        <p>Browse all available courses below and continue your learning journey.</p>

        <div class="features" id="coursesGrid">
            <!-- Will be filled dynamically with .course-box cards -->
        </div>
    </section>

    <!-- Footer -->
    <footer class="eduai-footer">
        <div class="footer-bottom">
            <p>&copy; 2025 EDU AI – Student Portal</p>
        </div>
    </footer>

    <!-- Dropdown Scripts (Same as student.html) -->
        <script>
let notificationsRead = false;

// ---------- Favorite helpers (id-aware) ----------
function getFavorites() {
  try { return JSON.parse(localStorage.getItem('favorites')) || []; }
  catch { return []; }
}
function setFavorites(favs) {
  localStorage.setItem('favorites', JSON.stringify(favs));
}
function toggleFavorite(el) {
  const box = el.closest('.course-box');
  const id  = box?.dataset.courseId || null;
  const title = box.querySelector('h3')?.innerText?.trim() || 'Untitled';
  const description = box.querySelector('p')?.innerText?.trim() || '';

  let favorites = getFavorites();
  let idx = -1;
  if (id != null) idx = favorites.findIndex(f => String(f.id ?? '') === String(id));
  if (idx === -1) idx = favorites.findIndex(f => (f.title || '') === title);

  const icon = el.querySelector('i') || el;
  if (idx === -1) {
    favorites.push({ id, title, description });
    icon.classList.remove('fa-regular');
    icon.classList.add('fa-solid');
  } else {
    favorites.splice(idx, 1);
    icon.classList.remove('fa-solid');
    icon.classList.add('fa-regular');
  }
  setFavorites(favorites);
}

// ---------- Render a single .course-box (same UI) ----------
function renderCourseCard(course) {
  const div = document.createElement('div');
  div.className = 'course-box';
  div.dataset.courseId = course.id;

  div.innerHTML = `
    <div class="favorite-star"><i class="fa-regular fa-star"></i></div>
    <h3>${course.title || 'Untitled'}</h3>
    <p>${course.description || ''}</p>
    <button class="cta-btn" onclick="location.href='course.php?id=${encodeURIComponent(course.id)}'">Start Course</button>
  `;

  // bind favorite star
  const star = div.querySelector('.favorite-star');
  star.addEventListener('click', function() { toggleFavorite(this); });

  // restore star state
  const favorites = getFavorites();
  const isFav = favorites.some(f => 
    (f.id != null ? String(f.id) === String(course.id) : (f.title || '') === (course.title || 'Untitled'))
  );
  if (isFav) {
    const icon = star.querySelector('i');
    icon.classList.remove('fa-regular');
    icon.classList.add('fa-solid');
  }

  return div;
}

// ---------- Load & render all matching courses ----------
async function loadCoursesForStudent() {
  const grid = document.getElementById('coursesGrid') || document.querySelector('.features');
  if (!grid) return;

  grid.innerHTML = '<div class="course-box"><p>Loading courses…</p></div>';

  try {
    const res = await fetch('/edu_ai/courses_for_student.php', { credentials: 'same-origin', cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to load courses');

    const items = data.items || [];
    grid.innerHTML = '';

    if (!items.length) {
      grid.innerHTML = '<div class="course-box"><p>No courses for your study level yet.</p></div>';
      return;
    }

    items.forEach(c => grid.appendChild(renderCourseCard(c)));
  } catch (e) {
    grid.innerHTML = `<div class="course-box"><p style="color:#b00;">${e.message || 'Error loading courses'}</p></div>`;
  }
}

// ---------- Existing header/profile code (kept as-is) ----------
function toggleNotifications() {
  const notif = document.getElementById("notifDropdown");
  const badge = document.getElementById("notifBadge");
  notif.style.display = notif.style.display === "block" ? "none" : "block";
  if (!notificationsRead) { notificationsRead = true; badge.style.display = "none"; }
}
function toggleProfileMenu() {
  const profileMenu = document.getElementById("profileMenu");
  profileMenu.classList.toggle("hidden");
}
window.onclick = function(event) {
  const notifDropdown = document.getElementById("notifDropdown");
  const profileMenu = document.getElementById("profileMenu");
  const notifTrigger = document.querySelector(".nav-notification");
  const profileTrigger = document.querySelector(".avatar-menu-trigger");
  if (notifDropdown && !notifDropdown.contains(event.target) && !notifTrigger.contains(event.target)) {
    notifDropdown.style.display = "none";
  }
  if (profileMenu && !profileMenu.contains(event.target) && !profileTrigger.contains(event.target)) {
    profileMenu.classList.add("hidden");
  }
};
document.getElementById("logoutBtn")?.addEventListener("click", function(event) {
  event.preventDefault();
  localStorage.removeItem("isLoggedIn");
  localStorage.removeItem("userRole");
  window.location.href = "login_form.html";
});

// ---------- Kick off ----------
document.addEventListener('DOMContentLoaded', loadCoursesForStudent);

// Repaint stars from localStorage (used by the storage listener)
function refreshStarIconsFromStorage() {
  const favs = getFavorites();
  document.querySelectorAll('#coursesGrid .course-box').forEach(box => {
    const id = box.dataset.courseId || null;
    const title = box.querySelector('h3')?.innerText?.trim() || 'Untitled';
    const star = box.querySelector('.favorite-star i');
    if (!star) return;

    const isFav = favs.some(f => (f.id != null ? String(f.id) === String(id) : (f.title || '') === title));
    star.classList.toggle('fa-solid',   isFav);
    star.classList.toggle('fa-regular', !isFav);
  });
}

// If favorites change in another page/tab, update stars live here
window.addEventListener('storage', (ev) => {
  if (ev.key === 'favorites') refreshStarIconsFromStorage();
});

</script>
</body>
</html>
