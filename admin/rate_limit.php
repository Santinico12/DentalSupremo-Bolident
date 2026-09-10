<?php
session_start();
$currentTime = time();
$waitTime = isset($_SESSION['rate_limit']['blocked_until']) ? 
           $_SESSION['rate_limit']['blocked_until'] - $currentTime : 
           0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Demasiadas Peticiones</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
	<style>
		body {
			height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background-color: #f5f5f5;
		}
		.rate-limit-card {
			max-width: 400px;
			padding: 2rem;
			text-align: center;
			background: white;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		}
		.countdown {
			font-size: 2rem;
			font-weight: bold;
			color: #003B73;
			margin: 1rem 0;
		}
		.message {
			color: #666;
			margin-bottom: 1rem;
		}
	</style>
</head>
<body>
	<div class="rate-limit-card">
		<h2>Demasiadas Peticiones</h2>
		<p class="message">Por favor, espere antes de realizar más peticiones.</p>
		<div class="countdown" id="countdown"><?php echo $waitTime; ?></div>
		<p>segundos restantes</p>
		<button class="btn btn-primary" onclick="checkAndRedirect()" style="background-color: #003B73; border: none;">
			Intentar de nuevo
		</button>
	</div>

	<script>
		let timeLeft = <?php echo $waitTime; ?>;
		
		function updateCountdown() {
			if (timeLeft > 0) {
				document.getElementById('countdown').textContent = timeLeft;
				timeLeft--;
				setTimeout(updateCountdown, 1000);
			} else {
				window.location.href = 'login.php';
			}
		}

		function checkAndRedirect() {
			if (timeLeft <= 0) {
				window.location.href = 'login.php';
			}
		}

		updateCountdown();
	</script>
</body>
</html>
