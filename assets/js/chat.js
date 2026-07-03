/* assets/js/chat.js — Internal team chat widget (direct messages) */
(function () {
  var bubble      = document.getElementById('chat-bubble');
  var badge       = document.getElementById('chat-badge');
  var panel       = document.getElementById('chat-panel');
  var backBtn     = document.getElementById('chat-back');
  var closeBtn    = document.getElementById('chat-close');
  var title       = document.getElementById('chat-panel-title');
  var listEl      = document.getElementById('chat-contacts-list');
  var threadView  = document.getElementById('chat-thread-view');
  var threadMsgs  = document.getElementById('chat-thread-messages');
  var sendForm    = document.getElementById('chat-send-form');
  var input       = document.getElementById('chat-input');

  if (!bubble) return;

  var activeContactId = null;
  var activeContactName = '';
  var lastMsgId = 0;
  var threadPollTimer = null;
  var contacts = [];

  function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmtTime(sqlDateTime) {
    var d = new Date(sqlDateTime.replace(' ', 'T'));
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function fmtListTime(sqlDateTime) {
    var d = new Date(sqlDateTime.replace(' ', 'T'));
    var now = new Date();
    if (d.toDateString() === now.toDateString()) {
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
  }

  function updateBadge(totalUnread) {
    if (totalUnread > 0) {
      badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
      badge.style.display = 'grid';
    } else {
      badge.style.display = 'none';
    }
  }

  function loadContacts() {
    fetch('/api/chat_contacts.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        contacts = data.contacts;
        if (!activeContactId) renderContacts();
        var total = contacts.reduce(function (sum, c) { return sum + parseInt(c.unread_count, 10); }, 0);
        updateBadge(total);
      });
  }

  function renderContacts() {
    listEl.innerHTML = '';
    if (!contacts.length) {
      listEl.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--clr-muted);font-size:.83rem">No teammates yet.</div>';
      return;
    }
    contacts.forEach(function (c) {
      var unread = parseInt(c.unread_count, 10);
      var el = document.createElement('div');
      el.className = 'chat-contact-item';
      el.innerHTML =
        '<div class="chat-contact-avatar">' + escHtml(c.name.charAt(0).toUpperCase()) + '</div>' +
        '<div class="chat-contact-body">' +
          '<div class="chat-contact-name">' + escHtml(c.name) + '</div>' +
          '<div class="chat-contact-preview">' + (c.last_message ? escHtml(c.last_message) : 'Say hello 👋') + '</div>' +
        '</div>' +
        '<div class="chat-contact-meta">' +
          (c.last_message_at ? '<span class="chat-contact-time">' + fmtListTime(c.last_message_at) + '</span>' : '') +
          (unread > 0 ? '<span class="chat-unread-dot">' + unread + '</span>' : '') +
        '</div>';
      el.addEventListener('click', function () { openThread(c.id, c.name); });
      listEl.appendChild(el);
    });
  }

  function openThread(id, name) {
    activeContactId = id;
    activeContactName = name;
    lastMsgId = 0;
    title.textContent = name;
    backBtn.style.display = 'grid';
    listEl.style.display = 'none';
    threadView.style.display = 'flex';
    threadMsgs.innerHTML = '';
    loadThread(true);
    if (threadPollTimer) clearInterval(threadPollTimer);
    threadPollTimer = setInterval(function () { loadThread(false); }, 4000);
    input.focus();
  }

  function closeThread() {
    activeContactId = null;
    if (threadPollTimer) { clearInterval(threadPollTimer); threadPollTimer = null; }
    title.textContent = 'Team Chat';
    backBtn.style.display = 'none';
    listEl.style.display = 'block';
    threadView.style.display = 'none';
    loadContacts();
  }

  function loadThread(scrollDown) {
    if (!activeContactId) return;
    fetch('/api/chat_thread.php?with=' + activeContactId + '&since_id=' + lastMsgId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok || !data.messages.length) return;
        data.messages.forEach(function (m) { appendMessage(m); });
        lastMsgId = data.messages[data.messages.length - 1].id;
        if (scrollDown !== false) threadMsgs.scrollTop = threadMsgs.scrollHeight;
      });
  }

  function appendMessage(m) {
    var mine = String(m.sender_id) === String(window.CHAT_MY_ID);
    var el = document.createElement('div');
    el.className = 'chat-msg ' + (mine ? 'chat-msg-mine' : 'chat-msg-theirs');
    el.innerHTML = escHtml(m.message).replace(/\n/g, '<br>') + '<span class="chat-msg-time">' + fmtTime(m.created_at) + '</span>';
    threadMsgs.appendChild(el);
  }

  bubble.addEventListener('click', function () {
    var opening = panel.style.display === 'none';
    panel.style.display = opening ? 'flex' : 'none';
    if (opening) loadContacts();
  });
  closeBtn.addEventListener('click', function () { panel.style.display = 'none'; });
  backBtn.addEventListener('click', closeThread);

  sendForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = input.value.trim();
    if (!msg || !activeContactId) return;
    input.value = '';
    fetch('/api/chat_send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recipient_id: activeContactId, message: msg })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          appendMessage(data.message);
          lastMsgId = data.message.id;
          threadMsgs.scrollTop = threadMsgs.scrollHeight;
        }
      });
  });

  loadContacts();
  setInterval(loadContacts, 15000);
})();
