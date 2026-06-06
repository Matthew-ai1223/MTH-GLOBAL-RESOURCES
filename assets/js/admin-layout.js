/**
 * MTH GLOBAL RESOURCES — Admin Sidebar Layout
 * Completely separate from the customer navbar (layout.js)
 */

const ADMIN_NAV = [
  { group: 'Overview' },
  { label: 'Dashboard',  href: 'dashboard.html',       icon: 'fa-gauge-high' },
  { label: 'Reports',    href: 'reports.html',          icon: 'fa-chart-line' },
  { group: 'Store' },
  { label: 'Products',   href: 'products.html',         icon: 'fa-bottle-water' },
  { label: 'Orders',     href: 'orders.html',           icon: 'fa-clipboard-list' },
  { label: 'Users',      href: 'users.html',            icon: 'fa-users' },
  { group: 'Operations' },
  { label: 'Inventory',  href: 'inventory.html',        icon: 'fa-warehouse' },
  { label: 'Tasks',      href: 'tasks.html',            icon: 'fa-list-check' },
  { group: 'Finance' },
  { label: 'Expenses & Income', href: 'expenses-income.html', icon: 'fa-money-bill-trend-up' },
  { group: 'System' },
  { label: 'AI Assistant', href: 'ai-assistant.html', icon: 'fa-robot' },
  { label: 'Notifications', href: 'notifications.html', icon: 'fa-bell' },
];

function injectAdminStyles() {
  if (document.getElementById('admin-layout-css')) return;
  const style = document.createElement('style');
  style.id = 'admin-layout-css';
  style.textContent = `
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body.admin-body {
      display: flex;
      min-height: 100vh;
      background: #f0f2f5;
      font-family: 'Inter', sans-serif;
    }

    /* ── Sidebar ── */
    .admin-sidebar {
      position: fixed;
      top: 0; left: 0;
      width: 260px;
      height: 100vh;
      background: #0d1117;
      display: flex;
      flex-direction: column;
      z-index: 500;
      transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
      overflow-y: auto;
    }
    .admin-sidebar::-webkit-scrollbar { width: 4px; }
    .admin-sidebar::-webkit-scrollbar-thumb { background: #2d2d2d; border-radius: 4px; }

    .admin-sidebar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1.5rem 1.25rem;
      border-bottom: 1px solid #1e2227;
      text-decoration: none;
    }
    .admin-sidebar-brand .logo-icon {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, #1e3a8a, #3b82f6);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: white; font-size: 1.1rem; flex-shrink: 0;
    }
    .admin-sidebar-brand .logo-text { display: flex; flex-direction: column; }
    .admin-sidebar-brand .logo-text strong { color: #f9fafb; font-size: 0.9rem; font-weight: 800; letter-spacing: -0.02em; }
    .admin-sidebar-brand .logo-text span { color: #38bdf8; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; }

    .admin-nav { flex: 1; padding: 1rem 0.75rem; }

    .nav-group-label {
      color: #4b5563;
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 1.25rem 0.5rem 0.4rem;
    }
    .nav-group-label:first-child { padding-top: 0.25rem; }

    .admin-nav a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 0.75rem;
      border-radius: 8px;
      color: #9ca3af;
      font-size: 0.875rem;
      font-weight: 500;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;
      margin-bottom: 2px;
    }
    .admin-nav a i { font-size: 0.95rem; width: 18px; text-align: center; flex-shrink: 0; }
    .admin-nav a:hover { background: #1e2227; color: #e5e7eb; }
    .admin-nav a.active {
      background: linear-gradient(90deg, rgba(30,58,138,0.35), rgba(59,130,246,0.15));
      color: #38bdf8;
      font-weight: 600;
      border-left: 3px solid #38bdf8;
      padding-left: calc(0.75rem - 3px);
    }

    .admin-sidebar-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid #1e2227;
    }
    .admin-user-info {
      display: flex; align-items: center; gap: 0.75rem;
      margin-bottom: 0.75rem;
    }
    .admin-avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: linear-gradient(135deg, #1e3a8a, #3b82f6);
      display: flex; align-items: center; justify-content: center;
      color: white; font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
    }
    .admin-user-info .user-meta strong { display: block; color: #f9fafb; font-size: 0.85rem; }
    .admin-user-info .user-meta span { color: #6b7280; font-size: 0.75rem; }
    .admin-logout-btn {
      width: 100%;
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      padding: 0.6rem;
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.2);
      color: #f87171;
      border-radius: 8px;
      font-size: 0.85rem; font-weight: 600;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
      transition: background 0.15s;
    }
    .admin-logout-btn:hover { background: rgba(239,68,68,0.2); color: #fca5a5; }

    /* ── Top Bar ── */
    .admin-topbar {
      position: fixed;
      top: 0; left: 260px; right: 0;
      height: 64px;
      background: white;
      border-bottom: 1px solid #e5e7eb;
      display: flex; align-items: center;
      padding: 0 1.75rem;
      gap: 1rem;
      z-index: 400;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .admin-topbar-toggle {
      display: none;
      background: none; border: none; cursor: pointer;
      color: #374151; font-size: 1.1rem; padding: 0.4rem;
    }
    .admin-topbar-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: #111827;
      flex: 1;
    }
    .admin-topbar-right {
      display: flex; align-items: center; gap: 1rem;
    }
    .admin-topbar-right .topbar-chip {
      display: flex; align-items: center; gap: 0.5rem;
      font-size: 0.85rem; font-weight: 600; color: #374151;
    }
    .admin-topbar-right .topbar-chip i { color: #3b82f6; }

    /* ── Page Content ── */
    .admin-page {
      margin-left: 260px;
      margin-top: 64px;
      flex: 1;
      min-height: calc(100vh - 64px);
      padding: 2rem;
    }

    /* ── Overlay ── */
    .admin-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 490;
    }

    /* ── Mobile ── */
    @media (max-width: 900px) {
      .admin-sidebar { transform: translateX(-100%); }
      .admin-sidebar.is-open { transform: translateX(0); }
      .admin-topbar { left: 0; }
      .admin-topbar-toggle { display: flex; align-items: center; justify-content: center; }
      .admin-page { margin-left: 0; padding: 1.25rem; }
      .admin-overlay.is-open { display: block; }
    }
  `;
  document.head.appendChild(style);
}

async function adminLogout() {
  try { await api("auth.logout", "POST"); } catch (_) {}
  window.location.href = (window.BASE_URL || '../../') + 'pages/login.html';
}

async function mountAdminLayout() {
  // Inject CSS
  injectAdminStyles();
  document.body.classList.add('admin-body');

  // Get current page
  const currentFile = window.location.pathname.split('/').pop();
  const pageTitle = ADMIN_NAV.find(n => n.href === currentFile)?.label || 'Admin Panel';

  // Get logged-in admin user
  let adminName = 'Admin';
  let adminInitial = 'A';
  try {
    const me = await api("auth.me");
    if (me.role !== 'admin') {
      window.location.href = (window.BASE_URL || '../../') + 'pages/login.html';
      return;
    }
    adminName = me.name || 'Admin';
    adminInitial = adminName.charAt(0).toUpperCase();
  } catch (_) {
    window.location.href = (window.BASE_URL || '../../') + 'pages/login.html';
    return;
  }

  // Build nav links HTML
  const navHTML = ADMIN_NAV.map(item => {
    if (item.group) {
      return `<div class="nav-group-label">${item.group}</div>`;
    }
    const isActive = currentFile === item.href ? 'active' : '';
    return `<a href="${item.href}" class="${isActive}"><i class="fa-solid ${item.icon}"></i> ${item.label}</a>`;
  }).join('');

  // Build full admin layout HTML
  const layoutHTML = `
    <!-- Overlay (mobile) -->
    <div class="admin-overlay" id="adminOverlay"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
      <a href="dashboard.html" class="admin-sidebar-brand">
        <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
        <div class="logo-text">
          <strong>MTH GLOBAL</strong>
          <span>Admin Panel</span>
        </div>
      </a>
      <nav class="admin-nav">${navHTML}</nav>
      <div class="admin-sidebar-footer">
        <div class="admin-user-info">
          <div class="admin-avatar">${adminInitial}</div>
          <div class="user-meta">
            <strong>${adminName}</strong>
            <span>Administrator</span>
          </div>
        </div>
        <button class="admin-logout-btn" onclick="adminLogout()">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
      </div>
    </aside>

    <!-- Top Bar -->
    <header class="admin-topbar">
      <button class="admin-topbar-toggle" id="sidebarToggle">
        <i class="fa-solid fa-bars"></i>
      </button>
      <span class="admin-topbar-title">${pageTitle}</span>
      <div class="admin-topbar-right">
        <div class="topbar-chip">
          <i class="fa-solid fa-circle-user"></i> ${adminName}
        </div>
      </div>
    </header>
  `;

  // Inject into #layout div
  const layoutRoot = document.getElementById('layout');
  if (layoutRoot) layoutRoot.outerHTML = layoutHTML;

  // Wrap all <main> elements
  document.querySelectorAll('main').forEach(main => {
    main.classList.add('admin-page');
  });

  // Mobile sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('adminOverlay');

  function openSidebar() {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  if (toggle) toggle.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  // Close sidebar when nav link clicked on mobile
  if (sidebar) {
    sidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) closeSidebar();
      });
    });
  }

  // Load AI Assistant
  if (!document.getElementById('ai-widget-script')) {
    const script = document.createElement('script');
    script.id = 'ai-widget-script';
    script.src = (window.BASE_URL || '../../') + 'assets/js/ai-widget.js';
    document.body.appendChild(script);
  }
}

async function authRequired() {
  try {
    const me = await api("auth.me");
    if (me.role !== 'admin') throw new Error("Not admin");
    return me;
  } catch (_) {
    window.location.href = (window.BASE_URL || '../../') + 'pages/login.html';
    return null;
  }
}
