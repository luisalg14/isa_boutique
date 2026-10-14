        configurarFlechaRegresoPublica();

        let productoSeleccionado = null;
        let productosCategoriaActual = [];
        let categoriasDisponibles = [];
        let carrito = leerCarritoGuardado();
        let imagenesCarrusel = [];
        let indiceImagenCarrusel = 0;
        let paginaCategoria = 1;
        const productosPorPaginaCategoria = 100;

        function abrirModalCompra(producto) {
            if (Number(producto.cantidad) <= 0 || producto.estado === "agotado") {
                alert("Este producto esta agotado.");
                return;
            }

            productoSeleccionado = producto;
            document.getElementById("resumenProducto").textContent =
                producto.nombre + " - " + formatoPrecio(producto.precio);
            document.getElementById("compraCantidad").value = "1";
            document.getElementById("compraCantidad").max = producto.cantidad;
            llenarColoresCompra(producto);
            document.getElementById("mensajeCompra").textContent = "";
            actualizarResumenCompraPublica();
            document.getElementById("modalCompra").classList.add("activo");
        }

        function cerrarModalCompra() {
            document.getElementById("modalCompra").classList.remove("activo");
            productoSeleccionado = null;
        }

        function abrirCarrito() {
            renderizarCarrito();
            document.getElementById("modalCarrito").classList.add("activo");
        }

        function cerrarCarrito() {
            document.getElementById("modalCarrito").classList.remove("activo");
        }

        function obtenerCategoriaDesdeURL() {
            const parametros = new URLSearchParams(window.location.search);
            return parametros.get("tipo");
        }

        function etiquetaCategoria(nombre) {
            return String(nombre || "")
                .replace(/_/g, " ")
                .replace(/\b\w/g, function(letra) {
                    return letra.toUpperCase();
                });
        }

        async function cargarCategoriasDisponibles() {
            try {
                const respuesta = await fetch("../php/categorias_listar.php?solo_activas=1");
                const datos = await respuesta.json();

                if (Array.isArray(datos)) {
                    categoriasDisponibles = datos;
                }
            } catch (error) {
                categoriasDisponibles = [];
                console.error("No se pudieron cargar las categorías", error);
            }
        }

        function formatearNombreCategoria(categoria) {
            const categoriaBD = categoriasDisponibles.find(function(item) {
                return item.nombre === categoria;
            });

            return categoriaBD ? etiquetaCategoria(categoriaBD.nombre) : etiquetaCategoria(categoria || "Categoría");
        }

        function renderizarPaginacionCategoria(totalProductos) {
            const contenedor = document.getElementById("contenedor");
            let paginacion = document.getElementById("paginacionCategoria");

            if (!paginacion) {
                paginacion = document.createElement("div");
                paginacion.id = "paginacionCategoria";
                paginacion.className = "pagination-control public-pagination";
                contenedor.insertAdjacentElement("afterend", paginacion);
            }

            const totalPaginas = Math.ceil(totalProductos / productosPorPaginaCategoria);
            paginacion.innerHTML = "";

            if (totalPaginas <= 1) {
                paginacion.hidden = true;
                return;
            }

            paginacion.hidden = false;

            if (paginaCategoria > totalPaginas) {
                paginaCategoria = totalPaginas;
            }

            const crearBoton = function(texto, pagina, activo, deshabilitado) {
                const boton = document.createElement("button");
                boton.type = "button";
                boton.textContent = texto;
                boton.className = activo ? "activo" : "";
                boton.disabled = deshabilitado;
                boton.addEventListener("click", function() {
                    paginaCategoria = pagina;
                    renderizarProductosCategoria();
                    contenedor.scrollIntoView({ behavior: "smooth", block: "start" });
                });
                paginacion.appendChild(boton);
            };

            crearBoton("‹", paginaCategoria - 1, false, paginaCategoria === 1);

            const inicio = Math.max(1, paginaCategoria - 2);
            const fin = Math.min(totalPaginas, paginaCategoria + 2);

            if (inicio > 1) {
                crearBoton("1", 1, paginaCategoria === 1, false);
                if (inicio > 2) {
                    const puntos = document.createElement("span");
                    puntos.textContent = "...";
                    paginacion.appendChild(puntos);
                }
            }

            for (let pagina = inicio; pagina <= fin; pagina++) {
                crearBoton(String(pagina), pagina, pagina === paginaCategoria, false);
            }

            if (fin < totalPaginas) {
                if (fin < totalPaginas - 1) {
                    const puntos = document.createElement("span");
                    puntos.textContent = "...";
                    paginacion.appendChild(puntos);
                }
                crearBoton(String(totalPaginas), totalPaginas, paginaCategoria === totalPaginas, false);
            }

            crearBoton("Next ›", paginaCategoria + 1, false, paginaCategoria === totalPaginas);
        }

        function renderizarProductosCategoria() {
            const contenedor = document.getElementById("contenedor");
            contenedor.innerHTML = "";

            if (productosCategoriaActual.length === 0) {
                contenedor.innerHTML = '<p style="text-align:center;">No hay productos registrados en esta categoría.</p>';
                renderizarPaginacionCategoria(0);
                return;
            }

            const totalPaginas = Math.max(1, Math.ceil(productosCategoriaActual.length / productosPorPaginaCategoria));
            if (paginaCategoria > totalPaginas) paginaCategoria = totalPaginas;

            const inicio = (paginaCategoria - 1) * productosPorPaginaCategoria;
            const productosPagina = productosCategoriaActual.slice(inicio, inicio + productosPorPaginaCategoria);

            productosPagina.forEach(function(producto) {
                const estadoTexto = Number(producto.cantidad) === 0 ? "agotado" : producto.estado;
                const agotado = Number(producto.cantidad) <= 0 || producto.estado === "agotado";

                const card = document.createElement("article");
                card.className = "card producto-card";
                card.innerHTML =
                    '<div class="producto-imagen-wrap">' +
                        '<img class="producto-img-click" src="' + rutaImagenProducto(imagenPrincipalProducto(producto)) + '" alt="' + producto.nombre + '">' +
                    '</div>' +
                    miniaturasProducto(producto) +
                    '<div class="producto-card-body">' +
                        '<p class="producto-codigo">Código: ' + producto.codigo + '</p>' +
                        '<h3>' + producto.nombre + '</h3>' +
                        '<p class="producto-meta">' + producto.marca + '</p>' +
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

            renderizarPaginacionCategoria(productosCategoriaActual.length);
        }

        async function cargarCategoria() {
            const tipoCategoria = obtenerCategoriaDesdeURL();
            const contenedor = document.getElementById("contenedor");
            const titulo = document.getElementById("tituloCategoria");

            contenedor.innerHTML = "";

            if (!tipoCategoria) {
                titulo.innerText = "Categoría no encontrada";
                contenedor.innerHTML = '<p style="text-align:center;">No se seleccionó una categoría.</p>';
                return;
            }

            titulo.innerText = formatearNombreCategoria(tipoCategoria);

            if (categoriasDisponibles.length > 0) {
                const existeCategoria = categoriasDisponibles.some(function(item) {
                    return item.nombre === tipoCategoria;
                });

                if (!existeCategoria) {
                    titulo.innerText = "Categoría no disponible";
                    contenedor.innerHTML = '<p style="text-align:center;">Esta categoría no está activa en el inventario.</p>';
                    return;
                }
            }

            try {
                const respuesta = await fetch("../php/productos_listar.php");
                const productos = await respuesta.json();

                if (productos.error) {
                    contenedor.innerHTML = "<p>Error: " + productos.mensaje + "</p>";
                    return;
                }

                const productosCategoria = productos.filter(function(producto) {
                    return producto.categoria === tipoCategoria && producto.estado !== "inactivo";
                });
                productosCategoriaActual = productosCategoria;

                if (productosCategoria.length === 0) {
                    contenedor.innerHTML = '<p style="text-align:center;">No hay productos registrados en esta categoría.</p>';
                    renderizarPaginacionCategoria(0);
                    return;
                }

                paginaCategoria = 1;
                renderizarProductosCategoria();

            } catch (error) {
                contenedor.innerHTML = "<p>Error al cargar productos desde la base de datos.</p>";
                console.error(error);
            }
        }

        async function finalizarCompraCarrito() {
            await finalizarCompraCarritoComun(cargarCategoria);
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

        actualizarContadorCarrito();
        cargarCategoriasDisponibles().then(cargarCategoria);
    
