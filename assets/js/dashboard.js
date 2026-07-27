document.addEventListener('DOMContentLoaded', function () {

    // DONUT SOLICITUDES
    new Chart(document.getElementById('solicitudesChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'Asignadas', 'Rechazadas'],
            datasets: [{
                data: [
                    datosDashboard.solicitudes.pendientes,
                    datosDashboard.solicitudes.asignadas,
                    datosDashboard.solicitudes.rechazadas
                ],
                backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#334155', padding: 16, usePointStyle: true }
                }
            }
        }
    });

    // BARRAS SESIONES
    new Chart(document.getElementById('sesionesChart'), {
        type: 'bar',
        data: {
            labels: ['Programadas', 'En Curso', 'Completas', 'Finalizadas', 'Canceladas'],
            datasets: [{
                label: 'Sesiones',
                data: [
                    datosDashboard.sesiones.programadas,
                    datosDashboard.sesiones.enCurso,
                    datosDashboard.sesiones.completas,
                    datosDashboard.sesiones.finalizadas,
                    datosDashboard.sesiones.canceladas
                ],
                backgroundColor: [
                    '#3b82f6',
                    '#60a5fa',
                    '#10b981',
                    '#6366f1',
                    '#ef4444'
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: '#e2e8f0' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

});