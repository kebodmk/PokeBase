let navBtn;
let homeBtn;
let inventoryBtn;
let salesBtn;
let scannerBtn;
let tradesBtn;

const currentUrl = window.location.href;
window.addEventListener("load", init)
function init(){
    let url;

    navBtn = document.getElementById("enableNav");
    navBtn.addEventListener("click", showButtons);

    homeBtn = document.getElementById("home");
    url = "./index.php";
    enableButtons(homeBtn, url);

    inventoryBtn = document.getElementById("inventory");
    url = "./inventory.php";
    enableButtons(inventoryBtn, url);

    salesBtn = document.getElementById("sales");
    url = "./sales.php";
    enableButtons(salesBtn, url);

    tradesBtn = document.getElementById("trades");
    url = "./trades.php";
    enableButtons(tradesBtn, url);

    scannerBtn = document.getElementById("scan");
    url = "./scanner.php";
    enableButtons(scannerBtn, url);
    
}

function showButtons(){
    document.getElementById("navButtons").classList.toggle("invisible");
    document.getElementById("main").classList.toggle("invisible");
}

//Checks what buttons can be displayed and adds event listeners to each.
function enableButtons(btn, url){
    if(!checkURL(url)){
        btn.addEventListener("click", function(){window.location.href = url;});
    }
    else{
        btn.classList.add("invisible");
    }
}

//Checks to see if already on page
function checkURL(url){
    if (currentUrl.includes(url.substring(1))){
        return true;
    }
    return false;
}