<?php
    // Partial: included at the top of the manager pages (audit, sales, users).
    // Opened directly, it sends the user to the Audits page instead.
    if (basename($_SERVER["SCRIPT_FILENAME"]) === "manager.php") {
        header("Location: ./audit.php");
        exit;
    }

    // $isManager is set by navigation.php; employees can view audits/sales but not these options
    if (!empty($isManager)):
?>
<script src="managerNavigation.js"></script>
<div id="managerButtons">
    <button id="audit_log">Audit Log</button>
    <button id="sales_hist">Sales History</button>
    <button id="users">Users</button>
</div>
<?php endif; ?>
