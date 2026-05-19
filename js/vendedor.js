let productosVendedor = [];

function rutaApiVendedor(archivo) {
    const ruta = window.location.pathname;
    const base = ruta.includes("/html/")
        ? ruta.split("/html/")[0] + "/"
        : ruta.substring(0, ruta.lastIndexOf("/") + 1);

    return base + "php/" + archivo;
}

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

function etiquetaCanalVendedor(canal) {
    const etiquetas = {
        tienda_fisica: "Tienda fisica",
        pagina_web: "Página web",
        whatsapp: "WhatsApp",
        instagram: "Instagram"
    };

    return etiquetas[canal] || "Tienda fisica";
}

function etiquetaEntregaVendedor(entrega) {
    const etiquetas = {
        recoger_tienda: "Recoge en tienda",
        envio_local: "Envio local",
        envio_nacional: "Envio nacional"
    };

    return etiquetas[entrega] || "Recoge en tienda";
}

function etiquetaEstadoVendedor(estado) {
    const etiquetas = {
        pagada: "Pagada",
        pendiente: "Pendiente",
        cancelada: "Cancelada",
        devuelta: "Devuelta"
    };

    return etiquetas[estado] || "Pagada";
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
    llenarSelectorTallas();
}

function leerTallasVendedor(tallas) {
    if (Array.isArray(tallas)) return tallas;

    if (typeof tallas === "string" && tallas.trim() !== "") {
        try {
            return JSON.parse(tallas);
        } catch (error) {
            return [];
        }
    }

    return [];
}

function textoTallasVendedor(tallas) {
    const lista = leerTallasVendedor(tallas);

    if (lista.length === 0) return "-";

    return lista.map(function(item) {
        return item.talla + " (" + item.cantidad + ")";
    }).join(", ");
}

function llenarSelectorTallas() {
    const idProducto = Number(document.getElementById("ventaProducto")?.value || 0);
    const selector = document.getElementById("ventaTalla");
    const producto = productosVendedor.find(function(item) {
        return Number(item.id_producto) === idProducto;
    });

    if (!selector) return;

    selector.innerHTML = '<option value="">Seleccionar talla</option>';

    if (!producto) {
        actualizarResumenVenta();
        return;
    }

    leerTallasVendedor(producto.tallas)
        .filter(function(item) {
            return Number(item.cantidad) > 0;
        })
        .forEach(function(item) {
            const opcion = document.createElement("option");
            opcion.value = item.talla;
            opcion.textContent = item.talla + " - " + item.cantidad + " disponibles";
            selector.appendChild(opcion);
        });

    if (selector.options.length === 2) {
        selector.selectedIndex = 1;
    }

    actualizarResumenVenta();
}

function actualizarResumenVenta() {
    const idProducto = Number(document.getElementById("ventaProducto")?.value || 0);
    const cantidad = Number(document.getElementById("ventaCantidad")?.value || 0);
    const talla = document.getElementById("ventaTalla")?.value || "";
    const producto = productosVendedor.find(function(item) {
        return Number(item.id_producto) === idProducto;
    });
    const tallaSeleccionada = leerTallasVendedor(producto ? producto.tallas : []).find(function(item) {
        return item.talla === talla;
    });

    document.getElementById("ventaNombreProducto").textContent = producto ? producto.nombre : "Sin producto seleccionado";
    document.getElementById("ventaCodigoProducto").textContent = "Codigo: " + (producto ? producto.codigo : "-");
    document.getElementById("ventaTallasProducto").textContent = "Tallas disponibles: " + (producto ? textoTallasVendedor(producto.tallas) : "-");
    document.getElementById("ventaStockProducto").textContent = "Stock talla: " + (tallaSeleccionada ? tallaSeleccionada.cantidad : "-");
    document.getElementById("ventaTotal").textContent = formatoPrecioVendedor(producto ? Number(producto.precio) * cantidad : 0);
}

async function cargarInventarioVendedor() {
    const tabla = document.getElementById("tablaInventarioVendedor");
    const buscador = document.getElementById("buscadorVendedor");
    const textoBusqueda = buscador ? buscador.value.toLowerCase() : "";

    try {
        const respuesta = await fetch(rutaApiVendedor("productos_listar.php"));
        const productos = await respuesta.json();

        if (productos.error) {
            if (tabla) {
                tabla.innerHTML = `<tr><td colspan="8">${productos.mensaje}</td></tr>`;
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
            tabla.innerHTML = '<tr><td colspan="8">No se encontraron productos</td></tr>';
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
                    <td>${textoTallasVendedor(producto.tallas)}</td>
                    <td class="${claseEstadoVendedor(producto)}">${producto.estado}</td>
                </tr>
            `;
        });
    } catch (error) {
        if (tabla) {
            tabla.innerHTML = '<tr><td colspan="8">Error al cargar inventario</td></tr>';
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
    datos.append("canal_venta", document.getElementById("ventaCanal").value);
    datos.append("tipo_entrega", document.getElementById("ventaEntrega").value);
    datos.append("talla", document.getElementById("ventaTalla").value);

    mensaje.textContent = "";

    try {
        const respuesta = await fetch(rutaApiVendedor("venta_registrar.php"), {
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
        const respuesta = await fetch(rutaApiVendedor("historial_listar.php"));
        const historial = await respuesta.json();

        if (historial.error) {
            tabla.innerHTML = `<tr><td colspan="14">${historial.mensaje}</td></tr>`;
            return;
        }

        if (historial.length === 0) {
            tabla.innerHTML = '<tr><td colspan="14">No hay ventas registradas</td></tr>';
            return;
        }

        tabla.innerHTML = "";

        historial.forEach(function(registro) {
            let accion = "<span>Registrada</span>";

            if (registro.tipo === "Venta" && registro.estado === "pendiente") {
                accion = `
                    <button onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'pagada')">Confirmar pago</button>
                    <button onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'cancelada')">Cancelar</button>
                `;
            } else if (registro.tipo === "Venta" && registro.estado === "pagada") {
                accion = `<button onclick="registrarDevolucionVendedor(${registro.id_venta}, ${registro.id_producto})">Devolución</button>`;
            }

            tabla.innerHTML += `
                <tr>
                    <td>${registro.cliente || "Sin nombre"}</td>
                    <td>${registro.telefono || "Sin telefono"}</td>
                    <td>${registro.tipo}</td>
                    <td>${registro.codigo || "Sin codigo"}</td>
                    <td>${registro.producto}</td>
                    <td>${registro.talla || "-"}</td>
                    <td>${registro.cantidad}</td>
                    <td>${formatoPrecioVendedor(registro.subtotal)}</td>
                    <td>${registro.medio_pago}</td>
                    <td>${etiquetaCanalVendedor(registro.canal_venta)}</td>
                    <td>${etiquetaEntregaVendedor(registro.tipo_entrega)}</td>
                    <td>${etiquetaEstadoVendedor(registro.estado)}</td>
                    <td>${new Date(registro.fecha).toLocaleString()}</td>
                    <td>${accion}</td>
                </tr>
            `;
        });
    } catch (error) {
        tabla.innerHTML = '<tr><td colspan="14">Error al cargar historial</td></tr>';
        console.error(error);
    }
}

async function actualizarEstadoVentaVendedor(idVenta, estado) {
    const texto = estado === "pagada" ? "confirmar este pedido como pagado" : "cancelar este pedido y devolver el stock";

    if (!confirm("Deseas " + texto + "?")) {
        return;
    }

    const datos = new FormData();
    datos.append("id_venta", idVenta);
    datos.append("estado", estado);

    try {
        const respuesta = await fetch(rutaApiVendedor("venta_actualizar_estado.php"), {
            method: "POST",
            body: datos
        });
        const resultado = await respuesta.json();

        alert(resultado.mensaje);

        if (!resultado.error) {
            await cargarInventarioVendedor();
            await cargarHistorialVendedor();
            await cargarReportesVendedor();
        }
    } catch (error) {
        alert("Error al actualizar el pedido.");
        console.error(error);
    }
}

async function cargarReportesVendedor() {
    try {
        const respuesta = await fetch(rutaApiVendedor("reportes_admin.php"));
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

    const motivo = prompt("Motivo de la devolución:");

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
        const respuesta = await fetch(rutaApiVendedor("devolucion_registrar.php"), {
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
        alert("Error al registrar la devolución.");
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
document.getElementById("ventaProducto")?.addEventListener("change", llenarSelectorTallas);
document.getElementById("ventaTalla")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaCantidad")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("formVenta")?.addEventListener("submit", registrarVentaVendedor);

window.registrarDevolucionVendedor = registrarDevolucionVendedor;



