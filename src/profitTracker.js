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
        addBtn.disabled = true;

        try {
            const res = await fetch('/api/inventory_tracker/add', { method: 'POST' });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                console.error('Add entry failed:', data.error || res.status);
                return;
            }
            window.location.reload();
        } catch (err) {
            console.error('Add entry failed:', err);
        } finally {
            addBtn.disabled = false;
        }
    });
}