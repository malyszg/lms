// LMS Application JavaScript - Fluent Design System 2

// Initialize application on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar-wrapper');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // Initialize User Menu Dropdown
    initializeUserMenu();

    // Initialize Pagination Per Page Dropdowns
    initializePaginationPerPageDropdowns();

    // Style Fluent Menu components (legacy, for any remaining fluent-menu elements)
    // initializeFluentMenu(); // Disabled - causes HTMX conflicts
});

// Initialize User Menu Dropdown
function initializeUserMenu() {
    const menuTrigger = document.getElementById('userMenuTrigger');
    const menuDropdown = document.getElementById('userMenuDropdown');

    if (!menuTrigger || !menuDropdown) return;

    // Toggle menu on click
    menuTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = menuDropdown.classList.contains('show');

        if (isOpen) {
            closeUserMenu();
        } else {
            openUserMenu();
        }
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!menuTrigger.contains(event.target) && !menuDropdown.contains(event.target)) {
            closeUserMenu();
        }
    });

    // Close menu on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeUserMenu();
        }
    });
}

function openUserMenu() {
    const menuTrigger = document.getElementById('userMenuTrigger');
    const menuDropdown = document.getElementById('userMenuDropdown');

    if (menuTrigger && menuDropdown) {
        menuTrigger.classList.add('active');
        menuDropdown.classList.add('show');
    }
}

function closeUserMenu() {
    const menuTrigger = document.getElementById('userMenuTrigger');
    const menuDropdown = document.getElementById('userMenuDropdown');

    if (menuTrigger && menuDropdown) {
        menuTrigger.classList.remove('active');
        menuDropdown.classList.remove('show');
    }
}

// Initialize Pagination Per Page Dropdowns
function initializePaginationPerPageDropdowns() {
    const triggers = document.querySelectorAll('.pagination-per-page-trigger');
    
    triggers.forEach(trigger => {
        // Skip if already initialized
        if (trigger.dataset.initialized === 'true') return;
        
        // Get the corresponding dropdown ID from trigger ID
        const triggerId = trigger.id;
        const dropdownId = triggerId.replace('Trigger', 'Dropdown');
        const dropdown = document.getElementById(dropdownId);
        
        if (!dropdown) return;
        
        // Mark as initialized
        trigger.dataset.initialized = 'true';
        
        // Add click listener to trigger
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('show');
            
            // Close all other dropdowns first
            document.querySelectorAll('.pagination-per-page-dropdown.show').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                    const t = document.getElementById(d.id.replace('Dropdown', 'Trigger'));
                    if (t) {
                        t.classList.remove('active');
                        t.dataset.initialized = 'false'; // Allow re-initialization after HTMX swap
                    }
                }
            });
            
            if (isOpen) {
                closePaginationPerPageDropdown(trigger, dropdown);
            } else {
                openPaginationPerPageDropdown(trigger, dropdown);
            }
        });
    });
    
    // Close dropdowns when clicking outside (only one listener needed)
    if (!window.paginationClickOutsideHandler) {
        window.paginationClickOutsideHandler = function(event) {
            const allDropdowns = document.querySelectorAll('.pagination-per-page-dropdown.show');
            allDropdowns.forEach(dropdown => {
                const triggerId = dropdown.id.replace('Dropdown', 'Trigger');
                const trigger = document.getElementById(triggerId);
                
                if (trigger && !trigger.contains(event.target) && !dropdown.contains(event.target)) {
                    closePaginationPerPageDropdown(trigger, dropdown);
                    trigger.dataset.initialized = 'false'; // Allow re-initialization after HTMX swap
                }
            });
        };
        document.addEventListener('click', window.paginationClickOutsideHandler);
    }
    
    // Close dropdowns on escape key (only one listener needed)
    if (!window.paginationEscapeHandler) {
        window.paginationEscapeHandler = function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.pagination-per-page-dropdown.show').forEach(dropdown => {
                    const triggerId = dropdown.id.replace('Dropdown', 'Trigger');
                    const trigger = document.getElementById(triggerId);
                    if (trigger) {
                        closePaginationPerPageDropdown(trigger, dropdown);
                        trigger.dataset.initialized = 'false'; // Allow re-initialization after HTMX swap
                    }
                });
            }
        };
        document.addEventListener('keydown', window.paginationEscapeHandler);
    }
}

function openPaginationPerPageDropdown(trigger, dropdown) {
    if (trigger && dropdown) {
        trigger.classList.add('active');
        dropdown.classList.add('show');
    }
}

function closePaginationPerPageDropdown(trigger, dropdown) {
    if (trigger && dropdown) {
        trigger.classList.remove('active');
        dropdown.classList.remove('show');
    }
}

// Initialize Fluent Menu styling
function initializeFluentMenu() {
    // Wait for Fluent Web Components to be defined
    if (customElements.get('fluent-menu')) {
        applyFluentMenuStyles();
    } else {
        // If not yet defined, wait for them with timeout
        const timeout = setTimeout(() => {
            console.warn('fluent-menu not defined after 5 seconds, skipping initialization');
        }, 5000);
        
        customElements.whenDefined('fluent-menu').then(() => {
            clearTimeout(timeout);
            applyFluentMenuStyles();
        }).catch(error => {
            clearTimeout(timeout);
            console.warn('Error waiting for fluent-menu:', error);
        });
    }
}

// Apply styles to Fluent Menu
function applyFluentMenuStyles() {
    const menus = document.querySelectorAll('fluent-menu');

    menus.forEach(menu => {
        // Set design tokens for the menu (Fluent Web Components v3)
        const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (isDarkMode) {
            menu.style.setProperty('--base-layer-luminance', '0');
            menu.style.setProperty('--neutral-layer-floating', '#2b2b2b');
            menu.style.setProperty('--neutral-layer-1', '#2b2b2b');
            menu.style.setProperty('--neutral-fill-rest', '#2b2b2b');
            menu.style.setProperty('--neutral-fill-hover', '#323232');
            menu.style.setProperty('--neutral-stroke-rest', '#424242');
        } else {
            menu.style.setProperty('--base-layer-luminance', '1');
            menu.style.setProperty('--neutral-layer-floating', '#ffffff');
            menu.style.setProperty('--neutral-layer-1', '#ffffff');
            menu.style.setProperty('--neutral-fill-rest', '#ffffff');
            menu.style.setProperty('--neutral-fill-hover', '#f5f5f5');
            menu.style.setProperty('--neutral-stroke-rest', '#e0e0e0');
        }

        menu.style.setProperty('--elevation-shadow', '0 8px 16px rgba(0,0,0,0.14)');
        menu.style.setProperty('--corner-radius', '8px');
        menu.style.setProperty('--stroke-width', '1px');

        // Observe when menu opens to apply additional styles
        const observer = new MutationObserver(() => {
            const menuItems = menu.querySelectorAll('fluent-menu-item');
            menuItems.forEach(item => {
                // Set design tokens for menu items
                if (isDarkMode) {
                    item.style.setProperty('--neutral-fill-hover', '#323232');
                    item.style.setProperty('--neutral-fill-rest', 'transparent');
                    item.style.setProperty('--neutral-foreground-rest', '#ffffff');
                } else {
                    item.style.setProperty('--neutral-fill-hover', '#f5f5f5');
                    item.style.setProperty('--neutral-fill-rest', 'transparent');
                    item.style.setProperty('--neutral-foreground-rest', '#242424');
                }

                // Apply inline styles to fix icon alignment
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.gap = '12px';
                item.style.padding = '10px 16px';

                // Style SVG icons inside menu items
                const svgs = item.querySelectorAll('svg');
                svgs.forEach(svg => {
                    svg.style.width = '16px';
                    svg.style.height = '16px';
                    svg.style.minWidth = '16px';
                    svg.style.flexShrink = '0';
                    svg.style.display = 'inline-block';
                    svg.style.verticalAlign = 'middle';
                });

                // Try to access shadow DOM if possible
                if (item.shadowRoot) {
                    const shadowContent = item.shadowRoot.querySelector('.content');
                    if (shadowContent) {
                        shadowContent.style.display = 'flex';
                        shadowContent.style.alignItems = 'center';
                        shadowContent.style.gap = '12px';
                    }
                }
            });
        });

        observer.observe(menu, {
            childList: true,
            subtree: true,
            attributes: true
        });

        // Trigger initial styling if menu items already exist
        const existingItems = menu.querySelectorAll('fluent-menu-item');
        if (existingItems.length > 0) {
            observer.disconnect();
            observer.observe(menu, {
                childList: true,
                subtree: true,
                attributes: true
            });
        }
    });
}

// Listen for dark mode changes and reapply menu styles
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    applyFluentMenuStyles();
});

// HTMX Error Handling
document.addEventListener('htmx:responseError', function(event) {
    const status = event.detail.xhr.status;
    const requestPath = event.detail.pathInfo.requestPath;

    // Skip global error handling for preferences form - it handles its own errors
    if (requestPath.includes('/update-preferences')) {
        return;
    }

    if (status === 401) {
        // Redirect to login
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
    } else if (status === 403) {
        showToast('Nie masz uprawnień do wykonania tej operacji', 'error');
    } else if (status === 404) {
        showToast('Zasób nie został znaleziony', 'error');
    } else if (status >= 500) {
        showToast('Wystąpił błąd serwera. Spróbuj ponownie później.', 'error');
    } else {
        showToast('Wystąpił błąd. Spróbuj ponownie.', 'warning');
    }
});

// Network errors (no connection)
document.addEventListener('htmx:sendError', function(event) {
    showToast('Brak połączenia z serwerem. Sprawdź połączenie internetowe.', 'error');
});

// Timeout errors
document.addEventListener('htmx:timeout', function(event) {
    showToast('Przekroczono czas oczekiwania. Spróbuj ponownie.', 'warning');
});

// After successful HTMX swap
document.addEventListener('htmx:afterSwap', function(event) {
    // Re-initialize pagination dropdowns after HTMX swap
    initializePaginationPerPageDropdowns();
    // Any post-swap initialization can be done here
    console.log('Content swapped successfully');
});

// Toast notification function - Fluent Design
function showToast(message, type) {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.error('Toast container not found');
        return;
    }

    // Map type to Fluent classes
    const typeClass = {
        'success': 'fluent-toast-success',
        'error': 'fluent-toast-error',
        'warning': 'fluent-toast-warning',
        'info': 'fluent-toast-info'
    }[type] || 'fluent-toast-info';

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `fluent-toast ${typeClass}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    // Icon based on type
    const icons = {
        'success': '<path d="M8 1c3.86 0 7 3.14 7 7s-3.14 7-7 7-7-3.14-7-7 3.14-7 7-7zm3.5 5L7 10.5 5.5 9 4 10.5l3 3 6-6L11.5 6z"/>',
        'error': '<path d="M8 1c3.86 0 7 3.14 7 7s-3.14 7-7 7-7-3.14-7-7 3.14-7 7-7zm1 10H7v2h2v-2zm0-8H7v6h2V3z"/>',
        'warning': '<path d="M8 1l7 13H1L8 1zm0 3.5L3.5 12h9L8 4.5zM7 8h2v3H7V8zm0 4h2v2H7v-2z"/>',
        'info': '<path d="M8 1c3.86 0 7 3.14 7 7s-3.14 7-7 7-7-3.14-7-7 3.14-7 7-7zm1 10H7v2h2v-2zm0-8H7v6h2V3z"/>'
    };

    const iconSvg = icons[type] || icons['info'];

    toast.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor" style="flex-shrink: 0;">
            ${iconSvg}
        </svg>
        <div style="flex: 1; font-size: var(--fluent-font-size-300);">
            ${message}
        </div>
        <button onclick="this.parentElement.remove()" 
                style="background: transparent; border: none; cursor: pointer; color: inherit; padding: 4px; z-index: 10; position: relative;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 6.586L5.707 4.293 4.293 5.707 6.586 8l-2.293 2.293 1.414 1.414L8 9.414l2.293 2.293 1.414-1.414L9.414 8l2.293-2.293-1.414-1.414L8 6.586z"/>
            </svg>
        </button>
    `;

    toastContainer.appendChild(toast);

    // Auto-remove toast after delay
    setTimeout(() => {
        if (toast.parentNode) { // Check if toast still exists
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000); // 5 seconds for all toast types including errors
}

// Session management - last check timestamp for new leads
function updateLastCheckTimestamp() {
    sessionStorage.setItem('leads_last_check', new Date().toISOString());
}

function getLastCheckTimestamp() {
    return sessionStorage.getItem('leads_last_check') || new Date().toISOString();
}

// Update timestamp after polling new leads count
document.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath.includes('/leads/new-count')) {
        updateLastCheckTimestamp();
    }
});

// Date range validation
document.addEventListener('DOMContentLoaded', function() {
    const filtersForm = document.getElementById('filters-form');
    if (filtersForm) {
        filtersForm.addEventListener('submit', function(e) {
            const fromDate = document.getElementById('created_from');
            const toDate = document.getElementById('created_to');

            if (fromDate && toDate && fromDate.value && toDate.value) {
                const from = new Date(fromDate.value);
                const to = new Date(toDate.value);

                if (from > to) {
                    e.preventDefault();
                    showToast('Data od musi być wcześniejsza niż data do', 'warning');
                    return false;
                }
            }
        });
    }
});

// Helper function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pl-PL', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Helper function to update badge counts
function updateBadgeCount(elementId, count) {
    const badge = document.getElementById(elementId);
    if (badge) {
        if (count > 0) {
            badge.innerHTML = `<span class="nav-badge">${count}</span>`;
        } else {
            badge.innerHTML = '';
        }
    }
}

// Close lead details slider (Fluent Design)
window.closeLeadDetails = function() {
    const slider = document.getElementById('lead-details-slider');
    const backdrop = document.querySelector('.fluent-slider-backdrop');

    if (slider) {
        slider.style.animation = 'slideOutRight 200ms cubic-bezier(0.33, 0, 0.67, 1)';
    }
    if (backdrop) {
        backdrop.style.animation = 'fadeOut 200ms cubic-bezier(0.33, 0, 0.67, 1)';
    }

    setTimeout(() => {
        const container = document.getElementById('slider-container');
        if (container) {
            container.innerHTML = '';
        }
        
        // Also check for customers page slider container
        const leadSliderContainer = document.getElementById('lead-slider-container');
        if (leadSliderContainer) {
            leadSliderContainer.remove();
        }
    }, 200);
}

// Lead deletion modal handling
window.loadDeleteModal = function(leadId, leadUuid) {
    console.log('loadDeleteModal called from app.js with leadId:', leadId, 'leadUuid:', leadUuid);
    
    const modalContainer = document.getElementById('delete-modal-container');
    if (!modalContainer) {
        console.error('Delete modal container not found');
        return;
    }

    console.log('Modal container found, loading modal content');

    // Load modal content via HTMX
    htmx.ajax('GET', `/leads/${leadId}/delete-modal`, {
        target: '#delete-modal-container',
        swap: 'innerHTML'
    }).then(() => {
        console.log('Modal loaded successfully, setting up listeners');
        // Setup event listeners for the modal
        setupDeleteModalListeners(leadId);
    }).catch(error => {
        console.error('Failed to load delete modal:', error);
        showToast('Wystąpił błąd podczas ładowania modala', 'error');
    });
};

// Setup event listeners for delete modal
function setupDeleteModalListeners(leadId) {
    console.log('setupDeleteModalListeners called from app.js for leadId:', leadId);
    
    const modalElement = document.getElementById('deleteModal');
    const backdropElement = document.getElementById('delete-modal-backdrop');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancel-delete-btn');
    const closeBtn = document.getElementById('close-delete-modal-btn');

    console.log('Modal elements found in app.js:', {
        modalElement: !!modalElement,
        backdropElement: !!backdropElement,
        confirmDeleteBtn: !!confirmDeleteBtn,
        cancelBtn: !!cancelBtn,
        closeBtn: !!closeBtn
    });

    if (!modalElement || !confirmDeleteBtn) {
        console.error('Required modal elements not found in app.js');
        return;
    }

    // Close modal function
    function closeModal() {
        modalElement.style.animation = 'modalSlideOut 200ms cubic-bezier(0.33, 0, 0.67, 1)';
        backdropElement.style.animation = 'fadeOut 200ms cubic-bezier(0.33, 0, 0.67, 1)';

        setTimeout(() => {
            const container = document.getElementById('delete-modal-container');
            if (container) {
                container.innerHTML = '';
            }
        }, 200);
    }

    // Close modal event listeners
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (backdropElement) {
        backdropElement.addEventListener('click', closeModal);
    }

    // Close on ESC key
    const escHandler = function(e) {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);

    // Handle successful deletion
    confirmDeleteBtn.addEventListener('htmx:afterRequest', function(event) {
        console.log('HTMX afterRequest event fired in app.js:', event.detail.xhr.status);
        if (event.detail.xhr.status === 200) {
            console.log('Lead deleted successfully in app.js, handling cleanup');
            
            // Remove lead row from table
            const leadRow = document.getElementById(`lead-row-${leadId}`);
            if (leadRow) {
                console.log('Removing lead row from table');
                leadRow.style.animation = 'fadeOut 300ms ease-out';
                setTimeout(() => {
                    leadRow.remove();
                }, 300);
            } else {
                console.log('Lead row not found in table');
            }

            // Close details slider if open (check both leads page and customers page)
            // Check for customers page slider FIRST (most specific)
            const customersSliderContainer = document.getElementById('lead-slider-container');
            
            console.log('Checking for sliders:', {
                customersSliderContainer: !!customersSliderContainer
            });
            
            if (customersSliderContainer) {
                console.log('Closing lead details slider (customers page)');
                
                // Animate out the slider
                const sliderInContainer = customersSliderContainer.querySelector('#lead-details-slider');
                const backdrop = customersSliderContainer.querySelector('#lead-slider-backdrop');
                
                if (sliderInContainer) {
                    sliderInContainer.style.animation = 'slideOutRight 200ms cubic-bezier(0.33, 0, 0.67, 1)';
                }
                if (backdrop) {
                    backdrop.style.animation = 'fadeOut 200ms cubic-bezier(0.33, 0, 0.67, 1)';
                }
                
                // Remove the container after animation
                setTimeout(() => {
                    console.log('Removing lead-slider-container');
                    customersSliderContainer.remove();
                    
                    // Reload customers page to refresh the list
                    window.location.reload();
                }, 200);
            } else {
                // Check for leads page slider
                const slider = document.getElementById('lead-details-slider');
                const sliderBackdrop = document.getElementById('lead-slider-backdrop');
                
                console.log('Checking for leads page sliders:', {
                    slider: !!slider,
                    sliderBackdrop: !!sliderBackdrop
                });
                
                if (slider && sliderBackdrop) {
                console.log('Closing lead details slider (leads page)');
                console.log('Slider element:', slider);
                console.log('SliderBackdrop element:', sliderBackdrop);
                
                slider.style.animation = 'slideOutRight 200ms cubic-bezier(0.33, 0, 0.67, 1)';
                sliderBackdrop.style.animation = 'fadeOut 200ms cubic-bezier(0.33, 0, 0.67, 1)';
                
                setTimeout(() => {
                    // Check for customers page slider container first
                    const leadSliderContainer = document.getElementById('lead-slider-container');
                    if (leadSliderContainer) {
                        console.log('Found lead-slider-container, removing it');
                        leadSliderContainer.remove();
                    } else {
                        // Check for leads page slider container
                        const container = document.getElementById('slider-container');
                        console.log('Looking for slider-container:', container);
                        if (container) {
                            console.log('Clearing slider-container content');
                            container.innerHTML = '';
                            
                            // Also hide the slider itself
                            if (slider) {
                                console.log('Hiding slider element');
                                slider.style.display = 'none';
                            }
                            if (sliderBackdrop) {
                                console.log('Hiding slider backdrop');
                                sliderBackdrop.style.display = 'none';
                            }
                        } else {
                            console.log('Neither slider container found');
                            console.log('All elements with "slider" in ID:', 
                                Array.from(document.querySelectorAll('[id*="slider"]')).map(el => el.id));
                            
                            // Force remove any slider elements
                            const allSliders = document.querySelectorAll('[id*="slider"]');
                            console.log('Force removing all slider elements:', allSliders.length);
                            allSliders.forEach(sliderEl => {
                                console.log('Removing slider:', sliderEl.id);
                                sliderEl.remove();
                            });
                        }
                    }
                }, 200);
                }
            }
            
            // Fallback cleanup - if we're on customers page and no slider was found, ensure cleanup
            if (!customersSliderContainer && window.location.pathname.includes('/customers')) {
                console.log('On customers page, ensuring any leftover sliders are cleaned up');
                const leadSliderContainer = document.getElementById('lead-slider-container');
                if (leadSliderContainer) {
                    leadSliderContainer.remove();
                }
                
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            }

            // Close modal first
            closeModal();
            // Show success toast after modal is closed
            setTimeout(() => {
                showToast('Lead został pomyślnie usunięty', 'success');
            }, 300);
        }
    });

    // Handle deletion errors
    confirmDeleteBtn.addEventListener('htmx:responseError', function(event) {
        const status = event.detail.xhr.status;
        let message = 'Wystąpił nieoczekiwany błąd';

        switch(status) {
            case 403:
                message = 'Nie masz uprawnień do usunięcia tego leada';
                break;
            case 404:
                message = 'Lead nie został znaleziony';
                break;
            case 500:
                message = 'Wystąpił błąd serwera. Spróbuj ponownie';
                break;
        }

        showToast(message, 'error');

        // Re-enable button
        confirmDeleteBtn.disabled = false;
    });
}

// Handle successful lead deletion
document.addEventListener('htmx:afterRequest', function(event) {
    // Check if this was a delete request
    if (event.detail.xhr.status === 200 &&
        event.detail.pathInfo.requestPath.includes('/api/leads/') &&
        event.detail.elt.method === 'DELETE') {

        showToast('Lead został pomyślnie usunięty', 'success');

        // Remove the row from table with animation
        const targetElement = event.detail.elt.getAttribute('hx-target');
        if (targetElement) {
            const row = document.querySelector(targetElement);
            if (row) {
                row.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-100%)';

                setTimeout(() => {
                    row.remove();
                }, 300);
            }
        }
    }
});

// Handle lead deletion errors specifically
document.addEventListener('htmx:responseError', function(event) {
    // Check if this was a delete request
    if (event.detail.pathInfo.requestPath.includes('/api/leads/') &&
        event.detail.elt.method === 'DELETE') {

        const status = event.detail.xhr.status;
        let message = 'Wystąpił błąd podczas usuwania leada';

        switch(status) {
            case 403:
                message = 'Nie masz uprawnień do usunięcia tego leada';
                break;
            case 404:
                message = 'Lead nie został znaleziony';
                break;
            case 500:
                message = 'Wystąpił błąd serwera podczas usuwania leada';
                break;
        }

        showToast(message, 'error');

        // Re-enable delete button if it was disabled
        const deleteBtn = event.detail.elt;
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = deleteBtn.innerHTML.replace(/<span[^>]*class="spinner-border[^"]*"[^>]*><\/span>\s*/, '');
        }
    }
});
