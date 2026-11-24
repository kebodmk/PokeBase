const baseURL = "../api";

let passed = 0;
let failed = 0;

function displayResult(testName, success, message = "") {

    const results = document.getElementById("results");

    const item = document.createElement("li");

    item.textContent =
        `${success ? "PASS" : "FAIL"}: ${testName} ${message}`;

    item.style.color = success ? "green" : "red";

    results.appendChild(item);

    if (success) {
        passed++;
    } else {
        failed++;
    }
}


// TEST 1: Search cards
async function testSearchCards() {

    const testName = "Search Cards";

    try {

        const response = await fetch(`${baseURL}/cards/search`);

        const data = await response.json();

        const success =
            response.status === 200 &&
            data.success === true &&
            Array.isArray(data.cards) &&
            data.count === data.cards.length;

        displayResult(testName, success);

    } catch (error) {

        displayResult(testName, false, error.message);
    }
}


// TEST 2: Invalid price filter
async function testInvalidPriceFilter() {

    const testName = "Invalid Price Filter";

    try {

        const response = await fetch(
            `${baseURL}/cards/search?price_min=abc`
        );

        const data = await response.json();

        const success =
            response.status === 400 &&
            data.success === false;

        displayResult(testName, success);

    } catch (error) {

        displayResult(testName, false, error.message);
    }
}


// TEST 3: Wrong method on search
async function testSearchMethodNotAllowed() {

    const testName = "Search Method Not Allowed";

    try {

        const response = await fetch(
            `${baseURL}/cards/search`,
            { method: "POST" }
        );

        const data = await response.json();

        const success =
            response.status === 405 &&
            data.success === false;

        displayResult(testName, success);

    } catch (error) {

        displayResult(testName, false, error.message);
    }
}


// TEST 4: Unknown endpoint
async function testEndpointNotFound() {

    const testName = "Endpoint Not Found";

    try {

        const response = await fetch(`${baseURL}/cards/doesnotexist`);

        const data = await response.json();

        const success =
            response.status === 404 &&
            data.success === false;

        displayResult(testName, success);

    } catch (error) {

        displayResult(testName, false, error.message);
    }
}


async function runTests() {

    passed = 0;
    failed = 0;

    document.getElementById("results").innerHTML = "";

    await testSearchCards();
    await testInvalidPriceFilter();
    await testSearchMethodNotAllowed();
    await testEndpointNotFound();

    document.getElementById("summary").textContent =
        `Tests Passed: ${passed} | Tests Failed: ${failed}`;
}
