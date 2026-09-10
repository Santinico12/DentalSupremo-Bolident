<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: calendario.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/User.php'; // Cambiar esto

// Rate Limiting
function checkRateLimit() {
    $maxRequests = 10;
    $timeWindow = 60;
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [
            'requests' => [],
            'blocked_until' => 0
        ];
    }
    
    $currentTime = time();
    
    if ($_SESSION['rate_limit']['blocked_until'] > $currentTime) {
        header('Location: rate_limit.php');
        exit();
    }
    
    $_SESSION['rate_limit']['requests'] = array_filter(
        $_SESSION['rate_limit']['requests'],
        function($timestamp) use ($currentTime, $timeWindow) {
            return $timestamp > ($currentTime - $timeWindow);
        }
    );
    
    $_SESSION['rate_limit']['requests'][] = $currentTime;
    
    if (count($_SESSION['rate_limit']['requests']) > $maxRequests) {
        $_SESSION['rate_limit']['blocked_until'] = $currentTime + 30;
        header('Location: rate_limit.php');
        exit();
    }
}

checkRateLimit();

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = 0;
}

$error = null;
$timeLeft = 0;

$blockTime = 30;
if ($_SESSION['login_attempts'] >= 3) {
    $timeSinceLastAttempt = time() - $_SESSION['last_attempt_time'];
    if ($timeSinceLastAttempt < $blockTime) {
        $timeLeft = $blockTime - $timeSinceLastAttempt;
        $error = "Demasiados intentos. Por favor, espere " . $timeLeft . " segundos.";
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

// MODIFICADO: Usar User directamente en lugar de AuthController
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$timeLeft) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Usar el modelo User directamente
        $userModel = new User($pdo);
        $user = $userModel->login($username, $password);

        if ($user) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['user'] = $user;
            header('Location: calendario.php');
            exit();
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            
            if ($_SESSION['login_attempts'] >= 4) {
                $error = "Demasiados intentos. Por favor, espere 30 segundos.";
                $timeLeft = 30;
            } 
            else {
                $error = "Usuario o contraseña incorrectos.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#003B73">
    <meta name="description" content="Iniciar sesión en Dentality - Odontología por Especialidades">
    <title>Iniciar Sesión - Dentality</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/images/logo_Dentality.png">
    
    <style>
        :root {
            --primary: #003B73;
            --primary-dark: #062846;
            --primary-light: #2998EC;
            --white: #ffffff;
            --gray-light: #F4F9FD;
            --shadow: 0 10px 30px rgba(0, 59, 115, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F4F9FD 0%, #E8F2FA 100%);
            position: relative;
            overflow: hidden;
        }

        /* Background decorativo con logo */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            opacity: 0.1;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -20%;
            width: 800px;
            height: 800px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            border-radius: 50%;
            opacity: 0.1;
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            padding: 20px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: white;
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        /* Decoración con logo de fondo */
        .login-card::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background-size: cover;
            background-position: center;
            opacity: 0.03;
            border-radius: 30px;
            z-index: 0;
        }

        .login-card > * {
            position: relative;
            z-index: 1;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 124px;
            height: 124px;
            border-radius: 26px;
            overflow: hidden;
            background: #ffffff;
            border: 2.5px solid rgba(20, 24, 104, 0.35);
            box-shadow: 0 10px 25px rgba(0, 59, 115, 0.16), 0 4px 10px rgba(0, 0, 0, 0.04);
            padding: 4px;
            animation: pulse 2.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 25px rgba(0, 59, 115, 0.16), 0 4px 10px rgba(0, 0, 0, 0.04);
            }
            50% {
                transform: scale(1.03);
                box-shadow: 0 14px 32px rgba(0, 59, 115, 0.26), 0 6px 14px rgba(0, 0, 0, 0.06);
            }
        }

        .logo {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            object-fit: contain;
            display: block;
        }

        h1 {
            text-align: center;
            color: var(--primary-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 35px;
        }

        .error-message {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        .timeout-message {
            background: linear-gradient(135deg, #003B73 0%, #2998EC 100%);
            color: white;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 59, 115, 0.2);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error-message i, .timeout-message i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(41, 152, 236, 0.18);
            transform: translateY(-2px);
        }

        .password-field {
            position: relative;
        }

        .password-field .form-control {
            padding-right: 50px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary-dark);
            transform: translateY(-50%) scale(1.1);
        }

        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(0, 59, 115, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .login-button:active {
            transform: translateY(-1px);
        }

        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-disabled {
            pointer-events: none;
            opacity: 0.6;
        }

        /* Footer info */
        .footer-info {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .footer-info p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .footer-info .brand {
            color: var(--primary);
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-card {
                padding: 40px 30px;
            }

            h1 {
                font-size: 1.6rem;
            }

            .logo-wrapper {
                width: 100px;
                height: 100px;
            }

            .form-control {
                padding: 13px 18px;
            }

            .login-button {
                padding: 14px;
                font-size: 1rem;
            }
        }

        /* Loading spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Security badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .security-badge i {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-wrapper">
                    <img src="assets/images/logo_Dentality.png" alt="Dentality Logo" class="logo">
                </div>
            </div>
            
            <h1>Dentality</h1>
            <p class="subtitle">Odontología por Especialidades</p>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="timeout-message" role="alert">
                    <i class="fas fa-clock"></i>
                    <span>Tu sesión se ha cerrado automáticamente por inactividad por seguridad.</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message" role="alert" <?php if ($timeLeft > 0) echo 'data-countdown="' . $timeLeft . '"'; ?>>
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate <?php if ($timeLeft > 0) echo 'class="form-disabled"'; ?>>
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Usuario
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="Ingrese su usuario"
                           required 
                           autocomplete="username"
                           <?php if ($timeLeft > 0) echo 'disabled'; ?>>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Contrase&ntilde;a
                    </label>
                    <div class="password-field">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Ingrese su contrase&ntilde;a"
                               required 
                               autocomplete="current-password"
                               <?php if ($timeLeft > 0) echo 'disabled'; ?>>
                        <button type="button" 
                                class="toggle-password" 
                                aria-label="Mostrar contrase&ntilde;a"
                                <?php if ($timeLeft > 0) echo 'disabled'; ?>>
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-button" id="loginBtn" <?php if ($timeLeft > 0) echo 'disabled'; ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Iniciar Sesión</span>
                </button>

                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sistema Monitoreado por <strong>Dentality</strong></span>
                </div>
            </form>

            <div class="footer-info">
                <p>&copy; 2026 <span class="brand">Dentality</span></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const toggleBtn = document.querySelector('.toggle-password');
            const passwordInput = document.getElementById('password');

            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'password') {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        this.setAttribute('aria-label', 'Mostrar contraseña');
                    } else {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        this.setAttribute('aria-label', 'Ocultar contraseña');
                    }
                });
            }

            // Countdown timer functionality
            const errorMessage = document.querySelector('.error-message[data-countdown]');
            if (errorMessage) {
                let timeLeft = parseInt(errorMessage.dataset.countdown);
                const form = document.querySelector('form');
                const loginBtn = document.getElementById('loginBtn');
                const inputs = form.querySelectorAll('input, button');
                
                const updateCountdown = () => {
                    if (timeLeft > 0) {
                        errorMessage.querySelector('span').textContent = 
                            `Demasiados intentos. Por favor, espere ${timeLeft} segundos.`;
                        timeLeft--;
                        setTimeout(updateCountdown, 1000);
                    } else {
                        errorMessage.remove();
                        form.classList.remove('form-disabled');
                        inputs.forEach(input => input.removeAttribute('disabled'));
                        location.reload();
                    }
                };

                updateCountdown();
            }

            // Form submission with loading state
            const form = document.querySelector('form');
            const loginBtn = document.getElementById('loginBtn');
            
            form.addEventListener('submit', function(e) {
                if (!errorMessage) {
                    loginBtn.innerHTML = '<div class="spinner"></div><span>Iniciando...</span>';
                    loginBtn.disabled = true;
                }
            });

            // Input animations
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' })
                    .then(registration => {
                        console.log('ServiceWorker registrado exitosamente:', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker error de registro:', err);
                    });
            });
        }
    </script>
</body>
</html>
