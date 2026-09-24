// Check if someone's actually logged in; if not, send them back to login
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
}

// Show their name in the header
document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;

const API_BASE = "http://137.184.210.119/api";
let contacts = [];

async function loadContacts(term = "") {
  try {
    const response = await fetch(`${API_BASE}/SearchContacts.php?userId=${currentUser.id}&term=${encodeURIComponent(term)}`);
    const data = await response.json();

    contacts = data.map(function (contact) {
      return {
        id: contact.ID,
        firstName: contact.FirstName,
        lastName: contact.LastName,
        cell: contact.Cell,
        email: contact.Email
      };
    });

    renderContacts(contacts);
  } catch (err) {
    document.getElementById("contacts-list").innerHTML = "<p>Could not load contacts.</p>";
  }
}

loadContacts();

function renderContacts(contactsToShow) {
  const listContainer = document.getElementById("contacts-list");
  listContainer.innerHTML = "";

  if (contactsToShow.length === 0) {
    listContainer.innerHTML = "<p>No contacts found.</p>";
    return;
  }

  contactsToShow.forEach(function (contact) {
    const card = document.createElement("div");
    card.className = "contact-card";
    card.innerHTML = `
      <div class="contact-info">
        <h3>${contact.firstName} ${contact.lastName}</h3>
        <p>${contact.cell} &middot; ${contact.email}</p>
      </div>
      <div class="contact-actions">
        <button class="edit-button" data-id="${contact.id}">Edit</button>
        <button class="delete-button" data-id="${contact.id}">Delete</button>
      </div>
    `;
    listContainer.appendChild(card);
  });
}

const searchInput = document.getElementById("search-input");

searchInput.addEventListener("input", function () {
  const query = searchInput.value.toLowerCase();

  const filtered = contacts.filter(function (contact) {
    const fullName = (contact.firstName + " " + contact.lastName).toLowerCase();
    return fullName.includes(query);
  });

  renderContacts(filtered);
});

document.getElementById("logout-button").addEventListener("click", function () {
  sessionStorage.removeItem("currentUser");
  window.location.href = "login.html";
});

const addContactButton = document.getElementById("add-contact-button");
const addContactForm = document.getElementById("add-contact-form");
const cancelAddButton = document.getElementById("cancel-add-button");
const saveContactButton = document.getElementById("save-contact-button");

let editingContactId = null;

addContactButton.addEventListener("click", function () {
  editingContactId = null;
  addContactForm.style.display = "block";
});

cancelAddButton.addEventListener("click", function () {
  addContactForm.style.display = "none";
});

saveContactButton.addEventListener("click", async function () {
  const firstName = document.getElementById("new-firstName").value;
  const lastName = document.getElementById("new-lastName").value;
  const cell = document.getElementById("new-cell").value;
  const email = document.getElementById("new-email").value;

  if (!firstName || !lastName) {
    return;
  }

  if (editingContactId === null) {
    // Adding a new contact
    try {
      const response = await fetch(`${API_BASE}/AddContact.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          userId: currentUser.id,
          firstName: firstName,
          lastName: lastName,
          cell: cell,
          email: email
        })
      });

      const data = await response.json();

      if (!response.ok) {
        console.error(data.error);
        return;
      }

      await loadContacts();
    } catch (err) {
      console.error("Could not add contact.");
      return;
    }
  } else {
    // Editing an existing contact
    try {
      const response = await fetch(`${API_BASE}/EditContact.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: editingContactId,
          userId: currentUser.id,
          firstName: firstName,
          lastName: lastName,
          cell: cell,
          email: email
        })
      });

      const data = await response.json();

      if (!response.ok) {
        console.error(data.error);
        return;
      }

      editingContactId = null;
      await loadContacts();
    } catch (err) {
      console.error("Could not update contact.");
      return;
    }
  }

  document.getElementById("new-firstName").value = "";
  document.getElementById("new-lastName").value = "";
  document.getElementById("new-cell").value = "";
  document.getElementById("new-email").value = "";
  addContactForm.style.display = "none";
});

document.getElementById("contacts-list").addEventListener("click", async function (event) {
  if (event.target.classList.contains("delete-button")) {
    const idToDelete = Number(event.target.getAttribute("data-id"));

    try {
      const response = await fetch(`${API_BASE}/DeleteContact.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: idToDelete, userId: currentUser.id })
      });

      const data = await response.json();

      if (!response.ok) {
        console.error(data.error);
        return;
      }

      await loadContacts();
    } catch (err) {
      console.error("Could not delete contact.");
      return;
    }
  }

  if (event.target.classList.contains("edit-button")) {
    const idToEdit = Number(event.target.getAttribute("data-id"));
    const contactToEdit = contacts.find(function (contact) {
      return contact.id === idToEdit;
    });

    document.getElementById("new-firstName").value = contactToEdit.firstName;
    document.getElementById("new-lastName").value = contactToEdit.lastName;
    document.getElementById("new-cell").value = contactToEdit.cell;
    document.getElementById("new-email").value = contactToEdit.email;

    editingContactId = idToEdit;
    addContactForm.style.display = "block";
  }
});