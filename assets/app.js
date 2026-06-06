const API_URL = "backend/api.php";

const state = {
  user: null,
  products: [],
  cart: [],
  orders: []
};

function toast(message) {
  const el = document.getElementById("toast");
  el.textContent = message;
  el.classList.add("show");
  setTimeout(() => el.classList.remove("show"), 1800);
}

async function api(route, method = "GET", body = null) {
  const opts = { method, credentials: "same-origin" };
  if (body) {
    opts.headers = { "Content-Type": "application/json" };
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(`${API_URL}?route=${encodeURIComponent(route)}`, opts);
  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.message || "Request failed");
  }
  return data.data;
}

function renderAuth() {
  const wrap = document.getElementById("authActions");
  if (!state.user) {
    wrap.innerHTML = "<span>Not logged in</span>";
    return;
  }
  wrap.innerHTML = `<span>${state.user.name} (${state.user.role})</span> <button id="logoutBtn">Logout</button>`;
  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await api("auth.logout", "POST");
    state.user = null;
    renderAuth();
    toast("Logged out");
  });
}

function renderProducts() {
  const target = document.getElementById("products");
  if (!state.products.length) {
    target.innerHTML = "<p>No products found.</p>";
    return;
  }
  target.innerHTML = state.products.map((p) => `
    <div class="product">
      <h4>${p.name}</h4>
      <p>${p.description || ""}</p>
      <div class="row">
        <strong>$${Number(p.price).toFixed(2)}</strong>
        <span>Stock: ${p.stock}</span>
      </div>
      <button data-product-id="${p.id}" class="addCartBtn">Add to Cart</button>
    </div>
  `).join("");

  document.querySelectorAll(".addCartBtn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      try {
        await api("cart.add", "POST", { product_id: Number(btn.dataset.productId), quantity: 1 });
        toast("Added to cart");
        await loadCart();
      } catch (err) {
        toast(err.message);
      }
    });
  });
}

function renderCart() {
  const target = document.getElementById("cart");
  if (!state.cart.length) {
    target.innerHTML = "<p>Cart is empty.</p>";
    return;
  }
  target.innerHTML = state.cart.map((item) => `
    <div class="row">
      <span>${item.name} x ${item.quantity}</span>
      <span>$${(Number(item.price) * Number(item.quantity)).toFixed(2)}</span>
    </div>
  `).join("");
}

function renderOrders() {
  const target = document.getElementById("orders");
  if (!state.orders.length) {
    target.innerHTML = "<p>No orders yet.</p>";
    return;
  }
  target.innerHTML = state.orders.map((o) => `
    <div class="product">
      <div class="row">
        <strong>Order #${o.id}</strong>
        <span>${o.status}</span>
      </div>
      <small>Total: $${Number(o.total_amount).toFixed(2)} | ${o.created_at}</small>
    </div>
  `).join("");
}

async function loadSession() {
  try {
    state.user = await api("auth.me");
  } catch (_) {
    state.user = null;
  }
  renderAuth();
}

async function loadProducts(search = "") {
  state.products = await api(`products.list&search=${encodeURIComponent(search)}`);
  renderProducts();
}

async function loadCart() {
  try {
    state.cart = await api("cart.list");
  } catch (_) {
    state.cart = [];
  }
  renderCart();
}

async function loadOrders() {
  try {
    state.orders = await api("orders.list");
  } catch (_) {
    state.orders = [];
  }
  renderOrders();
}

document.getElementById("registerForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  try {
    await api("auth.register", "POST", Object.fromEntries(fd.entries()));
    toast("Registration successful");
    e.target.reset();
  } catch (err) {
    toast(err.message);
  }
});

document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  try {
    state.user = await api("auth.login", "POST", Object.fromEntries(fd.entries()));
    renderAuth();
    toast("Welcome back");
    e.target.reset();
    await loadCart();
    await loadOrders();
  } catch (err) {
    toast(err.message);
  }
});

document.getElementById("checkoutBtn").addEventListener("click", async () => {
  try {
    await api("orders.checkout", "POST");
    toast("Order created");
    await loadCart();
    await loadOrders();
  } catch (err) {
    toast(err.message);
  }
});

document.getElementById("loadOrdersBtn").addEventListener("click", loadOrders);
document.getElementById("loadProductsBtn").addEventListener("click", () => {
  loadProducts(document.getElementById("searchInput").value.trim());
});

loadSession().then(() => {
  loadProducts();
  loadCart();
  loadOrders();
});
