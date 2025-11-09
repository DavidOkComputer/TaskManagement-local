
//configurar el objeto 
const DepartamentoConfig = { 
    API_ENDPOINT: '../php/create_department.php', 
    MAX_RETRIES: 3, 
    RETRY_DELAY: 2000, 
    REDIRECT_DELAY: 3000, 
    AUTO_SAVE_INTERVAL: 30000, // 30 seconds 
    DEBOUNCE_DELAY: 500 
}; 

//manejo de estado
const DepartamentoState = { 
    isSubmitting: false, 
    retryCount: 0, 
    formData: null, 
    autoSaveTimer: null 
}; 

//inicializar form 
document.addEventListener('DOMContentLoaded', function() { 
    initAdvancedDepartmentForm(); 
}); 

function initAdvancedDepartmentForm() { 
    const form = document.getElementById('formCrearDepartamento'); 
    if (!form) return;  
    setupFormValidation(); 
    setupFormSubmission(form); 
    setupAutoSave(); 
    setupNetworkDetection(); 
    setupFormRecovery(); 
    setupConfirmationDialogs(); 
    setupKeyboardShortcuts(); 
    console.log('Advanced Department Form initialized'); 
} 

function setupFormValidation() { 
    const nombreInput = document.getElementById('nombre'); 
    const descripcionInput = document.getElementById('descripcion'); 
    if (nombreInput) { 
        addDebouncedValidation(nombreInput, validateNombre); 
    } 
    if (descripcionInput) { 
        addDebouncedValidation(descripcionInput, validateDescripcion); 
    } 
} 

function addDebouncedValidation(field, validationFunc) { 
    let timeout; 
    field.addEventListener('input', function() { 
        clearTimeout(timeout); 
        timeout = setTimeout(() => { 
            validationFunc(field); 
        }, DepartamentoConfig.DEBOUNCE_DELAY); 
    }); 

    field.addEventListener('blur', function() { 
        validationFunc(field); 
    }); 
} 
function validateNombre(field) { 
    const value = field.value.trim();
    if (value === '') { 
        setFieldError(field, 'El nombre es requerido'); 
        return false; 
    } 

    if (value.length < 3) { 
        setFieldError(field, 'El nombre debe tener al menos 3 caracteres'); 
        return false; 
    } 

    if (value.length > 200) { 
        setFieldError(field, 'El nombre no puede exceder 200 caracteres'); 
        return false; 
    } 

    if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/.test(value)) { 
        setFieldError(field, 'El nombre solo puede contener letras, espacios y guiones'); 
        return false; 
    } 
    setFieldSuccess(field); 
    return true; 
} 
function validateDescripcion(field) { 
    const value = field.value.trim(); 
    if (value === '') { 
        setFieldError(field, 'La descripción es requerida'); 
        return false; 
    } 
    if (value.length < 10) { 
        setFieldError(field, 'La descripción debe tener al menos 10 caracteres'); 
        return false; 
    } 
    if (value.length > 200) { 
        setFieldError(field, 'La descripción no puede exceder 200 caracteres'); 
        return false; 
    } 
    setFieldSuccess(field); 
    return true; 
} 

function setFieldError(field, message) { 
    field.classList.add('is-invalid'); 
    field.classList.remove('is-valid'); 
    let feedback = field.parentElement.querySelector('.invalid-feedback'); 
    if (!feedback) { 
        feedback = document.createElement('div'); 
        feedback.className = 'invalid-feedback'; 
        field.parentElement.appendChild(feedback); 
    } 
    feedback.textContent = message; 
    feedback.style.display = 'block'; 
} 

function setFieldSuccess(field) { 
    field.classList.add('is-valid'); 
    field.classList.remove('is-invalid'); 
    const feedback = field.parentElement.querySelector('.invalid-feedback'); 
    if (feedback) { 
        feedback.style.display = 'none'; 
    } 
} 
function setupFormSubmission(form) { 
    form.addEventListener('submit', async function(e) { 
        e.preventDefault(); 
        if (DepartamentoState.isSubmitting) { 
            console.warn('Form is already being submitted'); 
            return; 
        } 
        if (!validateAllFields()) { 
            showNotification('error', 'Por favor corrija los errores en el formulario'); 
            return; 
        } 
        const formData = new FormData(form); 
        DepartamentoState.formData = formData; 
        await submitWithRetry(formData); 
    }); 
} 

function validateAllFields() { 
    const nombreInput = document.getElementById('nombre'); 
    const descripcionInput = document.getElementById('descripcion'); 
    const nombreValid = validateNombre(nombreInput); 
    const descripcionValid = validateDescripcion(descripcionInput); 
    return nombreValid && descripcionValid; 
} 

async function submitWithRetry(formData, retryCount = 0) { 
    DepartamentoState.isSubmitting = true; 
    DepartamentoState.retryCount = retryCount; 
    showLoadingOverlay(); 
    updateSubmitButton('loading'); 

    try { 
        const response = await fetch(DepartamentoConfig.API_ENDPOINT, { 
            method: 'POST', 
            body: formData, 
            headers: { 
                'Accept': 'application/json' 
            } 
        }); 

        if (!response.ok) { 
            throw new Error(`HTTP error! status: ${response.status}`); 
        } 

        const data = await response.json(); 
        if (data.success) { 
            handleSubmitSuccess(data); 
        } else { 
            handleSubmitError(data.message || 'Error desconocido'); 
        } 

    } catch (error) { 
        console.error('Submit error:', error); 
        if (retryCount < DepartamentoConfig.MAX_RETRIES) { 
            showNotification( 
                'warning', 
                `Error de conexión. Reintentando... (${retryCount + 1}/${DepartamentoConfig.MAX_RETRIES})` 
            ); 

            setTimeout(() => { 
                submitWithRetry(formData, retryCount + 1); 
            }, DepartamentoConfig.RETRY_DELAY); 
        } else { 
            handleSubmitError('No se pudo conectar al servidor después de varios intentos'); 
        } 

    } finally { 
        if (retryCount >= DepartamentoConfig.MAX_RETRIES || retryCount === 0) { 
            DepartamentoState.isSubmitting = false; 

            hideLoadingOverlay(); 

            updateSubmitButton('normal'); 

        } 

    } 

} 
function handleSubmitSuccess(data) { 
 
    clearAutoSavedData(); 

      
    showNotification( 

        'success', 

        `¡Éxito! Departamento "${data.nombre}" creado correctamente` 

    ); 
 
    document.getElementById('formCrearDepartamento').reset(); 

    clearAllValidationStates(); 

      
    setTimeout(() => { 

        window.location.href = '../gestionDeDepartamentos/'; 

    }, DepartamentoConfig.REDIRECT_DELAY); 

} 
 

function handleSubmitError(message) { 

    showNotification('error', message); 

} 
 

function showLoadingOverlay() { 

    let overlay = document.getElementById('loadingOverlay'); 

    if (!overlay) { 

        overlay = document.createElement('div'); 

        overlay.id = 'loadingOverlay'; 

        overlay.innerHTML = ` 

            <div class="loading-spinner"> 

                <div class="spinner-border text-primary" role="status"> 

                    <span class="visually-hidden">Cargando...</span> 

                </div> 

                <p class="mt-3">Procesando solicitud...</p> 

            </div> 

        `; 

        overlay.style.cssText = ` 

            position: fixed; 

            top: 0; 

            left: 0; 

            width: 100%; 

            height: 100%; 

            background: rgba(0, 0, 0, 0.5); 

            display: flex; 

            justify-content: center; 

            align-items: center; 

            z-index: 9999; 

        `; 

        document.body.appendChild(overlay); 

    } 

    overlay.style.display = 'flex'; 

} 

  
function hideLoadingOverlay() { 

    const overlay = document.getElementById('loadingOverlay'); 

    if (overlay) { 

        overlay.style.display = 'none'; 

    } 

}  

function updateSubmitButton(state) { 

    const button = document.getElementById('btnSubmit'); 

    if (!button) return; 

     

    const states = { 

        'loading': { 

            text: '<i class="mdi mdi-loading mdi-spin"></i> Procesando...', 

            disabled: true 

        }, 

        'normal': { 

            text: '<i class="mdi mdi-content-save"></i> Registrar Departamento', 

            disabled: false 

        }, 

        'success': { 

            text: '<i class="mdi mdi-check"></i> Registrado', 

            disabled: true 

        } 

    }; 

     

    const config = states[state] || states.normal; 

    button.innerHTML = config.text; 

    button.disabled = config.disabled; 

}  

function showNotification(type, message) { 

    const alertDiv = document.getElementById('alertMessage'); 

    if (!alertDiv) return; 

     

    const types = { 

        'success': { class: 'alert-success', icon: 'mdi-check-circle' }, 

        'error': { class: 'alert-danger', icon: 'mdi-alert-circle' }, 

        'warning': { class: 'alert-warning', icon: 'mdi-alert' }, 

        'info': { class: 'alert-info', icon: 'mdi-information' } 

    }; 

     

    const config = types[type] || types.info; 

     

    alertDiv.className = `alert ${config.class} alert-dismissible fade show`; 

    alertDiv.innerHTML = ` 

        <i class="mdi ${config.icon} me-2"></i> 

        ${message} 

        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'"></button> 

    `; 

    alertDiv.style.display = 'block'; 

      
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); 

} 
 
function setupAutoSave() { 

    const form = document.getElementById('formCrearDepartamento'); 

    if (!form) return; 

      

    form.addEventListener('input', debounce(function() { 

        saveFormData(); 

    }, 2000)); 

      

    DepartamentoState.autoSaveTimer = setInterval(() => { 

        if (hasFormData()) { 

            saveFormData(); 

        } 

    }, DepartamentoConfig.AUTO_SAVE_INTERVAL); 

} 

  
function saveFormData() { 

    const nombre = document.getElementById('nombre').value; 

    const descripcion = document.getElementById('descripcion').value; 

     

    if (nombre || descripcion) { 

        const data = { 

            nombre: nombre, 

            descripcion: descripcion, 

            timestamp: new Date().toISOString() 

        }; 

         

        localStorage.setItem('departamento_draft', JSON.stringify(data)); 

        console.log('Form data auto-saved'); 

    } 

} 
 
function hasFormData() { 

    const nombre = document.getElementById('nombre').value; 

    const descripcion = document.getElementById('descripcion').value; 

    return nombre.trim() !== '' || descripcion.trim() !== ''; 

} 

  
function clearAutoSavedData() { 

    localStorage.removeItem('departamento_draft'); 

} 

  
function setupFormRecovery() { 

    const savedData = localStorage.getItem('departamento_draft'); 

     

    if (savedData) { 

        try { 

            const data = JSON.parse(savedData); 

            const savedDate = new Date(data.timestamp); 

            const now = new Date(); 

            const hoursDiff = (now - savedDate) / (1000 * 60 * 60); 

             
  

            if (hoursDiff < 24) { 

                if (confirm('Se encontró un borrador guardado. ¿Desea recuperarlo?')) { 

                    document.getElementById('nombre').value = data.nombre || ''; 

                    document.getElementById('descripcion').value = data.descripcion || ''; 

                    showNotification('info', 'Borrador recuperado exitosamente'); 

                } else { 

                    clearAutoSavedData(); 

                } 

            } else { 

                clearAutoSavedData(); 

            } 

        } catch (e) { 

            console.error('Error recovering form data:', e); 

            clearAutoSavedData(); 

        } 

    } 

} 
 
function setupNetworkDetection() { 

    window.addEventListener('online', function() { 

        showNotification('success', 'Conexión restaurada'); 

    }); 

     

    window.addEventListener('offline', function() { 

        showNotification('warning', 'Sin conexión a internet. Los cambios se guardarán localmente.'); 

    }); 

} 
 
function setupConfirmationDialogs() { 

    let formDirty = false; 

     

    const form = document.getElementById('formCrearDepartamento'); 

    form.addEventListener('input', function() { 

        formDirty = true; 

    }); 

     

    form.addEventListener('submit', function() { 

        formDirty = false; 

    }); 

     

    window.addEventListener('beforeunload', function(e) { 

        if (formDirty && hasFormData()) { 

            const message = '¿Está seguro de que desea salir? Los cambios no guardados se perderán.'; 

            e.returnValue = message; 

            return message; 

        } 

    }); 

} 

  
function setupKeyboardShortcuts() { 

    document.addEventListener('keydown', function(e) { 

        // Ctrl/Cmd + S para subir 

        if ((e.ctrlKey || e.metaKey) && e.key === 's') { 

            e.preventDefault(); 

            document.getElementById('formCrearDepartamento').requestSubmit(); 

        } 

         

        // Ctrl/Cmd + R para reinciar

        if ((e.ctrlKey || e.metaKey) && e.key === 'r') { 

            e.preventDefault(); 

            if (confirm('¿Está seguro de que desea limpiar el formulario?')) { 

                document.getElementById('formCrearDepartamento').reset(); 

                clearAllValidationStates(); 

            } 

        } 

    }); 

} 

function clearAllValidationStates() { 

    const inputs = document.querySelectorAll('.is-valid, .is-invalid'); 

    inputs.forEach(input => { 

        input.classList.remove('is-valid', 'is-invalid'); 

    }); 

     

    const feedbacks = document.querySelectorAll('.invalid-feedback'); 

    feedbacks.forEach(feedback => { 

        feedback.style.display = 'none'; 

    }); 

} 


function debounce(func, wait) { 

    let timeout; 

    return function executedFunction(...args) { 

        const later = () => { 

            clearTimeout(timeout); 

            func(...args); 

        }; 

        clearTimeout(timeout); 

        timeout = setTimeout(later, wait); 

    }; 

} 


window.addEventListener('beforeunload', function() { 

    if (DepartamentoState.autoSaveTimer) { 

        clearInterval(DepartamentoState.autoSaveTimer); 

    } 

}); 

 

if (typeof module !== 'undefined' && module.exports) { 

    module.exports = { 

        validateNombre, 

        validateDescripcion, 

        submitWithRetry, 

        saveFormData 

    }; 

} 