<?PHP
  //Adjust the Login link depending on login status
  $isLoggedIn = isset($_SESSION["user"]);

  $isManager = $isLoggedIn && strtolower(trim($_SESSION["user"]["role"] ?? "")) === "manager";
?>
    <header>
        <button id = "enableNav" aria-label = "Toggle menu">☰</button>
        <a href = "./index.php"><img src = "./images/logo.jpg" alt = "Pokébase logo"></a>
    </header>
    <div id = "navButtons" class = "invisible">
        <button id = "home">Home</button>
        <button id = "inventory">Inventory</button>
        <button id = "sales">Sales</button>
        <button id = "trades">Trades</button>
        <?php if ($isManager): ?>
        <button id = "users">Users</button>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?>
            <form id = "logoutForm" method = "post" action = "./processes/logout.php">
                <button id = "logout" type = "submit">Logout</button>
            </form>
        <?php else: ?>
            <button id = "login">Login</button>
        <?php endif; ?>
        <!-- SET THIS TO SHOW UP IF PYTHON SERVER IS AVAILABLE -->
        <button id = "scan">Scan Cards</button>
    </div>
