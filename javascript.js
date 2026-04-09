// ================================================
// JAF INVESTMENTS - CRM SYSTEM JAVASCRIPT
// ================================================

(function() {
    'use strict';

    // ================================================
    // GLOBAL VARIABLES
    // ================================================
    
    let currentPage = window.location.pathname.split('/').pop();
    
    // ================================================
    // INITIALIZE ON PAGE LOAD
    // ================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        initializeComponents();
        initializeEventListeners();
        initializeFormValidation();
    });

    // ================================================
    // COMPONENT INITIALIZATION
    // ================================================
    
    function initializeComponents() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    }

    // ================================================
    // EVENT LISTENERS
    // ================================================
    
    function initializeEventListeners() {
        // Global search
        const globalSearch = document.getElementById('globalSearch');
        if (globalSearch) {
            globalSearch.addEventListener('keyup', debounce(function(e) {
                const query = e.target.value;
                if (query.length >= 3) {
                    performGlobalSearch(query);
                }
            }, 500));
        }

        // Select all checkboxes
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.item-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // Table row click
        document.querySelectorAll('.table tbody tr[data-href]').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                if (!e.target.closest('button') && !e.target.closest('a') && !e.target.closest('input')) {
                    window.location.href = this.dataset.href;
                }
            });
        });

        // Form auto-save
        const autoSaveForms = document.querySelectorAll('[data-autosave]');
        autoSaveForms.forEach(form => {
            form.addEventListener('change', debounce(function() {
                autoSaveForm(form);
            }, 2000));
        });
    }

    // ================================================
    // FORM VALIDATION
    // ================================================
    
    function initializeFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        // Custom validators
        const emailInputs = document.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateEmail(this);
            });
        });

        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('blur', function() {
                validatePhone(this);
            });
        });
    }

    // ================================================
    // SEARCH FUNCTIONS
    // ================================================
    
    function performGlobalSearch(query) {
        fetch(`ajax/global-search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data);
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }

    function displaySearchResults(results) {
        const searchResults = document.getElementById('searchResults');
        if (!searchResults) return;

        if (results.length === 0) {
            searchResults.innerHTML = '<div class="p-3 text-muted">No se encontraron resultados</div>';
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        results.forEach(result => {
            html += `
                <a href="${result.url}" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${result.title}</h6>
                        <small class="text-muted">${result.type}</small>
                    </div>
                    <small class="text-muted">${result.description}</small>
                </a>
            `;
        });
        html += '</div>';

        searchResults.innerHTML = html;
    }

    // ================================================
    // FORM FUNCTIONS
    // ================================================
    
    function autoSaveForm(form) {
        const formData = new FormData(form);
        const indicator = document.getElementById('autoSaveIndicator');

        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';
        }

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (indicator) {
                if (data.success) {
                    indicator.innerHTML = '<i class="fas fa-check me-2 text-success"></i> Guardado automáticamente';
                } else {
                    indicator.innerHTML = '<i class="fas fa-exclamation-circle me-2 text-danger"></i> Error al guardar';
                }
                
                setTimeout(() => {
                    indicator.innerHTML = '';
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Auto-save error:', error);
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-exclamation-circle me-2 text-danger"></i> Error al guardar';
            }
        });
    }

    // ================================================
    // VALIDATION FUNCTIONS
    // ================================================
    
    function validateEmail(input) {
        const email = input.value;
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !regex.test(email)) {
            input.classList.add('is-invalid');
            showFieldError(input, 'Email inválido');
            return false;
        } else {
            input.classList.remove('is-invalid');
            hideFieldError(input);
            return true;
        }
    }

    function validatePhone(input) {
        const phone = input.value;
        const regex = /^[0-9\-\+\(\)\s]+$/;
        
        if (phone && !regex.test(phone)) {
            input.classList.add('is-invalid');
            showFieldError(input, 'Teléfono inválido');
            return false;
        } else {
            input.classList.remove('is-invalid');
            hideFieldError(input);
            return true;
        }
    }

    function showFieldError(input, message) {
        let errorElement = input.parentElement.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            input.parentElement.appendChild(errorElement);
        }
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    function hideFieldError(input) {
        const errorElement = input.parentElement.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }

    // ================================================
    // AJAX FUNCTIONS
    // ================================================
    
    window.ajaxRequest = function(url, method, data, successCallback, errorCallback) {
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: method !== 'GET' ? JSON.stringify(data) : null
        })
        .then(response => response.json())
        .then(data => {
            if (successCallback) successCallback(data);
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            if (errorCallback) errorCallback(error);
        });
    };

    // ================================================
    // NOTIFICATION FUNCTIONS
    // ================================================
    
    window.showNotification = function(type, message, title = '') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: type,
            title: title || message,
            text: title ? message : ''
        });
    };

    window.confirmAction = function(title, text, confirmCallback) {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed && confirmCallback) {
                confirmCallback();
            }
        });
    };

    // ================================================
    // IMAGE PREVIEW
    // ================================================
    
    window.previewImage = function(input, previewElementId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.getElementById(previewElementId);
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    };

    // ================================================
    // UTILITY FUNCTIONS
    // ================================================
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function formatCurrency(amount, currency = 'USD') {
        const symbols = {
            'USD': '$',
            'EUR': '€',
            'DOP': 'RD$'
        };
        
        const formatted = new Intl.NumberFormat('es-DO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
        
        return (symbols[currency] || '$') + formatted;
    }

    function formatDate(date, format = 'dd/mm/yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year);
    }

    // ================================================
    // EXPORT FUNCTIONS
    // ================================================
    
    window.exportToExcel = function(tableId, filename) {
        const table = document.getElementById(tableId);
        const html = table.outerHTML;
        const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename + '.xls';
        link.click();
    };

    window.exportToPDF = function(elementId, filename) {
        const element = document.getElementById(elementId);
        
        html2pdf()
            .from(element)
            .set({
                margin: 10,
                filename: filename + '.pdf',
                html2canvas: { scale: 2 },
                jsPDF: { orientation: 'portrait' }
            })
            .save();
    };

    // ================================================
    // CALENDAR FUNCTIONS
    // ================================================
    
    window.renderCalendar = function(containerId, events) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        let html = '<div class="calendar-grid">';
        
        // Days of week
        const daysOfWeek = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        daysOfWeek.forEach(day => {
            html += `<div class="calendar-day-name">${day}</div>`;
        });
        
        // Empty cells for first week
        for (let i = 0; i < firstDay.getDay(); i++) {
            html += '<div class="calendar-day empty"></div>';
        }
        
        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = day === today.getDate() && 
                           currentMonth === today.getMonth() && 
                           currentYear === today.getFullYear();
            
            html += `<div class="calendar-day ${isToday ? 'today' : ''}">${day}</div>`;
        }
        
        html += '</div>';
        container.innerHTML = html;
    };

    // ================================================
    // DATA TABLE HELPER
    // ================================================
    
    window.initDataTable = function(tableId, options = {}) {
        const defaultOptions = {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            responsive: true,
            pageLength: 25,
            dom: 'Bfrtip',
            buttons: ['copy', 'excel', 'pdf', 'print']
        };
        
        return $('#' + tableId).DataTable({...defaultOptions, ...options});
    };

    // ================================================
    // CLIPBOARD FUNCTIONS
    // ================================================
    
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('success', 'Copiado al portapapeles');
        }).catch(err => {
            console.error('Error copying to clipboard:', err);
            showNotification('error', 'Error al copiar');
        });
    };

    // ================================================
    // EXPOSE UTILITIES GLOBALLY
    // ================================================
    
    window.JAF = {
        formatCurrency,
        formatDate,
        debounce,
        validateEmail,
        validatePhone,
        showNotification,
        confirmAction
    };

})();

// ================================================
// ADDITIONAL HELPER FUNCTIONS
// ================================================

// Handle file upload preview
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('file-upload-preview')) {
        const input = e.target;
        const preview = document.getElementById(input.dataset.preview);
        
        if (input.files && input.files[0] && preview) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-auto-dismiss').forEach(alert => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Confirm delete actions
document.addEventListener('click', function(e) {
    if (e.target.closest('[data-confirm-delete]')) {
        e.preventDefault();
        const element = e.target.closest('[data-confirm-delete]');
        const message = element.dataset.confirmDelete || '¿Estás seguro de que deseas eliminar este elemento?';
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                if (element.tagName === 'A') {
                    window.location.href = element.href;
                } else if (element.tagName === 'FORM') {
                    element.submit();
                }
            }
        });
    }
});