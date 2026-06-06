async function authRequired() {
  try {
    return await api("auth.me");
  } catch (_) {
    location.href = (window.BASE_URL || "/") + "pages/login.html";
    return null;
  }
}

function htmlTable(headers, rows) {
  return `<table><thead><tr>${headers.map((h) => `<th>${h}</th>`).join("")}</tr></thead><tbody>${rows.join("")}</tbody></table>`;
}
