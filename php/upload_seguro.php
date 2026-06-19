<?php

function validar_y_guardar_imagen_producto($archivo, $indice = null) {
    $error = $indice === null ? ($archivo["error"] ?? UPLOAD_ERR_NO_FILE) : ($archivo["error"][$indice] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new Exception("No se pudo cargar una de las imagenes del producto.");
    }

    $tmpName = $indice === null ? ($archivo["tmp_name"] ?? "") : ($archivo["tmp_name"][$indice] ?? "");
    $nombreOriginal = $indice === null ? ($archivo["name"] ?? "") : ($archivo["name"][$indice] ?? "");
    $tamano = $indice === null ? intval($archivo["size"] ?? 0) : intval($archivo["size"][$indice] ?? 0);

    if (!is_uploaded_file($tmpName)) {
        throw new Exception("La imagen enviada no es valida.");
    }

    $tamanoMaximo = 4 * 1024 * 1024;
    if ($tamano <= 0 || $tamano > $tamanoMaximo) {
        throw new Exception("Cada imagen debe pesar maximo 4 MB.");
    }

    $extensionOriginal = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extensionesPermitidas = [
        "jpg" => "jpg",
        "jpeg" => "jpg",
        "png" => "png",
        "webp" => "webp",
        "gif" => "gif"
    ];

    if (!isset($extensionesPermitidas[$extensionOriginal])) {
        throw new Exception("Solo se permiten imagenes JPG, PNG, WebP o GIF.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    $mimesPermitidos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif"
    ];

    if (!isset($mimesPermitidos[$mime]) || $mimesPermitidos[$mime] !== $extensionesPermitidas[$extensionOriginal]) {
        throw new Exception("El archivo no coincide con un formato de imagen permitido.");
    }

    if (@getimagesize($tmpName) === false) {
        throw new Exception("El archivo no parece ser una imagen real.");
    }

    $carpetaDestino = dirname(__DIR__) . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "productos";

    if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0755, true)) {
        throw new Exception("No se pudo preparar la carpeta de imagenes.");
    }

    $nombreArchivo = "producto_" . bin2hex(random_bytes(16)) . "." . $mimesPermitidos[$mime];
    $rutaServidor = $carpetaDestino . DIRECTORY_SEPARATOR . $nombreArchivo;

    if (!move_uploaded_file($tmpName, $rutaServidor)) {
        throw new Exception("No se pudo guardar la imagen del producto.");
    }

    @chmod($rutaServidor, 0644);

    return "img/productos/" . $nombreArchivo;
}

function guardar_imagenes_producto_seguras($campoMultiple = "imagenes", $campoSimple = "imagen", $maximoImagenes = 8) {
    $imagenes = [];

    if (isset($_FILES[$campoMultiple]) && is_array($_FILES[$campoMultiple]["name"] ?? null)) {
        $total = count($_FILES[$campoMultiple]["name"]);

        if ($total > $maximoImagenes) {
            throw new Exception("Puedes subir maximo " . $maximoImagenes . " imagenes por producto.");
        }

        for ($indice = 0; $indice < $total; $indice++) {
            $ruta = validar_y_guardar_imagen_producto($_FILES[$campoMultiple], $indice);
            if ($ruta !== null) {
                $imagenes[] = $ruta;
            }
        }
    } elseif (isset($_FILES[$campoSimple])) {
        $ruta = validar_y_guardar_imagen_producto($_FILES[$campoSimple]);
        if ($ruta !== null) {
            $imagenes[] = $ruta;
        }
    }

    return $imagenes;
}

?>
