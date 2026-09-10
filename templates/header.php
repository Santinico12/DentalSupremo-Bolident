<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6B1D49">
    <meta name="description" content="Sistema de Gestión de Citas Odontológicas - Dra. Tatiana Ruiz">
    <title>Dra. Tatiana Ruiz - Sistema de Citas</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/admin/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Dra. Tatiana Ruiz">
    
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" href="/admin/assets/images/logo_DraTatianaRuiz.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/admin/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/admin/assets/icons/icon-192x192.png">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="assets/js/script.js" defer></script>

    <style>
        header {
            background: linear-gradient(135deg, #6B1D49 0%, #531438 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        header .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        header .logo img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.12);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            padding: 2px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        header .logo h1 {
            margin: 0;
            font-size: 1.35em;
            font-weight: bold;
            color: #FFFFFF;
        }
        nav {
            display: flex;
            gap: 15px;
        }
        nav a {
            color: #FDF8FA;
            text-decoration: none;
            font-size: 1.05em;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 6px;
        }
        nav a:hover {
            color: #FFFFFF;
            background-color: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <a href="dashboard.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">
                <img src="assets/images/logo_DraTatianaRuiz.png" alt="Logo Dra. Tatiana Ruiz">
                <h1>Dra. Tatiana Ruiz</h1>
            </a>
        </div>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="calendario.php">Calendario</a>
            <a href="logout.php">Cerrar Sesión</a>
        </nav>
    </header>
</body>
</html>