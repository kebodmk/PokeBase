const form = document.getElementById('trade-form');
const template = document.getElementById('card-template');
const completeButton = document.getElementById('complete-transaction');
const tradeStatus = document.getElementById('trade-status');
const cardsOnly = form.elements.cards_only;
const moneyInputs = [form.elements.incoming_money, form.elements.outgoing_money];
let cardIndex = 0;

function updateCompleteButton() {
    completeButton.disabled = form.querySelector('.trade-card.editing') !== null;
}

function updateMoney() {
    const filled = moneyInputs.find(input => input.value.trim() !== '');
    moneyInputs.forEach(input => {
        input.disabled = cardsOnly.checked || (filled !== undefined && filled !== input);
        input.required = !input.disabled;
    });
}

function showStatus(className, text) {
    tradeStatus.className = className;
    tradeStatus.textContent = text;
}

function setupCard(card) {
    const type = card.querySelector('[data-field="card_type"]');
    const toggleType = () => card.querySelectorAll('[data-type]').forEach(label => {
        const active = label.dataset.type === type.value;
        label.hidden = !active;
        label.querySelector('[data-field]').disabled = !active;
    });
    type.addEventListener('change', toggleType);
    toggleType();

    card.querySelector('.cancel-card').addEventListener('click', () => {
        card.remove();
        updateCompleteButton();
    });

    card.querySelector('.confirm-card').addEventListener('click', () => {
        const fields = [...card.querySelectorAll('[data-field]')].filter(field => !field.disabled);
        if (!fields.every(field => field.reportValidity())) {
            return;
        }

        const values = Object.fromEntries(fields.map(field => [field.dataset.field, field.value.trim()]));
        const index = cardIndex++;
        const hidden = Object.entries({ ...values, recieved: card.dataset.recieved }).map(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `cards[${index}][${key}]`;
            input.value = value;
            return input;
        });

        const summary = document.createElement('p');
        summary.textContent = `${values.name} (${values.set} ${values.set_num})\n` +
            `${values.lang} · ${values.condition ?? 'Grade ' + values.grade} · ${values.printing}`;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Remove';
        remove.addEventListener('click', () => card.remove());

        card.replaceChildren(summary, ...hidden, remove);
        card.classList.remove('editing');
        updateCompleteButton();
    });
}

document.querySelectorAll('.add-card').forEach(button => {
    button.addEventListener('click', () => {
        const column = button.closest('.trade-column');
        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.recieved = column.dataset.recieved;
        column.querySelector('.card-list').appendChild(card);
        setupCard(card);
        updateCompleteButton();
    });
});

moneyInputs.forEach(input => input.addEventListener('input', updateMoney));
cardsOnly.addEventListener('change', updateMoney);

// Handle submission, check data in input fields, clear form after successful submission
form.addEventListener('submit', async event => {
    event.preventDefault();

    if (form.querySelector('.trade-card.editing')) {
        return;
    }
    if (!form.querySelector('.trade-card')) {
        return showStatus('gerror', 'Add at least one card.');
    }
    if (!cardsOnly.checked && moneyInputs.every(input => input.value.trim() !== '')) {
        return showStatus('gerror', 'Only one money field can be filled.');
    }

    completeButton.disabled = true;
    showStatus('', 'Saving transaction...');

    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
        const result = await response.json().catch(() => ({}));

        if (result.success !== true) {
            throw new Error([result.message ?? 'Unable to complete the transaction.', ...(result.errors ?? [])].join('\n'));
        }

        form.querySelectorAll('.trade-card').forEach(card => card.remove());
        form.reset();
        updateMoney();
        showStatus('gmessage', result.message);
    } catch (error) {
        showStatus('gerror', error.message);
    } finally {
        updateCompleteButton();
    }
});

updateMoney();
