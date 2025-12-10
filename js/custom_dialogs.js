/**  custom_dialogs.js para los dialogos personalizados compartidos para el sistema */

// Variable para almacenar la instancia del modal
let confirmModalInstance = null;

function initializeCustomDialogs() {
    if (document.getElementById('customConfirmModal')) {
        return;
    }

    // Crear modal HTML con estructura consistente
    const modalHTML = `
        <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white" id="confirmHeader">
                        <h5 class="modal-title" id="customConfirmLabel">
                            <i class="mdi mdi-alert-outline me-2" id="confirmIcon"></i>
                            <span id="confirmTitle">Confirmar acción</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMessage" class="mb-0">¿Está seguro de que desea continuar con esta acción?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="confirmCancelBtn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-warning" id="confirmOkBtn">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Inicializar cuando el DOM está listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCustomDialogs);
} else {
    initializeCustomDialogs();
}

function createCustomDialogSystem() {
    // Esta función ahora solo llama a initializeCustomDialogs para mantener consistencia
    initializeCustomDialogs();
}

function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    // Inicializar si no existe
    if (!document.getElementById('customConfirmModal')) {
        initializeCustomDialogs();
    }

    const modal = document.getElementById('customConfirmModal');
    if (!modal) {
        console.error('No se pudo crear el modal de confirmación');
        return;
    }

    // Obtener elementos con verificación de null
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = document.getElementById('confirmHeader');
    const iconElement = document.getElementById('confirmIcon');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');

    // Verificar que todos los elementos existen
    if (!titleElement || !messageElement || !headerElement || !iconElement || !confirmBtn || !cancelBtn) {
        console.error('Elementos del modal no encontrados, recreando modal...');
        // Remover modal corrupto y recrear
        modal.remove();
        confirmModalInstance = null;
        initializeCustomDialogs();
        // Intentar de nuevo después de recrear
        setTimeout(() => showConfirm(message, onConfirm, title, options), 100);
        return;
    }

    // Configuración default
    const config = {
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        type: 'warning',
        ...options
    };

    // Asignar título y mensaje
    titleElement.textContent = title;
    messageElement.innerHTML = message.replace(/\n/g, '<br>');

    // Asignar texto de botones
    confirmBtn.textContent = config.confirmText;
    cancelBtn.textContent = config.cancelText;

    // Mapeo de iconos y color para diferentes dialogos
    const iconMap = {
        'info': {
            icon: 'mdi-information-outline',
            headerClass: 'bg-info text-white',
            btnClass: 'btn-info'
        },
        'warning': {
            icon: 'mdi-alert-outline',
            headerClass: 'bg-warning text-white',
            btnClass: 'btn-warning'
        },
        'danger': {
            icon: 'mdi-alert-octagon-outline',
            headerClass: 'bg-danger text-white',
            btnClass: 'btn-danger'
        },
        'success': {
            icon: 'mdi-check-circle-outline',
            headerClass: 'bg-success text-white',
            btnClass: 'btn-success'
        }
    };

    // Aplicar diferentes estilos
    const typeConfig = iconMap[config.type] || iconMap['warning'];

    // Actualizar icono
    iconElement.className = `mdi ${typeConfig.icon} me-2`;

    // Actualizar header - remover clases previas y agregar nuevas
    headerElement.className = 'modal-header ' + typeConfig.headerClass;

    // Actualizar botón de confirmar
    confirmBtn.className = `btn ${typeConfig.btnClass}`;

    // Remover event listeners previos clonando los botones
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

    // Agregar event listener para botón de confirmar
    newConfirmBtn.addEventListener('click', function() {
        hideConfirmModal();
        if (onConfirm && typeof onConfirm === 'function') {
            // Pequeño delay para asegurar que el modal se cierre antes de ejecutar la acción
            setTimeout(onConfirm, 150);
        }
    });

    // Agregar event listener para botón de cancelar (aunque data-bs-dismiss también lo maneja)
    newCancelBtn.addEventListener('click', function() {
        hideConfirmModal();
    });

    // Mostrar modal usando instancia existente o creando nueva
    showConfirmModal();
}

function showConfirmModal() {
    const modal = document.getElementById('customConfirmModal');
    if (!modal) return;

    // Obtener instancia existente o crear nueva
    confirmModalInstance = bootstrap.Modal.getInstance(modal);
    
    if (!confirmModalInstance) {
        confirmModalInstance = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: true
        });
    }

    confirmModalInstance.show();
}

function hideConfirmModal() {
    const modal = document.getElementById('customConfirmModal');
    if (!modal) return;

    confirmModalInstance = bootstrap.Modal.getInstance(modal);
    
    if (confirmModalInstance) {
        confirmModalInstance.hide();
    }
}

// Función de utilidad para mostrar alertas simples (sin confirmación)
function showAlert(message, title = 'Aviso', type = 'info') {
    showConfirm(message, null, title, {
        type: type,
        confirmText: 'Entendido',
        cancelText: null // Ocultar botón cancelar para alertas
    });
    
    // Ocultar botón cancelar
    const cancelBtn = document.getElementById('confirmCancelBtn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Restaurar botón cancelar cuando se cierre el modal
document.addEventListener('hidden.bs.modal', function(event) {
    if (event.target.id === 'customConfirmModal') {
        const cancelBtn = document.getElementById('confirmCancelBtn');
        if (cancelBtn) {
            cancelBtn.style.display = '';
        }
    }
});

// Hacer que las funciones estén globalmente disponibles
window.showConfirm = showConfirm;
window.showAlert = showAlert;
window.createCustomDialogSystem = createCustomDialogSystem;
window.hideConfirmModal = hideConfirmModal;