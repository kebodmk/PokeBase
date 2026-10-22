const searchBar = document.getElementById('search-bar');
const resultsDiv = document.getElementById('results');
let debounceTimer;

searchBar.addEventListener('input', function () {
    const query = this.value.trim();

    clearTimeout(debounceTimer);

    debounceTimer = setTimeout(() => {
        if (query === '') {
            resultsDiv.innerHTML = '';
            return;
        }

        fetch('search.php?query=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(data => {
                resultsDiv.innerHTML = data;
            })
            .catch(error => console.error('Error fetching results:', error));
    }, 300);
});