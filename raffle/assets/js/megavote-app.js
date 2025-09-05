/**
 * MEGAVOTE - SISTEMA DE SORTEIO
 * JavaScript moderno para interatividade e UX
 */

class MegavoteApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupFormValidation();
        this.setupTableFeatures();
        this.setupAnimations();
        this.setupTooltips();
    }

    /**
     * Configura event listeners principais
     */
    setupEventListeners() {
        // Confirmações de ações perigosas
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', (e) => {
                const message = element.getAttribute('data-confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        // Upload de arquivo com preview
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', this.handleFileUpload.bind(this));
        });

        // Botões de loading
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        });

        // Filtros de tabela
        const searchInputs = document.querySelectorAll('[data-table-search]');
        searchInputs.forEach(input => {
            input.addEventListener('input', this.handleTableSearch.bind(this));
        });
    }

    /**
     * Configura validação de formulários
     */
    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });

            // Validação em tempo real
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });
        });
    }

    /**
     * Configura recursos de tabela
     */
    setupTableFeatures() {
        // Ordenação de colunas
        document.querySelectorAll('[data-sortable]').forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(header);
            });
        });

        // Seleção de linhas
        document.querySelectorAll('table[data-selectable] tbody tr').forEach(row => {
            row.addEventListener('click', () => {
                row.classList.toggle('selected');
            });
        });
    }

    /**
     * Configura animações
     */
    setupAnimations() {
        // Fade in para elementos
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('megavote-fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.megavote-card, .megavote-alert').forEach(el => {
            observer.observe(el);
        });
    }

    /**
     * Configura tooltips
     */
    setupTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip.bind(this));
            element.addEventListener('mouseleave', this.hideTooltip.bind(this));
        });
    }

    /**
     * Manipula upload de arquivos
     */
    handleFileUpload(event) {
        const file = event.target.files[0];
        const preview = event.target.parentElement.querySelector('.file-preview');
        
        if (file) {
            const fileName = file.name;
            const fileSize = this.formatFileSize(file.size);
            
            if (preview) {
                preview.innerHTML = `
                    <div class="megavote-alert megavote-alert-info">
                        <span>📄 ${fileName} (${fileSize})</span>
                    </div>
                `;
            }

            // Validação de tipo de arquivo
            const allowedTypes = event.target.getAttribute('accept');
            if (allowedTypes && !this.isValidFileType(file, allowedTypes)) {
                this.showError(event.target, 'Tipo de arquivo não permitido');
                event.target.value = '';
                if (preview) preview.innerHTML = '';
            }
        }
    }

    /**
     * Manipula envio de formulários
     */
    handleFormSubmit(event) {
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="megavote-loading"></span> Processando...';
            submitBtn.disabled = true;

            // Restaura o botão após um tempo (caso não haja redirecionamento)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        }
    }

    /**
     * Manipula busca em tabelas
     */
    handleTableSearch(event) {
        const searchTerm = event.target.value.toLowerCase();
        const tableId = event.target.getAttribute('data-table-search');
        const table = document.getElementById(tableId);
        
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
    }

    /**
     * Valida formulário completo
     */
    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Valida campo individual
     */
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        let isValid = true;
        let errorMessage = '';

        // Validação de campo obrigatório
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'Este campo é obrigatório';
        }

        // Validação de email
        if (type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Email inválido';
        }

        // Validação de arquivo
        if (type === 'file' && field.hasAttribute('required') && !field.files.length) {
            isValid = false;
            errorMessage = 'Selecione um arquivo';
        }

        if (!isValid) {
            this.showError(field, errorMessage);
        } else {
            this.clearFieldError(field);
        }

        return isValid;
    }

    /**
     * Mostra erro em campo
     */
    showError(field, message) {
        this.clearFieldError(field);
        
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error megavote-text-small';
        errorDiv.style.color = 'var(--megavote-danger)';
        errorDiv.style.marginTop = '0.25rem';
        errorDiv.textContent = message;
        
        field.parentElement.appendChild(errorDiv);
    }

    /**
     * Remove erro de campo
     */
    clearFieldError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentElement.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    /**
     * Ordena tabela
     */
    sortTable(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(header.parentElement.children).indexOf(header);
        const isAscending = !header.classList.contains('sort-asc');

        rows.sort((a, b) => {
            const aValue = a.children[columnIndex].textContent.trim();
            const bValue = b.children[columnIndex].textContent.trim();
            
            // Tenta converter para número
            const aNum = parseFloat(aValue);
            const bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            }
            
            return isAscending ? 
                aValue.localeCompare(bValue) : 
                bValue.localeCompare(aValue);
        });

        // Remove classes de ordenação de todos os headers
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });

        // Adiciona classe de ordenação ao header atual
        header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');

        // Reordena as linhas
        rows.forEach(row => tbody.appendChild(row));
    }

    /**
     * Mostra tooltip
     */
    showTooltip(event) {
        const element = event.target;
        const text = element.getAttribute('data-tooltip');
        
        const tooltip = document.createElement('div');
        tooltip.className = 'megavote-tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: var(--megavote-gray-900);
            color: white;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        
        element._tooltip = tooltip;
    }

    /**
     * Esconde tooltip
     */
    hideTooltip(event) {
        const element = event.target;
        if (element._tooltip) {
            element._tooltip.remove();
            delete element._tooltip;
        }
    }

    /**
     * Utilitários
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidFileType(file, allowedTypes) {
        const types = allowedTypes.split(',').map(type => type.trim());
        return types.some(type => {
            if (type.startsWith('.')) {
                return file.name.toLowerCase().endsWith(type.toLowerCase());
            }
            return file.type.match(type);
        });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Métodos públicos para uso externo
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `megavote-alert megavote-alert-${type} megavote-fade-in`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        `;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="margin-left: 1rem; background: none; border: none; font-size: 1.2rem; cursor: pointer;">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
}

// Inicializa a aplicação quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.megavoteApp = new MegavoteApp();
});

// Funções globais para compatibilidade
function showNotification(message, type = 'info') {
    if (window.megavoteApp) {
        window.megavoteApp.showNotification(message, type);
    }
}

function confirmAction(message, callback) {
    if (window.megavoteApp) {
        window.megavoteApp.confirmAction(message, callback);
    }
}

