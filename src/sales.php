<?php
    include "includes/header.php";
    include "navigation.php";
    include "includes/functions.php";
    require_once __DIR__ . "/includes/config.php";

    // 1. Fetch inventory
    $invDataURL  = $mainURL . "/api/inventory_tracker/get";
    $invResponse = getJSONFromURL($invDataURL);
    $inv         = $invResponse["inventory_tracker"] ?? [];

    // 2. Filters (safe defaults, no more undefined $date)
    $date      = trim($_GET['date'] ?? date('Y-m-d'));
    $total_val = trim($_GET['total_value'] ?? '');

    // Monday-Sunday week containing the selected date
    $ts        = strtotime($date) ?: time();
    $weekStart = strtotime('monday this week', $ts);
    $weekEnd   = strtotime('sunday this week 23:59:59', $ts);

    // 3. Build chart data from the inventory: sum total_value per weekday
    $days   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $totals = array_fill_keys($days, 0);

    foreach ($inv as $row) {
        $rowTs = strtotime($row['date'] ?? '');
        if (!$rowTs || $rowTs < $weekStart || $rowTs > $weekEnd) {
            continue;
        }
        $rowVal = (float)($row['total_value'] ?? 0);
        if ($total_val !== '' && $rowVal < (float)$total_val) {
            continue; // only include rows >= the minimum total value
        }
        $totals[date('l', $rowTs)] += $rowVal;
    }

    $dataPoints = [];
    foreach ($totals as $label => $value) {
        $dataPoints[] = ["label" => $label, "value" => $value];
    }

    // 4. Format for HTML data attributes
    $labels     = array_column($dataPoints, 'label');
    $values     = array_column($dataPoints, 'value');
    $jsonLabels = htmlspecialchars(json_encode($labels), ENT_QUOTES, 'UTF-8');
    $jsonValues = htmlspecialchars(json_encode($values, JSON_NUMERIC_CHECK), ENT_QUOTES, 'UTF-8');
?>

<div id="main">
    <h2 style="text-align:center">Recent sales</h2>

    <form method="get" style="text-align:center; margin-bottom:1rem;">
        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
        <input type="number" step="0.01" name="total_value" placeholder="Min total value"
               value="<?php echo htmlspecialchars($total_val); ?>">
        <button type="submit">Filter</button>
    </form>

    <div class="chart-container">
        <canvas id="myLineChart"
                data-labels="<?php echo $jsonLabels; ?>"
                data-values="<?php echo $jsonValues; ?>">
        </canvas>
    </div>
</div>

<!-- Chart.js first, then your script (defer keeps the order) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="profitTracker.js" defer></script>

<?php
    include "includes/footer.php";
?>