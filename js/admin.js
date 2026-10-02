// Check login + confirm this user is actually an admin
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
} else if (currentUser.role !== "Admin") {
  window.location.href = "dashboard.html"; // the server also refuses admin requests from non-admins
}

document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;
renderNav("admin");

const API_BASE = "/api";
let users = [];

async function loadUsers() {
  document.getElementById("users-list").innerHTML = "<p>Loading users...</p>";
  try {
    // The server checks that the signed-in user is an admin; no adminId is sent.
    const result = await apiRequest("ListUsers.php?term=");
    const data = result.data;

    users = data.map(function (user) {
      return {
        id: user.ID,
        firstName: user.FirstName,
        lastName: user.LastName,
        login: user.Login,
        role: user.Role,
        isDisabled: Boolean(Number(user.IsDisabled)),
        isLocked: Boolean(Number(user.IsLocked)),
        mustChangePassword: Boolean(Number(user.MustChangePassword)),
        lastLoginAt: user.LastLoginAt,
        contactCount: Number(user.ContactCount)
      };
    });

    refreshList();
  } catch (err) {
    document.getElementById("users-list").innerHTML = "<p>Could not load users.</p>";
  }
}

function renderUsers(usersToShow) {
  const listContainer = document.getElementById("users-list");
  listContainer.innerHTML = "";

  document.getElementById("user-count").textContent =
    users.length + (users.length === 1 ? " user" : " users");

  if (usersToShow.length === 0) {
    listContainer.innerHTML = "<p>No users found.</p>";
    return;
  }

  usersToShow.forEach(function (user) {
    const card = document.createElement("div");
    card.className = "user-card";

    let badges = "";

    const isSelf = user.id === currentUser.id;
    if (isSelf) {
      badges += `<span class="badge badge-admin">You</span>`;
    }
    if (user.role === "Admin") {
      badges += `<span class="badge badge-admin">Admin</span>`;
    }
    if (user.isDisabled) {
      badges += `<span class="badge badge-disabled">Disabled</span>`;
    }
    if (user.isLocked) {
      badges += `<span class="badge badge-warning">Locked</span>`;
    }
    if (user.mustChangePassword) {
      badges += `<span class="badge badge-warning">Must change password</span>`;
    }

    const lastSeen = user.lastLoginAt ? "last sign-in " + timeAgo(user.lastLoginAt) : "never signed in";
    const fullName = escapeHtml(user.firstName + " " + user.lastName);

    card.innerHTML = `
      <div class="user-info">
        <h2>${escapeHtml(user.firstName)} ${escapeHtml(user.lastName)} ${badges}</h2>
        <p>${escapeHtml(user.login)} &middot; ${user.contactCount} contact(s) &middot; ${lastSeen}</p>
      </div>
      <div class="user-actions">
        <button type="button" class="toggle-disable-button" data-id="${user.id}" aria-label="${isSelf ? "Can't disable yourself" : (user.isDisabled ? "Enable " : "Disable ") + fullName}" ${isSelf ? "disabled" : ""}>
          ${isSelf ? "Can't disable yourself" : (user.isDisabled ? "Enable" : "Disable")}
        </button>
        <button type="button" class="toggle-admin-button" data-id="${user.id}" aria-label="${(user.role === "Admin" ? "Demote " : "Promote to Admin ") + fullName}" ${isSelf ? "disabled" : ""}>
          ${user.role === "Admin" ? "Demote" : "Promote to Admin"}
        </button>
        <button type="button" class="change-password-button" data-id="${user.id}" aria-label="Change Password for ${fullName}">Change Password</button>
        ${user.isLocked ? `<button type="button" class="unlock-button" data-id="${user.id}" aria-label="Unlock ${fullName}">Unlock</button>` : ""}
        ${isSelf ? "" : `<button type="button" class="signout-user-button" data-id="${user.id}" aria-label="Sign Out ${fullName}">Sign Out</button>`}
        ${isSelf ? "" : `<button type="button" class="delete-user-button" data-id="${user.id}" aria-label="Delete ${fullName}">Delete</button>`}
      </div>
    `;
    listContainer.appendChild(card);
  });
}

function showConfirm(message, confirmLabel) {
  return new Promise(function (resolve) {
    const modal = document.getElementById("confirm-modal");
    document.getElementById("confirm-message").textContent = message;
    modal.style.display = "flex";

    const yesButton = document.getElementById("confirm-yes-button");
    const noButton = document.getElementById("confirm-no-button");
    const previouslyFocused = document.activeElement;
    yesButton.textContent = confirmLabel || "Disable";

    function onYes() { cleanup(true); }
    function onNo() { cleanup(false); }

    // Escape cancels, and Tab stays inside the dialog
    function onKeydown(event) {
      if (event.key === "Escape") {
        cleanup(false);
      } else if (event.key === "Tab") {
        event.preventDefault();
        (document.activeElement === noButton ? yesButton : noButton).focus();
      }
    }

    function cleanup(result) {
      modal.style.display = "none";
      yesButton.removeEventListener("click", onYes);
      noButton.removeEventListener("click", onNo);
      modal.removeEventListener("keydown", onKeydown);
      if (previouslyFocused && document.body.contains(previouslyFocused)) {
        previouslyFocused.focus();
      }
      resolve(result);
    }

    yesButton.addEventListener("click", onYes);
    noButton.addEventListener("click", onNo);
    modal.addEventListener("keydown", onKeydown);
    noButton.focus();
  });
}

function refreshList() {
  const query = searchInput.value.toLowerCase();
  let filtered = users.filter(function (user) {
    const fullName = (user.firstName + " " + user.lastName).toLowerCase();
    const matchesSearch = fullName.includes(query) || user.login.toLowerCase().includes(query);
    const matchesRole = roleFilter.value === "all" || user.role === roleFilter.value;
    const status = statusFilter.value;
    const matchesStatus = status === "all" ||
      (status === "active" && !user.isDisabled) ||
      (status === "disabled" && user.isDisabled) ||
      (status === "locked" && user.isLocked);
    return matchesSearch && matchesRole && matchesStatus;
  });

  const sortBy = sortSelect.value;

  if (sortBy === "lastName") {
    filtered.sort(function (a, b) {
      return a.lastName.localeCompare(b.lastName);
    });
  } else if (sortBy === "firstName") {
    filtered.sort(function (a, b) {
      return a.firstName.localeCompare(b.firstName);
    });
  } else if (sortBy === "recent") {
    filtered.sort(function (a, b) {
      return b.id - a.id;
    });
  }

  renderUsers(filtered);
}

const searchInput = document.getElementById("search-input");
const sortSelect = document.getElementById("sort-select");
const roleFilter = document.getElementById("role-filter");
const statusFilter = document.getElementById("status-filter");

loadUsers();

window.addEventListener("load", function () {
  setTimeout(function () {
    searchInput.value = "";
    refreshList();
  }, 150);
});

searchInput.addEventListener("input", function () {
  refreshList();
});

sortSelect.addEventListener("change", function () {
  refreshList();
});

roleFilter.addEventListener("change", function () {
  refreshList();
});

statusFilter.addEventListener("change", function () {
  refreshList();
});

document.getElementById("logout-button").addEventListener("click", function () {
  logOut(); // ends the server session too (js/common.js)
});

let passwordTargetUserId = null;
const passwordForm = document.getElementById("password-form");

document.getElementById("users-list").addEventListener("click", async function (event) {
  const clickedId = Number(event.target.getAttribute("data-id"));

  const clickedUser = users.find(function (u) { return u.id === clickedId; });

  if (event.target.classList.contains("toggle-disable-button")) {
    // Disabled accounts can now be re-enabled, so only disabling asks for confirmation.
    if (!clickedUser.isDisabled) {
      const confirmed = await showConfirm("Disable this user? They'll be signed out right away and won't be able to log in until re-enabled.");
      if (!confirmed) {
        return;
      }
    }

    try {
      const result = await apiRequest("DisableUser.php", {
        method: "POST",
        body: { targetUserId: clickedId, disabled: !clickedUser.isDisabled }
      });

      if (!result.ok) {
        showToast(errorMessage(result), "error");
        return;
      }

      await loadUsers();
    } catch (err) {
      console.error("Could not disable user.");
    }
  }

  if (event.target.classList.contains("toggle-admin-button")) {
    const promote = clickedUser.role !== "Admin";
    const confirmed = await showConfirm(
      promote ? "Make this user an admin? Admins can manage every account." : "Remove this user's admin rights?",
      promote ? "Promote" : "Demote"
    );
    if (confirmed) {
      await runAdminAction("SetUserRole.php", { targetUserId: clickedId, role: promote ? "Admin" : "User" });
    }
  }

  if (event.target.classList.contains("unlock-button")) {
    await runAdminAction("UnlockUser.php", { targetUserId: clickedId });
  }

  if (event.target.classList.contains("signout-user-button")) {
    if (await showConfirm("Sign this user out on every device?", "Sign Out")) {
      await runAdminAction("LogoutUser.php", { targetUserId: clickedId });
    }
  }

  if (event.target.classList.contains("delete-user-button")) {
    if (await showConfirm("Permanently delete " + clickedUser.login + " and all their contacts? This can't be undone.", "Delete")) {
      await runAdminAction("DeleteUser.php", { targetUserId: clickedId });
    }
  }

  if (event.target.classList.contains("change-password-button")) {
    passwordTargetUserId = clickedId;
    document.getElementById("password-message").textContent = "";
    passwordForm.style.display = "block";
    document.getElementById("new-password").focus();
  }
});

document.getElementById("cancel-password-button").addEventListener("click", function () {
  passwordForm.style.display = "none";
  document.getElementById("new-password").value = "";
});

document.getElementById("save-password-button").addEventListener("click", async function () {
  const newPassword = document.getElementById("new-password").value;
  const messageBox = document.getElementById("password-message");

  try {
    // Leaving the password empty makes the server generate a temporary one.
    const result = await apiRequest("ChangePassword.php", {
      method: "POST",
      body: {
        targetUserId: passwordTargetUserId,
        newPassword: newPassword,
        mustChangePassword: document.getElementById("must-change-password").checked
      }
    });
    const data = result.data;

    if (!result.ok) {
      messageBox.textContent = errorMessage(result);
      messageBox.className = "form-message form-message-error";
      return;
    }

    if (data.temporaryPassword) {
      // Shown once: the admin passes it on to the user.
      messageBox.textContent = "Temporary password: " + data.temporaryPassword + " (copy it now, it won't be shown again)";
      messageBox.className = "form-message form-message-success";
      document.getElementById("new-password").value = "";
      await loadUsers();
      return;
    }

    messageBox.textContent = "Password updated!";
    messageBox.className = "form-message form-message-success";
    await loadUsers();

    setTimeout(function () {
      passwordForm.style.display = "none";
      document.getElementById("new-password").value = "";
      messageBox.textContent = "";
      messageBox.className = "form-message";
    }, 1200);
  } catch (err) {
    messageBox.textContent = "Could not change password.";
    messageBox.className = "form-message form-message-error";
  }
});


// Send one admin action (promote, unlock, sign out, delete...) and refresh the list.
async function runAdminAction(endpoint, body) {
  const result = await apiRequest(endpoint, { method: "POST", body: body });
  if (!result.ok) {
    showToast(errorMessage(result), "error");
    return false;
  }
  showToast("Done");
  await loadUsers();
  return true;
}
