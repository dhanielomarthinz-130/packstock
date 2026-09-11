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

$pageTitle = "IMS";
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 relative overflow-hidden font-sans select-none" style="background: radial-gradient(circle at 10% 20%, #f0f3ff 0%, #e8edff 45%, #f4f6fb 90%);">
  
  <!-- Atmospheric Glowing Ambient Orbs -->
  <div class="absolute -top-40 -left-40 w-[550px] h-[550px] bg-[#5147E6]/15 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute -bottom-40 -right-40 w-[550px] h-[550px] bg-[#634DE9]/12 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[750px] h-[750px] bg-[#584CE7]/10 rounded-full blur-[140px] pointer-events-none"></div>

  <!-- Background Grid Pattern in Soft Indigo Tint -->
  <div class="absolute inset-0 bg-[linear-gradient(to_right,#5147e612_1px,transparent_1px),linear-gradient(to_bottom,#5147e612_1px,transparent_1px)] bg-[size:36px_36px] pointer-events-none"></div>

  <!-- MAIN LOGIN CARD (PREMIUM DUAL-PANE ENTERPRISE SUITE) -->
  <div class="w-full max-w-[880px] grid grid-cols-1 lg:grid-cols-12 rounded-[32px] sm:rounded-[36px] bg-white shadow-2xl shadow-[#5147E6]/20 overflow-hidden relative z-10 border border-indigo-100/90">
    
    <!-- ========================================================================= -->
    <!-- LEFT PANEL: BRANDING (VIBRANT ROYAL INDIGO THEME) -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-5 p-6 sm:p-8 flex flex-col justify-between relative overflow-hidden border-b lg:border-b-0 lg:border-r border-white/10" style="background: linear-gradient(180deg, #5147E6 0%, #584CE7 50%, #634DE9 100%);">
      
      <!-- Subtle Ambient Glow inside Panel -->
      <div class="absolute -right-12 -top-12 w-44 h-44 bg-white/20 rounded-full blur-2xl pointer-events-none"></div>
      <div class="absolute -left-12 -bottom-12 w-44 h-44 bg-indigo-300/25 rounded-full blur-2xl pointer-events-none"></div>

      <!-- Brand Header -->
      <div class="space-y-4 relative z-10">
        <!-- Top Status Pill -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 border border-white/30 shadow-xs backdrop-blur-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-[10px] font-black tracking-wider uppercase" style="color: #ffffff;">IMS</span>
        </div>

        <!-- Logo & Title -->
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-white border border-white/40 p-1.5 shadow-xl shadow-black/25 flex-shrink-0 flex items-center justify-center overflow-hidden ring-2 ring-white/10">
            <img src="<?= (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') ?>/assets/img/logo-IEG.png" alt="IEG Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <div class="flex items-center gap-2 leading-none">
              <h1 class="text-2xl font-black leading-tight" style="color: #ffffff; letter-spacing: 0.22em;">IMS</h1>
            </div>
            <p class="text-[11px] font-bold tracking-wide mt-1" style="color: rgba(255, 255, 255, 0.92);">Inventory Management System</p>
          </div>
        </div>

        <p class="text-xs leading-relaxed pt-1.5 font-normal" style="color: rgba(255, 255, 255, 0.92);">
          Sistem manajemen persediaan Stock Kemas / Gimmick / Consumable terpadu dengan sinkronisasi mutasi real-time dan penugasan PIC.
        </p>
      </div>

      <!-- Feature Highlight List -->
      <div class="my-5 space-y-2.5 relative z-10">
        
        <div class="flex items-center gap-3 p-2.5 rounded-2xl bg-white/[0.12] border border-white/20 hover:bg-white/[0.18] transition-all shadow-2xs">
          <div class="w-8 h-8 rounded-xl bg-white/25 text-white flex items-center justify-center shrink-0" style="color: #ffffff;">
            <span class="material-symbols-outlined text-[18px]">sync_alt</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs truncate" style="color: #ffffff;">Real-Time Stock Mutation</h4>
            <p class="text-[10.5px] truncate" style="color: rgba(255, 255, 255, 0.85);">Pelacakan stok & pemotongan otomatis</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-2xl bg-white/[0.12] border border-white/20 hover:bg-white/[0.18] transition-all shadow-2xs">
          <div class="w-8 h-8 rounded-xl bg-white/25 text-white flex items-center justify-center shrink-0" style="color: #ffffff;">
            <span class="material-symbols-outlined text-[18px]">checklist</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs truncate" style="color: #ffffff;">Dynamic Count & Opname</h4>
            <p class="text-[10.5px] truncate" style="color: rgba(255, 255, 255, 0.85);">Hitung fisik akurat tanpa bias sistem</p>
          </div>
        </div>

        <div class="flex items-center gap-3 p-2.5 rounded-2xl bg-white/[0.12] border border-white/20 hover:bg-white/[0.18] transition-all shadow-2xs">
          <div class="w-8 h-8 rounded-xl bg-white/25 text-white flex items-center justify-center shrink-0" style="color: #ffffff;">
            <span class="material-symbols-outlined text-[18px]">assignment_turned_in</span>
          </div>
          <div class="min-w-0">
            <h4 class="font-bold text-xs truncate" style="color: #ffffff;">Picking Task Dispatch</h4>
            <p class="text-[10.5px] truncate" style="color: rgba(255, 255, 255, 0.85);">Serah terima Stock Kemas / Gimmick / Consumable</p>
          </div>
        </div>

      </div>

      <!-- Bottom Security & Version Badge -->
      <div class="pt-3 border-t border-white/20 flex items-center justify-between text-[10.5px] relative z-10" style="color: rgba(255, 255, 255, 0.85);">
        <span class="flex items-center gap-1.5 font-semibold" style="color: #ffffff;">
          <span class="material-symbols-outlined text-[14px]">lock</span>
          <span>SSL 256-bit Encrypted</span>
        </span>
        <span class="font-mono" style="color: rgba(255, 255, 255, 0.9);">2026 Edition</span>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- RIGHT PANEL: AUTHENTICATION FORM (EXECUTIVE WHITE WITH ROYAL INDIGO ACCENT) -->
    <!-- ========================================================================= -->
    <div class="lg:col-span-7 bg-white p-6 sm:p-8 lg:p-10 flex flex-col justify-between">
      
      <!-- Top Form Header -->
      <div>
        <div class="border-b border-slate-100 pb-4 mb-6 flex items-center gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200/80 p-1.5 shadow-xs flex items-center justify-center shrink-0">
            <img src="<?= (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') ?>/assets/img/logo-IEG.png" alt="IEG Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <h2 class="text-2xl font-black text-[#5147E6] tracking-tight uppercase leading-tight">IMS LOGIN</h2>
            <p class="text-[11.5px] font-semibold" style="color: #4f46e5;">Masuk untuk mengelola stok & operasional</p>
          </div>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
          <!-- Session Timeout Banner Notification -->
          <div class="mb-5 p-3.5 bg-amber-50 border border-amber-300 rounded-2xl text-xs text-amber-900 flex items-start gap-3 shadow-xs animate-fade-in">
            <span class="material-symbols-outlined text-amber-600 text-[22px] shrink-0 mt-0.5">timer_off</span>
            <div>
              <span class="font-black text-amber-950 block text-xs">Sesi Berakhir (Inactivity Timeout)</span>
              <span class="text-[11px] text-amber-800 leading-tight block mt-0.5">
                Sistem otomatis keluar demi keamanan akun karena tidak ada aktivitas selama 1 jam. Silakan masukkan kredensial untuk masuk kembali.
              </span>
            </div>
          </div>
        <?php endif; ?>

        <!-- Form Elements -->
        <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-4">
          
          <!-- Username Input -->
          <div>
            <label class="block text-xs font-black mb-1.5" style="color: #272466;">
              Username <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none" style="color: #5147E6;">
                <span class="material-symbols-outlined text-[19px]">account_circle</span>
              </span>
              <input type="text" id="username" required placeholder="Masukkan username akun Anda..." autocomplete="username"
                class="w-full pl-10 pr-4 py-3 bg-slate-50/90 border border-indigo-200/80 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#5147E6] focus:ring-4 focus:ring-[#5147E6]/15 transition-all placeholder:text-slate-400 placeholder:font-normal shadow-2xs" style="color: #272466;">
            </div>
          </div>

          <!-- Password Input with Show/Hide Eye Toggle -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-black" style="color: #272466;">
                Password <span class="text-rose-500">*</span>
              </label>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none" style="color: #5147E6;">
                <span class="material-symbols-outlined text-[19px]">lock</span>
              </span>
              <input type="password" id="password" required placeholder="Masukkan kata sandi..." autocomplete="current-password"
                class="w-full pl-10 pr-11 py-3 bg-slate-50/90 border border-indigo-200/80 rounded-xl text-xs font-bold outline-none focus:bg-white focus:border-[#5147E6] focus:ring-4 focus:ring-[#5147E6]/15 transition-all placeholder:text-slate-400 placeholder:font-normal shadow-2xs" style="color: #272466;">
              
              <button type="button" onclick="togglePasswordVisibility()" title="Tampilkan / Sembunyikan Password" 
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center hover:opacity-80 transition-colors cursor-pointer" style="color: #5147E6;">
                <span id="iconTogglePass" class="material-symbols-outlined text-[19px]">visibility</span>
              </button>
            </div>
          </div>

          <!-- Security Notice & Remember Session -->
          <div class="flex items-center justify-between pt-0.5 text-xs">
            <label class="flex items-center gap-2 font-bold cursor-pointer" style="color: #272466;">
              <input type="checkbox" id="rememberMe" checked class="w-3.5 h-3.5 rounded border-indigo-300 text-[#5147E6] focus:ring-[#5147E6]">
              <span class="text-[11.5px]">Ingat Sesi Login</span>
            </label>
            <span class="text-[11px] font-semibold flex items-center gap-1" style="color: #4f46e5;">
              <span class="material-symbols-outlined text-[13px] text-emerald-600">verified</span>
              <span>Sesi Terisolasi</span>
            </span>
          </div>

          <!-- Error Alert Banner -->
          <div id="loginAlert" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-center gap-2.5 animate-shake">
            <span class="material-symbols-outlined text-rose-600 text-[20px] shrink-0">error</span>
            <span id="loginAlertText" class="font-semibold">Username atau kata sandi yang Anda masukkan salah!</span>
          </div>

          <!-- Submit Button in Vibrant Royal Indigo Gradient -->
          <div class="pt-2">
            <button type="submit" id="btnSubmit" 
              class="w-full py-3.5 px-5 text-white font-black text-xs rounded-xl shadow-lg shadow-[#5147E6]/30 hover:shadow-[#5147E6]/50 hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer"
              style="background: linear-gradient(135deg, #5147E6 0%, #584CE7 50%, #634DE9 100%);">
              <span>IMS Login</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
          </div>

        </form>
      </div>

      <!-- Footer Branding & Copyright -->
      <div class="pt-6 mt-4 border-t border-slate-100 text-center space-y-1">
        <p class="text-[11px] font-semibold" style="color: #64748b;">
          IMS &copy; <?= date('Y') ?>
        </p>
        <p class="text-[10px] font-bold tracking-wide" style="color: #64748b;">
          Powered By <span class="text-[#5147E6] font-black">Dhanielo_Marthinz IMS</span>
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
