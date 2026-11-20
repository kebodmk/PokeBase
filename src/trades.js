const form = document.getElementById('trade-form');
const template = document.getElementById('card-template');
const completeButton = document.getElementById('complete-transaction');
const updatePricesButton = document.getElementById('update-prices');
const tradeStatus = document.getElementById('trade-status');
const cardsOnly = form.elements.cards_only;
const moneyInputs = [form.elements.incoming_money, form.elements.outgoing_money];

const CARD_SEARCH_URL = 'api/cards/search';
const TRADE_PRICE_URL = 'api/just_tcg/index.php?route=trade_price';
const DAY_MS = 24 * 60 * 60 * 1000;
const MONEY_PATTERN = /^\d+(\.\d{1,2})?$/;
const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

// Per completed card: { values, source, cardId }
//   values = the card's form fields, used again when its price is synced
//   source = where the shown price came from: { price (number|null), priceDate, fromApi, matched, company }
//   cardId = database card_id of an outgoing card, so the same inventory card is not sold twice
const cardState = new WeakMap();
let cardIndex = 0;
let pendingPriceRequests = 0;

function completedCards(root = form) {
    return [...root.querySelectorAll('.trade-card:not(.editing)')];
}

function today() {
    return new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD
}

function updateCompleteButton() {
    completeButton.disabled = form.querySelector('.trade-card.editing') !== null || pendingPriceRequests > 0;
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

function moneyValue(input) {
    const value = input.value.trim();
    return MONEY_PATTERN.test(value) ? Number(value) : null;
}

// Each column total = its card prices + its money field. Blank or invalid amounts count as 0.
function updateTotals() {
    document.querySelectorAll('.trade-column').forEach(column => {
        const money = column.dataset.recieved === '1' ? form.elements.incoming_money : form.elements.outgoing_money;
        let total = completedCards(column).reduce((sum, card) => sum + (moneyValue(card.querySelector('.price-input')) ?? 0), 0);

        if (!money.disabled) {
            total += moneyValue(money) ?? 0;
        }
        column.querySelector('.column-total output').value = usd.format(total);
    });
}

function isPriceStale(card) {
    return Date.now() - Number(card.dataset.pricedAt) >= DAY_MS;
}

// Enables sync buttons and Update Prices only for prices at least 24 hours old
function refreshPriceControls() {
    const cards = completedCards();
    const busy = pendingPriceRequests > 0;

    cards.forEach(card => {
        const button = card.querySelector('.sync-price');
        const stale = isPriceStale(card);
        button.disabled = busy || !stale;
        button.title = stale ? 'Fetch the current price from JustTCG' : 'Price synced within 24 hours';
    });

    const anyStale = cards.some(isPriceStale);
    updatePricesButton.disabled = busy || !anyStale;
    updatePricesButton.title = cards.length === 0 ? 'Add a card to update prices'
        : anyStale ? 'Fetch current JustTCG prices for cards priced over 24 hours ago'
        : 'All prices synced within 24 hours';
}

function refreshPage() {
    updateCompleteButton();
    updateTotals();
    refreshPriceControls();
}

async function readJson(response) {
    return response.json().catch(() => ({}));
}

// Same matching rule as complete_trade.php: exact name, and no condition on graded cards / no grade on raw ones.
// Outgoing cards only match cards still in inventory, skipping card_ids already used by other outgoing cards.
// Returns { match, count } where count is how many database cards matched before skipping.
async function findDatabaseCard(values, outgoingExcludeIds = null) {
    const graded = values.card_type === 'graded';
    const params = new URLSearchParams({
        name: values.name,
        set: values.set,
        set_num: values.set_num,
        lang: values.lang,
        printing: values.printing,
    });
    params.set(graded ? 'grade' : 'condition', graded ? String(parseFloat(values.grade)) : values.condition);
    if (outgoingExcludeIds !== null) {
        params.set('in_inventory', '1');
    }

    const result = await readJson(await fetch(`${CARD_SEARCH_URL}?${params}`, { credentials: 'same-origin' }));
    if (result.success !== true) {
        throw new Error('Unable to look up the card in the database.');
    }

    const name = values.name.toLowerCase();
    const matches = result.cards.filter(row => row.name.toLowerCase() === name && (graded ? row.condition : row.grade) === null);
    const excluded = outgoingExcludeIds ?? [];

    return {
        match: matches.find(row => !excluded.includes(Number(row.card_id))) ?? null,
        count: matches.length,
    };
}

// Graded cards can come back with price null when the default company has no listing for that grade
async function fetchTcgPrice(values) {
    const body = new FormData();
    Object.entries(values).forEach(([key, value]) => body.append(key, value));

    const result = await readJson(await fetch(TRADE_PRICE_URL, { method: 'POST', body, credentials: 'same-origin' }));
    if (result.success !== true) {
        throw new Error(result.error ?? 'Unable to get a price from JustTCG.');
    }

    return {
        price: result.price === null ? null : Number(result.price),
        priceDate: result.price_date,
        fromApi: true,
        matched: result.matched,
        company: result.grading_company,
    };
}

function localMidnight(isoDate) {
    const [year, month, day] = isoDate.split('-').map(Number);
    return new Date(year, month - 1, day).getTime();
}

function setHidden(card, key, value) {
    card.querySelector(`input[name="cards[${card.dataset.index}][${key}]"]`).value = value;
}

function missingPriceText(state) {
    return `No ${state.source.company} Grade ${state.values.grade} price on JustTCG. Enter a price.`;
}

function sourceText(state) {
    const { source } = state;
    if (source.price === null) {
        return missingPriceText(state);
    }
    const label = source.fromApi ? `JustTCG${source.matched ? ', ' + source.matched : ''}` : 'database';
    return `as of ${source.priceDate} (${label})`;
}

// Database dates have no time, so they count from midnight. JustTCG and typed prices are from right now.
function setPriceDate(card, priceDate, pricedNow) {
    setHidden(card, 'price_date', priceDate);
    card.dataset.pricedAt = pricedNow ? Date.now() : localMidnight(priceDate);
}

function setOverridden(card, overridden) {
    setHidden(card, 'price_overridden', overridden ? '1' : '0');
    card.querySelector('.price-override').hidden = !overridden;
}

// Shows a price from the database or JustTCG and clears any override
function applySource(card, source) {
    const state = cardState.get(card);
    state.source = source;

    card.querySelector('.price-input').value = source.price === null ? '' : source.price.toFixed(2);
    setHidden(card, 'price_from_api', source.fromApi ? '1' : '0');
    setPriceDate(card, source.price === null ? today() : source.priceDate, source.fromApi);
    setOverridden(card, false);
    card.querySelector('.price-source').textContent = sourceText(state);

    refreshPage();
}

// Any edit that differs from the fetched price is an override, dated today
function onPriceInput(card) {
    const state = cardState.get(card);
    const { source } = state;
    const input = card.querySelector('.price-input');
    const blank = input.value.trim() === '';
    const typed = moneyValue(input);
    const matchesSource = source.price !== null && typed !== null && typed === Number(source.price.toFixed(2));

    if (matchesSource) {
        setPriceDate(card, source.priceDate, source.fromApi);
        card.querySelector('.price-source').textContent = sourceText(state);
    } else {
        setPriceDate(card, today(), true);
        card.querySelector('.price-source').textContent = source.price === null && blank
            ? missingPriceText(state)
            : `as of ${today()}`;
    }
    setOverridden(card, !matchesSource && !blank);
    refreshPage();
}

// Returns null on success or the error message. A graded card with no JustTCG price keeps its current price.
async function syncCardPrice(card) {
    const state = cardState.get(card);
    pendingPriceRequests++;
    card.querySelector('.sync-price').textContent = 'Syncing...';
    refreshPage();

    try {
        const source = await fetchTcgPrice(state.values);
        if (source.price === null) {
            return `No ${source.company} Grade ${state.values.grade} price on JustTCG. The current price was kept.`;
        }
        applySource(card, source);
        return null;
    } catch (error) {
        return error.message;
    } finally {
        pendingPriceRequests--;
        card.querySelector('.sync-price').textContent = 'Sync Price';
        refreshPage();
    }
}

function hiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function finishCard(card, values, source, cardId) {
    const index = cardIndex++;
    card.dataset.index = index;
    cardState.set(card, { values, source, cardId });

    const hiddenValues = {
        ...values,
        recieved: card.dataset.recieved,
        price_date: '',
        price_from_api: '0',
        price_overridden: '0',
    };
    const hidden = Object.entries(hiddenValues).map(([key, value]) => hiddenInput(`cards[${index}][${key}]`, value));

    const summary = document.createElement('p');
    summary.textContent = `${values.name} (${values.set} ${values.set_num})\n` +
        `${values.lang} · ${values.condition ?? 'Grade ' + values.grade} · ${values.printing}`;

    // Price row: editable price, where it came from, override note, sync button
    const priceRow = document.createElement('div');
    priceRow.className = 'card-price';

    const priceLabel = document.createElement('label');
    priceLabel.className = 'price-label';
    priceLabel.append('Price $');
    const priceInput = document.createElement('input');
    priceInput.type = 'text';
    priceInput.className = 'price-input';
    priceInput.name = `cards[${index}][price]`;
    priceInput.inputMode = 'decimal';
    priceInput.pattern = '\\d+(\\.\\d{1,2})?';
    priceInput.title = 'A dollar amount, e.g. 12.50';
    priceInput.required = true;
    priceInput.addEventListener('input', () => onPriceInput(card));
    priceLabel.append(priceInput);

    const priceSource = document.createElement('span');
    priceSource.className = 'price-source';

    const override = document.createElement('span');
    override.className = 'price-override';
    override.textContent = 'Price Overridden';
    override.hidden = true;

    const sync = document.createElement('button');
    sync.type = 'button';
    sync.className = 'sync-price';
    sync.textContent = 'Sync Price';
    sync.addEventListener('click', async () => {
        const error = await syncCardPrice(card);
        if (error !== null) {
            showStatus('gerror', `Could not sync ${values.name}: ${error}`);
        }
    });

    priceRow.append(priceLabel, priceSource, override, sync);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
        card.remove();
        refreshPage();
    });

    card.replaceChildren(summary, priceRow, ...hidden, remove);
    card.classList.remove('editing');
    applySource(card, source);
}

// card_ids already claimed by the other outgoing cards on the page
function usedOutgoingIds(except) {
    return completedCards(document.querySelector('.trade-column[data-recieved="0"]'))
        .filter(card => card !== except)
        .map(card => cardState.get(card).cardId)
        .filter(id => id !== null);
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

    const cardError = card.querySelector('.card-error');
    const confirm = card.querySelector('.confirm-card');

    card.querySelector('.cancel-card').addEventListener('click', () => {
        card.remove();
        refreshPage();
    });

    // Outgoing: the card must be in inventory. Incoming: look in the database first, then JustTCG.
    confirm.addEventListener('click', async () => {
        const fields = [...card.querySelectorAll('[data-field]')].filter(field => !field.disabled);
        if (!fields.every(field => field.reportValidity())) {
            return;
        }

        const values = Object.fromEntries(fields.map(field => [field.dataset.field, field.value.trim()]));
        const outgoing = card.dataset.recieved === '0';
        cardError.hidden = true;
        card.inert = true;
        confirm.textContent = 'Checking...';

        try {
            const { match, count } = await findDatabaseCard(values, outgoing ? usedOutgoingIds(card) : null);
            let source;

            if (match !== null) {
                source = { price: Number(match.price), priceDate: match.price_date, fromApi: false };
            } else if (outgoing) {
                throw new Error(count > 0
                    ? `Card not added. All ${count} matching card(s) in inventory are already in this trade.`
                    : 'Card not added. Outgoing cards must be in inventory, and no matching card is in inventory.');
            } else {
                try {
                    source = await fetchTcgPrice(values);
                } catch (error) {
                    throw new Error(`Card not added. It is not in the database. ${error.message}`);
                }
            }

            finishCard(card, values, source, outgoing ? Number(match.card_id) : null);
        } catch (error) {
            cardError.textContent = error.message;
            cardError.hidden = false;
        } finally {
            card.inert = false;
            confirm.textContent = 'Add Card';
        }
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

// Syncs every card priced over 24 hours ago, one request at a time
updatePricesButton.addEventListener('click', async () => {
    const staleCards = completedCards().filter(isPriceStale);
    const errors = [];

    showStatus('', `Updating ${staleCards.length} price(s)...`);
    for (const card of staleCards) {
        const error = await syncCardPrice(card);
        if (error !== null) {
            errors.push(`${cardState.get(card).values.name}: ${error}`);
        }
    }

    const updated = staleCards.length - errors.length;
    if (errors.length > 0) {
        showStatus('gerror', [`Updated ${updated} of ${staleCards.length} price(s). Could not update:`, ...errors].join('\n'));
    } else {
        showStatus('gmessage', `Updated ${updated} price(s).`);
    }
});

moneyInputs.forEach(input => input.addEventListener('input', () => {
    updateMoney();
    updateTotals();
}));
cardsOnly.addEventListener('change', () => {
    updateMoney();
    updateTotals();
});

// Handle submission, check data in input fields, clear form after successful submission.
// The browser blocks submitting while a price field is blank or not a dollar amount.
form.addEventListener('submit', async event => {
    event.preventDefault();

    if (form.querySelector('.trade-card.editing') || pendingPriceRequests > 0) {
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
        refreshPage();
    }
});

// A price pulled while the page is open can pass 24 hours old, so recheck every minute
setInterval(refreshPriceControls, 60 * 1000);

updateMoney();
refreshPage();
