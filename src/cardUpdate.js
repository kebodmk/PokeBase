const form = document.getElementById("cardForm");
const editableInputs = form.querySelectorAll("input:not([type='hidden'])");
const editBtn = document.getElementById("editBtn");
const submitBtn = document.getElementById("submitBtn");

editBtn.addEventListener("click", () => {
editableInputs.forEach(input => input.readOnly = false);

editableInputs[0].focus();
editBtn.classList.add("invisible");
submitBtn.classList.remove("invisible");
});

form.addEventListener("submit", async (e) => {
    e.preventDefault(); // stop the normal form submission

    const data = Object.fromEntries(new FormData(form).entries());

    try {
      const response = await fetch(form.getAttribute("action"), {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        credentials: "same-origin", // sends your session cookie
        body: JSON.stringify(data)
      });

      if (response.status === 401) {
        window.location.href = "login.php?page=login";
        return;
      }

      const result = await response.json();

      if (result.success) {
        // Lock the form again
        editableInputs.forEach(input => input.readOnly = true);
        submitBtn.classList.add("invisible");
        editBtn.classList.remove("invisible");
      } else {
        alert(result.message || "Update failed.");
      }
    } catch (err) {
      alert("Network error: " + err.message);
    }
});