<?php

return [
    "app_env" => "production", // Usar local solo en desarrollo loopback con HTTP.
    "host" => "localhost",
    "port" => "5432",
    "database" => "nombre_base_datos",
    "user" => "usuario_base_datos",
    "password" => "contrasena_segura",
    "sslmode" => "",
    "options" => "",
    "mail" => [
        "provider" => "brevo",
        "api_key" => "",
        "host" => "smtp.gmail.com",
        "port" => 587,
        "username" => "correo_del_negocio@gmail.com",
        "password" => "app_password",
        "encryption" => "tls",
        "from_email" => "correo_del_negocio@gmail.com",
        "from_name" => "Isa Boutique",
        "timeout" => 6
    ]
];
