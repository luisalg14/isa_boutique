<?php

function normalizar_categoria($texto) {
    $texto = strtolower(trim($texto));
    $texto = preg_replace('/\s+/', '-', $texto);
    return preg_replace('/[^a-z0-9_-]/', '', $texto);
}
