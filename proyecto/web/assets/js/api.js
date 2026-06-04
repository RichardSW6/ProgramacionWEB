/**
 * api.js - Wrapper para llamadas AJAX a la API REST
 * GastosEmp - Sistema de Gestión de Gastos Empresariales
 */

const API = {
    BASE: '/proyecto/api/public',

    /**
     * Realiza una petición AJAX
     * @param {string} method - Método HTTP (GET, POST, PUT, DELETE)
     * @param {string} endpoint - Endpoint relativo
     * @param {*} data - Datos a enviar
     * @param {boolean} isFormData - Si los datos son FormData (multipart)
     * @returns {Promise}
     */
    _request(method, endpoint, data = null, isFormData = false) {
        const config = {
            url: this.BASE + endpoint,
            method: method,
            xhrFields: { withCredentials: true },
            headers: {},
        };

        if (isFormData) {
            config.contentType = false;
            config.processData = false;
            config.data = data;
        } else {
            config.contentType = 'application/json';
            config.processData = false;
            if (data !== null) {
                config.data = JSON.stringify(data);
            }
        }

        return new Promise((resolve, reject) => {
            $.ajax(config)
                .done(function (response) {
                    // Si la API retorna success: false en el cuerpo
                    if (response && response.success === false) {
                        reject({
                            message: response.message || 'Error en la operación',
                            data: response
                        });
                    } else {
                        resolve(response);
                    }
                })
                .fail(function (xhr) {
                    const status = xhr.status;
                    let errorMsg = 'Error de conexión con el servidor';

                    // Intentar parsear la respuesta de error
                    try {
                        const errData = JSON.parse(xhr.responseText);
                        errorMsg = errData.message || errorMsg;
                    } catch (e) {
                        // Usar mensaje genérico
                    }

                    // Manejar errores HTTP específicos
                    if (status === 401) {
                        // No autenticado: redirigir al login
                        Auth.clear();
                        window.location.href = '/proyecto/web/index.html';
                        return;
                    }

                    if (status === 403) {
                        // Sin permisos
                        Swal.fire({
                            icon: 'error',
                            title: 'Sin permisos',
                            text: 'No tienes permisos para realizar esta acción.',
                            confirmButtonText: 'Entendido',
                        });
                        reject({ message: errorMsg, status });
                        return;
                    }

                    if (status === 404) {
                        errorMsg = 'Recurso no encontrado.';
                    }

                    if (status === 422) {
                        try {
                            const errData = JSON.parse(xhr.responseText);
                            errorMsg = errData.message || 'Datos inválidos.';
                        } catch (e) {}
                    }

                    if (status === 500) {
                        errorMsg = 'Error interno del servidor. Por favor intente más tarde.';
                    }

                    reject({ message: errorMsg, status, xhr });
                });
        });
    },

    /**
     * GET request
     */
    get(endpoint) {
        return this._request('GET', endpoint);
    },

    /**
     * POST request con JSON
     */
    post(endpoint, data) {
        return this._request('POST', endpoint, data);
    },

    /**
     * PUT request con JSON
     */
    put(endpoint, data) {
        return this._request('PUT', endpoint, data);
    },

    /**
     * DELETE request
     */
    delete(endpoint) {
        return this._request('DELETE', endpoint);
    },

    /**
     * POST multipart/form-data (subida de archivos)
     */
    upload(endpoint, formData) {
        return this._request('POST', endpoint, formData, true);
    },

    /**
     * PUT multipart/form-data (actualización con archivos)
     */
    putUpload(endpoint, formData) {
        return this._request('PUT', endpoint, formData, true);
    },
};
