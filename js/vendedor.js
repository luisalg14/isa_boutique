let productosVendedor = [];

function activarPanelVendedor(idPanel) {
    document.querySelectorAll(".admin-panel").forEach(function(panel) {
        panel.classList.toggle("activo", panel.id === idPanel);
    });

    document.querySelectorAll(".admin-tab").forEach(function(tab) {
        tab.classList.toggle("activo", tab.dataset.panelTarget === idPanel);
    });
}

function formatoPrecioVendedor(valor) {
    return "$" + Number(valor || 0).toLocaleString("es-CO");
}

function claseEstadoVendedor(producto) {
    if (producto.estado === "inactivo" || producto.estado === "agotado" || Number(producto.cantidad) <= 3) {
        return "stock-bajo";
    }

    if (Number(producto.cantidad) <= 7) {
        return "stock-medio";
    }

    return "stock-alto";
}

function productosVendibles() {
    return productosVendedor.filter(function(producto) {
        return producto.estado !== "inactivo" && Number(producto.cantidad) > 0;
    });
}

function llenarSelectorProductos() {
    const selector = document.getElementById("ventaProducto");
    if (!selector) return;

    const seleccionado = selector.value;

    selector.innerHTML = '<option value="">Seleccionar producto</option>';

    productosVendibles().forEach(function(producto) {
        const opcion = document.createElement("option");
        opcion.value = producto.id_producto;
        opcion.textContent = producto.codigo + " - " + producto.nombre + " (" + formatoPrecioVendedor(producto.precio) + ")";
        selector.appendChild(opcion);
    });

    selector.value = seleccionado;
}

function actualizarResumenVenta() {
    const idProducto = Number(document.getElementById("ventaProducto")?.value || 0);
    const cantidad = Number(document.getElementById("ventaCantidad")?.value || 0);
    const producto = productosVendedor.find(function(item) {
        return Number(item.id_producto) === idProducto;
    });

    document.getElementById("ventaNombreProducto").textContent = producto ? producto.nombre : "Sin producto seleccionado";
    document.getElementById("ventaCodigoProducto").textContent = "Codigo: " + (producto ? producto.codigo : "-");
    document.getElementById("ventaStockProducto").textContent = "Stock: " + (producto ? producto.cantidad : "-");
    document.getElementById("ventaTotal").textContent = formatoPrecioVendedor(producto ? Number(producto.precio) * cantidad : 0);
}

async function cargarInventarioVendedor() {
    const tabla = document.getElementById("tablaInventarioVendedor");
    const buscador = document.getElementById("buscadorVendedor");
    const textoBusqueda = buscador ? buscador.value.toLowerCase() : "";

    try {
        const respuesta = await fetch("php/productos_listar.php");
        const productos = await respuesta.json();

        if (productos.error) {
            if (tabla) {
                tabla.innerHTML = `<tr><td colspan="7">${productos.mensaje}</td></tr>`;
            }
            return;
        }

        productosVendedor = productos;
        llenarSelectorProductos();
        actualizarResumenVenta();

        if (!tabla) return;

        const productosFiltrados = productos.filter(function(producto) {
            const texto = [
                producto.codigo,
                producto.nombre,
                producto.marca,
                producto.categoria,
                producto.estado
            ].join(" ").toLowerCase();

            return texto.includes(textoBusqueda);
        });

        if (productosFiltrados.length === 0) {
            tabla.innerHTML = '<tr><td colspan="7">No se encontraron productos</td></tr>';
            return;
        }

        tabla.innerHTML = "";

        productosFiltrados.forEach(function(producto) {
            tabla.innerHTML += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.nombre}</td>
                    <td>${producto.marca}</td>
                    <td>${producto.categoria}</td>
                    <td>${formatoPrecioVendedor(producto.precio)}</td>
                    <td>${producto.cantidad}</td>
                    <td class="${claseEstadoVendedor(producto)}">${producto.estado}</td>
                </tr>
            `;
        });
    } catch (error) {
        if (tabla) {
            tabla.innerHTML = '<tr><td colspan="7">Error al cargar inventario</td></tr>';
        }
        console.error(error);
    }
}

async function registrarVentaVendedor(evento) {
    evento.preventDefault();

    const mensaje = document.getElementById("mensajeVenta");
    const datos = new FormData();

    datos.append("id_producto", document.getElementById("ventaProducto").value);
    datos.append("cliente", document.getElementById("ventaCliente").value.trim());
    datos.append("telefono", document.getElementById("ventaTelefono").value.trim());
    datos.append("cantidad", document.getElementById("ventaCantidad").value);
    datos.append("medio_pago", document.getElementById("ventaMedioPago").value);

    mensaje.textContent = "";

    try {
        const respuesta = await fetch("php/venta_registrar.php", {
            method: "POST",
            body: datos
        });

        const resultado = await respuesta.json();

        if (resultado.error) {
            mensaje.textContent = resultado.mensaje;
            return;
        }

        alert(resultado.mensaje + "\nTotal: " + formatoPrecioVendedor(resultado.total));
        document.getElementById("formVenta").reset();
        document.getElementById("ventaCantidad").value = 1;

        await cargarInventarioVendedor();
        await cargarHistorialVendedor();
        await cargarReportesVendedor();
    } catch (error) {
        mensaje.textContent = "Error al registrar la venta";
        console.error(error);
    }
}

async function cargarHistorialVendedor() {
    const tabla = document.getElementById("tablaHistorialVendedor");
    if (!tabla) return;

    try {
        const respuesta = await fetch("php/historial_listar.php");
        const historial = await respuesta.json();

        if (historial.error) {
            tabla.innerHTML = `<tr><td colspan="10">${historial.mensaje}</td></tr>`;
            return;
        }

        if (historial.length === 0) {
            tabla.innerHTML = '<tr><td colspan="10">No hay ventas registradas</td></tr>';
            return;
        }

        tabla.innerHTML = "";

        historial.forEach(function(registro) {
            const accion = registro.tipo === "Venta"
                ? `<button onclick="registrarDevolucionVendedor(${registro.id_venta}, ${registro.id_producto})">Devolucion</button>`
                : "<span>Registrada</span>";

            tabla.innerHTML += `
                <tr>
                    <td>${registro.cliente || "Sin nombre"}</td>
                    <td>${registro.telefono || "Sin telefono"}</td>
                    <td>${registro.tipo}</td>
                    <td>${registro.codigo || "Sin codigo"}</td>
                    <td>${registro.producto}</td>
                    <td>${registro.cantidad}</td>
                    <td>${formatoPrecioVendedor(registro.subtotal)}</td>
                    <td>${registro.medio_pago}</td>
                    <td>${new Date(registro.fecha).toLocaleString()}</td>
                    <td>${accion}</td>
                </tr>
            `;
        });
    } catch (error) {
        tabla.innerHTML = '<tr><td colspan="10">Error al cargar historial</td></tr>';
        console.error(error);
    }
}

async function cargarReportesVendedor() {
    try {
        const respuesta = await fetch("php/reportes_admin.php");
        const reportes = await respuesta.json();

        if (reportes.error) {
            console.error(reportes.mensaje);
            return;
        }

        document.getElementById("ventasHoy").textContent = formatoPrecioVendedor(reportes.ventas_hoy);
        document.getElementById("devolucionesHoy").textContent = formatoPrecioVendedor(reportes.devoluciones_hoy);
        document.getElementById("netoHoy").textContent = formatoPrecioVendedor(reportes.neto_hoy);
        document.getElementById("ventasMes").textContent = formatoPrecioVendedor(reportes.ventas_mes);
        document.getElementById("netoMes").textContent = formatoPrecioVendedor(reportes.neto_mes);
        document.getElementById("productoTop").textContent = reportes.producto_top;
    } catch (error) {
        console.error("Error al cargar reportes", error);
    }
}

async function registrarDevolucionVendedor(idVenta, idProducto) {
    const cantidadTexto = prompt("Cuantas unidades deseas devolver?");
    if (cantidadTexto === null) return;

    const cantidad = parseInt(cantidadTexto);

    if (isNaN(cantidad) || cantidad <= 0) {
        alert("Ingresa una cantidad valida.");
        return;
    }

    const motivo = prompt("Motivo de la devolucion:");

    if (motivo === null || motivo.trim() === "") {
        alert("Debes ingresar un motivo.");
        return;
    }

    const datos = new FormData();
    datos.append("id_venta", idVenta);
    datos.append("id_producto", idProducto);
    datos.append("cantidad", cantidad);
    datos.append("motivo", motivo.trim());

    try {
        const respuesta = await fetch("php/devolucion_registrar.php", {
            method: "POST",
            body: datos
        });

        const resultado = await respuesta.json();

        if (resultado.error) {
            alert(resultado.mensaje);
            return;
        }

        alert(resultado.mensaje + "\nTotal devuelto: " + formatoPrecioVendedor(resultado.total_devuelto));

        await cargarInventarioVendedor();
        await cargarHistorialVendedor();
        await cargarReportesVendedor();
    } catch (error) {
        alert("Error al registrar la devolucion.");
        console.error(error);
    }
}

function iniciarPanelVendedor() {
    cargarInventarioVendedor();
    cargarHistorialVendedor();
    cargarReportesVendedor();
}

document.querySelectorAll(".admin-tab").forEach(function(tab) {
    tab.addEventListener("click", function() {
        activarPanelVendedor(tab.dataset.panelTarget);
    });
});

window.addEventListener("sesion-lista", iniciarPanelVendedor);

document.getElementById("buscadorVendedor")?.addEventListener("input", cargarInventarioVendedor);
document.getElementById("ventaProducto")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaCantidad")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("formVenta")?.addEventListener("submit", registrarVentaVendedor);

window.registrarDevolucionVendedor = registrarDevolucionVendedor;
