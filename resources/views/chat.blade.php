<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        .navbar {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex-shrink: 0;
            z-index: 10;
        }
        .navbar-brand { font-size: 18px; font-weight: 700; color: #FF2D20; display: flex; align-items: center; gap: 8px; }
        .navbar-user { display: flex; align-items: center; gap: 12px; }
        .navbar-user span { font-size: 14px; color: #555; font-weight: 600; }
        .btn-logout { background: #FF2D20; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .btn-logout:hover { background: #cc2419; }

        .chat-wrapper { display: flex; flex: 1; overflow: hidden; height: calc(100vh - 60px); }

        .sidebar { width: 320px; background: #fff; border-right: 1px solid #e0e0e0; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
        .sidebar-header h3 { font-size: 16px; font-weight: 700; color: #333; }
        .sidebar-header p { font-size: 12px; color: #888; margin-top: 2px; }

        .user-list { flex: 1; overflow-y: auto; background: #fff; }
        .loading-users { padding: 20px; text-align: center; color: #888; font-size: 13px; }

        .user-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; cursor: pointer; border-bottom: 1px solid #f9f9f9; transition: background 0.2s; }
        .user-item:hover { background: #f5f6f7; }
        .user-item.active { background: #ffebeb; border-left: 4px solid #FF2D20; }

        .avatar { width: 40px; height: 40px; border-radius: 50%; background: #FF2D20; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; position: relative; flex-shrink: 0; }
        .online-dot { width: 11px; height: 11px; background: #ccc; border: 2px solid white; border-radius: 50%; position: absolute; bottom: 0; right: 0; }
        .online-dot.online { background: #22c55e; }

        .user-info { flex: 1; min-width: 0; }
        .user-info .name { font-size: 14px; font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-info .status { font-size: 12px; color: #888; margin-top: 2px; }
        .user-info .status.online { color: #22c55e; }

        .chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #f0f2f5; }
        .chat-header { padding: 12px 20px; background: #fff; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; gap: 12px; height: 60px; flex-shrink: 0; }
        .chat-header .avatar { width: 36px; height: 36px; font-size: 13px; }
        .chat-header .info .name { font-size: 15px; font-weight: 600; color: #333; }
        .chat-header .info .status { font-size: 11px; color: #888; }
        .chat-header .info .status.online { color: #22c55e; }

        .empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #aaa; gap: 10px; }
        .empty-state .icon { font-size: 48px; }
        .empty-state p { font-size: 15px; }

        .messages-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 8px; background: #e5ddd5; }
        .msg-bubble { max-width: 65%; display: flex; flex-direction: column; }
        .msg-bubble.mine { align-self: flex-end; align-items: flex-end; }
        .msg-bubble.other { align-self: flex-start; align-items: flex-start; }

        .bubble-text { padding: 8px 12px; border-radius: 12px; font-size: 14px; line-height: 1.4; word-break: break-word; }
        .msg-bubble.mine .bubble-text { background: #FF2D20; color: white; border-top-right-radius: 0px; }
        .msg-bubble.other .bubble-text { background: white; color: #333; border-top-left-radius: 0px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
        .bubble-time { font-size: 10px; color: #8c8c8c; margin-top: 2px; padding: 0 4px; }

        .input-area { padding: 12px 20px; background: #f0f2f5; display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
        .input-area input { flex: 1; padding: 10px 16px; border: 1px solid #fff; border-radius: 24px; font-size: 14px; outline: none; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: border-color 0.2s; }
        .input-area input:focus { border-color: #FF2D20; }
        .btn-send { background: #FF2D20; color: white; border: none; padding: 10px 20px; border-radius: 24px; cursor: pointer; font-size: 14px; font-weight: 600; transition: background 0.2s; }
        .btn-send:hover { background: #cc2419; }
        .btn-send:disabled { background: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <svg width="24" height="24" viewBox="0 0 50 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M49.6 10.5L37.7 0.3C37.3 0 36.8 0 36.3 0.3L28 7.6L19.7 0.3C19.2 0 18.7 0 18.3 0.3L6.4 10.5C6.1 10.8 6 11.2 6 11.5V40.5C6 40.9 6.2 41.2 6.5 41.5L18.4 51.7C18.9 52.1 19.6 52.1 20.1 51.7L28 44.9L35.9 51.7C36.4 52.1 37.1 52.1 37.6 51.7L49.5 41.5C49.8 41.2 50 40.8 50 40.5V11.5C50 11.1 49.9 10.8 49.6 10.5Z" fill="#FF2D20"/>
            </svg>
            Chat App
        </div>
        <div class="navbar-user">
            <span>{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <div class="chat-wrapper">

        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Pengguna & Grup</h3>
                <p id="online-count">Memuat...</p>
            </div>

            <div style="display:flex; border-bottom: 1px solid #e0e0e0; flex-shrink:0;">
                <button onclick="switchTab('users')" id="tab-users"
                    style="flex:1; padding:12px; border:none; background:#fff3f2; font-size:13px; font-weight:600; color:#FF2D20; cursor:pointer; border-bottom: 2px solid #FF2D20; transition:all 0.2s;">
                    👤 Personal
                </button>
                <button onclick="switchTab('groups')" id="tab-groups"
                    style="flex:1; padding:12px; border:none; background:#fff; font-size:13px; font-weight:600; color:#888; cursor:pointer; border-bottom: 2px solid transparent; transition:all 0.2s;">
                    👥 Grup Chat
                </button>
            </div>

            <div class="user-list" id="user-list">
                <div class="loading-users">⏳ Memuat daftar pengguna...</div>
            </div>

            <div class="user-list" id="group-list" style="display:none;">
                <div style="padding:12px 16px; border-bottom: 1px solid #f5f5f5;">
                    <button onclick="showCreateGroup()"
                        style="width:100%; padding:10px; background:#FF2D20; color:white; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                        + Buat Grup Baru
                    </button>
                </div>
                <div id="group-items">
                    <div class="loading-users">⏳ Memuat grup...</div>
                </div>
            </div>
        </div>

        <div class="chat-area" id="chat-area">
            <div class="empty-state">
                <div class="icon">💬</div>
                <p>Pilih teman atau grup untuk memulai percakapan</p>
            </div>
        </div>

    </div>

    {{-- MODAL BUAT GRUP --}}
    <div id="modal-create-group" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:24px; width:360px; max-width:90%; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <h3 style="margin-bottom:16px; font-size:16px; font-weight:700; color:#333;">Buat Grup Baru</h3>
            <input type="text" id="group-name-input" placeholder="Tulis nama grup..."
                style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:14px; font-size:14px; box-sizing:border-box; outline:none;">
            <p style="font-size:13px; font-weight:600; color:#555; margin-bottom:8px;">Pilih Anggota:</p>
            <div id="member-checkboxes" style="max-height:180px; overflow-y:auto; margin-bottom:20px; border:1px solid #eee; padding:8px; border-radius:8px; background:#fafafa;"></div>
            <div style="display:flex; gap:10px;">
                <button onclick="hideCreateGroup()"
                    style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px; background:white; font-size:13px; font-weight:600; cursor:pointer;">
                    Batal
                </button>
                <button onclick="submitCreateGroup()"
                    style="flex:1; padding:10px; background:#FF2D20; color:white; border:none; border-radius:8px; font-size:13px; cursor:pointer; font-weight:600;">
                    Buat Grup
                </button>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER = {
            id: {{ Auth::id() }},
            name: "{{ Auth::user()->name }}"
        };
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // State
        let selectedUser   = null;
        let selectedGroup  = null;
        let echoChannel    = null;
        let groupChannel   = null;
        let allUsers       = [];
        let allGroups      = [];
        let activeTab      = 'users';

        // ============================================================
        // UTILITY
        // ============================================================
        function formatTime(isoString) {
            const d = new Date(isoString);
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function scrollToBottom() {
            const c = document.getElementById('messages-container');
            if (c) c.scrollTop = c.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // ============================================================
        // APPEND MESSAGE (satu fungsi saja)
        // ============================================================
        function appendMessage(msg) {
            const container = document.getElementById('messages-container');
            if (!container) return;

            // Hapus teks placeholder jika ada
            const placeholder = container.querySelector('[data-placeholder]');
            if (placeholder) placeholder.remove();

            const div = document.createElement('div');
            div.className = `msg-bubble ${msg.is_mine ? 'mine' : 'other'}`;
            div.innerHTML = `
                <div class="bubble-text">${escapeHtml(msg.content)}</div>
                <div class="bubble-time">
                    ${formatTime(msg.created_at)}
                    ${msg.sender_name && !msg.is_mine ? ' · ' + escapeHtml(msg.sender_name) : ''}
                </div>
            `;
            container.appendChild(div);
            scrollToBottom();
        }

        // ============================================================
        // RENDER MESSAGES (list dari API)
        // ============================================================
        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            if (!container) return;

            if (messages.length === 0) {
                container.innerHTML = '<div data-placeholder style="text-align:center;color:#aaa;font-size:13px;margin-top:20px;">Belum ada pesan. Mulai percakapan! 👋</div>';
                return;
            }

            container.innerHTML = messages.map(msg => `
                <div class="msg-bubble ${msg.is_mine ? 'mine' : 'other'}">
                    <div class="bubble-text">${escapeHtml(msg.content)}</div>
                    <div class="bubble-time">
                        ${formatTime(msg.created_at)}
                        ${msg.sender_name && !msg.is_mine ? ' · ' + escapeHtml(msg.sender_name) : ''}
                    </div>
                </div>
            `).join('');

            scrollToBottom();
        }

        // ============================================================
        // TAB SWITCH
        // ============================================================
        function switchTab(tab) {
            activeTab = tab;
            const userList  = document.getElementById('user-list');
            const groupList = document.getElementById('group-list');
            const tabUsers  = document.getElementById('tab-users');
            const tabGroups = document.getElementById('tab-groups');

            if (tab === 'users') {
                userList.style.display  = 'block';
                groupList.style.display = 'none';
                tabUsers.style.cssText  = 'flex:1;padding:12px;border:none;background:#fff3f2;font-size:13px;font-weight:600;color:#FF2D20;cursor:pointer;border-bottom:2px solid #FF2D20;transition:all 0.2s;';
                tabGroups.style.cssText = 'flex:1;padding:12px;border:none;background:#fff;font-size:13px;font-weight:600;color:#888;cursor:pointer;border-bottom:2px solid transparent;transition:all 0.2s;';
            } else {
                userList.style.display  = 'none';
                groupList.style.display = 'block';
                tabUsers.style.cssText  = 'flex:1;padding:12px;border:none;background:#fff;font-size:13px;font-weight:600;color:#888;cursor:pointer;border-bottom:2px solid transparent;transition:all 0.2s;';
                tabGroups.style.cssText = 'flex:1;padding:12px;border:none;background:#fff3f2;font-size:13px;font-weight:600;color:#FF2D20;cursor:pointer;border-bottom:2px solid #FF2D20;transition:all 0.2s;';
                loadGroups();
            }
        }

        // ============================================================
        // LOAD USERS
        // ============================================================
        async function loadUsers() {
            try {
                const res = await fetch('/api/users', {
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                allUsers = await res.json();
                renderUserList();
                const onlineCount = allUsers.filter(u => u.is_online).length;
                document.getElementById('online-count').textContent =
                    `${allUsers.length} pengguna · ${onlineCount} online`;
            } catch (err) {
                console.error('loadUsers error:', err);
                document.getElementById('user-list').innerHTML =
                    `<div class="loading-users" style="color:red;">❌ Gagal memuat. Coba refresh.</div>`;
            }
        }

        function renderUserList() {
            const list = document.getElementById('user-list');
            if (allUsers.length === 0) {
                list.innerHTML = '<div class="loading-users">Tidak ada pengguna lain.</div>';
                return;
            }
            list.innerHTML = allUsers.map(user => `
                <div class="user-item ${selectedUser && selectedUser.id === user.id ? 'active' : ''}"
                     onclick="selectUser(${user.id})">
                    <div class="avatar">
                        ${user.avatar || user.name.charAt(0).toUpperCase()}
                        <span class="online-dot ${user.is_online ? 'online' : ''}"></span>
                    </div>
                    <div class="user-info">
                        <div class="name">${user.name}</div>
                        <div class="status ${user.is_online ? 'online' : ''}">
                            ${user.is_online ? '● Online' : '○ Offline'}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // ============================================================
        // SELECT USER
        // ============================================================
        async function selectUser(userId) {
            selectedUser  = allUsers.find(u => u.id === userId);
            selectedGroup = null;
            if (!selectedUser) return;

            renderUserList();

            if (echoChannel)   { try { window.Echo.leave(echoChannel);  } catch(e){} echoChannel  = null; }
            if (groupChannel)  { try { window.Echo.leave(groupChannel); } catch(e){} groupChannel = null; }

            document.getElementById('chat-area').innerHTML = `
                <div class="chat-header">
                    <div class="avatar">
                        ${selectedUser.avatar || selectedUser.name.charAt(0).toUpperCase()}
                        <span class="online-dot ${selectedUser.is_online ? 'online' : ''}"></span>
                    </div>
                    <div class="info">
                        <div class="name">${selectedUser.name}</div>
                        <div class="status ${selectedUser.is_online ? 'online' : ''}" id="chat-status">
                            ${selectedUser.is_online ? '● Online' : '○ Offline'}
                        </div>
                    </div>
                </div>
                <div class="messages-container" id="messages-container">
                    <div data-placeholder style="text-align:center;color:#aaa;font-size:13px;margin-top:20px;">Memuat pesan...</div>
                </div>
                <div class="input-area">
                    <input type="text" id="message-input" placeholder="Ketik pesan ke ${selectedUser.name}..."
                           onkeydown="if(event.key==='Enter') sendMessage()">
                    <button class="btn-send" id="send-btn" onclick="sendMessage()">Kirim</button>
                </div>
            `;

            await loadMessages(userId);
            subscribeToChannel(userId);
            document.getElementById('message-input').focus();
        }

        async function loadMessages(userId) {
            try {
                const res = await fetch(`/api/messages/${userId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                renderMessages(await res.json());
            } catch (err) {
                console.error('loadMessages error:', err);
                document.getElementById('messages-container').innerHTML =
                    '<div style="text-align:center;color:red;padding:20px;">Gagal memuat pesan.</div>';
            }
        }

        // ============================================================
        // SEND MESSAGE (private)
        // ============================================================
        async function sendMessage() {
            const input = document.getElementById('message-input');
            const btn   = document.getElementById('send-btn');
            if (!input || !selectedUser) return;
            const content = input.value.trim();
            if (!content) return;

            input.value  = '';
            btn.disabled = true;

            try {
                const res = await fetch('/api/send-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ receiver_id: selectedUser.id, content })
                });
                if (!res.ok) throw new Error(JSON.stringify(await res.json()));
                appendMessage(await res.json());
            } catch (err) {
                console.error('sendMessage error:', err);
                alert('Gagal mengirim pesan. Coba lagi.');
                input.value = content;
            } finally {
                btn.disabled = false;
                input.focus();
            }
        }

        // ============================================================
        // WEBSOCKET — PRIVATE CHAT
        // ============================================================
        function subscribeToChannel(otherUserId) {
            if (echoChannel) { try { window.Echo.leave(echoChannel); } catch(e){} }

            const ids = [CURRENT_USER.id, otherUserId].sort((a, b) => a - b);
            const channelName = `chat.${ids[0]}.${ids[1]}`;
            echoChannel = channelName;

            window.Echo.private(channelName)
                .listen('.message.sent', (data) => {
                    console.log('✅ WebSocket received:', data);
                    if (data.sender_id !== CURRENT_USER.id) {
                        appendMessage({
                            content:     data.content,
                            sender_id:   data.sender_id,
                            sender_name: data.sender_name,
                            is_mine:     false,
                            created_at:  data.created_at,
                        });
                    }
                })
                .listenToAll((event, data) => {
                    console.log('📡 Event masuk:', event, data);
                });

            console.log('🚀 Subscribed to:', channelName);
        }

        // ============================================================
        // LOAD GROUPS
        // ============================================================
        async function loadGroups() {
            try {
                const res = await fetch('/api/groups', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                allGroups = await res.json();
                renderGroupList();
            } catch (err) {
                console.error('loadGroups error:', err);
                document.getElementById('group-items').innerHTML =
                    '<div class="loading-users" style="color:red;">❌ Gagal memuat grup.</div>';
            }
        }

        function renderGroupList() {
            const container = document.getElementById('group-items');
            if (allGroups.length === 0) {
                container.innerHTML = '<div class="loading-users">Belum ada grup. Buat yang pertama!</div>';
                return;
            }
            container.innerHTML = allGroups.map(group => `
                <div class="user-item ${selectedGroup && selectedGroup.id === group.id ? 'active' : ''}"
                     onclick="selectGroup(${group.id})">
                    <div class="avatar" style="background:#7F77DD;">
                        ${group.avatar || group.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="user-info">
                        <div class="name">${group.name}</div>
                        <div class="status">${group.member_count} anggota</div>
                    </div>
                </div>
            `).join('');
        }

        // ============================================================
        // SELECT GROUP
        // ============================================================
        async function selectGroup(groupId) {
            selectedGroup = allGroups.find(g => g.id === groupId);
            selectedUser  = null;
            if (!selectedGroup) return;

            renderGroupList();

            if (groupChannel) { try { window.Echo.leave(groupChannel); } catch(e){} groupChannel = null; }
            if (echoChannel)  { try { window.Echo.leave(echoChannel);  } catch(e){} echoChannel  = null; }

            document.getElementById('chat-area').innerHTML = `
                <div class="chat-header">
                    <div class="avatar" style="background:#7F77DD;">
                        ${selectedGroup.avatar || selectedGroup.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="info">
                        <div class="name">${selectedGroup.name}</div>
                        <div class="status">${selectedGroup.member_count} anggota</div>
                    </div>
                </div>
                <div class="messages-container" id="messages-container">
                    <div data-placeholder style="text-align:center;color:#aaa;font-size:13px;margin-top:20px;">Memuat pesan...</div>
                </div>
                <div class="input-area">
                    <input type="text" id="message-input" placeholder="Ketik pesan ke grup ${selectedGroup.name}..."
                           onkeydown="if(event.key==='Enter') sendGroupMessage()">
                    <button class="btn-send" id="send-btn" onclick="sendGroupMessage()">Kirim</button>
                </div>
            `;

            await loadGroupMessages(groupId);
            ;
            document.getElementById('message-input').focus();
        }

        async function loadGroupMessages(groupId) {
            try {
                const res = await fetch(`/api/groups/${groupId}/messages`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                renderMessages(await res.json());
            } catch (err) {
                console.error('loadGroupMessages error:', err);
                document.getElementById('messages-container').innerHTML =
                    '<div style="text-align:center;color:red;padding:20px;">Gagal memuat pesan grup.</div>';
            }
        }

        // ============================================================
        // SEND MESSAGE (group)
        // ============================================================
        async function sendGroupMessage() {
            const input = document.getElementById('message-input');
            const btn   = document.getElementById('send-btn');
            if (!input || !selectedGroup) return;
            const content = input.value.trim();
            if (!content) return;

            input.value  = '';
            btn.disabled = true;

            try {
                const res = await fetch('/api/groups/send-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ group_id: selectedGroup.id, content })
                });
                if (!res.ok) throw new Error(await res.text());
                appendMessage(await res.json());
            } catch (err) {
                console.error('sendGroupMessage error:', err);
                alert('Gagal mengirim pesan. Coba lagi.');
                input.value = content;
            } finally {
                btn.disabled = false;
                input.focus();
            }
        }

        // ============================================================
        // WEBSOCKET — GROUP CHAT
        // ============================================================
        function subscribeToGroupChannel(groupId) {
    if (groupChannel) { try { window.Echo.leave(groupChannel); } catch(e){} }

    const channelName = `group.${groupId}`;
    groupChannel = channelName;

    window.Echo.channel(channelName)
        .listen('.group.message.sent', (data) => {
            console.log('👥 Group message received:', data);
            if (data.sender_id !== CURRENT_USER.id) {
                appendMessage({
                    content:     data.content,
                    sender_id:   data.sender_id,
                    sender_name: data.sender_name,
                    is_mine:     false,
                    created_at:  data.created_at,
                });
            }
        })
        // 🔴 TAMBAHKAN KODE DI BAWAH INI:
        .listenForWhisper('typing', (e) => {
            // Jika yang mengetik adalah orang lain, munculkan teks
            if (e.sender_id !== CURRENT_USER.id) {
                showTypingIndicator(`${e.sender_name} sedang mengetik...`);
            }
        })
        .listenForWhisper('stop_typing', (e) => {
            // Jika orang lain berhenti mengetik, hapus teks
            if (e.sender_id !== CURRENT_USER.id) {
                hideTypingIndicator();
            }
        });

    console.log('🚀 Subscribed to group channel:', channelName);
}

        // ============================================================
        // PRESENCE TRACKING
        // ============================================================
        function subscribeToPresence() {
            window.Echo.channel('presence')
                .listen('.presence.updated', (data) => {
                    const user = allUsers.find(u => u.id === data.user_id);
                    if (user) {
                        user.is_online = data.is_online;
                        renderUserList();
                        if (selectedUser && selectedUser.id === data.user_id) {
                            selectedUser.is_online = data.is_online;
                            const statusEl = document.getElementById('chat-status');
                            if (statusEl) {
                                statusEl.textContent = data.is_online ? '● Online' : '○ Offline';
                                statusEl.className   = `status ${data.is_online ? 'online' : ''}`;
                            }
                        }
                        const onlineCount = allUsers.filter(u => u.is_online).length;
                        document.getElementById('online-count').textContent =
                            `${allUsers.length} pengguna · ${onlineCount} online`;
                    }
                });
        }

        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon('/api/update-presence',
                new Blob([JSON.stringify({ is_online: false, _token: CSRF_TOKEN })],
                { type: 'application/json' }));
        });

        // ============================================================
        // MODAL CREATE GROUP
        // ============================================================
        function showCreateGroup() {
            document.getElementById('modal-create-group').style.display = 'flex';
            document.getElementById('member-checkboxes').innerHTML = allUsers.map(user => `
                <label style="display:flex;align-items:center;gap:10px;padding:8px 0;cursor:pointer;font-size:13px;color:#444;">
                    <input type="checkbox" value="${user.id}" style="width:16px;height:16px;accent-color:#FF2D20;">
                    <span>${user.name}</span>
                </label>
            `).join('');
        }

        function hideCreateGroup() {
            document.getElementById('modal-create-group').style.display = 'none';
            document.getElementById('group-name-input').value = '';
        }

        async function submitCreateGroup() {
            const name = document.getElementById('group-name-input').value.trim();
            if (!name) { alert('Nama grup wajib diisi!'); return; }

            const checked   = document.querySelectorAll('#member-checkboxes input:checked');
            const memberIds = Array.from(checked).map(cb => parseInt(cb.value));
            if (memberIds.length === 0) { alert('Pilih minimal 1 anggota!'); return; }

            try {
                const res = await fetch('/api/groups', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name, member_ids: memberIds })
                });
                if (!res.ok) throw new Error(await res.text());
                const group = await res.json();
                allGroups.push({ ...group, avatar: group.name.charAt(0).toUpperCase() });
                renderGroupList();
                hideCreateGroup();
                alert(`Grup "${group.name}" berhasil dibuat!`);
            } catch (err) {
                console.error('createGroup error:', err);
                alert('Gagal membuat grup. Coba lagi.');
            }
        }

        // ============================================================
        // INIT
        // ============================================================
        document.addEventListener('DOMContentLoaded', async () => {
            if (typeof window.Echo === 'undefined') {
                console.error('❌ Echo tidak tersedia!');
                return;
            }

            await loadUsers();
            subscribeToPresence();

            // ============================================================
// LOGIKA TYING INDICATOR (TAMBAHAN)
// ============================================================
let typingTimeout;

// 1. Tangkap elemen input chat kamu
const msgInput = document.getElementById('message-input');

if (msgInput) {
    // 2. Deteksi setiap kali user menekan tombol di keyboard
    msgInput.addEventListener('keyup', () => {
        // Pastikan kita sedang berada di dalam grup chat
        if (!groupChannel) return; 

        // Kirim sinyal "sedang mengetik" ke grup lewat Echo Whisper
        window.Echo.channel(groupChannel).whisper('typing', {
            sender_id: CURRENT_USER.id,
            sender_name: CURRENT_USER.name
        });

        // Hapus timer yang lama
        clearTimeout(typingTimeout);

        // Jika diam selama 1.5 detik, kirim sinyal "berhenti mengetik"
        typingTimeout = setTimeout(() => {
            window.Echo.channel(groupChannel).whisper('stop_typing', {
                sender_id: CURRENT_USER.id
            });
        }, 1500);
    });
}

            await fetch('/api/update-presence', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify({ is_online: true })
            });
        });

        // Fungsi untuk memunculkan teks indikator
function showTypingIndicator(text) {
    let indicator = document.getElementById('typing-indicator');
    
    // Jika elemennya belum ada di HTML, kita buat otomatis lewat JS
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'typing-indicator';
        indicator.style.cssText = "color: gray; font-size: 12px; font-style: italic; padding: 5px 15px;";
        
        // Letakkan di atas kotak input chat (sebelum elemen message-input)
        const inputEl = document.getElementById('message-input');
        inputEl.parentNode.insertBefore(indicator, inputEl);
    }
    
    indicator.textContent = text;
    indicator.style.display = 'block';
}

// Fungsi untuk menyembunyikan teks indikator
function hideTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
}
    </script>
</body>
</html>