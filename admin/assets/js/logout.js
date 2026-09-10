// Prevenir navegación hacia atrás después del logout
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

// Agregar listener al botón de logout
document.querySelector('.logout-button').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Realizar el logout
    fetch('logout.php')
        .then(() => {
            // Limpiar cualquier dato almacenado en el navegador
            localStorage.clear();
            sessionStorage.clear();
            
            // Redirigir al login
            window.location.href = 'login.php';
        });
});