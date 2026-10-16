<?php
    include "header.php";
    include "navigation.php"
?>
<div id = "main">
    <div id = "intro">
        <h1> Welcome to Pokébase! </h1>
        <h2> Pokébase is a tool used to keep inventory of your cards and see the value of each of them.</h2>
        <BR><BR>
        <h2> Features </h2>
        <BR>
        <ol>
            <li><h3>Inventory</h3></li>
            <ul>
                <li>Check any card you have in inventory's value with a search</li>
            </ul>
            <li><h3>Sales</h3></li>
            <ul>
                <li>Search the value of a card at market price</li>
                <li>Log a recent sale</li>
                <li>Look back at previous sales completed</li>
            </ul>
            <li><h3>Trades</h3></li>
            <ul>
                <li>Compare value of cards while negotiation trades</li>
                <li>Log recent trades</li>
                <li>Look back at previous trades completed</li>
            </ul>
            <li><h3>Scan Cards</h3></li>
            <ul>
                <li>Take a picture of a card to get information on a card without typing it in</li>
            </ul>
        </ol>
    </div>
    <br>
    <div class = "logo-container">
        <img src = "./images/logo.jpg" alt = "Pokébase logo">
    </div>
</div>
<?php
    include "footer.php";
?>