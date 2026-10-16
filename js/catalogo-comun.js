export function crearCatalogo(entorno, estado, acciones = {}) {
    const {document, window, localStorage, sessionStorage, crypto, TextEncoder, FormData, fetch, alert, console} = entorno;
    // Funciones compartidas por el catalogo general y las categorias.

    let envioCarritoEnCurso = false;
    class PedidoPendienteError extends Error {}

    async function intencionPedidoCarrito(datos) {
        const bytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(datos)));
        const firma = Array.from(new Uint8Array(bytes), valor => valor.toString(16).padStart(2,'0')).join('');
        let guardado;
        try { guardado = JSON.parse(localStorage.getItem('isa_pedido_pendiente') || 'null'); }
        catch (error) { throw new PedidoPendienteError('No se pudo recuperar el intento anterior. Confirma su estado con la tienda antes de volver a comprar.'); }
        if (guardado) {
            if (guardado.firma !== firma || !/^[a-f0-9]{32}$/.test(guardado.clave || '')) {
                throw new PedidoPendienteError('Hay un pedido anterior sin confirmar. Reintenta con el carrito y los datos originales antes de cambiarlos.');
            }
            return guardado.clave;
        }
        const clave = Array.from(crypto.getRandomValues(new Uint8Array(16)), valor => valor.toString(16).padStart(2,'0')).join('');
        localStorage.setItem('isa_pedido_pendiente',JSON.stringify({clave,firma}));
        return clave;
    }

    async function finalizarCompraCarrito() {
        if (envioCarritoEnCurso) return;
        const mensaje = document.getElementById('mensajeCarrito');
        const boton = document.getElementById('btnComprarCarrito');
        const datos = {
            cliente:document.getElementById('carritoCliente').value.trim(),
            telefono:document.getElementById('carritoTelefono').value.trim(),
            correo:document.getElementById('carritoCorreo').value.trim(),
            medio_pago:document.getElementById('carritoMedioPago').value,
            tipo_entrega:document.getElementById('carritoEntrega').value,
            items:estado.carrito.map(item=>({id_producto:Number(item.id_producto),id_producto_color:Number(item.id_producto_color||0),
                talla:String(item.talla||'').toUpperCase(),cantidad:Number(item.cantidad)}))
                .sort((a,b)=>a.id_producto-b.id_producto || a.id_producto_color-b.id_producto_color || a.talla.localeCompare(b.talla))
        };
        mensaje.textContent = '';
        if (!datos.items.length || !datos.cliente || !datos.telefono || !datos.medio_pago || !datos.tipo_entrega) {
            mensaje.textContent = 'Completa el carrito y tus datos para confirmar el pedido.';
            return;
        }
        envioCarritoEnCurso = true;
        boton.disabled = true;
        try {
            const clave = await intencionPedidoCarrito(datos);
            const formulario = new FormData();
            Object.entries(datos).forEach(([nombre,valor])=>formulario.append(nombre,nombre==='items'?JSON.stringify(valor):valor));
            formulario.append('clave_pedido',clave);
            const headers = {};
            const csrf = document.cookie.split(';').map(item=>item.trim()).find(item=>item.startsWith('isa_csrf_token='));
            if (csrf) headers['X-CSRF-Token'] = decodeURIComponent(csrf.slice('isa_csrf_token='.length));
            const respuesta = await fetch('../php/pedido_carrito_registrar.php',{method:'POST',body:formulario,headers});
            const resultado = await respuesta.json();
            if (resultado.error || !respuesta.ok) {
                if (resultado.conservar_clave === false) localStorage.removeItem('isa_pedido_pendiente');
                mensaje.textContent = resultado.mensaje || 'No se pudo confirmar el pedido. Vuelve a intentar sin cambiar el carrito.';
                return;
            }
            estado.carrito = [];
            guardarCarrito();
            localStorage.removeItem('isa_pedido_pendiente');
            acciones.renderizarCarrito();
            acciones.cerrarCarrito();
            alert(resultado.mensaje);
            if (acciones.cargarProductos) await acciones.cargarProductos();
            else await cargarCategoria();
        } catch (error) {
            mensaje.textContent = error instanceof PedidoPendienteError ? error.message : 'No se pudo confirmar el pedido. Conserva el carrito y vuelve a intentar sin cambiar los datos.';
            console.error(error);
        } finally {
            envioCarritoEnCurso = false;
            boton.disabled = false;
        }
    }

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
        if (typeof ruta !== "string" || !ruta || /^(?:javascript|data|vbscript):/i.test(ruta.trim())) return "../img/logo.png.png";
        if (ruta.startsWith("http") || ruta.startsWith("../")) return ruta;
        if (ruta.startsWith("img/")) return "../" + ruta;
        return ruta;
    }

    function leerImagenesProducto(imagenes) {
        return leerListaCatalogo(imagenes);
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
            return '<button type="button" class="producto-thumb ' + (indice === 0 ? "activo" : "") + '" data-img="' + escaparHtmlCatalogo(rutaImagenProducto(ruta)) + '">' +
                '<img src="' + escaparHtmlCatalogo(rutaImagenProducto(ruta)) + '" alt="' + escaparHtmlCatalogo(producto.nombre) + ' foto ' + (indice + 1) + '">' +
            '</button>';
        }).join("") + '</div>';
    }

    function mostrarImagenCarrusel() {
        const modal = document.getElementById("modalImagenProducto");
        const imagenGrande = document.getElementById("imagenProductoGrande");
        const contador = document.getElementById("contadorImagenProducto");
        const anterior = document.getElementById("btnImagenAnterior");
        const siguiente = document.getElementById("btnImagenSiguiente");

        if (estado.imagenesCarrusel.length === 0) return;

        const actual = estado.imagenesCarrusel[estado.indiceImagenCarrusel];
        imagenGrande.src = actual.src;
        imagenGrande.alt = actual.alt || "Producto Isa Boutique";
        contador.textContent = (estado.indiceImagenCarrusel + 1) + " / " + estado.imagenesCarrusel.length;
        anterior.style.display = estado.imagenesCarrusel.length > 1 ? "grid" : "none";
        siguiente.style.display = estado.imagenesCarrusel.length > 1 ? "grid" : "none";
        modal.classList.add("activo");
        modal.setAttribute("aria-hidden", "false");
    }

    function abrirImagenProducto(imagen) {
        const card = imagen.closest(".producto-card");
        const imagenes = Array.from(card.querySelectorAll(".producto-thumb img"));

        estado.imagenesCarrusel = imagenes.length > 0
            ? imagenes.map(function(item) {
                return { src: item.src, alt: item.alt };
            })
            : [{ src: imagen.src, alt: imagen.alt }];

        estado.indiceImagenCarrusel = Math.max(0, estado.imagenesCarrusel.findIndex(function(item) {
            return item.src === imagen.src;
        }));

        mostrarImagenCarrusel();
    }

    function cambiarImagenCarrusel(direccion) {
        if (estado.imagenesCarrusel.length <= 1) return;
        estado.indiceImagenCarrusel = (estado.indiceImagenCarrusel + direccion + estado.imagenesCarrusel.length) % estado.imagenesCarrusel.length;
        mostrarImagenCarrusel();
    }

    function cerrarImagenProducto() {
        const modal = document.getElementById("modalImagenProducto");
        const imagenGrande = document.getElementById("imagenProductoGrande");

        modal.classList.remove("activo");
        modal.setAttribute("aria-hidden", "true");
        imagenGrande.src = "";
        estado.imagenesCarrusel = [];
        estado.indiceImagenCarrusel = 0;
    }

    function leerTallas(tallas) {
        return leerListaCatalogo(tallas);
    }

    function leerColores(colores) {
        return leerListaCatalogo(colores);
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
                return '<span class="producto-color-dot" style="--dot-color:' + valorColorVisual(nombre) + '" title="' + escaparHtmlCatalogo(nombre) + '"></span>';
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
                return escaparHtmlCatalogo(item.talla) + " agotada";
            }

            if (cantidad < 4) {
                return escaparHtmlCatalogo(item.talla) + " (ultimas " + cantidad + ")";
            }

            return escaparHtmlCatalogo(item.talla);
        }).join(", ");
    }

    function colorTieneStock(color) {
        return leerTallas(color ? color.tallas : []).some(function(item) {
            return Number(item.cantidad) > 0;
        });
    }

    function actualizarContadorCarrito() {
        const total = estado.carrito.reduce(function(suma, item) {
            return suma + Number(item.cantidad);
        }, 0);

        document.getElementById("cartCount").textContent = total;
    }

    function guardarCarrito() {
        localStorage.setItem("isa_carrito", JSON.stringify(estado.carrito));
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
        const total = estado.productoSeleccionado ? Number(estado.productoSeleccionado.precio) * cantidad : 0;
        const base = total / 1.19;
        const iva = total - base;

        contenedor.innerHTML = `
            <div><span>Total estimado</span><strong>${formatoPrecio(total)}</strong></div>
            <div><span>IVA incluido</span><strong>${formatoPrecio(iva)}</strong></div>
            <p>Tu pedido queda pendiente hasta que Isa Boutique confirme el pago.</p>
        `;
    }

    function escaparHtmlCatalogo(valor) {
        return String(valor ?? "").replace(/[&<>"']/g, function(caracter) {
            return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[caracter];
        });
    }

    function leerListaCatalogo(valor) {
        try {
            const datos = typeof valor === "string" ? JSON.parse(valor) : valor;
            return Array.isArray(datos) ? datos.filter(item => item && typeof item === "object" && !Array.isArray(item)) : [];
        } catch (error) {
            return [];
        }
    }

    function cargarCarritoCatalogo() {
        try {
            return leerListaCatalogo(localStorage.getItem("isa_carrito")).filter(function(item) {
                return Number.isInteger(Number(item.id_producto)) && Number(item.id_producto) > 0
                    && Number.isInteger(Number(item.cantidad)) && Number(item.cantidad) > 0
                    && Number.isFinite(Number(item.precio)) && Number(item.precio) >= 0;
            });
        } catch (error) {
            return [];
        }
    }

    return {
        intencionPedidoCarrito, finalizarCompraCarrito, configurarFlechaRegresoPublica, formatoPrecio, rutaImagenProducto, leerImagenesProducto, imagenPrincipalProducto, imagenesProductoPublicas, miniaturasProducto, mostrarImagenCarrusel, abrirImagenProducto, cambiarImagenCarrusel, cerrarImagenProducto, leerTallas, leerColores, obtenerColorCompra, textoColores, valorColorVisual, puntosColoresProducto, textoTallas, colorTieneStock, actualizarContadorCarrito, guardarCarrito, llenarColoresCompra, llenarTallasCompra, actualizarResumenCompraPublica, escaparHtmlCatalogo, leerListaCatalogo, cargarCarritoCatalogo
    };
}
