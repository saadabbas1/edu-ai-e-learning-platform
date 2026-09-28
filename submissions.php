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
  <link rel="stylesheet" href="quiz_history.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <title>Edu AI – Quiz Submissions</title>
</head>

<body data-theme="<?= htmlspecialchars($theme ?? 'light') ?>">
<header class="navbar">
  <div class="navbar-left">
    <img src="images/Book Logo.png" class="logo">
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
      <img src="images/Profile Avatar.png" class="avatar-icon">
    </div>

    <div id="profileMenu" class="profile-menu hidden">
      <div class="profile-header">
        <img src="images/Profile Avatar.png" class="avatar">
        <span class="user-name" style="cursor:pointer;" onclick="document.location='profile.php'">
                  <?php echo htmlspecialchars($userName); ?>
                </span>
      </div>

      <div class="profile-actions">
        <div onclick="location='mylist.php'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
        <div onclick="location='bookmark.php'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
        <div onclick="location='submissions.php'" class="action-btn"><i class="fas fa-upload"></i> Submissions</div>
        <div onclick="location='quiz_history.php'" class="action-btn"><i class="fas fa-chart-line"></i> Progress</div>
      </div>

      <ul class="dropdown-list">
        <li><a href="logout.php" id="logoutBtn">Log out</a></li>
      </ul>
    </div>
  </nav>
</header>

<div class="history-wrapper">
  <h2>Quiz Submissions Overview</h2>

  <div style="display:flex;gap:20px;margin-bottom:20px;justify-content:center;">
    <div>
      <label>Select Course:</label><br>
      <select id="courseDropdown"></select>
    </div>

    <div>
      <label>Select Lesson:</label><br>
      <select id="lessonDropdown" style="display:none;"></select>
    </div>
  </div>

  <div class="progress-chart-container">
    <h3 style="text-align:center;color:#007bff;font-size:1.5rem;">Quiz Scores</h3>
    <canvas id="submissionChart" style="display:block;margin:0 auto;width:100%;height:400px;"></canvas>
  </div>
</div>

<footer class="eduai-footer">
  <div class="footer-bottom">
    <p>&copy; 2025 EDU AI – Student Portal</p>
  </div>
</footer>

<script>
/* ===== GLOBAL STATE ===== */
let courses = [];
let lessonsByCourse = {};
let submissions = [];

const courseDropdown = document.getElementById("courseDropdown");
const lessonDropdown = document.getElementById("lessonDropdown");

/* ===== LOAD DATA ===== */
window.onload = async () => {
  try {
    const cRes = await fetch('/edu_ai/get_courses_and_lessons.php', { credentials:'same-origin' });
    const cData = await cRes.json();
    if (!cData.ok) throw cData.error;

    courses = cData.courses;
    lessonsByCourse = cData.lessons;

    const sRes = await fetch('/edu_ai/get_quiz_submissions.php', { credentials:'same-origin' });
    const sData = await sRes.json();
    if (!sData.ok) throw sData.error;

    submissions = sData.submissions;

    populateCourses();
    updateLessonVisibility();
    updateChart();

  } catch (e) {
    console.error(e);
    alert('Failed to load quiz submissions');
  }
};

/* ===== DROPDOWNS ===== */
function populateCourses() {
  courseDropdown.innerHTML = '<option value="all">All Courses</option>';
  courses.forEach(c => {
    courseDropdown.add(new Option(c.title, c.id));
  });
}

function populateLessons(courseId) {
  lessonDropdown.innerHTML = '';
  if (courseId === 'all') {
    lessonDropdown.style.display = 'none';
    return;
  }
  lessonDropdown.style.display = 'inline-block';
  lessonDropdown.add(new Option('All Lessons', 'all'));
  (lessonsByCourse[courseId] || []).forEach(l => {
    lessonDropdown.add(new Option(l.title, l.id));
  });
}

/* ===== FILTER ===== */
function getFilteredSubmissions(courseId, lessonId) {
  return submissions.filter(s => {
    const c = courseId === 'all' || String(s.course_id) === String(courseId);
    const l = lessonId === 'all' || String(s.lesson_id) === String(lessonId);
    return c && l;
  });
}

/* ===== CHART (UNCHANGED DESIGN) ===== */
const ctx = document.getElementById("submissionChart").getContext("2d");
const submissionChartInstance = new Chart(ctx, {
  type: "bar",
  data: {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: "#00c6ff",
      borderRadius: 10,
      maxBarThickness: 30,
      hoverBackgroundColor: "#007bff",
      borderSkipped: false
    }]
  },
  options: {
    responsive: false,
    maintainAspectRatio: false,
    layout: { padding: { top: 10, bottom: 10 } },
    scales: {
      y: {
        beginAtZero: true,
        max: 100,
        ticks: {
          stepSize: 20,
          color: "#007bff",
          font: { weight: "600", size: 12 },
          callback: v => v + "%"
        },
        grid: {
          color: "#e0f0ff",
          borderColor: "#c0d8ff",
          lineWidth: 1
        }
      },
      x: {
        ticks: {
          color: "#007bff",
          font: { weight: "600", size: 12 }
        },
        grid: { display: false }
      }
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        enabled: true,
        backgroundColor: "#007bff",
        titleColor: "#ffffff",
        bodyColor: "#e0f0ff",
        padding: 8,
        cornerRadius: 6,
        callbacks: {
          label: ctx => `Score: ${ctx.parsed.y}%`
        }
      }
    },
    aspectRatio: 1.7
  }
});

function renderSubmissionChart(list) {
  submissionChartInstance.data.labels = list.map((_, i) => `Quiz ${i + 1}`);
  submissionChartInstance.data.datasets[0].data = list.map(s =>
    s.max_score ? Math.round((s.score / s.max_score) * 100) : 0
  );
  submissionChartInstance.update();

  /* ===== TOTAL QUIZZES TEXT (RESTORED) ===== */
  const container = document.querySelector(".progress-chart-container");

  let totalText = container.querySelector(".total-quizzes-text");
  if (totalText) totalText.remove();

  totalText = document.createElement("p");
  totalText.className = "total-quizzes-text";
  totalText.style.cssText =
    "text-align:center; font-weight:600; color:#007bff; margin-top:0.5rem;";
  totalText.textContent = `Total quizzes: ${list.length}`;

  container.appendChild(totalText);
}

/* ===== UPDATE ===== */
function updateChart() {
  renderSubmissionChart(
    getFilteredSubmissions(courseDropdown.value, lessonDropdown.value || 'all')
  );
}

function updateLessonVisibility() {
  populateLessons(courseDropdown.value);
}

courseDropdown.addEventListener("change", () => {
  updateLessonVisibility();
  updateChart();
});
lessonDropdown.addEventListener("change", updateChart);

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
  document.getElementById("profileMenu").classList.toggle("hidden");
}

document.getElementById("logoutBtn").onclick = e => {
  e.preventDefault();
  localStorage.clear();
  location.href = "login_form.html";
};
</script>
</body>
</html>
