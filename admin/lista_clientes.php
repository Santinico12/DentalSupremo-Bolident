<?php
ob_start();

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';

$clientModel = new Client($pdo);

// Estadísticas optimizadas directamente en SQL (Server-Side)
$total_clientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$total_contactos = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE telefono IS NOT NULL AND CHAR_LENGTH(TRIM(telefono)) > 3")->fetchColumn();
$total_historias = (int)$pdo->query("SELECT COUNT(DISTINCT cliente_id) FROM historia_clinica")->fetchColumn();

require_once '../templates/header_general.php';
?>

<!-- Dependencies: Bootstrap 5, FontAwesome 6, DataTables & Extensions -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.0/css/responsive.bootstrap5.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #6B1D49;
        --primary-gradient: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%);
        --primary-dark: #531438;
        --accent-teal: #C47D9F;
        --accent-green: #10B981;
        --accent-purple: #8B5CF6;
        --accent-blue: #0284C7;
        --danger: #EF4444;
        --warning: #F59E0B;
        --bg-main: #FDF8FA;
        --card-bg: #FFFFFF;
        --text-dark: #0F172A;
        --text-muted: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.04);
        --shadow-md: 0 10px 25px rgba(15, 76, 110, 0.08);
        --shadow-lg: 0 20px 40px rgba(15, 76, 110, 0.12);
        --radius-lg: 16px;
        --radius-md: 10px;
        --radius-sm: 8px;
    }

    body {
        background-color: var(--bg-main);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--text-dark);
    }

    .main-container {
        width: 100%;
        margin: 0;
        padding: 10px 20px 30px 20px;
    }

    /* ============================================
       KPI STAT CARDS
       ============================================ */
    .kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .kpi-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 18px 22px;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: rgba(0, 168, 150, 0.3);
    }

    .kpi-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .kpi-icon.navy {
        background: rgba(15, 76, 110, 0.1);
        color: var(--primary);
    }

    .kpi-icon.teal {
        background: rgba(0, 168, 150, 0.1);
        color: var(--accent-teal);
    }

    .kpi-icon.purple {
        background: rgba(139, 92, 246, 0.1);
        color: var(--accent-purple);
    }

    .kpi-content h4 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-dark);
        line-height: 1.2;
    }

    .kpi-content p {
        margin: 2px 0 0 0;
        font-size: 0.82rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    /* ============================================
       HEADER BANNER
       ============================================ */
    .header-card {
        background: var(--primary-gradient);
        border-radius: var(--radius-lg);
        padding: 20px 26px;
        color: white;
        box-shadow: var(--shadow-md);
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        position: relative;
        overflow: hidden;
    }

    .header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
        z-index: 1;
    }

    .header-icon-box {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .header-title-box h2 {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 800;
        letter-spacing: -0.3px;
    }

    .header-title-box p {
        margin: 2px 0 0 0;
        font-size: 0.85rem;
        opacity: 0.9;
        font-weight: 500;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1;
    }

    .btn-add-client {
        background: #FFFFFF;
        color: var(--primary);
        padding: 10px 20px;
        border-radius: var(--radius-md);
        border: none;
        font-weight: 700;
        font-size: 0.88rem;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .btn-add-client:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.18);
        color: var(--accent-teal);
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        color: white;
        padding: 10px 16px;
        border-radius: var(--radius-md);
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-weight: 600;
        font-size: 0.88rem;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    /* ============================================
       TABLE CARD CONTAINER
       ============================================ */
    .table-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 22px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
    }

    .table-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 14px;
    }

    .table-card-header h3 {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-export {
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        font-weight: 700;
        font-size: 0.8rem;
        border: 1px solid var(--border-color);
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        background: #FFFFFF;
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .btn-export.excel { border-color: #10B981; color: #047857; background: #ECFDF5; }
    .btn-export.excel:hover { background: #10B981; color: white; }
    .btn-export.pdf { border-color: #EF4444; color: #B91C1C; background: #FEF2F2; }
    .btn-export.pdf:hover { background: #EF4444; color: white; }
    .btn-export.csv { border-color: #C47D9F; color: #6B1D49; background: #FDF8FA; }
    .btn-export.csv:hover { background: #6B1D49; color: white; }
    .btn-export.print { border-color: #64748B; color: #334155; background: #F8FAFC; }
    .btn-export.print:hover { background: #64748B; color: white; }

    /* ============================================
       DATATABLE STYLES
       ============================================ */
    #clientesTable { width: 100% !important; border-collapse: separate; border-spacing: 0; }

    #clientesTable thead th {
        background: #F1F5F9;
        color: var(--primary);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.76rem;
        letter-spacing: 0.6px;
        padding: 14px 16px;
        border-bottom: 2px solid var(--border-color);
        white-space: nowrap;
    }

    #clientesTable tbody tr {
        transition: all 0.2s ease;
    }

    #clientesTable tbody tr:hover {
        background-color: rgba(0, 168, 150, 0.04) !important;
    }

    #clientesTable tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 0.9rem;
        border-bottom: 1px solid #F1F5F9;
    }

    /* Avatar & Client name */
    .client-name {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .client-avatar {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 12px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.05rem;
        box-shadow: 0 4px 10px rgba(15, 76, 110, 0.2);
    }

    .client-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-dark);
    }

    /* Phone Badge */
    .phone-badge {
        background: #F0FDFA;
        color: #0D9488;
        border: 1px solid #CCFBF1;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }

    /* Action Buttons Bar */
    .action-buttons {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: nowrap;
    }

    .btn-action {
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 10px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        font-size: 0.9rem;
        text-decoration: none;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .btn-edit { background: rgba(15, 76, 110, 0.1); color: var(--primary); }
    .btn-edit:hover { background: var(--primary); color: white; }

    .btn-whatsapp { background: rgba(37, 211, 102, 0.12); color: #16A34A; }
    .btn-whatsapp:hover { background: #25D366; color: white; }

    .btn-historia { background: rgba(0, 168, 150, 0.12); color: var(--accent-teal); }
    .btn-historia:hover { background: var(--accent-teal); color: white; }

    .btn-odontograma { background: rgba(2, 132, 199, 0.12); color: var(--accent-blue); }
    .btn-odontograma:hover { background: var(--accent-blue); color: white; }

    .btn-historial { background: rgba(139, 92, 246, 0.12); color: var(--accent-purple); }
    .btn-historial:hover { background: var(--accent-purple); color: white; }

    .btn-delete { background: rgba(239, 68, 68, 0.12); color: var(--danger); }
    .btn-delete:hover { background: var(--danger); color: white; }

    /* ============================================
       MODAL STYLES
       ============================================ */
    .modal-content {
        border-radius: var(--radius-lg);
        border: none;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: var(--primary-gradient);
        color: white;
        padding: 18px 24px;
        border: none;
    }

    .modal-body { padding: 24px; }

    .form-group { margin-bottom: 18px; }

    .form-group label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.88rem;
    }

    .form-group label i { color: var(--primary); }

    .form-control {
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        font-size: 0.92rem;
        transition: all 0.25s ease;
    }

    .form-control:focus {
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 3px rgba(0, 168, 150, 0.15);
    }

    .btn-save {
        background: var(--primary-gradient);
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 700;
        width: 100%;
        transition: all 0.25s ease;
        font-size: 0.95rem;
    }

    .btn-save:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* ============================================
       MOBILE CARD VIEW & SEARCH
       ============================================ */
    @media (max-width: 991px) {
        .export-buttons .btn-export span.btn-text { display: none; }
        .export-buttons .btn-export { padding: 8px 12px; }
    }

    @media (max-width: 768px) {
        .main-container { padding: 10px; }

        .header-card {
            padding: 16px;
            margin-bottom: 14px;
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .header-left { justify-content: space-between; }
        .header-title-box h2 { font-size: 1.2rem; }

        .header-actions { justify-content: space-between; }

        .table-card { padding: 14px; margin-bottom: 14px; }
        .table-card-header { margin-bottom: 12px; }

        .table-responsive { display: none !important; }
        .mobile-cards { display: flex !important; }

        .export-buttons { width: 100%; }
        .btn-export { flex: 1; justify-content: center; padding: 7px 8px; font-size: 0.74rem; }
    }

    @media (min-width: 769px) {
        .mobile-cards { display: none !important; }
    }

    .mobile-cards {
        flex-direction: column;
        gap: 12px;
        display: none;
    }

    .mobile-search {
        position: relative;
        margin-bottom: 14px;
    }

    .mobile-search input {
        width: 100%;
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 10px 14px 10px 40px;
        font-size: 0.9rem;
        transition: all 0.25s ease;
    }

    .mobile-search input:focus {
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 3px rgba(0, 168, 150, 0.15);
        outline: none;
    }

    .mobile-search i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .mobile-client-card {
        background: white;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--primary);
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.2s ease;
    }

    .mobile-client-card:hover {
        box-shadow: var(--shadow-md);
    }

    .mobile-client-avatar {
        width: 44px;
        height: 44px;
        min-width: 44px;
        border-radius: 12px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.1rem;
    }

    .mobile-client-info {
        flex: 1;
        min-width: 0;
    }

    .mobile-client-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-dark);
        margin: 0 0 4px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mobile-client-phone {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
    }

    .mobile-client-phone i { color: var(--accent-teal); }

    .mobile-client-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .mobile-btn {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.85rem;
        text-decoration: none;
    }

    .mobile-more-btn {
        background: var(--primary);
        color: white;
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.85rem;
    }

    .mobile-dropdown {
        position: absolute;
        right: 0;
        bottom: 100%;
        margin-bottom: 6px;
        background: white;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--border-color);
        z-index: 200;
        overflow: hidden;
        display: none;
        min-width: 180px;
    }

    .mobile-dropdown.show { display: block; }

    .mobile-dropdown a,
    .mobile-dropdown button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        width: 100%;
        border: none;
        background: white;
        color: var(--text-dark);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }

    .mobile-dropdown a:hover,
    .mobile-dropdown button:hover {
        background: var(--bg-main);
    }

    /* ============================================
       HISTORIAL DRAWER
       ============================================ */
    .historial-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1050;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .historial-overlay.open { opacity: 1; visibility: visible; }

    .historial-drawer {
        position: fixed;
        top: 0; right: -450px;
        width: 420px;
        max-width: 92vw;
        height: 100vh;
        background: #F8FAFC;
        z-index: 1051;
        transition: right 0.35s cubic-bezier(0.22, 0.61, 0.36, 1);
        display: flex;
        flex-direction: column;
        box-shadow: -10px 0 30px rgba(15, 23, 42, 0.18);
    }

    .historial-drawer.open { right: 0; }

    .historial-drawer-header {
        background: var(--primary-gradient);
        color: white;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .historial-drawer-header h4 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .historial-drawer-header .close-drawer {
        background: rgba(255,255,255,0.18);
        border: none;
        color: white;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.1rem;
        transition: background 0.2s ease;
    }

    .historial-drawer-header .close-drawer:hover {
        background: rgba(255,255,255,0.3);
    }

    .historial-client-info {
        padding: 16px 20px;
        background: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        flex-shrink: 0;
    }

    .historial-client-avatar {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 14px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(15, 76, 110, 0.15);
    }

    .historial-client-details h5 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--text-dark); }
    .historial-client-details small { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-top: 2px; display: block; }

    .historial-stats-row {
        display: flex;
        gap: 10px;
        padding: 14px 20px;
        background: white;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .historial-stat {
        flex: 1;
        text-align: center;
        padding: 10px 6px;
        background: #F8FAFC;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        border-top: 3px solid var(--primary);
    }

    .historial-stat.stat-total { border-top-color: var(--primary); }
    .historial-stat.stat-confirmadas { border-top-color: var(--accent-green); }
    .historial-stat.stat-canceladas { border-top-color: var(--danger); }

    .historial-stat .stat-num { font-size: 1.25rem; font-weight: 800; color: var(--text-dark); display: block; line-height: 1.1; }
    .historial-stat .stat-label { font-size: 0.68rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; display: block; }

    .historial-filters {
        display: flex;
        gap: 8px;
        padding: 12px 20px;
        background: white;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .historial-tag {
        flex: 1;
        padding: 8px 10px;
        border-radius: 20px;
        border: 1.5px solid var(--border-color);
        background: #F8FAFC;
        color: var(--text-dark);
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .historial-tag:hover {
        background: rgba(15, 76, 110, 0.06);
        border-color: var(--primary);
    }

    .historial-tag.active {
        background: var(--primary-gradient);
        color: white;
        border-color: transparent;
        box-shadow: 0 3px 8px rgba(15, 76, 110, 0.2);
    }

    .historial-tag .tag-count {
        background: rgba(0,0,0,0.08);
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 0.72rem;
    }

    .historial-tag.active .tag-count {
        background: rgba(255,255,255,0.25);
        color: white;
    }

    .historial-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px 20px;
        background: #F8FAFC;
    }

    .historial-item {
        background: white;
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 14px;
        border: 1px solid var(--border-color);
        border-left: 5px solid var(--primary);
        box-shadow: 0 2px 8px rgba(15, 76, 110, 0.04);
        transition: all 0.2s ease;
    }

    .historial-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(15, 76, 110, 0.1);
    }

    .historial-item.estado-confirmado { border-left-color: var(--accent-green); }
    .historial-item.estado-cancelado { border-left-color: var(--danger); }
    .historial-item.estado-pospuesto { border-left-color: var(--warning); }
    .historial-item.estado-activo { border-left-color: var(--accent-teal); }

    .dataTables_wrapper .dataTables_filter input {
        border: 1.5px solid var(--border-color);
        border-radius: 20px;
        padding: 7px 16px;
        font-size: 0.88rem;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 3px rgba(0, 168, 150, 0.15);
        outline: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--primary-gradient) !important;
        border: none !important;
        color: white !important;
        border-radius: 8px !important;
    }
</style>

<div class="main-container">
    <!-- Notifications Alert -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius: 12px; font-weight: 600;">
            <i class="fas fa-<?php echo $_SESSION['message_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Header Banner -->
    <div class="header-card">
        <div class="header-left">
            <div class="header-icon-box">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="header-title-box">
                <h2>Directorio de Pacientes</h2>
                <p>Gestión integral de clientes, historias clínicas y cronograma</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="registrar_cliente.php" class="btn-add-client">
                <i class="fas fa-user-plus"></i> Nuevo Paciente
            </a>
        </div>
    </div>

    <!-- KPI Stats Bar -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-icon navy">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-content">
                <h4><?php echo $total_clientes; ?></h4>
                <p>Total Pacientes Registrados</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon teal">
                <i class="fas fa-phone-alt"></i>
            </div>
            <div class="kpi-content">
                <h4><?php echo $total_clientes; ?></h4>
                <p>Contactos Verificados</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon purple">
                <i class="fas fa-notes-medical"></i>
            </div>
            <div class="kpi-content">
                <h4>100%</h4>
                <p>Historias Clínicas Activas</p>
            </div>
        </div>
    </div>

    <!-- Table Card Container -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>
                <i class="fas fa-address-book" style="color: var(--primary);"></i>
                Lista de Pacientes
            </h3>
            <div class="export-buttons" id="exportButtonsContainer">
                <!-- DataTables export buttons generated here -->
            </div>
        </div>

        <!-- Desktop DataTable -->
        <div class="table-responsive">
            <table id="clientesTable" class="table align-middle">
                <thead>
                    <tr>
                        <th>Paciente / Cliente</th>
                        <th>Teléfono de Contacto</th>
                        <th style="text-align: right; padding-right: 20px;">Acciones Rápidas</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Poblando únicamente los 15 pacientes por servidor (LIMIT 15 OFFSET X) -->
                </tbody>
            </table>
        </div>

        <!-- Mobile Card Layout -->
        <div class="mobile-cards" id="mobileCards">
            <div class="mobile-search">
                <i class="fas fa-search"></i>
                <input type="text" id="mobileSearchInput" placeholder="Buscar paciente por nombre o teléfono..." autocomplete="off">
            </div>
            <div id="mobileCardsList">
                <!-- Poblado dinámicamente mediante DataTables Server-Side drawCallback -->
            </div>
            <div class="mobile-pagination" id="mobilePagination"></div>
            <div class="mobile-client-count">
                Mostrando los <span id="mobileVisibleCount">15</span> pacientes de la página activa
            </div>
        </div>
    </div>
</div>

<!-- Historial Drawer -->
<div class="historial-overlay" id="historialOverlay" onclick="closeHistorial()"></div>
<div class="historial-drawer" id="historialDrawer">
    <div class="historial-drawer-header">
        <h4><i class="fas fa-history"></i> Historial de Citas</h4>
        <button class="close-drawer" onclick="closeHistorial()">&times;</button>
    </div>
    <div class="historial-client-info" id="historialClientInfo">
        <div class="historial-client-avatar" id="historialAvatar">?</div>
        <div class="historial-client-details">
            <h5 id="historialClientName">-</h5>
            <small id="historialClientPhone">-</small>
        </div>
    </div>
    <div class="historial-stats-row" id="historialStatsRow">
        <div class="historial-stat"><span class="stat-num" id="histTotalCitas">0</span><span class="stat-label">Total</span></div>
        <div class="historial-stat"><span class="stat-num" id="histConfirmadas">0</span><span class="stat-label">Confirmadas</span></div>
        <div class="historial-stat"><span class="stat-num" id="histCanceladas">0</span><span class="stat-label">Canceladas</span></div>
    </div>
    <div class="historial-filters" id="historialFilters">
        <button class="historial-tag active" data-filter="todas" onclick="filterHistorial('todas', this)">Todas <span class="tag-count" id="tagCountTodas">0</span></button>
        <button class="historial-tag" data-filter="proximas" onclick="filterHistorial('proximas', this)"><i class="fas fa-arrow-up" style="font-size:0.65rem"></i> Próximas <span class="tag-count" id="tagCountProximas">0</span></button>
        <button class="historial-tag" data-filter="pasadas" onclick="filterHistorial('pasadas', this)"><i class="fas fa-arrow-down" style="font-size:0.65rem"></i> Pasadas <span class="tag-count" id="tagCountPasadas">0</span></button>
    </div>
    <div class="historial-body" id="historialBody">
        <div class="historial-loading text-center py-4 text-muted">
            <i class="fas fa-spinner fa-spin fa-2x mb-2" style="color: var(--primary);"></i>
            <p>Cargando historial...</p>
        </div>
    </div>
</div>

<!-- Modal Modificar Cliente -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="clientModalLabel">
                    <i class="fas fa-user-edit me-2"></i> Modificar Datos del Paciente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="clientForm" method="POST" action="update_client.php">
                    <input type="hidden" id="client_id" name="id">
                    
                    <div class="form-group">
                        <label for="client_nombre">
                            <i class="fas fa-user"></i> Nombre Completo
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="client_nombre" 
                               name="nombre" 
                               placeholder="Ingrese el nombre completo"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_telefono">
                            <i class="fas fa-phone"></i> Teléfono / Celular
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="client_telefono" 
                               name="telefono" 
                               placeholder="591XXXXXXXX"
                               required>
                        <small class="text-muted d-block mt-1">Formato: Código de país + número (ej: 59170000000)</small>
                    </div>
                    
                    <button type="submit" class="btn-save mt-3">
                        <i class="fas fa-check-circle me-2"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<script>
function editClient(id, nombre, telefono) {
    document.getElementById('client_id').value = id;
    document.getElementById('client_nombre').value = nombre;
    document.getElementById('client_telefono').value = telefono;
    var clientModal = new bootstrap.Modal(document.getElementById('clientModal'));
    clientModal.show();
}

function deleteClient(id) {
    if (confirm('¿Está seguro de eliminar este cliente?\n\nEsta acción no se puede deshacer.')) {
        window.location.href = 'delete_client.php?id=' + id;
    }
}

// Historial Drawer Logic
var historialCitasData = [];

function openHistorial(clienteId, nombre, telefono) {
    var drawer = document.getElementById('historialDrawer');
    var overlay = document.getElementById('historialOverlay');
    var body = document.getElementById('historialBody');

    document.getElementById('historialAvatar').textContent = nombre ? nombre.charAt(0).toUpperCase() : '?';
    document.getElementById('historialClientName').textContent = nombre;
    
    var phoneHtml = '<i class="fas fa-phone-alt me-1 text-primary"></i>' + (telefono || 'Sin teléfono');
    if (telefono) {
        var cleanPhone = telefono.replace(/[^0-9]/g, '');
        phoneHtml += ' <a href="https://wa.me/' + cleanPhone + '" target="_blank" class="text-success ms-2" title="Enviar WhatsApp"><i class="fab fa-whatsapp"></i></a>';
    }
    document.getElementById('historialClientPhone').innerHTML = phoneHtml;

    document.querySelectorAll('.historial-tag').forEach(function(t) { t.classList.remove('active'); });
    var tagTodas = document.querySelector('.historial-tag[data-filter="todas"]');
    if (tagTodas) tagTodas.classList.add('active');

    body.innerHTML = '<div class="historial-loading text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-3" style="color: var(--primary);"></i><p class="fw-semibold">Cargando expediente de citas...</p></div>';

    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch('get_historial_cliente.php?cliente_id=' + clienteId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.citas || data.citas.length === 0) {
                historialCitasData = [];
                updateHistorialCounts();
                body.innerHTML = '<div class="empty-state py-5 text-center"><div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(0, 168, 150, 0.1); color: var(--accent-teal);"><i class="fas fa-calendar-times" style="font-size: 1.8rem;"></i></div><h6 class="fw-bold text-dark mb-1">No hay citas registradas</h6><p class="text-muted small">El historial de citas de este paciente se mostrará aquí.</p></div>';
                return;
            }

            var today = new Date();
            today.setHours(0,0,0,0);
            data.citas.forEach(function(c) {
                var parts = c.fecha.split('/');
                var citaDate = new Date(parts[2], parts[1] - 1, parts[0]);
                c._date = citaDate;
                c._isProxima = citaDate >= today;
            });

            historialCitasData = data.citas;
            updateHistorialCounts();
            renderHistorialCards('todas');
        })
        .catch(function() {
            body.innerHTML = '<div class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><p class="fw-bold">Error al cargar el historial</p></div>';
        });
}

function updateHistorialCounts() {
    var total = historialCitasData.length;
    var proximas = 0, pasadas = 0, confirmadas = 0, canceladas = 0;
    historialCitasData.forEach(function(c) {
        if (c._isProxima) proximas++; else pasadas++;
        var est = (c.estado || 'activo').toLowerCase();
        if (est === 'confirmado') confirmadas++;
        if (est === 'cancelado') canceladas++;
    });
    document.getElementById('histTotalCitas').textContent = total;
    document.getElementById('histConfirmadas').textContent = confirmadas;
    document.getElementById('histCanceladas').textContent = canceladas;
    document.getElementById('tagCountTodas').textContent = total;
    document.getElementById('tagCountProximas').textContent = proximas;
    document.getElementById('tagCountPasadas').textContent = pasadas;
}

function filterHistorial(filter, btn) {
    document.querySelectorAll('.historial-tag').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    renderHistorialCards(filter);
}

function renderHistorialCards(filter) {
    var body = document.getElementById('historialBody');
    var filtered = historialCitasData.filter(function(c) {
        if (filter === 'proximas') return c._isProxima;
        if (filter === 'pasadas') return !c._isProxima;
        return true;
    });

    if (filtered.length === 0) {
        var msg = filter === 'proximas' ? 'No hay citas próximas programadas' : filter === 'pasadas' ? 'No hay registro de citas pasadas' : 'No hay citas registradas para este paciente';
        body.innerHTML = '<div class="empty-state py-5 text-center"><div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(0, 168, 150, 0.1); color: var(--accent-teal);"><i class="fas fa-calendar-times" style="font-size: 1.8rem;"></i></div><h6 class="fw-bold text-dark mb-1">' + msg + '</h6></div>';
        return;
    }

    var html = '';
    filtered.forEach(function(cita) {
        var est = (cita.estado || 'activo').toLowerCase();
        var estadoClass = 'estado-' + est;
        
        var badgeColorClass = est === 'confirmado' ? 'bg-success text-white' : (est === 'pospuesto' ? 'bg-warning text-dark' : (est === 'cancelado' ? 'bg-danger text-white' : 'bg-secondary text-white'));
        var estadoLabel = cita.estado ? (cita.estado.charAt(0).toUpperCase() + cita.estado.slice(1)) : 'Activo';
        
        var timeBadge = cita._isProxima
            ? '<span class="badge me-1" style="background: rgba(107, 29, 73, 0.12); color: #6B1D49; font-size: 0.72rem; font-weight: 700;"><i class="fas fa-arrow-up me-1"></i> Próxima</span>'
            : '<span class="badge me-1" style="background: rgba(100, 116, 139, 0.12); color: #64748B; font-size: 0.72rem; font-weight: 700;"><i class="fas fa-history me-1"></i> Pasada</span>';

        html += '<div class="historial-item ' + estadoClass + '">';
        html += '  <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom: 1px dashed var(--border-color);">';
        html += '    <span class="fw-bold text-dark" style="font-size: 0.92rem;"><i class="fas fa-calendar-day me-1" style="color: var(--primary);"></i> ' + cita.fecha + '</span>';
        html += '    <div class="d-flex align-items-center gap-1">' + timeBadge + '<span class="badge ' + badgeColorClass + '" style="font-size: 0.72rem; padding: 4px 8px;">' + estadoLabel + '</span></div>';
        html += '  </div>';
        html += '  <div class="row g-2 text-muted" style="font-size: 0.82rem;">';
        html += '    <div class="col-6"><i class="fas fa-clock text-primary me-1"></i> <strong>' + cita.hora + '</strong></div>';
        html += '    <div class="col-6 text-end"><i class="fas fa-stopwatch me-1" style="color: var(--accent);"></i> ' + cita.duracion_estimada + ' min</div>';
        html += '    <div class="col-12 text-truncate"><i class="fas fa-clinic-medical text-primary me-1"></i> <span class="text-dark fw-semibold">' + cita.consultorio_nombre + '</span></div>';
        html += '    <div class="col-12 text-truncate"><i class="fas fa-user-md me-1" style="color: var(--accent-teal);"></i> <span>' + (cita.doctor_nombre || 'Sin asignar') + '</span></div>';
        html += '  </div>';
        if (cita.descripcion) {
            html += '  <div class="mt-2 p-2 rounded text-secondary" style="background: #F1F5F9; font-size: 0.78rem; border-left: 3px solid var(--accent-teal);"><i class="fas fa-comment-alt me-1 text-muted"></i> ' + cita.descripcion + '</div>';
        }
        html += '</div>';
    });
    body.innerHTML = html;
}

function closeHistorial() {
    document.getElementById('historialDrawer').classList.remove('open');
    document.getElementById('historialOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeHistorial();
});

function toggleMobileMenu(btn) {
    var dropdown = btn.nextElementSibling;
    var isOpen = dropdown.classList.contains('show');
    closeMobileMenus();
    if (!isOpen) {
        dropdown.classList.add('show');
        btn.closest('.mobile-client-card').classList.add('menu-open');
    }
}

function closeMobileMenus() {
    document.querySelectorAll('.mobile-dropdown.show').forEach(function(d) {
        d.classList.remove('show');
    });
    document.querySelectorAll('.mobile-client-card.menu-open').forEach(function(c) {
        c.classList.remove('menu-open');
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.mobile-more-btn') && !e.target.closest('.mobile-dropdown')) {
        closeMobileMenus();
    }
});

function normalize(str) {
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function levenshtein(a, b) {
    var m = a.length, n = b.length;
    if (m === 0) return n;
    if (n === 0) return m;
    var d = [];
    for (var i = 0; i <= m; i++) { d[i] = [i]; }
    for (var j = 0; j <= n; j++) { d[0][j] = j; }
    for (i = 1; i <= m; i++) {
        for (j = 1; j <= n; j++) {
            var cost = a[i-1] === b[j-1] ? 0 : 1;
            d[i][j] = Math.min(d[i-1][j] + 1, d[i][j-1] + 1, d[i-1][j-1] + cost);
        }
    }
    return d[m][n];
}

function fuzzyMatch(text, query) {
    text = normalize(text);
    query = normalize(query);
    if (text.includes(query)) return true;
    var words = query.split(/\s+/).filter(function(w) { return w.length > 0; });
    return words.every(function(word) {
        if (text.includes(word)) return true;
        var textWords = text.split(/\s+/);
        var threshold = Math.max(1, Math.floor(word.length * 0.35));
        return textWords.some(function(tw) {
            if (tw.startsWith(word) || word.startsWith(tw)) return true;
            var sub = tw.substring(0, word.length);
            return levenshtein(sub, word) <= threshold || levenshtein(tw, word) <= threshold;
        });
    });
}

var mobilePerPage = 10;
var mobileCurrentPage = 1;
var mobileAllCards = [];
var mobileFilteredCards = [];

document.addEventListener('DOMContentLoaded', function() {
    mobileAllCards = Array.from(document.querySelectorAll('.mobile-client-card'));
    mobileFilteredCards = mobileAllCards.slice();
    renderMobilePage();

    var searchInput = document.getElementById('mobileSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.trim();
            mobileCurrentPage = 1;
            if (query.length === 0) {
                mobileFilteredCards = mobileAllCards.slice();
            } else {
                mobileFilteredCards = mobileAllCards.filter(function(card) {
                    var name = card.getAttribute('data-name') || '';
                    var phone = card.getAttribute('data-phone') || '';
                    return fuzzyMatch(name, query) || fuzzyMatch(phone, query);
                });
            }
            renderMobilePage();
        });
    }
});

function renderMobilePage() {
    var total = mobileFilteredCards.length;
    var totalPages = Math.max(1, Math.ceil(total / mobilePerPage));
    if (mobileCurrentPage > totalPages) mobileCurrentPage = totalPages;
    var start = (mobileCurrentPage - 1) * mobilePerPage;
    var end = start + mobilePerPage;

    mobileAllCards.forEach(function(c) { c.style.display = 'none'; });
    mobileFilteredCards.forEach(function(c, i) {
        c.style.display = (i >= start && i < end) ? 'flex' : 'none';
    });

    var counter = document.getElementById('mobileVisibleCount');
    if (counter) {
        if (total === 0) {
            counter.textContent = '0';
        } else {
            counter.textContent = (start + 1) + '-' + Math.min(end, total) + ' de ' + total;
        }
    }

    renderMobilePagination(totalPages);
}

function renderMobilePagination(totalPages) {
    var container = document.getElementById('mobilePagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    var html = '';
    html += '<button ' + (mobileCurrentPage === 1 ? 'disabled style="opacity:0.4;cursor:default"' : '') + ' onclick="goMobilePage(' + (mobileCurrentPage - 1) + ')"><i class="fas fa-chevron-left"></i></button>';

    var startPage = Math.max(1, mobileCurrentPage - 2);
    var endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    for (var p = startPage; p <= endPage; p++) {
        html += '<button class="' + (p === mobileCurrentPage ? 'active' : '') + '" onclick="goMobilePage(' + p + ')">' + p + '</button>';
    }

    html += '<button ' + (mobileCurrentPage === totalPages ? 'disabled style="opacity:0.4;cursor:default"' : '') + ' onclick="goMobilePage(' + (mobileCurrentPage + 1) + ')"><i class="fas fa-chevron-right"></i></button>';

    container.innerHTML = html;
}

function goMobilePage(page) {
    var totalPages = Math.max(1, Math.ceil(mobileFilteredCards.length / mobilePerPage));
    if (page < 1 || page > totalPages) return;
    mobileCurrentPage = page;
    renderMobilePage();
    var cardsList = document.getElementById('mobileCardsList');
    if (cardsList) cardsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderMobileCardsFromData(dataList) {
    var cardsList = document.getElementById('mobileCardsList');
    if (!cardsList) return;
    if (!dataList || dataList.length === 0) {
        cardsList.innerHTML = '<div class="text-center py-4 text-muted"><p>No se encontraron pacientes que coincidan.</p></div>';
        return;
    }
    var html = '';
    dataList.forEach(function(row) {
        var id = row.raw_id;
        var nombre = row.raw_nombre || '';
        var telefono = row.raw_telefono || '';
        var iniciales = nombre ? nombre.charAt(0).toUpperCase() : '?';
        var cleanPhone = telefono.replace(/[^0-9]/g, '');

        var escNombre = nombre.replace(/'/g, "\\'");
        var escTelefono = telefono.replace(/'/g, "\\'");

        html += `
        <div class="mobile-client-card">
            <div class="mobile-client-avatar">${iniciales}</div>
            <div class="mobile-client-info">
                <p class="mobile-client-name">${nombre}</p>
                <p class="mobile-client-phone">
                    <i class="fas fa-phone-alt"></i> ${telefono}
                </p>
            </div>
            <div class="mobile-client-actions">
                <a href="https://wa.me/${cleanPhone}" target="_blank" class="mobile-btn" style="background: #25D366; color: white;" title="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <div style="position:relative;">
                    <button class="mobile-more-btn" onclick="toggleMobileMenu(this)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="mobile-dropdown">
                        <button onclick="editClient(${id}, '${escNombre}', '${escTelefono}'); closeMobileMenus();">
                            <i class="fas fa-pen" style="color: var(--primary);"></i> Editar datos
                        </button>
                        <a href="historia_clinica.php?id=${id}">
                            <i class="fas fa-file-medical" style="color: var(--accent-teal);"></i> Historia clínica
                        </a>
                        <a href="odontograma.php?cliente_id=${id}">
                            <i class="fas fa-tooth" style="color: var(--accent-blue);"></i> Odontograma
                        </a>
                        <button onclick="openHistorial(${id}, '${escNombre}', '${escTelefono}'); closeMobileMenus();">
                            <i class="fas fa-history" style="color: var(--accent-purple);"></i> Historial de citas
                        </button>
                        <button class="text-danger" onclick="deleteClient(${id}); closeMobileMenus();">
                            <i class="fas fa-trash-alt"></i> Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    });
    cardsList.innerHTML = html;
}

// DataTables Initializer (Server-Side Processing backend activo)
$(document).ready(function() {
    var table = $('#clientesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api_clientes_paginados.php',
            type: 'POST'
        },
        columns: [
            { orderable: true },
            { orderable: true },
            { orderable: false }
        ],
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"lip>',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-1"></i><span class="btn-text">Excel</span>',
                className: 'btn-export excel',
                exportOptions: { columns: [0, 1] },
                title: 'Lista de Clientes - Dra. Tatiana Ruiz - <?php echo date("d-m-Y"); ?>'
            },
            {
                text: '<i class="fas fa-file-pdf me-1"></i><span class="btn-text">PDF</span>',
                className: 'btn-export pdf',
                action: function(e, dt, button, config) {
                    var search = $('input[type="search"]').val();
                    var params = search ? '?search=' + encodeURIComponent(search) : '';
                    window.location.href = 'exportar_clientes_pdf.php' + params;
                }
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv me-1"></i><span class="btn-text">CSV</span>',
                className: 'btn-export csv',
                exportOptions: { columns: [0, 1] },
                title: 'Lista de Clientes - Dra. Tatiana Ruiz'
            },
            {
                text: '<i class="fas fa-print me-1"></i><span class="btn-text">Imprimir</span>',
                className: 'btn-export print',
                exportOptions: { columns: [0, 1] },
                title: 'Lista de Clientes - Dra. Tatiana Ruiz',
                messageTop: '<h3>Fecha: <?php echo date("d/m/Y"); ?></h3>'
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json',
            search: "_INPUT_",
            searchPlaceholder: "Buscar pacientes por nombre o teléfono..."
        },
        pageLength: 15,
        responsive: true,
        order: [[0, 'asc']],
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        initComplete: function() {
            $('.dt-buttons').appendTo('#exportButtonsContainer');
        },
        drawCallback: function(settings) {
            var apiData = settings.json ? settings.json.data : [];
            renderMobileCardsFromData(apiData);
            if (settings.json && document.getElementById('mobileVisibleCount')) {
                document.getElementById('mobileVisibleCount').textContent = apiData.length;
            }
        }
    });
});
</script>

</body>
</html>
