/*user_create_project.js para crear y actualizar proyectos como usuario normal*/ 

const editMode = { 
    isEditing: false, 
    projectId: null 
}; 

document.addEventListener('DOMContentLoaded', function() { 

    //saber si estamos en modo edición 
    const params = new URLSearchParams(window.location.search); 
    editMode.projectId = params.get('edit'); 
    editMode.isEditing = !!editMode.projectId; 

    // Cambiar título y botón si estamos editando 
    if (editMode.isEditing) { 
        const titleElement = document.querySelector('h4.card-title'); 
        const subtitleElement = document.querySelector('p.card-subtitle'); 
        const btnCrear = document.getElementById('btnCrear'); 

        if (titleElement) titleElement.textContent = 'Editar Proyecto'; 
        if (subtitleElement) subtitleElement.textContent = 'Actualiza la información de tu proyecto'; 
        if (btnCrear) btnCrear.textContent = 'Actualizar'; 
    }

    cargarDepartamentoUsuario(); 
    setupFormHandlers(); 
    setupCharacterCounters(); 

    // Si es edición, cargar datos del proyecto 
    if (editMode.isEditing) { 
        cargarProyectoParaEditar(editMode.projectId); 
    } 
}); 

function cargarDepartamentoUsuario() { 
    fetch('../php/get_user_department.php') 
    .then(response => { 
        if (!response.ok) { 
            throw new Error('Network response was not ok'); 
        } 
        return response.json(); 
    }) 

    .then(data => { 
        if (data.success && data.department) { 
            const dept = data.department; 

            // Actualizar el campo visible 
            const deptDisplay = document.getElementById('departamento_display'); 
            if (deptDisplay) { 
                deptDisplay.value = dept.nombre; 
            } 

            // Actualizar el campo oculto 
            const deptHidden = document.getElementById('id_departamento'); 
            if (deptHidden) { 
                deptHidden.value = dept.id_departamento; 
            } 
            console.log('Departamento cargado:', dept.nombre, '(ID:', dept.id_departamento + ')'); 

        } else { 
            const errorMsg = data.message || 'No se pudo cargar el departamento'; 
            showAlert('Error: ' + errorMsg, 'warning'); 
            const deptDisplay = document.getElementById('departamento_display'); 
            if (deptDisplay) { 
                deptDisplay.value = 'No asignado'; 
            } 

            // Log debug info if available 

            if (data.debug) { 
                console.error('Debug info:', data.debug); 
            } 
        } 
    }) 

    .catch(error => { 
        console.error('Error al cargar departamento:', error); 
        showAlert('Error al cargar tu departamento. Por favor, recarga la página.', 'danger'); 
        const deptDisplay = document.getElementById('departamento_display'); 
        if (deptDisplay) { 
            deptDisplay.value = 'Error al cargar'; 
        } 
    }); 
} 

function setupFormHandlers() { 
    // Manejador para el botón de subir archivo 
    const btnSubirArchivo = document.getElementById('btnSubirArchivo'); 
    if (btnSubirArchivo) { 
        btnSubirArchivo.addEventListener('click', function() { 
            document.getElementById('archivoInput').click(); 
        }); 
    } 

    // Manejador para cambio de archivo 
    const archivoInput = document.getElementById('archivoInput'); 
    if (archivoInput) { 
        archivoInput.addEventListener('change', function(e) { 
            if (e.target.files.length > 0) { 
                document.getElementById('nombreArchivo').value = e.target.files[0].name; 
            } 
        }); 
    } 

    // Manejador para el botón cancelar 
    const btnCancelar = document.getElementById('btnCancelar'); 
    if (btnCancelar) { 
        btnCancelar.addEventListener('click', function() { 
            showConfirm( 
                '¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.', 
                function() { 
                    window.location.href = '../revisarProyectosUser/'; 
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

    // Manejador para el envío del formulario 

    const proyectoForm = document.getElementById('proyectoForm'); 
    if (proyectoForm) { 
        proyectoForm.addEventListener('submit', function(e) { 
            e.preventDefault(); 
            if (editMode.isEditing) { 
                editarProyecto(); 
            } else { 
                crearProyecto(); 
            } 
        }); 
    } 
} 

function crearProyecto() { 
    const form = document.getElementById('proyectoForm'); 
    const formData = new FormData(form); 
    const archivoInput = document.getElementById('archivoInput'); 
 
    // Validar formulario 
    if (!form.checkValidity()) { 
        showAlert('Por favor, completa todos los campos requeridos', 'danger'); 
        form.classList.add('was-validated'); 
        return; 
    } 

    // Validar que el departamento esté cargado 
    const idDepartamento = document.getElementById('id_departamento').value; 
    if (!idDepartamento || idDepartamento === '' || idDepartamento === '0') { 
        showAlert('Error: No se ha cargado tu departamento. Por favor, recarga la página e intenta nuevamente.', 'danger'); 
        return; 
    } 

    const btnCrear = document.getElementById('btnCrear'); 
    btnCrear.disabled = true; 
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...'; 

    // Si hay archivo, subirlo primero 
    if (archivoInput.files.length > 0) { 
        uploadFile(archivoInput.files[0], function(filePath) { 
            if (filePath) { 
                formData.set('archivo_adjunto', filePath); 
                submitForm(formData, btnCrear, 'create'); 
            } else { 
                btnCrear.disabled = false; 
                btnCrear.innerHTML = 'Crear'; 
            } 
        }); 
    } else { 
        formData.set('archivo_adjunto', ''); 
        submitForm(formData, btnCrear, 'create'); 
    } 
} 

function editarProyecto() { 
    const form = document.getElementById('proyectoForm'); 
    const formData = new FormData(form); 
    const archivoInput = document.getElementById('archivoInput'); 

    // Validar formulario 

    if (!form.checkValidity()) { 
        showAlert('Por favor, completa todos los campos requeridos', 'danger'); 
        form.classList.add('was-validated'); 
        return; 
    } 

    const btnCrear = document.getElementById('btnCrear'); 
    btnCrear.disabled = true; 
    btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Actualizando...'; 

    // Si hay archivo nuevo, subirlo 
    if (archivoInput.files.length > 0) { 
        uploadFile(archivoInput.files[0], function(filePath) { 
            if (filePath) { 
                formData.set('archivo_adjunto', filePath); 
                submitForm(formData, btnCrear, 'edit'); 
            } else { 
                btnCrear.disabled = false; 
                btnCrear.innerHTML = 'Actualizar'; 
            } 
        }); 
    } else { 
        // Si no hay archivo nuevo, mantener el existente 
        const nombreArchivoField = document.getElementById('nombreArchivo').value; 
        if (nombreArchivoField) { 
            formData.set('archivo_adjunto', nombreArchivoField); 
        } 
        submitForm(formData, btnCrear, 'edit'); 
    } 
} 

function uploadFile(file, callback) { 
    const fileFormData = new FormData(); 
    fileFormData.append('archivo', file); 

    fetch('../php/upload_file.php', { 
        method: 'POST', 
        body: fileFormData 
    }) 

    .then(response => {
        if (!response.ok) { 
            throw new Error('Network response was not ok'); 
        } 
        return response.json(); 
    }) 

    .then(data => { 
        if (data.success) { 
            callback(data.filePath); 

        } else { 
            showAlert('Error al subir el archivo: ' + data.message, 'danger'); 
            callback(null); 
        } 
    }) 

    .catch(error => { 
        console.error('Error uploading file:', error); 
        showAlert('Error al subir el archivo: ' + error.message, 'danger'); 
        callback(null); 
    }); 
} 

function submitForm(formData, btnCrear, action) { 
    const endpoint = action === 'edit' ? '../php/user_update_project.php' : '../php/user_create_project.php'; 

    if (editMode.isEditing) { 
        // Agregar ID del proyecto si es edición 
        formData.append('id_proyecto', editMode.projectId); 
    } 

    fetch(endpoint, { 
        method: 'POST', 
        body: formData 
    }) 

    .then(response => { 
        if (!response.ok) { 
            throw new Error('Network response was not ok'); 
        } 
        return response.json(); 
    }) 

    .then(data => { 
        if (data.success) { 
            const successMessage = action === 'edit'  
                ? '¡Proyecto actualizado exitosamente!'  
                : '¡Proyecto creado exitosamente!'; 
            showAlert(successMessage, 'success'); 

            // Redirigir a lista de proyectos después de 1.5 segundos 
            setTimeout(function() { 
                window.location.href = '../revisarProyectosUser/'; 
            }, 1500); 
        } else { 
            showAlert('Error: ' + data.message, 'danger'); 
            btnCrear.disabled = false; 
            btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear'; 
        } 
    }) 

    .catch(error => { 
        console.error('Error:', error); 
        const errorMsg = action === 'edit'  
            ? 'Error al actualizar el proyecto: '  
            : 'Error al crear el proyecto: '; 

        showAlert(errorMsg + error.message, 'danger'); 
        btnCrear.disabled = false; 
        btnCrear.innerHTML = action === 'edit' ? 'Actualizar' : 'Crear'; 
    }); 
} 

function cargarProyectoParaEditar(projectId) { 
    fetch(`../php/get_project_by_id.php?id=${projectId}`) 

    .then(response => { 
        if (!response.ok) { 
            throw new Error('La respuesta de red no fue ok'); 
        } 
        return response.json(); 
    }) 

    .then(data => { 
        if (data.success && data.proyecto) { 
            const proyecto = data.proyecto; 

            // Llenar campos del formulario 
            document.getElementById('nombre').value = proyecto.nombre || ''; 
            document.getElementById('descripcion').value = proyecto.descripcion || ''; 

            // Convertir datetime de SQL a formato local para input datetime-local 
            if (proyecto.fecha_inicio) { 
                // Convertir "2024-11-13 14:30:00" a "2024-11-13T14:30" 
                const fechaInicio = proyecto.fecha_inicio.replace(' ', 'T').substring(0, 16); 
                document.getElementById('fecha_creacion').value = fechaInicio; 
            } 

            // Para input de fecha extraer solo la parte de fecha 
            if (proyecto.fecha_cumplimiento) { 
                // Extraer "2024-11-13" de "2024-11-13 14:30:00" o "2024-11-13" 
                const fechaCumplimiento = proyecto.fecha_cumplimiento.split(' ')[0]; 
                document.getElementById('fecha_cumplimiento').value = fechaCumplimiento; 
            } 

            document.getElementById('progreso').value = proyecto.progreso || 0; 
            document.getElementById('ar').value = proyecto.ar || ''; 
            document.getElementById('estado').value = proyecto.estado || 'pendiente'; 

            // Si existe archivo adjunto, mostrarlo 
            if (proyecto.archivo_adjunto) { 
                document.getElementById('nombreArchivo').value = proyecto.archivo_adjunto.split('/').pop(); 
            } 
            showAlert('Proyecto cargado correctamente', 'success'); 
        } else { 
            showAlert('Error al cargar el proyecto: ' + data.message, 'danger'); 
            setTimeout(function() { 
                window.location.href = '../revisarProyectosUser/'; 
            }, 2000); 
        } 
    }) 

    .catch(error => { 
        console.error('Error al cargar proyecto:', error); 
        showAlert('Error al cargar el proyecto: ' + error.message, 'danger'); 
        setTimeout(function() { 
            window.location.href = '../revisarProyectosUser/'; 
        }, 2000); 
    }); 
} 

function showAlert(message, type) { 
    const alertContainer = document.getElementById('alertContainer'); 
    const alertDiv = document.createElement('div'); 
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`; 
    alertDiv.setAttribute('role', 'alert'); 
    alertDiv.innerHTML = ` 
        ${message} 
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> 
    `; 

    alertContainer.innerHTML = ''; // Limpiar alertas anteriores 
    alertContainer.appendChild(alertDiv); 

    // Auto eliminar después de 5 segundos 
    setTimeout(function() { 
        if (alertDiv.parentNode) { 
            alertDiv.remove(); 
        } 
    }, 5000); 
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