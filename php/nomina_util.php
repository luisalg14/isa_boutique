<?php

const PAGO_FIJO_TRABAJADOR = 2000000;

function validar_pago_fijo($tipo, $valor) {
    if ($tipo !== 'salario' || !is_numeric($valor) || (float) $valor !== (float) PAGO_FIJO_TRABAJADOR) {
        throw new DomainException('Por ahora solo se registran salarios fijos de $2.000.000');
    }
    return PAGO_FIJO_TRABAJADOR;
}
