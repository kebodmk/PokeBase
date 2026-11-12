<?php
    require_once __DIR__ . "/includes/auth_cookie.php";
    require_once __DIR__ . "/includes/trade_options.php";
    requirePageRole([ROLE_MANAGER, ROLE_EMPLOYEE]);

    include "includes/header.php";
    include "navigation.php";

    function options(array $values): string
    {
        $html = '';
        foreach ($values as $value) {
            $value = htmlspecialchars($value);
            $html .= "<option value=\"$value\">$value</option>";
        }
        return $html;
    }
?>
<div id = "main">
    <div id = "trades">
        <div id = "trades-top">
            <button type = "button">Past Transactions</button>
        </div>
        <form id = "trade-form" action = "processes/complete_trade.php" method = "POST">
            <div id = "trade-grid">
                <section class = "trade-column" data-recieved = "1">
                    <h3>Incoming (Purchases)</h3>
                    <div class = "card-list"></div>
                    <button type = "button" class = "add-card" aria-label = "Add incoming card">+</button>
                </section>
                <div id = "trade-divider"></div>
                <section class = "trade-column" data-recieved = "0">
                    <h3>Outgoing (Sales)</h3>
                    <div class = "card-list"></div>
                    <button type = "button" class = "add-card" aria-label = "Add outgoing card">+</button>
                </section>
                <label class = "money">Incoming Money
                    <input type = "text" name = "incoming_money" inputmode = "decimal" pattern = "\d+(\.\d{1,2})?" title = "A dollar amount, e.g. 12.50">
                </label>
                <label id = "cards-only"><input type = "checkbox" name = "cards_only" value = "1"> Cards Only</label>
                <label class = "money">Outgoing Money
                    <input type = "text" name = "outgoing_money" inputmode = "decimal" pattern = "\d+(\.\d{1,2})?" title = "A dollar amount, e.g. 12.50">
                </label>
            </div>
            <p id = "trade-status" role = "status"></p>
            <button type = "submit" id = "complete-transaction">Complete Transaction</button>
        </form>
    </div>
</div>
<template id = "card-template">
    <div class = "trade-card editing">
        <label>Set
            <select data-field = "set" required>
                <option value = "">-- Select a set --</option>
                <?php echo options(getSetNames()); ?>
            </select>
        </label>
        <label>Set Number <input type = "text" data-field = "set_num" maxlength = "45" required></label>
        <label>Name <input type = "text" data-field = "name" maxlength = "45" required></label>
        <label>Language
            <select data-field = "lang" required><?php echo options(TRADE_LANGUAGES); ?></select>
        </label>
        <label>Card Type
            <select data-field = "card_type" required>
                <option value = "raw">Raw</option>
                <option value = "graded">Graded</option>
            </select>
        </label>
        <label data-type = "raw">Condition
            <select data-field = "condition" required>
                <option value = "">-- Select a condition --</option>
                <?php echo options(TRADE_CONDITIONS); ?>
            </select>
        </label>
        <label data-type = "graded">Grade <input type = "number" data-field = "grade" min = "1" max = "10" step = "0.5" required></label>
        <label>Printing
            <select data-field = "printing" required><?php echo options(TRADE_PRINTINGS); ?></select>
        </label>
        <button type = "button" class = "confirm-card">Add Card</button>
        <button type = "button" class = "cancel-card">Cancel</button>
    </div>
</template>
<script src = "trades.js" defer></script>
<?php
    include "includes/footer.php";
?>
