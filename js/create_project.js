/**create_proyect.js //javascript para creacion de proyectos*/
//inicializar pagina al cargar
document.addEventListener('DOMContentLoaded', function() {
  cargarDepartamentos();
  //cargarEmpleados();
  loadUsuarios();
  setupFormHandlers();
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
                    console.log(`${data.usuarios.length}`);
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

        // Limpiar opciones existentes excepto la primera
        select.innerHTML = '<option value="0">Sin usuario asignado</option>';

        // Agregar opciones
        usuarios.forEach(usuario => {
            const option = document.createElement('option');
            option.value = usuario.id_usuario;
            option.textContent = usuario.nombre_completo + ' (ID: ' + usuario.num_empleado + ')';
            select.appendChild(option);
        });
    }

/*
function cargarEmpleados() {
  fetch('../php/get_users.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('La respuesta de la red no fue ok.');
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
        showAlert('Error al cargar empleados', 'warning');//alerta en amarillo
      }
    })
    .catch(error => {
      console.error('Error al cargar los empleados: ', error);
      showAlert('Error al cargar empleados.', 'danger');//alerta en rojo
    });
}*/

function setupFormHandlers() {
    document.getElementById('btnSubirArchivo').addEventListener('click', function() {
    document.getElementById('archivoInput').click();
  });

  document.getElementById('archivoInput').addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
      document.getElementById('nombreArchivo').value = e.target.files[0].name;
    }
  });

  document.getElementById('btnCancelar').addEventListener('click', function() {
    if (confirm('¿Deseas cancelar la creación del proyecto?')) {
      window.location.href = '../revisarProyectos/';
    }
  });

  document.getElementById('proyectoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    crearProyecto();
  });
}



function crearProyecto() {
  const form = document.getElementById('proyectoForm');
  const formData = new FormData(form);
  const archivoInput = document.getElementById('archivoInput');

  if (!form.checkValidity()) {
    showAlert('Por favor, completa todos los campos requeridos', 'danger');
    form.classList.add('was-validated');
    return;
  }

  const btnCrear = document.getElementById('btnCrear');
  btnCrear.disabled = true;
  btnCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creando...';

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



function submitForm(formData, btnCrear) {
  fetch('../php/create_project.php', {
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
      //redirigir despues de 1.5s
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

function showAlert(message, type) {
  const alertContainer = document.getElementById('alertContainer');
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
  alertDiv.setAttribute('role', 'alert');
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;
  
  alertContainer.innerHTML = '';//limpiar alertas anteriores
  alertContainer.appendChild(alertDiv);

  //auto eliminar despues de 5s
  setTimeout(function() {
    if (alertDiv.parentNode) {
      alertDiv.remove();
    }
  }, 5000);
}