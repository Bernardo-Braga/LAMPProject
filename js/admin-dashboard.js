// Admin page extras: the Statistics and Audit log tabs, and the "New User" dialog.
// The Users tab itself is in js/admin.js.

// ---------- Tabs ----------

const adminTabs = document.querySelectorAll('.tabs [role="tab"]');

function selectAdminTab(name) {
  adminTabs.forEach(function (tab) {
    const active = tab.getAttribute("data-tab") === name;
    tab.setAttribute("aria-selected", String(active));
    tab.tabIndex = active ? 0 : -1;
    document.getElementById("panel-" + tab.getAttribute("data-tab")).style.display = active ? "block" : "none";
  });
  if (name === "stats") loadStats();
  if (name === "audit") loadAudit();
}

adminTabs.forEach(function (tab, index) {
  tab.addEventListener("click", function () {
    selectAdminTab(tab.getAttribute("data-tab"));
  });
  // Left/Right arrows move between tabs, as screen reader users expect
  tab.addEventListener("keydown", function (event) {
    const step = event.key === "ArrowRight" ? 1 : event.key === "ArrowLeft" ? -1 : 0;
    if (!step) return;
    const next = adminTabs[(index + step + adminTabs.length) % adminTabs.length];
    selectAdminTab(next.getAttribute("data-tab"));
    next.focus();
  });
});

// ---------- Statistics ----------

// Vertical bar chart drawn as SVG: series = [{ Date: "2026-09-01", Count: 3 }, ...]
function drawBarChart(container, series, noun) {
  const width = 600, height = 180, left = 30, bottom = 24, top = 12;
  const max = Math.max(1, ...series.map(function (d) { return d.Count; }));
  const step = (width - left) / series.length;
  const barWidth = Math.max(2, step * 0.7);
  const every = Math.ceil(series.length / 7);

  const bars = series.map(function (d, i) {
    const h = (d.Count / max) * (height - top - bottom);
    const x = left + i * step + (step - barWidth) / 2;
    const label = formatDate(d.Date, { month: "short", day: "numeric" });
    const tick = i % every === 0 ? `<text class="axis" x="${x + barWidth / 2}" y="${height - 6}" text-anchor="middle">${label}</text>` : "";
    return `<rect class="bar" x="${x}" y="${height - bottom - h}" width="${barWidth}" height="${Math.max(h, d.Count ? 2 : 0)}" rx="3">
      <title>${label}: ${d.Count} ${noun}${d.Count === 1 ? "" : "s"}</title></rect>${tick}`;
  }).join("");

  container.innerHTML = `<svg class="chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="${noun}s per day">
    <line class="grid" x1="${left}" x2="${width}" y1="${top}" y2="${top}"></line>
    <line class="grid" x1="${left}" x2="${width}" y1="${height - bottom}" y2="${height - bottom}"></line>
    <text class="axis" x="${left - 6}" y="${top + 4}" text-anchor="end">${max}</text>
    <text class="axis" x="${left - 6}" y="${height - bottom + 4}" text-anchor="end">0</text>
    ${bars}</svg>`;
}

// Horizontal bars for rankings: rows = [{ label, value }]
function drawRanking(container, rows, emptyText) {
  if (!rows.length) {
    container.innerHTML = `<p class="subtitle">${emptyText}</p>`;
    return;
  }
  const max = Math.max(1, ...rows.map(function (r) { return r.value; }));
  container.innerHTML = rows.map(function (r) {
    return `<div class="rank-row"><span class="rank-label">${escapeHtml(r.label)}</span>
      <span class="rank-track"><span class="rank-fill" style="width:${(r.value / max) * 100}%"></span></span>
      <strong>${r.value}</strong></div>`;
  }).join("");
}

async function loadStats() {
  const result = await apiRequest("AdminStats.php");
  if (!result.ok) {
    showToast(errorMessage(result), "error");
    return;
  }
  const s = result.data;
  const t = s.totals;
  const tiles = [
    [t.Users, "Users"], [t.ActiveLast7Days, "Active in last 7 days"], [t.Admins, "Admins"],
    [t.Contacts, "Contacts"], [t.Shares, "Shares"], [t.Tags, "Tags"],
    [t.Disabled, "Disabled"], [t.Locked, "Locked out"], [t.FailedLogins24h, "Failed sign-ins (24h)"],
  ];
  document.getElementById("stat-tiles").innerHTML = tiles.map(function (tile) {
    return `<div class="stat"><div class="stat-value">${tile[0]}</div><div class="stat-label">${tile[1]}</div></div>`;
  }).join("");

  drawBarChart(document.getElementById("chart-logins"), s.loginsByDay, "sign-in");
  drawBarChart(document.getElementById("chart-signups"), s.signupsByDay, "new account");
  drawBarChart(document.getElementById("chart-contacts"), s.contactsByDay, "contact");
  drawRanking(document.getElementById("chart-top-users"), s.topUsers.map(function (u) {
    return { label: u.FirstName + " " + u.LastName + " (@" + u.Login + ")", value: Number(u.Contacts) };
  }), "Nobody has contacts yet.");
  drawRanking(document.getElementById("chart-actions"), s.actionsLast7Days.map(function (a) {
    return { label: a.Action, value: Number(a.Count) };
  }), "No activity in the last 7 days.");
}

// ---------- Audit log ----------

let auditPage = 1;
let auditActionsLoaded = false;
let auditTimer = null;

async function loadAudit() {
  const term = document.getElementById("audit-search").value;
  const action = document.getElementById("audit-action").value;
  const result = await apiRequest(`AuditLog.php?term=${encodeURIComponent(term)}&action=${encodeURIComponent(action)}&page=${auditPage}`);
  if (!result.ok) {
    showToast(errorMessage(result), "error");
    return;
  }
  const data = result.data;
  auditPage = data.page;

  if (!auditActionsLoaded) {
    auditActionsLoaded = true;
    const groups = [...new Set(data.actions.map(function (a) { return a.split(".")[0]; }))];
    document.getElementById("audit-action").innerHTML = `<option value="">All Actions</option>` +
      groups.map(function (group) {
        return `<option value="${escapeHtml(group)}">All ${escapeHtml(group)} actions</option>` +
          data.actions.filter(function (a) { return a.startsWith(group + "."); })
            .map(function (a) { return `<option value="${escapeHtml(a)}">&nbsp;&nbsp;${escapeHtml(a)}</option>`; }).join("");
      }).join("");
  }

  document.getElementById("audit-list").innerHTML = data.items.length
    ? data.items.map(function (item) {
        return `<div class="audit-row">
          <span class="audit-time" title="${escapeHtml(formatDateTime(item.dateCreated))}">${escapeHtml(formatDateTime(item.dateCreated))}</span>
          <span class="audit-text">${escapeHtml(activityText(item))}</span>
          <span class="badge badge-muted">${escapeHtml(item.action)}</span>
          <span class="audit-ip">${escapeHtml(item.ipAddress || "")}</span>
        </div>`;
      }).join("")
    : `<p class="subtitle">No entries match.</p>`;

  document.getElementById("audit-page").textContent = `Page ${data.page} of ${data.pages} (${data.total} entries)`;
  document.getElementById("audit-prev").disabled = data.page <= 1;
  document.getElementById("audit-next").disabled = data.page >= data.pages;
}

document.getElementById("audit-search").addEventListener("input", function () {
  clearTimeout(auditTimer);
  auditTimer = setTimeout(function () {
    auditPage = 1;
    loadAudit();
  }, 300);
});
document.getElementById("audit-action").addEventListener("change", function () {
  auditPage = 1;
  loadAudit();
});
document.getElementById("audit-prev").addEventListener("click", function () {
  auditPage--;
  loadAudit();
});
document.getElementById("audit-next").addEventListener("click", function () {
  auditPage++;
  loadAudit();
});

// ---------- New user ----------

function randomPassword() {
  const alphabet = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  return Array.from(crypto.getRandomValues(new Uint32Array(12)), function (n) {
    return alphabet[n % alphabet.length];
  }).join("");
}

document.getElementById("new-user-button").addEventListener("click", function () {
  ["nu-firstName", "nu-lastName", "nu-login"].forEach(function (id) {
    document.getElementById(id).value = "";
  });
  document.getElementById("nu-password").value = randomPassword();
  document.getElementById("new-user-message").textContent = "";
  openDialog(document.getElementById("new-user-modal"));
});

document.getElementById("new-user-cancel").addEventListener("click", function () {
  closeDialog(document.getElementById("new-user-modal"));
});

document.getElementById("new-user-save").addEventListener("click", async function () {
  const result = await apiRequest("CreateAdmin.php", {
    method: "POST",
    body: {
      firstName: document.getElementById("nu-firstName").value,
      lastName: document.getElementById("nu-lastName").value,
      login: document.getElementById("nu-login").value,
      password: document.getElementById("nu-password").value,
      role: document.getElementById("nu-role").value,
      mustChangePassword: document.getElementById("nu-must-change").checked
    }
  });
  if (!result.ok) {
    document.getElementById("new-user-message").textContent = errorMessage(result);
    return;
  }
  closeDialog(document.getElementById("new-user-modal"));
  showToast("User created. Share the password with them securely.");
  loadUsers();
});
