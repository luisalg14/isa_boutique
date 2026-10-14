        function rutaApiRestablecer(archivo) {
            return new URL("../php/" + archivo, window.location.href).href;
        }

        async function restablecerPassword() {
            const mensaje = document.getElementById("mensaje");
            const token = new URLSearchParams(window.location.search).get("token") || "";
            const datos = new FormData();

            datos.append("token", token);
            datos.append("password_nueva", document.getElementById("passwordNueva").value);
            datos.append("password_confirmar", document.getElementById("passwordConfirmar").value);

            try {
                const respuesta = await fetch(rutaApiRestablecer("password_recuperar_confirmar.php"), {
                    method: "POST",
                    body: datos
                });
                const resultado = await respuesta.json();

                mensaje.textContent = resultado.mensaje;

                if (!resultado.error) {
                    document.getElementById("passwordNueva").value = "";
                    document.getElementById("passwordConfirmar").value = "";
                }
            } catch (error) {
                mensaje.textContent = "No se pudo actualizar la contrasena.";
                console.error(error);
            }
        }

        document.getElementById("btnRestablecer").addEventListener("click", restablecerPassword);
        document.getElementById("passwordConfirmar").addEventListener("keydown", function(evento) {
            if (evento.key === "Enter") {
                restablecerPassword();
            }
        });
    
