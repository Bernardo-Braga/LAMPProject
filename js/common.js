// Helpers shared by every page. Load it before the page's own script:
//   <script src="js/common.js"></script>
//   <script src="js/contacts.js"></script>

// Don't let other websites show these pages inside a frame (clickjacking).
if (window.top !== window.self) {
  window.top.location = window.self.location;
}

// The signed-in user saved by the login page (id, firstName, role, csrfToken...), or null.
function getCurrentUser() {
  try {
    return JSON.parse(sessionStorage.getItem("currentUser"));
  } catch (e) {
    return null;
  }
}

// Escape text before putting it inside innerHTML. Always use it for data people typed
// (names, notes...), otherwise a name like <img src=x onerror=...> would run as code.
function escapeHtml(value) {
  return String(value === null || value === undefined ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

// Call an API endpoint and return { status, data }.
//   apiRequest("SearchContacts.php?term=ben")
//   apiRequest("AddContact.php", { method: "POST", body: { firstName: "Ben" } })
//   apiRequest("UploadContactPhoto.php", { method: "POST", form: formData })
// POSTs carry the CSRF token from sign-in. If the session has expired, go back to sign in.
async function apiRequest(endpoint, options, isRetry) {
  options = options || {};
  const method = options.method || "GET";
  const headers = {};
  const user = getCurrentUser();

  if (method !== "GET" && user && user.csrfToken) {
    headers["X-CSRF-Token"] = user.csrfToken;
  }
  let body;
  if (options.form) {
    body = options.form; // FormData: the browser sets the multipart Content-Type itself
  } else if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const response = await fetch("/api/" + endpoint, { method: method, headers: headers, body: body });
  let data = null;
  try {
    data = await response.json();
  } catch (e) {
    data = null;
  }

  // Signing in again (e.g. in another tab) issues a new CSRF token. Fetch it and try once more;
  // if a different person signed in meanwhile, reload so the page shows their data.
  if (data && data.code === "csrf_failed" && !isRetry) {
    const me = await fetch("/api/Me.php").then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
    if (me && user && me.id === user.id) {
      sessionStorage.setItem("currentUser", JSON.stringify(Object.assign({}, user, me)));
      return apiRequest(endpoint, options, true);
    }
    if (me) {
      sessionStorage.setItem("currentUser", JSON.stringify(me));
      window.location.reload();
    }
  }

  if (response.status === 401 && !options.allowUnauthorized) {
    sessionStorage.removeItem("currentUser");
    window.location.href = "login.html";
  }
  if (data && data.code === "password_change_required" && !location.pathname.endsWith("account.html")) {
    window.location.href = "account.html?forced=1";
  }
  return { status: response.status, ok: response.ok, data: data || {} };
}

// The error to show for a failed request: field messages when there are some.
function errorMessage(result) {
  if (result.data && result.data.details) {
    return Object.values(result.data.details).join(" ");
  }
  return (result.data && result.data.error) || "Something went wrong. Please try again.";
}

// Sign out on the server, then return to the sign-in page.
async function logOut() {
  try {
    await apiRequest("Logout.php", { method: "POST", allowUnauthorized: true });
  } finally {
    sessionStorage.removeItem("currentUser");
    window.location.href = "login.html";
  }
}

// Adds Dashboard / Contacts / Admin / Account links to the page's top bar.
function renderNav(activePage) {
  const user = getCurrentUser();
  const topbar = document.querySelector(".topbar");
  if (!user || !topbar || document.querySelector(".main-nav")) {
    return;
  }
  const links = [
    ["dashboard.html", "Dashboard"],
    ["contacts.html", "Contacts"],
  ];
  if (user.role === "Admin") {
    links.push(["admin.html", "Admin"]);
  }
  links.push(["account.html", "Account"]);

  const nav = document.createElement("nav");
  nav.className = "main-nav";
  nav.setAttribute("aria-label", "Main");
  nav.innerHTML = links
    .map(function (link) {
      const current = link[0] === activePage + ".html" ? ' aria-current="page"' : "";
      return '<a href="' + link[0] + '"' + current + ">" + link[1] + "</a>";
    })
    .join("");
  topbar.insertBefore(nav, topbar.children[1] || null);
}

// Small message at the bottom of the screen that disappears by itself.
function showToast(message, type) {
  let region = document.getElementById("toast-region");
  if (!region) {
    region = document.createElement("div");
    region.id = "toast-region";
    region.className = "toast-region";
    region.setAttribute("role", "status");
    region.setAttribute("aria-live", "polite");
    document.body.appendChild(region);
  }
  const toast = document.createElement("div");
  toast.className = "toast" + (type === "error" ? " toast-error" : "");
  toast.textContent = message;
  region.appendChild(toast);
  setTimeout(function () {
    toast.remove();
  }, type === "error" ? 6000 : 3500);
}

// Dates from the API are UTC: "2026-09-27 18:04:05" (date and time) or "1990-05-01" (date only).
function parseApiDate(value) {
  if (!value) return null;
  return new Date(value.length === 10 ? value + "T00:00:00Z" : value.replace(" ", "T") + "Z");
}

function formatDate(value, options) {
  const date = parseApiDate(value);
  if (!date) return "";
  options = options || { year: "numeric", month: "short", day: "numeric" };
  if (value.length === 10) options = Object.assign({}, options, { timeZone: "UTC" });
  return date.toLocaleDateString(undefined, options);
}

function formatDateTime(value) {
  const date = parseApiDate(value);
  return date ? date.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "";
}

// "5 minutes ago", "yesterday", "3 weeks ago"...
function timeAgo(value) {
  const date = parseApiDate(value);
  if (!date) return "never";
  let amount = (Date.now() - date.getTime()) / 1000;
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });
  const steps = [[60, "second"], [60, "minute"], [24, "hour"], [7, "day"], [4.35, "week"], [12, "month"], [Infinity, "year"]];
  for (let i = 0; i < steps.length; i++) {
    if (Math.abs(amount) < steps[i][0]) return rtf.format(-Math.round(amount), steps[i][1]);
    amount /= steps[i][0];
  }
  return formatDate(value);
}

// Round picture with the contact's photo, or their initials on a colored background.
function avatarHtml(name, photoUrl) {
  const colors = ["#db2777", "#9333ea", "#2563eb", "#0891b2", "#059669", "#d97706", "#dc2626", "#7c3aed"];
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
  const initials = name.trim().split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join("");
  const color = colors[hash % colors.length];
  if (photoUrl) {
    return '<span class="avatar" style="background:' + color + '"><img src="' + escapeHtml(photoUrl) + '" alt="" loading="lazy"></span>';
  }
  return '<span class="avatar" style="background:' + color + '" aria-hidden="true">' + escapeHtml(initials.toUpperCase()) + "</span>";
}

// One activity entry as text: "You added contact Ben Brown", "Rosie P updated contact ..."
function activityText(item) {
  return (item.isYou ? "You" : item.actorName || "Someone") + " " + item.summary;
}
