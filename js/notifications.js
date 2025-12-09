/*notifications.js para manejar las notificaciones hacia el frontend*/

const NotificationManager = {
    // Configuración
    config: {
        pollInterval: 30000, // Intervalo de polling en ms (30 segundos)
        maxNotifications: 10, // Máximo de notificaciones en el dropdown
        apiBasePath: '../php/'
    },
    
    // Estado
    state: {
        pollTimer: null,
        lastCount: 0
    },
    
    init: function() {
        this.loadNotifications();
        this.updateBadge();
        this.startPolling();
        this.bindEvents();
    },
    
    bindEvents: function() {
        // Evento al abrir el dropdown de notificaciones
        const dropdown = document.getElementById('countDropdown');
        if (dropdown) {
            dropdown.addEventListener('click', () => {
                this.loadNotifications();
            });
        }
        
        // Evento para marcar todas como leídas
        const markAllBtn = document.getElementById('markAllNotificationsRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.markAllAsRead();
            });
        }
    },
    
    startPolling: function() {
        this.state.pollTimer = setInterval(() => {
            this.updateBadge();
        }, this.config.pollInterval);
    },
    
    stopPolling: function() {
        if (this.state.pollTimer) {
            clearInterval(this.state.pollTimer);
            this.state.pollTimer = null;
        }
    },
    
    loadNotifications: function() {
        fetch(`${this.config.apiBasePath}/get_notifications.php?limite=${this.config.maxNotifications}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderNotifications(data.notificaciones);
                    this.updateBadgeCount(data.total_no_leidas);
                } else {
                    console.error('Error al cargar notificaciones:', data.message);
                }
            })
            .catch(error => {
                console.error('Error de red al cargar notificaciones:', error);
            });
    },
    
    updateBadge: function() {
        fetch(`${this.config.apiBasePath}/get_notification_count.php`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateBadgeCount(data.count);
                    
                    // Si hay nuevas notificaciones, reproducir sonido o mostrar alerta
                    if (data.count > this.state.lastCount && this.state.lastCount !== 0) {
                        this.onNewNotification(data.count - this.state.lastCount);
                    }
                    this.state.lastCount = data.count;
                }
            })
            .catch(error => {
                console.error('Error al actualizar badge:', error);
            });
    },
    
    updateBadgeCount: function(count) {
        const badge = document.querySelector('#countDropdown .count');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
                badge.classList.add('count-indicator-animate');
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
                badge.classList.remove('count-indicator-animate');
            }
        }
    },
    
    renderNotifications: function(notificaciones) {
        const container = document.getElementById('notificationsContainer');
        if (!container) return;
        
        if (notificaciones.length === 0) {
            container.innerHTML = `
                <div class="dropdown-item text-center py-4">
                    <i class="mdi mdi-bell-off mdi-36px text-muted"></i>
                    <p class="text-muted mt-2 mb-0">No tienes notificaciones</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        notificaciones.forEach(notif => {
            const unreadClass = notif.leido ? '' : 'notification-unread';
            const iconColor = `text-${notif.color}`;
            
            html += `
                <a class="dropdown-item preview-item ${unreadClass}" 
                   href="javascript:void(0)" 
                   data-notification-id="${notif.id_notificacion}"
                   data-ref-type="${notif.tipo_referencia || ''}"
                   data-ref-id="${notif.id_referencia || ''}"
                   onclick="NotificationManager.handleNotificationClick(${notif.id_notificacion}, '${notif.tipo_referencia || ''}', ${notif.id_referencia || 'null'})">
                    <div class="preview-thumbnail">
                        <i class="mdi ${notif.icono} ${iconColor} mdi-24px"></i>
                    </div>
                    <div class="preview-item-content flex-grow py-2">
                        <p class="preview-subject ellipsis font-weight-medium text-dark mb-1">${this.escapeHtml(notif.titulo)}</p>
                        <p class="fw-light small-text mb-0 text-muted">${this.escapeHtml(notif.mensaje)}</p>
                        <p class="fw-light small-text mb-0 text-primary">${notif.tiempo_relativo}</p>
                    </div>
                    <div class="preview-actions">
                        <button class="btn btn-sm btn-link text-danger p-0" 
                                onclick="event.stopPropagation(); NotificationManager.deleteNotification(${notif.id_notificacion})"
                                title="Eliminar">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </a>
            `;
        });
        
        container.innerHTML = html;
    },
    
    handleNotificationClick: function(id_notificacion, tipo_referencia, id_referencia) {
        // Marcar como leída
        this.markAsRead(id_notificacion);
        
        // Navegar según el tipo de referencia
        if (tipo_referencia && id_referencia) {
            let url = '';
            switch (tipo_referencia) {
                case 'proyecto':
                    url = `../revisarProyectos/?id=${id_referencia}`;
                    break;
                case 'tarea':
                    url = `../revisarTareas/?id=${id_referencia}`;
                    break;
                case 'objetivo':
                    url = `../revisarObjetivos/?id=${id_referencia}`;
                    break;
            }
            if (url) {
                window.location.href = url;
            }
        }
    },
    
    markAsRead: function(id_notificacion) {
        fetch(`${this.config.apiBasePath}/mark_as_read.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id_notificacion: id_notificacion })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar UI
                const item = document.querySelector(`[data-notification-id="${id_notificacion}"]`);
                if (item) {
                    item.classList.remove('notification-unread');
                }
                this.updateBadge();
            }
        })
        .catch(error => {
            console.error('Error al marcar como leída:', error);
        });
    },
    
    markAllAsRead: function() {
        fetch(`${this.config.apiBasePath}/mark_as_read.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ todas: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar UI
                document.querySelectorAll('.notification-unread').forEach(item => {
                    item.classList.remove('notification-unread');
                });
                this.updateBadgeCount(0);
                this.showToast('Todas las notificaciones marcadas como leídas', 'success');
            }
        })
        .catch(error => {
            console.error('Error al marcar todas como leídas:', error);
        });
    },
    
    deleteNotification: function(id_notificacion) {
        fetch(`${this.config.apiBasePath}/delete_notification.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id_notificacion: id_notificacion })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remover del DOM
                const item = document.querySelector(`[data-notification-id="${id_notificacion}"]`);
                if (item) {
                    item.remove();
                }
                this.updateBadge();
                this.showToast('Notificación eliminada', 'success');
            }
        })
        .catch(error => {
            console.error('Error al eliminar notificación:', error);
        });
    },
    
    onNewNotification: function(newCount) {
        // Opcional reproducir sonido
        // this.playNotificationSound();
        
        // mostrar notificación del navegador
        if (Notification.permission === 'granted') {
            new Notification('Nuevas notificaciones', {
                body: `Tienes ${newCount} nueva(s) notificación(es)`,
                icon: '../images/Nidec Institutional Logo_Original Version.png'
            });
        }
        
        // Actualizar dropdown si está abierto
        this.loadNotifications();
    },
    
    requestBrowserPermission: function() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    },
    
    showToast: function(message, type = 'info') {
        // Si existe la función showNotification del proyecto, usarla
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    },
    
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    NotificationManager.init();
    
    // Solicitar permiso para notificaciones del navegador
    NotificationManager.requestBrowserPermission();
});