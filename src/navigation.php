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
    <button id = "enableNav">☰</button>
    <div id = "navButtons" class = "invisible">
        <button id = "home">Home</button>
        <BR>
        <!-- Right-sided navbar links. Hide them on small screens -->
        <button id = "inventory">Inventory</button>
        <BR>
        <button id = "sales">Sales</button>
        <BR>
        <button id = "trades">Trades</button>
        <BR>
        <button id = "login">Login</button>
        <BR>
        <!-- SET THIS TO SHOW UP IF PYTHON SERVER IS AVAILABLE -->
        <button id = "scan">Scan Cards</button>
        <BR>
    </div>