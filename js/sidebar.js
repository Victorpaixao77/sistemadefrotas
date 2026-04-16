/**
 * Sidebar functionality management
 */

document.addEventListener('DOMContentLoaded', function() {
    var mainContent = document.querySelector('.main-content');
    if (mainContent) {
        if (!mainContent.id) {
            mainContent.id = 'conteudo-principal';
        }
        if (!mainContent.hasAttribute('tabindex')) {
            mainContent.setAttribute('tabindex', '-1');
        }
    }

    // Initialize sidebar state
    initializeSidebar();
    
    // Setup sidebar toggle
    setupSidebarToggle();
    
    // Setup mobile menu
    setupMobileMenu();
    
    // Setup dropdown menus
    setupDropdowns();
});

/**
 * Initialize sidebar state from localStorage
 */
function initializeSidebar() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Initialize dropdown states
    initializeDropdowns();
}

/**
 * Setup sidebar toggle button
 */
function setupSidebarToggle() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Removido o comportamento de hover na sidebar
    // O menu lateral será expandido apenas ao clicar no botão de toggle
}

/**
 * Toggle sidebar expanded/collapsed state
 */
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    
    // Save state to localStorage
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
    
    // Update the toggle button icon
    const toggleIcon = document.querySelector('.sidebar-toggle i');
    
    if (toggleIcon) {
        if (isCollapsed) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    }
}

/**
 * Setup mobile menu toggle
 */
function setupMobileMenu() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (!mobileMenuToggle || !sidebar || !sidebarOverlay) {
        return;
    }

    function setMobileMenuOpen(isOpen) {
        mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        mobileMenuToggle.setAttribute('aria-label', isOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
    }

    function closeMobileSidebar(options) {
        var returnFocus = options && options.returnFocus;
        if (!sidebar.classList.contains('active')) {
            return;
        }
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
        setMobileMenuOpen(false);
        if (returnFocus) {
            mobileMenuToggle.focus();
        }
    }

    function openMobileSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        setMobileMenuOpen(true);
    }

    mobileMenuToggle.addEventListener('click', function () {
        if (sidebar.classList.contains('active')) {
            closeMobileSidebar({ returnFocus: false });
        } else {
            openMobileSidebar();
        }
    });

    sidebarOverlay.addEventListener('click', function () {
        closeMobileSidebar({ returnFocus: true });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (!sidebar.classList.contains('active')) {
            return;
        }
        e.preventDefault();
        closeMobileSidebar({ returnFocus: true });
    });

    sidebar.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a || a.classList.contains('sidebar-dropdown-toggle')) {
            return;
        }
        var href = (a.getAttribute('href') || '').trim();
        if (href === '#' || href.indexOf('javascript:') === 0) {
            return;
        }
        if (sidebar.classList.contains('active')) {
            closeMobileSidebar({ returnFocus: false });
        }
    });
}

/**
 * Initialize dropdown menu states from localStorage + servidor (data-dropdown-open / .active no submenu)
 */
function initializeDropdowns() {
    const pathname = (window.location.pathname || '').replace(/\\/g, '/');
    const isFiscalPage = /\/fiscal\/(pages\/(nfe|cte|mdfe|eventos)\.php|index\.php)$/i.test(pathname);

    let dropdownStates = {};
    try {
        const savedStates = localStorage.getItem('sidebarDropdowns');
        if (savedStates) {
            dropdownStates = JSON.parse(savedStates);
        }
    } catch (error) {
        console.error('Error parsing saved dropdown states:', error);
    }

    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
    const dropdowns = document.querySelectorAll('.sidebar-dropdown');

    dropdowns.forEach((dropdown, index) => {
        const parentLink = dropdownToggles[index];
        if (!parentLink) return;

        const linkText = (parentLink.querySelector('.sidebar-link-text') && parentLink.querySelector('.sidebar-link-text').textContent) || '';
        const isSistemaFiscal = linkText.indexOf('Sistema Fiscal') !== -1;
        const hasActiveChild = !!dropdown.querySelector('a.active, .sidebar-dropdown-link.active');
        const serverOpen = dropdown.getAttribute('data-dropdown-open') === '1';
        const shouldOpen = serverOpen || hasActiveChild || (isFiscalPage && isSistemaFiscal) || (dropdownStates[index] === true);

        if (shouldOpen) {
            dropdown.style.display = 'block';
            parentLink.classList.add('active');
            const icon = parentLink.querySelector('.dropdown-icon');
            if (icon) icon.classList.add('rotate');
        }

        if (isFiscalPage && isSistemaFiscal) {
            dropdown.querySelectorAll('a[href]').forEach(function (a) {
                if (a.classList.contains('active')) return;
                const href = (a.getAttribute('href') || '').replace(/^https?:\/\/[^/]*/, '');
                const match = (pathname === href) || (pathname.endsWith('cte.php') && href.endsWith('cte.php')) || (pathname.endsWith('nfe.php') && href.endsWith('nfe.php')) || (pathname.endsWith('mdfe.php') && href.endsWith('mdfe.php')) || (pathname.endsWith('eventos.php') && href.endsWith('eventos.php'));
                if (match) a.classList.add('active');
            });
        }
    });

    if (isFiscalPage) {
        saveDropdownStates();
    }
}

/**
 * Setup dropdown toggle functionality
 */
function setupDropdowns() {
    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
    
    if (!dropdownToggles.length) return;
    
    dropdownToggles.forEach((toggle) => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Find corresponding dropdown
            const dropdown = toggle.nextElementSibling;
            
            if (dropdown) {
                // Toggle dropdown visibility
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                    toggle.classList.remove('active');
                    toggle.querySelector('.dropdown-icon').classList.remove('rotate');
                } else {
                    dropdown.style.display = 'block';
                    toggle.classList.add('active');
                    toggle.querySelector('.dropdown-icon').classList.add('rotate');
                }
                
                // Save dropdown states
                saveDropdownStates();
            }
        });
    });
}

/**
 * Save current dropdown states to localStorage
 */
function saveDropdownStates() {
    const dropdowns = document.querySelectorAll('.sidebar-dropdown');
    const states = {};
    
    dropdowns.forEach((dropdown, index) => {
        states[index] = dropdown.style.display === 'block';
    });
    
    localStorage.setItem('sidebarDropdowns', JSON.stringify(states));
}
