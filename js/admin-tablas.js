function crearTablasAdmin(entorno, acciones) {
    const {document, fetch, console} = entorno;
    const {prepararPaginaAdmin, ocultarPaginacionAdmin, usuarioEsAdmin, editarProductoBD, cambiarEstadoProductoBD, eliminarProductoBD, abrirCodigosBarrasVariantesProducto} = acciones;

    function limpiarTextoBD(valor) {
        return String(valor || "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function formatoPrecioBD(valor) {
        return "$" + Number(valor || 0).toLocaleString("es-CO", {
            maximumFractionDigits: 0
        }) + " COP";
    }

    function leerTallasBD(tallas) {
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

    function leerColoresBD(colores) {
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

    function textoColoresBD(producto) {
        const colores = leerColoresBD(producto ? producto.colores : []);

        if (colores.length === 0) {
            return producto && producto.color ? producto.color : "-";
        }

        return colores.map(function(item) {
            return item.color;
        }).join(", ");
    }

    function leerImagenesBD(imagenes) {
        if (Array.isArray(imagenes)) return imagenes;

        if (typeof imagenes === "string" && imagenes.trim() !== "") {
            try {
                return JSON.parse(imagenes);
            } catch (error) {
                return [];
            }
        }

        return [];
    }

    function rutaImagenAdmin(producto) {
        const imagenes = leerImagenesBD(producto ? producto.imagenes : []);
        const principal = imagenes.find(function(item) {
            return item.principal === true || item.principal === "t" || item.principal === 1;
        });
        const ruta = (principal && principal.ruta) || (imagenes[0] && imagenes[0].ruta) || (producto ? producto.imagen : "");

        if (typeof ruta !== "string" || !ruta || /^(?:javascript|data|vbscript):/i.test(ruta.trim())) return "../img/logo.png.png";
        if (ruta.startsWith("http") || ruta.startsWith("../")) return ruta;
        if (ruta.startsWith("img/")) return "../" + ruta;
        return ruta;
    }

    function textoTallasBD(tallas) {
        const lista = leerTallasBD(tallas);

        if (lista.length === 0) {
            return "";
        }

        return lista.map(function(item) {
            return item.talla + ":" + item.cantidad;
        }).join(", ");
    }

    function codigosBarrasVariantesBD(producto) {
        const variantes = [];
        const colores = leerColoresBD(producto ? producto.colores : []);

        colores.forEach(function(color) {
            leerTallasBD(color.tallas).forEach(function(talla) {
                if (!talla.codigo_barras) return;

                variantes.push({
                    codigo: talla.codigo_barras,
                    nombre: producto.nombre || "Producto",
                    etiqueta: (color.color || "Color") + " / " + (talla.talla || "Talla"),
                    detalle: "Color: " + (color.color || "-")
                        + " | Talla: " + (talla.talla || "-")
                        + " | Stock: " + Number(talla.cantidad || 0)
                });
            });
        });

        if (variantes.length === 0 && producto && producto.codigo) {
            variantes.push({
                codigo: producto.codigo,
                nombre: producto.nombre || "Producto",
                etiqueta: "Producto",
                detalle: "Código general del producto."
            });
        }

        return variantes;
    }

    async function cargarInventarioBD() {
        const tabla = document.getElementById("tablaInventario");
        const buscador = document.getElementById("buscador");
        
        if (!tabla) return;
        
        const textoBusqueda = buscador ? buscador.value.toLowerCase() : "";
        
        try {
            const respuesta = await fetch("../php/productos_listar.php");
            const productos = await respuesta.json();
            
            tabla.innerHTML = "";
            
            if (productos.error) {
                tabla.innerHTML = `
                <tr>
                    <td colspan="13">${limpiarTextoBD(productos.mensaje)}</td>
                </tr>
                `;
                return;
            }
            
            const productosFiltrados = productos.filter(function(producto) {
                const codigo = producto.codigo ? producto.codigo.toLowerCase() : "";
                const codigosVariantes = codigosBarrasVariantesBD(producto).map(function(variante) {
                    return variante.codigo;
                }).join(" ").toLowerCase();
                const nombre = producto.nombre ? producto.nombre.toLowerCase() : "";
                const marca = producto.marca ? producto.marca.toLowerCase() : "";
                const color = textoColoresBD(producto).toLowerCase();
                const categoria = producto.categoria ? producto.categoria.toLowerCase() : "";
                const estado = producto.estado ? producto.estado.toLowerCase() : "";
                
                return (
                    codigo.includes(textoBusqueda) ||
                    codigosVariantes.includes(textoBusqueda) ||
                    nombre.includes(textoBusqueda) ||
                    marca.includes(textoBusqueda) ||
                    color.includes(textoBusqueda) ||
                    categoria.includes(textoBusqueda) ||
                    estado.includes(textoBusqueda)
                );
            });
            
            if (productosFiltrados.length === 0) {
                tabla.innerHTML = `
                <tr>
                    <td colspan="13">No se encontraron productos</td>
                </tr>
                `;
                ocultarPaginacionAdmin("inventario", tabla);
                return;
            }
            
            prepararPaginaAdmin(productosFiltrados, "inventario", 20, tabla, cargarInventarioBD).forEach(function(producto) {
                let claseEstado = "stock-alto";
                const variantesCodigo = codigosBarrasVariantesBD(producto);
                const accionesAdmin = usuarioEsAdmin()
                    ? `
                        <button type="button" data-inventario="editar">Editar</button>
                        <button type="button" data-inventario="estado">
                            ${producto.estado === "inactivo" ? "Activar" : "Desactivar"}</button>
                        <button type="button" class="btn-eliminar" data-inventario="eliminar">Eliminar</button>
                    `
                    : `<span>Solo lectura</span>`;
                
                if (producto.estado === "inactivo" || producto.estado === "agotado") {
                    claseEstado = "stock-bajo";
                } else if (Number(producto.cantidad) <= 3) {
                    claseEstado = "stock-bajo";
                } else if (Number(producto.cantidad) <= 7) {
                    claseEstado = "stock-medio";
                }
                
                const fila = document.createElement("tr");
                fila.innerHTML = `
                    <td>
                        <div class="inventory-product-cell">
                            <img src="${limpiarTextoBD(rutaImagenAdmin(producto))}" alt="${limpiarTextoBD(producto.nombre)}">
                            <div>
                                <strong>${limpiarTextoBD(producto.nombre)}</strong>
                                <span>Código: ${limpiarTextoBD(producto.codigo)}</span>
                                <span>Marca: ${limpiarTextoBD(producto.marca || "-")}</span>
                                <span>Color: ${limpiarTextoBD(textoColoresBD(producto) || "-")}</span>
                            </div>
                        </div>
                    </td>
                    <td>${limpiarTextoBD(producto.categoria)}</td>
                    <td>${formatoPrecioBD(producto.precio)}</td>
                    <td>${formatoPrecioBD(producto.costo_unitario || 0)}</td>
                    <td>${formatoPrecioBD(Number(producto.precio) - Number(producto.costo_unitario || 0))}</td>
                    <td>${limpiarTextoBD(producto.cantidad)}</td>
                    <td>${limpiarTextoBD(textoTallasBD(producto.tallas) || "-")}</td>
                    <td class="${claseEstado}">${limpiarTextoBD(producto.estado)}</td>
                    <td>
                        <button type="button" class="btn-barcode-producto" data-inventario="variantes">Variantes</button>
                    </td>
                    <td>${accionesAdmin}</td>
                `;
                fila.querySelector('[data-inventario="variantes"]').addEventListener("click", function() {
                    abrirCodigosBarrasVariantesProducto(variantesCodigo, producto.nombre || "Producto");
                });
                if (usuarioEsAdmin()) {
                    fila.querySelector('[data-inventario="editar"]').addEventListener("click", function() { editarProductoBD(Number(producto.id_producto)); });
                    fila.querySelector('[data-inventario="estado"]').addEventListener("click", function() { cambiarEstadoProductoBD(Number(producto.id_producto), producto.estado); });
                    fila.querySelector('[data-inventario="eliminar"]').addEventListener("click", function() { eliminarProductoBD(Number(producto.id_producto), producto.nombre || ""); });
                }
                tabla.appendChild(fila);
            });
        
        } catch (error) {
            tabla.innerHTML = `
            <tr>
                <td colspan="10">Error al cargar inventario desde PostgreSQL</td>
            </tr>
            `;
            console.error(error);
        }
    }
    
    async function cargarMovimientosBD() {
        const tabla = document.getElementById("tablaMovimientos");
        const buscador = document.getElementById("buscadorMovimientos");

        if (!tabla) return;

        try {
            const respuesta = await fetch("../php/movimientos_listar.php");
            const movimientos = await respuesta.json();  
        
            tabla.innerHTML = "";

            if (movimientos.error) {
                tabla.innerHTML = `
                <tr>
                    <td colspan="7">${limpiarTextoBD(movimientos.mensaje)}</td>
                </tr>
                `;
                return;
            }

            if (movimientos.length === 0) {
                tabla.innerHTML = `
                <tr>
                    <td colspan="7">No hay movimientos registrados</td>
                </tr>
                `;
                ocultarPaginacionAdmin("movimientos", tabla);
                return;
            }

            const textoBusqueda = buscador ? buscador.value.toLowerCase().trim() : "";
            const movimientosFiltrados = textoBusqueda
                ? movimientos.filter(function(movimiento) {
                    const fecha = movimiento.fecha ? new Date(movimiento.fecha).toLocaleString() : "";
                    const texto = [
                        fecha,
                        movimiento.tipo,
                        movimiento.codigo,
                        movimiento.producto,
                        movimiento.marca,
                        movimiento.cantidad,
                        movimiento.detalle,
                        movimiento.usuario
                    ].join(" ").toLowerCase();

                    return texto.includes(textoBusqueda);
                })
                : movimientos;

            if (movimientosFiltrados.length === 0) {
                tabla.innerHTML = `
                <tr>
                    <td colspan="7">No hay movimientos que coincidan con la búsqueda</td>
                </tr>
                `;
                ocultarPaginacionAdmin("movimientos", tabla);
                return;
            }
            
            prepararPaginaAdmin(movimientosFiltrados, "movimientos", 30, tabla, cargarMovimientosBD).forEach(function(movimiento) {
                tabla.innerHTML += `
                <tr>
                    <td>${new Date(movimiento.fecha).toLocaleString()}</td>
                    <td>${limpiarTextoBD(movimiento.tipo)}</td>
                    <td>${limpiarTextoBD(movimiento.codigo || "Sin código")}</td>
                    <td>${limpiarTextoBD(movimiento.producto)}</td>
                    <td>${limpiarTextoBD(movimiento.marca || "Sin marca")}</td>
                    <td>${limpiarTextoBD(movimiento.cantidad)}</td>
                    <td>${limpiarTextoBD(movimiento.detalle || "Sin detalle")}</td>
                </tr>
                `;
            });

        } catch (error) {
            tabla.innerHTML = `
            <tr>
                <td colspan="7">Error al cargar movimientos desde PostgreSQL</td>
            </tr>
            `;
            console.error(error);
        }
    }

    async function cargarTrabajadoresBD() {
        const tabla = document.getElementById("tablaTrabajadores");
        const selector = document.getElementById("pagoTrabajadorSelect");

        if (!tabla || !selector || !usuarioEsAdmin()) return;

        try {
            const respuesta = await fetch("../php/trabajadores_listar.php");
            const trabajadores = await respuesta.json();

            tabla.innerHTML = "";
            selector.innerHTML = '<option value="">Seleccionar trabajador</option>';

            if (trabajadores.error) {
                tabla.innerHTML = `<tr><td colspan="7">${limpiarTextoBD(trabajadores.mensaje)}</td></tr>`;
                return;
            }

            if (trabajadores.length === 0) {
                tabla.innerHTML = '<tr><td colspan="7">No hay trabajadores registrados</td></tr>';
                ocultarPaginacionAdmin("trabajadores", tabla);
                return;
            }

            trabajadores.forEach(function(trabajador) {
                if (trabajador.estado) {
                    const opcion = document.createElement("option");
                    opcion.value = trabajador.id_trabajador;
                    opcion.textContent = trabajador.nombre + " - " + trabajador.cargo;
                    selector.appendChild(opcion);
                }
            });

            prepararPaginaAdmin(trabajadores, "trabajadores", 20, tabla, cargarTrabajadoresBD).forEach(function(trabajador) {
                tabla.innerHTML += `
                    <tr>
                        <td>${limpiarTextoBD(trabajador.nombre)}</td>
                        <td>${limpiarTextoBD(trabajador.documento || "-")}</td>
                        <td>${limpiarTextoBD(trabajador.telefono || "-")}</td>
                        <td>${limpiarTextoBD(trabajador.cargo)}</td>
                        <td>${formatoPrecioBD(trabajador.salario_base)}</td>
                        <td>${new Date(trabajador.fecha_ingreso + "T00:00:00").toLocaleDateString()}</td>
                        <td class="${trabajador.estado ? "stock-alto" : "stock-bajo"}">${trabajador.estado ? "Activo" : "Inactivo"}</td>
                    </tr>
                `;
            });
        } catch (error) {
            tabla.innerHTML = '<tr><td colspan="7">Error al cargar trabajadores</td></tr>';
            console.error(error);
        }
    }

    async function cargarPagosTrabajadoresBD() {
        const tabla = document.getElementById("tablaPagosTrabajadores");

        if (!tabla || !usuarioEsAdmin()) return;

        try {
            const respuesta = await fetch("../php/pagos_trabajador_listar.php");
            const pagos = await respuesta.json();

            tabla.innerHTML = "";

            if (pagos.error) {
                tabla.innerHTML = `<tr><td colspan="7">${limpiarTextoBD(pagos.mensaje)}</td></tr>`;
                return;
            }

            if (pagos.length === 0) {
                tabla.innerHTML = '<tr><td colspan="7">No hay pagos registrados</td></tr>';
                ocultarPaginacionAdmin("pagos-trabajadores", tabla);
                return;
            }

            prepararPaginaAdmin(pagos, "pagos-trabajadores", 20, tabla, cargarPagosTrabajadoresBD).forEach(function(pago) {
                tabla.innerHTML += `
                    <tr>
                        <td>${new Date(pago.fecha + "T00:00:00").toLocaleDateString()}</td>
                        <td>${limpiarTextoBD(pago.trabajador)}</td>
                        <td>${limpiarTextoBD(pago.cargo)}</td>
                        <td>${limpiarTextoBD(pago.tipo_pago)}</td>
                        <td class="stock-bajo">${formatoPrecioBD(pago.valor)}</td>
                        <td>${limpiarTextoBD(pago.usuario || "Sin usuario")}</td>
                        <td>${limpiarTextoBD(pago.detalle || "Sin detalle")}</td>
                    </tr>
                `;
            });
        } catch (error) {
            tabla.innerHTML = '<tr><td colspan="7">Error al cargar pagos</td></tr>';
            console.error(error);
        }
    }

    return {limpiarTextoBD, formatoPrecioBD, leerTallasBD, leerColoresBD, textoColoresBD, leerImagenesBD, rutaImagenAdmin, textoTallasBD, codigosBarrasVariantesBD, cargarInventarioBD, cargarMovimientosBD, cargarTrabajadoresBD, cargarPagosTrabajadoresBD};
}

if (typeof module !== "undefined" && module.exports) {
    module.exports = {crearTablasAdmin};
}
