function layout(role = null, userName = '', cartCount = 0) {
  const b = window.BASE_URL || '/';
  const common = [
    ['Home', b + 'index.html', 'fa-house'],
    ['Shop', b + 'pages/shop.html', 'fa-shop'],
    ['Cart', b + 'pages/cart.html', 'fa-cart-shopping'],
  ];

  const roleLinks = {
    customer: [
      ['Dashboard', b + 'pages/customer/dashboard.html', 'fa-gauge'],
      ['Orders', b + 'pages/customer/orders.html', 'fa-box'],
      ['Wishlist', b + 'pages/customer/wishlist.html', 'fa-heart'],
      ['Profile', b + 'pages/customer/profile.html', 'fa-user'],
    ],
    staff: [
      ['Dashboard', b + 'pages/staff/dashboard.html', 'fa-gauge'],
      ['Tasks', b + 'pages/staff/tasks.html', 'fa-list-check'],
      ['Inventory', b + 'pages/staff/inventory.html', 'fa-warehouse'],
    ],
    admin: [
      ['Dashboard', b + 'pages/admin/dashboard.html', 'fa-gauge-high'],
    ],
  };

  const links = [...common, ...(role ? roleLinks[role] || [] : [])];

  if (!role) {
    links.push(['Sign In', b + 'pages/login.html', 'fa-right-to-bracket']);
    links.push(['Join Us', b + 'pages/register.html', 'fa-user-plus']);
  }

  const currentPath = window.location.pathname;
  const currentFile = currentPath.split('/').pop();

  // Admin gets a special sidebar-style layout indicator
  const isAdmin = role === 'admin';

  return `
    <header class="topbar ${isAdmin ? 'topbar-admin' : 'topbar-glass'}">
      <div class="topbar-wrap">
        <a class="brand" href="${b}index.html">
          <i class="fa-solid fa-droplet"></i> MTH GLOBAL RESOURCES
          ${isAdmin ? '<span class="admin-badge">ADMIN</span>' : ''}
        </a>
        <nav class="links" id="navLinks">
          ${links.map(([label, href, icon]) => {
            const file = href.split('/').pop();
            const isActive = currentFile === file ? 'active' : '';
            return `<a href="${href}" class="${isActive}" title="${label}">
              <i class="fa-solid ${icon}"></i> 
              <span>${label}</span>
            </a>`;
          }).join('')}
        </nav>
        <div class="user-action">
          ${role ? `
            ${role === 'customer' ? `
              <a href="${b}pages/cart.html" class="cart-link" title="Shopping Cart">
                <i class="fa-solid fa-cart-shopping"></i>
                ${cartCount > 0 ? `<span class="cart-badge">${cartCount}</span>` : ''}
              </a>
            ` : `
              <div class="user-chip">
                <i class="fa-solid fa-circle-user" style="color:var(--primary);"></i>
                <span>${userName || role}</span>
              </div>
            `}
            <button class="logout-btn" onclick="logout()" title="Logout">
              <i class="fa-solid fa-right-from-bracket"></i>
              <span class="btn-text">Logout</span>
            </button>
          ` : `
            <a href="${b}pages/login.html" class="btn btn-primary btn-small">Get Started</a>
          `}
        </div>
        <button class="mobile-menu-btn" id="appMobileMenuBtn">
          <i class="fa-solid fa-bars"></i>
        </button>
      </div>
    </header>
  `;
}

async function logout() {
  try {
    await api("auth.logout", "POST");
  } catch (_) {}
  window.location.href = (window.BASE_URL || '/') + 'pages/login.html';
}

async function mountLayout() {
  let role = null;
  let userName = '';
  let cartCount = 0;
  try {
    const me = await api("auth.me");
    role = me.role;
    userName = me.name ? me.name.split(' ')[0] : me.role;

    // Role-based page guards
    const currentPath = window.location.pathname;
    const b = window.BASE_URL || '/';
    if (currentPath.includes('/pages/customer/')) {
      if (role !== 'customer') {
        if (role === 'admin') {
          window.location.href = b + 'pages/admin/dashboard.html';
        } else if (role === 'staff') {
          window.location.href = b + 'pages/staff/dashboard.html';
        } else {
          window.location.href = b + 'pages/login.html';
        }
        return;
      }
    } else if (currentPath.includes('/pages/staff/')) {
      if (role !== 'staff') {
        if (role === 'admin') {
          window.location.href = b + 'pages/admin/dashboard.html';
        } else if (role === 'customer') {
          window.location.href = b + 'pages/customer/dashboard.html';
        } else {
          window.location.href = b + 'pages/login.html';
        }
        return;
      }
    }
    
    // Fetch cart count if customer
    if (role === 'customer') {
      try {
        const cartItems = await api("cart.list");
        cartCount = cartItems.length;
      } catch(_) {}
    }
  } catch (_) {}

  const root = document.getElementById("layout");
  if (root) {
    root.innerHTML = layout(role, userName, cartCount);

    // Inject admin-specific navbar styles if needed
    if (role === 'admin' && !document.getElementById('admin-nav-style')) {
      const style = document.createElement('style');
      style.id = 'admin-nav-style';
      style.textContent = `
        .topbar-admin { background: #0a0a0a !important; border-bottom: 1px solid #1a1a1a; }
        .topbar-admin .brand { color: #e0f2fe !important; }
        .topbar-admin .links a { color: #9ca3af; }
        .topbar-admin .links a:hover,
        .topbar-admin .links a.active { color: #fff; background: rgba(255,255,255,0.06); }
        .topbar-admin .links a.active { border-bottom-color: #38bdf8; }
        .admin-badge {
          background: linear-gradient(90deg, #1e3a8a, #3b82f6);
          color: white;
          font-size: 0.6rem;
          font-weight: 800;
          letter-spacing: .08em;
          padding: 0.2rem 0.5rem;
          border-radius: 4px;
          text-transform: uppercase;
          margin-left: 0.4rem;
          vertical-align: middle;
        }
        .user-chip {
          display: flex;
          align-items: center;
          gap: 0.4rem;
          font-size: 0.875rem;
          font-weight: 600;
          color: #e0f2fe;
          padding: 0.4rem 0.75rem;
          background: rgba(255,255,255,0.06);
          border-radius: var(--radius-md);
        }
        .logout-btn {
          display: flex;
          align-items: center;
          gap: 0.4rem;
          padding: 0.5rem 1rem;
          background: rgba(239,68,68,0.12);
          border: 1px solid rgba(239,68,68,0.25);
          color: #fca5a5;
          border-radius: var(--radius-md);
          cursor: pointer;
          font-size: 0.85rem;
          font-weight: 600;
          transition: var(--transition);
          font-family: 'Inter', sans-serif;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.22); color: #fff; }
      `;
      document.head.appendChild(style);
    }

    // Non-admin user chip and logout styles
    if (role && role !== 'admin' && !document.getElementById('user-action-style')) {
      const style = document.createElement('style');
      style.id = 'user-action-style';
      style.textContent = `
        .topbar-glass {
          background: rgba(255, 255, 255, 0.8) !important;
          backdrop-filter: blur(12px);
          -webkit-backdrop-filter: blur(12px);
          border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .user-chip { 
          display:flex; 
          align-items:center; 
          gap:.4rem; 
          font-size:.875rem; 
          font-weight:600; 
          color:var(--text-main); 
          padding:.4rem .75rem; 
          background:var(--bg-alt); 
          border-radius:var(--radius-md); 
          border:1px solid var(--border-color); 
        }
        .logout-btn { 
          display:flex; 
          align-items:center; 
          gap:.4rem; 
          padding:.5rem 1rem; 
          background:rgba(239,68,68,0.08); 
          border:1px solid rgba(239,68,68,0.2); 
          color:#b91c1c; 
          border-radius:var(--radius-md); 
          cursor:pointer; 
          font-size:.85rem; 
          font-weight:600; 
          font-family:'Inter',sans-serif; 
          transition:var(--transition); 
        }
        .logout-btn:hover { background:rgba(239,68,68,0.15); }
        .links a { position: relative; }
        .links a::after {
          content: '';
          position: absolute;
          bottom: 0; left: 50%; width: 0; height: 2px;
          background: var(--primary);
          transition: all 0.3s ease;
          transform: translateX(-50%);
        }
        .links a:hover::after, .links a.active::after { width: 100%; }

        .cart-link {
          position: relative;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 42px;
          height: 42px;
          background: var(--bg-alt);
          border: 1px solid var(--border-color);
          border-radius: var(--radius-md);
          color: var(--primary);
          font-size: 1.1rem;
          transition: var(--transition);
          text-decoration: none;
        }
        .cart-link:hover { background: var(--border-color); }
        .cart-badge {
          position: absolute;
          top: -6px;
          right: -6px;
          background: #ef4444;
          color: white;
          font-size: 0.7rem;
          font-weight: 800;
          min-width: 18px;
          height: 18px;
          border-radius: 99px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 0 4px;
          border: 2px solid white;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
          .btn-text { display: none; }
        }
      `;
      document.head.appendChild(style);
    }

    // Mobile Menu Toggle
    const mobileBtn = document.getElementById('appMobileMenuBtn');
    const navLinks = root.querySelector('.links');

    if (mobileBtn && navLinks) {
      const toggleMenu = () => {
        navLinks.classList.toggle('active');
        const icon = mobileBtn.querySelector('i');
        if (navLinks.classList.contains('active')) {
          icon.classList.replace('fa-bars', 'fa-xmark');
        } else {
          icon.classList.replace('fa-xmark', 'fa-bars');
        }
      };

      mobileBtn.addEventListener('click', toggleMenu);

      navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          if (navLinks.classList.contains('active')) toggleMenu();
        });
      });
    }

    // Load AI Assistant
    if (!document.getElementById('ai-widget-script')) {
      const script = document.createElement('script');
      script.id = 'ai-widget-script';
      script.src = (window.BASE_URL || '/') + 'assets/js/ai-widget.js';
      document.body.appendChild(script);
    }

    // Try to register/update push notification subscription
    registerPushSubscription().catch(err => console.error("Push registration error", err));
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');
 
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
 
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

async function registerPushSubscription() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    return;
  }
  
  // Register service worker if not already registered
  const b = window.BASE_URL || '/';
  try {
    await navigator.serviceWorker.register(b + 'sw.js');
  } catch (err) {
    console.error('Service worker registration failed:', err);
    return;
  }

  // Ask for permission (only if default)
  if (Notification.permission === 'default') {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;
  } else if (Notification.permission !== 'granted') {
    return;
  }

  try {
    const readyReg = await navigator.serviceWorker.ready;
    let sub = await readyReg.pushManager.getSubscription();
    if (!sub) {
      const vapidPublicKey = 'BH2r-koIpv02Kp9oqRlDbuuXUE1u3RE6Ihdtu7fi61X75ZYmXWgyF5-8nCe6SYqdSYdMlJl0oprIhRv7WEE58SA';
      const convertedKey = urlBase64ToUint8Array(vapidPublicKey);
      sub = await readyReg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: convertedKey
      });
    }

    // Send subscription to backend API
    await api("push.subscribe", "POST", sub.toJSON());
  } catch (err) {
    console.error('Push subscription failed:', err);
  }
}

// Global helper to update the cart badge from any page (e.g. after adding to cart)
window.updateCartBadge = async function() {
  const badge = document.querySelector('.cart-badge');
  const cartLink = document.querySelector('.cart-link');
  if (!cartLink) return;

  try {
    const items = await api("cart.list");
    const count = items.length;
    
    if (count > 0) {
      if (badge) {
        badge.textContent = count;
      } else {
        const newBadge = document.createElement('span');
        newBadge.className = 'cart-badge';
        newBadge.textContent = count;
        cartLink.appendChild(newBadge);
      }
    } else if (badge) {
      badge.remove();
    }
  } catch (err) {
    console.error("Failed to update cart badge", err);
  }
};

