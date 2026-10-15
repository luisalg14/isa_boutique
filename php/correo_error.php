<?php

// El proveedor confirmo que NO acepto el mensaje. Los fallos ambiguos no usan esta clase.
class CorreoNoAceptadoException extends RuntimeException {
    private $reintentable;
    public function __construct($mensaje, $reintentable = false) {
        parent::__construct($mensaje);
        $this->reintentable = $reintentable;
    }
    public function esReintentable() { return $this->reintentable; }
}
