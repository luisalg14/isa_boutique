// ===============================
// DATOS BASE
// ===============================

let inventario = JSON.parse(localStorage.getItem("inventario")) || [];
let historial = JSON.parse(localStorage.getItem("historial")) || [];
let movimientos = JSON.parse(localStorage.getItem("movimientos")) || [];
let cambiosPrecio = JSON.parse(localStorage.getItem("cambiosPrecio")) || [];


// ===============================
// FUNCIONES DE APOYO
// ===============================

function guardarTodo() {
    localStorage.setItem("inventario", JSON.stringify(inventario));
    localStorage.setItem("historial", JSON.stringify(historial));
    localStorage.setItem("movimientos", JSON.stringify(movimientos));
    localStorage.setItem("cambiosPrecio", JSON.stringify(cambiosPrecio));
  }

function formatoPrecio(valor) {
    return "$" + Number(valor).toLocaleString("es-CO");
  }

function obtenerProductoPorId(id) {
    return inventario.find(function(producto) {
        return producto.id === id;
      });
    }

function obtenerCodigoProducto(producto) {
    return producto.codigo ? producto.codigo : "Sin código";
  }

function productoTieneHistorial(producto) {
  return historial.some(function(registro) {
    return (
      registro.codigo === obtenerCodigoProducto(producto) ||
      (
        registro.producto === producto.nombre &&
        registro.marca === producto.marca
      )
    );
  });
}
function registrarMovimiento(tipo, producto, cantidad, detalle) {
    movimientos.push({
        fecha: new Date().toISOString(),
        tipo: tipo,
        codigo: obtenerCodigoProducto(producto),
        producto: producto.nombre,
        marca: producto.marca,
        cantidad: cantidad,
        detalle: detalle
      });
    }


// ===============================
// AGREGAR PRODUCTO
// ===============================

function agregarProducto() {
    const codigo = document.getElementById("inp-codigo").value.trim().toUpperCase();
    const nombre = document.getElementById("inp-nombre").value.trim();
    const marca = document.getElementById("inp-marca").value.trim();
    const tipo = document.getElementById("inp-tipo").value;
    const cantidad = document.getElementById("inp-cantidad").value;
    const precio = document.getElementById("inp-precio").value;
    const imagen = document.getElementById("inp-img").files[0];
    const mensajeError = document.getElementById("error-msg");

    if (!codigo || !nombre || !marca || !tipo || !cantidad || !precio || !imagen) {
        mensajeError.style.display = "block";
        return;
      }

    const codigoExiste = inventario.some(function(producto) {
        return producto.codigo === codigo;
      });

    if (codigoExiste) {
        alert("Ya existe un producto con ese código.");
        return;
      }

    mensajeError.style.display = "none";

    const lector = new FileReader();

    lector.onload = function(evento) {
        const nuevoProducto = {
            id: Date.now(),
            codigo: codigo,
            nombre: nombre,
            marca: marca,
            tipo: tipo,
            cantidad: parseInt(cantidad),
            precio: parseFloat(precio),
            img: evento.target.result,
            activo: true
          };

       inventario.push(nuevoProducto);
       
       registrarMovimiento(
        "Ingreso inicial",
        nuevoProducto,
        nuevoProducto.cantidad,
        "Producto registrado por primera vez"
      );
      
      guardarTodo();
      mostrarInventario();
      mostrarHistorial();
      mostrarMovimientos();

        alert("Producto agregado correctamente");

        limpiarFormularioProducto();
      };

    lector.readAsDataURL(imagen);
  }

function limpiarFormularioProducto() {
    document.getElementById("inp-codigo").value = "";
    document.getElementById("inp-nombre").value = "";
    document.getElementById("inp-marca").value = "";
    document.getElementById("inp-tipo").value = "";
    document.getElementById("inp-cantidad").value = "";
    document.getElementById("inp-precio").value = "";
    document.getElementById("inp-img").value = "";
  }


// ===============================
// MOSTRAR INVENTARIO
// ===============================

function mostrarInventario() {
    const tabla = document.getElementById("tablaInventario");

    if (!tabla) return;

    const buscador = document.getElementById("buscador");
    const textoBusqueda = buscador ? buscador.value.toLowerCase() : "";

    tabla.innerHTML = "";

    const productosFiltrados = inventario.filter(function(producto) {
        const codigo = producto.codigo ? producto.codigo.toLowerCase() : "";
        const nombre = producto.nombre ? producto.nombre.toLowerCase() : "";
        const marca = producto.marca ? producto.marca.toLowerCase() : "";
        const tipo = producto.tipo ? producto.tipo.toLowerCase() : "";
        const precio = producto.precio ? String(producto.precio) : "";
        const activoTexto = producto.activo === false ? "inactivo" : "activo";

        return (
            codigo.includes(textoBusqueda) ||
            nombre.includes(textoBusqueda) ||
            marca.includes(textoBusqueda) ||
            tipo.includes(textoBusqueda) ||
            precio.includes(textoBusqueda)||
            activoTexto.includes(textoBusqueda)
          );
        });

    if (productosFiltrados.length === 0) {
        tabla.innerHTML = `
            <tr>
                <td colspan="8">No se encontraron productos</td>
            </tr>
            `;
            return;
          }

    productosFiltrados.forEach(function(producto) {
      let estadoTexto = "";
      let claseEstado = "";
      
      if (producto.activo === false) {
        estadoTexto = "Inactivo";
        claseEstado = "stock-bajo";
      } else if (producto.cantidad === 0) {
        estadoTexto = "Agotado";
        claseEstado = "stock-bajo";
      } else if (producto.cantidad <= 3) {
        estadoTexto = "Bajo";
        claseEstado = "stock-bajo";
      } else if (producto.cantidad <= 7) {
        estadoTexto = "Medio";
        claseEstado = "stock-medio";
      } else {
        estadoTexto = "Disponible";
        claseEstado = "stock-alto";
      }
      
      tabla.innerHTML += `
        <tr>
          <td>${obtenerCodigoProducto(producto)}</td>
          <td>${producto.nombre}</td>
          <td>${producto.marca}</td>
          <td>${producto.tipo}</td>
          <td>${formatoPrecio(producto.precio)}</td>
          <td>${producto.cantidad}</td>
          <td class="${claseEstado}">${estadoTexto}</td>
         
          <td>
          <button onclick="sumarStock(${producto.id})">+</button>
          <button onclick="restarStock(${producto.id})">-</button>
          <button onclick="editarProducto(${producto.id})">Editar</button>
          <button onclick="eliminarProducto(${producto.id})">
          ${producto.activo === false ? "Eliminar definitivo" : "Eliminar"}
          </button>
          <button onclick="cambiarEstadoProducto(${producto.id})">
          ${producto.activo === false ? "Activar" : "Desactivar"}
          </button>
          </td>
        </tr>
      `;
    });
  }

// ===============================
// EDITAR PRODUCTO
// ===============================

function editarProducto(id) {
  const producto = obtenerProductoPorId(id);
  
  if (!producto) {
    alert("Producto no encontrado.");
    return;
  }
  
  const nuevoCodigo = prompt("Código del producto:", producto.codigo || "");
  
  if (nuevoCodigo === null) return;
  
  const codigoLimpio = nuevoCodigo.trim().toUpperCase();

  if (codigoLimpio === "") {
    alert("El código no puede quedar vacío.");
    return;
  }

  const codigoExiste = inventario.some(function(item) {
    return item.codigo === codigoLimpio && item.id !== producto.id;
  });

  if (codigoExiste) {
    alert("Ya existe otro producto con ese código.");
    return;
  }

  const nuevoNombre = prompt("Nombre del producto:", producto.nombre);

  if (nuevoNombre === null) return;

  if (nuevoNombre.trim() === "") {
    alert("El nombre no puede quedar vacío.");
    return;
  }

  const nuevaMarca = prompt("Marca:", producto.marca);

  if (nuevaMarca === null) return;

  if (nuevaMarca.trim() === "") {
    alert("La marca no puede quedar vacía.");
    return;
  }

  const nuevoTipo = prompt(
    "Categoría: vestidos, blusas, conjuntos, shorts, jeans, bodys, faldas o accesorios",
    producto.tipo
  );

  if (nuevoTipo === null) return;

  const tipoLimpio = nuevoTipo.trim().toLowerCase();
  
  const categoriasValidas = [
    "vestidos",
    "blusas",
    "conjuntos",
    "shorts",
    "jeans",
    "bodys",
    "faldas",
    "accesorios"
  ];
  
  if (!categoriasValidas.includes(tipoLimpio)) {
    alert("Categoría no válida. Usa: vestidos, blusas, conjuntos, shorts, jeans, bodys, faldas o accesorios.");
    return;
  }

  const nuevaCantidadTexto = prompt("Cantidad disponible:", producto.cantidad);

  if (nuevaCantidadTexto === null) return;
    
  const nuevaCantidad = parseInt(nuevaCantidadTexto);

  if (isNaN(nuevaCantidad) || nuevaCantidad < 0) {
    alert("La cantidad debe ser válida.");
    return;
  }

  const nuevoPrecioTexto = prompt("Precio:", producto.precio);

  if (nuevoPrecioTexto === null) return;

  const nuevoPrecio = parseFloat(nuevoPrecioTexto);
    
  if (isNaN(nuevoPrecio) || nuevoPrecio <= 0) {
    alert("El precio debe ser válido.");
    return;
  }

  const precioAnterior = Number(producto.precio);
  
  producto.codigo = codigoLimpio;
  producto.nombre = nuevoNombre.trim();
  producto.marca = nuevaMarca.trim();
  producto.tipo = tipoLimpio;
  producto.cantidad = nuevaCantidad;
  producto.precio = nuevoPrecio;

  if (precioAnterior !== nuevoPrecio) {
    cambiosPrecio.push({
      fecha: new Date().toISOString(),
      codigo: obtenerCodigoProducto(producto),
      producto: producto.nombre,
      marca: producto.marca,
      precioAnterior: precioAnterior,
      precioNuevo: nuevoPrecio,
      detalle: "Precio modificado desde el panel administrador"
    });
    
    registrarMovimiento(
      "Cambio de precio",
        producto,
        0,
        "Precio anterior: " + formatoPrecio(precioAnterior) +
        " | Precio nuevo: " + formatoPrecio(nuevoPrecio)
      );
    }
    
    guardarTodo();
    mostrarInventario();
    mostrarMovimientos();
    mostrarCambiosPrecio();
    recargarCategorias();

    alert("Producto actualizado correctamente.");
  }


// ===============================
// MODIFICAR STOCK
// ===============================

function sumarStock(id) {
    const producto = obtenerProductoPorId(id);

    if (!producto) return;

    producto.cantidad++;

    registrarMovimiento(
        "Ingreso de stock",
        producto,
        1,
        "Se agregó una unidad al inventario"
    );

    guardarTodo();
    mostrarInventario();
    mostrarMovimientos();
    recargarCategorias();
}

function restarStock(id) {
    const producto = obtenerProductoPorId(id);

    if (!producto) return;

    if (producto.cantidad > 0) {
        producto.cantidad--;

        registrarMovimiento(
            "Ajuste de stock",
            producto,
            1,
            "Se descontó una unidad manualmente"
        );
    }

    guardarTodo();
    mostrarInventario();
    mostrarMovimientos();
    recargarCategorias();
}

function eliminarProducto(id) {
  const producto = obtenerProductoPorId(id);

  if (!producto) return;

  const tieneHistorial = productoTieneHistorial(producto);
  
  if (tieneHistorial && producto.activo !== false) {
    const confirmarDesactivar = confirm(
      "Este producto tiene ventas o devoluciones asociadas.\n" +
      "No se recomienda eliminarlo porque afectaría el historial.\n\n" +
      "¿Deseas desactivarlo?"
    );
    
    if (!confirmarDesactivar) return;
    
    producto.activo = false;
    
    registrarMovimiento(
      "Desactivación",
      producto,
      producto.cantidad,
      "Producto desactivado porque tiene historial asociado"
    );
    
    guardarTodo();
    mostrarInventario();
    mostrarMovimientos();
    recargarCategorias();
    
    alert("Producto desactivado correctamente.");
    return;
  }
  
  if (tieneHistorial && producto.activo === false) {
    alert("Este producto tiene historial asociado. No se puede eliminar definitivamente.");
    return;
  }
  
  const confirmar = confirm("¿Deseas eliminar definitivamente este producto?");

  if (!confirmar) return;

  registrarMovimiento(
    "Eliminación",
    producto,
    producto.cantidad,
    "Producto eliminado definitivamente del inventario"
  );

  inventario = inventario.filter(function(item) {
    return item.id !== id;
  });
  
  guardarTodo();
  mostrarInventario();
  mostrarMovimientos();
  recargarCategorias();
  
  alert("Producto eliminado correctamente.");
}

function cambiarEstadoProducto(id) {
  const producto = obtenerProductoPorId(id);
    
  if (!producto) return;

  if (producto.activo === false) {
    producto.activo = true;

    registrarMovimiento(
      "Activación",
      producto,
      producto.cantidad,
      "Producto activado nuevamente"
    );

    alert("Producto activado correctamente.");
  } else {
      const confirmar = confirm("¿Deseas desactivar este producto?");
      
      if (!confirmar) return;

      producto.activo = false;

      registrarMovimiento(
        "Desactivación",
        producto,
        producto.cantidad,
        "Producto desactivado manualmente"
      );
      
      alert("Producto desactivado correctamente.");
    }

    guardarTodo();
    mostrarInventario();
    mostrarMovimientos();
    recargarCategorias();
}


// ===============================
// COMPRAR PRODUCTO
// ===============================

function comprarPorId(id) {
    const producto = obtenerProductoPorId(id);

    if (!producto) {
        alert("Producto no encontrado");
        return;
    }

    comprarProducto(producto);
}

function comprarProducto(producto) {
    if (!producto) {
        alert("Producto no encontrado");
        return;
    }

    if (producto.activo === false) {
        alert("Este producto está inactivo y no se puede vender.");
        return;
    }

    if (producto.cantidad === 0) {
        alert("Producto agotado");
        return;
    }

    const cantidadTexto = prompt("¿Cuántas unidades desea comprar?");

    if (cantidadTexto === null) return;

    const cantidad = parseInt(cantidadTexto);

    if (isNaN(cantidad) || cantidad <= 0) {
        alert("Ingresa una cantidad válida");
        return;
    }

    if (cantidad > producto.cantidad) {
        alert("Stock insuficiente. Solo hay " + producto.cantidad + " unidades disponibles.");
        return;
    }

    const cliente = prompt("Nombre del cliente:");

    if (cliente === null || cliente.trim() === "") {
        alert("Debes ingresar el nombre del cliente");
        return;
    }

    const telefono = prompt("Teléfono del cliente:");

    if (telefono === null || telefono.trim() === "") {
        alert("Debes ingresar el teléfono del cliente");
        return;
    }

    const medioPago = prompt(
        "Selecciona medio de pago:\n" +
        "1. Efectivo\n" +
        "2. Transferencia\n" +
        "3. Tarjeta débito\n" +
        "4. Tarjeta crédito"
    );

    if (medioPago === null) return;

    let medioTexto = "";

    if (medioPago === "1") {
        medioTexto = "Efectivo";
    } else if (medioPago === "2") {
        medioTexto = "Transferencia";
    } else if (medioPago === "3") {
        medioTexto = "Tarjeta débito";
    } else if (medioPago === "4") {
        medioTexto = "Tarjeta crédito";
    } else {
        alert("Medio de pago no válido");
        return;
    }

    const total = producto.precio * cantidad;

    producto.cantidad -= cantidad;

    historial.push({
        cliente: cliente.trim(),
        telefono: telefono.trim(),
        tipo: "Venta",
        codigo: obtenerCodigoProducto(producto),
        producto: producto.nombre,
        marca: producto.marca,
        cantidad: cantidad,
        precio: producto.precio,
        total: total,
        medioPago: medioTexto,
        fecha: new Date().toISOString()
    });

    registrarMovimiento(
        "Venta",
        producto,
        cantidad,
        "Venta realizada a " + cliente.trim()
    );

    guardarTodo();
    mostrarInventario();
    mostrarHistorial();
    mostrarMovimientos();
    recargarCategorias();

    if (producto.cantidad <= 3 && producto.cantidad > 0) {
        alert("Venta realizada. Quedan pocas unidades de " + producto.nombre);
    } else {
        alert("Venta realizada correctamente");
    }
}

function comprar(nombreProducto) {
    const producto = inventario.find(function(item) {
        return item.nombre === nombreProducto && item.activo !== false;
    });

    if (!producto) {
        alert("Producto no encontrado o inactivo");
        return;
    }

    comprarProducto(producto);
}

// ===============================
// MOSTRAR HISTORIAL
// ===============================

function mostrarHistorial() {
    const tabla = document.getElementById("tablaHistorial");
    
    if (!tabla) return;
    
    tabla.innerHTML = "";
    
    if (historial.length === 0) {
        tabla.innerHTML = `
            <tr>
                <td colspan="12">No hay ventas aún</td>
            </tr>
        `;
    } else {

        historial.forEach(function(registro) {
          let acciones = "";
          
          if (registro.tipo === "Venta") {
            acciones = `
            <button onclick="registrarDevolucion('${registro.fecha}')">Devolución</button>
            <button onclick="eliminarRegistro('${registro.fecha}')">Eliminar</button>
            `;
          } else {
            acciones = `
            <button onclick="eliminarRegistro('${registro.fecha}')">Eliminar</button>
            `;
          }
          
          tabla.innerHTML += `
          <tr>
            <td>${registro.cliente || "Sin nombre"}</td>
            <td>${registro.telefono || "Sin teléfono"}</td>
            <td>${registro.tipo}</td>
            <td>${registro.codigo || "Sin código"}</td>
            <td>${registro.producto}</td>
            <td>${registro.marca || "Sin marca"}</td>
            <td>${registro.cantidad}</td>
            <td>${formatoPrecio(registro.precio)}</td>
            <td>${formatoPrecio(registro.total)}</td>
            <td>${registro.medioPago || "No registrado"}</td>
            <td>${new Date(registro.fecha).toLocaleString()}</td>
            <td>${acciones}</td>
        </tr>
        `;
      });
    }

    mostrarTotalVentas();
    mostrarProductoMasVendido();
    mostrarReportes();
}

// ===============================
// MOSTRAR MOVIMIENTOS
// ===============================

function mostrarMovimientos() {
    const tabla = document.getElementById("tablaMovimientos");

    if (!tabla) return;

    tabla.innerHTML = "";

    if (movimientos.length === 0) {
        tabla.innerHTML = `
            <tr>
                <td colspan="7">No hay movimientos registrados</td>
            </tr>
        `;
        return;
    }

    movimientos.forEach(function(movimiento) {
        tabla.innerHTML += `
            <tr>
                <td>${new Date(movimiento.fecha).toLocaleString()}</td>
                <td>${movimiento.tipo}</td>
                <td>${movimiento.codigo || "Sin código"}</td>
                <td>${movimiento.producto}</td>
                <td>${movimiento.marca || "Sin marca"}</td>
                <td>${movimiento.cantidad}</td>
                <td>${movimiento.detalle}</td>
            </tr>
        `;
    });
}

// ===============================
// ELIMINAR MOVIMIENTO
// ===============================

function eliminarMovimiento(index) {
  const confirmar = confirm("¿Deseas eliminar este movimiento del inventario?");
  
  if (!confirmar) return;
  movimientos.splice(index, 1);
  
  guardarTodo();
  mostrarMovimientos();
  
  alert("Movimiento eliminado correctamente.");
}

// ===============================
// LIMPIAR MOVIMIENTOS POR FECHA
// ===============================

function eliminarMovimientosPorFecha() {
    const inputFecha = document.getElementById("fechaMovimientoEliminar");

    if (!inputFecha || inputFecha.value === "") {
        alert("Selecciona una fecha.");
        return;
    }

    const fechaSeleccionada = inputFecha.value;

    const confirmar = confirm(
        "¿Deseas eliminar los movimientos del día " + fechaSeleccionada + "?"
    );

    if (!confirmar) return;

    movimientos = movimientos.filter(function(movimiento) {
        const fechaMovimiento = new Date(movimiento.fecha)
            .toISOString()
            .slice(0, 10);

        return fechaMovimiento !== fechaSeleccionada;
    });

    guardarTodo();
    mostrarMovimientos();

    alert("Movimientos de esa fecha eliminados correctamente.");
}


// ===============================
// LIMPIAR MOVIMIENTOS ANTIGUOS
// ===============================

function limpiarMovimientosAntiguos() {
  const hoy = new Date();
  
  const confirmar = confirm(
    "¿Deseas eliminar los movimientos con más de 30 días?"
  );
  
  if (!confirmar) return;
  
  movimientos = movimientos.filter(function(movimiento) {
    const fechaMovimiento = new Date(movimiento.fecha);
    const diferenciaDias = (hoy - fechaMovimiento) / (1000 * 60 * 60 * 24);
    
    return diferenciaDias <= 30;
  });
  
  guardarTodo();
  mostrarMovimientos();
  
  alert("Movimientos antiguos eliminados correctamente.");
}

// ===============================
// DEVOLUCIONES
// ===============================

function buscarProductoPorRegistro(registro) {
    return inventario.find(function(producto) {
        return (
            producto.codigo === registro.codigo ||
            (
                producto.nombre === registro.producto &&
                producto.marca === registro.marca
            )
        );
    });
}

function calcularCantidadDevuelta(fechaVenta) {
    let totalDevuelto = 0;

    historial.forEach(function(registro) {
        if (registro.tipo === "Devolución" && registro.ventaOriginal === fechaVenta) {
            totalDevuelto += Number(registro.cantidad);
        }
    });

    return totalDevuelto;
}

function registrarDevolucion(fechaVenta) {
    const venta = historial.find(function(registro) {
        return registro.fecha === fechaVenta && registro.tipo === "Venta";
    });

    if (!venta) {
        alert("No se encontró la venta.");
        return;
    }

    const cantidadVendida = Number(venta.cantidad);
    const cantidadDevuelta = calcularCantidadDevuelta(fechaVenta);
    const cantidadDisponible = cantidadVendida - cantidadDevuelta;

    if (cantidadDisponible <= 0) {
        alert("Esta venta ya fue devuelta completamente.");
        return;
    }

    const cantidadTexto = prompt(
        "¿Cuántas unidades deseas devolver?\n" +
        "Disponibles para devolución: " + cantidadDisponible
    );

    if (cantidadTexto === null) return;

    const cantidad = parseInt(cantidadTexto);

    if (isNaN(cantidad) || cantidad <= 0) {
        alert("Ingresa una cantidad válida.");
        return;
    }

    if (cantidad > cantidadDisponible) {
        alert("No puedes devolver más unidades de las vendidas.");
        return;
    }

    const producto = buscarProductoPorRegistro(venta);

    if (!producto) {
        alert("No se encontró el producto en inventario. No se puede ajustar el stock.");
        return;
    }

    const motivo = prompt("Motivo de la devolución:");

    if (motivo === null || motivo.trim() === "") {
        alert("Debes ingresar un motivo.");
        return;
    }

    producto.cantidad += cantidad;

    historial.push({
        cliente: venta.cliente,
        telefono: venta.telefono,
        tipo: "Devolución",
        codigo: venta.codigo,
        producto: venta.producto,
        marca: venta.marca,
        cantidad: cantidad,
        precio: venta.precio,
        total: -Math.abs(Number(venta.precio) * cantidad),
        medioPago: venta.medioPago,
        fecha: new Date().toISOString(),
        ventaOriginal: venta.fecha,
        motivo: motivo.trim()
    });

    registrarMovimiento(
        "Devolución",
        producto,
        cantidad,
        "Devolución de venta. Motivo: " + motivo.trim()
    );

    guardarTodo();
    mostrarInventario();
    mostrarHistorial();
    mostrarMovimientos();
    recargarCategorias();

    alert("Devolución registrada correctamente.");
}

// ===============================
// REPORTES
// ===============================

function calcularTotalVentas() {
    let total = 0;

    historial.forEach(function(registro) {
        total += Number(registro.total);
    });

    return total;
}

function mostrarTotalVentas() {
    const totalVentas = document.getElementById("totalVentas");

    if (!totalVentas) return;

    const total = calcularTotalVentas();

    totalVentas.innerText = formatoPrecio(total);
}

function calcularProductoMasVendido() {
    const contador = {};

    historial.forEach(function(registro) {
        if (!registro.producto) return;

        if (!contador[registro.producto]) {
            contador[registro.producto] = 0;
        }

        if (registro.tipo === "Venta") {
            contador[registro.producto] += Number(registro.cantidad);
        }

        if (registro.tipo === "Devolución") {
            contador[registro.producto] -= Number(registro.cantidad);
        }
    });

    let productoTop = "Sin ventas";
    let cantidadTop = 0;

    Object.keys(contador).forEach(function(producto) {
        if (contador[producto] > cantidadTop) {
            productoTop = producto;
            cantidadTop = contador[producto];
        }
    });

    return {
        nombre: productoTop,
        cantidad: cantidadTop
    };
}

function mostrarProductoMasVendido() {
    const productoTopElemento = document.getElementById("productoTop");

    if (!productoTopElemento) return;

    const productoTop = calcularProductoMasVendido();

    if (productoTop.cantidad > 0) {
        productoTopElemento.innerText = productoTop.nombre + " (" + productoTop.cantidad + ")";
    } else {
        productoTopElemento.innerText = "Sin ventas";
    }
}

// ===============================
// REPORTES AUTOMÁTICOS
// ===============================

function obtenerReportePorPeriodo(periodo) {
    const hoy = new Date();

    let ventasBrutas = 0;
    let devoluciones = 0;

    historial.forEach(function(registro) {
        const fechaRegistro = new Date(registro.fecha);

        let pertenece = false;

        if (periodo === "hoy") {
            pertenece =
                fechaRegistro.getDate() === hoy.getDate() &&
                fechaRegistro.getMonth() === hoy.getMonth() &&
                fechaRegistro.getFullYear() === hoy.getFullYear();
        }

        if (periodo === "mes") {
            pertenece =
                fechaRegistro.getMonth() === hoy.getMonth() &&
                fechaRegistro.getFullYear() === hoy.getFullYear();
        }

        if (pertenece && registro.tipo === "Venta") {
            ventasBrutas += Number(registro.total);
        }

        if (pertenece && registro.tipo === "Devolución") {
            devoluciones += Math.abs(Number(registro.total));
        }
    });

    return {
        ventasBrutas: ventasBrutas,
        devoluciones: devoluciones,
        neto: ventasBrutas - devoluciones
    };
}

function calcularProductoMenorRotacion() {
    if (inventario.length === 0) {
        return {
            nombre: "Sin productos",
            cantidad: 0
        };
    }

    const contador = {};

    inventario.forEach(function(producto) {
        contador[producto.nombre] = 0;
    });

    historial.forEach(function(registro) {
        if (!registro.producto) return;

        if (!contador[registro.producto]) {
            contador[registro.producto] = 0;
        }

        if (registro.tipo === "Venta") {
            contador[registro.producto] += Number(registro.cantidad);
        }

        if (registro.tipo === "Devolución") {
            contador[registro.producto] -= Number(registro.cantidad);
        }
    });

    let productoMenor = "Sin productos";
    let cantidadMenor = null;

    Object.keys(contador).forEach(function(producto) {
        if (cantidadMenor === null || contador[producto] < cantidadMenor) {
            productoMenor = producto;
            cantidadMenor = contador[producto];
        }
    });

    return {
        nombre: productoMenor,
        cantidad: cantidadMenor
    };
}

function mostrarReportes() {
    const reporteHoy = obtenerReportePorPeriodo("hoy");
    const reporteMes = obtenerReportePorPeriodo("mes");

    const ventasHoy = document.getElementById("ventasHoy");
    const devolucionesHoy = document.getElementById("devolucionesHoy");
    const netoHoy = document.getElementById("netoHoy");

    const ventasMes = document.getElementById("ventasMes");
    const devolucionesMes = document.getElementById("devolucionesMes");
    const netoMes = document.getElementById("netoMes");

    const menorRotacion = document.getElementById("menorRotacion");

    if (ventasHoy) {
        ventasHoy.innerText = formatoPrecio(reporteHoy.ventasBrutas);
    }

    if (devolucionesHoy) {
        devolucionesHoy.innerText = formatoPrecio(reporteHoy.devoluciones);
    }

    if (netoHoy) {
        netoHoy.innerText = formatoPrecio(reporteHoy.neto);
    }

    if (ventasMes) {
        ventasMes.innerText = formatoPrecio(reporteMes.ventasBrutas);
    }

    if (devolucionesMes) {
        devolucionesMes.innerText = formatoPrecio(reporteMes.devoluciones);
    }

    if (netoMes) {
        netoMes.innerText = formatoPrecio(reporteMes.neto);
    }

    if (menorRotacion) {
        const menor = calcularProductoMenorRotacion();
        menorRotacion.innerText = menor.nombre + " (" + menor.cantidad + ")";
    }
}

// ===============================
// MOSTRAR CAMBIOS DE PRECIO
// ===============================

function mostrarCambiosPrecio() {
  const tabla = document.getElementById("tablaCambiosPrecio");

  if (!tabla) return;   
  tabla.innerHTML = "";

  if (cambiosPrecio.length === 0) {
    tabla.innerHTML = `
      <tr>
         <td colspan="7">No hay cambios de precio registrados</td>
      </tr>
    `;
      return;
  }
  
  cambiosPrecio.forEach(function(cambio) {    
    tabla.innerHTML += `        
    <tr>
      <td>${new Date(cambio.fecha).toLocaleString()}</td>
      <td>${cambio.codigo || "Sin código"}</td>
      <td>${cambio.producto}</td>
      <td>${cambio.marca || "Sin marca"}</td>
      <td>${formatoPrecio(cambio.precioAnterior)}</td>
      <td>${formatoPrecio(cambio.precioNuevo)}</td>
      <td>${cambio.detalle}</td>
    </tr>
    `;
  });
}


// ===============================
// ELIMINAR REGISTROS DEL HISTORIAL
// ===============================

function eliminarRegistro(fecha) {
    const confirmar = confirm("¿Deseas eliminar este registro?");

    if (!confirmar) return;

    historial = historial.filter(function(registro) {
        return registro.fecha !== fecha;
    });

    guardarTodo();
    mostrarHistorial();
}

function limpiarHistorialAntiguo() {
    const hoy = new Date();

    historial = historial.filter(function(registro) {
        const fechaRegistro = new Date(registro.fecha);
        const diferenciaDias = (hoy - fechaRegistro) / (1000 * 60 * 60 * 24);

        return diferenciaDias <= 30;
    });

    guardarTodo();
    mostrarHistorial();

    alert("Historial antiguo eliminado");
}


// ===============================
// RECARGAR CATEGORÍAS
// ===============================

function recargarCategorias() {
    if (typeof cargarVestidos === "function") {
        cargarVestidos();
    }

    if (typeof cargarBlusas === "function") {
        cargarBlusas();
    }

    if (typeof cargarConjuntos === "function") {
        cargarConjuntos();
    }

    if (typeof cargarProductos === "function") {
        cargarProductos();
    }
}


// ===============================
// BUSCADOR
// ===============================

const buscadorInput = document.getElementById("buscador");

if (buscadorInput) {
    buscadorInput.addEventListener("input", mostrarInventario);
}


// ===============================
// INICIO
// ===============================

mostrarInventario();
mostrarHistorial();
mostrarMovimientos();
mostrarCambiosPrecio();
mostrarReportes();