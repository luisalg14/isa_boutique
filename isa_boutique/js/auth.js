// auth.js
//ARROW FUNCTION
// En vez de escribir function verificarSesion() 
// lo escribimos como const verificarSesion = () => {}.
export const verificarSesion = (loginBox, contenido) => {
    const estado = localStorage.getItem("auth");
    if (estado === "true") {
        loginBox.style.display = "none";
        contenido.style.display = "block";
    }
};

export const iniciarSesion = (input, loginBox, contenido, mensaje) => {
    //DESTRUCTURING 
    //  creamos un objeto llamado datos que tiene la clave 
    // y la contraseña, y luego con const { clave, password }
    //  = datos extraemos esos valores en una sola línea en vez
    //  de escribir datos.clave y datos.password por separado.
    const datos = {
        clave: input,
        password: "1234"
    };

    const {  clave, password } = datos;

    if (clave === password) {
        localStorage.setItem("auth", "true");
        loginBox.style.display = "none";
        contenido.style.display = "block";
        mensaje.textContent = "";
    } else {
        mensaje.textContent = "Contraseña incorrecta";
    }
};