(function () {
  const form = document.getElementById("cardForm");
  const editableInputs = form.querySelectorAll("input:not([type='hidden'])");
  const editBtn = document.getElementById("editBtn");
  const submitBtn = document.getElementById("submitBtn");
  const priceInput = form.elements["price"];

  function lockForm() {
    editableInputs.forEach(input => input.readOnly = true);
    submitBtn.classList.add("invisible");
    editBtn.classList.remove("invisible");
  }

  function formatPrice() {
    const n = parseFloat(priceInput.value);
    if (!isNaN(n)) {
      priceInput.value = n.toFixed(2);
    }
  }

  priceInput.addEventListener("blur", formatPrice);

  editBtn.addEventListener("click", () => {
    editableInputs.forEach(input => input.readOnly = false);
    editableInputs[0].focus();
    editBtn.classList.add("invisible");
    submitBtn.classList.remove("invisible");
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const val = (name) => form.elements[name].value.trim();
    const data = {
      name:      val("name"),
      price:     parseFloat(val("price")),
      set:       val("set"),
      set_num:   val("set_num"),
      printing:  val("printing"),
      condition: val("condition"),
      grade:     val("grade") === "" ? null : val("grade"),
      lang:      val("language")
    };

    try {
      const response = await fetch(form.getAttribute("action"), {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        credentials: "same-origin",
        body: JSON.stringify(data)
      });

      if (response.status === 401) {
        window.location.href = "login.php?page=login";
        return;
      }

      const result = await response.json();

      if (result.success) {
        formatPrice();
        lockForm();
      } else {
        alert(result.message || "Update failed.");
      }
    } catch (err) {
      alert("Network error: " + err.message);
    }
  });

  // Start locked and formatted, and re-lock if restored from the back/forward cache
  formatPrice();
  lockForm();
  window.addEventListener("pageshow", lockForm);
})();