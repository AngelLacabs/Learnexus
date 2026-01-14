// Admin Panel JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        const collapseEl = document.getElementById('navbarNav');

        function getScrollbarWidth() {
            return window.innerWidth - document.documentElement.clientWidth;
        }

        function lockBodyScroll() {
            const sb = getScrollbarWidth();
            if (sb > 0) document.body.style.paddingRight = sb + 'px';
            document.body.style.overflow = 'hidden';
        }

        function unlockBodyScroll() {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }

        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();

            const isOpen = collapseEl.classList.contains('show');

            if (isOpen) {
                collapseEl.classList.remove('show');
                sidebar.classList.remove('show');
                sidebarToggle.setAttribute('aria-expanded', 'false');
                unlockBodyScroll();
            } else {
                collapseEl.classList.add('show');
                sidebar.classList.add('show');
                sidebarToggle.setAttribute('aria-expanded', 'true');
                lockBodyScroll();
            }
        });

        // Close overlay and sidebar when clicking a nav link (optional UX)
        document.querySelectorAll('#navbarNav .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (collapseEl.classList.contains('show')) {
                    collapseEl.classList.remove('show');
                    sidebar.classList.remove('show');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                    unlockBodyScroll();
                }
            });
        });

        // Ensure we cleanup if window resizes above mobile
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767.98 && collapseEl.classList.contains('show')) {
                collapseEl.classList.remove('show');
                sidebar.classList.remove('show');
                sidebarToggle.setAttribute('aria-expanded', 'false');
                unlockBodyScroll();
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this?';
            
            Swal.fire({
                title: 'Confirm Deletion',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = this.href;
                }
            });
        });
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Make .stat-pill.hover-stat trigger parent card hover (so Draft pill moves like cards)
    const statPills = document.querySelectorAll('.stat-pill.hover-stat');
    statPills.forEach(pill => {
        const card = pill.closest('.card');
        if (!card) return;

        function addHover() { card.classList.add('hover-stat'); }
        function removeHover() { card.classList.remove('hover-stat'); }

        pill.addEventListener('mouseenter', addHover);
        pill.addEventListener('mouseleave', removeHover);
        pill.addEventListener('focus', addHover);
        pill.addEventListener('blur', removeHover);
    });

    // Number formatting
    const numbers = document.querySelectorAll('[data-format-number]');
    numbers.forEach(el => {
        const value = parseFloat(el.textContent);
        if (!isNaN(value)) {
            el.textContent = value.toLocaleString();
        }
    });
});