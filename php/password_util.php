<?php

function validar_password_seguro($password) {
    if (strlen($password) < 8) {
        return "La nueva contrasena debe tener minimo 8 caracteres";
    }

    if (!preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        return "La nueva contrasena debe incluir letras y numeros";
    }

    return "";
}

?>
