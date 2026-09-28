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
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Chatbot</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
    font-family: Arial, sans-serif;
    background: #f2f6fc;
    margin: 0;
    padding: 0;
    color: #333;
  }

    #app {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      width: 720px;
      height: 90vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      margin-left: 30%;
      margin-top: 2%;
    }

    .header {
      padding: 1rem 2rem;
      font-weight: 700;
      font-size: 1.5rem;
      background: linear-gradient(to right, #00c6ff, #007bff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      border-bottom: 1px solid rgba(0,0,0,0.1);
      user-select: none;
    }

    #messages {
      flex: 1;
      padding: 1.5rem 2rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      background: #f0f4f8;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    .message {
      max-width: 70%;
      padding: 1rem 1.2rem;
      border-radius: 12px;
      line-height: 1.4;
      font-size: 1rem;
      white-space: pre-wrap;
      word-break: break-word;
      box-shadow: 0 2px 6px rgba(0, 198, 255, 0.15);
      font-weight: 500;
    }

    .user-message {
      align-self: flex-end;
      background: #007bff;
      color: #fff;
      border-bottom-right-radius: 0;
    }

    .bot-message {
      align-self: flex-start;
      background: #e2f0ff;
      color: #007bff;
      border-bottom-left-radius: 0;
    }

    footer {
      padding: 1rem 1.5rem;
      background: #fff;
      box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
    }

    .attach-btn:hover {
  color: #4338ca;
}

    textarea {
  flex: 1;
  resize: none;
  border: none;
  outline: none;
  background: transparent;
  font-size: 1rem;
  line-height: 1.4;
  min-height: 36px;
  max-height: 120px;
}

/* === ChatGPT-style single-row input === */
.input-wrapper {
  display: flex;
  align-items: center;       /* 🔑 same vertical level */
  gap: 10px;
  padding: 10px 12px;
  background: #f9fafb;
  border-radius: 18px;
  border: 1px solid #e5e7eb;
}

footer {
  padding: 12px 16px;
  background: #fff;
}

/* Attach icon */
.attach-btn {
  background: none;
  border: none;
  color: #6b7280;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.attach-btn:hover {
  color: #111827;
}

/* Textarea */
.input-wrapper textarea {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  resize: none;
  font-size: 15px;
  line-height: 1.5;
  min-height: 24px;
  max-height: 120px;
  padding: 4px 0;
  color: #111827;
}

/* Send button */
#sendBtn {
  background: #10a37f;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

#sendBtn:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}

#sendBtn svg {
  width: 14px;
  height: 14px;
}

    .icon-btn:hover:not(:disabled) {
      background-color: #e0e7ff; /* light blue */
      color: #4338ca; /* indigo-700 */
    }

    .icon-btn:disabled {
      cursor: not-allowed;
      color: #d1d5db;
    }

    /* Send button is a circle with a filled blue background */
    #sendBtn {
  background: #4338ca;
  color: white;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  border: none;
  cursor: pointer;
}
    #sendBtn:disabled {
  background: #a5b4fc;
  cursor: not-allowed;
}
    #sendBtn:hover:not(:disabled) {
      background: #3730a3; /* indigo-800 */
    }

    /* Scrollbar styling */
    #messages::-webkit-scrollbar {
      width: 8px;
    }

    #messages::-webkit-scrollbar-thumb {
      background-color: rgba(0, 198, 255, 0.3);
      border-radius: 4px;
    }

    #messages::-webkit-scrollbar-track {
      background: #f9fafb;
    }

.attach-btn, .icon-btn, #sendBtn {
  /* keep your existing button styles */
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  padding: 0;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.attach-btn:hover {
  background-color: #e0e7ff;
  color: #4338ca;
}

.icon-btn:hover {
  background-color: #e0e7ff;
  color: #4338ca;
}

#sendBtn {
  background: #4338ca;
  color: white;
  border-radius: 50%;
  width: 36px;
  height: 36px;
}

#sendBtn:disabled {
  background: #a5b4fc;
  cursor: not-allowed;
}

#sendBtn:hover:not(:disabled) {
  background: #3730a3;
}

textarea {
  flex: 1;
  resize: none;
  border: none;
  outline: none;
  background: transparent;
  font-family: 'Inter', sans-serif;
  font-weight: 500;
  font-size: 1rem;
  line-height: 1.4;
  min-height: 36px;
  max-height: 120px;
  padding: 6px 0;
  color: #111827;
  overflow-y: auto;
}

.attach-btn, .icon-btn {
  cursor: pointer;
  color: #6b7280;
  width: 36px;
  height: 36px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease, color 0.2s ease;
  background: none;
  border: none;
}

.attach-btn:hover, .icon-btn:hover {
  background-color: #e0e7ff;
  color: #4338ca;
}

#sendBtn {
  background: #4338ca;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease;
  border: none;
  cursor: pointer;
}

#sendBtn:disabled {
  background: #a5b4fc;
  cursor: not-allowed;
}

#sendBtn:hover:not(:disabled) {
  background: #3730a3;
}

.attach-btn{
  cursor: pointer;
  color: #4338ca; /* Indigo */
  background: #e0e7ff; /* Light Indigo bg */
  width: auto;
  height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  font-size: 0.9rem;
  border: none;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.attach-btn:hover {
  background-color: #c7d2fe; /* Slightly darker Indigo */
  color: #3730a3;
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 32px;
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 0 25px rgba(0, 198, 255, 0.1);
    font-family: 'Inter', sans-serif;
  }

  .navbar-left {
    display: flex;
    align-items: center;
  }

  .logo {
    height: 40px;
    margin-right: 12px;
  }

  .brand-name {
    font-size: 1.7rem;
    font-weight: 600;
    background: linear-gradient(to right, #00c6ff, #007bff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    width: max-content;
  }

  .nav-links {
  display: flex;
  align-items: center;
  gap: 24px;
}


  .nav-links a {
    position: relative;
    margin-left: 24px;
    color: #00c6ff;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .nav-links a::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -5px;
    width: 0%;
    height: 2px;
    background: linear-gradient(to right, #00c6ff, #007bff);
    transition: width 0.3s ease;
  }

  .nav-links a:hover::after {
    width: 100%;
  }

  .nav-links a:hover {
    color: #007bff;
  }

  @media (max-width: 768px) {
    .navbar {
      flex-direction: column;
      align-items: flex-start;
    }

    .nav-links {
      display: flex;
      flex-direction: column;
      width: 100%;
      margin-top: 10px;
    }

    .nav-links a {
      margin: 8px 0;
    }
  }

  @media (max-width: 768px) {
  .hero-content h1 {
    font-size: 2.2rem;
  }
  
  .cta-btn {
    width: 100%;
  }
  
  .nav-links {
    display: none;
  }
  
  .navbar {
    flex-direction: column;
  }
}

select {
    width: 200px;
    padding: 8px;
    font-size: 16px;
    background-color: #f2f2f2;
    border: 1px solid #ccc;
    border-radius: 4px;
    appearance: none;
    background: url('arrow-icon.png') no-repeat right center;
    transition: background-color 0.3s ease;
}

select:hover {
    background-color: #e6e6e6;
}

select:focus {
    outline: 2px solid #3366cc;
}

.dropdowns {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.dropdown {
  position: relative;
  display: inline-block;
  font-size: 0.95rem;
}

.nav-notification,
.avatar-icon {
  margin-left: 1rem;
}

.nav-notification {
    position: relative;
    cursor: pointer;
    font-size: 1.2rem;
    color: #00c6ff;
}

.notif-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.7rem;
}

.nav-notification:hover {
  color: #007bff;
}

.notif-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background-color: #fff;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    padding: 1rem;
    border-radius: 8px;
    min-width: 240px;
    z-index: 999;
}

.notif-dropdown.active {
  display: block;
}

.notif-dropdown p {
    margin: 0 0 0.5rem;
    font-weight: bold;
    color: #333;
}

.notif-dropdown a {
    display: block;
    padding: 0.5rem 0;
    color: #555;
    text-decoration: none;
    border-bottom: 1px solid #eee;
}

.notif-dropdown a:last-child {
    border-bottom: none;
}

.notif-dropdown a:hover {
    color: #007bff;
}

.avatar-icon {
  width: 25px;
  height: 25px;
  border-radius: 50%;
  object-fit: cover;
  cursor: pointer;
}

.profile-menu {
  position: absolute;
  top: 60px;
  right: 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
  width: 300px;
  padding: 1rem;
  z-index: 1000;
}

.hidden {
  display: none;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.profile-header img{
  cursor: pointer;
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.username {
  font-weight: 600;
  font-size: 1.1rem;
  color: #00c6ff;;
}

.profile-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.8rem;
  margin-bottom: 1rem;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem;
  border-radius: 8px;
  background: #f6f9ff;
  cursor: pointer;
  color: #007BFF; 
  transition: color 0.3s ease;
  background-color: transparent;
}
.action-btn:hover{
  color: #0056b3;              /* Darker blue on hover */
  background-color: #e6f0ff; 
}

.dropdown-section {
  margin-top: 1rem;
  margin-left: -7%;
}

.dropdown-title {
  font-size: 0.95rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.dropdown-list {
  list-style: none;
  padding-left: 0;
}

.dropdown-list li a {
  text-decoration: none;
  color: #007bff;
  display: block;
  padding: 0.5rem 0;
}

a:focus, button:focus {
  outline: 2px dashed #007bff;
  outline-offset: 3px;
}

:root {
  --blue: #007bff;
  --blue-light: #00c6ff;
  --gradient: linear-gradient(to right, var(--blue-light), var(--blue));
}

.cta-btn {
  background: var(--gradient);
}

html {
  scroll-behavior: smooth;
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
  
  <div id="app" role="main" aria-label="Chatbot interface">
    <p class="header">Chat with AI</p>
    <div id="messages" aria-live="polite" aria-relevant="additions" tabindex="0"></div>
    <footer>
      <div class="input-wrapper">
  
  <!-- Attach button (left, inside input) -->
  <button id="attachBtn" class="attach-btn" aria-label="Attach file" type="button">
    <i class="fas fa-paperclip"></i>
  </button>

  <!-- Text input -->
  <textarea
    id="input"
    aria-label="Type your message"
    placeholder="Type your message..."
    rows="1">
  </textarea>

  <!-- Send button (right) -->
  <button id="sendBtn" disabled type="button" aria-label="Send message">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
      stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
      width="16" height="16" viewBox="0 0 24 24">
      <line x1="22" y1="2" x2="11" y2="13" />
      <polygon points="22 2 15 22 11 13 2 9 22 2" />
    </svg>
  </button>

</div>
    </footer>
  </div>

  <input
  type="file"
  id="hiddenFileInput"
  style="display:none"
/>
  <script>
    let attachedFile = null;

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
    const messagesEl = document.getElementById('messages');
    const inputEl = document.getElementById('input');
    const sendBtn = document.getElementById('sendBtn');
    const attachBtn = document.getElementById('attachBtn');

    // Enable send button only when there's input
    inputEl.addEventListener('input', () => {
      const hasText = inputEl.value.trim().length > 0;
      sendBtn.disabled = !hasText;
      sendBtn.setAttribute('aria-disabled', !hasText);
      autoResizeTextarea(inputEl);
    });

    // Auto resize textarea
    function autoResizeTextarea(el) {
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    }
    autoResizeTextarea(inputEl);

    // Add message to chat
    function addMessage(text, sender = 'bot') {
      const msgDiv = document.createElement('div');
      msgDiv.className = 'message ' + (sender === 'user' ? 'user-message' : 'bot-message');
      msgDiv.textContent = text;
      messagesEl.appendChild(msgDiv);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Dummy bot reply with delay
    async function botReply(userText) {
      return new Promise(resolve => {
        setTimeout(() => {
          resolve("This is a dummy response to: " + userText);
        }, 1000);
      });
    }

    function cleanMarkdown(text) {
  if (!text) return '';

  return text
    // remove headings ### ##
    .replace(/^#{1,6}\s*/gm, '')
    // remove bold/italic ** __ * _
    .replace(/\*\*(.*?)\*\*/g, '$1')
    .replace(/\*(.*?)\*/g, '$1')
    .replace(/__(.*?)__/g, '$1')
    .replace(/_(.*?)_/g, '$1')
    // remove bullet dashes
    .replace(/^\s*-\s+/gm, '')
    // remove extra blank lines
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

let waitingMessageEl = null;

function showWaitingMessage() {
  waitingMessageEl = document.createElement('div');
  waitingMessageEl.className = 'message bot-message';
  waitingMessageEl.textContent = '🤖 AI is thinking… please wait';
  messagesEl.appendChild(waitingMessageEl);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function removeWaitingMessage() {
  if (waitingMessageEl) {
    waitingMessageEl.remove();
    waitingMessageEl = null;
  }
}

function disableChatInput() {
  inputEl.disabled = true;
  sendBtn.disabled = true;
  sendBtn.setAttribute('aria-disabled', 'true');
}

function enableChatInput() {
  inputEl.disabled = false;
  inputEl.focus();
}

    // Send message handler
    async function sendMessage() {
  const msg = inputEl.value.trim();
  if (!msg) return;

  // 1️⃣ Show user message
  addMessage(msg, 'user');
  inputEl.value = '';
  autoResizeTextarea(inputEl);

  // 2️⃣ Disable input + show waiting
  disableChatInput();
  showWaitingMessage();

  try {
    const fd = new FormData();
fd.append('message', msg);

if (attachedFile) {
  fd.append('file', attachedFile);
}

    const res = await fetch('chatbot_api.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const data = await res.json();

    // 3️⃣ Remove waiting message
    removeWaitingMessage();

    // 4️⃣ Clean markdown & show AI reply
    const cleanReply = cleanMarkdown(data.reply || 'No response');
    addMessage(cleanReply, 'bot');

  } catch (e) {
    removeWaitingMessage();
    addMessage('⚠️ Error contacting AI. Please try again.', 'bot');
  } finally {
    // 5️⃣ Re-enable input
    enableChatInput();
  }

  attachedFile = null;

}

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
  const hiddenFileInput = document.getElementById('hiddenFileInput');

  attachBtn.addEventListener('click', () => {
    hiddenFileInput.click();
    setTimeout(() => inputEl.focus(), 0);
  });

  hiddenFileInput.addEventListener('change', () => {
    const file = hiddenFileInput.files[0];
    if (!file) return;

    attachedFile = file; // ✅ store file

    addMessage(`📎 Attached: ${file.name}`, 'user');

    inputEl.focus();
    hiddenFileInput.value = '';
  });
});

    inputEl.focus();

  </script>
</body>
</html>
