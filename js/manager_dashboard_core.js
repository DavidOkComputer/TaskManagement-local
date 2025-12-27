/*manager_dashboard_core.js controlador de todos los graficos con soporte para múltiples departamentos*/

let managerDashboard = {
    charts: {},
    department: null,
    allDepartments: [],
    hasMultipleDepartments: false,
    refreshInterval: null,
    refreshRate: 60000, // 60 segundos o un minuto
    isRefreshing: false,
    
    colors: {
        primary: 'rgba(34, 139, 89, 0.7)',
        primarySolid: 'rgba(34, 139, 89, 1)',
        secondary: 'rgba(80, 154, 108, 0.7)',
        secondarySolid: 'rgba(80, 154, 108, 1)',
        dark: 'rgba(24, 97, 62, 0.7)',
        darkSolid: 'rgba(24, 97, 62, 1)',
        gray: 'rgba(130, 140, 150, 0.7)',
        graySolid: 'rgba(130, 140, 150, 1)',
        light: 'rgba(200, 205, 210, 0.7)',
        lightSolid: 'rgba(200, 205, 210, 1)',
        black: 'rgba(50, 50, 50, 0.7)',
        blackSolid: 'rgba(50, 50, 50, 1)'
    },
    
    statusColors: {
        completado: '#009b4a',
        'en proceso': '#F2994A',
        pendiente: '#F2C94C',
        vencido: '#C62828'
    },
    
    statusBorderColors: {
        completado: '#009b4a',
        'en proceso': '#F2994A',
        pendiente: '#F2C94C',
        vencido: '#C62828'
    }
};

function initializeManagerDashboard() {
    //cargar el departamento del gerente luego los graficos
    loadManagerDepartment()
        .then(() => {
            initializeAllCharts();
            startAutoRefresh();
        })
        .catch(error => {
            console.error('Error inicializando dashboard:', error);
            showGlobalError('Error al cargar información del departamento');
        });
}

function loadManagerDepartment() {
    return fetch('../php/manager_get_department.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error de conexión: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Error al obtener departamento');
            }
            
            // Guardar departamento principal
            managerDashboard.department = {
                id: data.department.id_departamento,
                nombre: data.department.nombre,
                descripcion: data.department.descripcion
            };
            
            // Guardar información de múltiples departamentos
            managerDashboard.allDepartments = data.managed_departments || data.all_departments || [];
            managerDashboard.hasMultipleDepartments = data.has_multiple_departments || false;
            
            // Actualizar titulo de pagina con nombre de departamento
            updateDepartmentDisplay(managerDashboard.department.nombre);
            
            // Si hay múltiples departamentos, actualizar el texto del dropdown
            if (managerDashboard.hasMultipleDepartments) {
                updateDropdownWithCurrentDepartment(managerDashboard.department.nombre);
            }
            
            return managerDashboard.department;
        });
}

function updateDepartmentDisplay(deptName) {
    // Cargar todos los elementos que muestren el nombre del departamento
    const deptDisplayElements = document.querySelectorAll('.manager-department-name');
    deptDisplayElements.forEach(el => {
        el.textContent = deptName;
    });
    
    // Actualizar subtitulo si es que existe
    const welcomeSubtext = document.querySelector('.welcome-sub-text');
    if (welcomeSubtext) {
        const hasMutliple = managerDashboard.hasMultipleDepartments;
        if (hasMutliple) {
            welcomeSubtext.innerHTML = `Departamento: <strong>${deptName}</strong> <span class="multi-dept-badge"><i class="mdi mdi-swap-horizontal"></i> Cambiar</span>`;
        } else {
            welcomeSubtext.textContent = `Departamento: ${deptName}`;
        }
    }
}

function updateDropdownWithCurrentDepartment(deptName) {
    const dropdownButton = document.getElementById('departmentDropdown');
    if (dropdownButton) {
        dropdownButton.innerHTML = `${escapeHtmlCore(deptName)} <i class="mdi mdi-chevron-down"></i>`;
    }
}

function escapeHtmlCore(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function initializeAllCharts() {
    initializeManagerDoughnutChart();
    
    setTimeout(() => {
        initializeManagerBarChart();
    }, 100);
    
    setTimeout(() => {
        initializeManagerLineChart();
    }, 200);
    
    setTimeout(() => {
        initializeManagerAreaChart();
    }, 300);
    
    setTimeout(() => {
        initializeManagerScatterChart();
    }, 400);
    
    setTimeout(() => {
        initializeManagerWorkloadChart();
    }, 500);
}

function refreshAllCharts() {
    if (managerDashboard.isRefreshing) {
        return;
    }
    
    managerDashboard.isRefreshing = true;
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    Promise.all([
        refreshManagerDoughnutChart(deptId, deptName),
        refreshManagerBarChart(deptId, deptName),
        refreshManagerLineChart(deptId, deptName),
        refreshManagerAreaChart(deptId, deptName),
        refreshManagerScatterChart(deptId, deptName),
        refreshManagerWorkloadChart(deptId, deptName)
    ])
    .then(() => {
    })
    .catch(error => {
        console.error('Error en refresh:', error);
    })
    .finally(() => {
        managerDashboard.isRefreshing = false;
    });
}

function startAutoRefresh() {
    if (managerDashboard.refreshInterval) {
        clearInterval(managerDashboard.refreshInterval);
    }
    
    managerDashboard.refreshInterval = setInterval(() => {
        refreshAllCharts();
    }, managerDashboard.refreshRate);
}

function stopAutoRefresh() {
    if (managerDashboard.refreshInterval) {
        clearInterval(managerDashboard.refreshInterval);
        managerDashboard.refreshInterval = null;
    }
}

function setRefreshRate(milliseconds) {
    managerDashboard.refreshRate = milliseconds;
    startAutoRefresh();
}

function switchDepartment(deptId, deptName) {
    // Actualizar el estado global
    managerDashboard.department = {
        id: deptId,
        nombre: deptName,
        descripcion: ''
    };
    
    // Actualizar la UI
    updateDepartmentDisplay(deptName);
    updateDropdownWithCurrentDepartment(deptName);
    
    // Refrescar todas las gráficas
    refreshAllChartsForNewDepartment(deptId, deptName);
}

function refreshAllChartsForNewDepartment(deptId, deptName) {
    // Mostrar indicador de actualización
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.add('active');
    }
    
    // Refrescar secuencialmente para evitar sobrecarga
    refreshManagerDoughnutChart(deptId, deptName)
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, 100));
        })
        .then(() => {
            return refreshManagerBarChart(deptId, deptName);
        })
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, 100));
        })
        .then(() => {
            return refreshManagerLineChart(deptId, deptName);
        })
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, 100));
        })
        .then(() => {
            return refreshManagerAreaChart(deptId, deptName);
        })
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, 100));
        })
        .then(() => {
            return refreshManagerScatterChart(deptId, deptName);
        })
        .then(() => {
            return new Promise(resolve => setTimeout(resolve, 100));
        })
        .then(() => {
            return refreshManagerWorkloadChart(deptId, deptName);
        })
        .catch(error => {
            console.error('Error actualizando gráficas:', error);
        })
        .finally(() => {
            // Ocultar indicador
            if (indicator) {
                indicator.classList.remove('active');
            }
        });
}

function shortenTitle(title, maxLength = 15) {
    if (!title) return '';
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength) + '...';
}

function showNoDataMessage(canvasId, title, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`Canvas ${canvasId} no encontrado`);
        return;
    }
    
    // Destruir graficas existentes
    if (managerDashboard.charts[canvasId]) {
        managerDashboard.charts[canvasId].destroy();
        managerDashboard.charts[canvasId] = null;
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Icono
    ctx.fillStyle = '#e0e0e0';
    ctx.font = '48px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('', canvas.width / 2, canvas.height / 2 - 30);
    
    // Titulo
    ctx.fillStyle = '#495057';
    ctx.font = 'bold 16px Arial';
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20);
    
    // Mensaje
    ctx.fillStyle = '#6c757d';
    ctx.font = '14px Arial';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 45);
}

function showGlobalError(message) {
    console.error('Error global:', message);
    
    const canvasIds = ['lineChart', 'barChart', 'areaChart', 'doughnutChart', 'scatterChart', 'workloadChart'];
    canvasIds.forEach(id => {
        showNoDataMessage(id, 'Error', message);
    });
}

function getColorByIndex(index, opacity = 0.7) {
    const colorPalette = [
        `rgba(34, 139, 89, ${opacity})`,
        `rgba(80, 154, 108, ${opacity})`,
        `rgba(24, 97, 62, ${opacity})`,
        `rgba(130, 140, 150, ${opacity})`,
        `rgba(200, 205, 210, ${opacity})`,
        `rgba(50, 50, 50, ${opacity})`,
        `rgba(45, 110, 80, ${opacity})`,
        `rgba(160, 170, 180, ${opacity})`
    ];
    return colorPalette[index % colorPalette.length];
}

function getProgressColor(progress) {
    if (progress === 100) return managerDashboard.colors.primary;
    if (progress >= 75) return managerDashboard.colors.secondary;
    if (progress >= 50) return managerDashboard.colors.gray;
    return managerDashboard.colors.light;
}

function getCurrentDepartment() {
    return managerDashboard.department;
}

function managerHasMultipleDepartments() {
    return managerDashboard.hasMultipleDepartments;
}

function getManagerDepartments() {
    return managerDashboard.allDepartments;
}

// Exportar funciones para uso externo
window.switchDepartment = switchDepartment;
window.getCurrentDepartment = getCurrentDepartment;
window.managerHasMultipleDepartments = managerHasMultipleDepartments;
window.getManagerDepartments = getManagerDepartments;

window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

document.addEventListener('DOMContentLoaded', function() {
    initializeManagerDashboard();
});