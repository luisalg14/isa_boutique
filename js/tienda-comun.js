// Funciones compartidas de la tienda publica.

function configurarFlechaRegresoPublica() {
    const flecha = document.querySelector(".nav-home-arrow");
    if (!flecha) return;

    let destino = "index.html";

    try {
        const regreso = JSON.parse(sessionStorage.getItem("isa_internal_return") || "null");
        if (regreso && regreso.url && (regreso.tipo === "admin" || regreso.tipo === "vendedor")) {
            destino = regreso.url;
        }
    } catch (error) {
        destino = "index.html";
    }

    flecha.href = destino;
    flecha.title = destino === "index.html" ? "Volver al inicio" : "Volver al panel interno";
}

function formatoPrecio(valor) {
    return "$" + Number(valor).toLocaleString("es-CO");
}

function rutaImagenProducto(ruta) {
    if (!ruta) return "../img/logo.png.png";
    if (ruta.startsWith("http") || ruta.startsWith("../")) return ruta;
    if (ruta.startsWith("img/")) return "../" + ruta;
    return ruta;
}

function leerListaProducto(valor) {
    if (Array.isArray(valor)) return valor;

    if (typeof valor === "string" && valor.trim() !== "") {
        try {
            const lista = JSON.parse(valor);
            return Array.isArray(lista) ? lista : [];
        } catch {
            return [];
        }
    }

    return [];
}

function leerImagenesProducto(imagenes) {
    return leerListaProducto(imagenes);
}

function leerCarritoGuardado() {
    try {
        return leerListaProducto(localStorage.getItem("isa_carrito"));
    } catch {
        return [];
    }
}

function escaparHtmlProducto(valor) {
    return String(valor ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function imagenPrincipalProducto(producto) {
    const imagenes = leerImagenesProducto(producto.imagenes);
    const principal = imagenes.find(function(item) {
        return item.principal === true || item.principal === "t" || item.principal === 1;
    });

    return (principal && principal.ruta) || (imagenes[0] && imagenes[0].ruta) || producto.imagen;
}

function imagenesProductoPublicas(producto) {
    const imagenes = leerImagenesProducto(producto.imagenes)
        .map(function(item) {
            return item.ruta;
        })
        .filter(Boolean);

    if (imagenes.length === 0 && producto.imagen) {
        imagenes.push(producto.imagen);
    }

    return [...new Set(imagenes)];
}

function miniaturasProducto(producto) {
    const imagenes = imagenesProductoPublicas(producto);

    if (imagenes.length <= 1) return "";

    return '<div class="producto-thumbs">' + imagenes.map(function(ruta, indice) {
        const rutaSegura = escaparHtmlProducto(rutaImagenProducto(ruta));
        return '<button type="button" class="producto-thumb ' + (indice === 0 ? "activo" : "") + '" data-img="' + rutaSegura + '">' +
            '<img src="' + rutaSegura + '" alt="' + escaparHtmlProducto(producto.nombre) + ' foto ' + (indice + 1) + '">' +
        '</button>';
    }).join("") + '</div>';
}

function mostrarImagenCarrusel() {
    const modal = document.getElementById("modalImagenProducto");
    const imagenGrande = document.getElementById("imagenProductoGrande");
    const contador = document.getElementById("contadorImagenProducto");
    const anterior = document.getElementById("btnImagenAnterior");
    const siguiente = document.getElementById("btnImagenSiguiente");

    if (imagenesCarrusel.length === 0) return;

    const actual = imagenesCarrusel[indiceImagenCarrusel];
    imagenGrande.src = actual.src;
    imagenGrande.alt = actual.alt || "Producto Isa Boutique";
    contador.textContent = (indiceImagenCarrusel + 1) + " / " + imagenesCarrusel.length;
    anterior.style.display = imagenesCarrusel.length > 1 ? "grid" : "none";
    siguiente.style.display = imagenesCarrusel.length > 1 ? "grid" : "none";
    modal.classList.add("activo");
    modal.setAttribute("aria-hidden", "false");
}

function abrirImagenProducto(imagen) {
    const card = imagen.closest(".producto-card");
    const imagenes = Array.from(card.querySelectorAll(".producto-thumb img"));

    imagenesCarrusel = imagenes.length > 0
        ? imagenes.map(function(item) {
            return { src: item.src, alt: item.alt };
        })
        : [{ src: imagen.src, alt: imagen.alt }];

    indiceImagenCarrusel = Math.max(0, imagenesCarrusel.findIndex(function(item) {
        return item.src === imagen.src;
    }));

    mostrarImagenCarrusel();
}

function cambiarImagenCarrusel(direccion) {
    if (imagenesCarrusel.length <= 1) return;
    indiceImagenCarrusel = (indiceImagenCarrusel + direccion + imagenesCarrusel.length) % imagenesCarrusel.length;
    mostrarImagenCarrusel();
}

function cerrarImagenProducto() {
    const modal = document.getElementById("modalImagenProducto");
    const imagenGrande = document.getElementById("imagenProductoGrande");

    modal.classList.remove("activo");
    modal.setAttribute("aria-hidden", "true");
    imagenGrande.src = "";
    imagenesCarrusel = [];
    indiceImagenCarrusel = 0;
}

function leerTallas(tallas) {
    return leerListaProducto(tallas);
}

function leerColores(colores) {
    return leerListaProducto(colores);
}

function obtenerColorCompra(producto) {
    const selector = document.getElementById("compraColor");
    const idColor = Number(selector?.value || 0);
    const colores = leerColores(producto ? producto.colores : []);

    return colores.find(function(item) {
        return Number(item.id_producto_color) === idColor;
    }) || null;
}

function textoColores(producto) {
    const colores = leerColores(producto ? producto.colores : []);

    if (colores.length === 0) {
        return producto && producto.color ? producto.color : "";
    }

    return colores.map(function(item) {
        return item.color;
    }).join(", ");
}

function valorColorVisual(nombre) {
    const color = (nombre || "").toLowerCase().trim();
    const mapa = {
        amarillo: "#f5c84b",
        azul: "#2f6db5",
        beige: "#d9c6a5",
        blanco: "#ffffff",
        cafe: "#7a4f35",
        café: "#7a4f35",
        dorado: "#c9a66b",
        fucsia: "#c23a7a",
        gris: "#9ca3af",
        morado: "#7c3a8a",
        naranja: "#e98235",
        negro: "#171717",
        rojo: "#c0393f",
        rosado: "#e8a5b5",
        rosa: "#e8a5b5",
        verde: "#3f8f5f",
        vinotinto: "#7c3344",
        vino: "#7c3344"
    };

    return mapa[color] || "#d9c1c3";
}

function puntosColoresProducto(producto) {
    const colores = leerColores(producto ? producto.colores : []);
    const nombres = colores.length > 0
        ? colores.map(function(item) { return item.color; })
        : (producto && producto.color ? [producto.color] : []);

    const unicos = [...new Set(nombres.filter(Boolean))];

    if (unicos.length === 0) {
        return '<div class="producto-color-swatches producto-color-swatches-vacio">Color por confirmar</div>';
    }

    return '<div class="producto-color-swatches" aria-label="Colores disponibles">' +
        unicos.slice(0, 7).map(function(nombre) {
            return '<span class="producto-color-dot" style="--dot-color:' + valorColorVisual(nombre) + '" title="' + escaparHtmlProducto(nombre) + '"></span>';
        }).join("") +
        (unicos.length > 7 ? '<small>+' + (unicos.length - 7) + '</small>' : '') +
    '</div>';
}

function textoTallas(tallas) {
    const lista = leerTallas(tallas);

    if (lista.length === 0) return "Sin tallas disponibles";

    return "Tallas disponibles: " + lista.map(function(item) {
        const cantidad = Number(item.cantidad);

        if (cantidad <= 0) {
            return item.talla + " agotada";
        }

        if (cantidad < 4) {
            return item.talla + " (ultimas " + cantidad + ")";
        }

        return item.talla;
    }).join(", ");
}

function colorTieneStock(color) {
    return leerTallas(color ? color.tallas : []).some(function(item) {
        return Number(item.cantidad) > 0;
    });
}

function actualizarContadorCarrito() {
    const total = carrito.reduce(function(suma, item) {
        return suma + Number(item.cantidad);
    }, 0);

    document.getElementById("cartCount").textContent = total;
}

function guardarCarrito() {
    localStorage.setItem("isa_carrito", JSON.stringify(carrito));
    actualizarContadorCarrito();
}

function llenarColoresCompra(producto) {
    const selector = document.getElementById("compraColor");
    selector.innerHTML = '<option value="">Seleccionar color</option>';

    const colores = leerColores(producto.colores);

    if (colores.length === 0 && producto.color) {
        const opcion = document.createElement("option");
        opcion.value = "";
        opcion.textContent = producto.color;
        selector.appendChild(opcion);
    }

    colores.forEach(function(item) {
        const opcion = document.createElement("option");
        opcion.value = item.id_producto_color;
        const disponible = colorTieneStock(item);
        opcion.textContent = disponible ? item.color : item.color + " - agotado";
        opcion.disabled = !disponible;
        selector.appendChild(opcion);
    });

    const primeraDisponible = Array.from(selector.options).find(function(opcion) {
        return opcion.value !== "" && !opcion.disabled;
    });

    if (primeraDisponible) {
        selector.value = primeraDisponible.value;
    } else if (selector.options.length === 2) {
        selector.selectedIndex = 1;
    }

    llenarTallasCompra(producto);
}

function llenarTallasCompra(producto) {
    const input = document.getElementById("compraTalla");
    const contenedor = document.getElementById("compraTallaOpciones");
    input.value = "";
    contenedor.innerHTML = "";

    const colorSeleccionado = obtenerColorCompra(producto);
    const tallas = colorSeleccionado ? colorSeleccionado.tallas : producto.tallas;

    leerTallas(tallas)
        .forEach(function(item) {
            const cantidad = Number(item.cantidad);
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "size-option";
            boton.dataset.talla = item.talla;
            boton.dataset.cantidad = item.cantidad;
            boton.disabled = cantidad <= 0;
            boton.textContent = cantidad > 0 && cantidad < 4
                ? item.talla + " - ultimas " + cantidad
                : item.talla;

            if (cantidad <= 0) {
                boton.classList.add("agotada");
                boton.title = "Talla agotada";
            }

            boton.addEventListener("click", function() {
                if (boton.disabled) return;
                input.value = item.talla;
                document.getElementById("compraCantidad").max = item.cantidad;
                contenedor.querySelectorAll(".size-option").forEach(function(opcion) {
                    opcion.classList.toggle("activo", opcion === boton);
                });
            });
            contenedor.appendChild(boton);
        });

    const primera = contenedor.querySelector(".size-option:not(:disabled)");
    if (primera) {
        primera.click();
    }
}

function actualizarResumenCompraPublica() {
    const contenedor = document.getElementById("resumenCompraPublica");
    if (!contenedor) return;

    const cantidad = Number(document.getElementById("compraCantidad")?.value || 1);
    const total = productoSeleccionado ? Number(productoSeleccionado.precio) * cantidad : 0;
    const base = total / 1.19;
    const iva = total - base;

    contenedor.innerHTML = `
        <div><span>Total estimado</span><strong>${formatoPrecio(total)}</strong></div>
        <div><span>IVA incluido</span><strong>${formatoPrecio(iva)}</strong></div>
        <p>Tu pedido queda pendiente hasta que Isa Boutique confirme el pago.</p>
    `;
}

function agregarProductoAlCarrito() {
    if (!productoSeleccionado) return;

    const cantidad = parseInt(document.getElementById("compraCantidad").value);
    const talla = document.getElementById("compraTalla").value;
    const colorSeleccionado = obtenerColorCompra(productoSeleccionado);
    const mensaje = document.getElementById("mensajeCompra");

    if (!talla) {
        mensaje.textContent = "Selecciona una talla.";
        return;
    }

    if (isNaN(cantidad) || cantidad <= 0) {
        mensaje.textContent = "Ingresa una cantidad valida.";
        return;
    }

    const tallas = colorSeleccionado ? colorSeleccionado.tallas : productoSeleccionado.tallas;
    const tallaSeleccionada = leerTallas(tallas).find(function(item) {
        return item.talla === talla;
    });

    const existente = carrito.find(function(item) {
        return Number(item.id_producto) === Number(productoSeleccionado.id_producto)
            && item.talla === talla
            && String(item.id_producto_color || "") === String(colorSeleccionado ? colorSeleccionado.id_producto_color : "");
    });
    const cantidadFinal = Number(existente ? existente.cantidad : 0) + cantidad;

    if (!tallaSeleccionada || cantidadFinal > Number(tallaSeleccionada.cantidad)) {
        mensaje.textContent = "No hay suficientes unidades en esa talla.";
        return;
    }

    if (existente) {
        existente.cantidad = cantidadFinal;
    } else {
        carrito.push({
            id_producto: productoSeleccionado.id_producto,
            id_producto_color: colorSeleccionado ? colorSeleccionado.id_producto_color : "",
            codigo: productoSeleccionado.codigo,
            nombre: productoSeleccionado.nombre,
            precio: productoSeleccionado.precio,
            color: colorSeleccionado ? colorSeleccionado.color : document.getElementById("compraColor").selectedOptions[0]?.textContent || "",
            talla: talla,
            cantidad: cantidad
        });
    }

    guardarCarrito();
    cerrarModalCompra();
    abrirCarrito();
}

function renderizarCarrito() {
    const lista = document.getElementById("listaCarrito");
    const datosCliente = document.getElementById("datosClienteCarrito");
    const botonComprar = document.getElementById("btnComprarCarrito");
    const total = carrito.reduce(function(suma, item) {
        return suma + Number(item.precio) * Number(item.cantidad);
    }, 0);

    if (carrito.length === 0) {
        lista.innerHTML = '<p>Tu carrito esta vacio.</p>';
        document.getElementById("totalCarrito").textContent = "$0";
        datosCliente.style.display = "none";
        botonComprar.style.display = "none";
        return;
    }

    datosCliente.style.display = "grid";
    botonComprar.style.display = "";
    lista.innerHTML = "";

    carrito.forEach(function(item, indice) {
        const fila = document.createElement("div");
        fila.className = "cart-item";
        fila.innerHTML =
            '<div>' +
                '<strong>' + escaparHtmlProducto(item.nombre) + '</strong>' +
                '<p>Color: ' + escaparHtmlProducto(item.color || '-') + ' | Talla: ' + escaparHtmlProducto(item.talla) + ' | Cantidad: ' + escaparHtmlProducto(item.cantidad) + '</p>' +
                '<p>' + formatoPrecio(Number(item.precio) * Number(item.cantidad)) + '</p>' +
            '</div>' +
            '<button type="button">Quitar</button>';

        fila.querySelector("button").addEventListener("click", function() {
            carrito.splice(indice, 1);
            guardarCarrito();
            renderizarCarrito();
        });

        lista.appendChild(fila);
    });

    document.getElementById("totalCarrito").textContent = "Total carrito: " + formatoPrecio(total);
}

async function finalizarCompraCarritoComun(recargar) {
    const mensaje = document.getElementById("mensajeCarrito");
    const cliente = document.getElementById("carritoCliente").value.trim();
    const telefono = document.getElementById("carritoTelefono").value.trim();
    const correo = document.getElementById("carritoCorreo").value.trim();
    const medioPago = document.getElementById("carritoMedioPago").value;
    const tipoEntrega = document.getElementById("carritoEntrega").value;

    mensaje.textContent = "";

    if (carrito.length === 0) {
        mensaje.textContent = "Tu carrito está vacío.";
        return;
    }

    if (!cliente || !telefono || !medioPago || !tipoEntrega) {
        mensaje.textContent = "Completa tus datos para finalizar la compra.";
        return;
    }

    try {
        for (const item of carrito) {
            const datos = new FormData();
            datos.append("id_producto", item.id_producto);
            datos.append("id_producto_color", item.id_producto_color || "");
            datos.append("color", item.color || "");
            datos.append("talla", item.talla);
            datos.append("cantidad", item.cantidad);
            datos.append("cliente", cliente);
            datos.append("telefono", telefono);
            datos.append("correo", correo);
            datos.append("medio_pago", medioPago);
            datos.append("canal_venta", "pagina_web");
            datos.append("tipo_entrega", tipoEntrega);

            const respuesta = await fetch("../php/venta_registrar.php", {
                method: "POST",
                body: datos
            });
            const resultado = await respuesta.json();

            if (resultado.error) {
                mensaje.textContent = resultado.mensaje;
                return;
            }
        }

        carrito = [];
        guardarCarrito();
        renderizarCarrito();
        cerrarCarrito();
        await recargar();
        alert("Compra registrada correctamente. Tu pedido queda pendiente de confirmación.");
    } catch (error) {
        mensaje.textContent = "Error al finalizar la compra.";
        console.error(error);
    }
}
