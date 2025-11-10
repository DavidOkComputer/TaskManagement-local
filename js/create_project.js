/**
 * proyecto-form-handler.js
 * Handles all form operations for project creation including validation, file upload, and submission
 */

// Initialize form on page load
document.addEventListener('DOMContentLoaded', function() {
  cargarDepartamentos();
  cargarEmpleados();
  setupFormHandlers();
});

/**
 * Load departments from database and populate select dropdown
 */
function cargarDepartamentos() {
  fetch('../php/obtener_departamentos.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
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
      console.error('Error loading departments:', error);
      showAlert('Error al cargar departamentos', 'danger');
    });
}

/**
 * Load employees from database and populate select dropdown
 */
function cargarEmpleados() {
  fetch('../php/obtener_empleados.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success && data.empleados) {
        const select = document.getElementById('id_participante');
        data.empleados.forEach(emp => {
          const option = document.createElement('option');
          option.value = emp.id_usuario;
          option.textContent = emp.nombre + ' ' + emp.apellido;
          select.appendChild(option);
        });
      } else {
        showAlert('Error al cargar empleados', 'warning');
      }
    })
    .catch(error => {
      console.error('Error loading employees:', error);
      showAlert('Error al cargar empleados', 'danger');
    });
}

/**
 * Setup event listeners for form buttons and inputs
 */
function setupFormHandlers() {
  // File upload button click
  document.getElementById('btnSubirArchivo').addEventListener('click', function() {
    document.getElementById('archivoInput').click();
  });

  // File input change - display selected filename
  document.getElementById('archivoInput').addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
      document.getElementById('nombreArchivo').value = e.target.files[0].name;
    }
  });

  // Cancel button - redirect to projects list
  document.getElementById('btnCancelar').addEventListener('click', function() {
    if (confirm('¿Deseas cancelar la creación del proyecto?')) {
      window.location.href = '../revisarProyectos/';
    }
  });

  // Form submission
  document.getElementById('proyectoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    crearProyecto();
  });
}

/**
 * Main function to create project - handles file upload and form submission
 */
function crearProyecto() {
  const form = document.getElementById('proyectoForm');
  const formData = new FormData(form);
  const archivoInput = document.getElementById('archivoInput');

  // Validate that all required fields are filled
  if (!form.checkValidity()) {
    showAlert('Por favor, completa todos los campos requeridos', 'danger');
    form.classList.add('was-validated');
    return;
  }

  // Disable submit button to prevent double submission
  const btnCrear = document.getElementById('btnCrear');
  btnCrear.disabled = true;
  btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';

  // If file is selected, upload it first
  if (archivoInput.files.length > 0) {
    uploadFile(archivoInput.files[0], function(filePath) {
      if (filePath) {
        formData.set('archivo_adjunto', filePath);
        submitForm(formData, btnCrear);
      } else {
        btnCrear.disabled = false;
        btnCrear.innerHTML = 'Crear';
      }
    });
  } else {
    formData.set('archivo_adjunto', '');
    submitForm(formData, btnCrear);
  }
}

/**
 * Upload file to server
 * @param {File} file - The file to upload
 * @param {Function} callback - Callback function with file path parameter
 */
function uploadFile(file, callback) {
  const fileFormData = new FormData();
  fileFormData.append('archivo', file);

  fetch('../php/subir_archivo.php', {
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

/**
 * Submit form data to backend
 * @param {FormData} formData - The form data to submit
 * @param {HTMLElement} btnCrear - The submit button element
 */
function submitForm(formData, btnCrear) {
  fetch('../php/crear_proyecto.php', {
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
      showAlert('¡Proyecto creado exitosamente!', 'success');
      // Redirect after 1.5 seconds
      setTimeout(function() {
        window.location.href = '../revisarProyectos/';
      }, 1500);
    } else {
      showAlert('Error: ' + data.message, 'danger');
      btnCrear.disabled = false;
      btnCrear.innerHTML = 'Crear';
    }
  })
  .catch(error => {
    console.error('Error creating project:', error);
    showAlert('Error al crear el proyecto: ' + error.message, 'danger');
    btnCrear.disabled = false;
    btnCrear.innerHTML = 'Crear';
  });
}

/**
 * Display alert messages in the alert container
 * @param {string} message - The message to display
 * @param {string} type - Bootstrap alert type (success, danger, warning, info)
 */
function showAlert(message, type) {
  const alertContainer = document.getElementById('alertContainer');
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;
  
  // Clear previous alerts
  alertContainer.innerHTML = '';
  alertContainer.appendChild(alertDiv);

  // Auto-dismiss after 5 seconds
  setTimeout(function() {
    if (alertDiv.parentNode) {
      alertDiv.remove();
    }
  }, 5000);
}