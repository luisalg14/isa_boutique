// ===============================
// FUNCIONES DEL LOGIN
// ===============================

export function verificarSesion(loginBox, contenido) {
    const sesionActiva = localStorage.getItem("auth");

    if (sesionActiva === "true") {
        loginBox.style.display = "none";
        contenido.style.display = "block";
    } else {
        loginBox.style.display = "flex";
        contenido.style.display = "none";
    }
}

export function iniciarSesion(claveIngresada, loginBox, contenido, mensaje) {
    const password = "1234";

    if (claveIngresada === password) {
        localStorage.setItem("auth", "true");
        loginBox.style.display = "none";
        contenido.style.display = "block";
        mensaje.textContent = "";
    } else {
        mensaje.textContent = "Contraseña incorrecta";
    }
}

export function cerrarSesion(loginBox, contenido) {
    localStorage.removeItem("auth");
    contenido.style.display = "none";
    loginBox.style.display = "flex";
}