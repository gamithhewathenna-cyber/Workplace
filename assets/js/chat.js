/* assets/js/chat.js — Internal team chat widget (direct messages, multiple floating windows) */
(function () {
  var bubble    = document.getElementById('chat-bubble');
  var badge     = document.getElementById('chat-badge');
  var panel     = document.getElementById('chat-panel');
  var closeBtn  = document.getElementById('chat-close');
  var listEl    = document.getElementById('chat-contacts-list');
  var winsBox   = document.getElementById('chat-windows-container');

  if (!bubble) return;

  var contacts = [];
  var lastTotalUnread = 0;
  var contactsLoadedOnce = false;
  var audioCtx = null;
  var openWindows = []; // { id, name, el, lastMsgId, pollTimer }
  var MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2MB — must match CHAT_IMAGE_MAX_BYTES in includes/config.php

  function maxWindows() {
    return window.innerWidth < 480 ? 1 : 3;
  }

  function playNotifySound() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var now = audioCtx.currentTime;
      [880, 1175].forEach(function (freq, i) {
        var o = audioCtx.createOscillator();
        var g = audioCtx.createGain();
        o.type = 'sine';
        o.frequency.value = freq;
        var start = now + i * 0.11;
        g.gain.setValueAtTime(0.0001, start);
        g.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, start + 0.3);
        o.connect(g); g.connect(audioCtx.destination);
        o.start(start);
        o.stop(start + 0.32);
      });
    } catch (e) { /* audio unavailable/blocked — ignore */ }
  }

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
        renderContacts();
        var total = contacts.reduce(function (sum, c) { return sum + parseInt(c.unread_count, 10); }, 0);
        updateBadge(total);
        if (contactsLoadedOnce && total > lastTotalUnread) playNotifySound();
        contactsLoadedOnce = true;
        lastTotalUnread = total;
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
      el.addEventListener('click', function () { openChatWindow(c.id, c.name); });
      listEl.appendChild(el);
    });
  }

  function findWindow(id) {
    for (var i = 0; i < openWindows.length; i++) {
      if (openWindows[i].id === id) return openWindows[i];
    }
    return null;
  }

  function appendMessage(msgsEl, m) {
    var mine = String(m.sender_id) === String(window.CHAT_MY_ID);
    var el = document.createElement('div');
    el.className = 'chat-msg ' + (mine ? 'chat-msg-mine' : 'chat-msg-theirs') + (m.attachment_path ? ' chat-msg-image' : '');
    var html = '';
    if (m.attachment_path) {
      var url = '/uploads/' + m.attachment_path;
      html += '<a href="' + escHtml(url) + '" target="_blank" rel="noopener"><img src="' + escHtml(url) + '" alt="Shared image" loading="lazy"></a>';
    }
    if (m.message) {
      html += '<div class="chat-msg-text">' + escHtml(m.message).replace(/\n/g, '<br>') + '</div>';
    } else if (!m.attachment_path) {
      html += '<div class="chat-msg-text" style="opacity:.6;font-style:italic">Photo (auto-deleted after 3 days)</div>';
    }
    html += '<span class="chat-msg-time">' + fmtTime(m.created_at) + '</span>';
    el.innerHTML = html;
    msgsEl.appendChild(el);
  }

  function openChatWindow(id, name) {
    var existing = findWindow(id);
    if (existing) {
      existing.el.querySelector('.chat-float-input').focus();
      return;
    }

    // Enforce the max concurrent windows by closing the oldest one first —
    // same "bump the oldest chat head" behavior people expect from Messenger.
    while (openWindows.length >= maxWindows()) {
      closeChatWindow(openWindows[0].id);
    }

    var el = document.createElement('div');
    el.className = 'chat-float-window';
    el.innerHTML =
      '<div class="chat-float-header">' +
        '<div class="chat-float-avatar">' + escHtml(name.charAt(0).toUpperCase()) + '</div>' +
        '<span class="chat-float-name">' + escHtml(name) + '</span>' +
        '<span class="chat-float-header-badge" style="display:none"></span>' +
        '<button type="button" class="chat-float-minimize" aria-label="Minimize"><i class="fa fa-minus"></i></button>' +
        '<button type="button" class="chat-float-close" aria-label="Close"><i class="fa fa-xmark"></i></button>' +
      '</div>' +
      '<div class="chat-thread-view">' +
        '<div class="chat-thread-messages"></div>' +
        '<form class="chat-send-form">' +
          '<button type="button" class="chat-attach-btn" aria-label="Attach image" title="Attach image (max 2MB)"><i class="fa fa-image"></i></button>' +
          '<input type="file" class="chat-file-input" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none">' +
          '<input type="text" class="chat-float-input" placeholder="Type a message…" autocomplete="off" maxlength="2000">' +
          '<button type="submit" aria-label="Send"><i class="fa fa-paper-plane"></i></button>' +
        '</form>' +
      '</div>';
    winsBox.appendChild(el);

    var win = { id: id, name: name, el: el, lastMsgId: 0, pollTimer: null, minimized: false, unreadWhileMinimized: 0 };
    openWindows.push(win);

    var headerEl    = el.querySelector('.chat-float-header');
    var minimizeBtn = el.querySelector('.chat-float-minimize');
    var headerBadge = el.querySelector('.chat-float-header-badge');

    function setMinimized(val) {
      win.minimized = val;
      el.classList.toggle('minimized', val);
      minimizeBtn.innerHTML = val ? '<i class="fa fa-chevron-up"></i>' : '<i class="fa fa-minus"></i>';
      if (!val) {
        win.unreadWhileMinimized = 0;
        headerBadge.style.display = 'none';
        var msgsEl = el.querySelector('.chat-thread-messages');
        msgsEl.scrollTop = msgsEl.scrollHeight;
        el.querySelector('.chat-float-input').focus();
      }
    }

    headerEl.addEventListener('click', function () { setMinimized(!win.minimized); });
    minimizeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      setMinimized(!win.minimized);
    });
    el.querySelector('.chat-float-close').addEventListener('click', function (e) {
      e.stopPropagation();
      closeChatWindow(id);
    });
    el.querySelector('.chat-send-form').addEventListener('submit', function (e) {
      e.preventDefault();
      sendMessage(win);
    });

    var fileInput = el.querySelector('.chat-file-input');
    el.querySelector('.chat-attach-btn').addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files[0];
      fileInput.value = '';
      if (file) uploadImage(win, file);
    });

    loadWindowThread(win, true);
    win.pollTimer = setInterval(function () { loadWindowThread(win, false); }, 4000);
    el.querySelector('.chat-float-input').focus();
  }

  function closeChatWindow(id) {
    var win = findWindow(id);
    if (!win) return;
    if (win.pollTimer) clearInterval(win.pollTimer);
    win.el.remove();
    openWindows = openWindows.filter(function (w) { return w.id !== id; });
  }

  function loadWindowThread(win, isInitial) {
    fetch('/api/chat_thread.php?with=' + win.id + '&since_id=' + win.lastMsgId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok || !data.messages.length) return;
        var msgsEl = win.el.querySelector('.chat-thread-messages');
        var gotIncoming = false;
        data.messages.forEach(function (m) {
          appendMessage(msgsEl, m);
          if (String(m.sender_id) !== String(window.CHAT_MY_ID)) gotIncoming = true;
        });
        win.lastMsgId = data.messages[data.messages.length - 1].id;
        if (win.minimized) {
          if (!isInitial && gotIncoming) {
            win.unreadWhileMinimized++;
            var headerBadge = win.el.querySelector('.chat-float-header-badge');
            headerBadge.textContent = win.unreadWhileMinimized;
            headerBadge.style.display = 'grid';
          }
        } else if (isInitial || gotIncoming) {
          msgsEl.scrollTop = msgsEl.scrollHeight;
        }
        if (!isInitial && gotIncoming) playNotifySound();
        if (gotIncoming) loadContacts(); // this contact's messages just got marked read server-side
      });
  }

  function sendMessage(win) {
    var input = win.el.querySelector('.chat-float-input');
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    fetch('/api/chat_send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recipient_id: win.id, message: msg })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          var msgsEl = win.el.querySelector('.chat-thread-messages');
          appendMessage(msgsEl, data.message);
          win.lastMsgId = data.message.id;
          msgsEl.scrollTop = msgsEl.scrollHeight;
        }
      });
  }

  function uploadImage(win, file) {
    if (!/^image\//.test(file.type)) {
      alert('Please choose an image file.');
      return;
    }
    if (file.size > MAX_IMAGE_BYTES) {
      alert('That image is larger than 2MB. Please choose a smaller one.');
      return;
    }
    var fd = new FormData();
    fd.append('recipient_id', win.id);
    fd.append('image', file);
    fetch('/api/chat_upload.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          var msgsEl = win.el.querySelector('.chat-thread-messages');
          appendMessage(msgsEl, data.message);
          win.lastMsgId = data.message.id;
          msgsEl.scrollTop = msgsEl.scrollHeight;
        } else {
          alert(data.error || 'Could not send image.');
        }
      })
      .catch(function () { alert('Could not send image.'); });
  }

  bubble.addEventListener('click', function () {
    var opening = panel.style.display === 'none';
    panel.style.display = opening ? 'flex' : 'none';
    document.body.classList.toggle('chat-panel-open', opening);
    if (opening) loadContacts();
  });
  closeBtn.addEventListener('click', function () {
    panel.style.display = 'none';
    document.body.classList.remove('chat-panel-open');
  });

  loadContacts();
  setInterval(loadContacts, 15000);
})();
