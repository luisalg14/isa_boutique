let productosVendedor = [];

let pedidosPendientesVendedor = [];

const historialPanelesVendedor = [];

const paginacionVendedor = {};

function obtenerPaginaVendedor(clave) {
    return Number(paginacionVendedor[clave] || 1);
}

function cambiarPaginaVendedor(clave, pagina, renderizar) {
    paginacionVendedor[clave] = pagina;
    renderizar();
}

function prepararPaginaVendedor(datos, clave, porPagina, cuerpoTabla, renderizar) {
    const totalPaginas = Math.max(1, Math.ceil(datos.length / porPagina));
    let paginaActual = obtenerPaginaVendedor(clave);

    if (paginaActual > totalPaginas) {
        paginaActual = totalPaginas;
        paginacionVendedor[clave] = paginaActual;
    }

    if (paginaActual < 1) {
        paginaActual = 1;
        paginacionVendedor[clave] = paginaActual;
    }

    renderizarPaginacionVendedor(clave, datos.length, porPagina, paginaActual, cuerpoTabla, renderizar);

    const inicio = (paginaActual - 1) * porPagina;
    return datos.slice(inicio, inicio + porPagina);
}

function renderizarPaginacionVendedor(clave, totalRegistros, porPagina, paginaActual, cuerpoTabla, renderizar) {
    if (!cuerpoTabla) return;

    const tabla = cuerpoTabla.closest("table");
    if (!tabla) return;

    let contenedor = tabla.nextElementSibling;

    if (!contenedor || contenedor.dataset.pagination !== clave) {
        contenedor = document.createElement("div");
        contenedor.className = "pagination-control";
        contenedor.dataset.pagination = clave;
        tabla.insertAdjacentElement("afterend", contenedor);
    }

    const totalPaginas = Math.ceil(totalRegistros / porPagina);
    contenedor.innerHTML = "";

    if (totalPaginas <= 1) {
        contenedor.hidden = true;
        return;
    }

    contenedor.hidden = false;

    const crearBoton = function(texto, pagina, activo, deshabilitado) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.textContent = texto;
        boton.className = activo ? "activo" : "";
        boton.disabled = deshabilitado;
        boton.addEventListener("click", function() {
            cambiarPaginaVendedor(clave, pagina, renderizar);
        });
        contenedor.appendChild(boton);
    };

    crearBoton("‹", paginaActual - 1, false, paginaActual === 1);

    const inicio = Math.max(1, paginaActual - 2);
    const fin = Math.min(totalPaginas, paginaActual + 2);

    if (inicio > 1) {
        crearBoton("1", 1, paginaActual === 1, false);
        if (inicio > 2) {
            const puntos = document.createElement("span");
            puntos.textContent = "...";
            contenedor.appendChild(puntos);
        }
    }

    for (let pagina = inicio; pagina <= fin; pagina++) {
        crearBoton(String(pagina), pagina, pagina === paginaActual, false);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            const puntos = document.createElement("span");
            puntos.textContent = "...";
            contenedor.appendChild(puntos);
        }
        crearBoton(String(totalPaginas), totalPaginas, paginaActual === totalPaginas, false);
    }

    crearBoton("Next ›", paginaActual + 1, false, paginaActual === totalPaginas);
}

function ocultarPaginacionVendedor(clave, cuerpoTabla) {
    const tabla = cuerpoTabla ? cuerpoTabla.closest("table") : null;
    const contenedor = tabla ? tabla.nextElementSibling : null;

    if (contenedor && contenedor.dataset.pagination === clave) {
        contenedor.hidden = true;
        contenedor.innerHTML = "";
    }
}

function rutaApiVendedor(archivo) {
    const ruta = window.location.pathname;
    const base = ruta.includes("/html/")
        ? ruta.split("/html/")[0] + "/"
        : ruta.substring(0, ruta.lastIndexOf("/") + 1);

    return base + "php/" + archivo;
}

function panelActivoVendedor() {
    const panel = document.querySelector(".admin-panel.activo");
    return panel ? panel.id : "";
}

function guardarRegresoInternoVendedor(idPanel) {
    if (!idPanel) return;

    sessionStorage.setItem("isa_internal_return", JSON.stringify({
        tipo: "vendedor",
        url: "vendedor.html#" + idPanel,
        panel: idPanel
    }));
}

function activarPanelVendedor(idPanel, opciones) {
    opciones = opciones || {};
    const panelActual = panelActivoVendedor();

    if (!opciones.omitirHistorial && panelActual && panelActual !== idPanel) {
        historialPanelesVendedor.push(panelActual);
    }

    document.querySelectorAll(".admin-panel").forEach(function(panel) {
        panel.classList.toggle("activo", panel.id === idPanel);
    });

    document.querySelectorAll(".admin-tab").forEach(function(tab) {
        tab.classList.toggle("activo", tab.dataset.panelTarget === idPanel);
    });

    guardarRegresoInternoVendedor(idPanel);
    if (window.location.hash !== "#" + idPanel) {
        history.replaceState(null, "", "#" + idPanel);
    }
}

function volverPanelInternoVendedor() {
    const anterior = historialPanelesVendedor.pop();

    if (anterior) {
        activarPanelVendedor(anterior, { omitirHistorial: true });
        return;
    }

    activarPanelVendedor("panel-venta", { omitirHistorial: true });
}

function formatoPrecioVendedor(valor) {
    return "$" + Number(valor || 0).toLocaleString("es-CO", {
        maximumFractionDigits: 0
    }) + " COP";
}

function actualizarMetaDiariaVendedor(meta) {
    if (!meta) return;

    const objetivo = Number(meta.objetivo || 0);
    const avance = Math.max(0, Math.min(100, Number(meta.avance || 0)));
    const faltante = Number(meta.faltante || 0);
    const ventasHoy = Number(meta.ventas_hoy || 0);
    const texto = faltante <= 0
        ? "Meta cumplida. Ventas netas de hoy: " + formatoPrecioVendedor(ventasHoy) + "."
        : "Faltan " + formatoPrecioVendedor(faltante) + " para cumplir la meta de hoy.";

    [
        {
            valor: "vendedorMetaDiaria",
            detalle: "vendedorMetaDiariaTexto",
            porcentaje: "vendedorMetaDiariaAvance",
            barra: "vendedorMetaDiariaBarra"
        },
        {
            valor: "vendedorMetaDiariaVenta",
            detalle: "vendedorMetaDiariaTextoVenta",
            porcentaje: "vendedorMetaDiariaAvanceVenta",
            barra: "vendedorMetaDiariaBarraVenta"
        }
    ].forEach(function(ids) {
        const valor = document.getElementById(ids.valor);
        const detalle = document.getElementById(ids.detalle);
        const porcentaje = document.getElementById(ids.porcentaje);
        const barra = document.getElementById(ids.barra);

        if (valor) valor.textContent = formatoPrecioVendedor(objetivo);
        if (detalle) detalle.textContent = texto;
        if (porcentaje) porcentaje.textContent = avance.toFixed(0) + "%";
        if (barra) barra.style.width = avance + "%";
    });
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

function obtenerCodigoDesdeBarrasVendedor(valor) {
    const texto = String(valor || "").trim();
    if (!texto) return "";

    const partes = texto.split("|");
    return partes[partes.length - 1].trim().toUpperCase();
}

function buscarVariantePorCodigoVendedor(codigo) {
    const codigoNormalizado = String(codigo || "").toUpperCase();

    for (const producto of productosVendedor) {
        const colores = leerColoresVendedor(producto.colores);

        for (const color of colores) {
            const tallas = leerTallasVendedor(color.tallas);

            for (const talla of tallas) {
                if (String(talla.codigo_barras || "").toUpperCase() === codigoNormalizado) {
                    return {
                        producto,
                        color,
                        talla
                    };
                }
            }
        }
    }

    return null;
}

function seleccionarProductoVentaPorCodigo(codigoLeido) {
    const codigo = obtenerCodigoDesdeBarrasVendedor(codigoLeido);
    const mensaje = document.getElementById("mensajeVenta");
    const campoCodigo = document.getElementById("ventaCodigoBarras");
    const selectorProducto = document.getElementById("ventaProducto");
    const selectorColor = document.getElementById("ventaColor");
    const selectorTalla = document.getElementById("ventaTalla");

    if (campoCodigo) campoCodigo.value = codigo;

    const variante = buscarVariantePorCodigoVendedor(codigo);

    if (variante) {
        selectorProducto.value = variante.producto.id_producto;
        llenarSelectorColores();
        if (selectorColor) selectorColor.value = variante.color.id_producto_color;
        llenarSelectorTallas();
        if (selectorTalla) selectorTalla.value = variante.talla.talla;
        actualizarResumenVenta();

        if (mensaje) {
            mensaje.textContent = "Variante seleccionada: "
                + variante.producto.nombre
                + " / " + variante.color.color
                + " / talla " + variante.talla.talla + ".";
        }
        return true;
    }

    const producto = productosVendedor.find(function(item) {
        return String(item.codigo || "").toUpperCase() === codigo;
    });

    if (!producto) {
        if (mensaje) mensaje.textContent = "No se encontró producto ni variante con el código " + codigo + ".";
        return false;
    }

    selectorProducto.value = producto.id_producto;
    llenarSelectorColores();
    actualizarResumenVenta();

    if (mensaje) mensaje.textContent = "Producto seleccionado por código de barras: " + producto.nombre + ".";
    return true;
}

function buscarProductoVentaPorCodigo() {
    const codigo = document.getElementById("ventaCodigoBarras")?.value || "";
    seleccionarProductoVentaPorCodigo(codigo);
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

function codigosBarrasVendedor(producto) {
    const codigos = [];
    const colores = leerColoresVendedor(producto ? producto.colores : []);

    colores.forEach(function(color) {
        leerTallasVendedor(color.tallas).forEach(function(talla) {
            if (talla.codigo_barras) codigos.push(talla.codigo_barras);
        });
    });

    return codigos.join(" ");
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

    const codigoResumen = tallaSeleccionada && tallaSeleccionada.codigo_barras
        ? tallaSeleccionada.codigo_barras
        : (producto ? producto.codigo : "-");

    document.getElementById("ventaNombreProducto").textContent = producto ? producto.nombre : "Sin producto seleccionado";
    document.getElementById("ventaCodigoProducto").textContent = "Codigo: " + codigoResumen;
    document.getElementById("ventaColorProducto").textContent = "Color: " + (colorSeleccionado ? colorSeleccionado.color : (producto ? textoColoresVendedor(producto) : "-"));
    document.getElementById("ventaTallasProducto").textContent = "Tallas disponibles: " + (producto ? textoTallasVendedor(tallas) : "-");
    document.getElementById("ventaStockProducto").textContent = "Stock talla: " + (tallaSeleccionada ? tallaSeleccionada.cantidad : "-");
    const bruto = producto ? Number(producto.precio) * cantidad : 0;
    let descuento = descuentoTipo === "porcentaje" ? bruto * (descuentoValor / 100) : descuentoValor;
    descuento = Math.min(Math.max(descuento, 0), bruto);
    const total = bruto - descuento;
    const base = total / 1.19;
    const iva = total - base;
    const desglose = document.getElementById("ventaDesglose");

    if (desglose) {
        desglose.innerHTML = `
            <div><span>Subtotal</span><strong>${formatoPrecioVendedor(bruto)}</strong></div>
            <div><span>Descuento</span><strong>${formatoPrecioVendedor(descuento)}</strong></div>
            <div><span>Base sin IVA</span><strong>${formatoPrecioVendedor(base)}</strong></div>
            <div><span>IVA incluido</span><strong>${formatoPrecioVendedor(iva)}</strong></div>
        `;
    }

    document.getElementById("ventaTotal").innerHTML = `
        <span class="venta-total-monto">${formatoPrecioVendedor(total)}</span>
        <span class="venta-total-iva">IVA incluido: ${formatoPrecioVendedor(iva)}</span>
    `;
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
                codigosBarrasVendedor(producto),
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
            ocultarPaginacionVendedor("inventario-vendedor", tabla);
            return;
        }

        tabla.innerHTML = "";

        prepararPaginaVendedor(productosFiltrados, "inventario-vendedor", 20, tabla, cargarInventarioVendedor).forEach(function(producto) {
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
    datos.append("tipo_documento", document.getElementById("ventaTipoDocumento")?.value || "CC");
    datos.append("numero_documento", document.getElementById("ventaNumeroDocumento")?.value.trim() || "");
    datos.append("cantidad", document.getElementById("ventaCantidad").value);
    datos.append("descuento_tipo", document.getElementById("ventaDescuentoTipo").value);
    datos.append("descuento_valor", document.getElementById("ventaDescuentoValor").value);
    datos.append("medio_pago", document.getElementById("ventaMedioPago").value);
    datos.append("canal_venta", document.getElementById("ventaCanal").value);
    datos.append("tipo_entrega", document.getElementById("ventaEntrega").value);
    datos.append("id_producto_color", document.getElementById("ventaColor")?.value || "");
    datos.append("color", document.getElementById("ventaColor")?.selectedOptions[0]?.textContent || "");
    datos.append("talla", document.getElementById("ventaTalla").value);
    datos.append("codigo_barras", document.getElementById("ventaCodigoBarras")?.value.trim() || "");

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
        } else {
            mensaje.textContent = "Pedido registrado como pendiente. La factura se emite cuando se confirme el pago.";
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
    const panelPendientes = document.getElementById("pedidosPendientesVendedor");
    const buscador = document.getElementById("buscadorHistorialVendedor");
    const textoBusqueda = buscador ? buscador.value.toLowerCase().trim() : "";
    if (!tabla && !panelPendientes) return;

    try {
        const respuesta = await fetch(rutaApiVendedor("historial_listar.php"));
        const historial = await respuesta.json();

        if (historial.error) {
            if (tabla) tabla.innerHTML = `<tr><td colspan="8">${historial.mensaje}</td></tr>`;
            if (panelPendientes) panelPendientes.innerHTML = `<p class="empty-dashboard">${historial.mensaje}</p>`;
            return;
        }

        renderizarPedidosPendientesVendedor(historial);

        const historialFiltrado = textoBusqueda
            ? historial.filter(function(registro) {
                const texto = [
                    registro.cliente,
                    registro.telefono,
                    registro.tipo,
                    registro.codigo,
                    registro.producto,
                    registro.color,
                    registro.talla,
                    registro.medio_pago,
                    registro.canal_venta,
                    registro.tipo_entrega,
                    registro.estado,
                    registro.numero_factura
                ].join(" ").toLowerCase();

                return texto.includes(textoBusqueda);
            })
            : historial;

        if (tabla && historialFiltrado.length === 0) {
            tabla.innerHTML = '<tr><td colspan="8">No hay ventas registradas</td></tr>';
            ocultarPaginacionVendedor("historial-vendedor", tabla);
            return;
        }

        if (!tabla) return;

        tabla.innerHTML = "";

        prepararPaginaVendedor(historialFiltrado, "historial-vendedor", 20, tabla, cargarHistorialVendedor).forEach(function(registro) {
            let accion = "<span>Registrada</span>";
            let botonFactura = "";
            const fecha = registro.fecha ? new Date(registro.fecha) : null;
            const fechaTexto = fecha && !Number.isNaN(fecha.getTime()) ? fecha.toLocaleString() : "-";

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
                    <td>
                        <div class="compact-stack-cell">
                            <strong>${registro.cliente || "Sin nombre"}</strong>
                            <span>${registro.telefono || "Sin telefono"}</span>
                        </div>
                    </td>
                    <td>
                        <div class="compact-stack-cell">
                            <strong>${registro.producto}</strong>
                            <span>${registro.codigo || "Sin codigo"}</span>
                        </div>
                    </td>
                    <td>
                        <div class="compact-stack-cell">
                            <strong>${registro.tipo}</strong>
                            <span>Talla: ${registro.talla || "-"}</span>
                        </div>
                    </td>
                    <td>
                        <div class="compact-stack-cell money-stack">
                            <strong>Cant. ${registro.cantidad}</strong>
                            <span>${formatoPrecioVendedor(registro.total ?? registro.subtotal)}</span>
                        </div>
                    </td>
                    <td>
                        <div class="compact-stack-cell">
                            <strong>${registro.medio_pago || "-"}</strong>
                            <span>${etiquetaCanalVendedor(registro.canal_venta)}</span>
                            <small>${etiquetaEntregaVendedor(registro.tipo_entrega)}</small>
                        </div>
                    </td>
                    <td>${etiquetaEstadoVendedor(registro.estado)}</td>
                    <td>${fechaTexto}</td>
                    <td><div class="table-actions-inline">${botonFactura}${accion}</div></td>
                </tr>
            `;
        });
    } catch (error) {
        if (tabla) tabla.innerHTML = '<tr><td colspan="8">Error al cargar historial</td></tr>';
        if (panelPendientes) panelPendientes.innerHTML = '<p class="empty-dashboard">Error al cargar pedidos pendientes.</p>';
        console.error(error);
    }
}

function renderizarPedidosPendientesVendedor(historial) {
    const contenedor = document.getElementById("pedidosPendientesVendedor");
    const botonNotificacion = document.getElementById("btnNotificacionesVendedor");
    const contadorNotificacion = document.getElementById("contadorNotificacionesVendedor");

    const todosPendientes = Array.isArray(historial)
        ? historial.filter(function(registro) {
            return registro.tipo === "Venta" && registro.estado === "pendiente";
        })
        : [];
    const pendientes = todosPendientes.slice(0, 5);
    pedidosPendientesVendedor = todosPendientes;

    if (contadorNotificacion) {
        contadorNotificacion.textContent = todosPendientes.length;
    }

    if (botonNotificacion) {
        botonNotificacion.classList.toggle("tiene-alerta", todosPendientes.length > 0);
        botonNotificacion.title = todosPendientes.length > 0
            ? todosPendientes.length + " pedidos web pendientes"
            : "Sin pedidos web pendientes";
    }

    renderizarNotificacionesVendedor(todosPendientes);

    if (!contenedor) return;

    if (pendientes.length === 0) {
        contenedor.innerHTML = '<p class="empty-dashboard">No hay pedidos web pendientes.</p>';
        return;
    }

    contenedor.innerHTML = "";

    pendientes.forEach(function(registro) {
        contenedor.innerHTML += `
            <div class="pending-order-card">
                <div>
                    <strong>${limpiarTextoVendedor(registro.cliente || "Cliente sin nombre")}</strong>
                    <span>${limpiarTextoVendedor(registro.producto || "Producto")} | ${limpiarTextoVendedor(registro.color || "Sin color")} | Talla ${limpiarTextoVendedor(registro.talla || "-")}</span>
                    <span>${limpiarTextoVendedor(etiquetaCanalVendedor(registro.canal_venta))} | ${limpiarTextoVendedor(etiquetaEntregaVendedor(registro.tipo_entrega))}</span>
                    <small>${limpiarTextoVendedor(registro.telefono || "Sin telefono")} | ${new Date(registro.fecha).toLocaleString()}</small>
                </div>
                <p>${formatoPrecioVendedor(registro.total || registro.subtotal || 0)}</p>
                <div class="pending-order-actions">
                    <button type="button" onclick="alternarDetallePedidoVendedor(${registro.id_venta})">Ver detalle</button>
                    <button type="button" onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'pagada')">Confirmar</button>
                    <button type="button" onclick="actualizarEstadoVentaVendedor(${registro.id_venta}, 'cancelada')">Cancelar</button>
                </div>
                <div class="pending-order-detail" id="detallePedidoVendedor${registro.id_venta}">
                    ${detallePedidoPendienteVendedor(registro)}
                </div>
            </div>
        `;
    });
}

function renderizarNotificacionesVendedor(pedidos) {
    const panel = document.getElementById("panelNotificacionesVendedor");
    if (!panel) return;

    const pendientes = Array.isArray(pedidos) ? pedidos.slice(0, 5) : [];

    if (pendientes.length === 0) {
        panel.innerHTML = `
            <h4>Notificaciones</h4>
            <p>No hay pedidos web pendientes por revisar.</p>
        `;
        return;
    }

    panel.innerHTML = `<h4>Pedidos web pendientes</h4>`;
    pendientes.forEach(function(registro) {
        panel.innerHTML += `
            <div class="notification-item">
                <strong>${limpiarTextoVendedor(registro.cliente || "Cliente sin nombre")}</strong>
                <span>${limpiarTextoVendedor(registro.producto || "Producto")} | ${limpiarTextoVendedor(registro.color || "Sin color")} | Talla ${limpiarTextoVendedor(registro.talla || "-")}</span>
                <small>${formatoPrecioVendedor(registro.total || registro.subtotal || 0)} | ${new Date(registro.fecha).toLocaleString()}</small>
                <button type="button" onclick="revisarPedidoPendienteVendedor(${registro.id_venta})">Revisar pedido</button>
            </div>
        `;
    });
}

function buscarProductoPendienteVendedor(registro) {
    return productosVendedor.find(function(producto) {
        return Number(producto.id_producto) === Number(registro.id_producto);
    });
}

function buscarVariantePendienteVendedor(registro, producto) {
    const colorPedido = String(registro.color || "").toLowerCase();
    const tallaPedido = String(registro.talla || "").toUpperCase();
    const colores = leerColoresVendedor(producto ? producto.colores : []);

    for (const color of colores) {
        const coincideColor = colorPedido === "" || String(color.color || "").toLowerCase() === colorPedido;
        if (!coincideColor) continue;

        const talla = leerTallasVendedor(color.tallas).find(function(item) {
            return String(item.talla || "").toUpperCase() === tallaPedido;
        });

        if (talla) {
            return {
                color,
                talla
            };
        }
    }

    return null;
}

function detallePedidoPendienteVendedor(registro) {
    const producto = buscarProductoPendienteVendedor(registro);
    const variante = buscarVariantePendienteVendedor(registro, producto);
    const stockActual = variante ? variante.talla.cantidad : "Validar";
    const codigoVariante = variante && variante.talla.codigo_barras ? variante.talla.codigo_barras : (registro.codigo || "-");

    return `
        <div>
            <span>Código</span>
            <strong>${limpiarTextoVendedor(codigoVariante)}</strong>
        </div>
        <div>
            <span>Producto</span>
            <strong>${limpiarTextoVendedor(registro.producto || "-")}</strong>
        </div>
        <div>
            <span>Color y talla</span>
            <strong>${limpiarTextoVendedor(registro.color || "Sin color")} / ${limpiarTextoVendedor(registro.talla || "-")}</strong>
        </div>
        <div>
            <span>Pedido</span>
            <strong>${Number(registro.cantidad || 0)} unidad(es)</strong>
        </div>
        <div>
            <span>Stock actual</span>
            <strong>${limpiarTextoVendedor(stockActual)}</strong>
        </div>
        <div>
            <span>Entrega</span>
            <strong>${limpiarTextoVendedor(etiquetaEntregaVendedor(registro.tipo_entrega))}</strong>
        </div>
        <div>
            <span>Pago</span>
            <strong>${limpiarTextoVendedor(registro.medio_pago || "-")}</strong>
        </div>
        <div>
            <span>Contacto</span>
            <strong>${limpiarTextoVendedor(registro.telefono || "Sin telefono")}</strong>
        </div>
    `;
}

function alternarDetallePedidoVendedor(idVenta, abrir) {
    const tarjeta = document.getElementById("detallePedidoVendedor" + idVenta)
        ?.closest(".pending-order-card")
    if (!tarjeta) return;

    if (abrir) {
        tarjeta.classList.add("detalle-abierto");
        return;
    }

    tarjeta.classList.toggle("detalle-abierto");
}

function revisarPedidoPendienteVendedor(idVenta) {
    document.getElementById("panelNotificacionesVendedor")?.classList.remove("abierto");
    activarPanelVendedor("panel-venta");

    setTimeout(function() {
        alternarDetallePedidoVendedor(idVenta, true);
        document.getElementById("detallePedidoVendedor" + idVenta)?.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }, 80);
}

async function abrirFacturaVendedor(idVenta) {
    try {
        const respuesta = await fetch(rutaApiVendedor("factura_obtener.php?id_venta=" + encodeURIComponent(idVenta)));
        const resultado = await respuesta.json();

        if (resultado.error) {
            alert(resultado.mensaje);
            return;
        }

        imprimirFacturaVendedor(resultado.factura, resultado.detalles, resultado.empresa);
        await cargarHistorialVendedor();
    } catch (error) {
        alert("Error al abrir la factura.");
        console.error(error);
    }
}

function imprimirFacturaVendedor(factura, detalles, empresa) {
    empresa = empresa || {};
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
                * { box-sizing: border-box; }
                body { margin: 0; background: #f6f1ed; color: #2f2424; font-family: Arial, sans-serif; }
                .factura { width: min(960px, calc(100% - 36px)); margin: 24px auto; padding: 34px; background: #fffdfb; border: 1px solid #ead8d2; }
                .acciones { display: flex; gap: 10px; width: min(960px, calc(100% - 36px)); margin: 24px auto 0; }
                .acciones button, .acciones a { border: 0; border-radius: 8px; background: #954053; color: #fff; padding: 11px 16px; text-decoration: none; cursor: pointer; font-size: 14px; }
                .acciones a.inactivo { background: #b8aaa5; pointer-events: none; }
                header { display: grid; grid-template-columns: 1.4fr 0.8fr; gap: 24px; border-bottom: 3px solid #954053; padding-bottom: 22px; margin-bottom: 24px; }
                h1 { margin: 0 0 8px; color: #954053; font-family: Georgia, serif; font-size: 34px; font-weight: 400; }
                h2 { margin: 0 0 8px; color: #5c353b; font-size: 17px; }
                p { margin: 4px 0; line-height: 1.45; }
                .muted { color: #746760; font-size: 12px; }
                .factura-box { border: 1px solid #ead8d2; border-radius: 12px; padding: 16px; background: #fff8f5; }
                .factura-box strong { display: block; color: #954053; font-size: 22px; margin-bottom: 8px; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px; }
                .panel { border: 1px solid #ead8d2; border-radius: 12px; padding: 16px; }
                table { width: 100%; border-collapse: collapse; margin-top: 22px; }
                th, td { border-bottom: 1px solid #ead8d2; padding: 11px 9px; text-align: left; font-size: 13px; }
                th { background: #fff3ef; color: #5c353b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
                .totales { margin-left: auto; margin-top: 24px; width: min(360px, 100%); border: 1px solid #ead8d2; border-radius: 12px; padding: 14px 16px; background: #fff8f5; }
                .totales div { display: flex; justify-content: space-between; gap: 16px; padding: 8px 0; border-bottom: 1px solid #ead8d2; }
                .totales div:last-child { border-bottom: 0; }
                .total { font-size: 18px; font-weight: 700; color: #954053; }
                .nota { margin-top: 24px; padding: 14px 16px; border-left: 4px solid #d6b890; background: #fff8f0; color: #6f5b4f; font-size: 12px; }
                @media print { .acciones { display: none; } body { background: #fff; } .factura { width: 100%; margin: 0; border: 0; } }
            </style>
        </head>
        <body>
            <div class="acciones">
                <button onclick="window.print()">Imprimir</button>
                <a class="${enlaceCorreo ? "" : "inactivo"}" href="${enlaceCorreo || "#"}">Enviar por correo</a>
            </div>
            <div class="factura">
                <header>
                    <div>
                        <h1>${limpiarTextoVendedor(empresa.nombre_comercial || "Isa Boutique")}</h1>
                        <p><strong>${limpiarTextoVendedor(empresa.razon_social || "Isa Boutique")}</strong></p>
                        <p>NIT/Documento: ${limpiarTextoVendedor(empresa.nit || "Pendiente")}</p>
                        <p>${limpiarTextoVendedor(empresa.regimen || "Régimen no definido")}</p>
                        <p>${limpiarTextoVendedor(empresa.direccion || "Dirección pendiente")} - ${limpiarTextoVendedor(empresa.ciudad || "Colombia")}</p>
                        <p>Tel: ${limpiarTextoVendedor(empresa.telefono || "Pendiente")} | ${limpiarTextoVendedor(empresa.correo || "contacto@isaboutique.com")}</p>
                        <p class="muted">${limpiarTextoVendedor(empresa.actividad_economica || "Comercio al por menor")}</p>
                    </div>
                    <div class="factura-box">
                        <strong>${limpiarTextoVendedor(factura.numero_factura)}</strong>
                        <p>Venta: ${factura.id_venta}</p>
                        <p>Fecha: ${new Date(factura.fecha_factura).toLocaleString()}</p>
                        <p>Estado: ${limpiarTextoVendedor(factura.estado_factura)}</p>
                        <p>IVA incluido: ${factura.precio_incluye_iva ? "Sí" : "No"}</p>
                    </div>
                </header>

                <section class="grid">
                    <div class="panel">
                        <h2>Cliente</h2>
                        <p><strong>${limpiarTextoVendedor(factura.cliente)}</strong></p>
                        <p>Documento: ${limpiarTextoVendedor(factura.tipo_documento || "CC")} ${limpiarTextoVendedor(factura.numero_documento || "-")}</p>
                        <p>Teléfono: ${limpiarTextoVendedor(factura.telefono)}</p>
                        <p>Correo: ${limpiarTextoVendedor(factura.correo || "-")}</p>
                        <p>Dirección: ${limpiarTextoVendedor(factura.direccion || "-")}</p>
                    </div>
                    <div class="panel">
                        <h2>Operación</h2>
                        <p>Canal: ${etiquetaCanalVendedor(factura.canal_venta)}</p>
                        <p>Pago: ${limpiarTextoVendedor(factura.medio_pago)}</p>
                        <p>Entrega: ${limpiarTextoVendedor(factura.tipo_entrega || "-")}</p>
                        <p>Atendido por: ${limpiarTextoVendedor(factura.atendido_por)}</p>
                    </div>
                </section>

                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Color</th>
                            <th>Talla</th>
                            <th>Cant.</th>
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

                <div class="nota">${limpiarTextoVendedor(empresa.nota_legal || "")}</div>
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
        actualizarMetaDiariaVendedor(reportes.meta_diaria);
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
    const panelInicial = window.location.hash ? window.location.hash.substring(1) : "";

    if (panelInicial && document.getElementById(panelInicial)) {
        activarPanelVendedor(panelInicial, { omitirHistorial: true });
    } else {
        guardarRegresoInternoVendedor(panelActivoVendedor() || "panel-venta");
    }

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
document.getElementById("buscadorHistorialVendedor")?.addEventListener("input", function() {
    paginacionVendedor["historial-vendedor"] = 1;
    cargarHistorialVendedor();
});
document.getElementById("ventaProducto")?.addEventListener("change", llenarSelectorColores);
document.getElementById("ventaColor")?.addEventListener("change", llenarSelectorTallas);
document.getElementById("ventaTalla")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaCantidad")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("ventaDescuentoTipo")?.addEventListener("change", actualizarResumenVenta);
document.getElementById("ventaDescuentoValor")?.addEventListener("input", actualizarResumenVenta);
document.getElementById("formVenta")?.addEventListener("submit", registrarVentaVendedor);
document.getElementById("btnVolverPanelVendedor")?.addEventListener("click", volverPanelInternoVendedor);
document.getElementById("btnNotificacionesVendedor")?.addEventListener("click", function(evento) {
    evento.stopPropagation();
    document.getElementById("panelNotificacionesVendedor")?.classList.toggle("abierto");
});
document.addEventListener("click", function(evento) {
    const panel = document.getElementById("panelNotificacionesVendedor");
    const boton = document.getElementById("btnNotificacionesVendedor");

    if (!panel || !boton || panel.contains(evento.target) || boton.contains(evento.target)) return;
    panel.classList.remove("abierto");
});
document.getElementById("btnBuscarCodigoVenta")?.addEventListener("click", buscarProductoVentaPorCodigo);
document.getElementById("ventaCodigoBarras")?.addEventListener("keydown", function(evento) {
    if (evento.key === "Enter") {
        evento.preventDefault();
        buscarProductoVentaPorCodigo();
    }
});
window.registrarDevolucionVendedor = registrarDevolucionVendedor;
window.abrirFacturaVendedor = abrirFacturaVendedor;
window.alternarDetallePedidoVendedor = alternarDetallePedidoVendedor;
window.revisarPedidoPendienteVendedor = revisarPedidoPendienteVendedor;



