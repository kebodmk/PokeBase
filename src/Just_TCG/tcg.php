<?php
$set_input = isset($_POST["text1"]) && is_string($_POST["text1"]) ? $_POST["text1"] : "";
$set_number_input = isset($_POST["text2"]) && is_string($_POST["text2"]) ? $_POST["text2"] : "";
$name_input = isset($_POST["text3"]) && is_string($_POST["text3"]) ? $_POST["text3"] : "";
$extra_input_1 = isset($_POST["extra1"]) && is_string($_POST["extra1"]) ? $_POST["extra1"] : "";
$extra_input_2 = isset($_POST["extra2"]) && is_string($_POST["extra2"]) ? $_POST["extra2"] : "";
$extra_input_3 = isset($_POST["extra3"]) && is_string($_POST["extra3"]) ? $_POST["extra3"] : "";
$extra_input_4 = isset($_POST["extra4"]) && is_string($_POST["extra4"]) ? $_POST["extra4"] : "";
$submitted = $_SERVER["REQUEST_METHOD"] === "POST";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Just TCG</title>
</head>
<body>
    <h1>Just TCG</h1>

    <button id="update-sets-button" type="button">Update Sets</button>
<p id="update-sets-status"></p>

<script>
const updateButton = document.getElementById("update-sets-button");
const updateStatus = document.getElementById("update-sets-status");

updateButton.addEventListener("click", async function () {
    updateStatus.textContent = "Button clicked. Sending request...";
    updateButton.disabled = true;

    try {
        const response = await fetch("C:\xampp\htdocs\PokeBase\src\api\just_tcg\index.php", {
            method: "POST"
        });

        // Read as text first so PHP errors or HTML error pages are visible.
        const rawResponse = await response.text();

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

        if (!response.ok || data.success !== true) {
            throw new Error(
                "HTTP " + response.status + ": " +
                (data.error || "API did not confirm a successful file update.")
            );
        }

        updateStatus.textContent =
            "Success! Saved " + data.count + " sets. " +
            (data.bytesWritten ?? "?") + " bytes written.";
    } catch (error) {
        console.error(error);
        updateStatus.textContent = "Error: " + error.message;
    } finally {
        updateButton.disabled = false;
    }
});
</script>

<!-- Your existing form goes below this closing script tag. -->

    <form method="post">
        <label for="text1">Set</label>
        <input type="text" id="text1" name="text1" value="<?= htmlspecialchars($set_input, ENT_QUOTES, "UTF-8") ?>">
        <BR>
        <label for="text2">Set Number</label>
        <input type="text" id="text2" name="text2" value="<?= htmlspecialchars($set_number_input, ENT_QUOTES, "UTF-8") ?>">
        <BR>
        <label for="text3">Name</label>
        <input type="text" id="text3" name="text3" value="<?= htmlspecialchars($name_input, ENT_QUOTES, "UTF-8") ?>">
        <BR>
        <details>
            <summary>More options</summary>
            <label for="extra1">Language</label>
            <select id="language" name = "Language">
                <option value="en" <?= $extra_input_1 === "en" ? "selected" : "" ?>>English</option>
                <option value="jap" <?= $extra_input_1 === "jap" ? "selected" : "" ?>>Japanese</option>
                <option value="ch" <?= $extra_input_1 === "ch" ? "selected" : "" ?>>Chinese</option>
                <option value="kor" <?= $extra_input_1 === "kor" ? "selected" : "" ?>>Korean</option>
            </select>
            <label for="extra2">Additional field 2</label>
            <input type="text" id="extra2" name="extra2" value="<?= htmlspecialchars($extra_input_2, ENT_QUOTES, "UTF-8") ?>">

            <label for="extra3">Additional field 3</label>
            <input type="text" id="extra3" name="extra3" value="<?= htmlspecialchars($extra_input_3, ENT_QUOTES, "UTF-8") ?>">

            <label for="extra4">Additional field 4</label>
            <input type="text" id="extra4" name="extra4" value="<?= htmlspecialchars($extra_input_4, ENT_QUOTES, "UTF-8") ?>">
        </details>

        <button type="submit">Save</button>
    </form>

    <?php if ($submitted): ?>
        <h2>Selected Options</h2>
        <ul>
            <li><strong>Set:</strong> <?= htmlspecialchars($set_input, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Set Number:</strong> <?= htmlspecialchars($set_number_input, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Name:</strong> <?= htmlspecialchars($name_input, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Additional field 1:</strong> <?= htmlspecialchars($extra_input_1, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Additional field 2:</strong> <?= htmlspecialchars($extra_input_2, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Additional field 3:</strong> <?= htmlspecialchars($extra_input_3, ENT_QUOTES, "UTF-8") ?></li>
            <li><strong>Additional field 4:</strong> <?= htmlspecialchars($extra_input_4, ENT_QUOTES, "UTF-8") ?></li>
        </ul>
    <?php endif; ?>
</body>
</html>
