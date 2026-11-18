document.addEventListener('DOMContentLoaded', () => {
    // 1. Select the canvas element
    const canvas = document.getElementById('myLineChart');
    if (!canvas) return;

    // 2. Extract and decode the JSON arrays from the data attributes
    const chartLabels = JSON.parse(canvas.getAttribute('data-labels'));
    const chartData = JSON.parse(canvas.getAttribute('data-values'));

    const money = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    });
    // 3. Initialize the Chart.js line graph
    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Total Card Value',
                data: chartData,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => money.format(value)
                    }
                }
            }
        }
    });
});
const addBtn = document.getElementById('updateInventoryBtn');
if (addBtn) {
    addBtn.addEventListener('click', async () => {
        const url = new URL('./api/inventory_tracker/add', window.location.href).href;
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                console.error('Add entry failed:', res.status, data);
                return;
            }

            console.log('Added:', data);
            window.location.reload();
        } catch (err) {
            console.error('Add entry failed:', err);
        }
    });
}