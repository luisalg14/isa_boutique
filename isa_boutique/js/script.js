//MODULO
//En el proyecto creamos un archivo auth.js que contiene toda la lógica 
// del login con export, y en el script.js lo traemos con import. 
// Esto hace el código más ordenado y fácil de entender.

//“Aquí utilicé arrow functions para definir funciones de manera más moderna y compacta, 
// especialmente en la lógica del login y en los eventos del sistema.”
import { verificarSesion, iniciarSesion } from './auth.js'; //<- usando arrow functions

document.addEventListener("DOMContentLoaded", () => { //<-

    //MANIPULACION DEL DOM
    //“Manipulé el DOM para obtener elementos HTML, 
    // responder a eventos y modificar dinámicamente el contenido de la página.”

    const loginBox   = document.getElementById("loginBox");
    const btnEntrar  = document.getElementById("btnEntrar");
    const contenido  = document.getElementById("contenido");
    const mensaje    = document.getElementById("mensaje");
    const claveInput = document.getElementById("clave");
    const btnCerrar = document.getElementById("btnCerrar");

    btnEntrar.addEventListener("click", () => {
        iniciarSesion(claveInput.value, loginBox, contenido, mensaje); //<-
    });

    btnCerrar.addEventListener("click", () =>{
        localStorage.removeItem("auth");
        contenido.style.display = "none";
        loginBox.style.display = "block";
    });
    

    verificarSesion(loginBox, contenido);
});