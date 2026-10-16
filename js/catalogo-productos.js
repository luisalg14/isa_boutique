import {crearCatalogo} from "./catalogo-comun.js?v=20261016-modulos";

export function iniciarProductos(entorno) {
    const {document, window, URLSearchParams, fetch, alert, console} = entorno;
    const estado = {
        get carrito() { return carrito; }, set carrito(valor) { carrito = valor; },
        get productoSeleccionado() { return productoSeleccionado; }, set productoSeleccionado(valor) { productoSeleccionado = valor; },
        get imagenesCarrusel() { return imagenesCarrusel; }, set imagenesCarrusel(valor) { imagenesCarrusel = valor; },
        get indiceImagenCarrusel() { return indiceImagenCarrusel; }, set indiceImagenCarrusel(valor) { indiceImagenCarrusel = valor; }
    };
    const compartido = crearCatalogo(entorno, estado, {renderizarCarrito, cerrarCarrito, cargarProductos});
    const {
        finalizarCompraCarrito, configurarFlechaRegresoPublica, formatoPrecio, rutaImagenProducto, imagenPrincipalProducto, miniaturasProducto, abrirImagenProducto, cambiarImagenCarrusel, cerrarImagenProducto, leerTallas, obtenerColorCompra, puntosColoresProducto, textoTallas, actualizarContadorCarrito, guardarCarrito, llenarColoresCompra, llenarTallasCompra, actualizarResumenCompraPublica, escaparHtmlCatalogo, cargarCarritoCatalogo
    } = compartido;
    configurarFlechaRegresoPublica();

    let productoSeleccionado = null;
    let productosCatalogo = [];
    let categoriasCatalogo = [];
    let carrito = cargarCarritoCatalogo();
    let imagenesCarrusel = [];
    let indiceImagenCarrusel = 0;
    let paginaCatalogo = 1;
    const productosPorPaginaCatalogo = 100;

    const contenedor = document.getElementById("contenedor-productos");
    const buscador = document.getElementById("buscarProducto");
    const filtroCategoria = document.getElementById("filtroCategoria");
    const ordenProductos = document.getElementById("ordenProductos");
    const filtroPrecioMaximo = document.getElementById("filtroPrecioMaximo");
    const precioMaximoTexto = document.getElementById("precioMaximoTexto");
    const precioMinimoTexto = document.getElementById("precioMinimoTexto");
    const precioTopeTexto = document.getElementById("precioTopeTexto");
    const soloDisponibles = document.getElementById("soloDisponibles");
    const resumenCatalogo = document.getElementById("resumenCatalogo");

    function formatearCategoria(categoria) {
        const encontrada = categoriasCatalogo.find(function(item) {
            return item.nombre === categoria;
        });

        if (encontrada) {
            return encontrada.nombre.replaceAll("-", " ").replace(/\b\w/g, function(letra) {
                return letra.toUpperCase();
            });
        }

        return categoria ? categoria.replaceAll("-", " ") : "Sin categoría";
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
                    '<strong>' + escaparHtmlCatalogo(item.nombre) + '</strong>' +
                    '<p>Color: ' + escaparHtmlCatalogo(item.color || '-') + ' | Talla: ' + escaparHtmlCatalogo(item.talla) + ' | Cantidad: ' + escaparHtmlCatalogo(item.cantidad) + '</p>' +
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

    function abrirCarrito() {
        renderizarCarrito();
        const modal = document.getElementById("modalCarrito");
        modal.classList.add("activo");
        modal.setAttribute("aria-hidden", "false");
    }

    function cerrarCarrito() {
        const modal = document.getElementById("modalCarrito");
        modal.classList.remove("activo");
        modal.setAttribute("aria-hidden", "true");
    }

    function abrirModalCompra(producto) {
        if (Number(producto.cantidad) <= 0 || producto.estado === "agotado") {
            alert("Este producto esta agotado.");
            return;
        }

        productoSeleccionado = producto;
        document.getElementById("resumenProducto").textContent =
            producto.nombre + " - " + formatoPrecio(producto.precio) + " | Disponibles: " + producto.cantidad;
        document.getElementById("compraCantidad").value = "1";
        document.getElementById("compraCantidad").max = producto.cantidad;
        llenarColoresCompra(producto);
        document.getElementById("mensajeCompra").textContent = "";
        actualizarResumenCompraPublica();

        const modal = document.getElementById("modalCompra");
        modal.classList.add("activo");
        modal.setAttribute("aria-hidden", "false");
    }

    function cerrarModalCompra() {
        const modal = document.getElementById("modalCompra");
        modal.classList.remove("activo");
        modal.setAttribute("aria-hidden", "true");
        productoSeleccionado = null;
    }

    function agregarProductoAlCarrito() {
        if (!productoSeleccionado) return;

        const cantidad = Number.parseInt(document.getElementById("compraCantidad").value);
        const talla = document.getElementById("compraTalla").value;
        const colorSeleccionado = obtenerColorCompra(productoSeleccionado);
        const mensaje = document.getElementById("mensajeCompra");

        if (!talla) {
            mensaje.textContent = "Selecciona una talla.";
            return;
        }

        if (Number.isNaN(cantidad) || cantidad <= 0) {
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


    function obtenerProductosFiltrados() {
        const texto = buscador.value.trim().toLowerCase();
        const categoria = filtroCategoria.value;
        const soloStock = soloDisponibles.checked;
        const precioMaximo = Number(filtroPrecioMaximo.value || 0);
        const aplicarPrecio = precioMaximo > 0 && precioMaximo < Number(filtroPrecioMaximo.max || 0);

        let productos = productosCatalogo.filter(function(producto) {
            const activo = producto.estado !== "inactivo";
            const disponible = Number(producto.cantidad) > 0 && producto.estado !== "agotado";
            const precioProducto = Number(producto.precio || 0);
            const coincideTexto = (
                (producto.nombre || "").toLowerCase().includes(texto) ||
                (producto.marca || "").toLowerCase().includes(texto) ||
                (producto.codigo || "").toLowerCase().includes(texto)
            );
            const coincideCategoria = !categoria || producto.categoria === categoria;
            const coincidePrecio = !aplicarPrecio || precioProducto <= precioMaximo;

            return activo && coincideTexto && coincideCategoria && coincidePrecio && (!soloStock || disponible);
        });

        if (ordenProductos.value === "precio_asc") {
            productos.sort(function(a, b) { return Number(a.precio) - Number(b.precio); });
        } else if (ordenProductos.value === "precio_desc") {
            productos.sort(function(a, b) { return Number(b.precio) - Number(a.precio); });
        } else if (ordenProductos.value === "stock_desc") {
            productos.sort(function(a, b) { return Number(b.cantidad) - Number(a.cantidad); });
        }

        return productos;
    }

    function renderizarPaginacionCatalogo(totalProductos) {
        let paginacion = document.getElementById("paginacionCatalogo");

        if (!paginacion) {
            paginacion = document.createElement("div");
            paginacion.id = "paginacionCatalogo";
            paginacion.className = "pagination-control public-pagination";
            contenedor.insertAdjacentElement("afterend", paginacion);
        }

        const totalPaginas = Math.ceil(totalProductos / productosPorPaginaCatalogo);
        paginacion.innerHTML = "";

        if (totalPaginas <= 1) {
            paginacion.hidden = true;
            return;
        }

        paginacion.hidden = false;

        if (paginaCatalogo > totalPaginas) {
            paginaCatalogo = totalPaginas;
        }

        const crearBoton = function(texto, pagina, activo, deshabilitado) {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.textContent = texto;
            boton.className = activo ? "activo" : "";
            boton.disabled = deshabilitado;
            boton.addEventListener("click", function() {
                paginaCatalogo = pagina;
                renderizarProductos();
                contenedor.scrollIntoView({ behavior: "smooth", block: "start" });
            });
            paginacion.appendChild(boton);
        };

        crearBoton("‹", paginaCatalogo - 1, false, paginaCatalogo === 1);

        const inicio = Math.max(1, paginaCatalogo - 2);
        const fin = Math.min(totalPaginas, paginaCatalogo + 2);

        if (inicio > 1) {
            crearBoton("1", 1, paginaCatalogo === 1, false);
            if (inicio > 2) {
                const puntos = document.createElement("span");
                puntos.textContent = "...";
                paginacion.appendChild(puntos);
            }
        }

        for (let pagina = inicio; pagina <= fin; pagina++) {
            crearBoton(String(pagina), pagina, pagina === paginaCatalogo, false);
        }

        if (fin < totalPaginas) {
            if (fin < totalPaginas - 1) {
                const puntos = document.createElement("span");
                puntos.textContent = "...";
                paginacion.appendChild(puntos);
            }
            crearBoton(String(totalPaginas), totalPaginas, paginaCatalogo === totalPaginas, false);
        }

        crearBoton("Next ›", paginaCatalogo + 1, false, paginaCatalogo === totalPaginas);
    }

    function reiniciarPaginaCatalogo() {
        paginaCatalogo = 1;
        renderizarProductos();
    }

    function actualizarTextoPrecioMaximo() {
        const valor = Number(filtroPrecioMaximo.value || 0);
        const maximo = Number(filtroPrecioMaximo.max || 0);

        if (!maximo || valor >= maximo) {
            precioMaximoTexto.textContent = "Todos";
            return;
        }

        precioMaximoTexto.textContent = "Hasta " + formatoPrecio(valor);
    }

    function configurarRangoPrecioCatalogo() {
        const productosActivos = productosCatalogo.filter(function(producto) {
            return producto.estado !== "inactivo";
        });
        const precios = productosActivos
            .map(function(producto) {
                return Number(producto.precio || 0);
            })
            .filter(function(precio) {
                return precio > 0;
            });

        if (precios.length === 0) {
            filtroPrecioMaximo.disabled = true;
            filtroPrecioMaximo.min = 0;
            filtroPrecioMaximo.max = 0;
            filtroPrecioMaximo.value = 0;
            precioMinimoTexto.textContent = "$0";
            precioTopeTexto.textContent = "$0";
            precioMaximoTexto.textContent = "Sin precios";
            return;
        }

        const minimo = Math.min(...precios);
        const maximo = Math.max(...precios);
        const maximoRedondeado = Math.ceil(maximo / 1000) * 1000;

        filtroPrecioMaximo.disabled = false;
        filtroPrecioMaximo.min = Math.floor(minimo / 1000) * 1000;
        filtroPrecioMaximo.max = maximoRedondeado;
        filtroPrecioMaximo.value = maximoRedondeado;
        precioMinimoTexto.textContent = formatoPrecio(minimo);
        precioTopeTexto.textContent = formatoPrecio(maximo);
        actualizarTextoPrecioMaximo();
    }

    async function cargarCategoriasCatalogo() {
        try {
            const respuesta = await fetch("../php/categorias_listar.php?solo_activas=1");
            const categorias = await respuesta.json();

            if (categorias.error) {
                console.error(categorias.mensaje);
                return;
            }

            categoriasCatalogo = categorias;
            filtroCategoria.innerHTML = '<option value="">Todas</option>';
            document.getElementById("categoriaChips").innerHTML = '<button class="chip activo" data-categoria="">Todo</button>';

            categorias.forEach(function(categoria) {
                const texto = formatearCategoria(categoria.nombre);
                const opcion = document.createElement("option");
                opcion.value = categoria.nombre;
                opcion.textContent = texto;
                filtroCategoria.appendChild(opcion);

                const chip = document.createElement("button");
                chip.className = "chip";
                chip.dataset.categoria = categoria.nombre;
                chip.textContent = texto;
                chip.addEventListener("click", function() {
                    filtroCategoria.value = chip.dataset.categoria;
                    document.querySelectorAll(".chip").forEach(function(item) {
                        item.classList.remove("activo");
                    });
                    chip.classList.add("activo");
                    reiniciarPaginaCatalogo();
                });
                document.getElementById("categoriaChips").appendChild(chip);
            });

            document.querySelector('.chip[data-categoria=""]')?.addEventListener("click", function() {
                filtroCategoria.value = "";
                document.querySelectorAll(".chip").forEach(function(item) {
                    item.classList.toggle("activo", item.dataset.categoria === "");
                });
                reiniciarPaginaCatalogo();
            });
        } catch (error) {
            console.error("Error al cargar categorías", error);
        }
    }

    function renderizarProductos() {
        const productos = obtenerProductosFiltrados();
        const totalVisibles = productosCatalogo.filter(function(producto) {
            return producto.estado !== "inactivo";
        }).length;

        contenedor.innerHTML = "";
        resumenCatalogo.textContent = productos.length + " de " + totalVisibles + " productos visibles";

        if (productos.length === 0) {
            contenedor.innerHTML = '<div class="estado-vacio">No encontramos productos con esos filtros.</div>';
            renderizarPaginacionCatalogo(0);
            return;
        }

        const totalPaginas = Math.max(1, Math.ceil(productos.length / productosPorPaginaCatalogo));
        if (paginaCatalogo > totalPaginas) paginaCatalogo = totalPaginas;

        const inicio = (paginaCatalogo - 1) * productosPorPaginaCatalogo;
        const productosPagina = productos.slice(inicio, inicio + productosPorPaginaCatalogo);

        productosPagina.forEach(function(producto) {
            const agotado = Number(producto.cantidad) <= 0 || producto.estado === "agotado";
            const estadoTexto = agotado ? "Agotado" : "Disponible";
            const card = document.createElement("article");

            card.className = "card producto-card";
            card.innerHTML =
                '<div class="producto-imagen-wrap">' +
                    '<img class="producto-img-click" src="' + escaparHtmlCatalogo(rutaImagenProducto(imagenPrincipalProducto(producto))) + '" alt="' + escaparHtmlCatalogo(producto.nombre) + '">' +
                    '<span class="producto-badge ' + (agotado ? "agotado" : "disponible") + '">' + estadoTexto + '</span>' +
                '</div>' +
                miniaturasProducto(producto) +
                '<div class="producto-card-body">' +
                    '<p class="producto-codigo">Código: ' + escaparHtmlCatalogo(producto.codigo) + '</p>' +
                    '<h3>' + escaparHtmlCatalogo(producto.nombre) + '</h3>' +
                    '<p class="producto-meta">' + escaparHtmlCatalogo(producto.marca) + '  -  ' + escaparHtmlCatalogo(formatearCategoria(producto.categoria)) + '</p>' +
                    puntosColoresProducto(producto) +
                    '<p class="producto-precio">' + formatoPrecio(producto.precio) + '</p>' +
                    '<p class="producto-stock">' + estadoTexto + '</p>' +
                    '<p class="producto-stock">' + textoTallas(producto.tallas) + '</p>' +
                    '<button class="boton-comprar" ' + (agotado ? "disabled" : "") + '>' +
                        (agotado ? "Agotado" : "Comprar") +
                    '</button>' +
                '</div>';

            card.querySelector(".boton-comprar").addEventListener("click", function() {
                abrirModalCompra(producto);
            });

            contenedor.appendChild(card);
        });

        renderizarPaginacionCatalogo(productos.length);
    }

    async function cargarProductos() {
        contenedor.innerHTML = '<div class="estado-vacio">Cargando productos...</div>';

        try {
            const respuesta = await fetch("../php/productos_listar.php");
            const productos = await respuesta.json();

            if (productos.error) {
                contenedor.innerHTML = "<p>Error: " + productos.mensaje + "</p>";
                return;
            }

            productosCatalogo = productos;
            configurarRangoPrecioCatalogo();
            renderizarProductos();
        } catch (error) {
            contenedor.innerHTML = "<p>Error al cargar productos desde la base de datos.</p>";
            console.error(error);
        }
    }

    document.getElementById("formCompra").addEventListener("submit", function(evento) {
        evento.preventDefault();
        agregarProductoAlCarrito();
    });
    document.getElementById("btnCancelarCompra").addEventListener("click", cerrarModalCompra);
    document.getElementById("btnAgregarCarrito").addEventListener("click", agregarProductoAlCarrito);
    document.getElementById("btnAbrirCarrito").addEventListener("click", abrirCarrito);
    document.getElementById("compraColor").addEventListener("change", function() {
        if (productoSeleccionado) {
            llenarTallasCompra(productoSeleccionado);
            actualizarResumenCompraPublica();
        }
    });
    document.getElementById("compraCantidad").addEventListener("input", actualizarResumenCompraPublica);
    document.getElementById("btnCerrarCarrito").addEventListener("click", cerrarCarrito);
    document.getElementById("btnComprarCarrito").addEventListener("click", finalizarCompraCarrito);
    document.getElementById("btnCerrarImagenProducto").addEventListener("click", cerrarImagenProducto);
    document.getElementById("btnImagenAnterior").addEventListener("click", function() {
        cambiarImagenCarrusel(-1);
    });
    document.getElementById("btnImagenSiguiente").addEventListener("click", function() {
        cambiarImagenCarrusel(1);
    });
    document.getElementById("btnVaciarCarrito").addEventListener("click", function() {
        carrito = [];
        guardarCarrito();
        renderizarCarrito();
    });
    document.getElementById("modalCompra").addEventListener("click", function(evento) {
        if (evento.target.id === "modalCompra") {
            cerrarModalCompra();
        }
    });
    document.getElementById("modalCarrito").addEventListener("click", function(evento) {
        if (evento.target.id === "modalCarrito") {
            cerrarCarrito();
        }
    });
    document.getElementById("modalImagenProducto").addEventListener("click", function(evento) {
        if (evento.target.id === "modalImagenProducto") {
            cerrarImagenProducto();
        }
    });
    contenedor.addEventListener("click", function(evento) {
        const miniatura = evento.target.closest(".producto-thumb");
        if (miniatura) {
            const card = miniatura.closest(".producto-card");
            const imagenPrincipal = card.querySelector(".producto-img-click");
            imagenPrincipal.src = miniatura.dataset.img;
            card.querySelectorAll(".producto-thumb").forEach(function(item) {
                item.classList.toggle("activo", item === miniatura);
            });
            return;
        }

        const imagen = evento.target.closest(".producto-img-click");
        if (imagen) {
            abrirImagenProducto(imagen);
        }
    });

    buscador.addEventListener("input", reiniciarPaginaCatalogo);
    filtroCategoria.addEventListener("change", function() {
        document.querySelectorAll(".chip").forEach(function(chip) {
            chip.classList.toggle("activo", chip.dataset.categoria === filtroCategoria.value);
        });
        reiniciarPaginaCatalogo();
    });
    ordenProductos.addEventListener("change", reiniciarPaginaCatalogo);
    filtroPrecioMaximo.addEventListener("input", function() {
        actualizarTextoPrecioMaximo();
        reiniciarPaginaCatalogo();
    });
    soloDisponibles.addEventListener("change", reiniciarPaginaCatalogo);

    actualizarContadorCarrito();
    cargarCategoriasCatalogo().then(cargarProductos).catch(error => console.error(error));

    return {...compartido, formatearCategoria, renderizarCarrito, abrirCarrito, cerrarCarrito, abrirModalCompra, cerrarModalCompra, agregarProductoAlCarrito, obtenerProductosFiltrados, renderizarPaginacionCatalogo, reiniciarPaginaCatalogo, actualizarTextoPrecioMaximo, configurarRangoPrecioCatalogo, cargarCategoriasCatalogo, renderizarProductos, cargarProductos};
}
