// Check if someone's actually logged in; if not, send them back to login
const currentUser = JSON.parse(sessionStorage.getItem("currentUser"));

if (!currentUser) {
  window.location.href = "login.html";
}

// Show their name in the header
document.getElementById("welcome-message").textContent =
  "Welcome, " + currentUser.firstName;
renderNav("contacts");

const API_BASE = "/api";
let contacts = [];

let tags = [];              // your tags, for the filter chips and the form
let currentView = "all";    // all | mine | shared | favorites
let currentTagId = null;    // filter by one tag
let pendingPhoto = null;    // photo chosen in the form, uploaded after saving
let removePhoto = false;

// Favorites are saved on the server now (FavoriteContact.php), so they follow you to other devices.
function isFavorite(id) {
  const contact = contacts.find(function (c) { return c.id === id; });
  return Boolean(contact && contact.isFavorite);
}

async function toggleFavorite(id) {
  const contact = contacts.find(function (c) { return c.id === id; });
  const result = await apiRequest("FavoriteContact.php", {
    method: "POST",
    body: { id: id, favorite: !contact.isFavorite }
  });
  if (result.ok) {
    contact.isFavorite = !contact.isFavorite;
  } else {
    showToast(errorMessage(result), "error");
  }
}

function matchesView(contact) {
  if (currentView === "mine") return contact.access === "owner";
  if (currentView === "shared") return contact.access !== "owner";
  if (currentView === "favorites") return contact.isFavorite;
  return true;
}

function matchesTag(contact) {
  return currentTagId === null || contact.tags.some(function (tag) { return tag.ID === currentTagId; });
}

const searchInput = document.getElementById("search-input");
const sortSelect = document.getElementById("sort-select");

function getDisplayedContacts() {
  const query = searchInput.value.toLowerCase();

  let filtered = contacts.filter(function (contact) {
    const fullName = (contact.firstName + " " + contact.lastName).toLowerCase();
    const details = [contact.company, contact.jobTitle, contact.email, contact.cell].join(" ").toLowerCase();
    return (fullName.includes(query) || details.includes(query)) && matchesView(contact) && matchesTag(contact);
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

    filtered.sort(function (a, b) {
      return (isFavorite(b.id) ? 1 : 0) - (isFavorite(a.id) ? 1 : 0);
    });

  return filtered;
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

async function loadContacts(term = "") {
  document.getElementById("contacts-list").innerHTML = "<p>Loading contacts...</p>";
  try {
    // The server knows who is signed in, so no userId is sent (see js/common.js for apiRequest).
    const result = await apiRequest(`SearchContacts.php?term=${encodeURIComponent(term)}`);
    const data = result.data;

    contacts = data.map(function (contact) {
      return {
        id: contact.ID,
        firstName: contact.FirstName,
        lastName: contact.LastName,
        cell: contact.Cell,
        email: contact.Email,
        company: contact.Company,
        jobTitle: contact.JobTitle,
        address: contact.Address,
        birthday: contact.Birthday,
        notes: contact.Notes,
        photoUrl: contact.PhotoUrl,
        isFavorite: contact.IsFavorite,
        tags: contact.Tags,
        access: contact.Access,              // owner | edit | view
        ownerName: contact.OwnerName,        // set when someone shared it with you
        sharedWithCount: contact.SharedWithCount
      };
    });

    renderContacts(getDisplayedContacts());
  } catch (err) {
    document.getElementById("contacts-list").innerHTML = "<p>Could not load contacts.</p>";
  }
}

loadContacts();

function renderContacts(contactsToShow) {
  const listContainer = document.getElementById("contacts-list");
  listContainer.innerHTML = "";

  document.getElementById("contact-count").textContent =
    contacts.length + (contacts.length === 1 ? " contact" : " contacts");

  if (contactsToShow.length === 0) {
    listContainer.innerHTML = "<p>No contacts found.</p>";
    return;
  }

  contactsToShow.forEach(function (contact) {
    const card = document.createElement("div");
    card.className = "contact-card";
    const fullName = contact.firstName + " " + contact.lastName;
    const work = [contact.jobTitle, contact.company].filter(Boolean).join(" at ");
    const reach = [contact.cell, contact.email].filter(Boolean).map(escapeHtml).join(" &middot; ");
    const tagChips = contact.tags.map(function (tag) {
      return `<span class="tag"><span class="dot" style="background:${escapeHtml(tag.Color)}"></span>${escapeHtml(tag.Name)}</span>`;
    }).join("");
    let sharing = "";
    if (contact.ownerName) {
      sharing = `<span class="badge badge-shared">Shared by ${escapeHtml(contact.ownerName)} &middot; ${contact.access === "edit" ? "can edit" : "view only"}</span>`;
    } else if (contact.sharedWithCount) {
      sharing = `<span class="badge badge-admin">Shared with ${contact.sharedWithCount}</span>`;
    }
    const canEdit = contact.access === "owner" || contact.access === "edit";
    const isOwner = contact.access === "owner";

    // Everything people typed goes through escapeHtml() so it can't run as code.
    card.innerHTML = `
      ${avatarHtml(fullName, contact.photoUrl)}
      <div class="contact-info">
        <h3>${escapeHtml(fullName)}</h3>
        ${work ? `<p>${escapeHtml(work)}</p>` : ""}
        ${reach ? `<p>${reach}</p>` : ""}
        ${contact.birthday ? `<p>Birthday: ${escapeHtml(formatDate(contact.birthday, { month: "long", day: "numeric" }))}</p>` : ""}
        ${tagChips || sharing ? `<div class="contact-meta">${tagChips}${sharing}</div>` : ""}
      </div>
      <div class="contact-actions">
        <button class="star-button ${isFavorite(contact.id) ? "favorited" : ""}" data-id="${contact.id}" aria-label="Favorite">★</button>
        ${canEdit ? `<button class="edit-button" data-id="${contact.id}">Edit</button>` : ""}
        ${isOwner ? `<button class="share-button" data-id="${contact.id}">Share</button>` : ""}
        ${isOwner ? `<button class="delete-button" data-id="${contact.id}">Delete</button>` : `<button class="leave-button" data-id="${contact.id}">Remove</button>`}
      </div>
    `;
    listContainer.appendChild(card);
  });
}

searchInput.addEventListener("input", function () {
  renderContacts(getDisplayedContacts());
});

sortSelect.addEventListener("change", function () {
  renderContacts(getDisplayedContacts());
});

document.getElementById("logout-button").addEventListener("click", function () {
  logOut(); // ends the server session too (js/common.js)
});

const addContactButton = document.getElementById("add-contact-button");
const addContactForm = document.getElementById("add-contact-form");
const cancelAddButton = document.getElementById("cancel-add-button");
const saveContactButton = document.getElementById("save-contact-button");

let editingContactId = null;

addContactButton.addEventListener("click", function () {
  editingContactId = null;
  clearContactForm();
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
  const extraFields = {
    company: document.getElementById("new-company").value,
    jobTitle: document.getElementById("new-jobTitle").value,
    address: document.getElementById("new-address").value,
    birthday: document.getElementById("new-birthday").value,
    notes: document.getElementById("new-notes").value
  };
  const editing = contacts.find(function (c) { return c.id === editingContactId; });
  if (!editing || editing.access === "owner") {
    // Only the owner can tag a contact (tags are their private labels).
    extraFields.tagIds = Array.from(document.querySelectorAll("#tag-picker input:checked")).map(function (box) {
      return Number(box.value);
    });
  }
  let savedId = editingContactId;

  const addMessageBox = document.getElementById("add-contact-message");
  addMessageBox.textContent = "";

  if (!firstName || !lastName) {
    addMessageBox.textContent = "First and last name are required.";
    return;
  }

  if (editingContactId === null) {
    // Adding a new contact
    try {
      const result = await apiRequest("AddContact.php", {
        method: "POST",
        body: Object.assign({
          firstName: firstName,
          lastName: lastName,
          cell: cell,
          email: email
        }, extraFields)
      });

      if (!result.ok) {
        addMessageBox.textContent = errorMessage(result);
        return;
      }
      savedId = result.data.id;
    } catch (err) {
      console.error("Could not add contact.");
      return;
    }
  } else {
    // Editing an existing contact
    try {
      const result = await apiRequest("EditContact.php", {
        method: "POST",
        body: Object.assign({
          id: editingContactId,
          firstName: firstName,
          lastName: lastName,
          cell: cell,
          email: email
        }, extraFields)
      });

      if (!result.ok) {
        addMessageBox.textContent = errorMessage(result);
        return;
      }

      editingContactId = null;
    } catch (err) {
      console.error("Could not update contact.");
      return;
    }
  }

  await savePhoto(savedId, editing);
  await Promise.all([loadContacts(), loadTags()]);
  showToast(editing ? "Contact updated" : "Contact added");

  clearContactForm();
  addContactForm.style.display = "none";
});

document.getElementById("contacts-list").addEventListener("click", async function (event) {
  if (event.target.classList.contains("star-button")) {
    const idToToggle = Number(event.target.getAttribute("data-id"));
    await toggleFavorite(idToToggle);
    renderContacts(getDisplayedContacts());
  }

  if (event.target.classList.contains("share-button")) {
    openShareModal(Number(event.target.getAttribute("data-id")));
  }

  if (event.target.classList.contains("leave-button")) {
    const idToLeave = Number(event.target.getAttribute("data-id"));
    const confirmed = await showConfirm("Remove this shared contact from your list? The owner keeps it.");
    if (confirmed) {
      const result = await apiRequest("UnshareContact.php", { method: "POST", body: { id: idToLeave, userId: currentUser.id } });
      if (result.ok) {
        await loadContacts();
      } else {
        showToast(errorMessage(result), "error");
      }
    }
  }
  
  if (event.target.classList.contains("delete-button")) {
    const idToDelete = Number(event.target.getAttribute("data-id"));

    const confirmed = await showConfirm("Delete this contact? This can't be undone.");
    if (!confirmed) {
      return;
    }

    try {
      const result = await apiRequest("DeleteContact.php", {
        method: "POST",
        body: { id: idToDelete }
      });

      if (!result.ok) {
        showToast(errorMessage(result), "error");
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

    clearContactForm(contactToEdit);
    document.getElementById("new-firstName").value = contactToEdit.firstName;
    document.getElementById("new-lastName").value = contactToEdit.lastName;
    document.getElementById("new-cell").value = contactToEdit.cell || "";
    document.getElementById("new-email").value = contactToEdit.email || "";
    document.getElementById("new-company").value = contactToEdit.company || "";
    document.getElementById("new-jobTitle").value = contactToEdit.jobTitle || "";
    document.getElementById("new-address").value = contactToEdit.address || "";
    document.getElementById("new-birthday").value = contactToEdit.birthday || "";
    document.getElementById("new-notes").value = contactToEdit.notes || "";

    editingContactId = idToEdit;
    addContactForm.style.display = "block";
    addContactForm.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});

// ===========================================================================
// Tags, views, photos, sharing and CSV import
// ===========================================================================

// Reset the add/edit form. When editing, `contact` fills the tag checkboxes and photo preview.
function clearContactForm(contact) {
  ["new-firstName", "new-lastName", "new-cell", "new-email", "new-company", "new-jobTitle",
    "new-address", "new-birthday", "new-notes"].forEach(function (id) {
    document.getElementById(id).value = "";
  });
  document.getElementById("add-contact-message").textContent = "";
  document.getElementById("new-birthday").max = new Date().toISOString().slice(0, 10);

  const isOwner = !contact || contact.access === "owner";
  document.getElementById("tag-picker-group").style.display = isOwner ? "block" : "none";
  const selected = contact ? contact.tags.map(function (tag) { return tag.ID; }) : [];
  document.getElementById("tag-picker").innerHTML = tags.length
    ? tags.map(function (tag) {
        return `<label class="tag-option"><input type="checkbox" value="${tag.ID}" ${selected.includes(tag.ID) ? "checked" : ""}>
          <span class="dot" style="background:${escapeHtml(tag.Color)}"></span>${escapeHtml(tag.Name)}</label>`;
      }).join("")
    : `<span class="subtitle">No tags yet. Create some with the Tags button.</span>`;

  pendingPhoto = null;
  removePhoto = false;
  document.getElementById("new-photo").value = "";
  showPhotoPreview(contact ? contact.photoUrl : null, contact ? contact.firstName + " " + contact.lastName : "");
}

function showPhotoPreview(url, name) {
  document.getElementById("photo-preview").innerHTML = avatarHtml(name || "?", url);
  document.getElementById("remove-photo-button").style.display = url ? "inline-flex" : "none";
}

document.getElementById("new-photo").addEventListener("change", function (event) {
  const file = event.target.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast("That photo is larger than 2 MB", "error");
    event.target.value = "";
    return;
  }
  pendingPhoto = file;
  removePhoto = false;
  showPhotoPreview(URL.createObjectURL(file), "");
});

document.getElementById("remove-photo-button").addEventListener("click", function () {
  pendingPhoto = null;
  removePhoto = true;
  document.getElementById("new-photo").value = "";
  showPhotoPreview(null, document.getElementById("new-firstName").value);
});

// Upload (or remove) the photo after the contact itself has been saved.
async function savePhoto(contactId, editingContact) {
  if (!contactId) return;
  if (pendingPhoto) {
    const form = new FormData();
    form.append("id", contactId);
    form.append("photo", pendingPhoto);
    const result = await apiRequest("UploadContactPhoto.php", { method: "POST", form: form });
    if (!result.ok) showToast("Saved, but the photo failed: " + errorMessage(result), "error");
  } else if (removePhoto && editingContact && editingContact.photoUrl) {
    await apiRequest("DeleteContactPhoto.php", { method: "POST", body: { id: contactId } });
  }
}

// ---- Views: All / Mine / Shared with me / Favorites ----

document.querySelectorAll("#view-buttons button").forEach(function (button) {
  button.addEventListener("click", function () {
    currentView = button.getAttribute("data-view");
    document.querySelectorAll("#view-buttons button").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b === button));
    });
    renderContacts(getDisplayedContacts());
  });
});

// ---- Tags ----

async function loadTags() {
  const result = await apiRequest("ListTags.php");
  tags = result.ok ? result.data : [];
  renderTagFilter();
}

function renderTagFilter() {
  const container = document.getElementById("tag-filter");
  if (!tags.length) {
    container.innerHTML = "";
    return;
  }
  container.innerHTML = `<button class="chip" data-tag="" aria-pressed="${currentTagId === null}">All tags</button>` +
    tags.map(function (tag) {
      return `<button class="chip" data-tag="${tag.ID}" aria-pressed="${currentTagId === tag.ID}">
        <span class="dot" style="background:${escapeHtml(tag.Color)}"></span>${escapeHtml(tag.Name)}
        <span class="chip-count">${tag.ContactCount}</span></button>`;
    }).join("");
  container.querySelectorAll(".chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      const id = chip.getAttribute("data-tag") ? Number(chip.getAttribute("data-tag")) : null;
      currentTagId = currentTagId === id ? null : id;
      renderTagFilter();
      renderContacts(getDisplayedContacts());
    });
  });
}

function renderTagManager() {
  document.getElementById("tag-list").innerHTML = tags.length
    ? tags.map(function (tag) {
        return `<div class="tag-row" data-id="${tag.ID}">
          <input type="color" value="${escapeHtml(tag.Color)}" aria-label="Color">
          <input type="text" value="${escapeHtml(tag.Name)}" maxlength="40" aria-label="Tag name">
          <span class="subtitle">${tag.ContactCount} contact(s)</span>
          <button class="button button-secondary button-small save-tag">Save</button>
          <button class="button button-secondary button-small delete-tag">Delete</button>
        </div>`;
      }).join("")
    : `<p class="subtitle">You haven't created any tags yet.</p>`;
}

document.getElementById("manage-tags-button").addEventListener("click", function () {
  document.getElementById("tags-message").textContent = "";
  renderTagManager();
  document.getElementById("tags-modal").style.display = "flex";
});

document.getElementById("tags-close-button").addEventListener("click", function () {
  document.getElementById("tags-modal").style.display = "none";
});

document.getElementById("add-tag-button").addEventListener("click", async function () {
  const message = document.getElementById("tags-message");
  const result = await apiRequest("AddTag.php", {
    method: "POST",
    body: { name: document.getElementById("new-tag-name").value, color: document.getElementById("new-tag-color").value }
  });
  if (!result.ok) {
    message.textContent = errorMessage(result);
    return;
  }
  message.textContent = "";
  document.getElementById("new-tag-name").value = "";
  await loadTags();
  renderTagManager();
});

document.getElementById("tag-list").addEventListener("click", async function (event) {
  const row = event.target.closest(".tag-row");
  if (!row) return;
  const id = Number(row.getAttribute("data-id"));
  let result = null;

  if (event.target.classList.contains("save-tag")) {
    const inputs = row.querySelectorAll("input");
    result = await apiRequest("EditTag.php", { method: "POST", body: { id: id, color: inputs[0].value, name: inputs[1].value } });
  }
  if (event.target.classList.contains("delete-tag")) {
    result = await apiRequest("DeleteTag.php", { method: "POST", body: { id: id } });
    if (currentTagId === id) currentTagId = null;
  }
  if (result) {
    document.getElementById("tags-message").textContent = result.ok ? "" : errorMessage(result);
    await Promise.all([loadTags(), loadContacts()]);
    renderTagManager();
  }
});

// ---- Sharing ----

let sharingContactId = null;

async function openShareModal(contactId) {
  sharingContactId = contactId;
  const contact = contacts.find(function (c) { return c.id === contactId; });
  document.getElementById("share-title").textContent = "Share " + contact.firstName + " " + contact.lastName;
  document.getElementById("share-login").value = "";
  document.getElementById("share-message").textContent = "";
  document.getElementById("share-modal").style.display = "flex";
  await renderShareList();
}

async function renderShareList() {
  const result = await apiRequest("ListShares.php?id=" + sharingContactId);
  const shares = result.ok ? result.data : [];
  document.getElementById("share-list").innerHTML = shares.length
    ? "<p class=\"label\">People with access</p>" + shares.map(function (share) {
        return `<div class="share-row">
          <span><strong>${escapeHtml(share.FirstName + " " + share.LastName)}</strong> @${escapeHtml(share.Login)}
            &middot; ${share.Permission === "edit" ? "can edit" : "view only"}</span>
          <button class="button button-secondary button-small remove-share" data-user="${share.UserID}">Remove</button>
        </div>`;
      }).join("")
    : `<p class="subtitle">Not shared with anyone yet.</p>`;
}

document.getElementById("share-save-button").addEventListener("click", async function () {
  const result = await apiRequest("ShareContact.php", {
    method: "POST",
    body: {
      id: sharingContactId,
      login: document.getElementById("share-login").value,
      permission: document.getElementById("share-permission").value
    }
  });
  document.getElementById("share-message").textContent = result.ok ? "" : errorMessage(result);
  if (result.ok) {
    document.getElementById("share-login").value = "";
    await renderShareList();
    await loadContacts();
  }
});

document.getElementById("share-list").addEventListener("click", async function (event) {
  if (!event.target.classList.contains("remove-share")) return;
  const result = await apiRequest("UnshareContact.php", {
    method: "POST",
    body: { id: sharingContactId, userId: Number(event.target.getAttribute("data-user")) }
  });
  if (!result.ok) showToast(errorMessage(result), "error");
  await renderShareList();
  await loadContacts();
});

document.getElementById("share-close-button").addEventListener("click", function () {
  document.getElementById("share-modal").style.display = "none";
});

// ---- CSV import ----

document.getElementById("import-button").addEventListener("click", function () {
  document.getElementById("import-file").value = "";
  document.getElementById("import-message").textContent = "";
  document.getElementById("import-modal").style.display = "flex";
});

document.getElementById("import-close-button").addEventListener("click", function () {
  document.getElementById("import-modal").style.display = "none";
});

document.getElementById("import-save-button").addEventListener("click", async function () {
  const message = document.getElementById("import-message");
  const file = document.getElementById("import-file").files[0];
  if (!file) {
    message.textContent = "Choose a CSV file first.";
    return;
  }
  const form = new FormData();
  form.append("file", file);
  const result = await apiRequest("ImportContacts.php", { method: "POST", form: form });
  if (!result.ok) {
    message.textContent = errorMessage(result);
    return;
  }
  const problems = result.data.errors.map(function (e) { return "Row " + e.row + ": " + e.message; }).join("\n");
  message.className = "form-message " + (result.data.imported ? "form-message-success" : "form-message-error");
  message.textContent = `Imported ${result.data.imported} contact(s)` +
    (result.data.skipped ? `, skipped ${result.data.skipped}:\n${problems}` : ".");
  await Promise.all([loadContacts(), loadTags()]);
});

loadTags();
