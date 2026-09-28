// Dashboard: counts, upcoming birthdays, recently added contacts and the activity feed.

const currentUser = getCurrentUser();

if (!currentUser) {
  window.location.href = "login.html";
}

document.getElementById("welcome-message").textContent = "Welcome, " + currentUser.firstName;
renderNav("dashboard");

document.getElementById("logout-button").addEventListener("click", function () {
  logOut();
});

const hour = new Date().getHours();
document.getElementById("greeting").textContent =
  "Good " + (hour < 12 ? "morning" : hour < 18 ? "afternoon" : "evening") + ", " + currentUser.firstName;
document.getElementById("today").textContent =
  new Date().toLocaleDateString(undefined, { weekday: "long", month: "long", day: "numeric" });

let activityPage = 0; // 0 = showing the short list from Dashboard.php

function activityItem(item) {
  return `<li><span class="activity-dot"></span><div>${escapeHtml(activityText(item))}
    <span class="activity-time" title="${escapeHtml(formatDateTime(item.dateCreated))}">${escapeHtml(timeAgo(item.dateCreated))}</span></div></li>`;
}

async function loadDashboard() {
  const result = await apiRequest("Dashboard.php");
  if (!result.ok) {
    showToast(errorMessage(result), "error");
    return;
  }
  const data = result.data;

  document.getElementById("count-contacts").textContent = data.counts.Contacts;
  document.getElementById("count-favorites").textContent = data.counts.Favorites;
  document.getElementById("count-shared-with-me").textContent = data.counts.SharedWithMe;
  document.getElementById("count-shared-by-me").textContent = data.counts.SharedByMe;

  document.getElementById("birthdays").innerHTML = data.upcomingBirthdays.length
    ? data.upcomingBirthdays.map(function (b) {
        const when = b.DaysUntil === 0 ? "Today!" : b.DaysUntil === 1 ? "Tomorrow" : "in " + b.DaysUntil + " days";
        return `<li><span>${escapeHtml(b.Name)}</span>
          <span class="subtitle">${when} &middot; ${escapeHtml(formatDate(b.Date, { month: "short", day: "numeric" }))}${b.Turning > 0 && b.Turning < 130 ? " &middot; turns " + b.Turning : ""}</span></li>`;
      }).join("")
    : `<li class="subtitle">No birthdays in the next 30 days. Add birthdays to your contacts to see them here.</li>`;

  document.getElementById("recent-contacts").innerHTML = data.recentContacts.length
    ? data.recentContacts.map(function (c) {
        return `<li><span>${escapeHtml(c.FirstName + " " + c.LastName)}${c.Company ? ` <span class="subtitle">&middot; ${escapeHtml(c.Company)}</span>` : ""}</span>
          <span class="subtitle">${escapeHtml(timeAgo(c.DateCreated))}</span></li>`;
      }).join("")
    : `<li class="subtitle">No contacts yet. <a href="contacts.html">Add one</a>.</li>`;

  document.getElementById("activity").innerHTML = data.activity.length
    ? data.activity.map(activityItem).join("")
    : `<li class="subtitle">Nothing yet. Things you and the people you share with do will show up here.</li>`;
  document.getElementById("more-activity").style.display = data.activity.length >= 8 ? "inline-flex" : "none";
}

document.getElementById("more-activity").addEventListener("click", async function () {
  activityPage++;
  const result = await apiRequest("ActivityFeed.php?page=" + activityPage);
  if (!result.ok) return;
  const list = document.getElementById("activity");
  const html = result.data.items.map(activityItem).join("");
  list.innerHTML = activityPage === 1 ? html : list.innerHTML + html;
  document.getElementById("more-activity").style.display = activityPage < result.data.pages ? "inline-flex" : "none";
});

loadDashboard();
