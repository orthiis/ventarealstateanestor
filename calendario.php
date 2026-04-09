<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('calendar.title', [], 'Calendario');
$currentUser = getCurrentUser();

// Obtener usuarios para filtros
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name 
     FROM users 
     WHERE status = 'active' 
     ORDER BY first_name"
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
    }

    .calendar-container {
        padding: 30px;
        background: #f8f9fa;
    }

    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    .calendar-wrapper {
        background: white;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .calendar-toolbar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-add-event {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-add-event:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }

    /* FullCalendar Customization */
    .fc {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .fc .fc-button-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
    }

    .fc .fc-button-primary:hover {
        background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: linear-gradient(135deg, #4a56c0 0%, #542f7a 100%);
    }

    .fc-event {
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 13px;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .fc-event:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .fc-daygrid-event {
        margin: 2px 0;
    }

    .event-type-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: 5px;
    }

    .legend-container {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        padding: 15px 0;
        border-top: 1px solid #e5e7eb;
        margin-top: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 4px;
    }

    .stats-mini {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-mini-card {
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }

    .stat-mini-number {
        font-size: 24px;
        font-weight: 700;
        margin: 5px 0;
    }

    .stat-mini-label {
        color: #6b7280;
        font-size: 12px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .calendar-container {
            padding: 15px;
        }

        .calendar-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .stats-mini {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css' rel='stylesheet' />

<!-- Main Content -->
<div class="main-content">
    <div class="calendar-container">
        
        <!-- Header -->
        <div class="page-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="page-title-modern">
                    <i class="fas fa-calendar-alt" style="color: #667eea;"></i> 
                    <?php echo __('calendar.title', [], 'Calendario'); ?>
                </h2>
                <p class="page-subtitle-modern">
                    <?php echo __('calendar.subtitle', [], 'Gestiona tus eventos, reuniones y visitas'); ?>
                </p>
            </div>
            <a href="nuevo-evento.php" class="btn-add-event">
                <i class="fas fa-plus"></i> 
                <?php echo __('calendar.new_event', [], 'Nuevo Evento'); ?>
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card" style="border-top: 3px solid #667eea;">
                <div class="stat-mini-label"><?php echo __('calendar.events_today', [], 'Eventos Hoy'); ?></div>
                <div class="stat-mini-number" style="color: #667eea;" id="eventsToday">0</div>
            </div>
            <div class="stat-mini-card" style="border-top: 3px solid #10b981;">
                <div class="stat-mini-label"><?php echo __('calendar.events_this_week', [], 'Esta Semana'); ?></div>
                <div class="stat-mini-number" style="color: #10b981;" id="eventsWeek">0</div>
            </div>
            <div class="stat-mini-card" style="border-top: 3px solid #f59e0b;">
                <div class="stat-mini-label"><?php echo __('calendar.events_this_month', [], 'Este Mes'); ?></div>
                <div class="stat-mini-number" style="color: #f59e0b;" id="eventsMonth">0</div>
            </div>
            <div class="stat-mini-card" style="border-top: 3px solid #ef4444;">
                <div class="stat-mini-label"><?php echo __('calendar.pending_events', [], 'Pendientes'); ?></div>
                <div class="stat-mini-number" style="color: #ef4444;" id="eventsPending">0</div>
            </div>
        </div>

        <!-- Toolbar con Filtros -->
        <div class="calendar-toolbar">
            <label style="font-weight: 600; color: #374151;">
                <i class="fas fa-filter"></i> <?php echo __('calendar.filter_by_type', [], 'Filtrar por tipo'); ?>:
            </label>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="visit" id="filter-visit" checked>
                <label class="form-check-label" for="filter-visit">
                    <span style="color: #10b981;">●</span> <?php echo __('calendar.visits', [], 'Visitas'); ?>
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="meeting" id="filter-meeting" checked>
                <label class="form-check-label" for="filter-meeting">
                    <span style="color: #3b82f6;">●</span> <?php echo __('calendar.meetings', [], 'Reuniones'); ?>
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="call" id="filter-call" checked>
                <label class="form-check-label" for="filter-call">
                    <span style="color: #f59e0b;">●</span> <?php echo __('calendar.calls', [], 'Llamadas'); ?>
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="signing" id="filter-signing" checked>
                <label class="form-check-label" for="filter-signing">
                    <span style="color: #8b5cf6;">●</span> <?php echo __('calendar.signings', [], 'Firmas'); ?>
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="deadline" id="filter-deadline" checked>
                <label class="form-check-label" for="filter-deadline">
                    <span style="color: #ef4444;">●</span> <?php echo __('calendar.deadlines', [], 'Fechas Límite'); ?>
                </label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input event-filter" type="checkbox" value="other" id="filter-other" checked>
                <label class="form-check-label" for="filter-other">
                    <span style="color: #6b7280;">●</span> <?php echo __('calendar.other', [], 'Otros'); ?>
                </label>
            </div>

            <?php if ($currentUser['role']['name'] === 'administrador'): ?>
            <select class="form-select" id="userFilter" style="width: auto; margin-left: auto;">
                <option value=""><?php echo __('all_users', [], 'Todos los usuarios'); ?></option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>

        <!-- Calendario -->
        <div class="calendar-wrapper">
            <div id="calendar"></div>

            <!-- Leyenda -->
            <div class="legend-container">
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span><?php echo __('calendar.visits', [], 'Visitas'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #3b82f6;"></div>
                    <span><?php echo __('calendar.meetings', [], 'Reuniones'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f59e0b;"></div>
                    <span><?php echo __('calendar.calls', [], 'Llamadas'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #8b5cf6;"></div>
                    <span><?php echo __('calendar.signings', [], 'Firmas'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span><?php echo __('calendar.deadlines', [], 'Fechas Límite'); ?></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #6b7280;"></div>
                    <span><?php echo __('calendar.other', [], 'Otros'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    let allEvents = [];
    const currentLang = '<?php echo currentLanguage(); ?>';

    // Colores por tipo de evento
    const eventColors = {
        visit: '#10b981',
        meeting: '#3b82f6',
        call: '#f59e0b',
        signing: '#8b5cf6',
        deadline: '#ef4444',
        other: '#6b7280'
    };

    // Textos según idioma
    const translations = {
        en: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day',
            list: 'List'
        },
        es: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            list: 'Lista'
        }
    };

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: currentLang,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: translations[currentLang],
        height: 'auto',
        navLinks: true,
        selectable: true,
        selectMirror: true,
        select: function(info) {
            // Crear nuevo evento al seleccionar rango de fechas
            const startDate = info.startStr;
            window.location.href = `nuevo-evento.php?start=${startDate}`;
        },
        eventClick: function(info) {
            // Ver detalle del evento
            window.location.href = `ver-evento.php?id=${info.event.id}`;
        },
        eventDrop: function(info) {
            // Actualizar fecha al arrastrar
            updateEventDate(info.event.id, info.event.start, info.event.end);
        },
        eventResize: function(info) {
            // Actualizar duración al redimensionar
            updateEventDate(info.event.id, info.event.start, info.event.end);
        },
        events: function(info, successCallback, failureCallback) {
            // Cargar eventos
            loadEvents(successCallback, failureCallback);
        },
        editable: true,
        dayMaxEvents: true,
        eventDidMount: function(info) {
            // Agregar tooltip
            info.el.title = info.event.title + '\n' + 
                           (info.event.extendedProps.description || '');
        }
    });

    calendar.render();

    // Cargar eventos desde el servidor
    function loadEvents(successCallback, failureCallback) {
        const userFilter = document.getElementById('userFilter')?.value || '';
        
        fetch(`ajax/get-events.php?user=${userFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allEvents = data.events.map(event => ({
                        id: event.id,
                        title: event.title,
                        start: event.start_datetime,
                        end: event.end_datetime,
                        allDay: event.all_day == 1,
                        backgroundColor: event.color || eventColors[event.event_type] || '#3b82f6',
                        borderColor: event.color || eventColors[event.event_type] || '#3b82f6',
                        extendedProps: {
                            description: event.description,
                            event_type: event.event_type,
                            location: event.location,
                            status: event.status
                        }
                    }));
                    
                    filterEvents();
                    updateStats();
                } else {
                    failureCallback(data.message);
                }
            })
            .catch(error => {
                console.error('Error loading events:', error);
                failureCallback(error);
            });
    }

    // Filtrar eventos por tipo
    function filterEvents() {
        const checkedTypes = Array.from(document.querySelectorAll('.event-filter:checked'))
            .map(cb => cb.value);
        
        const filteredEvents = allEvents.filter(event => 
            checkedTypes.includes(event.extendedProps.event_type)
        );
        
        calendar.removeAllEvents();
        calendar.addEventSource(filteredEvents);
    }

    // Actualizar estadísticas
    function updateStats() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const weekEnd = new Date(today);
        weekEnd.setDate(weekEnd.getDate() + 7);
        
        const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        const eventsToday = allEvents.filter(e => {
            const eventDate = new Date(e.start);
            eventDate.setHours(0, 0, 0, 0);
            return eventDate.getTime() === today.getTime();
        }).length;

        const eventsWeek = allEvents.filter(e => {
            const eventDate = new Date(e.start);
            return eventDate >= today && eventDate < weekEnd;
        }).length;

        const eventsMonth = allEvents.filter(e => {
            const eventDate = new Date(e.start);
            return eventDate >= today && eventDate <= monthEnd;
        }).length;

        const eventsPending = allEvents.filter(e => 
            e.extendedProps.status === 'scheduled'
        ).length;

        document.getElementById('eventsToday').textContent = eventsToday;
        document.getElementById('eventsWeek').textContent = eventsWeek;
        document.getElementById('eventsMonth').textContent = eventsMonth;
        document.getElementById('eventsPending').textContent = eventsPending;
    }

    // Event listeners para filtros
    document.querySelectorAll('.event-filter').forEach(checkbox => {
        checkbox.addEventListener('change', filterEvents);
    });

    if (document.getElementById('userFilter')) {
        document.getElementById('userFilter').addEventListener('change', function() {
            calendar.refetchEvents();
        });
    }

    // Actualizar fecha de evento (drag & drop)
    function updateEventDate(eventId, start, end) {
        fetch('ajax/calendar-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_date&id=${eventId}&start=${start.toISOString()}&end=${end ? end.toISOString() : ''}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                const errorMsg = currentLang === 'es' ? 'Error al actualizar el evento' : 'Error updating event';
                alert(errorMsg);
                calendar.refetchEvents();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            calendar.refetchEvents();
        });
    }
});
</script>

<?php include 'footer.php'; ?>