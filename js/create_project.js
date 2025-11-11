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