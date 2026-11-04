<?php
    require_once __DIR__ . "/includes/config.php";
    include "includes/header.php";
    include "navigation.php";
    include "search.php";
?>
<script src="search.js" defer></script>
<div id = "main">
    <div class="topnav" style = "text-align:center">
        <h2>Search for cards in inventory here</h2>
        <div class="topnav">
            <input type="text" id="search-bar" placeholder="Search..">
        </div>

        <!-- FILTERS -->
        <select id="sets-select" class="Filters" style="flex: 1; min-width: 50px; text-align:center;">
            <option value="">Select a Set</option>
            <?php
            if ($cards) {
                $displayedSets = [];
                foreach ($cards as $card) {
                    if (!isset($displayedSets[$card["set"]])) {
                        $displayedSets[$card["set"]] = true;
                        $setValue = htmlspecialchars($card['set']);
                        echo "<option value=\"{$setValue}\">{$setValue}</option>";
                    }
                }
            }
            ?>
        </select>

        <select id="prints-select" class="Filters" style="flex: 1; min-width: 50px; text-align:center;">
            <option value="">Select a Print</option>
            <?php
            if ($cards) {
                $displayedPrints = [];
                foreach ($cards as $card) {
                    if (!isset($displayedPrints[$card["printing"]])) {
                        $displayedPrints[$card["printing"]] = true;
                        $printValue = htmlspecialchars($card['printing']);
                        echo "<option value=\"{$printValue}\">{$printValue}</option>";
                    }
                }
            }
            ?>
        </select>

        <div id="results"></div>
    </div>
</div>
<?php
    include "includes/footer.php";
?>