// ===============================
// FUNCIONES DEL LOGIN
// ===============================
let csrfToken = "";
const fetchOriginal = window.fetch.bind(window);

function guardarCsrfToken(token) {
    csrfToken = token || "";
}

function esMetodoProtegido(method) {
    return ["POST", "PUT", "PATCH", "DELETE"].includes((method || "GET").toUpperCase());
}

function esUrlMismoOrigen(input) {
    const url = typeof input === "string" ? input : input.url;
    return new URL(url, window.location.href).origin === window.location.origin;
}

window.fetch = function(input, opciones = {}) {
    const method = opciones.method || (typeof input !== "string" && input.method) || "GET";

    if (csrfToken && esMetodoProtegido(method) && esUrlMismoOrigen(input)) {
        const headers = new Headers(opciones.headers || {});
        headers.set("X-CSRF-Token", csrfToken);
        opciones = {
            ...opciones,
            headers
        };
    }

    return fetchOriginal(input, opciones);
};

function mostrarLogin(loginBox, contenido) {
    loginBox.style.display = "flex";
    contenido.style.display = "none";
}

function mostrarContenido(loginBox, contenido) {
    loginBox.style.display = "none";
    contenido.style.display = "block";
}

function rutaApi(archivo) {
    const partesRuta = window.location.pathname.split("/").filter(Boolean);
    const indiceHtml = partesRuta.indexOf("html");
    const baseProyecto = indiceHtml > 0 ? "/" + partesRuta.slice(0, indiceHtml).join("/") + "/" : "/";
    const base = window.location.hostname === "localhost" ? baseProyecto : "/";

    return base + "php/" + archivo;
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
        const respuesta = await fetch(rutaApi("sesion_actual.php"));
        const sesion = await respuesta.json();
        guardarCsrfToken(sesion.csrf_token);

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
        console.error("Error al verificar la sesión", error);
    }
}

export async function iniciarSesion(correo, contrasena, loginBox, contenido, mensaje) {
    const captchaInput = document.getElementById("captchaRespuesta");
    const datos = new FormData();
    datos.append("correo", correo.trim());
    datos.append("contrasena", contrasena.trim());
    datos.append("captcha_respuesta", captchaInput ? captchaInput.value.trim() : "");

    try {
        const respuesta = await fetch(rutaApi("login.php"), {
            method: "POST",
            body: datos
        });

        const resultado = await respuesta.json();
        guardarCsrfToken(resultado.csrf_token);

        if (resultado.error) {
            mensaje.textContent = resultado.mensaje;
            cargarCaptchaLogin();
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
        mensaje.textContent = "Error al iniciar sesión";
        console.error(error);
    }
}

export async function cargarCaptchaLogin() {
    const pregunta = document.getElementById("captchaPregunta");
    const respuesta = document.getElementById("captchaRespuesta");

    if (!pregunta || !respuesta) return;

    pregunta.textContent = "...";
    respuesta.value = "";

    try {
        const respuestaHttp = await fetch(rutaApi("captcha_login.php"));
        const resultado = await respuestaHttp.json();

        pregunta.textContent = resultado.error ? "Nueva pregunta" : resultado.pregunta;
    } catch (error) {
        pregunta.textContent = "Recarga";
        console.error("Error al cargar captcha", error);
    }
}

export async function solicitarRecuperacionPassword(correo, mensaje) {
    const datos = new FormData();
    datos.append("correo", correo.trim());

    try {
        const respuesta = await fetch(rutaApi("password_recuperar_solicitar.php"), {
            method: "POST",
            body: datos
        });
        const resultado = await respuesta.json();

        mensaje.textContent = resultado.mensaje || "Revisa tu correo para continuar.";
    } catch (error) {
        mensaje.textContent = "No se pudo enviar la solicitud de recuperacion.";
        console.error(error);
    }
}

export async function cerrarSesion(loginBox, contenido) {
    try {
        await fetch(rutaApi("logout.php"), {
            method: "POST"
        });
    } catch (error) {
        console.error("Error al cerrar sesión", error);
    }

    aplicarRol(null);
    mostrarLogin(loginBox, contenido);
}
