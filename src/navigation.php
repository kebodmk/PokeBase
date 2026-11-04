<?PHP
  //Adjust the Login link depending on login status
  /**
  if(!isset($_SESSION["LoginStatus"]) || $_SESSION["LoginStatus"]!="YES"){
    $linkName = "Login";
  }else{
    $linkName = "Profile";
  }
    */
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
        <button id = "login">Login</button>
        <!-- SET THIS TO SHOW UP IF PYTHON SERVER IS AVAILABLE -->
        <button id = "scan">Scan Cards</button>
    </div>
