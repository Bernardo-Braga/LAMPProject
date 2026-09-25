const loginForm = document.getElementById("login-form");

if (loginForm) {
  loginForm.addEventListener("submit", function (event) {
    event.preventDefault();
    const login = document.getElementById("login").value;
    const password = document.getElementById("password").value;
    const messageBox = document.getElementById("form-message");

    fetch("/api/Login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ login: login, password: password })
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        if (result.status === 200) {
          sessionStorage.setItem("currentUser", JSON.stringify(result.data));

          document.getElementById("login-card").innerHTML = `
            <div class="success-state">
              <div class="success-icon">&#10003;</div>
              <h1>You're in!</h1>
              <p class="subtitle">Redirecting to your contacts...</p>
            </div>
          `;

          setTimeout(function () {
            // TEMPORARY: hardcoded admin check until Login.php returns a real Role field
            if (login === "root") {
              window.location.href = "admin.html";
            } else {
              window.location.href = "contacts.html";
            }
          }, 1000);
        } else {
          document.getElementById("login-card").innerHTML = `
            <div class="error-state">
              <div class="error-icon">&#10005;</div>
              <h1>Login Failed</h1>
              <p class="subtitle">${result.data.error}</p>
              <button onclick="location.reload()" class="button button-primary">Try Again</button>
            </div>
          `;
        }
      });
  });
}

const registerForm = document.getElementById("register-form");

if (registerForm) {
  registerForm.addEventListener("submit", function (event) {
    event.preventDefault();
    const firstName = document.getElementById("firstName").value;
    const lastName = document.getElementById("lastName").value;
    const login = document.getElementById("login").value;
    const password = document.getElementById("password").value;
    const messageBox = document.getElementById("form-message");

    fetch("/api/Register.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        firstName: firstName,
        lastName: lastName,
        login: login,
        password: password
      })
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        if (result.status === 201) {
          messageBox.textContent = "Account created! Redirecting to sign in...";
          messageBox.style.color = "green";

          setTimeout(function () {
            window.location.href = "login.html";
          }, 1000);
        } else {
          messageBox.textContent = result.data.error;
          messageBox.style.color = "red";
        }
      });
  });
}