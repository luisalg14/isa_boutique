<?php
require_once 'conexion.php';
require_once 'auth_guard.php';
require_once 'pedido_util.php';
header('Content-Type: application/json; charset=UTF-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') {header('Allow: POST');responder_json_error('Metodo no permitido',405);}
try {
    exigir_origen_mismo_sitio();
    $usuario=usuario_actual();
    if($usuario) $usuario=exigir_roles(['admin','vendedor']);
    $resultado=registrar_pedido_carrito($conexion,$_POST,$_POST['clave_pedido']??'', $usuario?(int)$usuario['id_usuario']:null);
    echo json_encode($resultado,JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    if($e instanceof DomainException) {
        http_response_code(409);
        echo json_encode(['error'=>true,'mensaje'=>$e->getMessage(),'conservar_clave'=>$e instanceof PedidoConflictoException],JSON_UNESCAPED_UNICODE);
        exit;
    }
    error_log('Error pedido_carrito_registrar: '.$e->getMessage());
    responder_json_error('No se pudo confirmar el pedido. Conserva el carrito y vuelve a intentar.',500);
}
