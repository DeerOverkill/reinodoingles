/* ==========================================================================
   ESDD - Interactive Script File
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

    // 1. Mobile Drawer Navigation Toggler
    const burgerMenu = document.querySelector('.burger-menu');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');
    const drawerCloseBtn = document.querySelector('.drawer-close');
    const mobileLinks = document.querySelectorAll('.mobile-nav a');

    function toggleMenu(forceClose = false) {
        const isOpen = forceClose ? false : !mobileDrawer.classList.contains('active');
        burgerMenu.classList.toggle('active', isOpen);
        mobileDrawer.classList.toggle('active', isOpen);
        drawerOverlay.classList.toggle('active', isOpen);
        burgerMenu.setAttribute('aria-expanded', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    if (burgerMenu) burgerMenu.addEventListener('click', () => toggleMenu());
    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', () => toggleMenu(true));
    if (drawerOverlay) drawerOverlay.addEventListener('click', () => toggleMenu(true));
    
    // Close mobile menu on clicking any navigation anchor link
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => toggleMenu(true));
    });


    // 2. Scroll Header styling (blurred translucent overlay)
    const mainHeader = document.querySelector('.main-header');
    
    function checkScrollHeader() {
        if (window.scrollY > 40) {
            mainHeader.classList.add('scrolled');
        } else {
            mainHeader.classList.remove('scrolled');
        }
    }
    
    window.addEventListener('scroll', checkScrollHeader);
    checkScrollHeader(); // Run check on load


    // 3. Scroll Reveal Transitions using IntersectionObserver
    const revealElements = document.querySelectorAll('.scroll-reveal');
    
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                    // Stop observing once visible to maintain stability
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px'
        });
        
        revealElements.forEach(element => revealObserver.observe(element));
    } else {
        // Fallback for older browsers
        revealElements.forEach(element => element.classList.add('reveal-active'));
    }


    // 4. Custom Responsive Slider / Carousel for Course Cards
    const courseSlider = document.getElementById('courseSlider');
    const btnPrev = document.getElementById('sliderPrev');
    const btnNext = document.getElementById('sliderNext');
    const dotsContainer = document.getElementById('sliderDots');

    if (courseSlider) {
        const slides = Array.from(courseSlider.children);
        let itemsPerView = getItemsPerView();
        let totalSteps = Math.ceil(slides.length / itemsPerView);
        let currentStepIndex = 0;

        // Calculate visible items based on media query layout
        function getItemsPerView() {
            if (window.innerWidth >= 1024) return 3;
            if (window.innerWidth >= 768) return 2;
            return 1;
        }

        // Initialize carousel navigation indicator dots
        function buildDots() {
            dotsContainer.innerHTML = '';
            itemsPerView = getItemsPerView();
            totalSteps = Math.ceil(slides.length / itemsPerView);
            
            for (let i = 0; i < totalSteps; i++) {
                const dot = document.createElement('div');
                dot.classList.add('dot');
                if (i === currentStepIndex) dot.classList.add('active');
                dot.addEventListener('click', () => {
                    goToStep(i);
                });
                dotsContainer.appendChild(dot);
            }
        }

        function updateDots() {
            const dots = dotsContainer.querySelectorAll('.dot');
            dots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === currentStepIndex);
            });
        }

        function goToStep(stepIndex) {
            if (stepIndex < 0 || stepIndex >= totalSteps) return;
            currentStepIndex = stepIndex;
            
            // Calculate scroll target based on average width
            const containerWidth = courseSlider.clientWidth;
            const scrollTarget = stepIndex * containerWidth;
            
            courseSlider.scrollTo({
                left: scrollTarget,
                behavior: 'smooth'
            });
            
            updateDots();
        }

        if (btnNext) {
            btnNext.addEventListener('click', () => {
                if (currentStepIndex < totalSteps - 1) {
                    goToStep(currentStepIndex + 1);
                } else {
                    goToStep(0); // Loop back
                }
            });
        }

        if (btnPrev) {
            btnPrev.addEventListener('click', () => {
                if (currentStepIndex > 0) {
                    goToStep(currentStepIndex - 1);
                } else {
                    goToStep(totalSteps - 1); // Loop to end
                }
            });
        }

        // Update indicators if browser gets resized
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const oldItemsView = itemsPerView;
                itemsPerView = getItemsPerView();
                if (oldItemsView !== itemsPerView) {
                    currentStepIndex = 0;
                    buildDots();
                    goToStep(0);
                }
            }, 100);
        });

        // Initialize controls on load
        buildDots();

        // Listen for manual swipe scrolls to adjust the indicators
        let scrollTimeout;
        courseSlider.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const scrollLeft = courseSlider.scrollLeft;
                const containerWidth = courseSlider.clientWidth;
                const index = Math.round(scrollLeft / containerWidth);
                if (index !== currentStepIndex && index < totalSteps) {
                    currentStepIndex = index;
                    updateDots();
                }
            }, 150);
        });
    }


    // 5. Scroll Spy Navigation Highlighter
    const sections = document.querySelectorAll('section[id]');
    const navItems = document.querySelectorAll('.nav-links a');

    function scrollSpy() {
        const scrollPosition = window.scrollY + 120; // offset matches header heights

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionId = section.getAttribute('id');

            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                navItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === `#${sectionId}`) {
                        item.classList.add('active');
                    }
                });
            }
        });
    }

    window.addEventListener('scroll', scrollSpy);
    scrollSpy(); // Initial call
});
