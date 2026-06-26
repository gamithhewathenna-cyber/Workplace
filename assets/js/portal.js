/* portal.js — Employee Portal Enhancement */

// ── Modal helpers ──────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'grid'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
// Close on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => { m.style.display = 'none'; });
});

// ── Auto-dismiss alerts ────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.opacity = '0'; a.style.transition = '.5s'; setTimeout(() => a.remove(), 500); }, 4000);
  });

  // Animate performance rings on load
  document.querySelectorAll('.perf-ring').forEach(ring => {
    const fill = ring.querySelector('.ring-fill');
    if (!fill) return;
    const pct = ring.dataset.pct || 0;
    fill.style.strokeDasharray = `${pct} 100`;
  });
});

// ── Notification dropdown ──────────────────────────────────
const notifBtn = document.querySelector('.nav-notif-btn');
const notifPanel = document.getElementById('notif-panel');
if (notifBtn && notifPanel) {
  notifBtn.addEventListener('click', e => {
    e.stopPropagation();
    notifPanel.classList.toggle('open');
    // Mark as read
    fetch('/api/mark_read.php', { method: 'POST' });
    const dot = notifBtn.querySelector('.nav-notif-dot');
    if (dot) dot.style.display = 'none';
  });
  document.addEventListener('click', () => notifPanel.classList.remove('open'));
}

// ── Time Tracker ───────────────────────────────────────────
let timerInterval = null;
let timerStart    = null;
let timerSeconds  = 0;
let timerStatus   = 'idle'; // idle | running | paused

function startTimer(trackId) {
  if (timerStatus === 'running') return;
  timerStatus = 'running';
  timerStart  = Date.now() - timerSeconds * 1000;
  timerInterval = setInterval(updateTimerDisplay, 1000);
  document.querySelectorAll('.timer-status').forEach(el => el.textContent = 'Running');
  apiTimer('start', trackId);
}
function pauseTimer(trackId) {
  if (timerStatus !== 'running') return;
  timerStatus = 'paused';
  clearInterval(timerInterval);
  document.querySelectorAll('.timer-status').forEach(el => el.textContent = 'Paused');
  apiTimer('pause', trackId);
}
function resumeTimer(trackId) {
  if (timerStatus !== 'paused') return;
  startTimer(trackId);
  apiTimer('resume', trackId);
}
function finishTimer(trackId) {
  clearInterval(timerInterval);
  timerStatus = 'idle';
  document.querySelectorAll('.timer-status').forEach(el => el.textContent = 'Finished');
  apiTimer('finish', trackId).then(() => { if (timerSeconds > 0) location.reload(); });
}

function updateTimerDisplay() {
  timerSeconds = Math.floor((Date.now() - timerStart) / 1000);
  const h = String(Math.floor(timerSeconds / 3600)).padStart(2,'0');
  const m = String(Math.floor((timerSeconds % 3600) / 60)).padStart(2,'0');
  const s = String(timerSeconds % 60).padStart(2,'0');
  document.querySelectorAll('.timer-display').forEach(el => el.textContent = `${h}:${m}:${s}`);
}

async function apiTimer(action, trackId) {
  return fetch('/api/time_track.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, track_id: trackId })
  }).then(r => r.json());
}

// ── Confirm delete ─────────────────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-confirm]');
  if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
});

// ── Auto-submit select filters ─────────────────────────────
document.querySelectorAll('select[data-autosubmit]').forEach(sel => {
  sel.addEventListener('change', () => sel.closest('form').submit());
});

// ── Sidebar mobile toggle ──────────────────────────────────
const menuToggle = document.getElementById('menu-toggle');
const sidebar    = document.querySelector('.portal-sidebar');
if (menuToggle && sidebar) {
  menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}

// ── Export helpers ─────────────────────────────────────────
function exportTable(tableId, filename) {
  const rows = [];
  const table = document.getElementById(tableId);
  if (!table) return;
  table.querySelectorAll('tr').forEach(tr => {
    const cells = [...tr.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g,'""') + '"');
    rows.push(cells.join(','));
  });
  const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename + '.csv';
  a.click();
}
