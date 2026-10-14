        configurarFlechaRegresoPublica();

        // Lógica existente del carrito
        const carritoInicio = leerCarritoGuardado();
        const totalCarritoInicio = carritoInicio.reduce(function(suma, item) {
            return suma + Number(item.cantidad);
        }, 0);
        document.getElementById("cartCount").textContent = totalCarritoInicio;

        // NUEVO: Lógica del Carrusel del Hero
        const slides = document.querySelectorAll(".hero-slide");
        const indicators = document.querySelectorAll(".hero-indicators .indicator");
        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove("active"));
            indicators.forEach(ind => ind.classList.remove("active"));
            
            slides[index].classList.add("active");
            indicators[index].classList.add("active");
            currentSlide = index;
        }

        function nextSlide() {
            let next = (currentSlide + 1) % slides.length;
            showSlide(next);
        }

        function startSlideShow() {
            slideInterval = setInterval(nextSlide, 6000); // 6 segundos por diapositiva
        }

        function resetSlideShow() {
            clearInterval(slideInterval);
            startSlideShow();
        }

        // Eventos para los indicadores
        indicators.forEach((indicator, index) => {
            indicator.addEventListener("click", () => {
                showSlide(index);
                resetSlideShow();
            });
        });

        // Iniciar slideshow
        startSlideShow();

        // NUEVO: Lógica de Scroll Reveal
        const revealElements = document.querySelectorAll(".reveal");
        const observerOptions = {
            root: null,
            threshold: 0.12,
            rootMargin: "0px 0px -50px 0px"
        };

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("reveal-active");
                    observer.unobserve(entry.target); // Solo revelar una vez
                }
            });
        }, observerOptions);

        revealElements.forEach(element => {
            revealObserver.observe(element);
        });
    
