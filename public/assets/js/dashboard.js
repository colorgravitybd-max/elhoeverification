/* Dashboard charts (Chart.js v4 expected on window.Chart) */
(function () {
    'use strict';
    function init() {
        if (!window.Chart) { setTimeout(init, 100); return; }
        const D = window.DASHBOARD_DATA || {};
        const palette = {
            primary: getCss('--admin-primary') || '#3E5641',
            accent:  getCss('--admin-accent') || '#A4B494',
            danger:  '#A94442',
            muted:   '#6B6B6B'
        };

        // Trend (line chart)
        const t = document.getElementById('chart-trend');
        if (t && D.trendLabels) {
            new Chart(t, {
                type: 'line',
                data: {
                    labels: D.trendLabels,
                    datasets: [
                        { label: 'Successful', data: D.trendOk, borderColor: palette.primary, backgroundColor: hexA(palette.primary, .12), tension: .35, fill: true },
                        { label: 'Failed',     data: D.trendFail, borderColor: palette.danger,  backgroundColor: hexA(palette.danger, .12),  tension: .35, fill: true }
                    ]
                },
                options: chartBase()
            });
        }

        // Hourly (bar)
        const h = document.getElementById('chart-hourly');
        if (h && D.hourly) {
            new Chart(h, {
                type: 'bar',
                data: {
                    labels: Array.from({length:24}, (_,i)=>String(i).padStart(2,'0')+'h'),
                    datasets: [{ label: 'Scans', data: D.hourly, backgroundColor: palette.accent, borderRadius: 4 }]
                },
                options: chartBase()
            });
        }
    }

    function chartBase() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        };
    }
    function hexA(hex, a) {
        const h = hex.replace('#','');
        const r = parseInt(h.slice(0,2),16), g = parseInt(h.slice(2,4),16), b = parseInt(h.slice(4,6),16);
        return `rgba(${r},${g},${b},${a})`;
    }
    function getCss(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
