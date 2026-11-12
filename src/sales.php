<?php
    include "includes/header.php";
    include "navigation.php";
    include "includes/functions.php";
    require_once __DIR__ . "/includes/config.php";

    // Fetch transactions
    $transDataUrl = $mainURL . "/api/transactions/get";
    $transResponse = getJSONFromURL($transDataUrl);
    $transaction = $transResponse['transaction'] ?? [];

    // 1. Fetch inventory
    $invDataURL  = $mainURL . "/api/inventory_tracker/get";
    $invResponse = getJSONFromURL($invDataURL);
    $inv         = $invResponse["inventory"] ?? [];
    

    $date      = trim($_GET['date'] ?? date('Y-m-d'));
    $total_val = trim($_GET['total_value'] ?? '');

    // First and last moment of the month containing the selected date
    $ts         = strtotime($date) ?: time();
    $monthStart = strtotime(date('Y-m-d 00:00:00', $ts));
    $monthEnd   = strtotime('+1 month 23:59:59', $monthStart); 

    // Sum total_value per logged date (days with no rows never get a key)
    $totals = [];

    foreach ($inv as $row) {
        $rowTs = strtotime($row['date'] ?? '');
        if (!$rowTs || $rowTs < $monthStart || $rowTs > $monthEnd) {
            continue;
        }
        $rowVal = (float)($row['total_value'] ?? 0);
        if ($total_val !== '' && $rowVal < (float)$total_val) {
            continue;
        }
        $key = date('Y-m-d', $rowTs);
        $totals[$key] = ($totals[$key] ?? 0) + $rowVal;
    }

    ksort($totals); // chronological order

    $labels = array_map(fn($d) => date('M j', strtotime($d)), array_keys($totals));
    $values = array_values($totals);

    $jsonLabels = htmlspecialchars(json_encode($labels), ENT_QUOTES, 'UTF-8');
    $jsonValues = htmlspecialchars(json_encode($values), ENT_QUOTES, 'UTF-8');
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
    
    <div class="table-container">
        <table id="transactionTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Employee ID</th>
                    <th>Sold For</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($transaction as $row): ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($row['trans_id']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['date']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['user_id']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['sold_for']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
    </div>
    
</div>

<!-- Chart.js first, then your script (defer keeps the order) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="profitTracker.js" defer></script>

<?php
    include "includes/footer.php";
?>