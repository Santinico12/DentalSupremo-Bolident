<?php
session_start();

class RateLimiter {
	private $maxRequests = 30; // Máximo de peticiones por ventana de tiempo
	private $timeWindow = 60; // Ventana de tiempo en segundos (1 minuto)
	private $blockDuration = 30; // Duración del bloqueo en segundos

	public function checkLimit() {
		if (!isset($_SESSION['rate_limit'])) {
			$_SESSION['rate_limit'] = [
				'requests' => [],
				'blocked_until' => 0
			];
		}

		$currentTime = time();

		// Comprobar si está bloqueado
		if ($_SESSION['rate_limit']['blocked_until'] > $currentTime) {
			return false;
		}

		// Limpiar peticiones antiguas
		$_SESSION['rate_limit']['requests'] = array_filter(
			$_SESSION['rate_limit']['requests'],
			function($timestamp) use ($currentTime) {
				return $timestamp > ($currentTime - $this->timeWindow);
			}
		);

		// Añadir petición actual
		$_SESSION['rate_limit']['requests'][] = $currentTime;

		// Comprobar si excede el límite
		if (count($_SESSION['rate_limit']['requests']) > $this->maxRequests) {
			$_SESSION['rate_limit']['blocked_until'] = $currentTime + $this->blockDuration;
			return false;
		}

		return true;
	}

	public function getTimeRemaining() {
		if (isset($_SESSION['rate_limit']['blocked_until'])) {
			$remaining = $_SESSION['rate_limit']['blocked_until'] - time();
			return $remaining > 0 ? $remaining : 0;
		}
		return 0;
	}
}
?>
