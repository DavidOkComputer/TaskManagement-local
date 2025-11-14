/**create_proyect.js //javascript para creacion y edicion de proyectos*/
 
const editMode = {
    isEditing: false,
    projectId: null
};
 
// Estado para proyecto grupal
const grupalState = {
    selectedUsers: [],
    usuariosModal: null
};
 
//inicializar pagina al cargar
document.addEventListener('DOMContentLoaded', function() {
  // Detectar si estamos en modo edición
  const params = new URLSearchParams(window.location.search);
  editMode.projectId = params.get('edit');
  editMode.isEditing = !!editMode.projectId;
 
  // Cambiar título y botón si estamos editando
  if (editMode.isEditing) {
    document.querySelector('h4.card-title')?.textContent == 'Editar Proyecto';
    document.querySelector('p.card-subtitle')?.textContent == 'Actualiza la información del proyecto';
    document.getElementById('btnCrear').textContent == 'Actualizar';
  }
 
  cargarDepartamentos();
  loadUsuarios();
  setupFormHandlers();
  setupGrupalHandlers();
 
  // Si es edición, cargar datos del proyecto
  if (editMode.isEditing) {
    cargarProyectoParaEditar(editMode.projectId);
  }
});
 
const app = {
    usuarios: []
};
 
function cargarDepartamentos() {
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
 
function loadUsuarios() {
  fetch('../php/get_users.php')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.usuarios) {
        app.usuarios = data.usuarios;
        populateUsuariosSelect(data.usuarios);
        populateGrupalModal(data.usuarios);
        console.log(`${data.usuarios.length} usuarios cargados`);
      } else {
        console.error('Error al cargar usuarios:', data.message);
      }
    })
    .catch(error => {
      console.error('Error en fetch de usuarios:', error);
    });
}
 
function populateUsuariosSelect(usuarios) {
  const select = document.getElementById('id_participante');
  if (!select) return;
 
  select.innerHTML = '<option value="0">Sin usuario asignado</option>';
 
  usuarios.forEach(usuario => {
    const option = document.createElement('option');
    option.value = usuario.id_usuario;
    option.textContent = usuario.nombre_completo + ' (ID: ' + usuario.num_empleado + ')';
    select.appendChild(option);
  });
}
 
function populateGrupalModal(usuarios) {
  const container = document.getElementById('usuariosListContainer');
  if (!container) return;
 
  container.innerHTML = '';
 
  usuarios.forEach(usuario => {
    const userCheckbox = document.createElement('div');
    userCheckbox.className = 'form-check mb-3 p-2 border-bottom';
    userCheckbox.innerHTML = `
      <input class="form-check-input usuario-checkbox" type="checkbox" value="${usuario.id_usuario}" id="check_${usuario.id_usuario}">
      <label class="form-check-label w-100" for="check_${usuario.id_usuario}">
        <strong>${usuario.nombre_completo}</strong>
        <br>
        <small class="text-muted">Empleado #${usuario.num_empleado} - ${usuario.e_mail}</small>
      </label>
    `;
    container.appendChild(userCheckbox);
  });
 
  document.querySelectorAll('.usuario-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
  });
}
 
function updateSelectedCount() {
  const checkedCount = document.querySelectorAll('.usuario-checkbox:checked').length;
  document.getElementById('countSelected').textContent = checkedCount;
}
 
//create_project.js para crear un proyecto

function setupGrupalHandlers() {
  const tipoProyectoRadios = document.querySelectorAll('input[name="id_tipo_proyecto"]');
  const participanteField = document.getElementById('id_participante');
  const btnSeleccionarGrupo = document.getElementById('btnSeleccionarGrupo');

  // Handler para el botón "Grupo"
  if (btnSeleccionarGrupo && !btnSeleccionarGrupo.hasListener) {
    btnSeleccionarGrupo.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Cambiar a proyecto grupal
      document.querySelector('input[name="id_tipo_proyecto"][value="1"]').checked = true;
      
      // Mostrar modal
      if (!grupalState.usuariosModal) {
        grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
      }
      grupalState.usuariosModal.show();
      
      // Desactivar campo de participante individual
      participanteField.disabled = true;
      participanteField.value = '';
      
      showAlert('Cambiado a proyecto grupal. Selecciona los integrantes del equipo.', 'info');
    });
    btnSeleccionarGrupo.hasListener = true;
  }

  tipoProyectoRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      if (this.value == '1') { // Grupal (value 1)
        // Mostrar modal de selección grupal
        if (!grupalState.usuariosModal) {
          grupalState.usuariosModal = new bootstrap.Modal(document.getElementById('grupalUsuariosModal'));
        }
        grupalState.usuariosModal.show();
        participanteField.disabled = true;
        participanteField.value = '';
      } else { // Individual (value 2)
        grupalState.selectedUsers = [];
        document.querySelectorAll('.usuario-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCount();
        participanteField.disabled = false;
      }
    });
  });

  const btnConfirmar = document.getElementById('btnConfirmarGrupal');
  if (btnConfirmar && !btnConfirmar.hasListener) {
    btnConfirmar.addEventListener('click', function() {
      const selectedCheckboxes = document.querySelectorAll('.usuario-checkbox:checked');
      if (selectedCheckboxes.length === 0) {
        showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'warning');
        return;
      }
      grupalState.selectedUsers = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));
      grupalState.usuariosModal.hide();
      showAlert(`${grupalState.selectedUsers.length} usuario(s) seleccionado(s) para el proyecto grupal`, 'success');
    });
    btnConfirmar.hasListener = true;
  }

  const searchInput = document.getElementById('searchUsuarios');
  if (searchInput && !searchInput.hasListener) {
    searchInput.addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase();
      const checkboxes = document.querySelectorAll('.usuario-checkbox');
      
      checkboxes.forEach(checkbox => {
        const label = checkbox.closest('.form-check').querySelector('label').textContent.toLowerCase();
        if (label.includes(searchTerm)) {
          checkbox.closest('.form-check').style.display = 'block';
        } else {
          checkbox.closest('.form-check').style.display = 'none';
        }
      });
    });
    searchInput.hasListener = true;
  }
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
                
                document.getElementById('nombre').value = proyecto.nombre || '';
                document.getElementById('descripcion').value = proyecto.descripcion || '';
                document.getElementById('id_departamento').value = proyecto.id_departamento || '';
                
                //convertir datetime de sql a formato local
                if (proyecto.fecha_inicio) {
                    //Convertir "2024-11-13 14:30:00" a "2024-11-13T14:30"
                    const fechaInicio = proyecto.fecha_inicio.replace(' ', 'T').substring(0, 16);
                    document.getElementById('fecha_creacion').value = fechaInicio;
                }
                
                // For date input, extract just the date part
                if (proyecto.fecha_cumplimiento) {
                    // Extract "2024-11-13" from "2024-11-13 14:30:00" or "2024-11-13"
                    const fechaCumplimiento = proyecto.fecha_cumplimiento.split(' ')[0];
                    document.getElementById('fecha_cumplimiento').value = fechaCumplimiento;
                }
                
                document.getElementById('progreso').value = proyecto.progreso || 0;
                document.getElementById('ar').value = proyecto.ar || '';
                document.getElementById('estado').value = proyecto.estado || 'pendiente';
                document.getElementById('id_participante').value = proyecto.id_participante || 0;
 
                //tipo de proyecto
                const tipoValue = proyecto.id_tipo_proyecto == 1 ? '1' : '2';
                document.querySelector(`input[name="id_tipo_proyecto"][value="${tipoValue}"]`).checked = true;
 
                // Si es grupal, cargar los usuarios asignados
                if (tipoValue == '1' && proyecto.usuarios_asignados) {
                    grupalState.selectedUsers = proyecto.usuarios_asignados.map(u => u.id_usuario);
                    
                    //checar checkboxes cuando carga modal
                    grupalState.selectedUsers.forEach(userId => {
                        const checkbox = document.querySelector(`#check_${userId}`);
                        if (checkbox) checkbox.checked = true;
                    });
                    updateSelectedCount();
                }
 
                if (proyecto.archivo_adjunto) {//si existe el archivo adjunto mostrarlo
                    document.getElementById('nombreArchivo').value = proyecto.archivo_adjunto.split('/').pop();
                }
                
                showAlert('Proyecto cargado correctamente', 'success');
            } else {
                showAlert('Error al cargar el proyecto: ' + data.message, 'danger');
                window.location.href = '../revisarProyectos/';
            }
        })
        .catch(error => {
            console.error('Error al cargar proyecto:', error);
            showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
            window.location.href = '../revisarProyectos/';
        });
}
 
function setupFormHandlers() {
  document.getElementById('btnSubirArchivo').addEventListener('click', function() {
    document.getElementById('archivoInput').click();
  });
 
  document.getElementById('archivoInput').addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
      document.getElementById('nombreArchivo').value = e.target.files[0].name;
    }
  });
 
  //usar dialogo de la app en vez de navegador
  document.getElementById('btnCancelar').addEventListener('click', function() {
    showConfirm(
      '¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.',
      function() {
        window.location.href = '../revisarProyectos/';
      },
      'Cancelar cambios',
      {
        type: 'warning',
        confirmText: 'Sí, cancelar',
        cancelText: 'Volver al formulario'
      }
    );
  });
 
  document.getElementById('proyectoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (editMode.isEditing) {
      editarProyecto();
    } else {
      crearProyecto();
    }
  });
}
 
function crearProyecto() {
  const form = document.getElementById('proyectoForm');
  const formData = new FormData(form);
  const archivoInput = document.getElementById('archivoInput');
  const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;
 
  if (!form.checkValidity()) {
    showAlert('Por favor, completa todos los campos requeridos', 'danger');
    form.classList.add('was-validated');
    return;
  }
  //revisar que se seleccionen usuarios para el poryecto grupal
  if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
    showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
    return;
  }
 
  const btnCrear = document.getElementById('btnCrear');
  btnCrear.disabled = true;
  btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';
 
  if (archivoInput.files.length > 0) {
    uploadFile(archivoInput.files[0], function(filePath) {
      if (filePath) {
        formData.set('archivo_adjunto', filePath);
        // Agregar usuarios seleccionados para proyecto grupal
        if (tipoProyecto == '1') {
          formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
        }
        submitForm(formData, btnCrear, 'create');
      } else {
        btnCrear.disabled = false;
        btnCrear.innerHTML = 'Crear';
      }
    });
  } else {
    formData.set('archivo_adjunto', '');
    // Agregar usuarios seleccionados para proyecto grupal
    if (tipoProyecto == '1') {
      formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
    }
    submitForm(formData, btnCrear, 'create');
  }
}
 
function editarProyecto() {
  const form = document.getElementById('proyectoForm');
  const formData = new FormData(form);
  const archivoInput = document.getElementById('archivoInput');
  const tipoProyecto = document.querySelector('input[name="id_tipo_proyecto"]:checked').value;
 
  if (!form.checkValidity()) {
    showAlert('Por favor, completa todos los campos requeridos', 'danger');
    form.classList.add('was-validated');
    return;
  }
 
  if (tipoProyecto == '1' && grupalState.selectedUsers.length === 0) {
    showAlert('Debes seleccionar al menos un usuario para el proyecto grupal', 'danger');
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
        // Agregar usuarios seleccionados para proyecto grupal
        if (tipoProyecto == '1') {
          formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
        }
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
    // Agregar usuarios seleccionados para proyecto grupal
    if (tipoProyecto == '1') {
      formData.set('usuarios_grupo', JSON.stringify(grupalState.selectedUsers));
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
  const endpoint = action === 'edit'
    ? '../php/update_project.php'
    : '../php/create_project.php';
 
  if (editMode.isEditing) {  // Agregar ID del proyecto si es edición
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
     
      setTimeout(function() {//redirigir a lista de proyectos
        window.location.href = '../revisarProyectos/';
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
 
function showAlert(message, type) {
  const alertContainer = document.getElementById('alertContainer');
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;
 
  alertContainer.innerHTML = ''; //limpiar alertas anteriores
  alertContainer.appendChild(alertDiv);
 
  //auto eliminar despues de 5s
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

document.addEventListener('DOMContentLoaded', setupCharacterCounters);