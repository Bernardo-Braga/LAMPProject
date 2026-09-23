const loginForm = document.getElementById("login-form");

if (loginForm) {
  loginForm.addEventListener("submit", function (event) {
    event.preventDefault();
    const login = document.getElementById("login").value;
    const password = document.getElementById("password").value;
    const messageBox = document.getElementById("form-message");

    fetch("http://137.184.210.119/api/Login.php", {
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
          messageBox.textContent = "Login successful! Redirecting...";
          messageBox.style.color = "green";

          sessionStorage.setItem("currentUser", JSON.stringify(result.data));

          setTimeout(function () {
            // TEMPORARY: hardcoded admin check until Login.php returns a real Role field
            if (login === "root") {
              window.location.href = "admin.html";
            } else {
              window.location.href = "contacts.html";
            }
          }, 1000);
        } else {
          messageBox.textContent = result.data.error;
          messageBox.style.color = "red";
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

    fetch("http://137.184.210.119/api/Register.php", {
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