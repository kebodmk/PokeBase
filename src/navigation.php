<?PHP
  //Adjust the Login link depending on login status
  // Read from the database on every page load, so role changes show without logging in again
  $sessionUser = getSessionUser();
  $isLoggedIn = $sessionUser !== null;

  $isManager = $isLoggedIn && strtolower(trim($sessionUser["role"])) === ROLE_MANAGER;

  $profileName = $isLoggedIn
    ? trim($sessionUser["first_name"] . " " . $sessionUser["last_name"])
    : "";
?>
    <header>
        <div id = "headerLeft">
            <button id = "enableNav" aria-label = "Toggle menu">☰</button>
            <?php if ($isLoggedIn): ?>
            <!-- Display only: shows who is logged in, not clickable -->
            <div id = "profile" title = "Logged in as <?php echo htmlspecialchars($profileName); ?>">
                <svg viewBox = "0 0 24 24" fill = "none" stroke = "currentColor" stroke-width = "1.5" aria-hidden = "true">
                    <circle cx = "12" cy = "12" r = "10.5"/>
                    <circle cx = "12" cy = "9.5" r = "3.5"/>
                    <path d = "M5.5 19c1.4-2.6 3.8-4 6.5-4s5.1 1.4 6.5 4"/>
                </svg>
                <span id = "profileName"><?php echo htmlspecialchars($profileName); ?></span>
            </div>
            <?php endif; ?>
        </div>
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
