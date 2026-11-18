let auditBtn;
let salesBtn;
let usersBtn;

window.addEventListener("load", init)
function init(){
    let url;

    auditBtn = document.getElementById("audit_log");
    url = "./audit.php";
    enableButtons(auditBtn, url);

    salesBtn = document.getElementById("sales_hist");
    url = "./sales.php";
    enableButtons(salesBtn, url);

    usersBtn = document.getElementById("users");
    url = "./users.php";
    enableButtons(usersBtn, url);
}
function enableButtons(btn, url) {
    btn.addEventListener("click", function () {
        window.location.href = url;
    });
}