const roleSelects = document.querySelectorAll('.role-select');
const saveButton = document.getElementById('save-roles');

// Button stays disabled until something changed
function checkForChanges() {
    let changed = false;

    roleSelects.forEach(select => {
        if (select.value !== select.dataset.original) {
            select.name = 'roles[' + select.dataset.userId + ']';
            changed = true;
        } else {
            select.removeAttribute('name');
        }
    });

    saveButton.disabled = !changed;
}

roleSelects.forEach(select => select.addEventListener('change', checkForChanges));

checkForChanges();
