// Account page: change your name or password, or delete your account.

const currentUser = getCurrentUser();

if (!currentUser) {
  window.location.href = "login.html";
}

document.getElementById("welcome-message").textContent = "Welcome, " + currentUser.firstName;
renderNav("account");

document.getElementById("logout-button").addEventListener("click", function () {
  logOut();
});

// When an admin reset the password, only the password form is usable until it's changed.
const forced = new URLSearchParams(location.search).has("forced") || currentUser.mustChangePassword;
if (forced) {
  document.getElementById("forced-banner").style.display = "block";
  document.getElementById("profile-panel").style.display = "none";
  document.getElementById("danger-panel").style.display = "none";
  document.getElementById("current-password-label").textContent = "Temporary Password";
  const nav = document.querySelector(".main-nav");
  if (nav) nav.style.display = "none";
  document.getElementById("current-password").focus();
}

function setMessage(id, text, ok) {
  const box = document.getElementById(id);
  box.textContent = text;
  box.className = "form-message " + (ok ? "form-message-success" : "form-message-error");
}

// Refresh the saved user so names and flags stay current.
function saveUser(user) {
  const updated = Object.assign({}, currentUser, user, { csrfToken: currentUser.csrfToken });
  sessionStorage.setItem("currentUser", JSON.stringify(updated));
  return updated;
}

async function loadAccount() {
  const result = await apiRequest("Me.php");
  if (!result.ok) return;
  const me = saveUser(result.data);
  document.getElementById("firstName").value = me.firstName;
  document.getElementById("lastName").value = me.lastName;
  document.getElementById("account-summary").textContent =
    "@" + me.login + " · " + (me.role === "Admin" ? "Administrator" : "Member") + " since " + formatDate(me.dateCreated) +
    (me.lastLoginAt ? " · last sign-in " + timeAgo(me.lastLoginAt) : "");
}

document.getElementById("save-profile-button").addEventListener("click", async function () {
  const result = await apiRequest("UpdateProfile.php", {
    method: "POST",
    body: { firstName: document.getElementById("firstName").value, lastName: document.getElementById("lastName").value }
  });
  if (!result.ok) {
    setMessage("profile-message", errorMessage(result), false);
    return;
  }
  saveUser(result.data);
  document.getElementById("welcome-message").textContent = "Welcome, " + result.data.firstName;
  setMessage("profile-message", "Profile saved.", true);
});

document.getElementById("save-password-button").addEventListener("click", async function () {
  const newPassword = document.getElementById("new-password").value;
  if (newPassword !== document.getElementById("confirm-password").value) {
    setMessage("password-message", "The new passwords don't match.", false);
    return;
  }
  const result = await apiRequest("UpdateMyPassword.php", {
    method: "POST",
    body: { currentPassword: document.getElementById("current-password").value, newPassword: newPassword }
  });
  if (!result.ok) {
    setMessage("password-message", errorMessage(result), false);
    return;
  }
  saveUser(result.data);
  ["current-password", "new-password", "confirm-password"].forEach(function (id) {
    document.getElementById(id).value = "";
  });
  setMessage("password-message", "Password changed.", true);
  if (forced) {
    setTimeout(function () {
      window.location.href = currentUser.role === "Admin" ? "admin.html" : "dashboard.html";
    }, 800);
  }
});

document.getElementById("delete-account-button").addEventListener("click", async function () {
  if (!confirm("Delete your account and all your contacts? This can't be undone.")) {
    return;
  }
  const result = await apiRequest("DeleteAccount.php", {
    method: "POST",
    body: { password: document.getElementById("delete-password").value }
  });
  if (!result.ok) {
    setMessage("delete-message", errorMessage(result), false);
    return;
  }
  sessionStorage.removeItem("currentUser");
  window.location.href = "login.html";
});

loadAccount();
