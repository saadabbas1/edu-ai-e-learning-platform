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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AI Generated Quizzes</title>
  <link rel="stylesheet" href="home_page.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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
                <img onclick="document.location='profile.php'" src="images/Profile Avatar.png" alt="User Avatar" class="avatar">
                <span class="user-name" style="cursor:pointer;" onclick="document.location='profile.php'">
                  <?php echo htmlspecialchars($userName); ?>
                </span>
            </div>

            <!-- Menu Buttons -->
            <div class="profile-actions">
                <div onclick="document.location='mylist.html'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
                <div onclick="document.location='bookmark.html'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
                <div onclick="document.location='submissions.html'" class="action-btn"><i class="fas fa-upload"></i> Submissions</div>
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

  <section class="section">
    <h2>Your AI Generated Quizzes</h2>
    <p>View and download previously generated quizzes.</p>

    <div id="quizList" class="features"></div>
  </section>

  <footer class="eduai-footer">
    <div class="footer-bottom">
      <p>&copy; 2025 EDU AI – Student Portal</p>
    </div>
  </footer>

  <!-- Quiz View Modal -->
<div id="quizViewBackdrop" style="
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.5);
  z-index:9998;">
</div>

<div id="quizViewModal" style="
  display:none;
  position:fixed;
  top:50%;
  left:50%;
  transform:translate(-50%,-50%);
  width:90%;
  max-width:800px;
  max-height:85vh;
  overflow:auto;
  background:#fff;
  border-radius:12px;
  padding:1.25rem;
  z-index:9999;">
  
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">📄 Quiz Preview</h3>
    <button onclick="closeQuizView()" style="font-size:1.5rem;border:none;background:none;cursor:pointer;">&times;</button>
  </div>

  <hr>

  <pre id="quizViewContent" style="
    white-space:pre-wrap;
    font-family:inherit;
    line-height:1.6;
    margin-top:1rem;">
  </pre>

  <div style="text-align:right;margin-top:1rem;">
    <button class="cta-btn" id="quizViewDownloadBtn">⬇️ Download</button>
  </div>
</div>

  <script>
    function renderQuizzes() {
  const quizList = document.getElementById("quizList");
  quizList.innerHTML = "";

  if (quizzes.length === 0) {
    quizList.innerHTML = "<p>No quizzes found. Generate some using the AI Quiz Generator.</p>";
    return;
  }

  [...quizzes].reverse().forEach((quiz, index) => {
    const box = document.createElement("div");
    box.className = "course-box";
    box.innerHTML = `
      <h3>${quiz.name || `Quiz ${quizzes.length - index}`}</h3>
      <pre style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;">${quiz.content}</pre>
      <button class="cta-btn" onclick="downloadPDF(${quizzes.length - 1 - index})">⬇️ Download</button>
      <button class="cta-btn danger" onclick="deleteQuiz(${quizzes.length - 1 - index})" style="background-color: #e74c3c; margin-left: 10px;">🗑️ Delete</button>
    `;
    quizList.appendChild(box);
  });
}

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

  <script>
let quizzes = [];

document.addEventListener('DOMContentLoaded', async () => {
  const container = document.getElementById('quizList');
  container.innerHTML = '<p>Loading quizzes...</p>';

  try {
    const res = await fetch('fetch_ai_quizzes.php', {
      credentials: 'same-origin',
      cache: 'no-store'
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);

    quizzes = await res.json();

    if (!Array.isArray(quizzes) || quizzes.length === 0) {
      container.innerHTML = '<p>No quizzes generated yet.</p>';
      return;
    }

    renderQuizzes();

  } catch (err) {
    container.innerHTML = `<p style="color:red">Error loading quizzes: ${err.message}</p>`;
  }
});
</script>

<script>
function renderQuizzes() {
  const container = document.getElementById('quizList');
  container.innerHTML = '';

  quizzes.forEach(q => {
    const box = document.createElement('div');
    box.className = 'course-box';

    box.innerHTML = `
      <h3>${escapeHtml(q.name || 'Untitled Quiz')}</h3>
      <p><strong>Format:</strong> ${escapeHtml(q.format || 'text')}</p>
      <p><strong>Created:</strong> ${new Date(q.created_at).toLocaleString()}</p>

      <div style="display:flex;justify-content:center;gap:1rem;margin-top:1rem;">
        <button class="cta-btn" onclick="viewQuiz(${q.id})">👁 View</button>
        <button class="cta-btn" onclick="downloadQuiz(${q.id})">⬇️ Download</button>
      </div>
    `;

    container.appendChild(box);
  });
}
</script>

<script>
/* ---------- VIEW MODAL ---------- */
function viewQuiz(id) {
  const quiz = quizzes.find(q => q.id == id);
  if (!quiz) return;

  document.getElementById('quizViewContent').textContent = quiz.content || '';
  document.getElementById('quizViewDownloadBtn').onclick = () => downloadQuiz(id);

  document.getElementById('quizViewBackdrop').style.display = 'block';
  document.getElementById('quizViewModal').style.display = 'block';
}

function closeQuizView() {
  document.getElementById('quizViewBackdrop').style.display = 'none';
  document.getElementById('quizViewModal').style.display = 'none';
}

/* ---------- DOWNLOAD ---------- */
function downloadPDF(text, filename) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  const marginLeft = 15;
  const marginTop = 20;
  const pageWidth = 210 - marginLeft * 2;
  const pageHeight = 297 - marginTop * 2;

  const cleanText = String(text || '')
    .replace(/\r/g, '')
    .trim();

  const lines = doc.splitTextToSize(cleanText, pageWidth);

  let cursorY = marginTop;

  for (const line of lines) {
    if (cursorY > pageHeight) {
      doc.addPage();
      cursorY = marginTop;
    }
    doc.text(line, marginLeft, cursorY);
    cursorY += 7;
  }

  doc.save(filename || "quiz.pdf");
}

/* ---------- HELPERS ---------- */
function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}
</script>

<script>
async function downloadImagesFromQuiz(text, baseFileName) {
  const zip = new JSZip();

  const MAX_LINES_PER_IMAGE = 28;  // 20-30 lines per image (as you wanted)
  const lineHeight = 34;
  const padding = 40;
  const canvasWidth = 1200;

  const cleanText = String(text || '')
    .replace(/\*\*/g, '')   // remove markdown bold
    .replace(/\r/g, '')
    .trim();

  const allLines = cleanText.split('\n').filter(l => l.trim() !== '');

  let imageIndex = 1;
  let buffer = [];

  function createImage(lines) {
    const canvasHeight = padding * 2 + lines.length * lineHeight;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = '#000000';
    ctx.font = '22px Arial';

    let y = padding;

    for (const line of lines) {
      ctx.fillText(line, 50, y);
      y += lineHeight;
    }

    return canvas.toDataURL('image/png').split(',')[1];
  }

  for (const line of allLines) {
    buffer.push(line);

    if (buffer.length >= MAX_LINES_PER_IMAGE) {
      const img = createImage(buffer);
      zip.file(`quiz_page_${imageIndex}.png`, img, { base64: true });
      imageIndex++;
      buffer = [];
    }
  }

  if (buffer.length > 0) {
    const img = createImage(buffer);
    zip.file(`quiz_page_${imageIndex}.png`, img, { base64: true });
  }

  const blob = await zip.generateAsync({ type: 'blob' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${baseFileName} quiz images.zip`;
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>

<script>
function downloadQuiz(id) {
  const quiz = quizzes.find(q => String(q.id) === String(id));
  if (!quiz) {
    alert('Quiz not found');
    return;
  }

  const baseName = (quiz.name || 'quiz')
    .replace(/[\\/:*?"<>|]+/g, '')
    .trim() || 'quiz';

  const format = (quiz.format || 'text').toLowerCase();

  if (format === 'image') {
    downloadImagesFromQuiz(quiz.content || '', baseName);
  } else {
    downloadPDF(quiz.content || '', `${baseName}.pdf`);
  }
}
</script>

</body>
</html>
