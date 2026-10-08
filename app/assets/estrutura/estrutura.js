document.querySelectorAll('.alerta').forEach((alerta) => {
    alerta.addEventListener('click', () => alerta.remove());
});
