<?php
require_once "conexion.php";
require_once "auth_guard.php";
header("Content-Type: application/json; charset=UTF-8");
exigir_roles(["admin"]);
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") responder_json_error("Metodo no permitido",405);
responder_json_error("El historial comercial y sus facturas se conservan. No esta permitido borrarlos desde el panel.",409);
