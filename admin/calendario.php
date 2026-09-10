<?php
date_default_timezone_set('America/La_Paz');

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Consultorio.php';
require_once '../src/models/Event.php';
require_once '../src/models/Doctor.php';
require_once '../src/models/Client.php';

$clientModel = new Client($pdo);
$clientes = $clientModel->getAll();

$consultorioModel = new Consultorio($pdo);
$consultorios = $consultorioModel->getAll();

$doctorModel = new Doctor($pdo);
$doctores = $doctorModel->getActivos();

?>
<?php require_once '../templates/header_general.php'; ?>

<!-- jQuery & Select2 Autocomplete -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Bootstrap 5 CSS y JS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dependencias del calendario -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales/es.js"></script>

<style>
    :root {
        --primary-color: #6B1D49;
        --secondary-color: #C47D9F;
    }

    body {
        background-color: #FDF8FA;
    }

    /* Header del calendario más compacto */
    .calendario-header {
        background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(107, 29, 73, 0.2);
    }

    .calendario-header h2 {
        color: white;
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .calendario-header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.85rem;
        margin: 5px 0 0 0;
    }

    /* Calendario más grande y optimizado */
    .calendar-container {
        background: white;
        border-radius: 14px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        width: 100% !important;
        max-width: 100% !important;
        padding: 18px !important;
        box-sizing: border-box !important;
        overflow: hidden;
    }

    #calendar {
        padding: 0;
        min-height: 720px;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Asegurar que la tabla y columnas aprovechen el 100% sin recortar el extremo derecho */
    .fc .fc-scrollgrid {
        border-radius: 8px;
        width: 100% !important;
        table-layout: fixed !important;
    }

    .fc .fc-scrollgrid-sync-table,
    .fc .fc-daygrid-body,
    .fc .fc-col-header {
        width: 100% !important;
    }

    /* Alineación y margen de seguridad para los números de fecha (evita el recorte en la columna Domingo) */
    .fc .fc-daygrid-day-top {
        display: flex !important;
        flex-direction: row-reverse !important;
        padding: 4px 12px 2px 4px !important;
    }

    .fc .fc-daygrid-day-number {
        font-size: 0.95rem;
        font-weight: 700;
        padding: 2px 6px !important;
        color: #495057;
        margin-right: 6px !important;
    }

    .fc .fc-day-today {
        background-color: #fff3e0 !important;
    }

    .fc .fc-day-today .fc-daygrid-day-number {
        background: var(--primary-color);
        color: white;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Encabezados de días del calendario (evitar color azul por defecto de enlaces) */
    .fc .fc-col-header-cell-cushion,
    .fc .fc-col-header-cell a,
    .fc th a,
    .fc-col-header-cell-cushion,
    .fc-col-header-cell a {
        color: var(--primary-color, #6B1D49) !important;
        font-weight: 700 !important;
        text-decoration: none !important;
        text-transform: capitalize !important;
    }

    .fc .fc-daygrid-day-number,
    .fc-daygrid-day-number {
        color: #495057 !important;
        text-decoration: none !important;
    }

    .fc .fc-more-link {
        color: var(--primary-color, #6B1D49) !important;
        font-weight: 600 !important;
    }

    /* Modal mejorado */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    .modal-header {
        background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 20px;
        border-bottom: none;
    }

    .modal-title {
        font-weight: 600;
        font-size: 1.25rem;
    }

    .modal-body {
        padding: 25px;
    }

    .list-group-item {
        border: none;
        border-bottom: 1px solid #e9ecef;
        padding: 12px 0;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .badge {
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    /* Botones de acción más grandes */
    .btn {
        padding: 10px 20px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-group .btn {
        padding: 8px 16px;
    }

    /* Responsive mejorado */
    @media (max-width: 768px) {
        .container-calendario {
            padding: 8px;
        }

        .calendario-header {
            padding: 12px;
            border-radius: 12px;
        }

        .calendario-header h2 {
            font-size: 1.1rem;
        }

        .calendario-header p {
            font-size: 0.75rem;
        }

        /* Calendario ocupa todo el ancho */
        #calendar {
            padding: 5px;
            min-height: 500px;
        }

        /* Toolbar del calendario más compacto */
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 8px;
            padding: 10px 0;
        }

        .fc .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .fc .fc-toolbar-title {
            font-size: 1.2rem;
            text-align: center;
            width: 100%;
        }

        /* Botones más grandes y táctiles */
        .fc .fc-button {
            padding: 10px 14px;
            font-size: 0.85rem;
            min-width: 44px;
            min-height: 44px;
        }

        .fc .fc-button-group {
            gap: 5px;
        }

        /* Vista del día de la semana */
        .fc .fc-col-header-cell {
            font-size: 0.75rem;
            padding: 8px 2px;
        }

        /* Números de día más grandes */
        .fc .fc-daygrid-day-number {
            font-size: 1.1rem;
            padding: 10px;
        }

        /* Eventos más legibles */
        .fc-event {
            font-size: 0.75rem;
            padding: 5px 4px;
            margin: 1px 0;
        }

        /* Modal a pantalla completa en móviles */
        .modal-dialog {
            margin: 0;
            max-width: 100%;
            height: 100%;
        }

        .modal-content {
            height: 100%;
            border-radius: 0;
        }

        .modal-header {
            border-radius: 0;
        }

        .modal-body {
            padding: 15px;
            overflow-y: auto;
        }

        .modal-footer {
            flex-wrap: wrap;
            gap: 8px;
            padding: 15px;
        }

        .modal-footer .btn {
            flex: 1 1 45%;
            min-width: 120px;
        }

        /* Botones de estado en columna */
        .btn-group {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 5px;
        }

        .btn-group .btn {
            width: 100%;
            border-radius: 8px !important;
        }

        /* Ajuste del contenedor para evitar que el header fijo cubra contenido */
        body.has-fixed-header {
            padding-top: 70px;
        }
    }

    @media (max-width: 576px) {
        .fc .fc-daygrid-day-frame {
            min-height: 80px;
        }

        .fc .fc-toolbar-title {
            font-size: 1rem;
        }

        .fc .fc-button {
            font-size: 0.75rem;
            padding: 8px 10px;
        }
    }

    /* Mejoras de accesibilidad táctil */
    @media (hover: none) and (pointer: coarse) {
        .fc-event,
        .btn,
        .fc .fc-button {
            min-height: 44px;
            min-width: 44px;
        }
    }
    /* Mejoras para eventos cortos en el cronograma */
    .fc-timegrid-event {
        overflow: visible !important;
        min-height: 40px !important;
    }

    .fc-timegrid-event .fc-event-main {
        padding: 3px 5px !important;
        overflow: visible !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
    }

    .fc-timegrid-event .fc-event-main-frame {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow: visible !important;
        line-height: 1.2 !important;
        height: 100% !important;
    }

    /* Estilo para eventos muy cortos (15-30 min) */
    .fc-timegrid-event-short {
        min-height: 45px !important;
    }

    .fc-timegrid-event-short .fc-event-title {
        font-size: 0.8rem !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .fc-timegrid-event-short .fc-event-time {
        font-size: 0.7rem !important;
        display: block !important;
        white-space: nowrap !important;
    }

    /* Ajustar altura de slots para mejor visualización */
    .fc-timegrid-slot {
        height: 2.5em !important;
    }

    /* Estilos para Select2 en modales */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px !important;
        min-height: 38px !important;
        border-color: #ced4da !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #212529 !important;
    }
    .select2-dropdown {
        z-index: 1065 !important;
    }
</style>

<div class="container-calendario">
    <!-- Header -->
    <div class="calendario-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2>
                    <i class="fas fa-calendar-alt"></i>
                    Calendario y Cronograma
                </h2>
                <p>Visualiza todas tus citas programadas</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="dashboard.php" class="btn btn-light btn-sm" style="border-radius: 8px;">
                    <i class="fas fa-home me-1"></i>Inicio
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show my-3 shadow-sm" role="alert" style="border-radius: 10px;">
            <i class="fas <?php echo ($_SESSION['message_type'] ?? 'info') === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (isset($_GET['mensaje'])): ?>
        <div class="alert alert-success alert-dismissible fade show my-3 shadow-sm" role="alert" style="border-radius: 10px;">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Calendario -->
    <div class="calendar-container">
        <div id="calendar"></div>
    </div>

    <!-- Modal para cronogramas -->
<div class="modal fade" id="cronogramaModal" tabindex="-1" aria-labelledby="cronogramaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white;">
                <div>
                    <h5 class="modal-title" id="cronogramaModalLabel">
                        <i class="fas fa-calendar-week me-2"></i>
                        Cronogramas por Consultorio
                    </h5>
                    <p class="mb-0" id="cronograma-titulo" style="font-size: 0.9rem;"></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cronogramas" class="row g-3">
                    <!-- Los calendarios se generarán dinámicamente aquí -->
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Añadir clase al body si hay header fijo
    if (document.querySelector('header.fixed-top') || document.querySelector('nav.fixed-top')) {
        document.body.classList.add('has-fixed-header');
    }

    // Configurar los eventos para limpiar los modales al cerrar
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
        });
    });

    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        height: 'auto',
        contentHeight: window.innerWidth < 768 ? 550 : 750,
        expandRows: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        dayMaxEvents: window.innerWidth < 768 ? 2 : 3,
        moreLinkText: 'más',
        nowIndicator: true,
        navLinks: false,
        editable: false,
        selectable: true,
        selectMirror: true,
        events: 'api_citas_rango.php',
        eventContent: function(arg) {
            var title = arg.event.title || '';
            var start = new Date(arg.event.start);
            var duration = parseInt(arg.event.extendedProps.duration);
            var end = new Date(start.getTime() + (duration * 60000));
            
            var startTime = start.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            var endTime = end.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            
            var est = (arg.event.extendedProps.estado || 'activo').toLowerCase();
            var tipo = arg.event.extendedProps.type || 'cita';
            var icon = tipo === 'evento' ? '📋' : (est === 'confirmado' ? '✓' : (est === 'pospuesto' ? '⏱' : (est === 'cancelado' ? '✕' : '📌')));
            
            var doctorInfo = '';
            if (tipo === 'cita') {
                var doctor = arg.event.extendedProps.doctor_nombre || 'Sin asignar';
                doctorInfo = '<div style="font-size: 0.8em; color: #666; margin-top: 2px;">👨‍⚕️ ' + doctor + '</div>';
            }
            
            return {
                html: `<div style=" line-height: 1.3; overflow: auto;">
                        <div style="font-weight: bold;">${icon} ${title}</div>
                        <div style="font-size: 0.85em; margin-top: 2px;">${startTime} - ${endTime}</div>
                        ${doctorInfo}
                      </div>`
            };
        },
        eventDidMount: function(info) {
            var color = info.event.extendedProps.consultorio_color || info.event.backgroundColor;
            if (color) {
                info.el.style.backgroundColor = color;
                info.el.style.borderColor = color;
                info.el.style.color = '#000000';
                
                var eventMain = info.el.querySelector('.fc-event-main');
                if (eventMain) {
                    eventMain.style.backgroundColor = color;
                    eventMain.style.borderColor = color;
                }
            }
            
            var est = (info.event.extendedProps.estado || 'activo').toLowerCase();
            if (est === 'cancelado') {
                info.el.style.textDecoration = 'line-through';
                info.el.style.opacity = '0.7';
            } else if (est === 'pospuesto') {
                info.el.style.borderStyle = 'dashed';
            } else if (est === 'confirmado') {
                info.el.style.boxShadow = '0 0 0 2px rgba(40,167,69,0.3)';
            }
        },
        eventClick: function(info) {
            var event = info.event;
            var tipo = event.extendedProps.type || 'cita';
            
            var modalTitle = document.getElementById('modal-title');
            if (modalTitle) {
                modalTitle.innerHTML = '<i class="fas ' + (tipo === 'evento' ? 'fa-calendar-day' : 'fa-user-circle') + ' me-2"></i>' + 
                    (tipo === 'evento' ? 'Evento: ' : 'Cliente: ') + event.title;
            }
            
            var deleteButton = document.getElementById('delete-button');
            if (deleteButton) {
                deleteButton.setAttribute('data-id', event.id);
                deleteButton.setAttribute('data-type', tipo);
                deleteButton.setAttribute('data-real-id', (event.extendedProps && event.extendedProps.real_id) ? event.extendedProps.real_id : '');
            }

            if (typeof enhanceModalForEvent === 'function') {
                enhanceModalForEvent(event);
            }

            var eventModalEl = document.getElementById('eventModal');
            if (eventModalEl) {
                var eventModal = bootstrap.Modal.getInstance(eventModalEl) || new bootstrap.Modal(eventModalEl);
                eventModal.show();
            }
        },
        dateClick: function(info) {
            var selectedDate = info.dateStr;
            document.getElementById('cronograma-titulo').innerText = 'Cronograma para el día: ' + selectedDate;
            
            // Limpiar contenedor primero
            document.getElementById('cronogramas').innerHTML = '';
            
            // Crear elementos del DOM para cada consultorio
            <?php foreach ($consultorios as $consultorio): ?>
            var consultorioDiv<?php echo $consultorio['id']; ?> = document.createElement('div');
            consultorioDiv<?php echo $consultorio['id']; ?>.className = 'col-md-6 col-lg-4 mb-3';
            consultorioDiv<?php echo $consultorio['id']; ?>.innerHTML = `
                <div class="card shadow-sm" style="border-left: 4px solid <?php echo htmlspecialchars($consultorio['color'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="card-header" style="background-color: <?php echo htmlspecialchars($consultorio['color'], ENT_QUOTES, 'UTF-8'); ?>; color: white;">
                        <h5 class="mb-0"><?php echo htmlspecialchars($consultorio['nombre'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    </div>
                    <div class="card-body p-2">
                        <div id="calendar-<?php echo $consultorio['id']; ?>" style="min-height: 400px;"></div>
                    </div>
                </div>
            `;
            document.getElementById('cronogramas').appendChild(consultorioDiv<?php echo $consultorio['id']; ?>);
            <?php endforeach; ?>
            
            // Mostrar el modal y renderizar calendarios DESPUÉS
            var cronogramaModal = new bootstrap.Modal(document.getElementById('cronogramaModal'));
            
            // Evento que se dispara DESPUÉS de que el modal está completamente visible
            document.getElementById('cronogramaModal').addEventListener('shown.bs.modal', function renderCalendars() {
                <?php foreach ($consultorios as $consultorio): ?>
                var calendarEl<?php echo $consultorio['id']; ?> = document.getElementById('calendar-<?php echo $consultorio['id']; ?>');
                if (calendarEl<?php echo $consultorio['id']; ?> && !calendarEl<?php echo $consultorio['id']; ?>.classList.contains('fc')) {
                    var consultorioCalendar<?php echo $consultorio['id']; ?> = new FullCalendar.Calendar(calendarEl<?php echo $consultorio['id']; ?>, {
                        initialView: 'timeGridDay',
                        initialDate: selectedDate,
                        locale: 'es',
                        allDaySlot: false,
                        slotMinTime: '08:00:00',
                        slotMaxTime: '21:00:00',
                        height: 'auto',
                        headerToolbar: false,
                        slotDuration: '00:15:00',
                        slotLabelInterval: '01:00:00',
                        slotLabelFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        },
                        events: 'api_citas_rango.php?consultorio_id=<?php echo $consultorio['id']; ?>',
                        eventContent: function(arg) {
                            var title = arg.event.title || '';
                            var startTime = arg.event.start.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                            var endTime = arg.event.end ? arg.event.end.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : '';
                            var est = (arg.event.extendedProps.estado || 'activo').toLowerCase();
                            var tipo = arg.event.extendedProps.type || 'cita';
                            var icon = tipo === 'evento' ? '📋' : (est === 'confirmado' ? '✔' : (est === 'pospuesto' ? '⌛' : (est === 'cancelado' ? '❌' : '📌')));
                            
                            var doctorInfo = '';

                            if (tipo === 'cita') {
                                var doctor = arg.event.extendedProps.doctor_nombre || 'Sin asignar';
                                doctorInfo = '👨‍⚕️ ' +doctor;
                            }
                            
                            return {
                                html: `<div class="fc-event-main-frame" style="overflow: hidden; color: #000000;">
                                        <div style="font-weight: 700; font-size: 0.9em; line-height: 1.2; margin-bottom: 2px;">
                                            ${icon} ${title} 
                                        </div>
                                        <div style="font-size: 0.75em; opacity: 0.95; line-height: 1;">
                                            ${startTime} - ${endTime} | ${doctorInfo}
                                        </div>
                                    </div>`
                            };
                        },
                        eventDidMount: function(info) {
                            info.el.style.fontSize = '0.85em';
                            info.el.style.padding = '4px';
                            info.el.style.fontWeight = '500';
                            info.el.style.minHeight = '35px';
                            info.el.style.overflow = 'visible';
                            
                            var est = (info.event.extendedProps.estado || 'activo').toLowerCase();
                            if (est === 'cancelado') {
                                info.el.style.textDecoration = 'line-through';
                                info.el.style.opacity = '0.7';
                            } else if (est === 'pospuesto') {
                                info.el.style.borderStyle = 'dashed';
                                info.el.style.borderWidth = '2px';
                            } else if (est === 'confirmado') {
                                info.el.style.boxShadow = '0 0 0 2px rgba(40,167,69,0.5)';
                                info.el.style.borderWidth = '2px';
                            }
                            
                            var duration = (info.event.end - info.event.start) / 60000;
                            if (duration <= 30) {
                                info.el.style.minHeight = '40px';
                            }
                        },
                        eventClick: function(info) {
                            var event = info.event;
                            var tipo = event.extendedProps.type || 'cita';
                            
                            var modalTitle = document.getElementById('modal-title');
                            if (modalTitle) {
                                modalTitle.innerHTML = '<i class="fas ' + (tipo === 'evento' ? 'fa-calendar-day' : 'fa-user-circle') + ' me-2"></i>' + 
                                    (tipo === 'evento' ? 'Evento: ' : 'Cliente: ') + event.title;
                            }

                            if (typeof enhanceModalForEvent === 'function') {
                                enhanceModalForEvent(event);
                            }
                            
                            var delBtn = document.getElementById('delete-button');
                            if (delBtn) {
                                delBtn.setAttribute('data-id', event.id);
                                delBtn.setAttribute('data-type', tipo);
                                delBtn.setAttribute('data-real-id', (event.extendedProps && event.extendedProps.real_id) ? event.extendedProps.real_id : '');
                            }
                            
                            var eventModalEl = document.getElementById('eventModal');
                            if (eventModalEl) {
                                var eventModal = bootstrap.Modal.getInstance(eventModalEl) || new bootstrap.Modal(eventModalEl);
                                eventModal.show();
                            }
                        },
                        dateClick: function(info) {
                            var selectedDateTime = info.dateStr;
                            var consultorioId = <?php echo $consultorio['id']; ?>;
                            showRegistroModal(selectedDateTime, consultorioId);
                        }
                    });
                    consultorioCalendar<?php echo $consultorio['id']; ?>.render();
                }
                <?php endforeach; ?>
                
                document.getElementById('cronogramaModal').removeEventListener('shown.bs.modal', renderCalendars);
            }, { once: true });
            
            cronogramaModal.show();
        }
    });

    calendar.render();

    // Variables globales para el modal de registro
    var currentSelectedDate = '';
    var currentConsultorioId = 0;

    // Función para mostrar modal de registro
    function showRegistroModal(selectedDateTime, consultorioId) {
        currentSelectedDate = selectedDateTime;
        currentConsultorioId = consultorioId;
        
        var registroModal = new bootstrap.Modal(document.getElementById('registroTypeModal'));
        registroModal.show();
    }

    // Event listeners para las opciones del modal
    document.getElementById('opcion-cita-card').addEventListener('click', function() {
        var registroTypeEl = document.getElementById('registroTypeModal');
        var registroModalObj = bootstrap.Modal.getInstance(registroTypeEl);
        if (registroModalObj) registroModalObj.hide();

        var alertBox = document.getElementById('nueva_cita_error_alert');
        if (alertBox) alertBox.classList.add('d-none');

        // Parsear fecha y hora seleccionada
        var dt = currentSelectedDate || '';
        var fDate = '';
        var fTime = '09:00';
        if (dt) {
            if (dt.includes('T')) {
                var parts = dt.split('T');
                fDate = parts[0];
                fTime = parts[1].substring(0, 5);
            } else if (dt.includes(' ')) {
                var parts = dt.split(' ');
                fDate = parts[0];
                fTime = parts[1].substring(0, 5);
            } else {
                fDate = dt;
            }
        } else {
            fDate = new Date().toISOString().split('T')[0];
        }

        var fecEl = document.getElementById('nueva_fecha');
        if (fecEl) fecEl.value = fDate;
        var fecDisp = document.getElementById('nueva_fecha_display');
        if (fecDisp) fecDisp.value = fDate;

        var horEl = document.getElementById('nueva_hora');
        if (horEl) horEl.value = fTime;
        var horDisp = document.getElementById('nueva_hora_display');
        if (horDisp) horDisp.value = fTime;

        var consVal = currentConsultorioId || '1';
        setSelectValue(document.getElementById('nueva_consultorio_id'), consVal);
        setSelectValue(document.getElementById('nueva_consultorio_id_display'), consVal);

        var nuevaModal = new bootstrap.Modal(document.getElementById('modalNuevaCita'));
        nuevaModal.show();
    });

    // Interceptar envío de nueva cita AJAX
    var formNueva = document.getElementById('formNuevaCita');
    if (formNueva) {
        formNueva.addEventListener('submit', function(e) {
            e.preventDefault();
            var alertBox = document.getElementById('nueva_cita_error_alert');
            if (alertBox) alertBox.classList.add('d-none');

            var formData = new FormData(this);
            fetch('registrar_cita.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    location.reload();
                } else {
                    if (alertBox) {
                        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (data.message || 'Error al registrar la cita.');
                        alertBox.classList.remove('d-none');
                    } else {
                        alert(data.message || 'Error al registrar la cita.');
                    }
                }
            })
            .catch(function() {
                if (alertBox) {
                    alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión. Intente nuevamente.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }

    // Inicializar Select2 con buscador autocomplete para pacientes
    $('#modalNuevaCita').on('shown.bs.modal', function () {
        $('#nueva_cliente_id').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalNuevaCita'),
            placeholder: '-- Buscar Paciente / Cliente --',
            allowClear: true,
            width: '100%'
        });
    });

    document.getElementById('opcion-evento-card').addEventListener('click', function() {
        var registroTypeEl = document.getElementById('registroTypeModal');
        var registroModalObj = bootstrap.Modal.getInstance(registroTypeEl);
        if (registroModalObj) registroModalObj.hide();

        var alertBox = document.getElementById('nuevo_evento_error_alert');
        if (alertBox) alertBox.classList.add('d-none');

        // Parsear fecha y hora seleccionada
        var dt = currentSelectedDate || '';
        var fDate = '';
        var fTime = '09:00';
        if (dt) {
            if (dt.includes('T')) {
                var parts = dt.split('T');
                fDate = parts[0];
                fTime = parts[1].substring(0, 5);
            } else if (dt.includes(' ')) {
                var parts = dt.split(' ');
                fDate = parts[0];
                fTime = parts[1].substring(0, 5);
            } else {
                fDate = dt;
            }
        } else {
            fDate = new Date().toISOString().split('T')[0];
        }

        var fecEl = document.getElementById('nuevo_evento_fecha');
        if (fecEl) fecEl.value = fDate;
        var fecDisp = document.getElementById('nuevo_evento_fecha_display');
        if (fecDisp) fecDisp.value = fDate;

        var horEl = document.getElementById('nuevo_evento_hora');
        if (horEl) horEl.value = fTime;
        var horDisp = document.getElementById('nuevo_evento_hora_display');
        if (horDisp) horDisp.value = fTime;

        var consVal = currentConsultorioId || '1';
        setSelectValue(document.getElementById('nuevo_evento_consultorio_id'), consVal);
        setSelectValue(document.getElementById('nuevo_evento_consultorio_id_display'), consVal);

        var nuevoEventoModal = new bootstrap.Modal(document.getElementById('modalNuevoEvento'));
        nuevoEventoModal.show();
    });

    // Interceptar envío de nuevo evento AJAX
    var formNuevoEvento = document.getElementById('formNuevoEvento');
    if (formNuevoEvento) {
        formNuevoEvento.addEventListener('submit', function(e) {
            e.preventDefault();
            var alertBox = document.getElementById('nuevo_evento_error_alert');
            if (alertBox) alertBox.classList.add('d-none');

            var formData = new FormData(this);
            fetch('registrar_evento.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    location.reload();
                } else {
                    if (alertBox) {
                        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (data.message || 'Error al registrar el evento.');
                        alertBox.classList.remove('d-none');
                    } else {
                        alert(data.message || 'Error al registrar el evento.');
                    }
                }
            })
            .catch(function() {
                if (alertBox) {
                    alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión. Intente nuevamente.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }

    // Redimensionar calendario en cambio de orientación
    window.addEventListener('resize', function() {
        calendar.updateSize();
    });

    // Event listeners para botones de eliminar y estado
    document.getElementById('delete-button').addEventListener('click', function() {
        var id = this.getAttribute('data-id') || '';
        var typeAttr = this.getAttribute('data-type') || '';
        var realIdAttr = this.getAttribute('data-real-id') || '';
        var esEvento = id.toString().startsWith('evento_') || id.toString().startsWith('ev_') || typeAttr === 'evento';
        
        if (esEvento) {
            // Eliminar evento
            var eventoId = realIdAttr || id.toString().replace('evento_', '').replace('ev_', '');
            console.log('Eliminando evento con ID:', eventoId);
            if (confirm('¿Estás seguro de que deseas eliminar este evento?')) {
                fetch('delete_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ evento_id: eventoId, id: eventoId })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        window.location.href = 'calendario.php?mensaje=' + encodeURIComponent('Evento eliminado correctamente');
                    } else {
                        alert(data.message || 'Error al eliminar el evento.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el evento: ' + error.message);
                });
            }
        } else {
            // Eliminar cita
            if (confirm('¿Estás seguro de que deseas eliminar esta cita?')) {
                fetch('delete_appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'calendario.php?mensaje=' + encodeURIComponent('Cita eliminada correctamente');
                    } else {
                        alert(data.message || 'Error al eliminar la cita.');
                    }
                });
            }
        }
    });

    function actualizarEstadoCita(id, estado) {
        return fetch('update_estado_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, estado: estado })
        }).then(r => r.json());
    }

    var btnConfirmar = document.getElementById('btn-confirmar-estado');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function() {
            var citaId = this.getAttribute('data-id');
            actualizarEstadoCita(citaId, 'confirmado').then(function(resp) {
                if (resp && resp.success) { location.reload(); }
                else { alert((resp && resp.message) || 'No se pudo actualizar el estado'); }
            });
        });
    }

    var btnActualizar = document.getElementById('btn-actualizar-estado');
    if (btnActualizar) {
        btnActualizar.addEventListener('click', function() {
            var citaId = this.getAttribute('data-id');
            var selectEstado = document.getElementById('estado-select');
            var nuevoEstado = selectEstado ? selectEstado.value : 'activo';
            actualizarEstadoCita(citaId, nuevoEstado).then(function(resp) {
                if (resp && resp.success) { location.reload(); }
                else { alert((resp && resp.message) || 'No se pudo actualizar el estado'); }
            });
        });
    }

    var formEditCal = document.getElementById('formEditCitaCal');
    if (formEditCal) {
        formEditCal.addEventListener('submit', function(e) {
            e.preventDefault();
            var alertBox = document.getElementById('edit_cal_error_alert');
            if (alertBox) alertBox.classList.add('d-none');

            var formData = new FormData(this);
            fetch('update_appointment.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    location.reload();
                } else {
                    if (alertBox) {
                        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (data.message || 'Error al guardar los cambios.');
                        alertBox.classList.remove('d-none');
                    } else {
                        alert(data.message || 'Error al guardar los cambios.');
                    }
                }
            })
            .catch(function() {
                if (alertBox) {
                    alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión. Intente nuevamente.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }
});

function setSelectValue(selectEl, targetVal) {
    if (!selectEl) return;
    var valStr = String(targetVal);
    if (!selectEl.options) {
        selectEl.value = valStr;
        return;
    }
    for (var i = 0; i < selectEl.options.length; i++) {
        if (String(selectEl.options[i].value) === valStr) {
            selectEl.options[i].selected = true;
            selectEl.selectedIndex = i;
            break;
        }
    }
    selectEl.value = valStr;
    selectEl.dispatchEvent(new Event('change', { bubbles: true }));
}

// Función enhanceModalForEvent
function enhanceModalForEvent(event) {
    historiaYaCargada = false;
    
    var body = document.querySelector('#eventModal .modal-body');
    if (!body) return;
    
    var tipo = event.extendedProps.type || 'cita';
    var fechaRaw = event.start.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    var fechaTxt = fechaRaw.charAt(0).toUpperCase() + fechaRaw.slice(1);
    
    var horaTxt = event.start.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) + 
                  (event.end ? ' - ' + event.end.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) : '');
    var consultorioTxt = event.extendedProps.consultorio_nombre || 'Sin consultorio';
    var doctorTxt = event.extendedProps.doctor_nombre || 'Sin asignar';
    var estado = (event.extendedProps.estado || 'activo').toLowerCase();
    var descripcionTxt = event.extendedProps.descripcion || 'Sin notas registradas';

    // Panel de historia clínica solo para citas
    var panelHistoria = tipo === 'cita' ? `
        <div class="row mt-3">
            <div class="col-12">
                <div class="card border-0 shadow-sm" style="border-left: 4px solid #6B1D49 !important; border-radius: 12px; overflow: hidden; background: #ffffff;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3" style="cursor: pointer;" onclick="toggleHistoriaPanel()">
                        <span class="fw-bold text-dark"><i class="fas fa-notes-medical me-2" style="color: #6B1D49;"></i>Historia Clínica del Paciente</span>
                        <div>
                            <a href="#" id="btn-historia-completa" class="btn btn-sm btn-outline-primary me-2" style="border-radius: 6px; border-color: #6B1D49; color: #6B1D49;">
                                <i class="fas fa-file-medical me-1"></i>Ver Completa
                            </a>
                            <i class="fas fa-chevron-down text-muted" id="historia-chevron"></i>
                        </div>
                    </div>
                    <div class="card-body" id="historia-panel-body" style="display: none; padding: 15px;">
                        <div id="historia-loading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin me-1" style="color: #6B1D49;"></i> Cargando expedientes...
                        </div>
                        <div id="historia-content" style="display: none;">
                            <div id="historia-alertas"></div>
                            <div id="historia-datos" class="mb-3"></div>
                            <div id="historia-evoluciones"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>` : '';

    body.innerHTML = `
        <div class="container-fluid p-0">
            <div class="row g-3">
                <!-- Columna Izquierda: Información de la cita -->
                <div class="col-md-6">
                    <div class="p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(107, 29, 73, 0.1); color: #6B1D49; flex-shrink: 0;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <span class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Fecha y Horario</span>
                                <strong class="text-dark d-block" style="font-size: 0.92rem;">${fechaTxt}</strong>
                                <span class="badge mt-1" style="background-color: #6B1D49; font-size: 0.78rem; font-weight: 600;">${horaTxt}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(196, 125, 159, 0.15); color: #6B1D49; flex-shrink: 0;">
                                <i class="fas fa-clinic-medical"></i>
                            </div>
                            <div>
                                <span class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Consultorio</span>
                                <strong class="text-dark" style="font-size: 0.92rem;">${consultorioTxt}</strong>
                            </div>
                        </div>

                        ${tipo === 'cita' ? `
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(107, 29, 73, 0.1); color: #6B1D49; flex-shrink: 0;">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div>
                                <span class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Doctor Asignado</span>
                                <strong class="text-dark" style="font-size: 0.92rem;">${doctorTxt}</strong>
                            </div>
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: rgba(108, 117, 125, 0.1); color: #6c757d; flex-shrink: 0;">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <span class="text-muted d-block mb-1" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Estado Actual</span>
                                <span id="modal-estado" class="badge rounded-pill px-3 py-1 ${estado==='confirmado'?'bg-success':estado==='pospuesto'?'bg-warning text-dark':estado==='cancelado'?'bg-danger':'bg-secondary'}" style="font-size: 0.82rem; font-weight: 600;">
                                    <i class="fas ${estado==='confirmado'?'fa-check-circle':estado==='pospuesto'?'fa-clock':estado==='cancelado'?'fa-times-circle':'fa-circle'} me-1"></i>
                                    ${estado.charAt(0).toUpperCase()+estado.slice(1)}
                                </span>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Columna Derecha: Descripción y Notas -->
                <div class="col-md-6">
                    <div class="card h-100 border-0" style="background: #f8fafc; border: 1px solid #e2e8f0 !important; border-radius: 12px;">
                        <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark" style="font-size: 0.88rem;"><i class="fas fa-notes-medical me-2" style="color: #6B1D49;"></i>Notas del Tratamiento</span>
                        </div>
                        <div class="card-body pt-2">
                            <p id="modal-description" class="text-secondary mb-0" style="white-space: pre-line; line-height: 1.5; font-size: 0.92rem; min-height: 80px;"></p>
                        </div>
                    </div>
                </div>
            </div>
            ${panelHistoria}
        </div>`;
    document.getElementById('modal-description').innerText = descripcionTxt;
    
    // Configurar link a historia clínica completa
    if (tipo === 'cita') {
        window.currentCitaId = event.id;
        var btnHistoria = document.getElementById('btn-historia-completa');
        if (btnHistoria) {
            btnHistoria.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                cargarHistoriaClinica(window.currentCitaId, true);
            };
        }
    }

    var footer = document.querySelector('#eventModal .modal-footer');
    if (footer) {
        var botonesEstado = tipo === 'evento' ? '' : `
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <span class="text-muted small me-1 d-none d-sm-inline" style="font-size: 0.78rem; font-weight: 600;">Estado:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-cambiar-estado-modal ${estado==='activo'?'active':''}" data-id="${event.id}" data-estado="activo">Activo</button>
                    <button type="button" class="btn btn-outline-success btn-cambiar-estado-modal ${estado==='confirmado'?'active':''}" data-id="${event.id}" data-estado="confirmado">Confirmar</button>
                    <button type="button" class="btn btn-outline-warning text-dark btn-cambiar-estado-modal ${estado==='pospuesto'?'active':''}" data-id="${event.id}" data-estado="pospuesto">Posponer</button>
                    <button type="button" class="btn btn-outline-danger btn-cambiar-estado-modal ${estado==='cancelado'?'active':''}" data-id="${event.id}" data-estado="cancelado">Cancelar</button>
                </div>
            </div>`;
        
        var botonWhatsApp = tipo === 'evento' ? '' : `
            <a id="whatsapp-button" href="#" target="_blank" class="btn btn-success btn-sm px-3 d-inline-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; background-color: #25D366; border: none;">
                <i class="fab fa-whatsapp fa-lg"></i> WhatsApp
            </a>`;
        
        var botonEditar = tipo === 'evento' ? '' : `
            <button type="button" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1" id="btn-reprogramar-cal" style="background: #6B1D49; border: none; border-radius: 8px; font-weight: 600;">
                <i class="fas fa-edit"></i> Editar / Reprogramar
            </button>`;
        
        footer.innerHTML = `
            <div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-2">
                ${botonesEstado}
                <div class="d-flex align-items-center gap-2 ms-auto">
                    ${botonWhatsApp}
                    ${botonEditar}
                    <button type="button" class="btn btn-outline-danger btn-sm px-3" id="delete-button" data-id="${event.id}" data-type="${tipo}" data-real-id="${event.extendedProps && event.extendedProps.real_id ? event.extendedProps.real_id : ''}" style="border-radius: 8px;">
                        <i class="fas fa-trash-alt me-1"></i>Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal" style="border-radius: 8px;">
                        Cerrar
                    </button>
                </div>
            </div>`;
    }

    // Configurar handler para reprogramar cita desde el calendario
    if (tipo === 'cita') {
        var btnReprog = document.getElementById('btn-reprogramar-cal');
        if (btnReprog) {
            btnReprog.onclick = function() {
                var eventModalEl = document.getElementById('eventModal');
                var eventModalObj = bootstrap.Modal.getInstance(eventModalEl);
                if (eventModalObj) eventModalObj.hide();

                var startDate = event.start;
                var year = startDate.getFullYear();
                var month = String(startDate.getMonth() + 1).padStart(2, '0');
                var day = String(startDate.getDate()).padStart(2, '0');
                var hours = String(startDate.getHours()).padStart(2, '0');
                var minutes = String(startDate.getMinutes()).padStart(2, '0');

                document.getElementById('edit_cita_id_cal').value = event.id;
                document.getElementById('edit_cliente_nombre_cal').value = event.title || '';
                document.getElementById('edit_fecha_cal').value = year + '-' + month + '-' + day;
                document.getElementById('edit_hora_cal').value = hours + ':' + minutes;
                setSelectValue(document.getElementById('edit_duracion_cal'), event.extendedProps.duration || 30);
                setSelectValue(document.getElementById('edit_consultorio_id_cal'), event.extendedProps.consultorio_id || '1');
                setSelectValue(document.getElementById('edit_doctor_id_cal'), event.extendedProps.doctor_id || '0');
                
                document.getElementById('edit_descripcion_cal').value = event.extendedProps.descripcion || '';

                var editModal = new bootstrap.Modal(document.getElementById('editCitaModalCal'));
                editModal.show();
            };
        }
    }

    // NUEVO: Preparar mensaje de WhatsApp personalizado en enhanceModalForEvent (solo para citas)
    if (tipo === 'cita') {
        var telefono = event.extendedProps.telefono;
        var wa = document.getElementById('whatsapp-button');
        if (wa) {
            if (telefono) {
                var nombreCliente = event.title;
                var fechaCita = event.start.toLocaleDateString('es-ES', { 
                    day: '2-digit', 
                    month: '2-digit', 
                    year: 'numeric' 
                });
                var horaCita = event.start.toLocaleTimeString('es-ES', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                var consultorioNombre = event.extendedProps.consultorio_nombre || 'nuestro consultorio';
                
                var mensaje = 'Hola *' + nombreCliente + '* \n\n';
                mensaje += 'Te hablamos del consultorio de la *Dra. Tatiana Ruiz*. Te recordamos que tienes una cita programada:\n\n';
                // Si la cita es hoy, usar "hoy" y si la cita es mañana, usar "mañana"
                if (event.start.toDateString() === new Date().toDateString()) {
                    mensaje += 'Fecha: Hoy\n';
                } else if (event.start.toDateString() === new Date(Date.now() + 86400000).toDateString()) {
                    mensaje += 'Fecha: Mañana\n';
                } else {
                    mensaje += 'Fecha: ' + fechaCita + '\n';
                }
                mensaje += 'Hora: ' + horaCita + '\n';
                mensaje += '¿Me confirmas tu asistencia por favor? \n\n';
                mensaje += 'Si necesitas reprogramar, déjanos saber. ';
                
                var mensajeEncoded = encodeURIComponent(mensaje);
                wa.href = 'https://wa.me/' + telefono + '?text=' + mensajeEncoded;
            } else {
                wa.href = '#';
            }
        }
    }

    function actualizarEstadoCita(id, estado) {
        return fetch('update_estado_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, estado: estado })
        }).then(r => r.json());
    }

    var btnsEstadoModal = document.querySelectorAll('#eventModal .btn-cambiar-estado-modal');
    btnsEstadoModal.forEach(function(btn) {
        btn.onclick = function() {
            var citaId = this.getAttribute('data-id');
            var nuevoEstado = this.getAttribute('data-estado');
            if (!citaId || !nuevoEstado) return;

            var self = this;
            self.disabled = true;

            actualizarEstadoCita(citaId, nuevoEstado).then(function(resp) {
                if (resp && resp.success) {
                    location.reload();
                } else {
                    alert((resp && resp.message) || 'No se pudo actualizar el estado de la cita');
                    self.disabled = false;
                }
            }).catch(function() {
                alert('Error al actualizar el estado de la cita.');
                self.disabled = false;
            });
        };
    });

    var del = document.getElementById('delete-button');
    if (del) {
        del.onclick = function(){
            var id = this.getAttribute('data-id') || '';
            var typeAttr = this.getAttribute('data-type') || '';
            var realIdAttr = this.getAttribute('data-real-id') || '';
            var esEvento = id.toString().startsWith('evento_') || id.toString().startsWith('ev_') || (tipo === 'evento') || typeAttr === 'evento';
            
            if (esEvento) {
                // Eliminar evento
                var eventoId = realIdAttr || id.toString().replace('evento_', '').replace('ev_', '');
                console.log('Eliminando evento con ID:', eventoId);
                if (confirm('¿Estás seguro de que deseas eliminar este evento?')) {
                    fetch('delete_event.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ evento_id: eventoId, id: eventoId })
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            window.location.href = 'calendario.php?mensaje=' + encodeURIComponent('Evento eliminado correctamente');
                        } else {
                            alert(data.message || 'Error al eliminar el evento.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al eliminar el evento: ' + error.message);
                    });
                }
            } else {
                // Eliminar cita
                if (confirm('¿Estás seguro de que deseas eliminar esta cita?')) {
                    fetch('delete_appointment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'calendario.php?mensaje=' + encodeURIComponent('Cita eliminada correctamente');
                        } else {
                            alert(data.message || 'Error al eliminar la cita.');
                        }
                    });
                }
            }
        };
    }

    var ensureDescModal = function(){
        if (!document.getElementById('editDescModal')) {
            document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="editDescModal" tabindex="-1" aria-labelledby="editDescLabel" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="editDescLabel">Editar descripción de la cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <textarea id="edit-desc-text" class="form-control" rows="5" placeholder="Escribe la nueva descripción..."></textarea>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-save-desc" data-id="">Guardar</button>
                  </div>
                </div>
              </div>
            </div>`);
        }
    };
    ensureDescModal();
    
    var btnEdit = document.getElementById('btn-edit-desc');
    if (btnEdit) {
        btnEdit.onclick = function(){
            var txt = document.getElementById('edit-desc-text');
            if (txt) txt.value = descripcionTxt;
            var save = document.getElementById('btn-save-desc');
            if (save) save.setAttribute('data-id', event.id);
            var modal = new bootstrap.Modal(document.getElementById('editDescModal'));
            modal.show();
        };
    }

    function actualizarDescripcionCita(id, descripcion) {
        return fetch('update_descripcion_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, descripcion: descripcion })
        }).then(r => r.json());
    }
    
    var btnSave = document.getElementById('btn-save-desc');
    if (btnSave) {
        btnSave.onclick = function(){
            var id = this.getAttribute('data-id');
            var val = (document.getElementById('edit-desc-text') || {}).value || '';
            actualizarDescripcionCita(id, val).then(function(resp){
                if (resp && resp.success) {
                    document.getElementById('modal-description').innerText = val;
                    var m = bootstrap.Modal.getInstance(document.getElementById('editDescModal'));
                    if (m) m.hide();
                    location.reload();
                } else {
                    alert((resp && resp.message) || 'No se pudo actualizar la descripción');
                }
            });
        };
    }
}

// ========== FUNCIONES DE HISTORIA CLÍNICA ==========
var historiaYaCargada = false;

function toggleHistoriaPanel() {
    var panel = document.getElementById('historia-panel-body');
    var chevron = document.getElementById('historia-chevron');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
        
        // Cargar datos si no se han cargado
        if (!historiaYaCargada && window.currentCitaId) {
            cargarHistoriaClinica(window.currentCitaId, false);
        }
    } else {
        panel.style.display = 'none';
        chevron.classList.remove('fa-chevron-up');
        chevron.classList.add('fa-chevron-down');
    }
}

function cargarHistoriaClinica(citaId, abrirCompleta) {
    var loading = document.getElementById('historia-loading');
    var content = document.getElementById('historia-content');
    
    fetch('api_historia_clinica.php?cita_id=' + citaId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                historiaYaCargada = true;
                
                // Si se pidió abrir completa, redirigir
                if (abrirCompleta) {
                    // Navegar dentro de la PWA sin abrir ventana externa
                    window.location.href = 'historia_clinica.php?id=' + data.cliente_id;
                    return;
                }
                
                // Actualizar link de historia completa
                var btnHistoria = document.getElementById('btn-historia-completa');
                if (btnHistoria) {
                    btnHistoria.href = 'historia_clinica.php?id=' + data.cliente_id;
                }
                
                // Renderizar alertas
                var alertasHtml = '';
                if (data.alertas && data.alertas.length > 0) {
                    data.alertas.forEach(function(a) {
                        var bgColor = a.tipo === 'danger' ? '#f8d7da' : (a.tipo === 'warning' ? '#fff3cd' : '#d1ecf1');
                        var textColor = a.tipo === 'danger' ? '#721c24' : (a.tipo === 'warning' ? '#856404' : '#0c5460');
                        alertasHtml += '<div style="background: ' + bgColor + '; color: ' + textColor + '; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 0.85rem;"><i class="fas fa-exclamation-triangle me-2"></i>' + a.texto + '</div>';
                    });
                }
                document.getElementById('historia-alertas').innerHTML = alertasHtml || '<div class="text-muted small">Sin alertas médicas</div>';
                
                // Renderizar datos básicos
                var datosHtml = '';
                if (data.historia) {
                    var h = data.historia;
                    if (h.grupo_sanguineo) datosHtml += '<span class="badge bg-secondary me-2">Sangre: ' + h.grupo_sanguineo + '</span>';
                    if (h.embarazo == 1) datosHtml += '<span class="badge bg-danger me-2">Embarazada</span>';
                    if (h.sexo) datosHtml += '<span class="badge bg-info me-2">' + (h.sexo === 'M' ? 'Masculino' : (h.sexo === 'F' ? 'Femenino' : h.sexo)) + '</span>';
                } else {
                    datosHtml = '<div class="text-muted small">Sin ficha médica registrada</div>';
                }
                document.getElementById('historia-datos').innerHTML = datosHtml;
                
                // Renderizar últimas evoluciones
                var evoHtml = '';
                if (data.evoluciones && data.evoluciones.length > 0) {
                    evoHtml = '<h6 class="text-muted mb-2" style="font-size: 0.85rem;"><i class="fas fa-history me-1"></i>Últimas ' + data.evoluciones.length + ' atenciones:</h6>';
                    evoHtml += '<div style="max-height: 200px; overflow-y: auto;">';
                    data.evoluciones.forEach(function(e) {
                        var fecha = new Date(e.fecha_atencion).toLocaleDateString('es-ES');
                        evoHtml += '<div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #6B1D49;">';
                        evoHtml += '<div style="font-size: 0.75rem; color: #888;">' + fecha + (e.doctor_nombre ? ' | Dr(a) ' + e.doctor_nombre : '') + '</div>';
                        evoHtml += '<div style="font-weight: 600; font-size: 0.9rem;">' + e.motivo_consulta + '</div>';
                        evoHtml += '<div style="font-size: 0.85rem; color: #555;">' + e.tratamiento_realizado + '</div>';
                        evoHtml += '</div>';
                    });
                    evoHtml += '</div>';
                    if (data.total_evoluciones > 5) {
                        evoHtml += '<div class="text-center mt-2"><a href="historia_clinica.php?id=' + data.cliente_id + '" class="btn btn-sm btn-outline-primary">Ver todas (' + data.total_evoluciones + ')</a></div>';
                    }
                } else {
                    evoHtml = '<div class="text-muted small">Sin evoluciones registradas</div>';
                }
                document.getElementById('historia-evoluciones').innerHTML = evoHtml;
                
                // Mostrar contenido, ocultar loading
                if (loading) loading.style.display = 'none';
                if (content) content.style.display = 'block';
            }
        })
        .catch(function(err) {
            console.error('Error cargando historia:', err);
            if (loading) loading.innerHTML = '<span class="text-danger">Error al cargar</span>';
        });
}
</script>


<!-- Modal para mostrar información de la cita -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 50px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white; padding: 18px 24px; border: none;">
                <h5 class="modal-title" id="modal-title" style="font-weight: 700; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-circle me-1"></i> Información de la Cita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 24px; background: #ffffff;">
                <p><strong>Descripción:</strong> <span id="modal-description"></span></p>
                <p><strong>Fecha:</strong> <span id="modal-date"></span></p>
                <p><strong>Hora:</strong> <span id="modal-time"></span></p>
                <p><strong>Consultorio:</strong> <span id="modal-consultorio"></span></p>
                <p><strong>Estado:</strong> <span id="modal-estado" class="badge bg-secondary">Activo</span></p>
            </div>
            <div class="modal-footer">
                <a id="whatsapp-button" href="#" target="_blank" class="btn btn-success">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <div class="d-flex align-items-center gap-2 me-auto">
                    <select id="estado-select" class="form-select form-select-sm" style="width:auto">
                        <option value="activo">Activo</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="pospuesto">Pospuesto</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-actualizar-estado" data-id="" style="background: #6B1D49; border-color: #6B1D49;">Actualizar estado</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-confirmar-estado" data-id="">Confirmar llegada</button>
                </div>
                <button type="button" class="btn btn-danger" id="delete-button" data-id="">Eliminar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar y reprogramar cita completa (Calendario) -->
<div class="modal fade" id="editCitaModalCal" tabindex="-1" aria-labelledby="editCitaLabelCal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white; padding: 18px 22px;">
                <h5 class="modal-title" id="editCitaLabelCal" style="font-weight: 700; font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit"></i>
                    Editar / Reprogramar Cita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_appointment.php" method="POST" id="formEditCitaCal">
                <div class="modal-body" style="padding: 22px;">
                    <input type="hidden" name="id" id="edit_cita_id_cal">
                    <input type="hidden" name="redirect" value="calendario.php">
                    <div id="edit_cal_error_alert" class="alert alert-danger d-none mb-3" role="alert" style="border-radius: 8px; font-weight: 600; font-size: 0.9rem;"></div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-user me-1" style="color: #6B1D49;"></i> Paciente
                        </label>
                        <input type="text" id="edit_cliente_nombre_cal" class="form-control" readonly style="background-color: #f8f9fa; font-weight: 700; color: #212529;">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_fecha_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-calendar-alt me-1" style="color: #6B1D49;"></i> Fecha de la Cita
                            </label>
                            <input type="date" name="fecha" id="edit_fecha_cal" class="form-control" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_hora_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clock me-1" style="color: #6B1D49;"></i> Hora de Inicio
                            </label>
                            <input type="time" name="hora" id="edit_hora_cal" class="form-control" required style="border-radius: 8px;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_doctor_id_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-user-md me-1" style="color: #6B1D49;"></i> Doctor Asignado
                            </label>
                            <select name="doctor_id" id="edit_doctor_id_cal" class="form-select" style="border-radius: 8px;">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($doctores as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_consultorio_id_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clinic-medical me-1" style="color: #6B1D49;"></i> Consultorio
                            </label>
                            <select name="consultorio_id" id="edit_consultorio_id_cal" class="form-select" required style="border-radius: 8px;">
                                <?php foreach ($consultorios as $cons): ?>
                                    <option value="<?php echo $cons['id']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_duracion_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-stopwatch me-1" style="color: #6B1D49;"></i> Duración Estimada
                        </label>
                        <select name="duracion_estimada" id="edit_duracion_cal" class="form-select" required style="border-radius: 8px;">
                            <option value="15">15 min</option>
                            <option value="30">30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hora</option>
                            <option value="90">1 hora y media</option>
                            <option value="120">2 horas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_descripcion_cal" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-notes-medical me-1" style="color: #6B1D49;"></i> Descripción / Notas del Tratamiento
                        </label>
                        <textarea name="descripcion" id="edit_descripcion_cal" class="form-control" rows="3" placeholder="Detalles de la cita..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8f9fa; padding: 14px 22px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600;">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Emergente para Registrar Cita Nueva -->
<div class="modal fade" id="modalNuevaCita" tabindex="-1" aria-labelledby="modalNuevaCitaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 50px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white; padding: 18px 24px; border: none;">
                <h5 class="modal-title" id="modalNuevaCitaLabel" style="font-weight: 700; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-plus"></i> Registrar Nueva Cita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="registrar_cita.php" method="POST" id="formNuevaCita">
                <div class="modal-body" style="padding: 24px; background: #ffffff;">
                    <div id="nueva_cita_error_alert" class="alert alert-danger d-none mb-3" role="alert" style="border-radius: 8px; font-weight: 600; font-size: 0.9rem;"></div>

                    <!-- Selección de Paciente -->
                    <div class="mb-3">
                        <label for="nueva_cliente_id" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-user me-1" style="color: #6B1D49;"></i> Paciente / Cliente <span class="text-danger">*</span>
                        </label>
                        <select name="cliente_id" id="nueva_cliente_id" class="form-select" required style="border-radius: 8px;">
                            <option value="">-- Seleccionar Paciente --</option>
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?php echo $cli['id']; ?>"><?php echo htmlspecialchars($cli['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campos ocultos para enviar la fecha, hora y consultorio bloqueados -->
                    <input type="hidden" name="fecha" id="nueva_fecha">
                    <input type="hidden" name="hora" id="nueva_hora">
                    <input type="hidden" name="consultorio_id" id="nueva_consultorio_id">

                    <!-- Fecha y Hora Bloqueadas pero Visibles -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-calendar-alt me-1" style="color: #6B1D49;"></i> Fecha
                            </label>
                            <input type="date" id="nueva_fecha_display" class="form-control" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clock me-1" style="color: #6B1D49;"></i> Hora de Inicio
                            </label>
                            <input type="time" id="nueva_hora_display" class="form-control" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                        </div>
                    </div>

                    <!-- Doctor y Consultorio -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nueva_doctor_id" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-user-md me-1" style="color: #6B1D49;"></i> Doctor Asignado
                            </label>
                            <select name="doctor_id" id="nueva_doctor_id" class="form-select" style="border-radius: 8px;">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($doctores as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clinic-medical me-1" style="color: #6B1D49;"></i> Consultorio
                            </label>
                            <select id="nueva_consultorio_id_display" class="form-select" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                                <?php foreach ($consultorios as $cons): ?>
                                    <option value="<?php echo $cons['id']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Duración -->
                    <div class="mb-3">
                        <label for="nueva_duracion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-stopwatch me-1" style="color: #6B1D49;"></i> Duración Estimada
                        </label>
                        <select name="duracion_estimada" id="nueva_duracion" class="form-select" required style="border-radius: 8px;">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hora</option>
                            <option value="90">1 hora y media</option>
                            <option value="120">2 horas</option>
                        </select>
                    </div>

                    <!-- Descripción / Notas -->
                    <div class="mb-3">
                        <label for="nueva_descripcion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-notes-medical me-1" style="color: #6B1D49;"></i> Notas / Tratamiento
                        </label>
                        <textarea name="descripcion" id="nueva_descripcion" class="form-control" rows="3" placeholder="Detalles de la cita o procedimiento..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8f9fa; padding: 14px 22px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-success" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); border: none; border-radius: 8px; padding: 8px 24px; font-weight: 600;">
                        <i class="fas fa-check me-1"></i> Guardar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Emergente para Registrar Evento Nuevo -->
<div class="modal fade" id="modalNuevoEvento" tabindex="-1" aria-labelledby="modalNuevoEventoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 50px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white; padding: 18px 24px; border: none;">
                <h5 class="modal-title" id="modalNuevoEventoLabel" style="font-weight: 700; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-alt"></i> Registrar Nuevo Evento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="registrar_evento.php" method="POST" id="formNuevoEvento">
                <div class="modal-body" style="padding: 24px; background: #ffffff;">
                    <div id="nuevo_evento_error_alert" class="alert alert-danger d-none mb-3" role="alert" style="border-radius: 8px; font-weight: 600; font-size: 0.9rem;"></div>

                    <!-- Nombre del Evento -->
                    <div class="mb-3">
                        <label for="nuevo_evento_nombre" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-tag me-1" style="color: #6B1D49;"></i> Nombre del Evento <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nombre" id="nuevo_evento_nombre" class="form-control" placeholder="Ej: Feriado, Cierre de consultorio, Mantenimiento, Reunión clínica" required style="border-radius: 8px;">
                    </div>

                    <!-- Campos ocultos para enviar la fecha, hora y consultorio bloqueados -->
                    <input type="hidden" name="fecha" id="nuevo_evento_fecha">
                    <input type="hidden" name="hora" id="nuevo_evento_hora">
                    <input type="hidden" name="consultorio_id" id="nuevo_evento_consultorio_id">

                    <!-- Fecha y Hora Bloqueadas pero Visibles -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-calendar-alt me-1" style="color: #6B1D49;"></i> Fecha
                            </label>
                            <input type="date" id="nuevo_evento_fecha_display" class="form-control" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clock me-1" style="color: #6B1D49;"></i> Hora de Inicio
                            </label>
                            <input type="time" id="nuevo_evento_hora_display" class="form-control" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                        </div>
                    </div>

                    <!-- Consultorio y Duración -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clinic-medical me-1" style="color: #6B1D49;"></i> Consultorio
                            </label>
                            <select id="nuevo_evento_consultorio_id_display" class="form-select" disabled style="border-radius: 8px; background-color: #e9ecef; cursor: not-allowed; font-weight: 600;">
                                <?php foreach ($consultorios as $cons): ?>
                                    <option value="<?php echo $cons['id']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nuevo_evento_duracion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-stopwatch me-1" style="color: #6B1D49;"></i> Duración Estimada
                            </label>
                            <select name="duracion_estimada" id="nuevo_evento_duracion" class="form-select" required style="border-radius: 8px;">
                                <option value="15">15 min</option>
                                <option value="30">30 min</option>
                                <option value="45">45 min</option>
                                <option value="60" selected>1 hora</option>
                                <option value="75">1 hora y 15 min</option>
                                <option value="90">1 hora y media</option>
                                <option value="105">1 hora y 45 min</option>
                                <option value="120">2 horas</option>
                                <option value="180">3 horas</option>
                                <option value="240">4 horas</option>
                                <option value="480">8 horas (Día completo)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Descripción / Notas -->
                    <div class="mb-3">
                        <label for="nuevo_evento_descripcion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-align-left me-1" style="color: #6B1D49;"></i> Descripción o Notas (Opcional)
                        </label>
                        <textarea name="descripcion" id="nuevo_evento_descripcion" class="form-control" rows="3" placeholder="Detalles sobre el evento o motivo del bloqueo..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8f9fa; padding: 14px 22px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); border: none; border-radius: 8px; padding: 8px 24px; font-weight: 600;">
                        <i class="fas fa-check me-1"></i> Guardar Evento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para seleccionar tipo de registro (Cita o Evento) -->
<div class="modal fade" id="registroTypeModal" tabindex="-1" aria-labelledby="registroTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%); color: white; border: none;">
                <h5 class="modal-title" id="registroTypeModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>
                    ¿Qué deseas registrar?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <p style="font-size: 1.05rem; color: #666; margin-bottom: 30px;">Selecciona qué tipo de evento deseas crear para esta fecha y consultorio:</p>
                
                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <!-- Opción Cita -->
                    <div style="
                        flex: 1;
                        min-width: 200px;
                        padding: 25px;
                        border-radius: 12px;
                        border: 2px solid #6B1D49;
                        background: linear-gradient(135deg, rgba(107, 29, 73, 0.05) 0%, rgba(196, 125, 159, 0.08) 100%);
                        cursor: pointer;
                        transition: all 0.3s ease;
                    " id="opcion-cita-card">
                        <div style="font-size: 3rem; margin-bottom: 15px; color: #6B1D49;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h6 style="font-weight: 700; color: #333; margin-bottom: 10px;">Registrar Cita</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">Cita con un cliente</p>
                    </div>

                    <!-- Opción Evento -->
                    <div style="
                        flex: 1;
                        min-width: 200px;
                        padding: 25px;
                        border-radius: 12px;
                        border: 2px solid #6c757d;
                        background: linear-gradient(135deg, rgba(108, 117, 125, 0.05) 0%, rgba(52, 58, 64, 0.05) 100%);
                        cursor: pointer;
                        transition: all 0.3s ease;
                    " id="opcion-evento-card">
                        <div style="font-size: 3rem; margin-bottom: 15px; color: #6c757d;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h6 style="font-weight: 700; color: #333; margin-bottom: 10px;">Registrar Evento</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">Evento especial o bloqueo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #opcion-cita-card:hover {
        border-color: #C47D9F;
        box-shadow: 0 8px 24px rgba(107, 29, 73, 0.25);
        transform: translateY(-4px);
    }

    #opcion-evento-card:hover {
        border-color: #495057;
        box-shadow: 0 8px 24px rgba(108, 117, 125, 0.2);
        transform: translateY(-4px);
    }

    /* Animación de entrada del modal */
    .modal.fade .modal-dialog {
        animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Estilos del modal */
    #registroTypeModal .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    #registroTypeModal .modal-header {
        border-radius: 15px 15px 0 0;
        font-weight: 600;
    }

    #registroTypeModal .modal-body {
        border-radius: 0 0 15px 15px;
    }

    /* Efectos en las opciones */
    #opcion-cita-card,
    #opcion-evento-card {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    #opcion-cita-card {
        animation: cardFadeIn 0.5s ease-out 0.1s both;
    }

    #opcion-evento-card {
        animation: cardFadeIn 0.5s ease-out 0.2s both;
    }

    @keyframes cardFadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Pulse suave en hover de las tarjetas */
    #opcion-cita-card:hover > div:first-child,
    #opcion-evento-card:hover > div:first-child {
        animation: iconPulse 0.6s ease-out;
    }

    @keyframes iconPulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.15);
        }
        100% {
            transform: scale(1);
        }
    }
</style>