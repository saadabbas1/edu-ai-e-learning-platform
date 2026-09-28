<?php
require __DIR__ . '/auth_student.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
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
  
    .btn1 {
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
  
    .btn1:hover {
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
    
    .toggle-btn {
      margin-top: 8%;
      position: absolute;
      right: 40px;
      background: none;
      border: none;
      color: #007bff;
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
    }

    .toggle-btn:hover {
      color: #0056b3;
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
            <a href="admin_dashboard.php">Dashboard</a>
        </nav>
    </header>

    <section class="settings-section">
        <h2>Settings</h2>
        <div class="setting-row">

            <div class="setting-item" onclick="openModal('changePasswordModal')">
                <i class="fas fa-bell"></i>
                <h4>Change Password</h4>
            </div>
            
            <div class="setting-item" onclick="openModal('logoutModal')">
                <i class="fas fa-sign-out-alt"></i>
                <h4>Logout</h4>
            </div>
            
<div class="modal" id="changePasswordModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal('changePasswordModal')" role="button" aria-label="Close">&times;</span>
    <h2 id="changePasswordTitle">Change Password</h2>

    <form id="changePasswordForm" autocomplete="off">
      <div class="password-group">
        <input type="password" id="oldPassword" name="oldPassword" placeholder="Old Password" required />
        <button type="button" class="toggle-btn" onclick="togglePassword('oldPassword', this)">Show</button>
      </div>
      
      <div class="error-message" id="oldPasswordError"></div>

      <div class="password-group">
        <input type="password" id="newPassword" name="newPassword" placeholder="New Password" required />
        <button type="button" class="toggle-btn" onclick="togglePassword('newPassword', this)">Show</button>
      </div>
      <div class="info-msg" id="passwordStrength"></div>
      <div class="error-message" id="newPasswordError"></div>

      <div class="password-group">
        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required />
        <button type="button" class="toggle-btn" onclick="togglePassword('confirmPassword', this)">Show</button>
      </div>
      <div class="info-msg" id="passwordMatch"></div>
      <div class="error-message" id="confirmNewPasswordError"></div>

      <button class="btn1" type="submit" id="updatePasswordBtn">Update Password</button>
    </form>
  </div>
</div>
  
            <div id="logoutModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('logoutModal')">&times;</span>
                    <h3>Confirm Logout</h3>
                    <p>Are you sure you want to logout?</p>
                    <button class="btn1" onclick="document.location='logout.php'">Logout</button>
                </div>
            </div>

            <footer class="eduai-footer">
                <div class="footer-bottom">
                    <p>&copy; 2025 EDU AI – Settings</p>
                </div>
            </footer>

            <div id="successMessage" class="success-toast">Changes saved successfully!</div>

    <script>
/* ---------------- Modal helpers ---------------- */
function openModal(id) {
  document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}
window.onclick = function(event) {
  document.querySelectorAll('.modal').forEach(modal => {
    if (event.target === modal) modal.style.display = 'none';
  });
};

/* ---------------- Toast ---------------- */
function showSuccessMessage(message = "Changes saved successfully!") {
  const toast = document.getElementById("successMessage");
  toast.textContent = message;
  toast.classList.add("show");
  setTimeout(() => toast.classList.remove("show"), 3000);
}

/* ---------------- Toggle password ---------------- */
function togglePassword(fieldId, btn) {
  const input = document.getElementById(fieldId);
  if (!input) return;
  if (input.type === "password") {
    input.type = "text";
    btn.textContent = "Hide";
  } else {
    input.type = "password";
    btn.textContent = "Show";
  }
}

/* ---------------- Password strength & match UI ---------------- */
const newPasswordInput = document.getElementById('newPassword');
const confirmPasswordInput = document.getElementById('confirmPassword');
const passwordStrengthMsg = document.getElementById('passwordStrength');
const passwordMatchMsg = document.getElementById('passwordMatch');

newPasswordInput.addEventListener('input', () => {
  const val = newPasswordInput.value;

  if (!val.trim()) {
    passwordStrengthMsg.textContent = '';
    return;
  }

  if (val.length < 8) {
    passwordStrengthMsg.textContent = 'Weak (min 8 characters)';
    passwordStrengthMsg.style.color = '#d93025';
  } else if (!/[A-Z]/.test(val) || !/[0-9]/.test(val)) {
    passwordStrengthMsg.textContent = 'Moderate (use 1 uppercase and 1 number)';
    passwordStrengthMsg.style.color = '#ff9800';
  } else {
    passwordStrengthMsg.textContent = 'Strong';
    passwordStrengthMsg.style.color = '#28a745';
  }
});

confirmPasswordInput.addEventListener('input', () => {
  const confirmVal = confirmPasswordInput.value;
  const newVal = newPasswordInput.value;

  if (!confirmVal.trim()) {
    passwordMatchMsg.textContent = '';
    return;
  }

  if (confirmVal !== newVal) {
    passwordMatchMsg.textContent = 'Passwords do not match';
    passwordMatchMsg.style.color = '#d93025';
  } else {
    passwordMatchMsg.textContent = 'Passwords match';
    passwordMatchMsg.style.color = '#28a745';
  }
});

/* ---------------- Submit handler (FIXED) ---------------- */
const changePasswordForm = document.getElementById('changePasswordForm');
const oldPasswordInput = document.getElementById('oldPassword');

const oldPasswordError = document.getElementById('oldPasswordError');
const newPasswordError = document.getElementById('newPasswordError');
const confirmNewPasswordError = document.getElementById('confirmNewPasswordError');

const updateBtn = document.getElementById('updatePasswordBtn');

changePasswordForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  const oldPassword = oldPasswordInput.value.trim();
  const newPassword = newPasswordInput.value.trim();
  const confirmPassword = confirmPasswordInput.value.trim();

  oldPasswordError.textContent = '';
  newPasswordError.textContent = '';
  confirmNewPasswordError.textContent = '';

  let valid = true;

  if (!oldPassword) {
    oldPasswordError.textContent = 'Old password is required.';
    valid = false;
  }

  if (!newPassword) {
    newPasswordError.textContent = 'New password is required.';
    valid = false;
  }

  if (newPassword !== confirmPassword) {
    confirmNewPasswordError.textContent = 'Passwords do not match.';
    valid = false;
  }

  if (newPassword.length < 8 || !/[A-Z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
    newPasswordError.textContent = 'Password must be 8+ chars, include 1 uppercase letter and 1 number.';
    valid = false;
  }

  if (!valid) return;

  // Disable button while submitting (no UI design change)
  updateBtn.disabled = true;

  try {
    const res = await fetch('/edu_ai/api/change_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ oldPassword, newPassword, confirmPassword })
    });

    const text = await res.text();

    // If server returned HTML/PHP error, show it (so you see the real issue)
    let data = null;
    try { data = JSON.parse(text); } catch (err) {}

    if (!data) {
      newPasswordError.textContent = 'Server returned non-JSON. Open DevTools → Network → Response to see error.';
      console.error('RAW RESPONSE:', text);
      return;
    }

    if (!data.ok) {
      const msg = data.error || 'Failed';
      if (msg.toLowerCase().includes('old')) oldPasswordError.textContent = msg;
      else if (msg.toLowerCase().includes('match')) confirmNewPasswordError.textContent = msg;
      else newPasswordError.textContent = msg;
      return;
    }

    showSuccessMessage('Password updated successfully');
    closeModal('changePasswordModal');
    changePasswordForm.reset();
    passwordStrengthMsg.textContent = '';
    passwordMatchMsg.textContent = '';

  } catch (err) {
    newPasswordError.textContent = 'Server error. Please try again.';
  } finally {
    updateBtn.disabled = false;
  }
});
    </script>
</body>
</html>
