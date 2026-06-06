import './bootstrap.js';

// Echo sudah diinisialisasi di bootstrap.js, tinggal pakai window.Echo

// ─── State Aplikasi ─────────────────────────────────────────────────────────
const state = {
    currentChat: null,   // { type: 'private'|'group', id: number, name: string }
    activeChannels: [],  // channel yang sedang di-subscribe
    groupChannels: new Set(), // group channel yang sudah di-subscribe (untuk badge)
    typingTimer: null,
    isTyping: false,
};

// ─── Shortcut DOM ───────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const cfg = window.chatConfig;

// ─── Subscribe semua group channel saat halaman load (untuk badge notifikasi) ─
function subscribeGroupChannel(groupId) {
    // Cegah double subscribe
    if (state.groupChannels.has(groupId)) return;
    state.groupChannels.add(groupId);

    window.Echo.private(`group.${groupId}`)
        .listen('.message.sent', (data) => {
            if (data.sender_id === cfg.currentUserId) return;

            // Kalau group ini sedang aktif, tampilkan pesan langsung
            if (state.currentChat?.type === 'group' && state.currentChat?.id === groupId) {
                appendMessage(data);
                scrollToBottom();
            } else {
                // Tidak aktif — tambah badge notifikasi di sidebar
                const current = getBadgeCount('group', groupId);
                updateBadge('group', groupId, current + 1);
            }
        });
}

function subscribeAllGroupChannels() {
    document.querySelectorAll('.contact-item[data-type="group"]').forEach(item => {
        subscribeGroupChannel(parseInt(item.dataset.id));
    });
}

// ─── Status koneksi WebSocket ────────────────────────────────────────────────
window.Echo.connector.pusher.connection.bind('connected', () => {
    $('wsDot').className = 'ws-dot connected';
    $('wsText').textContent = 'WebSocket terhubung';
    // Subscribe semua group channel setelah WebSocket terhubung
    subscribeAllGroupChannels();
});
window.Echo.connector.pusher.connection.bind('disconnected', () => {
    $('wsDot').className = 'ws-dot disconnected';
    $('wsText').textContent = 'WebSocket terputus';
});
window.Echo.connector.pusher.connection.bind('connecting', () => {
    $('wsDot').className = 'ws-dot';
    $('wsText').textContent = 'Menghubungkan...';
});

// ─── Subscribe presence channel global (online/offline) ─────────────────────
window.Echo.channel('presence')
    .listen('.user.presence', (data) => {
        updateUserPresenceInSidebar(data.user_id, data.status);
    });

// ─── Set offline saat user tutup browser/tab tanpa logout ────────────────────
window.addEventListener('beforeunload', () => {
    // Gunakan sendBeacon supaya request tetap terkirim meski browser ditutup
    const formData = new FormData();
    formData.append('_token', cfg.csrfToken);
    navigator.sendBeacon('/set-offline', formData);
});

// ─── Heartbeat setiap 30 detik — tandai user masih aktif ─────────────────────
setInterval(() => {
    apiFetch('/heartbeat', 'POST').catch(() => {});
}, 30000);

// ─── Load unread badge saat halaman dibuka ───────────────────────────────────
(async function loadUnreadCounts() {
    try {
        const res  = await apiFetch(cfg.routes.unreadCounts);
        const data = await res.json();
        Object.entries(data.private || {}).forEach(([senderId, count]) => {
            updateBadge('dm', senderId, count);
        });
    } catch (e) {
        console.error('Gagal load unread counts', e);
    }
})();

// ═══════════════════════════════════════════════════════════════════════════
// SIDEBAR — Pilih conversation
// ═══════════════════════════════════════════════════════════════════════════
document.querySelectorAll('.contact-item').forEach(item => {
    item.addEventListener('click', () => openConversation(item));
});

function openConversation(item) {
    const type = item.dataset.type;
    const id   = parseInt(item.dataset.id);

    // Highlight aktif
    document.querySelectorAll('.contact-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');

    // Unsubscribe dari channel sebelumnya
    leaveAllChannels();

    // Reset typing
    $('headerTyping').style.display = 'none';

    state.currentChat = { type, id, name: item.dataset.name };

    // Tampilkan area chat
    $('emptyState').style.display = 'none';
    $('chatArea').style.display   = 'flex';

    if (type === 'private') {
        openPrivateChat(id, item);
    } else {
        openGroupChat(id, item);
    }
}

// ─── Private Chat ─────────────────────────────────────────────────────────────
async function openPrivateChat(userId, item) {
    const isOnline = item.dataset.online === 'true';
    const color    = item.dataset.color || stringToColor(item.dataset.name);
    const initials = item.dataset.initials;

    const headerAvatar = $('chatHeaderAvatar');
    headerAvatar.textContent     = initials;
    headerAvatar.style.background = color;

    $('chatHeaderName').textContent = item.dataset.name;
    $('chatHeaderMeta').textContent = isOnline ? '🟢 Online' : '⚫ Offline';
    $('membersPanel').style.display  = 'none';

    // Hapus badge unread
    updateBadge('dm', userId, 0);

    // Muat pesan
    showLoadingMessages();
    try {
        const res  = await apiFetch(`${cfg.routes.privateMessages}/${userId}`);
        const data = await res.json();
        renderMessages(data.messages);
    } catch (e) {
        showError('Gagal memuat pesan.');
    }

    // Subscribe private channel
    const ids = [cfg.currentUserId, userId].sort((a, b) => a - b);
    const channelName = `chat.${ids.join('-')}`;

    window.Echo.private(channelName)
        .listen('.message.sent', (data) => {
            // Skip pesan milik sendiri — sudah ditambahkan langsung dari API response
            if (data.sender_id === cfg.currentUserId) return;

            if (state.currentChat?.type === 'private' && state.currentChat?.id === userId) {
                appendMessage(data);
                scrollToBottom();
            } else {
                // Update badge jika chat tidak aktif
                const currentCount = getBadgeCount('dm', data.sender_id);
                updateBadge('dm', data.sender_id, currentCount + 1);
            }
        })
        .listen('.user.typing', (data) => {
            if (data.sender_id !== cfg.currentUserId) {
                showTypingIndicator(data.sender_name, data.is_typing);
            }
        });

    state.activeChannels.push(channelName);
}

// ─── Group Chat ───────────────────────────────────────────────────────────────
async function openGroupChat(groupId, item) {
    $('chatHeaderAvatar').textContent      = '☐';
    $('chatHeaderAvatar').style.background = '#374151';
    $('chatHeaderName').textContent        = item.dataset.name;
    $('chatHeaderMeta').textContent        = `${item.dataset.members} anggota`;
    $('membersPanel').style.display        = 'block';
    $('membersList').innerHTML             = '<li style="color:#6b7280;font-size:13px;padding:8px">Memuat...</li>';

    showLoadingMessages();
    try {
        const res  = await apiFetch(`${cfg.routes.groupMessages}/${groupId}`);
        const data = await res.json();

        if (data.error) { showError(data.error); return; }

        renderMessages(data.messages);
        $('chatHeaderMeta').textContent =
            `${data.group.members_count} anggota · ${data.group.online_count} online`;

        renderMembersList(data.group.members);

    } catch (e) {
        showError('Gagal memuat pesan group.');
    }

    // Subscribe presence channel — untuk tracking online/offline anggota
    const channelName = `group.${groupId}`;

    window.Echo.join(channelName)
        .here((members) => {
            members.forEach(m => updateMemberPresenceItem(m.id, true));
        })
        .joining((member) => {
            updateMemberPresenceItem(member.id, true);
        })
        .leaving((member) => {
            updateMemberPresenceItem(member.id, false);
        })
        .listen('.user.typing', (data) => {
            if (data.sender_id !== cfg.currentUserId) {
                showTypingIndicator(data.sender_name, data.is_typing);
                updateMembersTypingStatus(data.sender_id, data.is_typing);
            }
        });

    // Subscribe private channel untuk pesan — pastikan sudah subscribe
    // (subscribeGroupChannel() sudah dipanggil saat connected, tapi group baru perlu di-subscribe manual)
    subscribeGroupChannel(groupId);

    // Hapus badge karena user sudah buka group ini
    updateBadge('group', groupId, 0);

    state.activeChannels.push(channelName);
}

// ─── Leave semua channel aktif ────────────────────────────────────────────────
function leaveAllChannels() {
    state.activeChannels.forEach(name => window.Echo.leave(name));
    state.activeChannels = [];
}

// ═══════════════════════════════════════════════════════════════════════════
// RENDER PESAN
// ═══════════════════════════════════════════════════════════════════════════
function renderMessages(messages) {
    const list = $('messagesList');
    list.innerHTML = '';

    if (!messages.length) {
        list.innerHTML = '<div style="text-align:center;color:#6b7280;padding:40px;font-size:14px">Belum ada pesan. Mulai percakapan!</div>';
        return;
    }

    let lastSenderId = null;

    messages.forEach(msg => {
        const isMine = msg.sender_id === cfg.currentUserId;

        if (lastSenderId !== null && lastSenderId !== msg.sender_id) {
            list.appendChild(createSpacer());
        }

        list.appendChild(buildMessageElement(msg, isMine, lastSenderId !== msg.sender_id));
        lastSenderId = msg.sender_id;
    });

    scrollToBottom();
}

function buildMessageElement(msg, isMine, showSenderName) {
    const div = document.createElement('div');
    div.className = `message-group ${isMine ? 'mine' : 'theirs'}`;
    div.dataset.messageId   = msg.id;
    div.dataset.senderId    = msg.sender_id;

    const senderHtml = (!isMine && msg.group_id && showSenderName)
        ? `<div class="message-sender-name">${escapeHtml(msg.sender.name)}</div>`
        : '';

    const avatarHtml = !isMine
        ? `<div class="avatar sm" style="background:${stringToColor(msg.sender.name)}">${escapeHtml(msg.sender.initials)}</div>`
        : '';

    div.innerHTML = `
        ${senderHtml}
        <div class="message-row">
            ${avatarHtml}
            <div class="message-bubble">${escapeHtml(msg.body)}</div>
        </div>
        <div class="message-time">${msg.created_at}</div>
    `;

    return div;
}

function appendMessage(msg) {
    const list = $('messagesList');

    // Hapus placeholder
    const placeholder = list.querySelector('[data-placeholder]');
    if (placeholder) placeholder.remove();

    const isMine = msg.sender_id === cfg.currentUserId;
    const lastGroup = list.querySelector('.message-group:last-child');

    // Spacer jika pengirim berbeda
    if (lastGroup && parseInt(lastGroup.dataset.senderId) !== msg.sender_id) {
        list.appendChild(createSpacer());
    }

    list.appendChild(buildMessageElement(msg, isMine, true));
}

function createSpacer() {
    const d = document.createElement('div');
    d.className = 'message-spacer';
    return d;
}

function showLoadingMessages() {
    $('messagesList').innerHTML =
        '<div style="text-align:center;color:#6b7280;padding:40px;font-size:14px">Memuat pesan...</div>';
}

function showError(msg) {
    $('messagesList').innerHTML =
        `<div style="text-align:center;color:#f87171;padding:40px;font-size:14px">${escapeHtml(msg)}</div>`;
}

function scrollToBottom() {
    const c = $('messagesContainer');
    setTimeout(() => { c.scrollTop = c.scrollHeight; }, 50);
}

// ═══════════════════════════════════════════════════════════════════════════
// KIRIM PESAN
// ═══════════════════════════════════════════════════════════════════════════
async function sendMessage() {
    const input = $('messageInput');
    const body  = input.value.trim();
    if (!body || !state.currentChat) return;

    input.value = '';
    stopTyping();

    const payload = { body };
    if (state.currentChat.type === 'private') {
        payload.receiver_id = state.currentChat.id;
    } else {
        payload.group_id = state.currentChat.id;
    }

    try {
        const res  = await apiFetch(cfg.routes.sendMessage, 'POST', payload);
        const data = await res.json();
        if (res.ok) {
            appendMessage(data);
            scrollToBottom();
        }
    } catch (e) {
        console.error('Error kirim pesan', e);
    }
}

$('btnSend').addEventListener('click', sendMessage);
$('messageInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// TYPING INDICATOR
// ═══════════════════════════════════════════════════════════════════════════
$('messageInput').addEventListener('input', () => {
    if (!state.currentChat) return;

    if (!state.isTyping) {
        state.isTyping = true;
        broadcastTyping(true);
    }
    clearTimeout(state.typingTimer);
    state.typingTimer = setTimeout(stopTyping, 2000);
});

function stopTyping() {
    if (state.isTyping) {
        state.isTyping = false;
        broadcastTyping(false);
    }
    clearTimeout(state.typingTimer);
}

async function broadcastTyping(isTyping) {
    if (!state.currentChat) return;
    const payload = { is_typing: isTyping };
    if (state.currentChat.type === 'private') {
        payload.receiver_id = state.currentChat.id;
    } else {
        payload.group_id = state.currentChat.id;
    }
    try {
        await apiFetch(cfg.routes.typing, 'POST', payload);
    } catch (_) {}
}

function showTypingIndicator(senderName, isTyping) {
    const el   = $('headerTyping');
    const text = $('headerTypingText');
    if (isTyping) {
        text.textContent  = `${senderName} sedang mengetik...`;
        el.style.display  = 'flex';
    } else {
        el.style.display  = 'none';
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// PANEL ANGGOTA (Group)
// ═══════════════════════════════════════════════════════════════════════════
function renderMembersList(members) {
    const list = $('membersList');
    list.innerHTML = '';
    members.forEach(member => {
        const isSelf = member.id === cfg.currentUserId;
        const li = document.createElement('li');
        li.className = 'member-item';
        li.dataset.memberId = member.id;
        li.innerHTML = `
            <div class="avatar sm" style="background:${stringToColor(member.name)}">
                ${escapeHtml(member.initials)}
            </div>
            <div class="member-info">
                <span class="member-name">${escapeHtml(member.name)}${isSelf ? ' <span style="color:#6b7280;font-size:11px">(Kamu)</span>' : ''}</span>
                <span class="member-status-text ${member.is_online ? 'online' : ''}" id="member-status-${member.id}">
                    ${member.is_online ? 'Online' : 'Offline'}
                </span>
            </div>
            <span class="status-dot ${member.is_online ? 'online' : 'offline'}" id="member-dot-${member.id}"></span>
        `;
        list.appendChild(li);
    });
}

function updateMemberPresenceItem(memberId, isOnline) {
    const dot    = $(`member-dot-${memberId}`);
    const status = $(`member-status-${memberId}`);
    if (dot)    dot.className    = `status-dot ${isOnline ? 'online' : 'offline'}`;
    if (status) {
        status.textContent = isOnline ? 'Online' : 'Offline';
        status.className   = `member-status-text ${isOnline ? 'online' : ''}`;
    }
}

function updateMembersTypingStatus(senderId, isTyping) {
    const status = $(`member-status-${senderId}`);
    if (status) {
        status.textContent = isTyping ? 'Mengetik...' : 'Online';
        status.className   = `member-status-text ${isTyping ? 'typing' : 'online'}`;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// PRESENCE GLOBAL — update dot di sidebar kiri
// ═══════════════════════════════════════════════════════════════════════════
function updateUserPresenceInSidebar(userId, status) {
    const dot = $(`status-${userId}`);
    if (dot) dot.className = `status-dot ${status}`;

    const item = document.querySelector(`.contact-item[data-id="${userId}"][data-type="private"]`);
    if (item) {
        item.dataset.online = status === 'online' ? 'true' : 'false';
        if (state.currentChat?.type === 'private' && state.currentChat?.id === userId) {
            $('chatHeaderMeta').textContent = status === 'online' ? '🟢 Online' : '⚫ Offline';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// BADGE UNREAD
// ═══════════════════════════════════════════════════════════════════════════
function updateBadge(type, id, count) {
    const badgeEl = $(`badge-${type}-${id}`);
    if (!badgeEl) return;
    if (count > 0) {
        badgeEl.textContent  = count > 99 ? '99+' : count;
        badgeEl.style.display = 'flex';
    } else {
        badgeEl.style.display = 'none';
    }
}

function getBadgeCount(type, id) {
    const el = $(`badge-${type}-${id}`);
    if (!el || el.style.display === 'none') return 0;
    return parseInt(el.textContent) || 0;
}

// ═══════════════════════════════════════════════════════════════════════════
// MODAL BUAT GROUP
// ═══════════════════════════════════════════════════════════════════════════
$('btnNewGroup').addEventListener('click', () => {
    $('modalOverlay').style.display = 'flex';
    $('groupName').value = '';
    document.querySelectorAll('#memberCheckboxes input').forEach(cb => cb.checked = false);
});

[$('modalClose'), $('btnCancel')].forEach(el => {
    el.addEventListener('click', () => { $('modalOverlay').style.display = 'none'; });
});

$('modalOverlay').addEventListener('click', e => {
    if (e.target === $('modalOverlay')) $('modalOverlay').style.display = 'none';
});

$('btnCreateGroup').addEventListener('click', async () => {
    const name = $('groupName').value.trim();
    if (!name) { alert('Nama group tidak boleh kosong.'); return; }

    const memberIds = Array.from(
        document.querySelectorAll('#memberCheckboxes input:checked')
    ).map(cb => parseInt(cb.value));

    if (!memberIds.length) { alert('Pilih minimal 1 anggota.'); return; }

    try {
        const res  = await apiFetch(cfg.routes.createGroup, 'POST', { name, member_ids: memberIds });
        const data = await res.json();
        if (res.ok) {
            addGroupToSidebar(data);
            // Subscribe channel group baru supaya notifikasi langsung aktif
            subscribeGroupChannel(data.id);
            $('modalOverlay').style.display = 'none';
        } else {
            alert(data.message || 'Gagal membuat group.');
        }
    } catch (_) {
        alert('Terjadi kesalahan. Coba lagi.');
    }
});

function addGroupToSidebar(group) {
    const list = $('groupList');
    const li   = document.createElement('li');
    li.className    = 'contact-item';
    li.dataset.type    = 'group';
    li.dataset.id      = group.id;
    li.dataset.name    = group.name;
    li.dataset.members = group.members_count;
    li.innerHTML = `
        <div class="group-icon">☐</div>
        <div class="contact-info">
            <span class="contact-name">${escapeHtml(group.name)}</span>
        </div>
        <span class="unread-badge" id="badge-group-${group.id}" style="display:none">0</span>
    `;
    li.addEventListener('click', () => openConversation(li));
    list.appendChild(li);
}

// ═══════════════════════════════════════════════════════════════════════════
// SEARCH SIDEBAR
// ═══════════════════════════════════════════════════════════════════════════
$('searchInput').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('.contact-item').forEach(item => {
        item.style.display = (item.dataset.name || '').toLowerCase().includes(q) ? '' : 'none';
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════
async function apiFetch(url, method = 'GET', body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': cfg.csrfToken,
        },
    };
    if (body) opts.body = JSON.stringify(body);
    return fetch(url, opts);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function stringToColor(str) {
    // Hitam putih — semua avatar pakai warna gelap
    return '#333333';
}
