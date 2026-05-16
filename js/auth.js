// ===============================
// FUNCIONES DEL LOGIN
// ===============================

function mostrarLogin(loginBox, contenido) {
    loginBox.style.display = "flex";
    contenido.style.display = "none";
}

function mostrarContenido(loginBox, contenido) {
    loginBox.style.display = "none";
    contenido.style.display = "block";
}

function panelPorRol(rol) {
    if (rol === "admin") {
        return "admin.html";
    }

    if (rol === "vendedor") {
        return "vendedor.html";
    }

    return "";
}

function redirigirSiCorresponde(usuario) {
    if (!usuario) return false;

    const panelActual = document.body.dataset.panel || "";
    const destino = panelPorRol(usuario.rol);

    if (!panelActual || !destino) return false;

    const archivoActual = window.location.pathname.split("/").pop() || "index.html";

    if (archivoActual !== destino) {
        window.location.href = destino;
        return true;
    }

    return false;
}

export function aplicarRol(usuario) {
    const rol = usuario ? usuario.rol : "";
    const nombreUsuario = document.getElementById("nombreUsuario");
    const rolUsuario = document.getElementById("rolUsuario");

    if (nombreUsuario) {
        nombreUsuario.textContent = usuario ? usuario.nombre : "";
    }

    if (rolUsuario) {
        rolUsuario.textContent = rol ? rol.toUpperCase() : "";
    }

    document.querySelectorAll("[data-admin-only]").forEach(function(elemento) {
        elemento.style.display = rol === "admin" ? "" : "none";
    });

    document.querySelectorAll("[data-vendedor-only]").forEach(function(elemento) {
        elemento.style.display = rol === "vendedor" ? "" : "none";
    });

    window.usuarioActual = usuario;
}

export async function verificarSesion(loginBox, contenido) {
    try {
        const respuesta = await fetch("php/sesion_actual.php");
        const sesion = await respuesta.json();

        if (sesion.autenticado) {
            mostrarContenido(loginBox, contenido);
            aplicarRol(sesion.usuario);

            if (redirigirSiCorresponde(sesion.usuario)) return;

            window.dispatchEvent(new CustomEvent("sesion-lista", {
                detail: sesion.usuario
            }));
        } else {
            mostrarLogin(loginBox, contenido);
            aplicarRol(null);
        }
    } catch (error) {
        mostrarLogin(loginBox, contenido);
        aplicarRol(null);
        console.error("Error al verificar la sesion", error);
    }
}

export async function iniciarSesion(correo, contrasena, loginBox, contenido, mensaje) {
    const datos = new FormData();
    datos.append("correo", correo.trim());
    datos.append("contrasena", contrasena.trim());

    try {
        const respuesta = await fetch("php/login.php", {
            method: "POST",
            body: datos
        });

        const resultado = await respuesta.json();

        if (resultado.error) {
            mensaje.textContent = resultado.mensaje;
            return;
        }

        mensaje.textContent = "";
        mostrarContenido(loginBox, contenido);
        aplicarRol(resultado.usuario);

        if (redirigirSiCorresponde(resultado.usuario)) return;

        window.dispatchEvent(new CustomEvent("sesion-lista", {
            detail: resultado.usuario
        }));
    } catch (error) {
        mensaje.textContent = "Error al iniciar sesion";
        console.error(error);
    }
}

export async function cerrarSesion(loginBox, contenido) {
    try {
        await fetch("php/logout.php");
    } catch (error) {
        console.error("Error al cerrar sesion", error);
    }

    aplicarRol(null);
    mostrarLogin(loginBox, contenido);
}
