// Check login + confirm this user is actually an admin
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
}

document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;

// Mock user data — stand-in until the real admin API endpoints exist
let users = [
  { id: 1, firstName: "Firstname", lastName: "Lastname", login: "admin", role: "Admin", isDisabled: false },
  { id: 2, firstName: "Harry", lastName: "Potter", login: "HarryP", role: "User", isDisabled: false },
  { id: 3, firstName: "Peter", lastName: "Parker", login: "Spiderman", role: "User", isDisabled: true }
];

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
        <button class="toggle-disable-button" data-id="${user.id}">
          ${user.isDisabled ? "Enable" : "Disable"}
        </button>
        <button class="toggle-admin-button" data-id="${user.id}">
          ${user.role === "Admin" ? "Demote" : "Promote to Admin"}
        </button>
        <button class="change-password-button" data-id="${user.id}">Change Password</button>
      </div>
    `;
    listContainer.appendChild(card);
  });
}

renderUsers(users);

const searchInput = document.getElementById("search-input");

searchInput.addEventListener("input", function () {
  const query = searchInput.value.toLowerCase();

  const filtered = users.filter(function (user) {
    const fullName = (user.firstName + " " + user.lastName).toLowerCase();
    return fullName.includes(query) || user.login.toLowerCase().includes(query);
  });

  renderUsers(filtered);
});

document.getElementById("logout-button").addEventListener("click", function () {
  sessionStorage.removeItem("currentUser");
  window.location.href = "login.html";
});

let passwordTargetUserId = null;
const passwordForm = document.getElementById("password-form");

document.getElementById("users-list").addEventListener("click", function (event) {
  const clickedId = Number(event.target.getAttribute("data-id"));

  if (event.target.classList.contains("toggle-disable-button")) {
    const user = users.find(function (u) { return u.id === clickedId; });
    user.isDisabled = !user.isDisabled;
    renderUsers(users);
  }

  if (event.target.classList.contains("toggle-admin-button")) {
    const user = users.find(function (u) { return u.id === clickedId; });
    user.role = (user.role === "Admin") ? "User" : "Admin";
    renderUsers(users);
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

document.getElementById("save-password-button").addEventListener("click", function () {
  const newPassword = document.getElementById("new-password").value;

  if (!newPassword) {
    return;
  }

  console.log("Would update password for user id " + passwordTargetUserId + " to: " + newPassword);

  passwordForm.style.display = "none";
  document.getElementById("new-password").value = "";
});
