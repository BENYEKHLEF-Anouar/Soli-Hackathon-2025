<!-- Footer -->
<footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">
                    <img src="../assets/images/White_Tower_Symbol.webp" alt="Mentora Logo">
                        <span class="footerlogo-text">Mentora</span>
                    </div>
                    <p class="footer-about">Mentora est la plateforme de mise en relation entre étudiants et mentors
                        pour un apprentissage personnalisé et efficace.</p>
                    <div class="footer-social">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div>
                    <h3 class="footer-heading">Navigation</h3>
                    <ul class="footer-links">
                        <li><a href="#home">Accueil</a></li>
                        <li><a href="#features">Fonctionnalités</a></li>
                        <li><a href="#profiles">Mentors</a></li>
                        <li><a href="#missions">Sessions</a></li>
                        <li><a href="#resources">Ressources</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="footer-heading">Support</h3>
                    <ul class="footer-links">
                        <li><a href="#">Centre d'aide</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Nous contacter</a></li>
                        <li><a href="#">Signaler un problème</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="footer-heading">Légal</h3>
                    <ul class="footer-links">
                        <li><a href="#">Conditions d'utilisation</a></li>
                        <li><a href="#">Politique de confidentialité</a></li>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">RGPD</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> Mentora. Tous droits réservés.</p>
            </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50,
        });
    </script>
    
    <script>
document.addEventListener("DOMContentLoaded", function() {
    const preloader = document.getElementById('preloader');
    const minDisplayTime = 800; // 1.5 seconds minimum display time
    let startTime;

    // Function to show preloader with minimum display time
    const showPreloader = (callback) => {
        startTime = new Date().getTime();
        preloader.style.transition = 'opacity 0.4s ease, visibility 0.4s ease';
        preloader.classList.remove('preloader-hidden');
        setTimeout(callback, minDisplayTime);
    };

    // Function to hide preloader after minimum time
    const hidePreloader = () => {
        const elapsed = new Date().getTime() - startTime;
        const remaining = minDisplayTime - elapsed;
        setTimeout(() => {
            preloader.classList.add('preloader-hidden');
        }, remaining > 0 ? remaining : 0);
    };

    

    // Initial page load
    window.addEventListener('load', () => {
        startTime = new Date().getTime();
        hidePreloader();
    });

    // Handle navigation links
    document.querySelectorAll('a:not([target="_blank"]):not([href^="#"]):not([href^="javascript:"])').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            showPreloader(() => {
                window.location.href = href;
            });
        });
    });

    // Handle back/forward navigation
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            preloader.classList.add('preloader-hidden');
        }
    });
});
        
        // --- DROPDOWN LOGIC (IMPROVED FOR NO CONFLICTS) ---
        const dropdowns = document.querySelectorAll('.profile-dropdown, .notification-dropdown');

        dropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('button');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (trigger && menu) {
                trigger.addEventListener('click', (event) => {
                    event.stopPropagation();
                    
                    // Close all other open dropdowns first to prevent overlap
                    dropdowns.forEach(otherDropdown => {
                        if (otherDropdown !== dropdown) {
                            otherDropdown.querySelector('.dropdown-menu').classList.remove('show');
                            otherDropdown.querySelector('button').classList.remove('active');
                        }
                    });

                    // Then, toggle the current one
                    menu.classList.toggle('show');
                    trigger.classList.toggle('active');
                });
            }
        });

        // Global click listener to close any open dropdown
        document.addEventListener('click', () => {
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                const trigger = dropdown.querySelector('button');
                if (menu) menu.classList.remove('show');
                if (trigger) trigger.classList.remove('active');
            });
        });
    </script>

</body>
</html>
