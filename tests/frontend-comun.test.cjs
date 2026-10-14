const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function cargar(archivo, entorno = {}) {
    const contexto = vm.createContext(entorno);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'js', archivo), 'utf8'), contexto);
    return contexto;
}

test('las listas invalidas y el carrito corrupto no rompen el catalogo', () => {
    const contexto = cargar('tienda-comun.js', { localStorage: { getItem: () => '{invalido' } });
    for (const lector of ['leerImagenesProducto', 'leerTallas', 'leerColores']) {
        for (const valor of ['{}', 'null', '{invalido', '', undefined]) {
            assert.equal(contexto[lector](valor).length, 0);
        }
        assert.equal(contexto[lector]('[{"cantidad":2}]')[0].cantidad, 2);
    }
    assert.equal(contexto.leerCarritoGuardado().length, 0);
    contexto.localStorage.getItem = () => { throw new Error('Almacenamiento bloqueado'); };
    assert.equal(contexto.leerCarritoGuardado().length, 0);
});

test('la galeria prioriza la imagen principal y elimina rutas repetidas', () => {
    const contexto = cargar('tienda-comun.js');
    const producto = { imagenes: [
        { ruta: 'img/primera.png' },
        { ruta: 'img/principal.png', principal: true },
        { ruta: 'img/primera.png' }
    ] };
    assert.equal(contexto.imagenPrincipalProducto(producto), 'img/principal.png');
    assert.equal(contexto.imagenesProductoPublicas(producto).length, 2);
    assert.equal(contexto.rutaImagenProducto('img/principal.png'), '../img/principal.png');
});

test('los nombres y las rutas no permiten inyectar atributos HTML', () => {
    const contexto = cargar('tienda-comun.js');
    const html = contexto.miniaturasProducto({ nombre: '"><script>prueba</script>', imagenes: [
        { ruta: 'img/a.png" onerror="prueba' }, { ruta: 'img/b.png' }
    ] });
    assert.ok(!html.includes('<script>'));
    assert.ok(!html.includes('" onerror="'));
    assert.ok(html.includes('&quot;'));
    assert.ok(contexto.puntosColoresProducto({ color: 'rojo" onclick="prueba' }).includes('&quot;'));
});

function elemento() {
    return {
        dataset: {}, children: [], hidden: false, handlers: {},
        set innerHTML(valor) { this.children = []; this.markup = valor; },
        appendChild(hijo) { this.children.push(hijo); },
        addEventListener(nombre, accion) { this.handlers[nombre] = accion; }
    };
}

test('la paginacion cambia de pagina y corrige la pagina al reducir los datos', () => {
    const contexto = cargar('sistema-comun.js', { document: { createElement: elemento } });
    const tabla = { nextElementSibling: null, insertAdjacentElement(_, nuevo) { this.nextElementSibling = nuevo; } };
    const cuerpo = { closest: () => tabla };
    const estado = {};
    const paginas = contexto.crearPaginacionTablas(estado);
    const datos = Array.from({ length: 25 }, (_, indice) => indice + 1);
    let renderizados = 0;
    const renderizar = () => { renderizados++; };
    assert.equal(paginas.preparar(datos, 'ventas', 10, cuerpo, renderizar)[0], 1);
    const siguiente = tabla.nextElementSibling.children.at(-1);
    siguiente.handlers.click();
    assert.equal(paginas.obtener('ventas'), 2);
    assert.equal(renderizados, 1);
    assert.equal(paginas.preparar(datos, 'ventas', 10, cuerpo, renderizar)[0], 11);
    assert.equal(paginas.preparar(datos.slice(0, 2), 'ventas', 10, cuerpo, renderizar)[0], 1);
    assert.equal(paginas.obtener('ventas'), 1);
    assert.equal(tabla.nextElementSibling.hidden, true);
    paginas.ocultar('ventas', cuerpo);
    assert.equal(tabla.nextElementSibling.children.length, 0);
});

test('la factura compartida imprime con los formatos de cada panel', () => {
    let salida = '';
    const contexto = cargar('sistema-comun.js', {
        window: { open: () => ({ document: { open() {}, write(html) { salida += html; }, close() {} } }) },
        alert() { throw new Error('No deberia bloquearse la ventana'); }
    });
    contexto.imprimirFacturaComun({ numero: 'F-1', total: 1500 }, [], { nombre: 'Isa Boutique' }, {
        limpiarTexto: valor => String(valor ?? '').replaceAll('<', '&lt;'),
        formatoPrecio: valor => '$' + Number(valor || 0),
        etiquetaCanal: () => 'Canal del panel'
    });
    assert.ok(salida.includes('Isa Boutique'));
    assert.ok(salida.includes('Canal del panel'));
    assert.ok(salida.includes('$1500'));
});

test('agregar al carrito respeta el stock acumulado de la talla', () => {
    const campos = {
        compraCantidad: { value: '2' }, compraTalla: { value: 'M' },
        compraColor: { value: '', selectedOptions: [{ textContent: 'Rojo' }] },
        mensajeCompra: {}, cartCount: {}
    };
    let guardado;
    const contexto = cargar('tienda-comun.js', {
        carrito: [],
        productoSeleccionado: { id_producto: 1, nombre: 'Blusa', precio: 1000, tallas: [{ talla: 'M', cantidad: 2 }] },
        document: { getElementById: id => campos[id] },
        localStorage: { setItem(_, valor) { guardado = valor; } },
        cerrarModalCompra() {}, abrirCarrito() {}
    });
    contexto.agregarProductoAlCarrito();
    assert.equal(contexto.carrito[0].cantidad, 2);
    assert.equal(JSON.parse(guardado)[0].cantidad, 2);
    campos.compraCantidad.value = '1';
    contexto.agregarProductoAlCarrito();
    assert.equal(contexto.carrito[0].cantidad, 2);
    assert.match(campos.mensajeCompra.textContent, /suficientes unidades/);
});

test('finalizar la compra conserva el carrito si falla y lo limpia cuando se acepta', async () => {
    const campos = Object.fromEntries([
        'mensajeCarrito', 'carritoCliente', 'carritoTelefono', 'carritoCorreo',
        'carritoMedioPago', 'carritoEntrega', 'cartCount', 'listaCarrito',
        'datosClienteCarrito', 'btnComprarCarrito', 'totalCarrito'
    ].map(id => [id, { value: 'dato', style: {} }]));
    let falla = true;
    let recargas = 0;
    const contexto = cargar('tienda-comun.js', {
        FormData, carrito: [{ id_producto: 1, talla: 'M', cantidad: 1 }],
        document: { getElementById: id => campos[id] },
        localStorage: { setItem() {} }, cerrarCarrito() {}, alert() {},
        fetch: async (ruta, opciones) => {
            assert.equal(ruta, '../php/venta_registrar.php');
            assert.equal(opciones.body.get('canal_venta'), 'pagina_web');
            return { json: async () => ({ error: falla, mensaje: 'Stock insuficiente' }) };
        }
    });
    const recargar = async () => { recargas++; };
    await contexto.finalizarCompraCarritoComun(recargar);
    assert.equal(contexto.carrito.length, 1);
    assert.equal(recargas, 0);
    falla = false;
    await contexto.finalizarCompraCarritoComun(recargar);
    assert.equal(contexto.carrito.length, 0);
    assert.equal(recargas, 1);
});
