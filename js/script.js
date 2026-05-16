// ===============================
// CONEXIÓN DEL LOGIN CON ADMIN.HTML
// ===============================

import { verificarSesion, iniciarSesion, cerrarSesion } from "./auth.js";

document.addEventListener("DOMContentLoaded", function() {
    const loginBox = document.getElementById("loginBox");
    const contenido = document.getElementById("contenido");
    const correoInput = document.getElementById("correo");
    const claveInput = document.getElementById("clave");
    const btnEntrar = document.getElementById("btnEntrar");
    const btnCerrar = document.getElementById("btnCerrar");
    const mensaje = document.getElementById("mensaje");

    // Si esta página no tiene login, no ejecuta nada
    if (!loginBox || !contenido || !correoInput || !claveInput || !btnEntrar || !btnCerrar || !mensaje) {
        return;
    }

    verificarSesion(loginBox, contenido);

    btnEntrar.addEventListener("click", function() {
        iniciarSesion(correoInput.value, claveInput.value, loginBox, contenido, mensaje);
    });

    claveInput.addEventListener("keydown", function(evento) {
        if (evento.key === "Enter") {
            iniciarSesion(correoInput.value, claveInput.value, loginBox, contenido, mensaje);
        }
    });

    correoInput.addEventListener("keydown", function(evento) {
        if (evento.key === "Enter") {
            iniciarSesion(correoInput.value, claveInput.value, loginBox, contenido, mensaje);
        }
    });

    btnCerrar.addEventListener("click", function() {
        cerrarSesion(loginBox, contenido);
    });
});
