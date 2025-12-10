/**user_objetivo_form.js para Manejar creacion y edicion de objetivos de gerente */

const editMode = { 
    isEditing: false, 
    objectiveId: null 
};

// Estado del departamento del gerente
const managerState = {
    departmentId: null,
    departmentName: null
};

document.addEventListener('DOMContentLoaded', function() { 
    // Detectar si estamos en modo edición
    const params = new URLSearchParams(window.location.search); 
    editMode.objectiveId = params.get('edit'); 
    editMode.isEditing = !!editMode.objectiveId; 
    
    // Cambiar título y botón si estamos editando
    if (editMode.isEditing) { 
        const titleElement = document.querySelector('h4.card-title'); 
        if (titleElement) { 
            titleElement.textContent = 'Editar Objetivo'; 
        } 
        const subtitleElement = document.querySelector('p.card-subtitle'); 
        if (subtitleElement) { 
            subtitleElement.textContent = 'Modifica la información del objetivo';
        } 
        const submitBtn = document.querySelector('button[type="submit"]'); 
        if (submitBtn) { 
            submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Actualizar Objetivo'; 
        } 
    } 
    
    loadDepartamentoGerente();
    initFileUpload();
    
    // Maneja la creación/edición del form
    const form = document.getElementById('formCrearObjetivo');
    if (form) { 
        form.addEventListener('submit', handleFormSubmit); 
    } 
    
    // Manejo del botón cancelar
    const cancelBtn = document.querySelector('.btn-light');
    if (cancelBtn) { 
        cancelBtn.addEventListener('click', function(e) { 
            e.preventDefault(); 
            showConfirm(
                '¿Estás seguro de que deseas cancelar?\n\nLos datos ingresados se perderán.', 
                function() { 
                    window.location.href = '../revisarObjetivosUser/'; 
                },
                'Cancelar cambios', 
                { 
                    type: 'warning', 
                    confirmText: 'Sí, cancelar', 
                    cancelText: 'Volver al formulario' 
                } 
            );
        });
    } 
    
    // Si es edición, cargar datos del objetivo
    if (editMode.isEditing) {
        cargarObjetivoParaEditar(editMode.objectiveId); 
    }
    setupCharacterCounters();
}); 

function loadDepartamentoGerente() {
    fetch('../php/manager_get_departments.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('La respuesta de red no fue ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.departamentos && data.departamentos.length > 0) {
                const departamento = data.departamentos[0]; // Solo el departamento del gerente
                const select = document.getElementById('id_departamento');
                const hiddenInput = document.getElementById('id_departamento_hidden');
                
                // Guardar información del departamento
                managerState.departmentId = departamento.id_departamento;
                managerState.departmentName = departamento.nombre;
                
                // Limpiar y configurar el select
                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = departamento.id_departamento;
                option.textContent = departamento.nombre;
                option.selected = true;
                select.appendChild(option);
                
                // Deshabilitar el select (no se puede cambiar)
                select.disabled = true;
                select.style.backgroundColor = '#f8f9fa';
                select.style.cursor = 'not-allowed';
                
                // Establecer el valor en el campo oculto
                if (hiddenInput) {
                    hiddenInput.value = departamento.id_departamento;
                }
                
            } else {
                showNotification('Error: No se pudo determinar tu departamento', 'error');
            }
        })
        .catch(error => {
            console.error('Error al cargar el departamento:', error);
            showNotification('Error al cargar el departamento', 'error');
        });
}

function cargarObjetivoParaEditar(objectiveId) { 
    fetch(`../php/get_objective_by_id.php?id=${objectiveId}`) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('La respuesta de red no fue ok'); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.objetivo) { 
                const objetivo = data.objetivo; 
                
                // Llenar formulario con datos del objetivo
                document.getElementById('nombre').value = objetivo.nombre || ''; 
                document.getElementById('descripcion').value = objetivo.descripcion || ''; 
                
                // Manejar fechas
                if (objetivo.fecha_inicio) {
                    document.getElementById('fecha_inicio').value = objetivo.fecha_inicio.split(' ')[0];
                }
                if (objetivo.fecha_cumplimiento) {
                    document.getElementById('fecha_cumplimiento').value = objetivo.fecha_cumplimiento.split(' ')[0];
                }
                
                document.getElementById('ar').value = objetivo.ar || ''; 
                
                // Verificar que el departamento del objetivo coincida con el del gerente
                if (objetivo.id_departamento && objetivo.id_departamento != managerState.departmentId) {
                   // showNotification('Advertencia: Este objetivo pertenece a otro departamento', 'warning');
                }
                
                // Mostrar archivo adjunto si existe
                if (objetivo.archivo_adjunto) { 
                    document.getElementById('fileUploadLabel').value = objetivo.archivo_adjunto.split('/').pop(); 
                    const fileContainer = document.querySelector('.input-group'); 
                    if (fileContainer) { 
                        const fileInfo = document.createElement('small'); 
                        fileInfo.className = 'text-muted d-block mt-2'; 
                        fileInfo.id = 'archivoActual'; 
                        fileInfo.innerHTML = `Archivo actual: <a href="../${objetivo.archivo_adjunto}" target="_blank">${objetivo.archivo_adjunto.split('/').pop()}</a>`;
                        fileContainer.parentElement.appendChild(fileInfo);
                    } 
                } 
                
                showNotification('Objetivo cargado correctamente', 'success'); 
            } else { 
                showNotification('Error al cargar el objetivo: ' + data.message, 'warning'); 
                window.location.href = '../revisarObjetivosUser/'; 
            } 
        }) 
        .catch(error => { 
            console.error('Error al cargar objetivo:', error); 
            showNotification('Error al cargar el objetivo: ' + error.message, 'error');
            window.location.href = '../revisarObjetivosUser/'; 
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

function handleFormSubmit(e) {
    e.preventDefault(); 
    
    // Validar que el departamento esté establecido
    if (!managerState.departmentId) {
        showNotification('Error: No se ha establecido el departamento', 'error');
        return;
    }
    
    const formData = new FormData(this); 
    const fileInput = document.querySelector('.file-upload-default'); 
    
    if (fileInput && fileInput.files.length > 0) { 
        formData.append('archivo', fileInput.files[0]); 
    } 
    
    // Asegurar que el departamento correcto esté en el formData
    formData.set('id_departamento', managerState.departmentId);
    
    // Agregar el ID del creador (se obtiene desde la función global)
    const idCreador = getUserId(); 
    formData.set('id_creador', idCreador); 
    
    // Si es edición, agregar ID del objetivo
    if (editMode.isEditing) { 
        formData.set('id_objetivo', editMode.objectiveId);
    } 
    
    // Validar el form
    if (!validateForm(formData)) { 
        return; 
    } 
    
    // Mostrar el estado de carga
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML; 
    submitBtn.disabled = true; 
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + 
                          (editMode.isEditing ? 'Actualizando...' : 'Creando...');
    
    // Elegir endpoint según modo
    const endpoint = editMode.isEditing 
        ? '../php/update_objective.php' 
        : '../php/create_objective.php'; 
    
    // Subir form
    fetch(endpoint, { 
        method: 'POST', 
        body: formData 
    }) 
    .then(response => response.json()) 
    .then(data => { 
        if (data.success) { 
            const successMessage = editMode.isEditing 
                ? '¡Objetivo actualizado exitosamente!' 
                : '¡Objetivo creado exitosamente!';
            showNotification(successMessage, 'success');
            
            // Redirigir después de 1.5 segundos
            setTimeout(() => { 
                window.location.href = '../revisarObjetivosUser/'; 
            }, 1500); 
        } else {
            showNotification('Error: ' + data.message, 'error'); 
            submitBtn.disabled = false; 
            submitBtn.innerHTML = originalText; 
        } 
    })
    .catch(error => { 
        console.error('Error:', error); 
        showNotification(
            'Error al ' + (editMode.isEditing ? 'actualizar' : 'crear') + ' el objetivo. Por favor, intente nuevamente.', 
            'error'
        ); 
        submitBtn.disabled = false; 
        submitBtn.innerHTML = originalText; 
    });
}

function validateForm(formData) { 
    const nombre = formData.get('nombre'); 
    const descripcion = formData.get('descripcion'); 
    const fecha_inicio = formData.get('fecha_inicio');
    const fecha_cumplimiento = formData.get('fecha_cumplimiento');
    const id_departamento = formData.get('id_departamento'); 
    
    // Revisar campos requeridos
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
    
    if (!fecha_inicio) {
        showNotification('La fecha de inicio es requerida', 'warning');
        document.getElementById('fecha_inicio').focus();
        return false;
    }
    
    if (!fecha_cumplimiento) {
        showNotification('La fecha de cumplimiento es requerida', 'warning'); 
        document.getElementById('fecha_cumplimiento').focus();
        return false;
    }
    
    // Validar que fecha de cumplimiento sea posterior a fecha de inicio
    if (fecha_inicio && fecha_cumplimiento) {
        if (new Date(fecha_cumplimiento) < new Date(fecha_inicio)) {
            showNotification('La fecha de cumplimiento debe ser posterior o igual a la fecha de inicio', 'warning');
            document.getElementById('fecha_cumplimiento').focus();
            return false;
        }
    }
    
    if (!id_departamento || id_departamento === '') { 
        showNotification('Debe seleccionar un departamento', 'warning');
        return false; 
    }
    
    // Validar el tamaño del archivo que se sube
    const fileInput = document.querySelector('.file-upload-default'); 
    if (fileInput && fileInput.files.length > 0) { 
        const file = fileInput.files[0]; 
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (file.size > maxSize) { 
            showNotification('El archivo no puede exceder 10MB', 'warning');
            return false; 
        }
        
        // Validar extensión de archivo
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
        const fileName = file.name.toLowerCase();
        const fileExtension = fileName.split('.').pop();
        
        if (!allowedExtensions.includes(fileExtension)) {
            showNotification('Tipo de archivo no permitido. Solo se permiten: ' + allowedExtensions.join(', '), 'warning');
            return false;
        }
    } 
    
    return true; 
}

function getUserId() { 
    // Esta función está definida en el HTML con el valor de la sesión PHP
    if (typeof window.getUserId === 'function') {
        return window.getUserId();
    }
    
    // Fallback: intentar obtener desde input hidden si existe
    const hiddenInput = document.querySelector('input[name="id_creador"]');
    if (hiddenInput && hiddenInput.value) {
        return hiddenInput.value;
    }
    
    console.error('No se pudo obtener el ID del usuario');
    return null;
}

function showNotification(message, type = 'info') { 
    // Revisar si existe el container para la notificación
    let container = document.getElementById('notificationContainer');
    
    if (!container) { 
        // Creación de container para notificaciones 
        container = document.createElement('div'); 
        container.id = 'notificationContainer'; 
        container.style.cssText = `
            position: fixed; 
            top: 80px; 
            right: 20px; 
            z-index: 9999; 
            min-width: 300px;
            max-width: 500px;
        `; 
        document.body.appendChild(container); 
    }
    
    // Crear elemento de notificaciones
    const notification = document.createElement('div');
    notification.className = `alert alert-${getAlertClass(type)} alert-dismissible fade show`; 
    notification.setAttribute('role', 'alert'); 
    notification.innerHTML = `
        <strong>${getAlertTitle(type)}</strong> 
        ${message} 
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `; 
    
    container.appendChild(notification);
    
    // Auto remover después de 5 segundos
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