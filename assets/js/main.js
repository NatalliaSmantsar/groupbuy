document.addEventListener('DOMContentLoaded', function () {
    autoDismissAlerts();
    setupConfirmDialogs();
    setupFormValidation();
    setupSearchForms();
});

function autoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 400ms ease, transform 400ms ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 450);
        }, 6000);
    });
}

function setupConfirmDialogs() {
    document.body.addEventListener('click', function (e) {
        const el = e.target.closest('[data-confirm]');
        if (!el) return;
        const msg = el.getAttribute('data-confirm') || 'Подтвердите действие';
        if (!confirm(msg)) {
            e.preventDefault();
            return false;
        }
    });
}

function setupFormValidation() {
    document.body.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.matches('[data-need-validation]')) return;
        
        const required = form.querySelectorAll('[required]');
        for (let i = 0; i < required.length; i++) {
            if (!required[i].value.trim()) {
                e.preventDefault();
                required[i].focus();
                showTempMessage('Пожалуйста, заполните все обязательные поля', 'error');
                return false;
            }
        }
        
        const numbers = form.querySelectorAll('input[type="number"]');
        for (let i = 0; i < numbers.length; i++) {
            const input = numbers[i];
            const value = parseFloat(input.value);
            const min = parseFloat(input.min) || 0;
            const max = parseFloat(input.max) || Infinity;
            
            if (value < min || value > max) {
                e.preventDefault();
                input.focus();
                showTempMessage(`Введите значение от ${min} до ${max}`, 'error');
                return false;
            }
        }
        
        const emails = form.querySelectorAll('input[type="email"]');
        for (let i = 0; i < emails.length; i++) {
            const email = emails[i].value;
            if (email && !validateEmail(email)) {
                e.preventDefault();
                emails[i].focus();
                showTempMessage('Введите корректный email адрес', 'error');
                return false;
            }
        }
    });
}

function setupSearchForms() {
    const searchForms = document.querySelectorAll('.search-form');
    searchForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input, select');
            let hasValue = false;
            
            inputs.forEach(input => {
                if (input.value && input.value.trim() !== '') {
                    hasValue = true;
                }
            });
            
            if (!hasValue) {
                e.preventDefault();
                showTempMessage('Введите параметры для поиска', 'info');
                return false;
            }
        });
    });
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showTempMessage(text = '', type = 'info') {
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.innerText = text;
    
    const container = document.querySelector('main, .admin-content, .container');
    if (container) {
        container.prepend(div);
    } else {
        document.body.prepend(div);
    }
    
    setTimeout(() => {
        div.style.transition = 'opacity 400ms ease';
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 450);
    }, 3500);
}

function formatPrice(price) {
    return parseFloat(price).toFixed(2).replace('.', ',');
}

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

function calculateOrderPrice(quantity, pricePerUnit) {
    return (quantity * pricePerUnit).toFixed(2);
}