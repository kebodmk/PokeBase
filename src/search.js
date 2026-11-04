const searchBar = document.getElementById('search-bar');
const setFilter = document.getElementById('sets-select');
const printFilter = document.getElementById('prints-select');
const resultsDiv = document.getElementById('results');

let debounceTimer;
let controller = new AbortController();

function runSearch() {
    const query = searchBar.value.trim();

    // Cancel any request still in flight so an old, slow response
    // can't overwrite a newer one.
    controller.abort();

    if (query === '') {
        resultsDiv.innerHTML = '';
        return;
    }

    controller = new AbortController();

    const params = new URLSearchParams({
        query: query,
        set: setFilter.value,
        printing: printFilter.value
    });

    fetch('search.php?' + params.toString(), { signal: controller.signal })
        .then(response => response.text())
        .then(data => {
            resultsDiv.innerHTML = data;
        })
        .catch(error => {
            if (error.name !== 'AbortError') {
                console.error('Error fetching results:', error);
            }
        });
}

// Text box is debounced so we don't fire a request on every keystroke.
searchBar.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, 300);
});

// Dropdowns are single clicks, so refresh immediately.
setFilter.addEventListener('change', runSearch);
printFilter.addEventListener('change', runSearch);