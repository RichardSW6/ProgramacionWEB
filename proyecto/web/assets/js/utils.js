/**
 * utils.js - Utilidades generales de la aplicación
 * GastosEmp - Sistema de Gestión de Gastos Empresariales
 */

const Utils = {

    /**
     * Formatea un número como moneda MXN
     * @param {number|string} amount
     * @returns {string} Ej: $1,234.56 MXN
     */
    formatMoney(amount) {
        if (amount === null || amount === undefined || amount === '') return '$0.00 MXN';
        const num = parseFloat(amount);
        if (isNaN(num)) return '$0.00 MXN';
        return '$' + num.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' MXN';
    },

    /**
     * Formatea una fecha a DD/MM/YYYY
     * @param {string} dateStr - Fecha en formato ISO o similar
     * @returns {string}
     */
    formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            // Manejar fechas con y sin hora
            const date = new Date(dateStr.includes('T') ? dateStr : dateStr + 'T00:00:00');
            if (isNaN(date.getTime())) return dateStr;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        } catch (e) {
            return dateStr;
        }
    },

    /**
     * Formatea una fecha y hora a DD/MM/YYYY HH:mm
     * @param {string} dateStr
     * @returns {string}
     */
    formatDateTime(dateStr) {
        if (!dateStr) return '—';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const mins = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${mins}`;
        } catch (e) {
            return dateStr;
        }
    },

    /**
     * Genera HTML de badge de estado
     * @param {string} clave - Clave del estado (borrador, pendiente, aprobado, rechazado, finalizado)
     * @param {string} nombre - Nombre a mostrar
     * @returns {string} HTML del badge
     */
    statusBadge(clave, nombre) {
        const claveNorm = (clave || '').toLowerCase().trim();
        const cssClass = 'status-' + claveNorm;
        const label = nombre || clave || '—';
        return `<span class="status-badge ${cssClass}">${label}</span>`;
    },

    /**
     * Muestra un spinner de carga dentro de un selector
     * @param {string} selector - Selector jQuery del contenedor
     * @param {string} [msg='Cargando...'] - Mensaje opcional
     */
    showLoading(selector, msg = 'Cargando...') {
        $(selector).html(`
            <div class="loading-spinner">
                <div class="spinner-border" role="status"></div>
                <span class="text-secondary">${msg}</span>
            </div>
        `);
    },

    /**
     * Muestra un estado vacío dentro de un selector
     * @param {string} selector
     * @param {string} [msg='Sin resultados']
     */
    showEmpty(selector, msg = 'Sin resultados') {
        $(selector).html(`
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <h5>Sin datos</h5>
                <p>${msg}</p>
            </div>
        `);
    },

    /**
     * Muestra una alerta de error con SweetAlert2
     * @param {string} msg - Mensaje de error
     * @param {string} [title='Error']
     */
    showError(msg, title = 'Error') {
        Swal.fire({
            icon: 'error',
            title: title,
            text: msg,
            confirmButtonText: 'Aceptar',
        });
    },

    /**
     * Muestra un toast de éxito (top-right)
     * @param {string} msg
     */
    showSuccess(msg) {
        Swal.fire({
            icon: 'success',
            title: msg,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    },

    /**
     * Muestra un diálogo de confirmación con SweetAlert2
     * @param {string} msg - Mensaje de confirmación
     * @param {Function} callback - Función a ejecutar si el usuario confirma
     * @param {Object} [options] - Opciones adicionales
     */
    confirm(msg, callback, options = {}) {
        Swal.fire({
            title: options.title || '¿Estás seguro?',
            text: msg,
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Sí, confirmar',
            cancelButtonText: options.cancelText || 'Cancelar',
            reverseButtons: true,
        }).then(result => {
            if (result.isConfirmed) {
                callback();
            }
        });
    },

    /**
     * Genera HTML de paginación Bootstrap
     * @param {number} currentPage - Página actual (1-indexed)
     * @param {number} totalPages - Total de páginas
     * @returns {string} HTML de paginación
     */
    paginationHTML(currentPage, totalPages) {
        if (totalPages <= 1) return '';

        let html = '<ul class="pagination">';

        // Botón anterior
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>`;

        // Páginas
        const range = Utils._getPageRange(currentPage, totalPages);
        let lastPage = 0;

        for (const page of range) {
            if (page === '...') {
                html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            } else {
                html += `<li class="page-item ${page === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${page}">${page}</a>
                </li>`;
            }
            lastPage = page;
        }

        // Botón siguiente
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>`;

        html += '</ul>';
        return html;
    },

    /**
     * Calcula el rango de páginas con elipsis
     * @private
     */
    _getPageRange(current, total) {
        const delta = 2;
        const range = [];
        const rangeWithDots = [];

        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
            range.push(i);
        }

        if (current - delta > 2) range.unshift('...');
        if (current + delta < total - 1) range.push('...');

        range.unshift(1);
        if (total > 1) range.push(total);

        return range;
    },

    /**
     * Obtiene parámetros de la URL
     * @param {string} name
     * @returns {string|null}
     */
    getQueryParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    },

    /**
     * Calcula el porcentaje de ejecución de presupuesto
     * @param {number} aprobado
     * @param {number} asignado
     * @returns {number}
     */
    calcPercent(aprobado, asignado) {
        if (!asignado || asignado === 0) return 0;
        return Math.min(Math.round((aprobado / asignado) * 100), 999);
    },

    /**
     * Color de barra de progreso según porcentaje
     * @param {number} percent
     * @returns {string}
     */
    progressColor(percent) {
        if (percent >= 100) return 'bg-danger';
        if (percent >= 80) return 'bg-warning';
        return 'bg-success';
    },

    /**
     * Genera HTML de barra de progreso
     * @param {number} percent
     * @returns {string}
     */
    progressBar(percent) {
        const color = this.progressColor(percent);
        const display = Math.min(percent, 100);
        return `
            <div class="progress-wrapper">
                <div class="progress flex-grow-1">
                    <div class="progress-bar ${color}" role="progressbar"
                         style="width: ${display}%"
                         aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <span class="ms-2 small ${color === 'bg-danger' ? 'text-danger-custom' : color === 'bg-warning' ? 'text-warning-custom' : 'text-success-custom'}"
                      style="min-width:40px; text-align:right;">
                    ${percent}%
                </span>
            </div>`;
    },

    /**
     * Deshabilita/habilita un botón con spinner
     * @param {string|jQuery} btn - Selector o elemento jQuery
     * @param {boolean} loading
     * @param {string} [loadingText='Procesando...']
     */
    setLoading(btn, loading, loadingText = 'Procesando...') {
        const $btn = $(btn);
        if (loading) {
            $btn.data('original-text', $btn.html());
            $btn.html(`<span class="spinner-border spinner-border-sm me-2" role="status"></span>${loadingText}`);
            $btn.prop('disabled', true);
        } else {
            const orig = $btn.data('original-text');
            if (orig) $btn.html(orig);
            $btn.prop('disabled', false);
        }
    },

    /**
     * Retorna el nombre del rol según ID
     * @param {number} rolId
     * @returns {string}
     */
    roleName(rolId) {
        const names = {
            1: 'Administrador',
            2: 'Capturista',
            3: 'Jefe de Área',
            4: 'Cuentas por Pagar',
        };
        return names[rolId] || 'Desconocido';
    },

    /**
     * Badge HTML para rol de usuario
     * @param {number} rolId
     * @returns {string}
     */
    roleBadge(rolId) {
        return `<span class="badge badge-role-${rolId} px-2 py-1">${this.roleName(rolId)}</span>`;
    },
};
