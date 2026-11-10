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

// Archive asks first, cancelling stops the form from submitting
document.querySelectorAll('.archive-button').forEach(button => {
    button.addEventListener('click', event => {
        if (!confirm('Remove ' + button.dataset.name + '? Their account will be archived and they will no longer be able to log in.')) {
            event.preventDefault();
        }
    });
});

checkForChanges();
