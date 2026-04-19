<x-filament-panels::page>
<style>
/* ═══════════════════════════════════════════════════
   FLASHSHIP AI — PREMIUM UI (REFACTORED)
═══════════════════════════════════════════════════ */
:root {
    --primary: #2563eb;
    --primary-light: #eff6ff;
    --secondary: #7c3aed;
    --secondary-light: #f5f3ff;
    --accent: #db2777;
    --accent-light: #fdf2f8;
    --glass: rgba(255, 255, 255, 0.85);
    --border-glass: rgba(255, 255, 255, 0.4);
    --shadow-main: 0 20px 40px -15px rgba(0, 0, 0, 0.08);
}

/* Base Layout Overrides */
.fi-main-ctn { max-width: 100% !important; }

/* Tabs Navigation */
.nav-container { display: flex; justify-content: center; margin-bottom: 24px; }
.chat-tabs {
    display: inline-flex;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 20px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
}
.chat-tab {
    padding: 10px 32px;
    border-radius: 16px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: transparent;
    color: #64748b;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.chat-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    transform: scale(1.02);
}

/* Page Layout */
.page-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 28px;
    height: auto;
    min-height: 500px;
    align-items: flex-start;
    animation: fadeIn 0.6s ease-out;
}
.chat-card {
    height: 550px; /* Độ cao cố định để cân đối với sidebar */
    display: flex;
    flex-direction: column;
    background: var(--glass);
    backdrop-filter: blur(20px);
    border-radius: 32px;
    border: 1px solid var(--border-glass);
    box-shadow: var(--shadow-main);
    overflow: hidden;
    position: relative;
}

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }


/* Chat Header */
.chat-top {
    padding: 20px 28px;
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    z-index: 10;
}
.teach-header { background: linear-gradient(135deg, #2563eb, #6366f1); color: white; }
.sim-header { background: linear-gradient(135deg, #7c3aed, #db2777); color: white; }

.bot-avatar {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.bot-info { flex: 1; }
.bot-name { font-weight: 850; font-size: 18px; letter-spacing: -0.02em; }
.bot-sub { opacity: 0.85; font-size: 13px; font-weight: 500; }

.status-pill {
    background: rgba(255,255,255,0.25);
    padding: 5px 14px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dot { width: 8px; height: 8px; border-radius: 50%; background: #4dfa9d; box-shadow: 0 0 10px #4dfa9d; animation: pulse 2s infinite; }
@keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.3); opacity: 0.6; } 100% { transform: scale(1); opacity: 1; } }

/* Region Selection Bar */
.region-bar {
    padding: 12px 28px;
    background: rgba(255,255,255,0.5);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 5;
}
.region-label { font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
.region-select {
    border: 1.5px solid #e2e8f0;
    background: white;
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    outline: none;
    transition: all 0.3s;
    flex: 1;
    max-width: 280px;
}
.region-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

/* Messages Area */
.msgs {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.msgs::-webkit-scrollbar { width: 4px; }
.msgs::-webkit-scrollbar-track { background: transparent; }
.msgs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 20px; }

.row { display: flex; gap: 14px; align-items: flex-end; max-width: 85%; }
.row.ai-row { align-self: flex-start; }
.row.me-row { align-self: flex-end; flex-direction: row-reverse; }

.ava {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.ava.ai  { background: white; border: 1px solid #f1f5f9; }
.ava.me  { background: var(--primary-light); color: var(--primary); }
.ava.sim { background: var(--accent-light); color: var(--accent); }

.bubble {
    padding: 14px 20px;
    border-radius: 22px;
    font-size: 15px;
    line-height: 1.6;
    word-break: break-word;
    position: relative;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: transform 0.2s;
}
.bubble:hover { transform: translateY(-1px); }

.bubble.from-ai {
    background: white;
    color: #334155;
    border-bottom-left-radius: 4px;
    border: 1px solid #f1f5f9;
}
.bubble.from-me {
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: white;
    border-bottom-right-radius: 4px;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.15);
}
.bubble.from-sim {
    background: linear-gradient(135deg, #7c3aed, #9333ea);
    color: white;
    border-bottom-right-radius: 4px;
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.15);
}
.bubble.from-sys {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    border-radius: 16px;
    font-size: 13px;
    max-width: 100%;
    text-align: center;
    padding: 8px 16px;
    align-self: center;
}
.bubble.from-err {
    background: #fff1f2;
    border: 1px solid #fecaca;
    color: #be123c;
    border-radius: 16px;
    font-size: 14px;
}

.msg-footer { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.me-row .msg-footer { flex-direction: row-reverse; }
.msg-time { font-size: 11px; font-weight: 600; color: #94a3b8; }

/* Knowledge Badges */
.k-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    padding: 4px 12px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(5px);
}
.bubble.from-ai .k-badge { color: inherit; background: #f1f5f9; }

/* Input Area */
.input-area {
    padding: 24px 30px;
    background: white;
    border-top: 1px solid #f1f5f9;
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
.input-wrapper {
    flex: 1;
    position: relative;
    background: #f8fafc;
    border: 2px solid #f1f5f9;
    border-radius: 24px;
    transition: all 0.3s;
    overflow: hidden;
}
.input-wrapper:focus-within {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.1);
}

.chat-in {
    width: 100%;
    resize: none;
    border: none;
    background: transparent;
    padding: 14px 20px;
    font-size: 15px;
    line-height: 1.5;
    outline: none;
    max-height: 150px;
    min-height: 48px;
}

.send-btn {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    flex-shrink: 0;
}
.send-btn.blue   { background: linear-gradient(135deg, #2563eb, #4f46e5); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3); }
.send-btn.purple { background: linear-gradient(135deg, #7c3aed, #9333ea); box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3); }
.send-btn:hover:not(:disabled) { transform: translateY(-4px) scale(1.05); }
.send-btn:active { transform: scale(0.95); }
.send-btn:disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; cursor: not-allowed; }

/* Sidebar Styles */
.sidebar { display: flex; flex-direction: column; gap: 24px; overflow-y: auto; padding-bottom: 20px; }
.s-card {
    background: white;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
    transition: all 0.4s;
}
.s-card:hover { transform: translateY(-5px); box-shadow: 0 20px 35px rgba(0,0,0,0.06); }

.s-head {
    padding: 18px 24px;
    font-size: 12px;
    font-weight: 850;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 10px;
}

.s-body { padding: 24px; }

/* Stat Widgets */
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.stat-box {
    background: #f8fafc;
    border-radius: 20px;
    padding: 20px 15px;
    text-align: center;
    border: 1.5px solid #f1f5f9;
    transition: 0.3s;
}
.stat-box:hover { background: white; border-color: var(--primary); transform: scale(1.05); }
.stat-num { font-size: 32px; font-weight: 900; color: #1e293b; line-height: 1; margin-bottom: 8px; }
.stat-lbl { font-size: 12px; color: #64748b; font-weight: 700; }

/* Quick Action Chips */
.chips { display: flex; flex-wrap: wrap; gap: 10px; }
.chip {
    padding: 8px 16px;
    border-radius: 14px;
    background: #f1f5f9;
    border: 1.5px solid transparent;
    font-size: 13.5px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.3s;
}
.chip:hover {
    background: white;
    border-color: var(--primary);
    color: var(--primary);
    transform: translateX(3px);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.1);
}

/* Simulation Role Selection */
.role-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 10px;
}
.role-card {
    background: #f8fafc;
    border: 2px solid #f1f5f9;
    border-radius: 18px;
    padding: 14px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.role-card:hover {
    border-color: #cbd5e1;
    background: white;
    transform: translateY(-2px);
}
.role-card.on {
    background: #faf5ff;
    border-color: #d8b4fe;
    box-shadow: 0 10px 20px rgba(168, 85, 247, 0.08);
}
.role-icon {
    font-size: 24px;
    margin-bottom: 2px;
}
.role-name {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
}
.role-sub {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.role-check {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #a855f7;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    box-shadow: 0 4px 8px rgba(168, 85, 247, 0.3);
}

.s-select.modern {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    appearance: none;
    padding-right: 40px;
}

.s-select {
    width: 100%;
    padding: 12px 16px;
    border-radius: 14px;
    border: 2px solid #f1f5f9;
    font-size: 14px;
    font-weight: 650;
    outline: none;
    background: #f8fafc;
    margin-top: 8px;
    transition: 0.3s;
}
.s-select:focus { border-color: var(--secondary); background: white; }

.btn-action {
    width: 100%;
    margin-top: 16px;
    padding: 14px;
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 800;
    font-size: 13px;
    border-radius: 16px;
    border: 1.5px solid transparent;
    transition: all 0.3s;
    cursor: pointer;
}
.btn-action:hover { background: var(--primary); color: white; transform: translateY(-2px); }

.btn-danger {
    width: 100%;
    padding: 14px;
    background: #fff1f2;
    color: #e11d48;
    font-weight: 800;
    border-radius: 16px;
    border: 1.5px solid #fecaca;
    cursor: pointer;
    transition: 0.3s;
}
.btn-danger:hover { background: #e11d48; color: white; border-color: #e11d48; }

.danger-zone {
    border: 1px dashed #fda4af !important;
    background: #fffafa !important;
}
.btn-reset {
    width: 100%;
    padding: 14px;
    background: #334155;
    color: white;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.05em;
    border-radius: 16px;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-reset:hover {
    background: #e11d48;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(225, 29, 72, 0.2);
}
.s-note {
    margin-top: 12px;
    font-size: 11px;
    color: #94a3b8;
    text-align: center;
    line-height: 1.4;
    font-weight: 600;
}

/* Typing Indicator 2.0 */
.typing-container { padding: 12px 20px; background: white; border-radius: 20px; border-bottom-left-radius: 4px; display: flex; gap: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.t-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; animation: jump 1s infinite alternate; }
.t-dot:nth-child(2) { animation-delay: 0.2s; }
.t-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes jump { from { transform: translateY(0); opacity: 0.4; } to { transform: translateY(-6px); opacity: 1; } }

@media(max-width:1100px) {
    .page-layout { grid-template-columns: 1fr; height: auto; }
}
</style>


<div x-data="{
    loading: false,
    scroll(id) {
        this.$nextTick(() => {
            const el = document.getElementById(id);
            if (el) el.scrollTop = el.scrollHeight;
        });
    }
}" x-init="scroll('teach-msgs')" class="nav-container-wrapper">

    {{-- ── TABS ── --}}
    <div class="nav-container">
        <div class="chat-tabs">
            <button class="chat-tab {{ $activeTab === 'teach' ? 'active' : '' }}"
                    wire:click="$set('activeTab','teach')"
                    @click="$nextTick(() => scroll('teach-msgs'))">
                <span style="font-size:18px">🎓</span> Dạy AI Học
            </button>
            <button class="chat-tab {{ $activeTab === 'simulate' ? 'active' : '' }}"
                    wire:click="$set('activeTab','simulate')"
                    @click="$nextTick(() => scroll('sim-msgs'))">
                <span style="font-size:18px">🧪</span> Test Giả Lập
            </button>
        </div>
    </div>

    {{-- ══════════════════ TAB 1: DẠY AI ══════════════════ --}}
    @if($activeTab === 'teach')
    <div class="teach-container" style="max-width: 1000px; margin: 0 auto; animation: fadeIn 0.6s ease-out;">

        <div class="chat-card" style="height: 550px;">
            {{-- Header --}}
            <div class="chat-top teach-header">
                <div class="bot-avatar">🤖</div>
                <div class="bot-info">
                    <div class="bot-name">Flashy Intelligence</div>
                    <div class="bot-sub">Chế độ đào tạo & học hỏi trực tiếp</div>
                </div>
                <div class="status-pill"><span class="dot"></span> ONLINE</div>
            </div>


            {{-- Messages --}}
            <div class="msgs" id="teach-msgs">
                @foreach($teachMessages as $msg)
                @php $r = $msg['role']; $isMe = ($r === 'admin'); @endphp
                <div class="row {{ $isMe ? 'me-row' : 'ai-row' }}">
                    <div class="ava {{ $isMe ? 'me' : 'ai' }}">{{ $isMe ? '👤' : '🤖' }}</div>
                    <div>
                        <div class="bubble {{ $isMe ? 'from-me' : 'from-ai' }}">
                            {!! nl2br(e($msg['content'])) !!}
                            @if(!empty($msg['saved']) && $msg['saved'])
                                <div>
                                    <span class="k-badge">
                                        ✅ Đã nhớ: {{ match($msg['type'] ?? '') { 'shortcut' => 'Từ khóa', 'rule' => 'Quy tắc', default => 'Ví dụ' } }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div class="msg-footer">
                            <span class="msg-time">{{ $msg['time'] ?? '' }}</span>
                        </div>
                    </div>
                </div>
                @endforeach

                <div class="row ai-row" x-show="loading" x-cloak>
                    <div class="ava ai">🤖</div>
                    <div class="typing-container">
                        <span class="t-dot"></span>
                        <span class="t-dot"></span>
                        <span class="t-dot"></span>
                    </div>
                </div>
            </div>

            {{-- Input --}}
            <div class="input-area">
                <div class="input-wrapper">
                    <textarea class="chat-in" wire:model="teachInput"
                        placeholder="Dạy AI: 'bv là bệnh viện', 'khi khách gấp hãy ưu tiên'..."
                        rows="1"
                        @keydown.enter.prevent="if(!$event.shiftKey && $wire.teachInput.trim()){ loading=true; $wire.sendTeachMessage().then(()=>{ loading=false; scroll('teach-msgs'); }); }"
                        x-on:input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,150)+'px'"
                        :disabled="loading"
                    ></textarea>
                </div>
                <button class="send-btn blue"
                    :disabled="loading || !$wire.teachInput.trim()"
                    @click="if($wire.teachInput.trim()){ loading=true; $wire.sendTeachMessage().then(()=>{ loading=false; scroll('teach-msgs'); }) }">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════ TAB 2: GIẢ LẬP ══════════════════ --}}
    @if($activeTab === 'simulate')
    <div class="page-layout animate-slide-up">
        <div class="chat-card sim-theme">
            {{-- Header --}}
            <div class="chat-top sim-header">
                <div class="bot-avatar sim">🧪</div>
                <div class="bot-info">
                    <div class="bot-name">AI Sandbox — Giả lập Zalo</div>
                    <div class="bot-sub">{{ $senderMode === 'shop' ? 'Vai trò: Đối tác Shop' : 'Vai trò: Khách lẻ' }}</div>
                </div>
                <div class="status-pill"><span class="dot" style="background:#f472b6; box-shadow:0 0 10px #f472b6"></span> DEBUG MODE</div>
            </div>

            {{-- Messages --}}
            <div class="msgs" id="sim-msgs" x-init="scroll('sim-msgs')">
                @foreach($simMessages as $msg)
                @php $r = $msg['role']; @endphp
                <div class="row {{ match($r){ 'user'=>'me-row','assistant'=>'ai-row',default=>'sys-row' } }}">
                    @if($r !== 'system')
                        <div class="ava {{ $r==='user'?'sim':'ai' }}">{{ $r==='user'?'👤':'🤖' }}</div>
                    @endif
                    <div class="msg-wrapper">
                        <div class="bubble {{ match($r){ 'user'=>'from-sim','assistant'=>'from-ai','error'=>'from-err',default=>'from-sys' } }}">
                            {!! nl2br(e($msg['content'])) !!}
                            
                            @if(!empty($msg['tool']))
                                <div class="tool-call-box">
                                    <div class="tool-header">
                                        <span class="tool-icon">⚡</span> EXECUTED TOOL
                                    </div>
                                    <div class="tool-name">{{ $msg['tool'] }}</div>
                                </div>
                            @endif
                        </div>
                        @if($r !== 'system')
                            <div class="msg-footer">
                                <span class="msg-time">{{ $msg['time'] ?? '' }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                @endforeach

                <div class="row ai-row" x-show="loading" x-cloak>
                    <div class="ava ai">🤖</div>
                    <div class="typing-container sim">
                        <span class="t-dot"></span>
                        <span class="t-dot"></span>
                        <span class="t-dot"></span>
                    </div>
                </div>
            </div>

            {{-- Input --}}
            <div class="input-area">
                <div class="input-wrapper sim-input">
                    <textarea class="chat-in" wire:model="simInput"
                        placeholder="Giả định tin nhắn khách hàng: 'Đơn này ship chưa shop?'..."
                        rows="1"
                        @keydown.enter.prevent="if(!$event.shiftKey && $wire.simInput.trim()){ loading=true; $wire.sendSimMessage().then(()=>{ loading=false; scroll('sim-msgs'); }); }"
                        x-on:input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,150)+'px'"
                        :disabled="loading"
                    ></textarea>
                </div>
                <button class="send-btn purple"
                    :disabled="loading || !$wire.simInput.trim()"
                    @click="if($wire.simInput.trim()){ loading=true; $wire.sendSimMessage().then(()=>{ loading=false; scroll('sim-msgs'); }) }">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="sidebar">
            <div class="s-card config-theme">
                <div class="s-head">⚙️ Cấu hình giả lập</div>
                <div class="s-body">
                    <div class="config-label">VAI TRÒ NGƯỜI GỬI</div>
                    <div class="role-grid">
                        <div class="role-card {{ $senderMode==='shop'?'on':'' }}" wire:click="$set('senderMode','shop')">
                            <div class="role-icon">🏪</div>
                            <div class="role-info">
                                <div class="role-name">Shop</div>
                                <div class="role-sub">Đối tác Shop</div>
                            </div>
                            @if($senderMode==='shop') <div class="role-check">✓</div> @endif
                        </div>
                        <div class="role-card {{ $senderMode==='retail'?'on':'' }}" wire:click="$set('senderMode','retail')">
                            <div class="role-icon">👤</div>
                            <div class="role-info">
                                <div class="role-name">Khách</div>
                                <div class="role-sub">Người dùng lẻ</div>
                            </div>
                            @if($senderMode==='retail') <div class="role-check">✓</div> @endif
                        </div>
                    </div>

                    @if($senderMode === 'shop')
                    <div style="margin-top:24px">
                        <div class="config-label">SHOP MỤC TIÊU</div>
                        <div class="custom-select-wrapper">
                            <select class="s-select modern" wire:model.live="selectedShopId">
                                <option value="0">🌐 -- Chọn Shop test --</option>
                                @foreach($this->shops as $shop)
                                    <option value="{{ $shop->id }}">🏛️ {{ $shop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
            </div>


            <div class="s-card danger-zone">
                <div class="s-head" style="color:#e11d48">🛠️ Hệ thống</div>
                <div class="s-body">
                    <button class="btn-reset" wire:click="clearSimSession" wire:confirm="Hành động này sẽ xóa toàn bộ nội dung chat giả lập. Bạn chắc chắn chứ?">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                        LÀM MỚI PHIÊN TEST
                    </button>
                    <p class="s-note">Dọn dẹp bộ nhớ đệm và lịch sử hội thoại để bắt đầu kịch bản thử nghiệm mới.</p>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

<script>
    function scroll(id) {
        setTimeout(() => {
            const el = document.getElementById(id);
            if (el) el.scrollTop = el.scrollHeight;
        }, 120);
    }
    document.addEventListener('livewire:update', () => {
        ['teach-msgs','sim-msgs'].forEach(id => scroll(id));
    });
</script>
</x-filament-panels::page>
