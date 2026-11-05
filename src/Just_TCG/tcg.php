<?php

function postString($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? $_POST[$key]
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
$condition_input = postString('extra2');
$card_type_input = postString('card_type', 'raw');
$graded_variant_input = postString('graded_variant_id');
$printing_input = postString('extra3');

$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';

$validationErrors = [];

$rawConditions = [
    'Near Mint',
    'Lightly Played',
    'Moderately Played',
    'Heavily Played',
    'Damaged',
    'Sealed',
];

// Normalize compatible values from the previous form.
$legacyConditions = [
    'mint' => 'Near Mint',
    'near-mint' => 'Near Mint',
];

$condition_input = $legacyConditions[$condition_input]
    ?? $condition_input;

if (!in_array($card_type_input, ['raw', 'graded'], true)) {
    if ($submitted) {
        $validationErrors[] = 'Please select a valid card type.';
    }

    $card_type_input = 'raw';
}

/*
 * GRADED VARIANTS INTEGRATION
 *
 * Populate this array on the server with the selected card's
 * JustTCG v2 variants before building $gradedOptions.
 *
 * For example, after your backend has fetched the selected card:
 *
 * $justtcgVariants = $card['variants'] ?? [];
 *
 * Request graded variants in your backend using graded=include.
 *
 * Your current file only loads sets.txt; it does not fetch a card.
 * No card-fetching API route is assumed or invented here.
 */
$justtcgVariants = $justtcgVariants ?? [];

if (!is_array($justtcgVariants)) {
    $justtcgVariants = [];
}

$gradedOptions = [];

foreach ($justtcgVariants as $variant) {
    if (
        !is_array($variant)
        || ($variant['type'] ?? '') !== 'graded'
    ) {
        continue;
    }

    $id = $variant['id'] ?? null;
    $grading = $variant['grading'] ?? [];
    $label = is_array($grading)
        ? ($grading['canonical'] ?? null)
        : null;

    if (
        (is_string($id) || is_int($id))
        && is_string($label)
        && $label !== ''
    ) {
        $gradedOptions[(string) $id] = $label;
    }
}

asort($gradedOptions, SORT_NATURAL | SORT_FLAG_CASE);

$selectedGradingLabel = $gradedOptions[$graded_variant_input] ?? '';

// This must match the location used by your API.
$setsFile = 'C:/xampp/htdocs/PokeBase/src/api/just_tcg/sets.txt';

$sets = [];
$setsError = '';
$selectedSetName = '';

if (!is_file($setsFile) || !is_readable($setsFile)) {
    $setsError = 'No readable sets.txt found. Click Update Sets to create it.';
} else {
    $contents = file_get_contents($setsFile);

    if ($contents === false) {
        $setsError = 'Could not read sets.txt.';
    } else {
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $setsError = 'sets.txt does not contain valid JSON.';
        } else {
            // Accept either the list itself or a full API response.
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

                    if ($set_input === $set['id']) {
                        $selectedSetName = $set['name'];
                    }
                }

                if (count($sets) === 0) {
                    $setsError = 'The saved file contains no usable sets.';
                }
            }
        }
    }
}

if ($submitted) {
    if ($set_input === '' || $selectedSetName === '') {
        $validationErrors[] = 'Please select a valid set.';
    }

    if ($card_type_input === 'raw') {
        if (!in_array($condition_input, $rawConditions, true)) {
            $validationErrors[] = 'Please select a valid raw condition.';
        }

        // Ignore inactive graded input.
        $graded_variant_input = '';
        $selectedGradingLabel = '';
    } else {
        if (
            $graded_variant_input === ''
            || !array_key_exists($graded_variant_input, $gradedOptions)
        ) {
            $validationErrors[] =
                'Please select a graded option returned for the selected card.';
        }

        // Graded cards do not use a raw condition.
        $condition_input = '';
    }
}

$validSubmission = $submitted && count($validationErrors) === 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Just TCG</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 750px;
            margin: 40px auto;
            padding: 0 20px;
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
            cursor: wait;
            opacity: 0.6;
        }

        details {
            margin-bottom: 20px;
        }

        summary {
            cursor: pointer;
            margin-bottom: 16px;
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
        }

        [hidden] {
            display: none !important;
        }
    </style>
</head>
<body>
    <h1>Just TCG</h1>

    <section>


        <button id="update-sets-button" type="button">
            Update Sets
        </button>

        <p id="update-sets-status" role="status" aria-live="polite"></p>

        <p>
            <span id="sets-count"><?= count($sets) ?></span> sets loaded.
        </p>

        <p
            id="sets-file-error"
            class="error"
            <?= $setsError === '' ? 'hidden' : '' ?>
        ><?= escape($setsError) ?></p>
    </section>

    <?php if ($submitted && $validationErrors): ?>
        <section class="error" role="alert">
            <h2>Please check your selections</h2>

            <ul>
                <?php foreach ($validationErrors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <form id="card-form" method="POST" action="<?= escape($_SERVER['REQUEST_URI']) ?>">
        <div class="field">
            <label for="text1">Set</label>

            <select id="text1" name="text1" required>
                <option value="">-- Select a set --</option>

                <?php foreach ($sets as $set): ?>
                    <option
                        value="<?= escape($set['id']) ?>"
                        <?= $set_input === $set['id'] ? 'selected' : '' ?>
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
            >
        </div>

        <div class="field">
            <label for="text3">Name</label>

            <input
                type="text"
                id="text3"
                name="text3"
                value="<?= escape($name_input) ?>"
            >
        </div>

        <details <?= $submitted ? 'open' : '' ?>>
            <summary>More options</summary>

            <div class="field">
                <label for="extra1">Language</label>

                <select id="extra1" name="extra1">
                    <option
                        value="en"
                        <?= $language_input === 'en' ? 'selected' : '' ?>
                    >
                        English
                    </option>

                    <option
                        value="jap"
                        <?= $language_input === 'jap' ? 'selected' : '' ?>
                    >
                        Japanese
                    </option>

                    <option
                        value="ch"
                        <?= $language_input === 'ch' ? 'selected' : '' ?>
                    >
                        Chinese
                    </option>

                    <option
                        value="kor"
                        <?= $language_input === 'kor' ? 'selected' : '' ?>
                    >
                        Korean
                    </option>
                </select>
            </div>

            <div class="field">
                <label for="card_type">Card Type</label>

                <select id="card_type" name="card_type" required>
                    <option
                        value="raw"
                        <?= $card_type_input === 'raw' ? 'selected' : '' ?>
                    >
                        Raw
                    </option>

                    <option
                        value="graded"
                        <?= $card_type_input === 'graded' ? 'selected' : '' ?>
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
                    <option value="">-- Select a condition --</option>

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
                    <?= $card_type_input === 'graded'
                        ? 'required'
                        : 'disabled' ?>
                >
                    <option value="">
                        <?= $gradedOptions
                            ? '-- Select a grading option --'
                            : '-- No graded options loaded --' ?>
                    </option>

                    <?php foreach ($gradedOptions as $id => $label): ?>
                        <option
                            value="<?= escape($id) ?>"
                            <?= (string) $graded_variant_input === (string) $id
                                ? 'selected'
                                : '' ?>
                        >
                            <?= escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small id="graded-help" class="help-text">
                    <?php if ($gradedOptions): ?>
                        Select a grading option available for this card.
                    <?php else: ?>
                        No graded variants are currently loaded.
                        Your backend must load the selected card's
                        JustTCG variants to populate this list.
                    <?php endif; ?>
                </small>
            </div>

            <div class="field">
                <label for="extra3">Printing</label>

                <select id="extra3" name="extra3">
                    <option
                        value="normal"
                        <?= $printing_input === 'normal' ? 'selected' : '' ?>
                    >
                        Normal
                    </option>

                    <option
                        value="Holofoil"
                        <?= $printing_input === 'Holofoil' ? 'selected' : '' ?>
                    >
                        Holofoil
                    </option>

                    <option
                        value="1st"
                        <?= $printing_input === '1st' ? 'selected' : '' ?>
                    >
                        First Edition
                    </option>

                    <option
                        value="1st_Holofoil"
                        <?= $printing_input === '1st_Holofoil' ? 'selected' : '' ?>
                    >
                        First Edition Holofoil
                    </option>
                </select>
            </div>
        </details>

        <button type="submit">Save</button>
    </form>

    <section>
        <h2>Card Results</h2>

        <p id="card-request-status" role="status" aria-live="polite"></p>

        <pre id="card-results" hidden></pre>
    </section>

    <?php if ($validSubmission): ?>
        <section>
            <h2>Selected Options</h2>

            <ul>
                <li>
                    Set:
                    <?= escape($selectedSetName) ?>
                </li>

                <li>Set ID: <?= escape($set_input) ?></li>
                <li>Set Number: <?= escape($set_number_input) ?></li>
                <li>Name: <?= escape($name_input) ?></li>
                <li>Language: <?= escape($language_input) ?></li>

                <li>
                    Card Type:
                    <?= $card_type_input === 'graded' ? 'Graded' : 'Raw' ?>
                </li>

                <?php if ($card_type_input === 'raw'): ?>
                    <li>
                        Condition: <?= escape($condition_input) ?>
                    </li>
                <?php else: ?>
                    <li>
                        Grading Option:
                        <?= escape($selectedGradingLabel) ?>
                    </li>

                    <li>
                        Graded Variant ID:
                        <?= escape($graded_variant_input) ?>
                    </li>
                <?php endif; ?>

                <li>
                    Printing:
                    <?= escape($printing_input) ?>
                </li>

            </ul>
        </section>
    <?php endif; ?>

    <script>

        const cardForm = document.getElementById("card-form");
        const cardRequestStatus = document.getElementById("card-request-status");
        const cardResults = document.getElementById("card-results");

        cardForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            
            const submitButton = cardForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            cardRequestStatus.className = "";
            cardRequestStatus.textContent = "Processing your request...";
            cardResults.textContent = "";

            try{
                const formData = newFormData(cardForm);
                
                const result = await requestJson(
                    apiUrl + "?route=search_cards",
                    {
                        method: "POST",
                        body: formData
                    }

                );

                if (result.success !== true){
                    throw new Error(
                        result.error ||
                        "API did not confirm the card search."
                    );
                }

                cardRequestStatus.className = "success";
                cardRequestStatus.textContent = "Success! Card search completed.";

                cardResults.textContent = JSON.stringify(result, null, 2);
            } catch (error) {
                console.error(error);

                cardRequestStatus.className = "error";
                cardRequestStatus.textContent = "Error: " + error.message;
            } finally {
                submitButton.disabled = false;
            }

        });


        const apiUrl = "/PokeBase/src/api/just_tcg/index.php";

        const updateButton = document.getElementById("update-sets-button");
        const updateStatus = document.getElementById("update-sets-status");
        const setSelect = document.getElementById("text1");
        const setsCount = document.getElementById("sets-count");
        const setsFileError = document.getElementById("sets-file-error");

        const cardTypeSelect = document.getElementById("card_type");
        const rawConditionField =
            document.getElementById("raw-condition-field");
        const gradedConditionField =
            document.getElementById("graded-condition-field");
        const rawConditionSelect = document.getElementById("extra2");
        const gradedVariantSelect =
            document.getElementById("graded_variant_id");

        function updateConditionFields() {
            const isGraded = cardTypeSelect.value === "graded";

            rawConditionField.hidden = isGraded;
            rawConditionSelect.disabled = isGraded;
            rawConditionSelect.required = !isGraded;

            gradedConditionField.hidden = !isGraded;
            gradedVariantSelect.disabled = !isGraded;
            gradedVariantSelect.required = isGraded;
        }

        cardTypeSelect.addEventListener(
            "change",
            updateConditionFields
        );

        updateConditionFields();

        async function requestJson(url, options = {}) {
            const response = await fetch(url, options);
            const rawResponse = await response.text();

            console.log("Request URL:", url);
            console.log("HTTP status:", response.status);
            console.log("API response:", rawResponse);

            let data;

            try {
                data = JSON.parse(rawResponse);
            } catch {
                throw new Error(
                    "HTTP " + response.status +
                    ": Expected JSON but received: " +
                    rawResponse.slice(0, 500)
                );
            }

            if (!response.ok) {
                throw new Error(
                    "HTTP " + response.status + ": " +
                    (data?.error || "Request failed.")
                );
            }

            return data;
        }

        updateButton.addEventListener("click", async function () {
            updateButton.disabled = true;
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

                // Reload the page in the background to read sets.txt,
                // preserving the user's current form entries.
                const pageResponse = await fetch(window.location.href, {
                    cache: "no-store"
                });

                if (!pageResponse.ok) {
                    throw new Error("Could not reload the saved sets.");
                }

                const pageHtml = await pageResponse.text();
                const refreshedPage = new DOMParser().parseFromString(
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

                // Copy only option text and values.
                const options = Array.from(
                    refreshedSelect.options,
                    option => new Option(option.text, option.value)
                );

                setSelect.replaceChildren(...options);

                if (
                    options.some(
                        option => option.value === previousSelection
                    )
                ) {
                    setSelect.value = previousSelection;
                }

                setsCount.textContent =
                    Math.max(0, options.length - 1);

                setsFileError.textContent = "";
                setsFileError.hidden = true;

                updateStatus.className = "success";
                updateStatus.textContent =
                    "Success! Saved " + result.count +
                    " sets and refreshed the dropdown." +
                    (
                        result.bytesWritten !== undefined
                            ? " " + result.bytesWritten +
                              " bytes written."
                            : ""
                    );
            } catch (error) {
                console.error(error);

                updateStatus.className = "error";
                updateStatus.textContent = saved
                    ? "The API reported that sets.txt was saved, " +
                      "but refreshing the dropdown failed: " +
                      error.message
                    : "Error: " + error.message;
            } finally {
                updateButton.disabled = false;
            }
        });
    </script>
</body>
</html>