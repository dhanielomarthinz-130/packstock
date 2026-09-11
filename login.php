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

$pageTitle = "Login Portal - IMS (Inventory Management System)";
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-4 sm:p-6 relative overflow-hidden font-sans select-none">
  
  <!-- Atmospheric Glowing Ambient Orbs -->
  <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none animate-pulse"></div>
  <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-purple-600/15 rounded-full blur-[120px] pointer-events-none animate-pulse"></div>
  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-indigo-500/10 rounded-full blur-[140px] pointer-events-none"></div>

  <!-- Background Grid Pattern -->
  <div class="absolute inset-0 bg-[linear-gradient(to_right,#33415515_1px,transparent_1px),linear-gradient(to_bottom,#33415515_1px,transparent_1px)] bg-[size:32px_32px] pointer-events-none"></div>

  <!-- MAIN LOGIN CARD (THEMED LIKE SIDEBAR) -->
  <div class="w-full max-w-[880px] grid grid-cols-1 lg:grid-cols-12 rounded-[28px] sm:rounded-[36px] shadow-2xl shadow-black/60 overflow-hidden relative z-10 border border-white/15" style="background: linear-gradient(180deg, #272466 0%, #29266B 25%, #2C2971 50%, #2E2B78 75%, #302E81 100%);">
    
    <!-- ========================================================================= -->
    <!-- LEFT PANEL: BRANDING & SYSTEM VALUE PROPOSITION -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-5 bg-black/15 backdrop-blur-xs p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden border-b lg:border-b-0 lg:border-r border-white/10">
      
      <!-- Subtle Ambient Glow inside Panel -->
      <div class="absolute -right-12 -top-12 w-44 h-44 bg-indigo-400/20 rounded-full blur-2xl pointer-events-none"></div>
      <div class="absolute -left-12 -bottom-12 w-44 h-44 bg-purple-500/15 rounded-full blur-2xl pointer-events-none"></div>

      <!-- Brand Header -->
      <div class="space-y-4 relative z-10">
        <!-- Top Status Pill -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20 shadow-xs backdrop-blur-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-[10px] font-black tracking-wider uppercase text-indigo-100">Enterprise WMS v2.4</span>
        </div>

        <!-- Logo & Title -->
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-white border border-white/30 p-1.5 shadow-lg shadow-black/30 flex-shrink-0 flex items-center justify-center overflow-hidden">
            <img src="<?= (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') ?>/assets/img/logo-IEG.png" alt="IEG Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <div class="flex items-center gap-2 leading-none">
              <h1 class="text-2xl font-black tracking-tight text-white leading-tight">IMS</h1>
              <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/20 text-indigo-100 border border-white/25">IEG</span>
            </div>
            <p class="text-[10.5px] text-indigo-200/90 font-medium tracking-wide mt-1">Inventory Management System</p>
          </div>
        </div>

        <p class="text-xs text-indigo-100/80 leading-relaxed pt-1.5 font-normal">
          Sistem manajemen persediaan Stock Kemas / Consumable terpadu dengan sinkronisasi mutasi real-time dan penugasan PIC.
        </p>
      </div>

      <!-- Feature Highlight List -->
      <div class="my-5 space-y-2.5 relative z-10">
        
        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.06] border border-white/10 hover:bg-white/10 transition-colors">
          <div class="w-8 h-8 rounded-lg bg-white/15 text-indigo-200 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">sync_alt</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Real-Time Stock Mutation</h4>
            <p class="text-[10px] text-indigo-200/70 truncate">Pelacakan stok & pemotongan otomatis</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.06] border border-white/10 hover:bg-white/10 transition-colors">
          <div class="w-8 h-8 rounded-lg bg-white/15 text-indigo-200 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">checklist</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Dynamic Count & Opname</h4>
            <p class="text-[10px] text-indigo-200/70 truncate">Hitung fisik akurat tanpa bias sistem</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-white/[0.06] border border-white/10 hover:bg-white/10 transition-colors">
          <div class="w-8 h-8 rounded-lg bg-white/15 text-indigo-200 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">assignment_turned_in</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs text-white truncate">Picking Task Dispatch</h4>
            <p class="text-[10px] text-indigo-200/70 truncate">Serah terima Stock Kemas / Consumable</p>
          </div>
        </div>

      </div>

      <!-- Bottom Security & Version Badge -->
      <div class="pt-3 border-t border-white/10 flex items-center justify-between text-[10px] text-indigo-200/70 relative z-10">
        <span class="flex items-center gap-1.5 text-indigo-200 font-semibold">
          <span class="material-symbols-outlined text-[14px]">lock</span>
          <span>SSL 256-bit Encrypted</span>
        </span>
        <span class="font-mono text-indigo-300/80">2026 Edition</span>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- RIGHT PANEL: AUTHENTICATION FORM -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-7 bg-white/[0.04] p-6 sm:p-8 lg:p-10 flex flex-col justify-between backdrop-blur-md">
      
      <!-- Top Form Header -->
      <div>
        <div class="border-b border-white/10 pb-4 mb-6 flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-white p-1 shadow-md flex items-center justify-center shrink-0 ring-1 ring-white/30">
            <img src="<?= (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') ?>/assets/img/logo-IEG.png" alt="IEG Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <h2 class="text-2xl font-black text-white tracking-tight uppercase leading-tight">Login Portal</h2>
            <p class="text-[11px] text-indigo-200/80 font-medium">Masuk untuk mengelola stok & operasional</p>
          </div>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
          <!-- Session Timeout Banner Notification -->
          <div class="mb-5 p-3.5 bg-amber-500/20 border border-amber-400/40 rounded-2xl text-xs text-amber-100 flex items-start gap-3 shadow-xs animate-fade-in">
            <span class="material-symbols-outlined text-amber-300 text-[22px] shrink-0 mt-0.5">timer_off</span>
            <div>
              <span class="font-black text-amber-100 block text-xs">Sesi Berakhir (Inactivity Timeout)</span>
              <span class="text-[11px] text-amber-200/90 leading-tight block mt-0.5">
                Sistem otomatis keluar demi keamanan akun karena tidak ada aktivitas selama 1 jam. Silakan masukkan kredensial untuk masuk kembali.
              </span>
            </div>
          </div>
        <?php endif; ?>

        <!-- Form Elements -->
        <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-4">
          
          <!-- Username Input -->
          <div>
            <label class="block text-xs font-bold text-indigo-100 mb-1.5">
              Username <span class="text-rose-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-indigo-300/70 pointer-events-none">
                <span class="material-symbols-outlined text-[19px]">account_circle</span>
              </span>
              <input type="text" id="username" required placeholder="Masukkan username akun Anda..." autocomplete="username"
                class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-xs font-bold text-white outline-none focus:bg-white/15 focus:border-indigo-300 focus:ring-4 focus:ring-indigo-400/20 transition-all placeholder:text-indigo-200/50 placeholder:font-normal shadow-inner">
            </div>
          </div>

          <!-- Password Input with Show/Hide Eye Toggle -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-indigo-100">
                Password <span class="text-rose-400">*</span>
              </label>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-indigo-300/70 pointer-events-none">
                <span class="material-symbols-outlined text-[19px]">lock</span>
              </span>
              <input type="password" id="password" required placeholder="Masukkan kata sandi..." autocomplete="current-password"
                class="w-full pl-10 pr-11 py-3 bg-white/10 border border-white/20 rounded-xl text-xs font-bold text-white outline-none focus:bg-white/15 focus:border-indigo-300 focus:ring-4 focus:ring-indigo-400/20 transition-all placeholder:text-indigo-200/50 placeholder:font-normal shadow-inner">
              
              <button type="button" onclick="togglePasswordVisibility()" title="Tampilkan / Sembunyikan Password" 
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-indigo-300/70 hover:text-white transition-colors cursor-pointer">
                <span id="iconTogglePass" class="material-symbols-outlined text-[19px]">visibility</span>
              </button>
            </div>
          </div>

          <!-- Security Notice & Remember Session -->
          <div class="flex items-center justify-between pt-0.5 text-xs">
            <label class="flex items-center gap-2 text-indigo-200 font-medium cursor-pointer">
              <input type="checkbox" id="rememberMe" checked class="w-3.5 h-3.5 rounded border-white/30 bg-white/10 text-indigo-500 focus:ring-indigo-400">
              <span class="text-[11px]">Ingat Sesi Login</span>
            </label>
            <span class="text-[11px] text-indigo-300/80 flex items-center gap-1">
              <span class="material-symbols-outlined text-[13px] text-emerald-400">verified</span>
              <span>Sesi Terisolasi</span>
            </span>
          </div>

          <!-- Error Alert Banner -->
          <div id="loginAlert" class="hidden p-3 bg-rose-500/20 border border-rose-400/40 rounded-xl text-xs text-rose-100 flex items-center gap-2.5 animate-shake">
            <span class="material-symbols-outlined text-rose-300 text-[20px] shrink-0">error</span>
            <span id="loginAlertText" class="font-semibold">Username atau kata sandi yang Anda masukkan salah!</span>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button type="submit" id="btnSubmit" 
              class="w-full py-3.5 px-5 bg-white hover:bg-indigo-50 active:scale-[0.98] text-[#272466] font-black text-xs rounded-xl shadow-xl shadow-black/30 hover:shadow-black/50 transition-all flex items-center justify-center gap-2 cursor-pointer">
              <span>Masuk Sekarang (Login)</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
          </div>

        </form>
      </div>

      <!-- Footer Branding & Copyright -->
      <div class="pt-6 mt-4 border-t border-white/10 text-center space-y-1">
        <p class="text-[11px] text-indigo-200/70 font-medium">
          IMS &bull; Inventory Management System &copy; <?= date('Y') ?>
        </p>
        <p class="text-[10px] text-indigo-200/50 font-semibold tracking-wide">
          Powered By <span class="text-indigo-300 font-bold">IEG IMS</span>
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
    btn.innerHTML = '<span>Login</span><span class="material-symbols-outlined text-[18px]">arrow_forward</span>';

    if (res.success) {
      App.toast('Login berhasil. Mengalihkan...', 'success');
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
