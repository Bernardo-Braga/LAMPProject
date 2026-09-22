// Check if someone's actually logged in; if not, send them back to login
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
}

// Show their name in the header
document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;

// Mock contact data — stand-in until the real API endpoint exists
let contacts = [
  { id: 1, firstName: "Ben", lastName: "Brown", cell: "(976) 395-9768", email: "bennieB@example.com" },
  { id: 2, firstName: "Chloe", lastName: "Carter", cell: "(467) 864-7732", email: "ccgirlie@example.com" },
  { id: 3, firstName: "Danny", lastName: "Evans", cell: "(348) 284-9733", email: "dantheman@example.com" }
];

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

renderContacts(contacts);

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

saveContactButton.addEventListener("click", function () {
  const firstName = document.getElementById("new-firstName").value;
  const lastName = document.getElementById("new-lastName").value;
  const cell = document.getElementById("new-cell").value;
  const email = document.getElementById("new-email").value;

  if (!firstName || !lastName) {
    return;
  }

  if (editingContactId === null) {
    // Adding a new contact
    const newContact = {
      id: Date.now(),
      firstName: firstName,
      lastName: lastName,
      cell: cell,
      email: email
    };
    contacts.push(newContact);
  } else {
    // Editing an existing contact
    const contactToEdit = contacts.find(function (contact) {
      return contact.id === editingContactId;
    });
    contactToEdit.firstName = firstName;
    contactToEdit.lastName = lastName;
    contactToEdit.cell = cell;
    contactToEdit.email = email;
    editingContactId = null;
  }

  renderContacts(contacts);

  document.getElementById("new-firstName").value = "";
  document.getElementById("new-lastName").value = "";
  document.getElementById("new-cell").value = "";
  document.getElementById("new-email").value = "";
  addContactForm.style.display = "none";
});

document.getElementById("contacts-list").addEventListener("click", function (event) {
  if (event.target.classList.contains("delete-button")) {
    const idToDelete = Number(event.target.getAttribute("data-id"));
    contacts = contacts.filter(function (contact) {
      return contact.id !== idToDelete;
    });
    renderContacts(contacts);
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