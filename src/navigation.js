let navBtn;
let homeBtn;
let inventoryBtn;
let salesBtn;
let scannerBtn;
let tradesBtn;
let loginBtn;
let logoutBtn;

const currentUrl = window.location.pathname;
window.addEventListener("load", init)
function init(){
    let url;

    navBtn = document.getElementById("enableNav");
    navBtn.addEventListener("click", showButtons);

    homeBtn = document.getElementById("home");
    url = "./";
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

    loginBtn = document.getElementById("login");
    url = "./login.php";
    enableButtons(loginBtn, url);

    logoutBtn = document.getElementById("logout");
    url = "./logout.php";
    enableButtons(logoutBtn, url);

    loginBtn = document.getElementById("login");
    if (loginBtn) {
        enableButtons(loginBtn, "./login.php");
    }

    logoutBtn = document.getElementById("logout");
    if (logoutBtn) {
        enableButtons(logoutBtn, "./logout.php");
    }

    scannerBtn = document.getElementById("scan");
    url = "./scanner.php";
    enableButtons(scannerBtn, url);
    
}

function showButtons(){
    document.getElementById("navButtons").classList.toggle("invisible");
    document.getElementById("main").classList.toggle("invisible");
}

// Returns the file name of a path, treating "" / index.* as "home"
function getPage(path) {
    const page = path.substring(path.lastIndexOf("/") + 1);
    if (page === "" || page === "index.php" || page === "index.html") {
        return "home";
    }
    return page;
}

// True if the given url points to the page we're  on
function checkURL(url) {
    const target = new URL(url, window.location.href).pathname;
    return getPage(window.location.pathname) === getPage(target);
}

// Show the button unless we're already on that page
function enableButtons(btn, url) {
    if (!btn) return;

    if (!checkURL(url)) {
        btn.addEventListener("click", function () {
            window.location.href = url;
        });
    } else {
        btn.classList.add("invisible");
    }
}