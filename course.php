<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edu AI – Course</title>
  <link rel="stylesheet" href="course.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body data-theme="<?= htmlspecialchars($theme ?? 'light') ?>">
  <header class="navbar">
    <div class="navbar-left">
        <img src="images/Book Logo.png" alt="Edu AI Logo" class="logo">
        <span class="brand-name">Edu AI – Student</span>
    </div>
    <nav class="nav-links">
        <a href="student.php">Dashboard</a>
    </nav>
  </header>

  <main class="course-container" role="main">
    <aside class="sidebar" role="navigation" aria-label="Course Lessons">
      <h3>Lessons</h3>
      <!-- Lesson buttons dynamically inserted here -->
    </aside>

    <section class="content-area" tabindex="0" aria-live="polite" aria-atomic="true">
      <button class="bookmark-btn" id="bookmarkBtn" aria-label="Bookmark this lesson" title="Bookmark this lesson">
        <i class="fa-regular fa-bookmark bookmark-lesson"></i>
      </button>

      <h2 class="lesson-title"></h2>

      <article class="lesson-content" tabindex="0"></article>
      <button id="startQuizBtn" aria-haspopup="dialog" aria-controls="quizModal">Start Quiz</button>
      <div class="progress-bar" aria-label="Course progress">
        <div class="progress-bar-fill" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
      </div>
    </section>
  </main>

  <footer class="eduai-footer" role="contentinfo">
    Edu AI - Student Portal
  </footer>

  <!-- Quiz Modal -->
  <div id="quizModal" class="modal" style="display:none;">
    <div class="modal-content feature-box" style="max-width:900px;margin:auto;">
      <span id="quizClose" class="close" style="float:right;cursor:pointer;">&times;</span>
      <h3 style="margin:0 0 .75rem;">Lesson Quiz</h3>
      <div id="quizContent"><p>Loading…</p></div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:10px;">
        <button type="button" class="cta-btn" id="quizSubmitBtn">Submit</button>
      </div>
    </div>
  </div>

<!-- QUIZ RESULT PANEL — fixed, centered, scrollable -->
<div id="quizResultPanel" class="popup centered" aria-hidden="true" style="display:none;">
  <div id="quizResultContent" class="popup-content">
    <button type="button" id="quizResultCloseBtn" class="close-btn" aria-label="Close">&times;</button>
    <!-- your JS injects result HTML here -->
  </div>
</div>


  <script>
  window.addEventListener("pageshow", (e) => { if (e.persisted) location.reload(); });
  </script>

  <!-- ===== LESSONS & COURSE INFO (UI unchanged) ===== -->
  <script>
    const sidebar      = document.querySelector('.sidebar');
    const h3           = sidebar ? sidebar.querySelector('h3') : null;
    const contentTitle = document.querySelector('.lesson-title');
    const contentArea  = document.querySelector('.lesson-content');

    // ===== BOOKMARKS (localStorage) =====
function getBookmarks() {
  try { return JSON.parse(localStorage.getItem('lessonBookmarks')) || []; }
  catch { return []; }
}
function saveBookmarks(list) {
  localStorage.setItem('lessonBookmarks', JSON.stringify(list));
}

// Build the current lesson context (what we store)
function currentLessonCtx() {
  const course_id    = window.currentCourseId || getCourseId();
  const lesson_idx   = (typeof window.activeIndex === 'number') ? window.activeIndex : 0;
  const lesson       = (Array.isArray(dbLessons) && dbLessons[lesson_idx]) || {};
  const course_title = window.currentCourseTitle || 'Course';
  const lesson_title = lesson.title || `Lesson ${lesson_idx + 1}`;
  return { course_id, course_title, lesson_idx, lesson_title };
}

// Check if the current lesson is already bookmarked
function isCurrentBookmarked() {
  const ctx = currentLessonCtx();
  return getBookmarks().some(b =>
    String(b.course_id) === String(ctx.course_id) &&
    Number(b.lesson_idx) === Number(ctx.lesson_idx)
  );
}

// Update the icon + ARIA state
function renderBookmarkBtn() {
  const btn = document.getElementById('bookmarkBtn');
  if (!btn) return;
  const icon = btn.querySelector('i');

  const on = isCurrentBookmarked();
  btn.classList.toggle('is-on', on);
  btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  btn.setAttribute('aria-label', on ? 'Remove from bookmarks' : 'Bookmark this lesson');
  btn.title = on ? 'Remove bookmark' : 'Bookmark this lesson';

  if (icon) {
    icon.classList.toggle('fa-solid', on);
    icon.classList.toggle('fa-regular', !on);
  }
}

// Toggle add/remove for the current lesson
function toggleBookmark() {
  const ctx = currentLessonCtx();
  const list = getBookmarks();
  const i = list.findIndex(b =>
    String(b.course_id) === String(ctx.course_id) &&
    Number(b.lesson_idx) === Number(ctx.lesson_idx)
  );
  if (i >= 0) list.splice(i, 1);
  else list.unshift(ctx);

  saveBookmarks(list);
  renderBookmarkBtn();

  // Nudge other tabs/pages (bookmark.html listens to storage)
  try { localStorage.setItem('lessonBookmarks_updatedAt', String(Date.now())); } catch {}
}

// Attach click handler (if the button exists)
document.getElementById('bookmarkBtn')?.addEventListener('click', toggleBookmark);

    let dbLessons = [];
    function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[c])); }

    async function loadDBLessons() {
      const id = getCourseId();
      if (!id) return;
      try {
        const res = await fetch('/edu_ai/lessons_by_course.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
        const data = await res.json();

        // Course meta for saving to DB
        window.currentCourseTitle = data.course?.title || 'Course';
        window.currentCourseLevel = data.course?.level || data.course?.difficulty || data.course?.grade || 'Beginner';
        window.currentCourseId    = id;

        if (contentTitle && data.course?.title) contentTitle.textContent = data.course.title;
        if (!data.ok) throw new Error(data.error || 'Failed to load lessons.');

        dbLessons = data.lessons || [];
        window.lessons = dbLessons;

        renderDBLessons();
        if (dbLessons.length) openDBLesson(0);
        if (dbLessons.length) {
  const initialIdx = getLessonIndex();
  openDBLesson(initialIdx);
}

        else if (contentArea) {
          if (contentTitle) contentTitle.textContent = window.currentCourseTitle;
          contentArea.innerHTML = '<p style="opacity:.7">No lessons uploaded yet.</p>';
        }
      } catch (e) { console.error(e); }
    }
    document.addEventListener('DOMContentLoaded', loadDBLessons);

    function renderDBLessons() {
      if (!sidebar || !h3) return;
      sidebar.querySelectorAll('.lesson[data-db="1"]').forEach(el => el.remove());
      let anchor = h3;
      dbLessons.forEach((lsn, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lesson';
        btn.setAttribute('data-db', '1');
        btn.innerHTML = `<i class="fas fa-file-alt"></i><span>${esc(lsn.title || ('Lesson ' + (idx+1)))}</span>`;
        btn.addEventListener('click', () => openDBLesson(idx));
        anchor.insertAdjacentElement('afterend', btn);
        anchor = btn;
      });
    }

    function openDBLesson(idx) {
      window.activeIndex = idx;
      const lsn = dbLessons[idx];
      if (!lsn || !contentArea) return;
      if (contentTitle) contentTitle.textContent = lsn.title || 'Lesson';
      const pdfPath = '/edu_ai/' + (lsn.file_path || '');
      contentArea.innerHTML = `
        ${lsn.description ? `<p>${esc(lsn.description)}</p>` : ''}
        <iframe src="${pdfPath}" width="100%" height="600" style="border:none;"></iframe>
      `;
      renderBookmarkBtn();
      const all = sidebar.querySelectorAll('.lesson[data-db="1"]');
      all.forEach((b, i) => b.classList.toggle('active', i === idx));
    }

    function getCourseId() {
      const p = new URLSearchParams(location.search);
      let id = parseInt(p.get('id') || '0', 10);
      if (!id) { try { const s = sessionStorage.getItem('selectedCourseId'); if (s) id = parseInt(s, 10); } catch {} }
      return id;
    }
    // Get lesson index from ?lesson= or sessionStorage
function getLessonIndex() {
  let idx = 0;
  try {
    const p = new URLSearchParams(location.search);
    if (p.has('lesson')) idx = parseInt(p.get('lesson'), 10) || 0;
    else {
      const s = sessionStorage.getItem('selectedLessonIndex');
      if (s) idx = parseInt(s, 10) || 0;
    }
  } catch {}
  return Math.max(0, idx);
}

  </script>

  <!-- ===== UNIVERSAL SAVE HELPER (now posts mcqs/shorts/long) ===== -->
  <script>
  async function saveQuizHistory(ctx, data, quiz) {
    // Build arrays to match save_quiz_history.php expectations
    const mcqsArr = (data?.scores?.mcq?.breakdown || []).map((m, i) => {
      const opts = Array.isArray(m.options) ? m.options : (quiz?.mcqs?.[i]?.options || []);
      const chosenText  = (typeof m.chosen === 'number' && opts[m.chosen] != null) ? opts[m.chosen] : '';
      const correctText = (typeof m.correct === 'number' && opts[m.correct] != null) ? opts[m.correct] : (quiz?.mcqs?.[i]?.options?.[quiz?.mcqs?.[i]?.correct_index] ?? '');
      return {
        question: m.question || quiz?.mcqs?.[i]?.question || '',
        answer: chosenText || 'No answer',
        correct_answer: correctText || 'N/A',
        points: m.points ?? 0,
        max_points: m.max ?? 1,
        options: opts
      };
    });

    const shortsArr = (data?.scores?.short?.breakdown || []).map((s, i) => ({
      question: s.question || quiz?.shorts?.[i]?.question || '',
      answer: s.user || '',
      correct_answer: s.gold || quiz?.shorts?.[i]?.answer || '',
      points: s.points ?? 0,
      max_points: s.max ?? 2
    }));

    const longArr = (data?.scores?.long?.detail ? [{
      question: data.scores.long.detail.question || quiz?.long?.question || '',
      answer: data.scores.long.detail.user || '',
      correct_answer: data.scores.long.detail.gold || quiz?.long?.answer || '',
      points: data.scores.long.detail.points ?? 0,
      max_points: data.scores.long.detail.max ?? 4
    }] : []);

    const payload = {
      course_id: ctx.course_id,
      course_level: ctx.course_level,
      lesson_title: ctx.lesson_title,
      score: data?.scores?.total?.score ?? 0,
      max_score: data?.scores?.total?.max ?? 0,
      mcqs: mcqsArr,
      shorts: shortsArr,
      long: longArr
    };

    const endpoints = [
      '/edu_ai/save_quiz_history.php',
      'save_quiz_history.php',
      './save_quiz_history.php',
      '/save_quiz_history.php',
      '/edu_ai/api/save_quiz_history.php',
      '/api/save_quiz_history.php'
    ];

    // 1) JSON path
    for (const url of endpoints) {
      try {
        const r = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });
        const txt = await r.text();
        let out; try { out = JSON.parse(txt); } catch { out = { ok:false, error:'Invalid JSON', raw:txt }; }
        if (out?.ok || out?.success || out?.attempt_id || out?.id) {
          return { ok: true, ...out };
        }
      } catch (e) { /* try next */ }
    }

    // 2) Classic POST (for $_POST servers)
    const fd = new FormData();
    fd.append('course_id', payload.course_id);
    fd.append('course_level', payload.course_level);
    fd.append('lesson_title', payload.lesson_title);
    fd.append('score', String(payload.score));
    fd.append('max_score', String(payload.max_score));
    fd.append('mcqs', JSON.stringify(payload.mcqs));
    fd.append('shorts', JSON.stringify(payload.shorts));
    fd.append('long', JSON.stringify(payload.long));

    for (const url of endpoints) {
      try {
        const r = await fetch(url, { method: 'POST', credentials: 'same-origin', body: fd });
        const txt = await r.text();
        let out; try { out = JSON.parse(txt); } catch { out = null; }
        if (out?.ok || out?.success || out?.attempt_id || out?.id) {
          return out.ok ? out : { ok: true, ...out };
        }
        if (r.ok && /success|saved|insert/i.test(txt)) {
          return { ok: true, raw: txt };
        }
      } catch (e) { /* try next */ }
    }

    return { ok:false, error:'No save endpoint accepted the data' };
  }
  </script>

  <!-- ===== AI QUIZ (generate, submit -> result -> SAVE TO DB) ===== -->
  <script>
  (function(){
    const startBtn   = document.getElementById('startQuizBtn');
    const quizModal  = document.getElementById('quizModal');
    const quizClose  = document.getElementById('quizClose');
    const content    = document.getElementById('quizContent');

    const resultPanel = document.getElementById('quizResultPanel');
    const resultBox   = document.getElementById('quizResultContent');
    const resultClose = document.getElementById('resultClose');

    function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}

    function openResultPanel(html){
      if (resultBox) resultBox.innerHTML = html || '<p>Loading…</p>';
      resultPanel.style.display = 'flex';
      resultPanel.style.alignItems = 'center';
      resultPanel.style.justifyContent = 'center';
    }
    function closeResultPanel(){ resultPanel.style.display = 'none'; }
    resultClose?.addEventListener('click', closeResultPanel);
    resultPanel.addEventListener('click', (e)=>{ if (e.target === resultPanel) closeResultPanel(); });

    function currentContext() {
      const idx = window.activeIndex ?? -1;
      const lsn = Array.isArray(window.lessons) && idx >= 0 ? window.lessons[idx] : {};
      const file = lsn?.file_path ? ('/edu_ai/' + lsn.file_path) : '';
      return {
        course_id: String(window.currentCourseId || ''),
        course_level: String(window.currentCourseLevel || 'Beginner'),
        lesson_title: lsn?.title || document.querySelector('.lesson-title')?.textContent?.trim() || '',
        lesson_desc:  lsn?.description || '',
        lesson_file:  file
      };
    }

    async function openAIGeneratedQuiz() {
      quizModal.style.display = 'block';
      content.innerHTML = '<p>Generating quiz… please wait.</p>';

      const ctx = currentContext();
      try {
        const fd = new FormData();
        fd.append('course_id', ctx.course_id);
        fd.append('lesson_title', ctx.lesson_title);
        fd.append('lesson_desc', ctx.lesson_desc);
        fd.append('lesson_file', ctx.lesson_file);

        const res = await fetch('/edu_ai/generate_quiz.php', { method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store' });
        const raw = await res.text();
        let data; try { data = JSON.parse(raw); } catch { throw new Error((raw && raw.trim()) || 'Empty/invalid response from server'); }
        if (!data.ok) {
          const details = [data.error, data.detail, data.http && `HTTP ${data.http}`].filter(Boolean).join(' — ');
          throw new Error(details || 'Failed to generate quiz');
        }
        renderQuiz(data.quiz);
      } catch (e) {
        content.innerHTML = `<p style="color:#b00;">${e.message || 'Error generating quiz'}</p>`;
      }
    }

    function renderQuiz(quiz) {
  let html = '<form id="quizForm" class="quiz-form">';

  quiz.mcqs.forEach((q,i)=>{
    html += `<div class="feature-box">
      <p><strong>Q${i+1}.</strong> ${esc(q.question)}</p>`;
    q.options.forEach((opt,oi)=>{
      html += `<label>
        <input type="radio" name="mcq_${i}" value="${oi}"> ${esc(opt)}
      </label>`;
    });
    html += `<input type="hidden" name="mcq_ans_${i}" value="${q.correct_index}">
    </div>`;
  });

  quiz.shorts.forEach((q,i)=>{
    html += `<div class="feature-box">
      <p><strong>Short ${i+1}.</strong> ${esc(q.question)}</p>
      <textarea name="short_${i}" rows="2"></textarea>
      <input type="hidden" name="short_ans_${i}" value="${esc(q.answer)}">
    </div>`;
  });

  quiz.longs.forEach((q,i)=>{
    html += `<div class="feature-box">
      <p><strong>Long ${i+1}.</strong> ${esc(q.question)}</p>
      <textarea name="long_${i}" rows="5"></textarea>
      <input type="hidden" name="long_ans_${i}" value="${esc(q.answer)}">
    </div>`;
  });

  content.innerHTML = html + '</form>';
}

    async function gradeQuiz(){
      const form = document.getElementById('quizForm');
      if (!form) return;

      const quiz = { mcqs: [], shorts: [], long: null };
      const mcqAnsFields = [...form.querySelectorAll('input[type="hidden"][name^="mcq_ans_"]')];
      mcqAnsFields.forEach((ansField, i) => {
        const qBlock = form.querySelectorAll('.feature-box')[i];
        const qText = qBlock ? (qBlock.querySelector('p')?.textContent || '').replace(/^Q\d+\.\s*/,'') : '';
        const options = [...form.querySelectorAll(`input[name="mcq_${i}"]`)].map(inp=>{
          const lab = inp.closest('label'); return lab ? lab.textContent.trim() : '';
        });
        const correctIndex = parseInt(ansField.value||'0',10);
        quiz.mcqs.push({question: qText, options, correct_index: correctIndex});
      });

      const shortHidden = [...form.querySelectorAll('input[name^="short_ans_"]')];
      shortHidden.forEach((f,i)=>{
        const idx = mcqAnsFields.length + i;
        const qBlock = form.querySelectorAll('.feature-box')[idx];
        const qText = qBlock ? (qBlock.querySelector('p')?.textContent || '').replace(/^Short\s+\d+\.\s*/,'') : '';
        const gold  = f.value || '';
        quiz.shorts.push({question: qText, answer: gold});
      });

      const longHidden = form.querySelector('input[name="long_ans_0"]');
      if (longHidden) {
        const blocks = form.querySelectorAll('.feature-box');
        const longBlock = blocks[blocks.length - 1];
        const longLabel = longBlock ? longBlock.querySelector('p')?.textContent || '' : '';
        const qText = longLabel.replace(/^Long\.\s*/,'').trim();
        const gold  = longHidden.value || '';
        quiz.long = {question: qText, answer: gold};
      }

      const answers = {
        mcqs: quiz.mcqs.map((_,i)=>{
          const chosen = form.querySelector(`input[name="mcq_${i}"]:checked`);
          return chosen ? parseInt(chosen.value,10) : -1;
        }),
        shorts: shortHidden.map((_,i)=> form.querySelector(`textarea[name="short_${i}"]`)?.value || ''),
        long:   form.querySelector('textarea[name="long_0"]')?.value || ''
      };

      window._submittedQuiz = quiz;

      // Close quiz modal
      document.getElementById('quizModal').style.display = 'none';

      // Show result panel immediately
      const resultPanel = document.getElementById('quizResultPanel');
      const resultBox   = document.getElementById('quizResultContent');
      resultPanel.style.display = 'flex';
      resultPanel.style.alignItems = 'center';
      resultPanel.style.justifyContent = 'center';
      resultBox.innerHTML = `
        <div class="feature-box">
          <p><strong>Quiz is under checking… please wait.</strong></p>
          <p style="opacity:.7">We are grading MCQs locally and asking the teacher AI to score short & long answers.</p>
        </div>
      `;

      const ctx = (function(){
        const idx = window.activeIndex ?? -1;
        const lsn = Array.isArray(window.lessons) && idx >= 0 ? window.lessons[idx] : {};
        return {
          course_id: String(window.currentCourseId || ''),
          course_level: String(window.currentCourseLevel || 'Beginner'),
          lesson_title: lsn?.title || document.querySelector('.lesson-title')?.textContent?.trim() || '',
        };
      })();

      try {
        // 1) Grade (server) — now robust to different install paths
        const gradeEndpoints = [
          '/edu_ai/grade_quiz.php',
          'grade_quiz.php',
          './grade_quiz.php',
          '/grade_quiz.php',
          '/edu_ai/api/grade_quiz.php',
          '/api/grade_quiz.php'
        ];
        let data = null;
        const body = JSON.stringify({quiz, answers, lesson_title: ctx.lesson_title});
        for (const url of gradeEndpoints) {
          try {
            const resp = await fetch(url, {
              method: 'POST',
              headers: {'Content-Type':'application/json'},
              body,
              credentials: 'same-origin',
              cache: 'no-store'
            });
            const raw = await resp.text();
            let parsed; try { parsed = JSON.parse(raw); } catch { parsed = null; }
            if (parsed?.ok) { data = parsed; break; }
            // allow fallback if HTTP OK but {ok:false}
          } catch (_) { /* try next endpoint */ }
        }
        if (!data) throw new Error('Grading failed (no reachable grade_quiz.php)');

        // 2) Save to DB
        try {
          const saveRes = await saveQuizHistory(ctx, data, quiz);
          if (!saveRes?.ok) console.warn('Save non-ok:', saveRes);
          else console.log('Quiz saved ✅', saveRes);
        } catch (e) {
          console.warn('Saving quiz history failed:', e);
        }

        // 3) Render result
        renderGradedResult(data);
      } catch (e) {
        resultBox.innerHTML = `
          <div class="feature-box">
            <p style="color:#b00;">${(e && e.message) ? e.message : 'Error grading quiz'}</p>
            <p style="opacity:.7">Please try again.</p>
          </div>`;
      }
    }

    function renderGradedResult(r){
      const esc = s => String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
      const mcqRows = (r.scores.mcq.breakdown||[]).map((m,i)=>{
        const opts = Array.isArray(m.options) ? m.options
                    : (window._submittedQuiz?.mcqs?.[i]?.options || []);
        const chosenText  = (typeof m.chosen==='number' && opts[m.chosen] != null) ? opts[m.chosen] : '-';
        const correctText = (typeof m.correct==='number' && opts[m.correct] != null) ? opts[m.correct] : '-';
        return `
        <tr>
          <td>${i+1}</td>
          <td>${esc(m.question||'')}</td>
          <td>${esc(chosenText)}</td>
          <td>${esc(correctText)}</td>
          <td>${m.points}/${m.max}</td>
        </tr>`;
      }).join('');

      const shortRows = (r.scores.short.breakdown||[]).map((s,i)=>`
        <tr>
          <td>${i+1}</td>
          <td>${esc(s.question||'')}</td>
          <td>${esc(s.user||'')}</td>
          <td>${esc(s.gold||'')}</td>
          <td>${s.points}/${s.max}</td>
          <td><small>${esc(s.rationale||'')}</small></td>
        </tr>
      `).join('');

      const longD = r.scores.long.detail || {};
      const total = r.scores.total || {score:0,max:0};

      document.getElementById('quizResultContent').innerHTML = `
        <div class="feature-box">
          <p><strong>Lesson:</strong> ${esc(r.lesson_title||'')}</p>
          <p><strong>Total Score:</strong> ${total.score} / ${total.max}</p>
        </div>
        <div class="feature-box">
          <h4>MCQs</h4>
          <table class="lesson-table" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>#</th><th>Question</th><th>Your Answer</th><th>Correct Answer</th><th>Points</th></tr></thead>
            <tbody>${mcqRows}</tbody>
          </table>
        </div>
        <div class="feature-box">
          <h4>Short Questions</h4>
          <table class="lesson-table" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>#</th><th>Question</th><th>Your Answer</th><th>Model Answer</th><th>Points</th><th>Why</th></tr></thead>
            <tbody>${shortRows}</tbody>
          </table>
        </div>
        <div class="feature-box">
          <h4>Long Question</h4>
          <p><strong>Question:</strong> ${esc(longD.question||'')}</p>
          <p><strong>Your Answer:</strong><br>${esc(longD.user||'')}</p>
          <p><strong>Model Answer:</strong><br>${esc(longD.gold||'')}</p>
          <p><strong>Points:</strong> ${longD.points||0} / ${longD.max||4}</p>
          <p><strong>Why:</strong> <small>${esc(longD.rationale||'')}</small></p>
        </div>
        <div class="feature-box">
          <h4>AI Feedback</h4>
          <p>${esc(r.feedback||'')}</p>
        </div>
      `;
      showDockedResults();
    }

    document.getElementById('startQuizBtn').addEventListener('click', openAIGeneratedQuiz);
    document.getElementById('quizClose')?.addEventListener('click', ()=> document.getElementById('quizModal').style.display='none');
    document.getElementById('quizModal').addEventListener('click', (e)=>{ if(e.target === document.getElementById('quizModal')) document.getElementById('quizModal').style.display='none'; });
    document.getElementById('quizSubmitBtn').addEventListener('click', gradeQuiz);
  })();
  </script>

<script>
// Helper to show the docked panel at a fixed place
function showDockedResults() {
  const panel = document.getElementById('quizResultPanel');
  if (!panel) return;
  panel.classList.add('docked', 'show');
  panel.style.display = 'block'; // ensure visible
  document.body.classList.add('has-docked-results'); // optional: push main content
}

// Call this right after you finish rendering results into #quizResultContent
// Example (where your code currently shows results):
// renderResults(...);
// showDockedResults();

document.getElementById('quizResultCloseBtn')?.addEventListener('click', () => {
  const panel = document.getElementById('quizResultPanel');
  if (panel) {
    panel.classList.remove('show');
    panel.style.display = 'none';
  }
  document.body.classList.remove('has-docked-results');
});

// (Optional) If you had "click outside to close", disable it for the docked panel:
document.getElementById('quizResultPanel')?.addEventListener('click', (e) => {
  // Do nothing; we don't auto-close when docked
  // (Keep this empty or remove any previous handler that closed on backdrop click)
});
</script>

<script>
function showDockedResults() {
  const panel = document.getElementById('quizResultPanel');
  if (!panel) return;
  panel.classList.add('centered', 'show');
  panel.style.display = 'flex';  // shows and centers it
}

document.getElementById('quizResultCloseBtn')?.addEventListener('click', () => {
  const panel = document.getElementById('quizResultPanel');
  if (panel) {
    panel.classList.remove('show');
    panel.style.display = 'none';
  }
});

// Optional: allow ESC key to close
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.getElementById('quizResultCloseBtn')?.click();
  }
});
</script>
</body>
</html>
