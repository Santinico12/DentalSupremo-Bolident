<?php
// Validar autenticación e inactividad automáticamente en todas las páginas
require_once __DIR__ . '/../admin/check_auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#003B73">
    <meta name="description" content="Sistema de Gestión Dental - Dentality">
    
    <!-- Anti-caché para iOS Safari (previene páginas estancadas) -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <title>Dentality - Panel Administrativo</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/admin/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Dentality">
    
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" href="/admin/assets/images/logo_Dentality.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/admin/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/admin/assets/icons/icon-192x192.png">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">

    <style>
        :root {
            --primary: #003B73;
            --primary-dark: #062846;
            --accent: #2998EC;
            --accent-hover: #4CB0F9;
            --secondary: #64748B;
            --background: #F4F9FD;
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --shadow: 0 4px 12px rgba(0, 59, 115, 0.08);
            --shadow-hover: 0 6px 18px rgba(0, 59, 115, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body, button, input, select, textarea, .form-control, .form-select, .btn, table, th, td, h1, h2, h3, h4, h5, h6, p, span:not(.fa):not(.fas):not(.far):not(.fab), a:not(.fa):not(.fas):not(.far):not(.fab), label {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Preservar fuente de íconos FontAwesome para evitar que se muestren cuadrados en el menú */
        i, .fa, .fas, .far, .fab, .fal, .fad, .fak, .nav-icon, [class^="fa-"], [class*=" fa-"] {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
        }

        body {
            background-color: var(--background);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Ajuste de margen principal y expansión 100% para aprovechamiento de pantalla */
        @media (min-width: 992px) {
            body {
                margin: 0 !important;
                padding: 0 !important;
                background-color: var(--background);
                overflow-x: hidden;
            }

            .main-container,
            .dashboard-container,
            .page-container,
            .clientes-container,
            .informes-container,
            .caja-container,
            .odonto-container,
            .container-calendario,
            .container,
            .container-fluid,
            main {
                margin-left: var(--sidebar-width) !important;
                margin-right: 0 !important;
                width: calc(100% - var(--sidebar-width)) !important;
                max-width: calc(100% - var(--sidebar-width)) !important;
                padding: 20px 25px !important;
                box-sizing: border-box !important;
            }
        }

        /* Modales nunca deben heredar el margin-left del sidebar */
        .modal .container,
        .modal .container-fluid,
        .modal .main-container,
        .modal .page-container,
        .modal .dashboard-container {
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
        }

        /* Garantizar tablas responsivas sin desbordamiento */
        .table-responsive {
            width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
        }

        table.dataTable {
            width: 100% !important;
        }

        /* Top bar superior para móviles/tablets */
        .mobile-top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 65px;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .menu-toggle-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: var(--white);
            font-size: 1.25rem;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .menu-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--white);
        }

        .mobile-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            padding: 1px;
            object-fit: contain;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .mobile-brand-name {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .mobile-user-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
        }

        /* Backdrop overlay para pantallas táctiles */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(6, 40, 70, 0.55);
            backdrop-filter: blur(3px);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Sidebar Lateral Izquierdo Principal */
        .sidebar-left {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.12);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        /* Transición móvil del Sidebar */
        @media (max-width: 991px) {
            .sidebar-left {
                transform: translateX(-100%);
            }
            .sidebar-left.active {
                transform: translateX(0);
            }
            body {
                padding-top: 80px;
                padding-left: 15px;
                padding-right: 15px;
                padding-bottom: 25px;
            }
        }

        /* Cabecera del Sidebar */
        .sidebar-header {
            padding: 1.5rem 1.25rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            text-decoration: none;
            color: var(--white);
        }

        .brand-logo-container {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.12);
            border: 2px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .sidebar-brand:hover .brand-logo-container {
            transform: scale(1.06);
            border-color: var(--accent);
            box-shadow: 0 0 14px rgba(41, 152, 236, 0.5);
        }

        .brand-logo {
            width: 100%;
            height: 100%;
            border-radius: 11px;
            object-fit: contain;
            display: block;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--white);
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }

        .brand-tagline {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            font-weight: 400;
        }

        .sidebar-close-btn {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.2s ease;
        }

        .sidebar-close-btn:hover {
            color: var(--white);
        }

        /* Navegación del Sidebar */
        .sidebar-nav {
            flex: 1;
            padding: 1.25rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .nav-group-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: rgba(255, 255, 255, 0.45);
            padding: 0.5rem 0.85rem 0.25rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.25s ease;
            position: relative;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
            transition: transform 0.25s ease, color 0.25s ease;
            color: rgba(255, 255, 255, 0.75);
        }

        .nav-item:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(4px);
        }

        .nav-item:hover .nav-icon {
            color: var(--accent-hover);
            transform: scale(1.12);
        }

        .nav-item.active {
            color: var(--white);
            background: linear-gradient(135deg, #003B73 0%, #2998EC 100%);
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(0, 59, 115, 0.35);
        }

        .nav-item.active .nav-icon {
            color: var(--white);
        }

        /* Footer del Sidebar (Perfil de usuario & Logout) */
        .sidebar-footer {
            padding: 1rem 0.85rem 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .user-profile-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 10px;
        }

        .avatar-icon {
            font-size: 1.6rem;
            color: var(--accent-hover);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .logout-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.65rem;
            background: rgba(220, 53, 69, 0.2);
            color: #FF6B6B;
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .logout-link:hover {
            background: #DC3545;
            color: var(--white);
            border-color: #DC3545;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.35);
        }
    </style>
</head>
<body>

    <!-- Mobile Top Bar (Solo visible en pantallas <= 991px) -->
    <div class="mobile-top-bar d-lg-none" id="mobileTopBar">
        <div class="d-flex align-items-center gap-2">
            <button class="menu-toggle-btn" id="menuToggle" aria-label="Abrir Menú">
                <i class="fas fa-bars"></i>
            </button>
            <a href="dashboard.php" class="mobile-brand">
                <img src="assets/images/logo_Dentality.png" alt="Dentality Logo" class="mobile-logo">
                <span class="mobile-brand-name">Dentality</span>
            </a>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="mobile-user-badge">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Backdrop Overlay para cerrar menú en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Navbar Lateral Izquierdo -->
    <aside class="sidebar-left" id="mainSidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <div class="brand-logo-container">
                    <img src="assets/images/logo_Dentality.png" alt="Dentality Logo" class="brand-logo">
                </div>
                <div class="brand-text">
                    <h1 class="brand-name">Dentality</h1>
                    <p class="brand-tagline">Odontología por Especialidades</p>
                </div>
            </a>
            <button class="sidebar-close-btn d-lg-none" id="sidebarClose" aria-label="Cerrar Menú">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-group-label">GESTIÓN PRINCIPAL</div>

            <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home nav-icon"></i>
                <span>Dashboard</span>
            </a>
            <a href="lista_clientes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'lista_clientes.php' ? 'active' : ''; ?>">
                <i class="fas fa-users nav-icon"></i>
                <span>Clientes</span>
            </a>
            <a href="calendario.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'calendario.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt nav-icon"></i>
                <span>Calendario</span>
            </a>
            <a href="registrar_consultorio.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'registrar_consultorio.php' ? 'active' : ''; ?>">
                <i class="fas fa-clinic-medical nav-icon"></i>
                <span>Consultorios</span>
            </a>
            <a href="registrar_doctor.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'registrar_doctor.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-md nav-icon"></i>
                <span>Doctores</span>
            </a>
            <a href="informes.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'informes.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar nav-icon"></i>
                <span>Informes</span>
            </a>
            <a href="tratamientos.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'tratamientos.php' ? 'active' : ''; ?>">
                <i class="fas fa-tooth nav-icon"></i>
                <span>Tratamientos</span>
            </a>
            <div class="nav-group-label mt-3">FINANZAS Y CAJA</div>

            <a href="presupuestos.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['presupuestos.php', 'crear_presupuesto.php', 'ver_presupuesto.php']) ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar nav-icon"></i>
                <span>Presupuestos</span>
            </a>
            <a href="pagos.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pagos.php', 'registrar_pago.php', 'ver_pago.php']) ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave nav-icon"></i>
                <span>Pagos</span>
            </a>
            <a href="caja.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['caja.php', 'registrar_egreso.php', 'registrar_ingreso.php']) ? 'active' : ''; ?>">
                <i class="fas fa-cash-register nav-icon"></i>
                <span>Caja</span>
            </a>

            <div class="nav-group-label mt-3">AUTOMATIZACIÓN E IA</div>

            <a href="whatsapp_agent.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'whatsapp_agent.php' ? 'active' : ''; ?>">
                <i class="fab fa-whatsapp nav-icon" style="color: #25D366;"></i>
                <span>Agente WhatsApp</span>
                <span class="badge ms-auto" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); font-size: 0.65rem; border-radius: 6px; padding: 3px 6px; color: white; font-weight: 800;">IA</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <?php if (isset($_SESSION['user'])): ?>
                <div class="user-profile-info">
                    <i class="fas fa-user-circle avatar-icon"></i>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                        <span class="user-role">Administrador</span>
                    </div>
                </div>
            <?php endif; ?>
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const mainSidebar = document.getElementById('mainSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                mainSidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                mainSidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (menuToggle) menuToggle.addEventListener('click', openSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

            // Cerrar menú al hacer clic en un item en móviles
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 991) {
                        closeSidebar();
                    }
                });
            });
        });

        // Registro de Service Worker para PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' })
                    .then(reg => console.log('PWA ServiceWorker activo:', reg.scope))
                    .catch(err => console.log('PWA ServiceWorker error:', err));
            });
        }

        // ==========================================================================
        // CIERRE AUTOMÁTICO DE SESIÓN POR INACTIVIDAD (15 MINUTOS)
        // ==========================================================================
        (function() {
            const INACTIVITY_LIMIT_MS = 15 * 60 * 1000; // 15 minutos de inactividad
            let idleTimeoutId = null;

            function resetInactivityTimer() {
                if (idleTimeoutId) clearTimeout(idleTimeoutId);
                idleTimeoutId = setTimeout(function() {
                    window.location.href = 'logout.php?timeout=1';
                }, INACTIVITY_LIMIT_MS);
            }

            // Escuchar eventos de interacción del usuario
            const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
            activityEvents.forEach(function(eventName) {
                window.addEventListener(eventName, resetInactivityTimer, { passive: true });
            });

            // Iniciar temporizador
            resetInactivityTimer();
        })();
    </script>
