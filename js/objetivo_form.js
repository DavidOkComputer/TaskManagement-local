
document.addEventListener('DOMContentLoaded', function() {
    loadDepartamentos();//cargar departamentos al cargar la pagina
    
    initFileUpload();//inicializar carga de pagina
    
    const form = document.getElementById('formCrearObjetivo');//maneja la creacion del form
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    const cancelBtn = document.querySelector('.btn-light');//manejo del boton cancelar
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('¿Está seguro de que desea cancelar? Se perderán los datos ingresados.')) {
                form.reset();
                document.getElementById('fileUploadLabel').value = '';
            }
        });
    }
});

function loadDepartamentos() {
  fetch('../php/get_departments.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de red no fue ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.departamentos) {
        const select = document.getElementById('id_departamento');
        data.departamentos.forEach(dept => {
          const option = document.createElement('option');
          option.value = dept.id_departamento;
          option.textContent = dept.nombre;
          select.appendChild(option);
        });
      } else {
        showAlert('Error al cargar departamentos', 'warning');
      }
    })
    .catch(error => {
      console.error('Error al cargar los departamentos:', error);
      showAlert('Error al cargar departamentos', 'danger');
    });
}

function initFileUpload() {
    const fileInput = document.querySelector('.file-upload-default');
    const fileLabel = document.getElementById('fileUploadLabel');
    const uploadBtn = document.querySelector('.file-upload-browse');
    
    if (uploadBtn && fileInput && fileLabel) {
        uploadBtn.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileLabel.value = this.files[0].name;
            } else {
                fileLabel.value = '';
            }
        });
    }
}

function handleFormSubmit(e) {//manejo de informacion del form
    e.preventDefault();
    
    const formData = new FormData(this);
    
    const fileInput = document.querySelector('.file-upload-default');
    if (fileInput && fileInput.files.length > 0) {
        formData.append('archivo', fileInput.files[0]);
    }
    //se agrega el ID del creador (se obtiene desde la sesion)
    const idCreador = getUserId();
    formData.append('id_creador', idCreador);
    
    //validar el form
    if (!validateForm(formData)) {
        return;
    }
    
    //se muestra el estado de carga
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creando...';
    
    //subir form
    fetch('../php/create_objective.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Objetivo creado exitosamente', 'success');
            
            //reiniciar form despues de un segundo
            setTimeout(() => {
                this.reset();
                document.getElementById('fileUploadLabel').value = '';
                
                //si se quiere redirigir a la lista de objetivos de manera automatica
                // window.location.href = '../revisarObjetivos';
            }, 1500);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al crear el objetivo. Por favor, intente nuevamente.', 'error');
    })
    .finally(() => {
        //restaurar el estado original del boton
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function validateForm(formData) {
    const nombre = formData.get('nombre');
    const descripcion = formData.get('descripcion');
    const fecha_cumplimiento = formData.get('fecha_cumplimiento');
    const id_departamento = formData.get('id_departamento');
    
    // Check required fields
    if (!nombre || nombre.trim() === '') {
        showNotification('El nombre es requerido', 'warning');
        document.getElementById('nombre').focus();
        return false;
    }
    
    if (nombre.length > 100) {
        showNotification('El nombre no puede exceder 100 caracteres', 'warning');
        document.getElementById('nombre').focus();
        return false;
    }
    
    if (!descripcion || descripcion.trim() === '') {
        showNotification('La descripción es requerida', 'warning');
        document.getElementById('descripcion').focus();
        return false;
    }
    
    if (descripcion.length > 200) {
        showNotification('La descripción no puede exceder 200 caracteres', 'warning');
        document.getElementById('descripcion').focus();
        return false;
    }
    
    if (!fecha_cumplimiento) {
        showNotification('La fecha de cumplimiento es requerida', 'warning');
        document.getElementById('fecha_cumplimiento').focus();
        return false;
    }
    
    
    if (!id_departamento || id_departamento === '') {
        showNotification('Debe seleccionar un departamento', 'warning');
        document.getElementById('id_departamento').focus();
        return false;
    }
    
    //validar el tamaño del archivo que se sube
    const fileInput = document.querySelector('.file-upload-default');
    if (fileInput && fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (file.size > maxSize) {
            showNotification('El archivo no puede exceder 10MB', 'warning');
            return false;
        }
    }
    
    return true;
}

/**
 * tomar el Id de la sesion
 * implementar basado en el sistema de autenticacion
 */
function getUserId() {
    
    //return sessionStorage.getItem('userId');
    // uso de id default 
    return 1; // se remplaza con id que se toma de la sesion
}

function showNotification(message, type = 'info') {
    //revisar si existe el container para la notificacion
    let container = document.getElementById('notificationContainer');
    
    if (!container) {
        //creacion de container para notificaciones
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        `;
        document.body.appendChild(container);
    }
    
    //crear elemento de notificaciones
    const notification = document.createElement('div');
    notification.className = `alert alert-${getAlertClass(type)} alert-dismissible fade show`;
    notification.setAttribute('role', 'alert');
    notification.innerHTML = `
        <strong>${getAlertTitle(type)}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    container.appendChild(notification);
    
    //auto remover despues de 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 150);
    }, 5000);
}

function getAlertClass(type) {
    const classes = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    return classes[type] || 'info';
}

function getAlertTitle(type) {
    const titles = {
        'success': '¡Éxito!',
        'error': 'Error:',
        'warning': 'Advertencia:',
        'info': 'Información:'
    };
    return titles[type] || 'Aviso:';
}

function setupCharacterCounters() {
    const nombreInput = document.getElementById('nombre');
    const descripcionInput = document.getElementById('descripcion');
    
    if (nombreInput) {
        addCharacterCounter(nombreInput, 100);
    }
    
    if (descripcionInput) {
        addCharacterCounter(descripcionInput, 200);
    }
}

function addCharacterCounter(input, maxLength) {
    const counter = document.createElement('small');
    counter.className = 'form-text text-muted';
    counter.textContent = `0/${maxLength} caracteres`;
    
    input.parentElement.appendChild(counter);
    
    input.addEventListener('input', function() {
        const length = this.value.length;
        counter.textContent = `${length}/${maxLength} caracteres`;
        
        if (length > maxLength) {
            counter.classList.add('text-danger');
            counter.classList.remove('text-muted');
        } else {
            counter.classList.add('text-muted');
            counter.classList.remove('text-danger');
        }
    });
}

document.addEventListener('DOMContentLoaded', setupCharacterCounters);