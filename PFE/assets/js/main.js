document.addEventListener('DOMContentLoaded', () => {

    // 1. Sticky Header on Scroll
    const header = document.querySelector('header');
    if (header) {
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 10);
        });
    }

    // 2. Mobile Menu Toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('nav');
    if (mobileMenuToggle && nav) {
        mobileMenuToggle.addEventListener('click', () => {
            nav.classList.toggle('nav-active');
        });
    }


    // 4. Testimonial Slider (Modern Scroller Implementation)
    const sliderContainer = document.querySelector('.testimonial-slider-container');
    if (sliderContainer) {
        const track = sliderContainer.querySelector('.testimonial-slider-track');
        const slides = track ? Array.from(track.children) : [];
        const nextButton = sliderContainer.querySelector('.next-btn');
        const prevButton = sliderContainer.querySelector('.prev-btn');
        const dotsNav = sliderContainer.querySelector('.slider-dots');

        if (track && nextButton && prevButton && dotsNav && slides.length > 0) {
            
            // --- Create Dots ---
            dotsNav.innerHTML = ''; // Clear any existing dots
            slides.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.classList.add('slider-dot');
                dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
                dotsNav.appendChild(dot);
            });
            const dots = Array.from(dotsNav.children);

            // --- Core Functions ---
            const updateUI = () => {
                const scrollLeft = track.scrollLeft;
                const scrollWidth = track.scrollWidth;
                const clientWidth = track.clientWidth;

                prevButton.disabled = scrollLeft < 1;
                nextButton.disabled = scrollLeft >= scrollWidth - clientWidth - 1;

                let currentSlideIndex = 0;
                let minDistance = Infinity;

                slides.forEach((slide, index) => {
                    const distance = Math.abs(scrollLeft - slide.offsetLeft);
                    if (distance < minDistance) {
                        minDistance = distance;
                        currentSlideIndex = index;
                    }
                });

                dots.forEach((dot, index) => dot.classList.toggle('active', index === currentSlideIndex));
            };

            const goToSlide = (index) => {
                if (slides[index]) {
                    track.scrollTo({ left: slides[index].offsetLeft, behavior: 'smooth' });
                }
            };

            // --- Event Listeners ---
            nextButton.addEventListener('click', () => track.scrollBy({ left: track.clientWidth, behavior: 'smooth' }));
            prevButton.addEventListener('click', () => track.scrollBy({ left: -track.clientWidth, behavior: 'smooth' }));
            dotsNav.addEventListener('click', e => {
                const targetDot = e.target.closest('.slider-dot');
                if (targetDot) {
                    const targetIndex = dots.findIndex(dot => dot === targetDot);
                    goToSlide(targetIndex);
                }
            });

            let scrollTimeout;
            track.addEventListener('scroll', () => {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(updateUI, 50);
            }, { passive: true });

            new ResizeObserver(updateUI).observe(track);
            updateUI(); // Initial setup
        }
    }

    // 5. AOS (Animate on Scroll) Initialization
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            offset: 50,
        });
    }

});