<?php
date_default_timezone_set('America/La_Paz');
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/controllers/ClientController.php';
require_once '../src/models/Client.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $codigo_pais = $_POST['codigo_pais'];
    $telefono = $_POST['telefono'];

    $telefono_completo = $codigo_pais . $telefono;

    $clientModel = new Client($pdo);

    $existingClientWithoutExtension = $clientModel->findByPhone($telefono);

    if ($existingClientWithoutExtension) {
        $clientModel->deleteByPhone($telefono);
    }

    $clienteId = $clientModel->create($nombre, $telefono_completo);

    // Enviar mensaje de bienvenida Dra. Tatiana Ruiz vía Bot si hay teléfono
    if (!empty($telefono_completo) && $clienteId) {
        try {
            $welcomeMsg = "🦷 *¡Bienvenido/a a la Clínica Dra. Tatiana Ruiz!* ✨\n\n"
                        . "¡Hola *{$nombre}*! 👋 Te saludamos cordialmente.\n\n"
                        . "Queremos darte la bienvenida y recordarte que en esta línea de WhatsApp dispones de nuestro *Asistente Virtual Inteligente 24/7* para:\n"
                        . "📅 *Consultar y confirmar tus citas odontológicas.*\n"
                        . "⏰ *Conocer horarios de atención y consultorios.*\n"
                        . "🩺 *Informarte sobre tratamientos de Rehabilitación, Estética y Ortodoncia.*\n\n"
                        . "¡Estamos a tu servicio para cuidar de tu sonrisa! Si necesitas algo, sólo responde a este mensaje. 😊🦷✨";

            $botApiUrl = getenv('BOT_API_URL') ? rtrim(getenv('BOT_API_URL'), '/') . '/send-welcome' : 'https://dratatianaruiz-bot.onrender.com/api/send-welcome';
            $postData = json_encode([
                'clienteId' => $clienteId,
                'telefono' => $telefono_completo,
                'pacienteNombre' => $nombre,
                'mensaje' => $welcomeMsg
            ]);

            $ch = curl_init($botApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {}
    }

    $_SESSION['message'] = 'Cliente registrado exitosamente.';
    $_SESSION['message_type'] = 'success';

    header('Location: lista_clientes.php');
    exit();
}

require_once '../templates/header_general.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root {
        --primary: #6B1D49;
        --primary-dark: #531438;
        --accent: #C47D9F;
        --success: #28a745;
        --danger: #dc3545;
        --light: #FDF8FA;
        --dark: #343a40;
        --shadow: 0 2px 8px rgba(107, 29, 73, 0.08);
        --shadow-hover: 0 4px 16px rgba(107, 29, 73, 0.2);
    }

    body {
        background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .main-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Card */
    .header-card {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-radius: 15px;
        padding: 30px;
        color: white;
        box-shadow: var(--shadow-hover);
        margin-bottom: 30px;
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header-card h2 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-card p {
        margin: 10px 0 0 0;
        opacity: 0.9;
        font-size: 1.1rem;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .btn-header {
        background: white;
        color: var(--primary);
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-header:hover {
        background: var(--light);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: var(--primary-dark);
    }

    /* Form Card */
    .form-card {
        background: white;
        border-radius: 15px;
        padding: 40px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid var(--primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-section-title i {
        color: var(--primary);
        font-size: 1.5rem;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
    }

    .form-label i {
        color: var(--primary);
        font-size: 1.1rem;
    }

    .form-label .required {
        color: var(--danger);
        margin-left: 3px;
    }

    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 14px 18px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 0.2rem rgba(196, 162, 126, 0.25);
        transform: translateY(-2px);
    }

    .input-group {
        display: flex;
        gap: 10px;
    }

    .input-group .form-select {
        flex: 0 0 140px;
    }

    .input-group .form-control {
        flex: 1;
    }

    .phone-input-wrapper {
        position: relative;
    }

    .phone-input-wrapper .input-icon {
        position: absolute;
        left: 155px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        pointer-events: none;
    }

    .phone-input-wrapper .form-control {
        padding-left: 40px;
    }

    .btn-submit {
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
        box-shadow: 0 4px 15px rgba(196, 162, 126, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-submit:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-hover);
    }

    .btn-submit:active {
        transform: translateY(-1px);
    }

    .alert {
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        border: none;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.5s ease;
    }

    .alert i {
        font-size: 1.3rem;
    }

    .alert-success {
        background: linear-gradient(135deg, #28a745 0%, #20853a 100%);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }

    .helper-text {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .helper-text i {
        color: var(--primary);
    }

    /* Info boxes */
    .info-box {
        background: rgba(196, 162, 126, 0.1);
        border-left: 4px solid var(--primary);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
    }

    .info-box-title {
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-box-text {
        color: #6c757d;
        font-size: 0.95rem;
        margin: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-card {
            padding: 25px 20px;
        }

        .header-card {
            padding: 25px 20px;
        }

        .header-card h2 {
            font-size: 1.5rem;
        }

        .input-group {
            flex-direction: column;
        }

        .input-group .form-select {
            flex: 1;
        }

        .phone-input-wrapper .input-icon {
            left: 15px;
        }

        .phone-input-wrapper .form-control {
            padding-left: 18px;
        }
    }
</style>

<div class="main-container">
    <!-- Alerts -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
            <i class="fas fa-<?php echo $_SESSION['message_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo $_SESSION['message']; ?></span>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <h2>
            <i class="fas fa-user-plus"></i>
            Registrar Nuevo Cliente
        </h2>
        <p>Agrega un nuevo paciente al sistema</p>
        <div class="header-actions">
            <a href="dashboard.php" class="btn-header">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="lista_clientes.php" class="btn-header">
                <i class="fas fa-list"></i>
                Ver Lista
            </a>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <div class="info-box-title">
            <i class="fas fa-info-circle"></i>
            Informaci&oacute;n importante
        </div>
        <p class="info-box-text">
            El n&uacute;mero de tel&eacute;fono se guardar&aacute; con el c&oacute;digo de pa&iacute;s para facilitar el contacto por WhatsApp.
        </p>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-section-title">
            <i class="fas fa-edit"></i>
            Datos del Cliente
        </div>

        <form method="POST" action="registrar_cliente.php" id="clientForm">
            <div class="form-group">
                <label for="nombre" class="form-label">
                    <i class="fas fa-user"></i>
                    Nombre Completo
                    <span class="required">*</span>
                </label>
                <input type="text" 
                       class="form-control" 
                       id="nombre" 
                       name="nombre" 
                       placeholder="Ej: Juan P&eacute;rez Garc&iacute;a"
                       required>
                <div class="helper-text">
                    <i class="fas fa-lightbulb"></i>
                    Ingrese el nombre completo del paciente
                </div>
            </div>

            <div class="form-group">
                <label for="codigo_pais" class="form-label">
                    <i class="fas fa-globe"></i>
                    Tel&eacute;fono de Contacto
                    <span class="required">*</span>
                </label>
                <div class="input-group">
                    <select class="form-select" id="codigo_pais" name="codigo_pais" required>
                        <option value="+591" selected>🇧🇴 +591</option>
                        <option value="+54">🇦🇷 +54 Argentina</option>
                        <option value="+55">🇧🇷 +55 Brasil</option>
                        <option value="+56">🇨🇱 +56 Chile</option>
                        <option value="+57">🇨🇴 +57 Colombia</option>
                        <option value="+593">🇪🇨 +593 Ecuador</option>
                        <option value="+595">🇵🇾 +595 Paraguay</option>
                        <option value="+51">🇵🇪 +51 Per&uacute;</option>
                        <option value="+598">🇺🇾 +598 Uruguay</option>
                        <option value="+58">🇻🇪 +58 Venezuela</option>
                        <option value="+52">🇲🇽 +52 M&eacute;xico</option>
                        <option value="+1">🇺🇸 +1 EE.UU/Canad&aacute;</option>
                        <option value="+34">🇪🇸 +34 Espa&ntilde;a</option>
                        <optgroup label="─────────────────"></optgroup>
                        <option value="+93">+93 Afganist&aacute;n</option>
                        <option value="+355">+355 Albania</option>
                        <option value="+49">+49 Alemania</option>
                        <option value="+376">+376 Andorra</option>
                        <option value="+244">+244 Angola</option>
                        <option value="+966">+966 Arabia Saudita</option>
                        <option value="+213">+213 Argelia</option>
                        <option value="+374">+374 Armenia</option>
                        <option value="+61">+61 Australia</option>
                        <option value="+43">+43 Austria</option>
                        <option value="+994">+994 Azerbaiy&aacute;n</option>
                        <option value="+1">+1 Bahamas</option>
                        <option value="+880">+880 Banglad&eacute;s</option>
                        <option value="+973">+973 Bar&eacute;in</option>
                        <option value="+32">+32 B&eacute;lgica</option>
                        <option value="+501">+501 Belice</option>
                        <option value="+375">+375 Bielorrusia</option>
                        <option value="+95">+95 Birmania</option>
                        <option value="+387">+387 Bosnia</option>
                        <option value="+267">+267 Botsuana</option>
                        <option value="+673">+673 Brun&eacute;i</option>
                        <option value="+359">+359 Bulgaria</option>
                        <option value="+855">+855 Camboya</option>
                        <option value="+237">+237 Camer&uacute;n</option>
                        <option value="+974">+974 Catar</option>
                        <option value="+86">+86 China</option>
                        <option value="+357">+357 Chipre</option>
                        <option value="+506">+506 Costa Rica</option>
                        <option value="+385">+385 Croacia</option>
                        <option value="+53">+53 Cuba</option>
                        <option value="+45">+45 Dinamarca</option>
                        <option value="+1">+1 Dominica</option>
                        <option value="+20">+20 Egipto</option>
                        <option value="+503">+503 El Salvador</option>
                        <option value="+971">+971 Emiratos &Aacute;rabes</option>
                        <option value="+372">+372 Estonia</option>
                        <option value="+251">+251 Etiop&iacute;a</option>
                        <option value="+63">+63 Filipinas</option>
                        <option value="+358">+358 Finlandia</option>
                        <option value="+33">+33 Francia</option>
                        <option value="+995">+995 Georgia</option>
                        <option value="+233">+233 Ghana</option>
                        <option value="+30">+30 Grecia</option>
                        <option value="+502">+502 Guatemala</option>
                        <option value="+504">+504 Honduras</option>
                        <option value="+852">+852 Hong Kong</option>
                        <option value="+36">+36 Hungr&iacute;a</option>
                        <option value="+91">+91 India</option>
                        <option value="+62">+62 Indonesia</option>
                        <option value="+964">+964 Irak</option>
                        <option value="+98">+98 Ir&aacute;n</option>
                        <option value="+353">+353 Irlanda</option>
                        <option value="+354">+354 Islandia</option>
                        <option value="+972">+972 Israel</option>
                        <option value="+39">+39 Italia</option>
                        <option value="+81">+81 Jap&oacute;n</option>
                        <option value="+962">+962 Jordania</option>
                        <option value="+254">+254 Kenia</option>
                        <option value="+965">+965 Kuwait</option>
                        <option value="+961">+961 L&iacute;bano</option>
                        <option value="+60">+60 Malasia</option>
                        <option value="+356">+356 Malta</option>
                        <option value="+212">+212 Marruecos</option>
                        <option value="+377">+377 M&oacute;naco</option>
                        <option value="+64">+64 Nueva Zelanda</option>
                        <option value="+31">+31 Pa&iacute;ses Bajos</option>
                        <option value="+92">+92 Pakist&aacute;n</option>
                        <option value="+507">+507 Panam&aacute;</option>
                        <option value="+48">+48 Polonia</option>
                        <option value="+351">+351 Portugal</option>
                        <option value="+44">+44 Reino Unido</option>
                        <option value="+1">+1 Rep. Dominicana</option>
                        <option value="+40">+40 Ruman&iacute;a</option>
                        <option value="+7">+7 Rusia</option>
                        <option value="+65">+65 Singapur</option>
                        <option value="+27">+27 Sud&aacute;frica</option>
                        <option value="+82">+82 Corea del Sur</option>
                        <option value="+94">+94 Sri Lanka</option>
                        <option value="+46">+46 Suecia</option>
                        <option value="+41">+41 Suiza</option>
                        <option value="+66">+66 Tailandia</option>
                        <option value="+886">+886 Taiw&aacute;n</option>
                        <option value="+90">+90 Turqu&iacute;a</option>
                        <option value="+380">+380 Ucrania</option>
                        <option value="+84">+84 Vietnam</option>
                    </select>
                    
                    <div class="phone-input-wrapper" style="flex: 1;">
                        <input type="tel" 
                               class="form-control" 
                               id="telefono" 
                               name="telefono" 
                               placeholder="70000000"
                               pattern="[0-9]+" 
                               maxlength="15"
                               required
                               onkeypress="return isNumberKey(event)">
                    </div>
                </div>
                <div class="helper-text">
                    <i class="fas fa-info-circle"></i>
                    Solo n&uacute;meros, sin espacios ni guiones
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i>
                Registrar Cliente
            </button>
        </form>
    </div>

    <div class="d-flex justify-content-center">
        <a href="lista_clientes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            Volver a la Lista
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function isNumberKey(event) {
        const charCode = (event.which) ? event.which : event.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            event.preventDefault();
            return false;
        }
        return true;
    }

    // Validación del formulario
    document.getElementById('clientForm').addEventListener('submit', function(e) {
        const nombre = document.getElementById('nombre').value.trim();
        const telefono = document.getElementById('telefono').value.trim();

        if (nombre.length < 3) {
            e.preventDefault();
            alert('El nombre debe tener al menos 3 caracteres');
            return false;
        }

        if (telefono.length < 6) {
            e.preventDefault();
            alert('El número de teléfono debe tener al menos 6 dígitos');
            return false;
        }

        // Animación del botón
        const btn = this.querySelector('.btn-submit');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });

    // Animaciones de focus en inputs
    const inputs = document.querySelectorAll('.form-control, .form-select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
</script>

</body>
</html>
