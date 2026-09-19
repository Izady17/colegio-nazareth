document.addEventListener('DOMContentLoaded', () => {
    // Seleccionar todas las tarjetas y el hero section para animar
    const animatedElements = document.querySelectorAll('.card, .hero-section');

    // Asignar la clase inicial de animación a cada elemento
    animatedElements.forEach(el => {
        el.classList.add('fade-in-element');
    });

    // Crear el observador de intersección (scroll)
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target); // Dejar de observar una vez animado
            }
        });
    }, {
        threshold: 0.1
    });

    // Observar cada elemento
    animatedElements.forEach(el => observer.observe(el));
});