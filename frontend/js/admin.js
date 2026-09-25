// Check login + confirm this user is actually an admin
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
}

document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;

const API_BASE = "http://137.184.210.119/api";
let users = [];

async function loadUsers() {
  try {
    const response = await fetch(`${API_BASE}/ListUsers.php?adminId=${currentUser.id}&term=`);
    const data = await response.json();

    users = data.map(function (user) {
      return {
        id: user.ID,
        firstName: user.FirstName,
        lastName: user.LastName,
        login: user.Login,
        role: user.Role,
        isDisabled: Boolean(user.IsDisabled)
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

  if (usersToShow.length === 0) {
    listContainer.innerHTML = "<p>No users found.</p>";
    return;
  }

  usersToShow.forEach(function (user) {
    const card = document.createElement("div");
    card.className = "user-card";

    let badges = "";
    if (user.role === "Admin") {
      badges += `<span class="badge badge-admin">Admin</span>`;
    }
    if (user.isDisabled) {
      badges += `<span class="badge badge-disabled">Disabled</span>`;
    }

    card.innerHTML = `
      <div class="user-info">
        <h3>${user.firstName} ${user.lastName} ${badges}</h3>
        <p>${user.login}</p>
      </div>
      <div class="user-actions">
        <button class="toggle-disable-button" data-id="${user.id}" ${user.isDisabled ? "disabled" : ""}>
          ${user.isDisabled ? "Disabled" : "Disable"}
        </button>
        <button class="toggle-admin-button" data-id="${user.id}" disabled>
          ${user.role === "Admin" ? "Demote" : "Promote to Admin"}
        </button>
        <button class="change-password-button" data-id="${user.id}">Change Password</button>
      </div>
    `;
    listContainer.appendChild(card);
  });
}

function showConfirm(message) {
  return new Promise(function (resolve) {
    const modal = document.getElementById("confirm-modal");
    document.getElementById("confirm-message").textContent = message;
    modal.style.display = "flex";

    const yesButton = document.getElementById("confirm-yes-button");
    const noButton = document.getElementById("confirm-no-button");

    function onYes() { cleanup(true); }
    function onNo() { cleanup(false); }

    function cleanup(result) {
      modal.style.display = "none";
      yesButton.removeEventListener("click", onYes);
      noButton.removeEventListener("click", onNo);
      resolve(result);
    }

    yesButton.addEventListener("click", onYes);
    noButton.addEventListener("click", onNo);
  });
}

function refreshList() {
  const query = searchInput.value.toLowerCase();
  let filtered = users.filter(function (user) {
    const fullName = (user.firstName + " " + user.lastName).toLowerCase();
    return fullName.includes(query) || user.login.toLowerCase().includes(query);
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

document.getElementById("logout-button").addEventListener("click", function () {
  sessionStorage.removeItem("currentUser");
  window.location.href = "login.html";
});

let passwordTargetUserId = null;
const passwordForm = document.getElementById("password-form");

document.getElementById("users-list").addEventListener("click", async function (event) {
  const clickedId = Number(event.target.getAttribute("data-id"));

  if (event.target.classList.contains("toggle-disable-button")) {
    const confirmed = await showConfirm("Disable this user? They won't be able to log in, and this can't be undone from here.");
    if (!confirmed) {
      return;
    }

    try {
      const response = await fetch(`${API_BASE}/DisableUser.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ adminId: currentUser.id, targetUserId: clickedId })
      });

      const data = await response.json();

      if (!response.ok) {
        console.error(data.error);
        return;
      }

      await loadUsers();
    } catch (err) {
      console.error("Could not disable user.");
    }
  }

  if (event.target.classList.contains("change-password-button")) {
    passwordTargetUserId = clickedId;
    passwordForm.style.display = "block";
  }
});

document.getElementById("cancel-password-button").addEventListener("click", function () {
  passwordForm.style.display = "none";
  document.getElementById("new-password").value = "";
});

document.getElementById("save-password-button").addEventListener("click", async function () {
  const newPassword = document.getElementById("new-password").value;
  const messageBox = document.getElementById("password-message");

  if (!newPassword) {
    return;
  }

  try {
    const response = await fetch(`${API_BASE}/ChangePassword.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        adminId: currentUser.id,
        targetUserId: passwordTargetUserId,
        newPassword: newPassword
      })
    });

    const data = await response.json();

    if (!response.ok) {
      messageBox.textContent = data.error;
      messageBox.className = "form-message form-message-error";
      return;
    }

    messageBox.textContent = "Password updated!";
    messageBox.className = "form-message form-message-success";

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
