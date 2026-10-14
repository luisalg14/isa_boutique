// Paginacion e impresion compartidas por los paneles internos.

function crearPaginacionTablas(estado) {
    function obtener(clave) {
        return Number(estado[clave] || 1);
    }

    function cambiar(clave, pagina, renderizar) {
        estado[clave] = pagina;
        renderizar();
    }

    function preparar(datos, clave, porPagina, cuerpoTabla, renderizar) {
        const totalPaginas = Math.max(1, Math.ceil(datos.length / porPagina));
        let paginaActual = obtener(clave);

        if (paginaActual > totalPaginas) {
            paginaActual = totalPaginas;
            estado[clave] = paginaActual;
        }

        if (paginaActual < 1) {
            paginaActual = 1;
            estado[clave] = paginaActual;
        }

        renderizarPaginacion(clave, datos.length, porPagina, paginaActual, cuerpoTabla, renderizar);

        const inicio = (paginaActual - 1) * porPagina;
        return datos.slice(inicio, inicio + porPagina);
    }

    function renderizarPaginacion(clave, totalRegistros, porPagina, paginaActual, cuerpoTabla, renderizar) {
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
                cambiar(clave, pagina, renderizar);
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

    function ocultar(clave, cuerpoTabla) {
        const tabla = cuerpoTabla ? cuerpoTabla.closest("table") : null;
        const contenedor = tabla ? tabla.nextElementSibling : null;

        if (contenedor && contenedor.dataset.pagination === clave) {
            contenedor.hidden = true;
            contenedor.innerHTML = "";
        }
    }

    return { obtener, cambiar, preparar, renderizar: renderizarPaginacion, ocultar };
}

function imprimirFacturaComun(factura, detalles, empresa, { limpiarTexto, formatoPrecio, etiquetaCanal }) {
    empresa = empresa || {};
    const filas = detalles.map(function(detalle) {
        return `
            <tr>
                <td>${limpiarTexto(detalle.codigo)}</td>
                <td>${limpiarTexto(detalle.producto)}</td>
                <td>${limpiarTexto(detalle.color || "-")}</td>
                <td>${limpiarTexto(detalle.talla || "-")}</td>
                <td>${detalle.cantidad}</td>
                <td>${formatoPrecio(detalle.precio_unitario)}</td>
                <td>${formatoPrecio(detalle.iva)}</td>
                <td>${formatoPrecio(detalle.subtotal)}</td>
            </tr>
        `;
    }).join("");

    const correoCliente = String(factura.correo || "");
    const asuntoCorreo = encodeURIComponent("Factura " + factura.numero_factura + " - Isa Boutique");
    const cuerpoCorreo = encodeURIComponent(
        "Hola " + (factura.cliente || "") + ",\n\n" +
        "Adjuntamos/compartimos la información de tu factura " + factura.numero_factura + ".\n" +
        "Total: " + formatoPrecio(factura.total) + "\n\n" +
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
            <title>${limpiarTexto(factura.numero_factura)}</title>
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
                        <h1>${limpiarTexto(empresa.nombre_comercial || "Isa Boutique")}</h1>
                        <p><strong>${limpiarTexto(empresa.razon_social || "Isa Boutique")}</strong></p>
                        <p>NIT/Documento: ${limpiarTexto(empresa.nit || "Pendiente")}</p>
                        <p>${limpiarTexto(empresa.regimen || "Régimen no definido")}</p>
                        <p>${limpiarTexto(empresa.direccion || "Dirección pendiente")} - ${limpiarTexto(empresa.ciudad || "Colombia")}</p>
                        <p>Tel: ${limpiarTexto(empresa.telefono || "Pendiente")} | ${limpiarTexto(empresa.correo || "contacto@isaboutique.com")}</p>
                        <p class="muted">${limpiarTexto(empresa.actividad_economica || "Comercio al por menor")}</p>
                    </div>
                    <div class="factura-box">
                        <strong>${limpiarTexto(factura.numero_factura)}</strong>
                        <p>Venta: ${factura.id_venta}</p>
                        <p>Fecha: ${new Date(factura.fecha_factura).toLocaleString()}</p>
                        <p>Estado: ${limpiarTexto(factura.estado_factura)}</p>
                        <p>IVA incluido: ${factura.precio_incluye_iva ? "Sí" : "No"}</p>
                    </div>
                </header>

                <section class="grid">
                    <div class="panel">
                        <h2>Cliente</h2>
                        <p><strong>${limpiarTexto(factura.cliente)}</strong></p>
                        <p>Documento: ${limpiarTexto(factura.tipo_documento || "CC")} ${limpiarTexto(factura.numero_documento || "-")}</p>
                        <p>Teléfono: ${limpiarTexto(factura.telefono)}</p>
                        <p>Correo: ${limpiarTexto(factura.correo || "-")}</p>
                        <p>Dirección: ${limpiarTexto(factura.direccion || "-")}</p>
                    </div>
                    <div class="panel">
                        <h2>Operación</h2>
                        <p>Canal: ${etiquetaCanal(factura.canal_venta)}</p>
                        <p>Pago: ${limpiarTexto(factura.medio_pago)}</p>
                        <p>Entrega: ${limpiarTexto(factura.tipo_entrega || "-")}</p>
                        <p>Atendido por: ${limpiarTexto(factura.atendido_por)}</p>
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
                    <div><span>Subtotal bruto</span><strong>${formatoPrecio(factura.subtotal)}</strong></div>
                    <div><span>Descuento</span><strong>${formatoPrecio(factura.descuento)}</strong></div>
                    <div><span>Base gravable</span><strong>${formatoPrecio(factura.base_gravable)}</strong></div>
                    <div><span>IVA (${Number(factura.tarifa_iva || 19).toFixed(0)}%)</span><strong>${formatoPrecio(factura.iva)}</strong></div>
                    <div class="total"><span>Total</span><strong>${formatoPrecio(factura.total)}</strong></div>
                </div>

                <div class="nota">${limpiarTexto(empresa.nota_legal || "")}</div>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
}
