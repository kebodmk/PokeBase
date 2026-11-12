<?php

function postString($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : $default;
}

function escape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$set_input = postString('text1');
$set_number_input = postString('text2');
$name_input = postString('text3');
$language_input = postString('extra1', 'en');
$condition_input = postString('extra2', 'Near Mint');
$card_type_input = postString('card_type', 'raw');
$printing_input = postString('printing');

if (!in_array($card_type_input, ['raw', 'graded'], true)) {
    $card_type_input = 'raw';
}

$languages = [
    'en' => 'English',
    'jap' => 'Japanese',
    'ch' => 'Chinese',
    'kor' => 'Korean',
];

if (!isset($languages[$language_input])) {
    $language_input = 'en';
}

$rawConditions = [
    'Near Mint',
    'Lightly Played',
    'Moderately Played',
    'Heavily Played',
    'Damaged',
    'Sealed',
];

$legacyConditions = [
    'mint' => 'Near Mint',
    'near-mint' => 'Near Mint',
];

$condition_input = $legacyConditions[$condition_input]
    ?? $condition_input;

if (!in_array($condition_input, $rawConditions, true)) {
    $condition_input = 'Near Mint';
}

$printingOptions = [
    'Normal',
    'Holofoil',
    '1st Edition Holofoil',
    'Unlimited Holofoil',
];

if (
    $printing_input !== ''
    && !in_array($printing_input, $printingOptions, true)
) {
    $printingOptions[] = $printing_input;
}

$setsFile = 'C:/xampp/htdocs/PokeBase/src/api/just_tcg/sets.txt';

$sets = [];
$setsError = '';

if (!is_file($setsFile) || !is_readable($setsFile)) {
    $setsError =
        'No readable sets.txt found. Click Update Sets to create it.';
} else {
    $contents = file_get_contents($setsFile);

    if ($contents === false) {
        $setsError = 'Could not read sets.txt.';
    } else {
        $decoded = json_decode($contents, true);

        if (
            json_last_error() !== JSON_ERROR_NONE
            || !is_array($decoded)
        ) {
            $setsError = 'sets.txt does not contain valid JSON.';
        } else {
            $savedSets = $decoded['data'] ?? $decoded;

            if (!is_array($savedSets)) {
                $setsError = 'No sets list found in sets.txt.';
            } else {
                foreach ($savedSets as $set) {
                    if (
                        !is_array($set)
                        || !isset($set['id'], $set['name'])
                        || !is_string($set['id'])
                        || !is_string($set['name'])
                    ) {
                        continue;
                    }

                    $sets[] = $set;
                }

                if (!$sets) {
                    $setsError =
                        'The saved file contains no usable sets.';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Just TCG Card List</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1300px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
        }

        #card-form,
        .sets-section {
            max-width: 750px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
        }

        input,
        select {
            box-sizing: border-box;
            width: 100%;
            padding: 8px;
        }

        button {
            padding: 8px 14px;
            cursor: pointer;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        details {
            margin-bottom: 20px;
        }

        summary {
            cursor: pointer;
            margin-bottom: 16px;
        }

        section {
            margin-bottom: 28px;
        }

        .error {
            color: #b00020;
        }

        .success {
            color: #176b28;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            color: #555;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .data-table th,
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        .data-table th {
            background: #f5f5f5;
        }

        .market-price {
            white-space: nowrap;
            font-weight: 600;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .result-group {
            margin-bottom: 28px;
        }

        .result-group h3 {
            margin-bottom: 8px;
        }

        .result-description {
            color: #555;
            font-size: 0.9rem;
        }

        [hidden] {
            display: none !important;
        }
    </style>
</head>
<body>
    <h1>Just TCG</h1>

    <section class="sets-section">
        <button id="update-sets-button" type="button">
            Update Sets
        </button>

        <p
            id="update-sets-status"
            role="status"
            aria-live="polite"
        ></p>

        <p>
            <span id="sets-count"><?= count($sets) ?></span>
            sets loaded.
        </p>

        <p
            id="sets-file-error"
            class="error"
            <?= $setsError === '' ? 'hidden' : '' ?>
        ><?= escape($setsError) ?></p>
    </section>

    <form id="card-form" method="post">
        <h2>Add a Card</h2>

        <div class="field">
            <label for="text1">Set</label>

            <select id="text1" name="text1" required>
                <option value="">-- Select a set --</option>

                <?php foreach ($sets as $set): ?>
                    <option
                        value="<?= escape($set['id']) ?>"
                        <?= $set_input === $set['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= escape($set['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="text2">Set Number</label>

            <input
                type="text"
                id="text2"
                name="text2"
                value="<?= escape($set_number_input) ?>"
                placeholder="For example: 4 or 004/102"
            >
        </div>

        <div class="field">
            <label for="text3">Name</label>

            <input
                type="text"
                id="text3"
                name="text3"
                value="<?= escape($name_input) ?>"
                placeholder="For example: Charizard"
            >
        </div>

        <details open>
            <summary>More options</summary>

            <div class="field">
                <label for="extra1">Language</label>

                <select id="extra1" name="extra1">
                    <?php foreach ($languages as $value => $label): ?>
                        <option
                            value="<?= escape($value) ?>"
                            <?= $language_input === $value
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="card_type">Card Type</label>

                <select id="card_type" name="card_type" required>
                    <option
                        value="raw"
                        <?= $card_type_input === 'raw'
                            ? 'selected'
                            : '' ?>
                    >
                        Raw
                    </option>

                    <option
                        value="graded"
                        <?= $card_type_input === 'graded'
                            ? 'selected'
                            : '' ?>
                    >
                        Graded
                    </option>
                </select>
            </div>

            <div
                class="field"
                id="raw-condition-field"
                <?= $card_type_input === 'graded' ? 'hidden' : '' ?>
            >
                <label for="extra2">Condition</label>

                <select
                    id="extra2"
                    name="extra2"
                    <?= $card_type_input === 'raw'
                        ? 'required'
                        : 'disabled' ?>
                >
                    <?php foreach ($rawConditions as $condition): ?>
                        <option
                            value="<?= escape($condition) ?>"
                            <?= $condition_input === $condition
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escape($condition) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div
                class="field"
                id="graded-condition-field"
                <?= $card_type_input === 'raw' ? 'hidden' : '' ?>
            >
                <label for="graded_variant_id">Grading Option</label>

                <select
                    id="graded_variant_id"
                    name="graded_variant_id"
                    aria-describedby="graded-help"
                    <?= $card_type_input === 'raw' ? 'disabled' : '' ?>
                >
                    <option value="">
                        -- All available grades --
                    </option>
                </select>

                <small id="graded-help" class="help-text">
                    Grade choices load only after you query the list.
                    Select a returned grade and add a new entry to
                    queue that specific variant.
                </small>
            </div>

            <div class="field">
                <label for="printing">Printing</label>

                <select id="printing" name="printing">
                    <option value="">-- Any printing --</option>

                    <?php foreach ($printingOptions as $printing): ?>
                        <option
                            value="<?= escape($printing) ?>"
                            <?= $printing_input === $printing
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escape($printing) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small class="help-text">
                    Use Any printing for the broadest search.
                </small>
            </div>
        </details>

        <button id="add-card-button" type="submit">
            Add to List
        </button>

        <p class="help-text">
            Adding a card does not call JustTCG.
            Queries run only when you press Query Listed Cards.
        </p>

        <p
            id="add-card-status"
            role="status"
            aria-live="polite"
        ></p>
    </form>

    <noscript>
        <p class="error">
            Enable JavaScript to queue and query cards.
        </p>
    </noscript>

    <section>
        <h2>
            Queued Cards
            (<span id="queue-count">0</span>/20)
        </h2>

        <p id="empty-queue-message">No cards added yet.</p>

        <div class="table-container">
            <table id="queue-table" class="data-table" hidden>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Card</th>
                        <th scope="col">Set</th>
                        <th scope="col">Number</th>
                        <th scope="col">Type</th>
                        <th scope="col">Condition / Grade</th>
                        <th scope="col">Printing</th>
                        <th scope="col">Language</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody id="queue-body"></tbody>
            </table>
        </div>

        <div class="actions">
            <button id="query-list-button" type="button" disabled>
                Query Listed Cards
            </button>

            <button id="clear-list-button" type="button" disabled>
                Clear List
            </button>
        </div>

        <p class="help-text">
            Entries store a snapshot of your selections.
            Changing the form does not change entries already queued.
            Refreshing this page clears the list.
        </p>
    </section>

    <section>
        <h2>Card Results</h2>

        <p
            id="card-request-status"
            role="status"
            aria-live="polite"
        ></p>

        <div id="card-results"></div>
    </section>

    <script>
        const apiUrl = "/PokeBase/src/api/just_tcg/index.php";
        const cardsAddUrl = "/PokeBase/src/api/cards/add";
        const MAX_CARDS = 20;

        const cardForm = document.getElementById("card-form");
        const addButton = document.getElementById("add-card-button");
        const addStatus = document.getElementById("add-card-status");
        const queryButton = document.getElementById("query-list-button");
        const clearButton = document.getElementById("clear-list-button");

        const queueCount = document.getElementById("queue-count");
        const queueTable = document.getElementById("queue-table");
        const queueBody = document.getElementById("queue-body");
        const emptyQueueMessage =
            document.getElementById("empty-queue-message");

        const cardRequestStatus =
            document.getElementById("card-request-status");
        const cardResults = document.getElementById("card-results");

        const updateButton =
            document.getElementById("update-sets-button");
        const updateStatus =
            document.getElementById("update-sets-status");
        const setSelect = document.getElementById("text1");
        const setsCount = document.getElementById("sets-count");
        const setsFileError =
            document.getElementById("sets-file-error");

        const numberInput = document.getElementById("text2");
        const nameInput = document.getElementById("text3");
        const languageSelect = document.getElementById("extra1");
        const cardTypeSelect = document.getElementById("card_type");
        const rawConditionField =
            document.getElementById("raw-condition-field");
        const gradedConditionField =
            document.getElementById("graded-condition-field");
        const rawConditionSelect = document.getElementById("extra2");
        const gradedVariantSelect =
            document.getElementById("graded_variant_id");
        const gradedHelp = document.getElementById("graded-help");
        const printingSelect = document.getElementById("printing");

        // Local queue. No network request is made when adding entries.
        const cardQueue = [];

        let nextEntryId = 1;
        let queryInProgress = false;
        let setsUpdateInProgress = false;
        let gradingOptionsCache = new Map();

        function selectedText(select) {
            return select.selectedOptions[0]?.textContent.trim() ?? "";
        }

        function formSearchKey() {
            return JSON.stringify([
                setSelect.value,
                numberInput.value.trim(),
                nameInput.value.trim(),
                languageSelect.value,
                printingSelect.value
            ]);
        }

        function entrySearchKey(entry) {
            return JSON.stringify([
                entry.fields.text1,
                entry.fields.text2,
                entry.fields.text3,
                entry.fields.extra1,
                entry.fields.printing
            ]);
        }

        async function requestJson(url, options = {}) {
            const response = await fetch(url, options);
            const rawResponse = await response.text();

            let data;

            try {
                data = JSON.parse(rawResponse);
            } catch {
                throw new Error(
                    "HTTP " + response.status +
                    ": Expected a JSON response."
                );
            }

            if (!response.ok) {
                const upstreamStatus = data?.upstreamStatus
                    ? " | JustTCG HTTP " + data.upstreamStatus
                    : "";

                const upstreamDetails = data?.upstreamError
                    ? " | " + JSON.stringify(data.upstreamError)
                    : "";

                throw new Error(
                    "HTTP " + response.status + ": " +
                    (data?.error || "Request failed.") +
                    upstreamStatus +
                    upstreamDetails
                );
            }

            return data;
        }

        function updateConditionFields() {
            const isGraded = cardTypeSelect.value === "graded";

            rawConditionField.hidden = isGraded;
            rawConditionSelect.disabled = queryInProgress || isGraded;
            rawConditionSelect.required = !isGraded;

            gradedConditionField.hidden = !isGraded;
            gradedVariantSelect.disabled =
                queryInProgress || !isGraded;
            gradedVariantSelect.required = false;
        }

        function refreshButtons() {
            addButton.disabled =
                queryInProgress || cardQueue.length >= MAX_CARDS;

            queryButton.disabled =
                queryInProgress ||
                setsUpdateInProgress ||
                cardQueue.length === 0;

            clearButton.disabled =
                queryInProgress || cardQueue.length === 0;

            updateButton.disabled =
                queryInProgress || setsUpdateInProgress;

            queryButton.textContent = queryInProgress
                ? "Querying..."
                : "Query Listed Cards";
        }

        function resetGradingOptions() {
            gradingOptionsCache.clear();

            gradedVariantSelect.replaceChildren(
                new Option("-- All available grades --", "")
            );

            gradedHelp.textContent =
                "Grade choices load only after you query the list. " +
                "Select a returned grade and add a new entry to " +
                "queue that specific variant.";
        }

        function populateGradingOptions(result, entry) {
            // Only populate choices for the card currently in the form.
            if (formSearchKey() !== entrySearchKey(entry)) {
                return;
            }

            if (!entry.fields.graded_variant_id) {
                gradingOptionsCache.clear();
            }

            const cards = Array.isArray(result.data)
                ? result.data
                : [];

            for (const card of cards) {
                const variants = Array.isArray(card.variants)
                    ? card.variants
                    : [];

                for (const variant of variants) {
                    if (
                        variant.type !== "graded" ||
                        !variant.id ||
                        !variant.grading?.canonical
                    ) {
                        continue;
                    }

                    const label = [
                        card.name,
                        card.number,
                        variant.grading.canonical,
                        variant.printing,
                        variant.language
                    ].filter(Boolean).join(" — ");

                    gradingOptionsCache.set(
                        String(variant.id),
                        label
                    );
                }
            }

            const previousSelection = gradedVariantSelect.value;

            const options = [...gradingOptionsCache.entries()].sort(
                (a, b) => a[1].localeCompare(
                    b[1],
                    undefined,
                    { numeric: true }
                )
            );

            gradedVariantSelect.replaceChildren(
                new Option("-- All available grades --", "")
            );

            for (const [id, label] of options) {
                gradedVariantSelect.add(new Option(label, id));
            }

            if (gradingOptionsCache.has(previousSelection)) {
                gradedVariantSelect.value = previousSelection;
            }

            gradedHelp.textContent = options.length > 0
                ? options.length +
                  " grading option(s) loaded. Choose one and add " +
                  "a new entry to queue that specific variant."
                : "No graded choices matched this entry.";
        }

        function addReturnedPrintingOptions(result) {
            const existing = new Set(
                Array.from(
                    printingSelect.options,
                    option => option.value
                )
            );

            const cards = Array.isArray(result.data)
                ? result.data
                : [];

            for (const card of cards) {
                const variants = Array.isArray(card.variants)
                    ? card.variants
                    : [];

                for (const variant of variants) {
                    const printing = variant.printing;

                    if (
                        typeof printing !== "string" ||
                        printing === "" ||
                        existing.has(printing)
                    ) {
                        continue;
                    }

                    printingSelect.add(
                        new Option(printing, printing)
                    );

                    existing.add(printing);
                }
            }
        }

        function addCell(row, value, className = "") {
            const cell = document.createElement("td");

            cell.textContent =
                value === null ||
                value === undefined ||
                value === ""
                    ? "—"
                    : String(value);

            if (className) {
                cell.className = className;
            }

            row.appendChild(cell);
            return cell;
        }

        function renderQueue() {
            queueBody.replaceChildren();
            queueCount.textContent = cardQueue.length;
            queueTable.hidden = cardQueue.length === 0;
            emptyQueueMessage.hidden = cardQueue.length !== 0;

            cardQueue.forEach((entry, index) => {
                const row = document.createElement("tr");

                addCell(row, index + 1);
                addCell(row, entry.fields.text3 || "Number lookup");
                addCell(row, entry.setName);
                addCell(row, entry.fields.text2);
                addCell(
                    row,
                    entry.fields.card_type === "graded"
                        ? "Graded"
                        : "Raw"
                );
                addCell(row, entry.conditionLabel);
                addCell(row, entry.fields.printing || "Any");
                addCell(row, entry.languageLabel);
                addCell(row, entry.status);

                const actionsCell = document.createElement("td");
                const removeButton = document.createElement("button");

                removeButton.type = "button";
                removeButton.textContent = "Remove";
                removeButton.disabled = queryInProgress;

                removeButton.addEventListener("click", () => {
                    const position = cardQueue.findIndex(
                        item => item.id === entry.id
                    );

                    if (position !== -1) {
                        cardQueue.splice(position, 1);
                    }

                    renderQueue();
                });

                actionsCell.appendChild(removeButton);
                row.appendChild(actionsCell);
                queueBody.appendChild(row);
            });

            refreshButtons();
        }

        function formatPrice(price, currency) {
            if (
                typeof price !== "number" ||
                !Number.isFinite(price)
            ) {
                return "Unavailable";
            }

            if (!currency) {
                return price.toFixed(2) +
                    " (currency unspecified)";
            }

            try {
                return new Intl.NumberFormat(undefined, {
                    style: "currency",
                    currency,
                    currencyDisplay: "code"
                }).format(price);
            } catch {
                return price.toFixed(2) + " " + currency;
            }
        }

        function formatUpdated(timestamp) {
            if (
                typeof timestamp !== "number" ||
                !Number.isFinite(timestamp)
            ) {
                return "—";
            }

            const date = new Date(timestamp * 1000);

            return Number.isNaN(date.getTime())
                ? "—"
                : date.toLocaleString();
        }

        function createResultSection(entry, index) {
            const section = document.createElement("section");
            section.className = "result-group";

            const heading = document.createElement("h3");
            heading.textContent =
                (index + 1) + ". " +
                (entry.fields.text3 || "Card number " + entry.fields.text2);

            const description = document.createElement("p");
            description.className = "result-description";

            description.textContent = [
                entry.setName,
                entry.fields.text2,
                entry.conditionLabel,
                entry.fields.printing || "Any printing",
                entry.languageLabel
            ].filter(Boolean).join(" — ");

            const content = document.createElement("div");

            section.append(heading, description, content);
            cardResults.appendChild(section);

            return content;
        }

        function renderCardResults(container, result) {
            container.replaceChildren();

            const cards = Array.isArray(result.data)
                ? result.data
                : [];

            if (cards.length === 0) {
                const message = document.createElement("p");
                message.textContent =
                    "No cards matched this entry's filters.";

                container.appendChild(message);

                return 0;
            }

            const wrapper = document.createElement("div");
            wrapper.className = "table-container";

            const table = document.createElement("table");
            table.className = "data-table";

            const thead = document.createElement("thead");
            const headerRow = document.createElement("tr");

            for (const heading of [
                "Card",
                "Set",
                "Number",
                "Condition / Grade",
                "Printing",
                "Language",
                "Market",
                "Market Price",
                "Price Status",
                "Updated",
                "Action"
            ]) {
                const th = document.createElement("th");
                th.scope = "col";
                th.textContent = heading;
                headerRow.appendChild(th);
            }

            thead.appendChild(headerRow);

            const tbody = document.createElement("tbody");
            let rowCount = 0;

            for (const card of cards) {
                const variants = Array.isArray(card.variants)
                    ? card.variants
                    : [];

                const setName = typeof card.set === "string"
                    ? card.set
                    : card.set?.name ?? "";

                for (const variant of variants) {
                    const conditionOrGrade =
                        variant.type === "graded"
                            ? variant.grading?.canonical ?? "Graded"
                            : variant.condition ?? "";

                    const markets = Array.isArray(variant.markets)
                        ? variant.markets
                        : [];

                    for (const market of markets.length ? markets : [null]) {
                        const row = document.createElement("tr");

                        addCell(row, card.name);
                        addCell(row, setName);
                        addCell(row, card.number);
                        addCell(row, conditionOrGrade);
                        addCell(row, variant.printing);
                        addCell(row, variant.language);
                        addCell(row, market?.region);

                        addCell(
                            row,
                            formatPrice(
                                market?.price,
                                market?.currency
                            ),
                            "market-price"
                        );

                        addCell(row, market?.price_status);

                        addCell(
    row,
    formatUpdated(market?.updated_at)
);

addDatabaseButton(row, card, variant, market);

tbody.appendChild(row);
rowCount++;
                    }
                }
            }

            table.append(thead, tbody);
            wrapper.appendChild(table);
            container.appendChild(wrapper);

            return rowCount;
        }

        // Add to the local array only. No fetch() is called here.
        cardForm.addEventListener("submit", event => {
            event.preventDefault();

            if (queryInProgress) {
                return;
            }

            addStatus.className = "";
            addStatus.textContent = "";

            if (cardQueue.length >= MAX_CARDS) {
                addStatus.className = "error";
                addStatus.textContent =
                    "The list is full. Remove an entry before adding another.";
                return;
            }

            if (!cardForm.reportValidity()) {
                return;
            }

            if (
                numberInput.value.trim() === "" &&
                nameInput.value.trim() === ""
            ) {
                addStatus.className = "error";
                addStatus.textContent =
                    "Enter a card name or set number.";
                return;
            }

            const data = new FormData(cardForm);

            const fields = {
                text1: String(data.get("text1") ?? "").trim(),
                text2: String(data.get("text2") ?? "").trim(),
                text3: String(data.get("text3") ?? "").trim(),
                extra1: String(data.get("extra1") ?? "en"),
                extra2: String(data.get("extra2") ?? ""),
                card_type: String(data.get("card_type") ?? "raw"),
                graded_variant_id: String(
                    data.get("graded_variant_id") ?? ""
                ),
                printing: String(data.get("printing") ?? "")
            };

            cardQueue.push({
                id: nextEntryId++,
                fields,
                setName: selectedText(setSelect),
                languageLabel: selectedText(languageSelect),
                conditionLabel: fields.card_type === "graded"
                    ? (
                        fields.graded_variant_id
                            ? selectedText(gradedVariantSelect)
                            : "All available grades"
                    )
                    : fields.extra2,
                status: "Not queried"
            });

            addStatus.className = "success";
            addStatus.textContent =
                "Added to list. No API request has been made.";

            renderQueue();
        });

        clearButton.addEventListener("click", () => {
            if (queryInProgress) {
                return;
            }

            cardQueue.length = 0;
            cardResults.replaceChildren();
            cardRequestStatus.textContent = "";
            addStatus.textContent = "";

            renderQueue();
        });

        // Only this button sends queued card searches to your API.
        queryButton.addEventListener("click", async () => {
            if (
                queryInProgress ||
                setsUpdateInProgress ||
                cardQueue.length === 0
            ) {
                return;
            }

            queryInProgress = true;
            cardResults.replaceChildren();
            addStatus.textContent = "";

            for (const entry of cardQueue) {
                entry.status = "Waiting";
            }

            const formControls = Array.from(
                cardForm.querySelectorAll("input, select, button")
            );

            const previousStates = formControls.map(
                control => control.disabled
            );

            formControls.forEach(control => {
                control.disabled = true;
            });

            let successfulRequests = 0;
            let failedRequests = 0;
            let emptyResults = 0;
            let totalRows = 0;

            renderQueue();

            try {
                for (let index = 0; index < cardQueue.length; index++) {
                    const entry = cardQueue[index];

                    entry.status = "Querying";
                    renderQueue();

                    cardRequestStatus.className = "";
                    cardRequestStatus.textContent =
                        "Querying entry " + (index + 1) +
                        " of " + cardQueue.length + "...";

                    const output = createResultSection(entry, index);
                    const formData = new FormData();

                    for (const [key, value] of Object.entries(entry.fields)) {
                        formData.append(key, value);
                    }

                    try {
                        const result = await requestJson(
                            apiUrl + "?route=search_cards",
                            {
                                method: "POST",
                                body: formData
                            }
                        );

                        if (result.success !== true) {
                            throw new Error(
                                result.error || "The card search failed."
                            );
                        }

                        const cards = Array.isArray(result.data)
                            ? result.data
                            : [];

                        totalRows += renderCardResults(output, result);
                        successfulRequests++;

                        if (cards.length === 0) {
                            emptyResults++;
                            entry.status = "No matches";
                        } else {
                            entry.status =
                                "Found " + cards.length + " card(s)";
                        }

                        addReturnedPrintingOptions(result);

                        if (entry.fields.card_type === "graded") {
                            populateGradingOptions(result, entry);
                        }
                    } catch (error) {
                        console.error(error);

                        failedRequests++;
                        entry.status = "Failed";

                        const message = document.createElement("p");
                        message.className = "error";
                        message.textContent = error.message;
                        output.appendChild(message);
                    }

                    renderQueue();
                }

                cardRequestStatus.className =
                    failedRequests > 0 ? "error" : "success";

                cardRequestStatus.textContent =
                    "Finished. " +
                    successfulRequests + " successful request(s), " +
                    failedRequests + " failed request(s), " +
                    emptyResults + " with no matches. " +
                    "Showing " + totalRows +
                    " variant/market row(s).";
            } finally {
                queryInProgress = false;

                formControls.forEach((control, index) => {
                    control.disabled = previousStates[index];
                });

                updateConditionFields();
                renderQueue();
            }
        });

        cardTypeSelect.addEventListener(
            "change",
            updateConditionFields
        );

        for (const id of [
            "text1",
            "text2",
            "text3",
            "extra1",
            "printing"
        ]) {
            const element = document.getElementById(id);

            element.addEventListener(
                element.tagName === "SELECT" ? "change" : "input",
                resetGradingOptions
            );
        }

        updateButton.addEventListener("click", async () => {
            if (queryInProgress || setsUpdateInProgress) {
                return;
            }

            setsUpdateInProgress = true;
            refreshButtons();

            updateStatus.className = "";
            updateStatus.textContent =
                "Fetching sets and updating sets.txt...";

            let saved = false;

            try {
                const result = await requestJson(
                    apiUrl + "?route=update_sets",
                    { method: "POST" }
                );

                if (result.success !== true) {
                    throw new Error(
                        result.error ||
                        "API did not confirm the file update."
                    );
                }

                saved = true;

                const pageResponse = await fetch(
                    window.location.href,
                    { cache: "no-store" }
                );

                if (!pageResponse.ok) {
                    throw new Error(
                        "Could not reload the saved sets."
                    );
                }

                const pageHtml = await pageResponse.text();

                const refreshedPage =
                    new DOMParser().parseFromString(
                        pageHtml,
                        "text/html"
                    );

                const refreshedSelect =
                    refreshedPage.getElementById("text1");

                const refreshedError =
                    refreshedPage.getElementById("sets-file-error");

                if (!refreshedSelect || !refreshedError) {
                    throw new Error(
                        "Could not find the updated dropdown."
                    );
                }

                if (!refreshedError.hidden) {
                    throw new Error(
                        refreshedError.textContent.trim()
                    );
                }

                const previousSelection = setSelect.value;

                const options = Array.from(
                    refreshedSelect.options,
                    option => new Option(
                        option.text,
                        option.value
                    )
                );

                setSelect.replaceChildren(...options);

                if (
                    options.some(
                        option => option.value === previousSelection
                    )
                ) {
                    setSelect.value = previousSelection;
                }

                if (setSelect.value !== previousSelection) {
                    resetGradingOptions();
                }

                setsCount.textContent =
                    Math.max(0, options.length - 1);

                setsFileError.textContent = "";
                setsFileError.hidden = true;

                updateStatus.className = "success";
                updateStatus.textContent =
                    "Success! Saved " + result.count +
                    " sets and refreshed the dropdown.";
            } catch (error) {
                console.error(error);

                updateStatus.className = "error";
                updateStatus.textContent = saved
                    ? "Sets were saved, but refreshing failed: " +
                      error.message
                    : "Error: " + error.message;
            } finally {
                setsUpdateInProgress = false;
                refreshButtons();
            }
        });

        updateConditionFields();
        renderQueue();

        function addDatabaseButton(row, card, variant, market) {
    const cell = document.createElement("td");
    const button = document.createElement("button");
    const message = document.createElement("small");

    button.type = "button";
    button.textContent = "Add to Database";

    message.className = "help-text";
    message.setAttribute("role", "status");

    const validPrice =
        typeof market?.price === "number" &&
        Number.isFinite(market.price) &&
        market.price >= 0;

    const validTimestamp =
        typeof market?.updated_at === "number" &&
        Number.isFinite(market.updated_at);

    const priceDate = validTimestamp
        ? new Date(market.updated_at * 1000)
        : null;

    const validDate =
        priceDate !== null &&
        !Number.isNaN(priceDate.getTime());

    if (!validPrice || !validDate) {
        button.disabled = true;
        message.textContent =
            "A price and update date are required to save.";
    }

    button.addEventListener("click", async () => {
        const setName = typeof card.set === "string"
            ? card.set
            : card.set?.name ?? "";

        const payload = {
            name: card.name ?? "",
            set: setName,
            set_num: card.number ?? "",
            lang: variant.language ?? "",
            price: market.price,
            price_date: priceDate.toISOString().slice(0, 10),

            condition: variant.type === "graded"
                ? null
                : variant.condition ?? null,

            printing: variant.printing ?? null,

            grade: variant.type === "graded"
                ? variant.grading?.canonical ?? null
                : null
        };

        button.disabled = true;
        button.textContent = "Saving...";
        message.className = "help-text";
        message.textContent = "";

        try {
            const result = await requestJson(
    cardsAddUrl,
    {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
    }
);

            if (result.success !== true) {
                throw new Error(
                    result.message ||
                    result.error || "The card could not be saved."
                );
            }

            button.textContent = "Added";
            message.className = "help-text success";
            message.textContent =
                "Database card ID: " + result.card.card_id;
        } catch (error) {
            console.error(error);

            button.disabled = false;
            button.textContent = "Retry Save";
            message.className = "help-text error";
            message.textContent = error.message;
        }
    });

    cell.append(button, message);
    row.appendChild(cell);
}

    </script>
</body>
</html>