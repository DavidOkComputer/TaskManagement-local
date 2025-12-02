/*dashboard_charts_core.js base de control de los graficos con auto-refresh y manejo de datos vacíos*/ 

let dashboardChartsInstance = { 
    charts: {}, 
    currentDepartment: null, 
    refreshInterval: null, 
    refreshRate: 60000, // 60 segundos  
    isRefreshing: false,
    userConfig: null,
    isRoleLocked: false, //si es verdad el usuario no puede cambiar departamentos
    departmentColors: [ 
        'rgba(34, 139, 89, 0.7)',      // Green (Primary) 
        'rgba(80, 154, 108, 0.7)',     // Green Light 
        'rgba(24, 97, 62, 0.7)',       // Green Dark 
        'rgba(200, 205, 210, 0.7)',    // Ice/Light Gray 
        'rgba(130, 140, 150, 0.7)',    // Gray 
        'rgba(50, 50, 50, 0.7)',       // Black 
        'rgba(45, 110, 80, 0.7)',      // Green Secondary 
        'rgba(160, 170, 180, 0.7)',    // Gray Light 
    ], 

    departmentBorderColors: [ 
        'rgba(34, 139, 89, 1)',        // Green (Primary) 
        'rgba(80, 154, 108, 1)',       // Green Light 
        'rgba(24, 97, 62, 1)',         // Green Dark
        'rgba(200, 205, 210, 1)',      // Ice/Light Gray 
        'rgba(130, 140, 150, 1)',      // Gray 
        'rgba(50, 50, 50, 1)',         // Black 
        'rgba(45, 110, 80, 1)',        // Green Secondary 
        'rgba(160, 170, 180, 1)',      // Gray Light 
    ] 
}; 

function shortenProjectTitle(title, maxLength = 15) { 
    if (!title) return ''; 
    if (title.length <= maxLength) return title; 
    return title.substring(0, maxLength) + '...'; 
} 

function initializeUserRoleConfig() {
    //revisar la configuracion deel usuario
    if (window.dashboardUserConfig) {
        dashboardChartsInstance.userConfig = window.dashboardUserConfig;
        dashboardChartsInstance.isRoleLocked = !window.dashboardUserConfig.canViewAllDepartments;
        
        console.log('CONFIGURACION DE USUARIO');
        console.log('UsUARIO:', dashboardChartsInstance.userConfig.userName);
        console.log('ID DE ROL:', dashboardChartsInstance.userConfig.userRol);
        console.log('ES ADMIN:', dashboardChartsInstance.userConfig.isAdmin);
        console.log('ES GERENTE:', dashboardChartsInstance.userConfig.isManager);
        console.log('PUEDE VER TODOS LOS DEPARTAMENTOS:', dashboardChartsInstance.userConfig.canViewAllDepartments);
        console.log('ROL BLOQUEADO:', dashboardChartsInstance.isRoleLocked);
        console.log('ID DEL DEPARTAMENTO DEL USUARIO:', dashboardChartsInstance.userConfig.userDepartamento);
        
        return true;
    } else {
        //revisar desde la api si falla la primer opcion
        console.log('configuracion no encontrada en ventana, obteniendo desde API...');
        return fetchUserRoleConfig();
    }
}

function fetchUserRoleConfig() {
    return fetch('../php/get_user_role_info.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('ERROR OBTENIENDO INFO DE ROL DE USUARIO');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                dashboardChartsInstance.userConfig = {
                    userId: data.data.id_usuario,
                    userName: data.data.nombre,
                    userRol: data.data.id_rol,
                    userDepartamento: data.data.id_departamento,
                    canViewAllDepartments: data.data.can_view_all_departments,
                    isAdmin: data.data.is_admin,
                    isManager: data.data.is_manager,
                    showDepartmentDropdown: data.data.show_department_dropdown
                };
                dashboardChartsInstance.isRoleLocked = !data.data.can_view_all_departments;
                
                console.log('CONFIGURACION DE ROL DE USUARIO OBTENIDO DESDE LA API', dashboardChartsInstance.userConfig);
                return true;
            } else {
                console.error('ERROR EN CONFIGURACION DE ROL DE USUARIO', data.message);
                return false;
            }
        })
        .catch(error => {
            console.error('ERROR OBTENIENDO LA CONFIGURACION DE ROL DE USUARIO', error);
            return false;
        });
}

function initializeDashboardCharts() { 
    console.log('Inicializando gráficos del dashboard...'); 
    
    initializeUserRoleConfig();
    
    //mostrar cierta vista depenediendo del rol de usuario
    if (dashboardChartsInstance.isRoleLocked) {
        //usuario o gerente solo mostrar su departamento
        console.log('USUARIO BLOQUEADO, CARGANDO SOLO INFO DEL DEPARTAMENTO');
        loadUserDepartmentViewLocked();
    } else {
        // ADMIN PUEDE CAMBIAR ENTRE DEPARTAMENTOS
        console.log('USUARIO ES ADMIN, CAMBIANDO PERMISOS DE VISTA');
        loadUserDepartmentView();
    }
    
    initializeLineChart(); 
    initializeAreaChart(); 
    initializeScatterChart(); 
    
    // Iniciar auto-refresh 
    startAutoRefresh(); 
    console.log(`Auto-refresh activado: cada ${dashboardChartsInstance.refreshRate / 1000} segundos`); 
}

function loadUserDepartmentViewLocked() {
    console.log('CARGANDO VISTA DE GERENTE/USUARIO...');
    
    //usar el id de departamento de la configuracion
    const userDeptId = dashboardChartsInstance.userConfig.userDepartamento;
    
    if (!userDeptId || userDeptId === 0) {
        console.error('No department assigned to this user!');
        showNoDepartmentData('Sin departamento asignado');
        return;
    }
    
    //obtener nombre de departamento y cargar vista
    fetch('../php/get_user_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error obteniendo departamento de usuario');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.department) {
                const userDept = data.department;
                console.log('Departamento cargado:', userDept.nombre);
                
                //no se puede cambiar el departamento
                dashboardChartsInstance.currentDepartment = {
                    id: userDept.id_departamento,
                    name: userDept.nombre,
                    isUserDept: true,
                    isLocked: true //marcar como bloqueado
                };
                
                //cargar info especifica del departamento
                loadDepartmentView(userDept.id_departamento, userDept.nombre);
                
            } else {
                console.error('NO se pudo obtener el departamento del usuario:', data.message);
                showNoDepartmentData('Error al cargar departamento');
            }
        })
        .catch(error => {
            console.error('Error cargando departamento del usuario:', error);
            showNoDepartmentData('Error de conexión');
        });
}

function startAutoRefresh() { 
    // Limpiar intervalo existente si hay uno 
    if (dashboardChartsInstance.refreshInterval) { 
        clearInterval(dashboardChartsInstance.refreshInterval); 
    } 

    // Crear nuevo intervalo 
    dashboardChartsInstance.refreshInterval = setInterval(() => { 
        refreshDashboardData(); 
    }, dashboardChartsInstance.refreshRate); 
    console.log('Auto-refresh iniciado'); 
} 

function stopAutoRefresh() { 
    if (dashboardChartsInstance.refreshInterval) { 
        clearInterval(dashboardChartsInstance.refreshInterval); 
        dashboardChartsInstance.refreshInterval = null; 
        console.log('Auto-refresh detenido'); 
    } 
} 

function refreshDashboardData() { 
    // Evitar múltiples refreshes simultáneos 
    if (dashboardChartsInstance.isRefreshing) { 
        console.log('Refresh ya en progreso, saltando...'); 
        return; 
    } 

    dashboardChartsInstance.isRefreshing = true; 
    console.log('Actualizando datos del dashboard...', new Date().toLocaleTimeString()); 

    // For role-locked users, always refresh department view
    if (dashboardChartsInstance.isRoleLocked && dashboardChartsInstance.currentDepartment) {
        const deptId = dashboardChartsInstance.currentDepartment.id;
        const deptName = dashboardChartsInstance.currentDepartment.name;
        console.log(`Refrescando vista del departamento (locked): ${deptName}`);
        refreshDepartmentView(deptId, deptName);
        return;
    }

    // Determinar qué vista refrescar basado en el estado actual 
    if (dashboardChartsInstance.currentDepartment) { 
        // Si hay un departamento seleccionado, refrescar vista de departamento 
        const deptId = dashboardChartsInstance.currentDepartment.id; 
        const deptName = dashboardChartsInstance.currentDepartment.name; 
        console.log(`Refrescando vista del departamento: ${deptName}`); 
        refreshDepartmentView(deptId, deptName); 
    } else { 
        // Si no hay departamento seleccionado, refrescar vista de comparación 
        console.log('Refrescando vista de comparación'); 
        refreshComparisonView(); 
    } 
} 

function refreshComparisonView() { 
    if (dashboardChartsInstance.isRoleLocked) {
        console.log('Comparison view blocked for role-locked user');
        dashboardChartsInstance.isRefreshing = false;
        return;
    }

    Promise.all([ 
        fetch('../php/get_departments.php').then(r => r.json()), 
        fetch('../php/get_projects.php').then(r => r.json()) 
    ]) 

    .then(([deptResponse, projResponse]) => { 
        if (deptResponse.success && projResponse.success) { 
            const departments = deptResponse.departamentos; 
            const projects = projResponse.proyectos; 
            console.log('Datos de comparación actualizados'); 
            processComparisonData(departments, projects); 
            // Refrescar otras gráficas 
            loadProjectTrendComparison(); 
            loadTaskTrendComparison(); 
            loadDepartmentEfficiency(); 
        } else { 
            console.error('Error en refresh de comparación'); 
        } 
    }) 

    .catch(error => { 
        console.error('Error en refreshComparisonView:', error); 
    }) 

    .finally(() => { 
        dashboardChartsInstance.isRefreshing = false; 
    }); 
} 

function refreshDepartmentView(deptId, deptName) { 
    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`) 
        .then(response => response.json()) 
        .then(data => { 

            if (data.success) { 
                console.log(`Datos del departamento ${deptName} actualizados`); 

                // Verificar si hay datos 
                if (data.proyectos.length === 0) { 
                    console.log(` No hay proyectos en el departamento ${deptName}`); 
                    showNoDepartmentData(deptName); 

                } else { 
                    processDepepartmentData(data.proyectos, deptName); 
                    // Refrescar otras gráficas del departamento 
                    loadProjectTrendForDepartment(deptId, deptName); 
                    loadTaskTrendForDepartment(deptId, deptName); 
                    loadPersonEfficiencyByDepartment(deptId, deptName); 
                } 
            } else { 
                console.error('Error en refresh de departamento:', data.message); 
            } 
        }) 

        .catch(error => { 
            console.error('Error en refreshDepartmentView:', error); 
        }) 
        .finally(() => { 
            dashboardChartsInstance.isRefreshing = false; 
        }); 
} 

// Función para cambiar el intervalo de refresh (opcional) 
function setRefreshRate(milliseconds) { 
    dashboardChartsInstance.refreshRate = milliseconds; 
    console.log(`Intervalo de refresh actualizado a ${milliseconds / 1000} segundos`); 
    // Reiniciar el auto-refresh con el nuevo intervalo 
    startAutoRefresh(); 
} 

function showNoDepartmentData(deptName) { 
    console.log(`Mostrando mensaje de "sin datos" para ${deptName}`); 
    // Mostrar mensaje en gráfica de barras 
    showNoDataMessage('barChart', `No hay proyectos en ${deptName}`, 'No se encontraron proyectos para mostrar'); 
    // Mostrar mensaje en gráfica de dona 
    showNoDataMessage('doughnutChart', `Sin datos - ${deptName}`, 'No hay proyectos para mostrar'); 
    // Mostrar mensaje en gráfica de línea
    showNoDataMessage('lineChart', `Sin datos - ${deptName}`, 'No hay progreso de proyectos para mostrar'); 
    // Mostrar mensaje en gráfica de área 
    showNoDataMessage('areaChart', `Sin datos - ${deptName}`, 'No hay avances de tareas para mostrar'); 
    // Mostrar mensaje en gráfica de dispersión 
    showNoDataMessage('scatterChart', `Sin datos - ${deptName}`, 'No hay datos de eficiencia para mostrar'); 
    // Mostrar mensaje en gráfica de carga de trabajo 
    showNoDataMessage('workloadChart', `Sin datos - ${deptName}`, 'No hay distribución de carga para mostrar'); 
} 

function showNoDataMessage(canvasId, title, message) { 
    const canvas = document.getElementById(canvasId); 
    if (!canvas) { 
        console.warn(`Canvas ${canvasId} no encontrado`); 
        return; 
    } 

    // Destruir gráfica existente si existe 
    if (dashboardChartsInstance.charts[canvasId]) { 
        dashboardChartsInstance.charts[canvasId].destroy(); 
        dashboardChartsInstance.charts[canvasId] = null; 
    } 

    // Obtener contexto del canvas 
    const ctx = canvas.getContext('2d'); 

    // Limpiar el canvas 
    ctx.clearRect(0, 0, canvas.width, canvas.height); 

    // Configurar estilos 
    ctx.fillStyle = '#e0e0e0'; 
    ctx.fillRect(0, 0, canvas.width, canvas.height); 

    // Dibujar icono de "sin datos" 
    ctx.fillStyle = '#999'; 
    ctx.font = 'bold 48px Arial'; 
    ctx.textAlign = 'center'; 
    ctx.textBaseline = 'middle'; 

    // Dibujar título 
    ctx.fillStyle = '#555'; 
    ctx.font = 'bold 18px Arial'; 
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20); 

    // Dibujar mensaje 
    ctx.fillStyle = '#777'; 
    ctx.font = '14px Arial'; 
    ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 45); 

    console.log(`Mensaje "sin datos" mostrado en ${canvasId}`); 
} 

function loadUserDepartmentView() { 
    console.log('Cargando vista del departamento del usuario...'); 

    fetch('../php/get_user_department.php') 
        .then(response => { 
            if (!response.ok) { 
                throw new Error('Error fetching user department'); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success && data.department) { 
                const userDept = data.department; 
                console.log('Departamento del usuario:', userDept); 

                dashboardChartsInstance.currentDepartment = { 
                    id: userDept.id_departamento, 
                    name: userDept.nombre, 
                    isUserDept: true 
                }; 

                updateDropdownButtonText(userDept.nombre); 
                loadDepartmentView(userDept.id_departamento, userDept.nombre); 

            } else { 
                console.warn('No se pudo obtener el departamento del usuario:', data.message); 
                if (!dashboardChartsInstance.isRoleLocked) {
                    loadComparisonView(); 
                }
            } 
        }) 

        .catch(error => { 
            console.error('Error loading user department:', error); 
            if (!dashboardChartsInstance.isRoleLocked) {
                loadComparisonView(); 
            }
        }); 
}

function loadComparisonView() { 
    if (dashboardChartsInstance.isRoleLocked) {
        console.log('vista de comparacion bloqueada');
        return;
    }

    console.log('Cargando vista de comparación (todos los departamentos)'); 
    console.log('Reseteando estado a comparación...'); 
    dashboardChartsInstance.currentDepartment = null; 

    Promise.all([ 
        fetch('../php/get_departments.php').then(r => r.json()), 
        fetch('../php/get_projects.php').then(r => r.json()) 
    ]) 

    .then(([deptResponse, projResponse]) => { 

        if (deptResponse.success && projResponse.success) { 
            const departments = deptResponse.departamentos; 
            const projects = projResponse.proyectos; 
            console.log('Datos de comparación obtenidos - actualizando gráficos...'); 
            processComparisonData(departments, projects);  
        } else { 
            console.error('Erro obteniendo info para la vista de comparacion'); 
        } 
    }) 

    .catch(error => { 
        console.error('Error in loadComparisonView:', error); 
    }); 
} 

function processComparisonData(departments, projects) { 

    console.log('Procesando datos de comparación...'); 
    console.log('Departamentos:', departments.length); 
    console.log('Proyectos:', projects.length); 
     
    const completedByDept = prepareCompletedProjectsByDepartment(departments, projects); 
    const statusDistribution = prepareProjectStatusDistribution(projects); 
    updateBarChart(completedByDept); 
    updateDoughnutChart(statusDistribution); 
} 

function loadDepartmentView(deptId, deptName) { 
    console.log('CAMBIAR A VISTA DE DEPARTAMENTO:', deptName); 
    dashboardChartsInstance.currentDepartment = { 
        id: deptId, 
        name: deptName, 
        updatedAt: new Date().getTime(),
        isLocked: dashboardChartsInstance.isRoleLocked //quedarse con el estado bloqueado
    }; 

    console.log('Estado de departamento actualizado:', dashboardChartsInstance.currentDepartment); 
    console.log('Cargando info especifica del departamento...'); 

    fetch(`../php/get_projects_by_department.php?id_departamento=${deptId}`) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error(`HTTP error! status: ${response.status}`); 
            } 
            return response.json(); 
        }) 

        .then(data => { 
            if (data.success) { 
                console.log('Info de proyecto recibida:', data.proyectos.length, 'proyectos'); 
                console.log('ID de departamento configurado:', data.id_departamento); 

                // Verificar si hay datos 
                if (data.proyectos.length === 0) { 
                    console.log(`No hay proyectos en el departamento ${deptName}`); 
                    showNoDepartmentData(deptName); 
                    return; // Detener aquí si no hay datos 
                } 

                    //procesar datos y hacer graficas
                processDepepartmentData(data.proyectos, deptName); 
                loadProjectTrendForDepartment(deptId, deptName);  
                loadTaskTrendForDepartment(deptId, deptName); 

                loadPersonEfficiencyByDepartment(deptId, deptName);  

            } else { 
                console.error('Error obteniendo proyectos:', data.message); 
                alert('Error al cargar proyectos: ' + data.message); 
            } 
        }) 
        .catch(error => { 
            console.error('Error cargando vista de departamento:', error); 
            alert('Error de conexión al cargar proyectos del departamento'); 
        }); 
} 

function processDepepartmentData(projects, deptName) { 
    console.log(`Procesando datos del departamento: ${deptName}`); 
    console.log(`Total de proyectos: ${projects.length}`); 
    const statusDistribution = prepareDepartmentStatusDistribution(projects); 
    updateDoughnutChartForDepartment(statusDistribution, deptName); 
    updateBarChartForDepartment(projects, deptName); 
} 

function clearDepartmentSelection() { 
    //bloquear la funcion si es usuario o gerente
    if (dashboardChartsInstance.isRoleLocked) {
        return;
    }
 
    //cargar graficas
    dashboardChartsInstance.currentDepartment = null; 
    updateDropdownButtonText('Revisar departamentos');

    loadComparisonView(); 

    setTimeout(() => { 
        loadProjectTrendComparison(); 
    }, 300); 

    setTimeout(() => {  
        loadTaskTrendComparison(); 
    }, 600); 

    setTimeout(() => { 
        loadDepartmentEfficiency(); 
    }, 900); 

    setTimeout(() => {  
        console.log('estado actual del departamento:', dashboardChartsInstance.currentDepartment); 
    }, 1200); 

} 

function selectDepartmentFromDropdown(deptId, deptName) { 
    if (dashboardChartsInstance.isRoleLocked) {
        return;
    } 
    loadDepartmentView(deptId, deptName); 
} 

function updateDropdownButtonText(text) { 
    const dropdownButton = document.querySelector('#messageDropdown'); 
    if (dropdownButton) { 
        const existingIcon = dropdownButton.querySelector('i'); 
        dropdownButton.textContent = text + ' '; 
        if (existingIcon) { 
            const newIcon = existingIcon.cloneNode(true); 
            dropdownButton.appendChild(newIcon); 
        } 
    } 
} 

function canSwitchDepartments() {
    return !dashboardChartsInstance.isRoleLocked;
}

function getUserRoleInfo() {
    return dashboardChartsInstance.userConfig;
}

// Limpiar intervalo cuando se cierra la página 
window.addEventListener('beforeunload', function() { 
    stopAutoRefresh(); 
}); 

document.addEventListener('DOMContentLoaded', function() { 
    initializeDashboardCharts(); 
});