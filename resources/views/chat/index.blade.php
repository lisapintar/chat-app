<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="chat-app" id="app">

    {{-- ═══════════════════════════════════════════════
         SIDEBAR KIRI
    ═══════════════════════════════════════════════ --}}
    <aside class="sidebar" id="sidebar">

        {{-- Profil user yang sedang login --}}
        <div class="sidebar-profile">
            <div class="avatar">{{ $currentUser->initials }}</div>
            <div class="profile-info">
                <span class="profile-name">{{ $currentUser->name }}</span>
                <span class="profile-status online">● Online</span>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin-left:auto">
                @csrf
                <button type="submit" class="btn-logout" title="Logout">Keluar</button>
            </form>
        </div>

        {{-- Pencarian --}}
        <div class="sidebar-search">
            <input type="text" id="searchInput" placeholder="Cari pengguna..." />
        </div>

        {{-- Direct Messages --}}
        <div class="sidebar-section-title">DIRECT MESSAGE</div>
        <ul class="contact-list" id="dmList">
            @foreach($users as $user)
            <li class="contact-item"
                data-type="private"
                data-id="{{ $user->id }}"
                data-name="{{ $user->name }}"
                data-initials="{{ $user->initials }}"
                data-online="{{ $user->is_online ? 'true' : 'false' }}"
                data-color="#333333">

                <div class="avatar">{{ $user->initials }}</div>
                <div class="contact-info">
                    <span class="contact-name">{{ $user->name }}</span>
                    <span id="typing-dm-{{ $user->id }}" style="display:none;font-size:11px;color:#444444;font-style:italic">mengetik...</span>
                </div>
                <span class="status-dot {{ $user->is_online ? 'online' : 'offline' }}"
                      id="status-{{ $user->id }}"></span>
                <span class="unread-badge" id="badge-dm-{{ $user->id }}" style="display:none">0</span>
            </li>
            @endforeach
        </ul>

        {{-- Group Chat --}}
        <div class="sidebar-section-title" style="margin-top:8px">
            GROUP CHAT
            <button class="btn-new-group" id="btnNewGroup" title="Buat Group Baru">+</button>
        </div>
        <ul class="contact-list" id="groupList">
            @foreach($groups as $group)
            <li class="contact-item"
                data-type="group"
                data-id="{{ $group->id }}"
                data-name="{{ $group->name }}"
                data-members="{{ $group->members_count }}">

                <div class="group-icon">#</div>
                <div class="contact-info">
                    <span class="contact-name">{{ $group->name }}</span>
                </div>
                <span class="unread-badge" id="badge-group-{{ $group->id }}" style="display:none">0</span>
            </li>
            @endforeach
        </ul>

    </aside>

    {{-- ═══════════════════════════════════════════════
         PANEL TENGAH — Area Chat
    ═══════════════════════════════════════════════ --}}
    <main class="chat-main" id="chatMain">

        <div class="empty-state" id="emptyState">
            <div class="empty-icon">💬</div>
            <h2>Selamat datang, {{ $currentUser->name }}</h2>
            <p>Pilih kontak atau group di sebelah kiri untuk mulai chat</p>
        </div>

        <div class="chat-area" id="chatArea" style="display:none">

            {{-- Header --}}
            <div class="chat-header" id="chatHeader">
                <div class="chat-header-info">
                    <div class="avatar" id="chatHeaderAvatar">?</div>
                    <div>
                        <div class="chat-header-name" id="chatHeaderName">–</div>
                        <div class="chat-header-meta" id="chatHeaderMeta">–</div>
                    </div>
                </div>
                <div class="header-typing" id="headerTyping" style="display:none">
                    <span id="headerTypingText">mengetik...</span>
                </div>
            </div>

            {{-- Pesan --}}
            <div class="messages-container" id="messagesContainer">
                <div class="messages-list" id="messagesList"></div>
            </div>

            {{-- Input --}}
            <div class="message-input-area">
                <input type="text"
                       id="messageInput"
                       class="message-input"
                       placeholder="Tulis pesan..."
                       autocomplete="off">
                <button class="btn-send" id="btnSend" title="Kirim">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>

            {{-- Status WebSocket --}}
            <div class="ws-status" id="wsStatus">
                <span class="ws-dot" id="wsDot"></span>
                <span id="wsText">Menghubungkan...</span>
            </div>

        </div>
    </main>

    {{-- ═══════════════════════════════════════════════
         SIDEBAR KANAN — Anggota Group
    ═══════════════════════════════════════════════ --}}
    <aside class="members-panel" id="membersPanel" style="display:none">
        <div class="members-title">ANGGOTA</div>
        <ul class="members-list" id="membersList"></ul>
    </aside>

</div>

{{-- MODAL Buat Group --}}
<div class="modal-overlay" id="modalOverlay" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Buat Group Baru</h3>
            <button class="modal-close" id="modalClose">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nama Group</label>
                <input type="text" id="groupName" placeholder="Contoh: Kelas TI-4A">
            </div>
            <div class="form-group">
                <label>Pilih Anggota</label>
                <div class="member-checkboxes" id="memberCheckboxes">
                    @foreach($users as $user)
                    <label class="checkbox-item">
                        <input type="checkbox" name="members" value="{{ $user->id }}">
                        <div class="avatar sm">{{ $user->initials }}</div>
                        {{ $user->name }}
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" id="btnCancel">Batal</button>
            <button class="btn-create" id="btnCreateGroup">Buat Group</button>
        </div>
    </div>
</div>

<script>
    window.chatConfig = {
        currentUserId:   {{ $currentUser->id }},
        currentUserName: @json($currentUser->name),
        routes: {
            privateMessages: '{{ url("/messages/private") }}',
            groupMessages:   '{{ url("/messages/group") }}',
            sendMessage:     '{{ route("chat.send") }}',
            typing:          '{{ route("chat.typing") }}',
            createGroup:     '{{ route("group.create") }}',
            unreadCounts:    '{{ route("chat.unread") }}',
        },
        csrfToken: '{{ csrf_token() }}',
    };
</script>

</body>
</html>
