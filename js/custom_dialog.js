/**custom_dialog.js - dialogo personalizado de la app*/

function createCustomDialogSystem() {
    if (document.getElementById('customConfirmModal')) {
        return;
    }//prevenir duplicados

    const dialogHTML = `
        <!-- Custom Confirm Dialog -->
        <div class="modal fade" id="customConfirmModal" tabindex="-1" role="dialog" aria-labelledby="customConfirmLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="customConfirmLabel">
                            <i class="mdi mdi-help-circle-outline me-2"></i>
                            <span id="confirmTitle">Confirmar acción</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMessage" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmCancelBtn">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="confirmOkBtn">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', dialogHTML);
}

function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    if (!document.getElementById('customConfirmModal')) {//inicializar el dialogo si no existe
        createCustomDialogSystem();
    }
 
    const modal = document.getElementById('customConfirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = modal.querySelector('.modal-header');
    const iconElement = modal.querySelector('.modal-title i');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
 
    const config = {
        confirmText: 'Aceptar',
        cancelText: 'Cancelar',
        type: 'warning',
        onCancel: null,
        ...options
    };
 
    titleElement.textContent = title;
    messageElement.innerHTML = message.replace(/\n/g, '<br>');
 
    confirmBtn.textContent = config.confirmText;
    cancelBtn.textContent = config.cancelText;
 
    headerElement.className = 'modal-header';
 
    const iconMap = {
        'info': { icon: 'mdi-information-outline', class: 'bg-info text-white', btnClass: 'btn-info' },
        'warning': { icon: 'mdi-alert-outline', class: 'bg-warning text-white', btnClass: 'btn-warning' },
        'danger': { icon: 'mdi-alert-octagon-outline', class: 'bg-danger text-white', btnClass: 'btn-danger' },
        'success': { icon: 'mdi-check-circle-outline', class: 'bg-success text-white', btnClass: 'btn-success' }
    };
 
    const typeConfig = iconMap[config.type] || iconMap['warning'];
    iconElement.className = `mdi ${typeConfig.icon} me-2`;
    headerElement.classList.add(...typeConfig.class.split(' '));
    confirmBtn.className = `btn ${typeConfig.btnClass}`;
 
    const newConfirmBtn = confirmBtn.cloneNode(true);//clonar botones para remover listeners de eventos pasados
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
 
    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
 
    newConfirmBtn.addEventListener('click', function() {//agregar boton de confirmar al listener de  evento
        const confirmModal = bootstrap.Modal.getInstance(modal);
        confirmModal.hide();
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
 
    newCancelBtn.addEventListener('click', function() {//agregar boton de cancelar listener de eventos con oncancel
        const confirmModal = bootstrap.Modal.getInstance(modal);
        confirmModal.hide();
        if (config.onCancel && typeof config.onCancel === 'function') {
            config.onCancel();
        }
    });
 
    //manejar el cierre del modal con un boton x
    modal.addEventListener('hidden.bs.modal', function handler() {
        modal.removeEventListener('hidden.bs.modal', handler);
        //solo el oncancel si no es confirmado
    }, { once: true });
 
    const confirmModal = new bootstrap.Modal(modal);
    confirmModal.show();
}

window.showConfirm = showConfirm;
window.createCustomDialogSystem = createCustomDialogSystem;