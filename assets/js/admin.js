document.addEventListener('DOMContentLoaded', function () {
    setupAdminConfirmDialogs();
    setupAdminSearch();
    setupAdminForms();
    setupAdminTables();
});

function setupAdminConfirmDialogs() {
    document.body.addEventListener('click', function (e) {
        const el = e.target.closest('.confirm-delete');
        if (!el) return;
        const msg = el.getAttribute('data-confirm') || 'Вы уверены, что хотите удалить?';
        if (!confirm(msg)) {
            e.preventDefault();
            return false;
        }
    });
}

function setupAdminSearch() {
    const searchInputs = document.querySelectorAll('.admin-search');
    searchInputs.forEach(inp => {
        inp.addEventListener('focus', () => inp.setAttribute('placeholder', 'Введите текст и нажмите Enter'));
        inp.addEventListener('blur', () => inp.setAttribute('placeholder', 'Поиск...'));
    });
}

function setupAdminForms() {
    const forms = document.querySelectorAll('.admin-content form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const numbers = this.querySelectorAll('input[type="number"]');
            for (let i = 0; i < numbers.length; i++) {
                const input = numbers[i];
                const value = parseFloat(input.value);
                
                if (input.hasAttribute('min') && value < parseFloat(input.min)) {
                    e.preventDefault();
                    input.focus();
                    alert(`Значение не может быть меньше ${input.min}`);
                    return false;
                }
                
                if (input.hasAttribute('max') && value > parseFloat(input.max)) {
                    e.preventDefault();
                    input.focus();
                    alert(`Значение не может быть больше ${input.max}`);
                    return false;
                }
            }
        });
    });
}

function setupAdminTables() {
    const tables = document.querySelectorAll('.admin-content table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(table, index);
            });
        });
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        if (!isNaN(aText) && !isNaN(bText)) {
            return parseFloat(aText) - parseFloat(bText);
        }
        
        return aText.localeCompare(bText);
    });
    
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    rows.forEach(row => tbody.appendChild(row));
}

function showAdminNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 300px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 4000);
}