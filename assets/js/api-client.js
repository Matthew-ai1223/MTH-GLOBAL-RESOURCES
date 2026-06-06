window.BASE_URL = window.location.pathname.includes('/nu-farm/') 
  ? window.location.pathname.split('/nu-farm/')[0] + '/nu-farm/' 
  : '/';
const API_URL = window.BASE_URL + "backend/api.php";

async function api(route, method = "GET", body = null) {
  const [baseRoute, ...queryParts] = route.split('&');
  let url = `${API_URL}?route=${encodeURIComponent(baseRoute)}`;
  if (queryParts.length > 0) {
    url += '&' + queryParts.join('&');
  }
  const options = { method, credentials: "include" };
  if (body) {
    if (Object.prototype.toString.call(body) === '[object FormData]') {
      options.body = body;
    } else {
      options.headers = { "Content-Type": "application/json" };
      options.body = JSON.stringify(body);
    }
  }
  const res = await fetch(url, options);
  
  let data;
  const rawText = await res.text();
  try {
    data = JSON.parse(rawText);
  } catch (e) {
    console.error("Raw server response:", rawText);
    throw new Error(`Server returned invalid response (Status ${res.status}). Raw: ${rawText.substring(0, 100)}...`);
  }
  
  if (!res.ok || !data.success) {
    throw new Error(data.message || `Request failed with status ${res.status}`);
  }
  return data.data;
}

/**
 * Returns the correct absolute URL for a product image
 * @param {string} path - The image path from the database
 * @returns {string}
 */
function getProductImageUrl(path) {
  if (!path) return BASE_URL + 'assets/images/placeholder.png';
  if (path.startsWith('http')) return path;
  // If it starts with uploads/, make it absolute from domain root
  return BASE_URL + path;
}

/**
 * Global Toast System
 * @param {string} msg - Message to display
 * @param {string} type - 'success', 'error', 'info', 'warning'
 * @param {string} title - Optional title
 */
function toast(msg, type = 'info', title = '') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: 'fa-check-circle',
    error: 'fa-circle-xmark',
    info: 'fa-circle-info',
    warning: 'fa-triangle-exclamation'
  };

  const titles = {
    success: 'Success',
    error: 'Error',
    info: 'Information',
    warning: 'Warning'
  };

  const toastEl = document.createElement('div');
  toastEl.className = `toast toast-${type}`;
  toastEl.innerHTML = `
    <div class="toast-icon">
      <i class="fa-solid ${icons[type] || icons.info}"></i>
    </div>
    <div class="toast-content">
      <span class="toast-title">${title || titles[type]}</span>
      <span class="toast-msg">${msg}</span>
    </div>
    <button class="toast-close"><i class="fa-solid fa-xmark"></i></button>
  `;

  container.appendChild(toastEl);

  // Animate show
  setTimeout(() => toastEl.classList.add('show'), 10);

  // Auto remove
  const timeout = setTimeout(() => hideToast(toastEl), 5000);

  // Manual close
  toastEl.querySelector('.toast-close').onclick = () => {
    clearTimeout(timeout);
    hideToast(toastEl);
  };
}

function hideToast(el) {
  el.classList.remove('show');
  setTimeout(() => el.remove(), 400);
}

window.showToast = toast;

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
