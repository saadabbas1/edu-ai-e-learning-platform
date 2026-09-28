<?php
require __DIR__ . '/auth_student.php';

// student.php — dynamic greeting + recommended courses
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
    $userLevelLower = strtolower(trim((string)$u['study_level']));  // beginner|intermediate|advanced
  }

  // Fetch ONLY courses that match the student's level
  $stmt = $db->prepare("
    SELECT
      c.id, c.title, c.description, c.level, c.created_at,
      (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
    FROM courses c
    WHERE LOWER(c.level) = ?
    ORDER BY c.created_at DESC
  ");
  $stmt->bind_param('s', $userLevelLower);
  $stmt->execute();
  $recommended = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Throwable $e) {
  $recommended = []; // fail-safe: render no boxes if DB fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edu AI – Student Dashboard</title>
    <link rel="stylesheet" href="home_page.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />

<style>
#modalBackdrop{
  position:fixed; inset:0;
  background:rgba(0,0,0,.45);
  display:none; z-index:9998;
}
#modalBackdrop.show{display:block;}

#quizModal{
  position:fixed; top:50%; left:50%;
  transform:translate(-50%,-50%);
  background:#fff;
  width:90%; max-width:600px;
  max-height:80vh; overflow:auto;
  border-radius:12px;
  padding:1.25rem;
  display:none; z-index:9999;
}
#quizModal.show{display:block;}
</style>
    
</head>

<body>
<header class="navbar">
    <div class="navbar-left">
        <img src="images/Book Logo.png" alt="Edu AI Logo" class="logo">
        <span class="brand-name">Edu AI – Student</span>
    </div>
    
    <nav class="nav-links">
        <a href="#">Dasboard</a>

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
                <img src="images/Profile Avatar.png" alt="User Avatar" class="avatar" style="cursor:pointer;" onclick="document.location='profile.php'">
                <span class="user-name" style="cursor:pointer;" onclick="document.location='profile.php'"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
            </div>

            <!-- Menu Buttons -->
            <div class="profile-actions">
                <div onclick="document.location='mylist.php'" class="action-btn"><i class="fas fa-list-ul"></i> My List</div>
                <div onclick="document.location='bookmark.php'" class="action-btn"><i class="fas fa-book"></i> Notebook</div>
                <div class="action-btn" onclick="document.location='submissions.php'"><i class="fas fa-upload"></i> Submissions</div>
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
    <h2>Welcome back, <?php echo htmlspecialchars($userName); ?></h2>
    <p>Continue where you left off or explore new learning paths.</p>
</section>

<section class="section">
    <h2>Recommended Courses</h2>
    <p>Choose a course acoording to your interest to start learning at your own pace.</p>
    <?php foreach ($recommended as $c): ?>
      <div class="features">
        <div class="course-box" data-course-id="<?php echo (int)$c['id']; ?>">
          <div class="favorite-star">
            <i class="fa-regular fa-star"></i>
          </div>

          <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
          <p class="course-desc"><?php echo nl2br(htmlspecialchars($c['description'])); ?></p>
          <button class="cta-btn" onclick="document.location='course.php?id=<?php echo (int)$c['id']; ?>'" style="margin-top: 1rem;">Start Course</button>
        </div>
      </div>
    <?php endforeach; ?>
    
    <div style="margin-top: 2rem;">
        <button class="cta-btn" onclick="document.location='all_courses.php'" style="margin-top: 1rem;">View All Courses</button>
    </div>
</section>  

<section class="section">
    <h2>Chat Bot</h2>

    <div class="features">
        <div class="course-box">
            <h3>Chat Bot</h3>
            <p>Chat with AI to gain the experience of learning.</p>
            <button class="cta-btn" onclick="document.location='chatbot.php'" style="margin-top: 1rem;">Chat</button>
        </div>

        <div class="course-box">
            <h3>AI Quiz Generator</h3>
            <p>Upload course material and generate quiz using AI.</p>

            <button class="cta-btn" data-action="open-generate-quiz" type="button" style="margin-top:1rem;">Generate Quiz</button>

        </div>

        <div class="course-box">
            <h3>Generated Quizzes</h3>
            <p>Overview and downlaod the previous genrated quizzes.</p>
            <button class="cta-btn" onclick="document.location='ai_quizzes.php'" style="margin-top: 1rem;">View Generated Quiz</button>
        </div>
    </div>
</section>

<section class="section" id="feedback">
    <h2>Give Us Your Feedback</h2>
    <p>We’d love to hear your thoughts and suggestions about Edu AI.</p>
    
    <button class="feedback-button" id="openFeedbackBtn">Give Feedback</button>
</section>

<footer class="eduai-footer">
    <div class="footer-bottom">
        <p>&copy; 2025 EDU AI – Student Portal</p>
    </div>
</footer>

<!-- Feedback Modal -->
<div id="feedbackModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999;">
  <div class="feature-box" style="max-width:600px; margin:5% auto; background:#fff; border-radius:12px; padding:1.25rem; position:relative;">
    <h3 style="margin:0 0 .75rem;">Send Feedback</h3>

    <form id="feedbackForm" novalidate>
      <label style="display:block;margin:.5rem 0 .25rem;">Rating (1–5)</label>
      <select name="rating" id="fbRating" required style="width:100%;padding:.6rem;border-radius:.5rem;border:1px solid #ddd;">
        <option value="">Select rating</option>
        <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
      </select>

      <label style="display:block;margin:1rem 0 .25rem;">Your feedback</label>
      <textarea name="message" id="fbMessage" rows="4" placeholder="Write your feedback..." required
                style="width:100%;padding:.6rem;border-radius:.5rem;border:1px solid #ddd;"></textarea>

      <div style="display:flex;gap:.5rem; margin-top:1rem;">
        <!-- type=submit so ONLY the form submit handler runs -->
        <button class="cta-btn" id="sendFeedbackBtn" type="submit">Send</button>
        <button class="cta-btn" id="closeFeedbackBtn" type="button" style="background:#999;">Cancel</button>
      </div>
    </form>

    <p id="fbStatus" style="margin-top:.75rem; color:#0a7;"></p>
  </div>
</div>

<template id="genQuizTemplate">
  <div class="modal-content enhanced-modal">

    <span class="close-btn" id="genCancelBtn">&times;</span>
    <h2 class="modal-title">AI Quiz Generator</h2>

    <form id="genQuizForm" method="POST" enctype="multipart/form-data">
      
      <label class="form-label" style="margin-top: 1rem;">Select Quiz Format</label>
      <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem;">
        <label><input type="radio" name="quizFormat" value="text" checked> Text</label>
        <label><input type="radio" name="quizFormat" value="image"> Image</label>
      </div>

      <label>Upload File</label>
      <div class="upload-area">
        <p>Drag & drop your file here<br><span>or click to browse</span></p>
        <input type="file" id="aiQuizFile" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,image/*">
        <p id="aiFileNameDisplay" style="margin-top:10px;font-weight:bold;"></p>
      </div>

      <label class="form-label">Select Question Types</label>
      <div class="quiz-switches">
  <label class="switch-label">
    <span>MCQs</span>
    <label class="switch">
      <input type="checkbox" name="types[]" value="mcq">
      <span class="slider round"></span>
    </label>
  </label>

  <label class="switch-label">
    <span>Short Questions</span>
    <label class="switch">
      <input type="checkbox" name="types[]" value="short">
      <span class="slider round"></span>
    </label>
  </label>

  <label class="switch-label">
    <span>Long Questions</span>
    <label class="switch">
      <input type="checkbox" name="types[]" value="long">
      <span class="slider round"></span>
    </label>
  </label>

  <label class="switch-label">
    <span>True / False</span>
    <label class="switch">
      <input type="checkbox" name="types[]" value="tf">
      <span class="slider round"></span>
    </label>
  </label>
</div>

      <button type="submit" class="cta-btn">🚀 Generate Quiz</button>
    </form>
      <p id="quizStatus"
   style="
     display:none;
     margin-top:1rem;
     font-weight:600;
     color:#555;
     text-align:center;">
  ⏳ Quiz is being generated, please wait...
</p>

    <div id="quizPreview" style="margin-top:2rem;display:none;">
      <h3>📝 Generated Quiz Preview</h3>
      <pre id="quizOutput" class="quiz-output"></pre>
      <button class="cta-btn" onclick="downloadGeneratedQuiz()">⬇️ Download Quiz</button>
    </div>
  </div>
</template>

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
    document.addEventListener('click', function (event) {
  const feedbackModal = document.getElementById("feedbackModal");
  if (feedbackModal && event.target === feedbackModal) {
    feedbackModal.style.display = "none";
  }
});

function downloadPDF(text) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  const marginLeft = 15;
  const marginTop = 20;
  const pageWidth = 210 - marginLeft * 2;
  const pageHeight = 297 - marginTop * 2;

  const lines = doc.splitTextToSize(text, pageWidth);

  let cursorY = marginTop;

  lines.forEach(line => {
    if (cursorY > pageHeight) {
      doc.addPage();
      cursorY = marginTop;
    }
    doc.text(line, marginLeft, cursorY);
    cursorY += 7;
  });

  // ✅ DYNAMIC FILE NAME BASED ON UPLOADED FILE
  const fileInput = document.querySelector('#aiQuizFile');
  let filename = 'quiz.pdf';

  if (fileInput && fileInput.files.length > 0) {
    const originalName = fileInput.files[0].name;
    const baseName = originalName.replace(/\.[^/.]+$/, '');
    filename = `${baseName} quiz.pdf`;
  }

  doc.save(filename);
}

async function downloadImagesFromQuiz(text, baseFileName) {
  const zip = new JSZip();

  const MAX_LINES_PER_IMAGE = 28;
  const lineHeight = 34;
  const padding = 40;
  const canvasWidth = 1200;

  // Clean markdown
  const cleanText = text
    .replace(/\*\*/g, '')
    .replace(/\r/g, '')
    .trim();

  const allLines = cleanText.split('\n').filter(l => l.trim() !== '');

  let imageIndex = 1;
  let lineBuffer = [];

  function createImage(lines, index) {
    const canvasHeight = padding * 2 + lines.length * lineHeight;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    // Background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Text
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
    lineBuffer.push(line);

    if (lineBuffer.length >= MAX_LINES_PER_IMAGE) {
      const img = createImage(lineBuffer, imageIndex);
      zip.file(`quiz_page_${imageIndex}.png`, img, { base64: true });
      imageIndex++;
      lineBuffer = [];
    }
  }

  // Remaining lines
  if (lineBuffer.length > 0) {
    const img = createImage(lineBuffer, imageIndex);
    zip.file(`quiz_page_${imageIndex}.png`, img, { base64: true });
  }

  const blob = await zip.generateAsync({ type: 'blob' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${baseFileName} quiz images.zip`;
  a.click();
}
</script>

<script>
(function () {
  // Prevent binding twice if this script is included again
  if (window.__feedbackBound) return;
  window.__feedbackBound = true;

  const openBtn  = document.getElementById('openFeedbackBtn');
  const modal    = document.getElementById('feedbackModal');
  const closeBtn = document.getElementById('closeFeedbackBtn');
  const form     = document.getElementById('feedbackForm');
  const ratingEl = document.getElementById('fbRating');
  const msgEl    = document.getElementById('fbMessage');
  const statusEl = document.getElementById('fbStatus');

  if (!modal || !form) return;

  // Open modal
  openBtn?.addEventListener('click', () => {
    statusEl.textContent = '';
    statusEl.style.color = '#0a7';
    ratingEl.value = '';
    msgEl.value = '';
    modal.style.display = 'block';
  });

  // Close modal
  function closeModal(){ modal.style.display = 'none'; }
  closeBtn?.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // SINGLE submit handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';

    const rating  = parseInt(ratingEl.value || '0', 10);
    const message = (msgEl.value || '').trim();

    if (!(rating >= 1 && rating <= 5) || !message) {
      statusEl.style.color = '#b00';
      statusEl.textContent = 'Please give a rating (1–5) and write feedback.';
      form.dataset.busy = '0';
      return;
    }

    const sendBtn = document.getElementById('sendFeedbackBtn');
    if (sendBtn) sendBtn.disabled = true;

    statusEl.style.color = '#555';
    statusEl.textContent = 'Sending...';

    try {
      const fd = new FormData();
      fd.append('rating', String(rating));
      fd.append('message', message);

      const res  = await fetch('/edu_ai/feedback_submit.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed to send');

      statusEl.style.color = '#0a7';
      statusEl.textContent = 'Thanks! Your feedback was sent.';
      ratingEl.value = '';
      msgEl.value = '';
      setTimeout(closeModal, 700);
    } catch (err) {
      statusEl.style.color = '#b00';
      statusEl.textContent = err.message || 'Could not send feedback.';
    } finally {
      if (sendBtn) sendBtn.disabled = false;
      form.dataset.busy = '0';
    }
  });
})();
</script>

<script>
(function () {
  if (window.__favInitStudent) return;
  window.__favInitStudent = true;

  function fav_get(){ try { return JSON.parse(localStorage.getItem('favorites')) || []; } catch { return []; } }
  function fav_set(l){ localStorage.setItem('favorites', JSON.stringify(l)); }
  function fav_setStar(el, filled){ const i=el.querySelector('i')||el; i.classList.toggle('fa-solid',filled); i.classList.toggle('fa-regular',!filled); }

  function infoFromCard(card){
    const id = card?.dataset?.courseId || null;
    const title = card.querySelector('.course-title,h3')?.innerText?.trim() || 'Untitled';
    const desc  = card.querySelector('.course-desc,p')?.innerText?.trim() || '';
    return { id, title, description: desc };
  }
  function findFavIndex(favs, info){
    let idx = -1;
    if (info.id != null) idx = favs.findIndex(f => String(f.id ?? '') === String(info.id));
    if (idx === -1) idx = favs.findIndex(f => (f.title || '') === info.title);
    return idx;
  }

  function bindStars(){
    document.querySelectorAll('.course-box .favorite-star').forEach(star=>{
      if (star.dataset.bound) return;
      star.dataset.bound = '1';
      star.addEventListener('click', ()=> {
        const card = star.closest('.course-box'); if (!card) return;
        const info = infoFromCard(card);
        let favs = fav_get();
        const idx = findFavIndex(favs, info);
        if (idx === -1) { favs.push({ id: info.id, title: info.title, description: info.description }); fav_setStar(star, true); }
        else { favs.splice(idx,1); fav_setStar(star, false); }
        fav_set(favs);
      });
    });
  }

  function restoreStars(){
    const favs = fav_get();
    document.querySelectorAll('.course-box').forEach(card=>{
      const star = card.querySelector('.favorite-star'); if (!star) return;
      const info = infoFromCard(card);
      const has  = findFavIndex(favs, info) !== -1;
      fav_setStar(star, has);
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{ bindStars(); restoreStars(); });
  window.addEventListener('storage', (e)=>{ if (e.key === 'favorites') restoreStars(); });

  const mo = new MutationObserver(()=>{ bindStars(); restoreStars(); });
  mo.observe(document.body, { childList: true, subtree: true });
})();
</script>

<script>
/* ===== Generate Quiz Modal Logic (FIXED & CLEAN) ===== */
(function () {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function getModalElements() {
    let modal = document.getElementById('quizModal');
    let backdrop = document.getElementById('modalBackdrop');

    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'modalBackdrop';
      document.body.appendChild(backdrop);
    }
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'quizModal';
      document.body.appendChild(modal);
    }
    return { modal, backdrop };
  }

  function openModal(modal, backdrop) {
    backdrop.classList.add('show');
    modal.classList.add('show');
    backdrop.style.display = 'block';
    modal.style.display = 'block';
  }

  function closeModal(modal, backdrop) {
    backdrop.classList.remove('show');
    modal.classList.remove('show');
    backdrop.style.display = 'none';
    modal.style.display = 'none';
  }

  function renderMarkdown(data) {
    if (!data || !Array.isArray(data.items)) return '';
    let out = `# ${data.title || 'Generated Quiz'}\n\n`;
    data.items.forEach((q, i) => {
      out += `Q${i + 1}. ${q.q}\n`;
      if (q.options) {
        q.options.forEach((o, j) => {
          out += `${String.fromCharCode(65 + j)}) ${o}\n`;
        });
      }
      out += `Answer: ${q.answer}\n\n`;
    });
    return out;
  }

  function init() {
    document.addEventListener('click', async function (e) {
      const btn = e.target.closest('[data-action="open-generate-quiz"]');
      if (!btn) return;

      const { modal, backdrop } = getModalElements();
      const tpl = document.getElementById('genQuizTemplate');
      if (!tpl) return;

      modal.innerHTML = '';
      modal.appendChild(tpl.content.cloneNode(true));
      openModal(modal, backdrop);

      const form = modal.querySelector('#genQuizForm');
      const preview = modal.querySelector('#quizOutput');
      const previewWrap = modal.querySelector('#quizPreview');
      const statusMsg = modal.querySelector('#quizStatus');
      const dlBtn = previewWrap.querySelector('button');

      backdrop.onclick = () => closeModal(modal, backdrop);

      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();

        const fd = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');

        statusMsg.style.display = 'block';
        submitBtn.disabled = true;
        previewWrap.style.display = 'none';

        let baseName = 'Quiz';
        const f = fd.get('file');
        if (f && f.name) baseName = f.name.replace(/\.[^/.]+$/, '');

        try {
          const res = await fetch('ai_quiz_generate.php', {
            method: 'POST',
            body: fd
          });

          if (!res.ok) throw new Error('Server error');

          const data = await res.json();
          const md = renderMarkdown(data);

          await fetch('save_ai_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              name: baseName + ' Quiz',
              content: md,
              format: fd.get('quizFormat')
            })
          });

          preview.textContent = md;
          previewWrap.style.display = 'block';

          dlBtn.onclick = () => {
            if (fd.get('quizFormat') === 'image') {
              downloadImagesFromQuiz(md, baseName);
            } else {
              downloadPDF(md);
            }
          };

        } catch (err) {
          alert(err.message);
        } finally {
          statusMsg.style.display = 'none';
          submitBtn.disabled = false;
        }
      });
    });
  }
})();
</script>

<script>
document.addEventListener('change', function (e) {
  if (e.target && e.target.id === 'aiQuizFile') {
    const fileInput = e.target;
    const fileNameDisplay = document.getElementById('aiFileNameDisplay');
    const hint = document.getElementById('aiUploadHint');

    if (fileInput.files && fileInput.files.length > 0) {
      // Show selected file name
      fileNameDisplay.textContent = 'Selected file: ' + fileInput.files[0].name;
      fileNameDisplay.style.display = 'block';

      // Hide hint text
      if (hint) hint.style.display = 'none';
    } else {
      // Reset if no file selected
      fileNameDisplay.textContent = '';
      fileNameDisplay.style.display = 'none';
      if (hint) hint.style.display = 'block';
    }
  }
});
</script>

<!-- Generate Quiz Modal -->
<div id="modalBackdrop"></div>
<div id="quizModal"></div>

</body>
</html>
