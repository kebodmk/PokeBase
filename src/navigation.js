let navBtn;
let homeBtn;
let inventoryBtn;
let scannerBtn;
let tradesBtn;
let loginBtn;
let logoutBtn;
let managerBtn;

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

    tradesBtn = document.getElementById("trades");
    url = "./trades.php";
    enableButtons(tradesBtn, url);

    addCardsBtn = document.getElementById("addCards");
    url = "./tcg.php";
    enableButtons(addCardsBtn, url);

    managerBtn = document.getElementById("manager");
    url = "./audit.php";
    if (managerBtn) enableButtons(managerBtn, url);

    loginBtn = document.getElementById("login");
    url = "./login.php";
    if (loginBtn) enableButtons(loginBtn, url);

    logoutBtn = document.getElementById("logout");
    url = "./logout.php";
    if (logoutBtn) enableButtons(logoutBtn, url);

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