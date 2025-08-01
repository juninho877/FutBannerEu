</div>
        </main>
    </div>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Mobile Menu Management
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('overlay');

        mobileMenuBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', closeSidebar);

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            mainContent.classList.toggle('expanded');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            mainContent.classList.remove('expanded');
        }

        // Active Navigation Item
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            item.classList.remove('active');
            const href = item.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'index.php')) {
                item.classList.add('active');
            }
        });

        // Close sidebar on window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });

        // Smooth animations for page load
        document.addEventListener('DOMContentLoaded', () => {
            const contentArea = document.querySelector('.content-area');
            if (contentArea) {
                contentArea.classList.add('animate-slide-in');
            }
        });

        // Handle SweetAlert modal cleanup on page navigation
        window.addEventListener('pageshow', function (event) {
            if (event.persisted && typeof Swal !== 'undefined' && Swal.isVisible()) {
                Swal.close();
            }
        });
    </script>
</body>
</html>