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

function limpiarTextoVendedor(valor) {
    return String(valor || "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
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
    llenarSelectorColores();
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

function leerColoresVendedor(colores) {
    if (Array.isArray(colores)) return colores;

    if (typeof colores === "string" && colores.trim() !== "") {
        try {
            return JSON.parse(colores);
        } catch (error) {
            return [];
        }
    }

    return [];
}

function obtenerColorSeleccionadoVendedor(producto) {
    const selectorColor = document.getElementById("ventaColor");
    const idColor = Number(selectorColor?.value || 0);
    const colores = leerColoresVendedor(producto ? producto.colores : []);

    return colores.find(function(item) {
        return Number(item.id_producto_color) === idColor;
    }) || null;
}

function textoColoresVendedor(producto) {
    const colores = leerColoresVendedor(producto ? producto.colores : []);

    if (colores.length === 0) {
        return producto && producto.color ? producto.color : "-";
    }

    return colores.map(function(item) {
        return item.color;
    }).join(", ");
}

function textoTallasVendedor(tallas) {
    const lista = leerTallasVendedor(tallas);

    if (lista.length === 0) return "-";

    return lista.map(function(item) {
        return item.talla + " (" + item.cantidad + ")";
    }).join(", ");
}

function llenarSelectorColores() {
    const idProducto = Number(document.getElementById("ventaProducto")?.value || 0);
    const selector = document.getElementById("ventaColor");
    const producto = productosVendedor.find(function(item) {
        return Number(item.id_producto) === idProducto;
    });

    if (!selector) return;

    selector.innerHTML = '<option value="">Seleccionar color</option>';

    if (!producto) {
        llenarSelectorTallas();
        return;
    }

    const colores = leerColoresVendedor(producto.colores);

    if (colores.length === 0 && producto.color) {
        const opcion = document.createElement("option");
        opcion.value = "";
        opcion.textContent = producto.color;
        selector.appendChild(opcion);
    }

    colores.forEach(function(item) {
        const opcion = document.createElement("option");
        opcion.value = item.id_producto_color;
        opcion.textContent = item.color;
        selector.appendChild(opcion);
    });

    if (selector.options.length === 2) {
        selector.selectedIndex = 1;
    }

    llenarSelectorTallas();
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

    const colorSeleccionado = obtenerColorSeleccionadoVendedor(producto);
    const tallas = colorSeleccionado ? colorSeleccionado.tallas : producto.tallas;

    leerTallasVendedor(tallas)
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
    const descuentoTipo = document.getElementById("ventaDescuentoTipo")?.value || "valor";
    const descuentoValor = Number(document.getElementById("ventaDescuentoValor")?.value || 0);
    const talla = document.getElementById("ventaTalla")?.value || "";
    const producto = productosVendedor.find(function(item) {
        return Number(item.id_producto) === idProducto;
    });
    const colorSeleccionado = obtenerColorSeleccionadoVendedor(producto);
    const tallas = colorSeleccionado ? colorSeleccionado.tallas : (producto ? producto.tallas : []);
    const tallaSeleccionada = leerTallasVendedor(tallas).find(function(item) {
        return item.talla === talla;
    });

    document.getElementById("ventaNombreProducto").textContent = producto ? producto.nombre : "Sin producto seleccionado";
    document.getElementById("ventaCodigoProducto").textContent = "Codigo: " + (producto ? producto.codigo : "-");
    document.getElementById("ventaColorProducto").textContent = "Color: " + (colorSeleccionado ? colorSeleccionado.color : (producto ? textoColoresVendedor(producto) : "-"));
    document.getElementById("ventaTallasProducto").textContent = "Tallas disponibles: " + (producto ? textoTallasVendedor(tallas) : "-");
    document.getElementById("ventaStockProducto").textContent = "Stock talla: " + (tallaSeleccionada ? tallaSeleccionada.cantidad : "-");
    const bruto = producto ? Number(producto.precio) * cantidad : 0;
    let descuento = descuentoTipo === "porcentaje" ? bruto * (descuentoValor / 100) : descuentoValor;
    descuento = Math.min(Math.max(descuento, 0), bruto);
    const total = bruto - descuento;
    const base = total / 1.19;
    const iva = total - base;

    document.getElementById("ventaTotal").textContent = formatoPrecioVendedor(total) + " | IVA incluido: " + formatoPrecioVendedor(iva);
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
                tabla.innerHTML = `<tr><td colspan="9">${productos.mensaje}</td></tr>`;
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
                textoColoresVendedor(producto),
                producto.categoria,
                producto.estado
            ].join(" ").toLowerCase();

            return texto.includes(textoBusqueda);
        });

        if (productosFiltrados.length === 0) {
            tabla.innerHTML = '<tr><td colspan="9">No se encontraron productos</td></tr>';
            return;
        }

        tabla.innerHTML = "";

        productosFiltrados.forEach(function(producto) {
            tabla.innerHTML += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.nombre}</td>
                    <td>${producto.marca}</td>
                    <td>${textoColoresVendedor(producto)}</td>
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
            tabla.innerHTML = '<tr><td colspan="9">Error al cargar inventario</td></tr>';
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
    datos.append("correo", document.getElementById("ventaCorreo").value.trim());
    datos.append("cantidad", document.getElementById("ventaCantidad").value);
    datos.append("descuento_tipo", document.getElementById("ventaDescuentoTipo").value);
    datos.append("descuento_valor", document.getElementById("ventaDescuentoValor").value);
    datos.append("medio_pago", document.getElementById("ventaMedioPago").value);
    datos.append("canal_venta", document.getElementById("ventaCanal").value);
    datos.append("tipo_entrega", document.getElementById("ventaEntrega").value);
    datos.append("id_producto_color", document.getElementById("ventaColor")?.value || "");
    datos.append("color", document.getElementById("ventaColor")?.selectedOptions[0]?.textContent || "");
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

        const textoFactura = resultado.factura ? "\nFactura: " + resultado.factura.numero_factura : "";
        alert(resultado.mensaje + "\nTotal: " + formatoPrecioVendedor(resultado.total) + textoFactura);

        if (resultado.factura) {
            abrirFacturaVendedor(resultado.id_venta);
        }

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
            let botonFactura = "";

            if (registro.tipo === "Venta" && registro.estado === "pendiente") {
                accion = `
                    <button onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'pagada')">Confirmar pago</button>
                    <button onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'cancelada')">Cancelar</button>
                `;
            } else if (registro.tipo === "Venta" && registro.estado === "pagada") {
                accion = `<button onclick="registrarDevolucionVendedor(${registro.id_venta}, ${registro.id_producto})">Devolución</button>`;
            }

            if (registro.tipo === "Venta" && (registro.estado === "pagada" || registro.estado === "devuelta")) {
                botonFactura = `<button onclick="abrirFacturaVendedor(${registro.id_venta})">${registro.numero_factura || "Factura"}</button>`;
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
                    <td>${botonFactura}${accion}</td>
                </tr>
            `;
        });
    } catch (error) {
        tabla.innerHTML = '<tr><td colspan="14">Error al cargar historial</td></tr>';
        console.error(error);
    }
}

async function abrirFacturaVendedor(idVenta) {
    try {
        const respuesta = await fetch(rutaApiVendedor("factura_obtener.php?id_venta=" + encodeURIComponent(idVenta)));
        const resultado = await respuesta.json();

        if (resultado.error) {
            alert(resultado.mensaje);
            return;
        }

        imprimirFacturaVendedor(resultado.factura, resultado.detalles);
        await cargarHistorialVendedor();
    } catch (error) {
        alert("Error al abrir la factura.");
        console.error(error);
    }
}

function imprimirFacturaVendedor(factura, detalles) {
    const filas = detalles.map(function(detalle) {
        return `
            <tr>
                <td>${limpiarTextoVendedor(detalle.codigo)}</td>
                <td>${limpiarTextoVendedor(detalle.producto)}</td>
                <td>${limpiarTextoVendedor(detalle.color || "-")}</td>
                <td>${limpiarTextoVendedor(detalle.talla || "-")}</td>
                <td>${detalle.cantidad}</td>
                <td>${formatoPrecioVendedor(detalle.precio_unitario)}</td>
                <td>${formatoPrecioVendedor(detalle.iva)}</td>
                <td>${formatoPrecioVendedor(detalle.subtotal)}</td>
            </tr>
        `;
    }).join("");

    const correoCliente = String(factura.correo || "");
    const asuntoCorreo = encodeURIComponent("Factura " + factura.numero_factura + " - Isa Boutique");
    const cuerpoCorreo = encodeURIComponent(
        "Hola " + (factura.cliente || "") + ",\n\n" +
        "Adjuntamos/compartimos la información de tu factura " + factura.numero_factura + ".\n" +
        "Total: " + formatoPrecioVendedor(factura.total) + "\n\n" +
        "Gracias por comprar en Isa Boutique."
    );
    const enlaceCorreo = correoCliente
        ? "mailto:" + encodeURIComponent(correoCliente) + "?subject=" + asuntoCorreo + "&body=" + cuerpoCorreo
        : "";

    const ventana = window.open("", "_blank", "width=860,height=720");

    if (!ventana) {
        alert("El navegador bloqueó la ventana de impresión.");
        return;
    }

    ventana.document.write(`
        <!doctype html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${limpiarTextoVendedor(factura.numero_factura)}</title>
            <style>
                body { font-family: Arial, sans-serif; color: #2f2424; margin: 32px; }
                header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #954053; padding-bottom: 18px; margin-bottom: 24px; }
                h1 { margin: 0; color: #954053; font-family: Georgia, serif; font-weight: 400; }
                h2 { margin: 0 0 6px; font-size: 18px; }
                p { margin: 4px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 22px; }
                th, td { border-bottom: 1px solid #ead8d2; padding: 10px; text-align: left; }
                th { background: #fff3ef; color: #5c353b; }
                .totales { margin-left: auto; margin-top: 24px; width: 280px; }
                .totales div { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ead8d2; }
                .total { font-size: 18px; font-weight: 700; color: #954053; }
                .acciones { display: flex; gap: 10px; margin-bottom: 18px; }
                .acciones button, .acciones a { border: 0; background: #954053; color: #fff; padding: 10px 14px; text-decoration: none; cursor: pointer; font-size: 14px; }
                .acciones a.inactivo { background: #b8aaa5; pointer-events: none; }
                @media print { .acciones { display: none; } body { margin: 18px; } }
            </style>
        </head>
        <body>
            <div class="acciones">
                <button onclick="window.print()">Imprimir</button>
                <a class="${enlaceCorreo ? "" : "inactivo"}" href="${enlaceCorreo || "#"}">Enviar por correo</a>
            </div>
            <header>
                <div>
                    <h1>Isa Boutique</h1>
                    <p>Moda femenina</p>
                </div>
                <div>
                    <h2>Factura ${limpiarTextoVendedor(factura.numero_factura)}</h2>
                    <p>Venta: ${factura.id_venta}</p>
                    <p>Fecha: ${new Date(factura.fecha_factura).toLocaleString()}</p>
                    <p>Estado: ${limpiarTextoVendedor(factura.estado_factura)}</p>
                </div>
            </header>

            <section>
                <h2>Cliente</h2>
                <p>${limpiarTextoVendedor(factura.cliente)}</p>
                <p>Teléfono: ${limpiarTextoVendedor(factura.telefono)}</p>
                <p>Correo: ${limpiarTextoVendedor(factura.correo || "-")}</p>
                <p>Canal: ${etiquetaCanalVendedor(factura.canal_venta)} | Pago: ${limpiarTextoVendedor(factura.medio_pago)}</p>
                <p>Atendido por: ${limpiarTextoVendedor(factura.atendido_por)}</p>
            </section>

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Color</th>
                        <th>Talla</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>IVA</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>

            <div class="totales">
                <div><span>Subtotal bruto</span><strong>${formatoPrecioVendedor(factura.subtotal)}</strong></div>
                <div><span>Descuento</span><strong>${formatoPrecioVendedor(factura.descuento)}</strong></div>
                <div><span>Base gravable</span><strong>${formatoPrecioVendedor(factura.base_gravable)}</strong></div>
                <div><span>IVA (${Number(factura.tarifa_iva || 19).toFixed(0)}%)</span><strong>${formatoPrecioVendedor(factura.iva)}</strong></div>
                <div class="total"><span>Total</span><strong>${formatoPrecioVendedor(factura.total)}</strong></div>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
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
document.getElementById("ventaProducto")?.addEventListener("change", llenarSelectorColores);
document.getElementById("ventaColor")?.addEventListener("change", llenarSelectorTallas);
document.getElementById("ventaTalla")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaCantidad")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("ventaDescuentoTipo")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaDescuentoValor")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("formVenta")?.addEventListener("submit", registrarVentaVendedor);

window.registrarDevolucionVendedor = registrarDevolucionVendedor;
window.abrirFacturaVendedor = abrirFacturaVendedor;



