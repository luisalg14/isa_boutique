<?php

function obtener_configuracion_facturacion($conexion) {
    $configuracionBase = [
        "nombre_comercial" => "Isa Boutique",
        "razon_social" => "Isa Boutique",
        "nit" => "Pendiente por registrar",
        "regimen" => "Regimen no definido",
        "direccion" => "Direccion pendiente",
        "ciudad" => "Colombia",
        "telefono" => "Pendiente",
        "correo" => "contacto@isaboutique.com",
        "actividad_economica" => "Comercio al por menor de prendas de vestir",
        "nota_legal" => "Comprobante interno generado por el sistema Isa Boutique. No equivale a factura electronica DIAN hasta que el negocio cuente con habilitacion, resolucion de numeracion y proveedor tecnologico autorizado.",
        "tarifa_iva_general" => "19.00"
    ];

    try {
        $consultaExiste = $conexion->query("
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_name = 'configuracion_facturacion'
            ) AS existe
        ");

        if (!filter_var($consultaExiste->fetch()["existe"], FILTER_VALIDATE_BOOLEAN)) {
            return $configuracionBase;
        }

        $consulta = $conexion->query("
            SELECT
                nombre_comercial,
                razon_social,
                nit,
                regimen,
                direccion,
                ciudad,
                telefono,
                correo,
                actividad_economica,
                nota_legal,
                tarifa_iva_general
            FROM configuracion_facturacion
            WHERE id_configuracion_facturacion = 1
            LIMIT 1
        ");

        $configuracion = $consulta->fetch();

        if (!$configuracion) {
            return $configuracionBase;
        }

        return array_merge($configuracionBase, $configuracion);
    } catch (PDOException $e) {
        error_log("Error configuracion facturacion: " . $e->getMessage());
        return $configuracionBase;
    }
}

?>
