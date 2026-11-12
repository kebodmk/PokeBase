<?PHP
  //Adjust the Login link depending on login status
  $isLoggedIn = isset($_SESSION['user_id']);
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
        <?php if ($isLoggedIn): ?>
            <button id="login">Login</button>
        <?php else: ?>
            <button id="logout">Logout</button>
        <?php endif; ?>
        <!-- SET THIS TO SHOW UP IF PYTHON SERVER IS AVAILABLE -->
        <button id = "scan">Scan Cards</button>
    </div>
