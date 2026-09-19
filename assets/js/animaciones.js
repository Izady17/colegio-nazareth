document.addEventListener("DOMContentLoaded", function () {
    // 1. Ocultar alertas emergentes automáticamente tras 4 segundos
    const alertas = document.querySelectorAll(".alert");
    if (alertas.length > 0) {
        setTimeout(() => {
            alertas.forEach(alerta => {
                alerta.style.transition = "opacity 0.5s ease";
                alerta.style.opacity = "0";
                setTimeout(() => alerta.remove(), 500);
            });
        }, 4000);
    }

    // 2. Confirmación previa para envío de formularios o acciones críticas
    const confirmables = document.querySelectorAll(".confirm-action");
    confirmables.forEach(elemento => {
        elemento.addEventListener("click", function (e) {
            if (!confirm("¿Está seguro de realizar esta acción?")) {
                e.preventDefault();
            }
        });
    });
});