<?php
// login.php - Ultra-Premium Next-Gen Warehouse Management Login Portal
require_once __DIR__ . '/includes/auth.php';

if (Auth::check()) {
    if (Auth::isAdmin()) {
        header("Location: admin/");
        exit;
    } else {
        header("Location: operator/");
        exit;
    }
}

$pageTitle = "Login Portal - PackStock WMS Enterprise";
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-4 sm:p-6 relative overflow-hidden font-sans select-none">
  
  <!-- Atmospheric Glowing Ambient Orbs -->
  <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-emerald-500/15 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
  <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-teal-500/15 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-emerald-600/5 rounded-full blur-[120px] pointer-events-none"></div>

  <!-- Background Grid Pattern -->
  <div class="absolute inset-0 bg-[linear-gradient(to_right,#33415515_1px,transparent_1px),linear-gradient(to_bottom,#33415515_1px,transparent_1px)] bg-[size:32px_32px] pointer-events-none"></div>

  <!-- MAIN LOGIN CARD (BALANCED & SLEEK PROPORTIONS) -->
  <div class="w-full max-w-[880px] grid grid-cols-1 lg:grid-cols-12 rounded-[28px] sm:rounded-[36px] bg-slate-900/90 backdrop-blur-2xl border border-slate-800/90 shadow-2xl shadow-emerald-950/60 overflow-hidden relative z-10">
    
    <!-- ========================================================================= -->
    <!-- LEFT PANEL: BRANDING & SYSTEM VALUE PROPOSITION -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-5 bg-gradient-to-br from-emerald-900 via-emerald-950 to-slate-950 p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden border-b lg:border-b-0 lg:border-r border-emerald-600/20">
      
      <!-- Subtle Ambient Glow inside Panel -->
      <div class="absolute -right-12 -top-12 w-44 h-44 bg-emerald-500/20 rounded-full blur-2xl pointer-events-none"></div>
      <div class="absolute -left-12 -bottom-12 w-44 h-44 bg-teal-500/15 rounded-full blur-2xl pointer-events-none"></div>

      <!-- Brand Header -->
      <div class="space-y-4 relative z-10">
        <!-- Top Status Pill -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/80 border border-emerald-500/40 shadow-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-[10px] font-black tracking-wider uppercase text-emerald-200">Enterprise WMS v2.4</span>
        </div>

        <!-- Logo & Title -->
        <div class="flex items-center gap-3">
          <!-- Stylized "D" Monogram Logo Badge -->
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 via-emerald-600 to-teal-800 p-0.5 shadow-lg shadow-emerald-600/30 flex-shrink-0 flex items-center justify-center">
            <div class="w-full h-full rounded-[14px] bg-slate-950 flex items-center justify-center">
              <span class="font-black text-2xl tracking-tighter bg-gradient-to-br from-white via-emerald-200 to-emerald-400 bg-clip-text text-transparent">D</span>
            </div>
          </div>
          <div>
            <h1 class="text-xl font-black tracking-tight text-white leading-none">PackStock</h1>
            <p class="text-[11px] text-emerald-300/80 font-semibold mt-1">Stock Kemas Control & Dispatch</p>
          </div>
        </div>

        <p class="text-xs text-slate-300 leading-relaxed pt-1">
          Sistem manajemen persediaan Stock Kemas terpadu dengan sinkronisasi mutasi real-time dan penugasan PIC.
        </p>
      </div>

      <!-- Feature Highlight List -->
      <div class="my-5 space-y-2.5 relative z-10">
        
        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.07] transition-colors">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">sync_alt</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Real-Time Stock Mutation</h4>
            <p class="text-[10px] text-slate-400 truncate">Pelacakan stok & pemotongan otomatis</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.07] transition-colors">
          <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">checklist</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Dynamic Count & Opname</h4>
            <p class="text-[10px] text-slate-400 truncate">Hitung fisik akurat tanpa bias sistem</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.04] border border-white/[0.08] hover:bg-white/[0.07] transition-colors">
          <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-300 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">assignment_turned_in</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Picking Task Dispatch</h4>
            <p class="text-[10px] text-slate-400 truncate">Serah terima Stock Kemas ke line produksi</p>
          </div>
        </div>

      </div>

      <!-- Bottom Security & Version Badge -->
      <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-[10px] text-slate-400 relative z-10">
        <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
          <span class="material-symbols-outlined text-[14px]">lock</span>
          <span>SSL 256-bit Encrypted</span>
        </span>
        <span class="font-mono text-slate-500">2026 Edition</span>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- RIGHT PANEL: AUTHENTICATION FORM -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-7 bg-white p-6 sm:p-8 lg:p-10 flex flex-col justify-between">
      
      <!-- Top Form Header -->
      <div>
        <div class="border-b border-slate-100 pb-3 mb-6 flex items-center gap-3.5">
          <img src="/assets/img/favicon.svg" alt="Logo" class="w-9 h-9 flex-shrink-0">
          <h2 class="text-3xl font-black text-slate-900 tracking-tight uppercase">Login</h2>
        </div>

        <!-- Form Elements -->
        <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-4">
          
          <!-- Username Input -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Username <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[19px]">account_circle</span>
              </span>
              <input type="text" id="username" required placeholder="Masukkan username akun Anda..." autocomplete="username"
                class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:bg-white focus:border-emerald-600 focus:ring-4 focus:ring-emerald-500/10 transition-all placeholder:text-slate-400 placeholder:font-normal shadow-2xs">
            </div>
          </div>

          <!-- Password Input with Show/Hide Eye Toggle -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-700">
                Password <span class="text-rose-500">*</span>
              </label>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[19px]">lock</span>
              </span>
              <input type="password" id="password" required placeholder="Masukkan kata sandi..." autocomplete="current-password"
                class="w-full pl-10 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:bg-white focus:border-emerald-600 focus:ring-4 focus:ring-emerald-500/10 transition-all placeholder:text-slate-400 placeholder:font-normal shadow-2xs">
              
              <button type="button" onclick="togglePasswordVisibility()" title="Tampilkan / Sembunyikan Password" 
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 transition-colors cursor-pointer">
                <span id="iconTogglePass" class="material-symbols-outlined text-[19px]">visibility</span>
              </button>
            </div>
          </div>

          <!-- Security Notice & Remember Session -->
          <div class="flex items-center justify-between pt-0.5 text-xs">
            <label class="flex items-center gap-2 text-slate-600 font-medium cursor-pointer">
              <input type="checkbox" id="rememberMe" checked class="w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
              <span class="text-[11px]">Ingat Sesi Login</span>
            </label>
            <span class="text-[11px] text-slate-400 flex items-center gap-1">
              <span class="material-symbols-outlined text-[13px] text-emerald-600">verified</span>
              <span>Sesi Terisolasi</span>
            </span>
          </div>

          <!-- Error Alert Banner -->
          <div id="loginAlert" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-center gap-2.5 animate-shake">
            <span class="material-symbols-outlined text-rose-600 text-[20px] shrink-0">error</span>
            <span id="loginAlertText" class="font-semibold">Username atau kata sandi yang Anda masukkan salah!</span>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button type="submit" id="btnSubmit" 
              class="w-full py-3.5 px-5 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 hover:from-emerald-700 hover:to-teal-800 active:scale-[0.99] text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-700/25 hover:shadow-emerald-700/40 transition-all flex items-center justify-center gap-2 cursor-pointer">
              <span>Masuk ke Sistem PackStock</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
          </div>

        </form>
      </div>

      <!-- Footer Branding & Copyright -->
      <div class="pt-6 mt-4 border-t border-slate-100 text-center">
        <p class="text-[11px] text-slate-400 font-medium">
          PackStock WMS &bull; Enterprise Stock Control Panel &copy; <?= date('Y') ?>
        </p>
      </div>

    </div>

  </div>
</div>

<script>
  function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.getElementById('iconTogglePass');
    if (!input || !icon) return;

    if (input.type === 'password') {
      input.type = 'text';
      icon.innerText = 'visibility_off';
    } else {
      input.type = 'password';
      icon.innerText = 'visibility';
    }
  }

  async function handleLoginSubmit(e) {
    e.preventDefault();
    const u = document.getElementById('username').value.trim();
    const p = document.getElementById('password').value.trim();
    const btn = document.getElementById('btnSubmit');
    const alertBox = document.getElementById('loginAlert');
    const alertText = document.getElementById('loginAlertText');
    const passInput = document.getElementById('password');

    alertBox.classList.add('hidden');
    passInput.classList.remove('border-rose-500', 'bg-rose-50/30');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Memverifikasi Kredensial...';

    const res = await App.fetchJson('api/auth.php?action=login', {
      method: 'POST',
      body: JSON.stringify({ username: u, password: p })
    });

    btn.disabled = false;
    btn.innerHTML = '<span>Masuk ke Sistem PackStock</span><span class="material-symbols-outlined text-[18px]">arrow_forward</span>';

    if (res.success) {
      App.toast(`Login berhasil. Selamat datang, ${res.user.name}`, 'success');
      setTimeout(() => {
        window.location.href = res.redirect;
      }, 350);
    } else {
      alertText.innerText = res.message || 'Username atau kata sandi yang Anda masukkan salah!';
      alertBox.classList.remove('hidden');
      passInput.classList.add('border-rose-500', 'bg-rose-50/30');
      passInput.focus();
      App.toast(res.message || 'Login gagal. Periksa kembali kredensial Anda.', 'error');
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
