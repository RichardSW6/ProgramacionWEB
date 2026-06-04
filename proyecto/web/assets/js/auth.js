/**
 * auth.js - Manejo de autenticación y sesión de usuario
 * GastosEmp - Sistema de Gestión de Gastos Empresariales
 */

const Auth = {
    STORAGE_KEY: 'gastos_user',

    ROLE_NAMES: {
        1: 'Administrador',
        2: 'Capturista',
        3: 'Jefe de Área',
        4: 'Cuentas por Pagar',
    },

    /**
     * Obtiene el usuario actual del localStorage
     * @returns {Object|null}
     */
    getUser() {
        try {
            const raw = localStorage.getItem(this.STORAGE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            this.clear();
            return null;
        }
    },

    /**
     * Guarda el usuario en localStorage
     * @param {Object} user
     */
    setUser(user) {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(user));
        } catch (e) {
            console.error('Error guardando usuario en localStorage:', e);
        }
    },

    /**
     * Limpia los datos de sesión del localStorage
     */
    clear() {
        localStorage.removeItem(this.STORAGE_KEY);
    },

    /**
     * Verifica que el usuario esté autenticado.
     * Si no hay usuario, redirige al login.
     */
    check() {
        const user = this.getUser();
        if (!user) {
            window.location.href = '/proyecto/web/index.html';
            return false;
        }
        return true;
    },

    /**
     * Cierra la sesión del usuario
     */
    logout() {
        API.post('/auth/logout', {})
            .catch(() => {
                // Aunque falle la API, limpiar localmente
            })
            .finally(() => {
                this.clear();
                window.location.href = '/proyecto/web/index.html';
            });
    },

    /**
     * Verifica si el usuario tiene alguno de los roles especificados
     * @param {...number} roles - IDs de roles permitidos
     * @returns {boolean}
     */
    hasRole(...roles) {
        const user = this.getUser();
        if (!user) return false;
        return roles.includes(Number(user.rol_id));
    },

    /**
     * Requiere que el usuario tenga alguno de los roles especificados.
     * Si no los tiene, redirige al dashboard.
     * @param {number[]} roles
     */
    requireRole(roles) {
        if (!this.hasRole(...roles)) {
            window.location.href = '/proyecto/web/dashboard.html';
            return false;
        }
        return true;
    },

    /**
     * Carga los datos del usuario en los elementos del sidebar/navbar
     */
    loadUserUI() {
        const user = this.getUser();
        if (!user) return;

        const roleName = this.ROLE_NAMES[user.rol_id] || 'Usuario';
        const fullName = [user.nombre, user.apellido].filter(Boolean).join(' ') || user.correo || 'Usuario';
        const initials = fullName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

        // Nombre y rol
        $('.user-name').text(fullName);
        $('.user-role').text(roleName);
        $('.user-avatar').text(initials);

        // Ocultar/mostrar elementos según rol
        $('[data-roles]').each(function () {
            const allowed = $(this).data('roles').toString().split(',').map(Number);
            if (!allowed.includes(Number(user.rol_id))) {
                $(this).hide();
            }
        });

        // Ocultar menú admin si no es rol 1
        if (!Auth.hasRole(1)) {
            $('.nav-admin').hide();
        }

        // Ocultar reportes si no es rol 1 o 4
        if (!Auth.hasRole(1, 4)) {
            $('.nav-reportes').hide();
        }

        // Configurar botón logout
        $('#btn-logout').off('click').on('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: 'Tu sesión actual se cerrará.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, salir',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    Auth.logout();
                }
            });
        });
    },

    /**
     * Marca el ítem de navegación activo según la URL actual
     */
    setActiveNav() {
        const currentPath = window.location.pathname;
        $('.nav-link').each(function () {
            const href = $(this).attr('href');
            if (href && currentPath.endsWith(href)) {
                $(this).addClass('active');
            }
        });
    },
};
