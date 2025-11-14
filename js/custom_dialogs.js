/** * custom_dialogs.js - Shared custom dialog system *
 * */
function createCustomDialogSystem() {
     // Check if modal already exists to prevent duplicates revisar si el modal ya existe para prevendir duplicados
     if (document.getElementById('customConfirmModal')) { 
        return; 
        } 
        const dialogHTML = `
         <!-- Custom Confirm Dialog --> 
         <div class="modal fade" 
              id="customConfirmModal" 
              tabindex="-1" 
              role="dialog" 
              aria-labelledby="customConfirmLabel" 
              aria-hidden="true"
              > 
            <div 
                class="modal-dialog 
                modal-dialog-centered" 
                role="document"
            > 
                <div class="modal-content"> 
                    <div class="modal-header">
                        <h5 class="modal-title" id="customConfirmLabel">
                            <i class="mdi mdi-help-circle-outline me-2"></i> 
                            <span id="confirmTitle">Confirmar acción</span>
                        </h5> 
                        <button 
                                type="button" 
                                class="btn-close" 
                                data-bs-dismiss="modal" 
                                aria-label="Close"
                                >
                        </button> 
                    </div>
                    <div class="modal-body"> 
                    <p id="confirmMessage" class="mb-0"></p> 
                </div> 
                <div class="modal-footer">
                    <button 
                        type="button" 
                        class="btn btn-secondary" 
                        data-bs-dismiss="modal" 
                        id="confirmCancelBtn"
                        >Cancelar
                    </button> 
                    <button 
                        type="button" 
                        class="btn btn-primary" 
                        id="confirmOkBtn">Aceptar
                    </button> 
                </div>
            </div> 
            </div> 
        </div> 
        `; 
        document.body.insertAdjacentHTML('beforeend', dialogHTML);
} 
     
function showConfirm(message, onConfirm, title = 'Confirmar acción', options = {}) {
    //inicializar si no existe
    if (!document.getElementById('customConfirmModal')) { 
            createCustomDialogSystem(); 
        } 
    const modal = document.getElementById('customConfirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const headerElement = modal.querySelector('.modal-header'); 
    const iconElement = modal.querySelector('.modal-title i'); 
    const confirmBtn = document.getElementById('confirmOkBtn'); 
    const cancelBtn = document.getElementById('confirmCancelBtn'); //configuracion default
    const config = { confirmText: 'Aceptar', cancelText: 'Cancelar', type: 'warning', ...options };
    //asignar titulo y mensaje
    Element.textContent = title; 
    messageElement.innerHTML = message.replace(/\n/g, '<br>'); 
    //asignar texto de boton 
    confirmBtn.textContent = config.confirmText; 
    cancelBtn.textContent = config.cancelText; 
    //reinicar clases de header 
    headerElement.className = 'modal-header'; 
    // mapeo de iconos y color para diferentes dialogos
    const iconMap = { 
        'info': 
            {   icon: 'mdi-information-outline', 
                class: 'bg-info text-white', 
                btnClass: 'btn-info' 
            }, 
        'warning': 
            {   icon: 'mdi-alert-outline', 
                class: 'bg-warning text-white', 
                btnClass: 'btn-warning' 
            }, 
        'danger': 
            {   icon: 'mdi-alert-octagon-outline', 
                class: 'bg-danger text-white', 
                btnClass: 'btn-danger' 
            }, 
        'success': 
            {   icon: 'mdi-check-circle-outline', 
                class: 'bg-success text-white', 
                btnClass: 'btn-success' } 
    }; 
    //aplicar diferentes estilos
    const typeConfig = iconMap[config.type] || iconMap['warning']; 
    iconElement.className = `mdi ${typeConfig.icon} me-2`; 
    headerElement.classList.add(...typeConfig.class.split(' ')); 
    confirmBtn.className = `btn ${typeConfig.btnClass}`; 
    //clonar botones para remover viejos event listeners y prevenir varios trigers
    const newConfirmBtn = confirmBtn.cloneNode(true); 
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn); 
    const newCancelBtn = cancelBtn.cloneNode(true); 
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    //agregar nuevo event listener para boton de confirmar  
    newConfirmBtn.addEventListener('click', function() { const confirmModal = bootstrap.Modal.getInstance(modal); 
    confirmModal.hide(); 
    if (onConfirm && typeof onConfirm === 'function') { 
        onConfirm(); 
    } 
    }); 
    //mostrar modal
    const confirmModal = new bootstrap.Modal(modal); 
    confirmModal.show(); 
} 
//hacer que las funciones esten globalmnte disponibles
window.showConfirm = showConfirm; 
window.createCustomDialogSystem = createCustomDialogSystem;