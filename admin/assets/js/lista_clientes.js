// Verificar si DataTables ya está inicializado
function initializeDataTable() {
    if (!$.fn.DataTable.isDataTable('#clientesTable')) {
        $('#clientesTable').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'copy',
                    text: 'Copiar',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'excel',
                    text: 'Excel',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'pdf',
                    text: 'PDF',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'print',
                    text: 'Imprimir',
                    className: 'btn btn-primary btn-sm'
                }
            ],
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [
                {
                    targets: -1,
                    orderable: false
                }
            ]
        });
    }
}

// Esperar a que el documento esté listo
$(document).ready(function() {
    // Inicializar DataTable
    initializeDataTable();

    // Inicializar todos los tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Manejar la búsqueda
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        var searchTerm = $('#searchInput').val();
        var table = $('#clientesTable').DataTable();
        table.search(searchTerm).draw();
    });
});