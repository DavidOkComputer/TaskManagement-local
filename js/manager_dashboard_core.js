/*manager_dashboard_core.js controlador de todos los graficaos*/

let managerDashboard = {
    charts: {},
    department: null,
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
        completado: 'rgba(34, 139, 89, 0.7)',
        'en proceso': 'rgba(130, 140, 150, 0.7)',
        pendiente: 'rgba(200, 205, 210, 0.7)',
        vencido: 'rgba(50, 50, 50, 0.7)'
    },
    
    statusBorderColors: {
        completado: 'rgba(34, 139, 89, 1)',
        'en proceso': 'rgba(130, 140, 150, 1)',
        pendiente: 'rgba(200, 205, 210, 1)',
        vencido: 'rgba(50, 50, 50, 1)'
    }
};

function initializeManagerDashboard() {
    console.log('INICIALIZANDO DASHBOARD DE GERENTE');
    
    //cargar el departamento del gerente luego los graficso
    loadManagerDepartment()
        .then(() => {
            console.log('Departamento cargado:', managerDashboard.department.nombre);
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
            
            managerDashboard.department = {
                id: data.department.id_departamento,
                nombre: data.department.nombre,
                descripcion: data.department.descripcion
            };
            
            //actualizar titulo de pagina con nombre de departamento
            updateDepartmentDisplay(managerDashboard.department.nombre);
            
            return managerDashboard.department;
        });
}

function updateDepartmentDisplay(deptName) {
    //cargar todos los elementos que muestren el nombre del departamento
    const deptDisplayElements = document.querySelectorAll('.manager-department-name');
    deptDisplayElements.forEach(el => {
        el.textContent = deptName;
    });
    
    //actualizar subtitulo si es que existe
    const welcomeSubtext = document.querySelector('.welcome-sub-text');
    if (welcomeSubtext) {
        welcomeSubtext.textContent = `Departamento: ${deptName}`;
    }
}

function initializeAllCharts() {
    console.log('Inicializando todas las gráficas...');
    
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
        console.log('Refresh en progreso, saltando...');
        return;
    }
    
    managerDashboard.isRefreshing = true;
    console.log('Refrescando datos del dashboard...', new Date().toLocaleTimeString());
    
    const deptId = managerDashboard.department.id;
    const deptName = managerDashboard.department.nombre;
    
    Promise.all([//refrescar
        refreshManagerDoughnutChart(deptId, deptName),
        refreshManagerBarChart(deptId, deptName),
        refreshManagerLineChart(deptId, deptName),
        refreshManagerAreaChart(deptId, deptName),
        refreshManagerScatterChart(deptId, deptName),
        refreshManagerWorkloadChart(deptId, deptName)
    ])
    .then(() => {
        console.log('Todas las gráficas actualizadas');
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
    
    console.log(`Auto-refresh iniciado: cada ${managerDashboard.refreshRate / 1000} segundos`);
}

function stopAutoRefresh() {
    if (managerDashboard.refreshInterval) {
        clearInterval(managerDashboard.refreshInterval);
        managerDashboard.refreshInterval = null;
        console.log('Auto-refresh detenido');
    }
}

function setRefreshRate(milliseconds) {
    managerDashboard.refreshRate = milliseconds;
    console.log(`Intervalo actualizado a ${milliseconds / 1000} segundos`);
    startAutoRefresh();
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
    
    if (managerDashboard.charts[canvasId]) {//destruir graficas existentes
        managerDashboard.charts[canvasId].destroy();
        managerDashboard.charts[canvasId] = null;
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#666666';
    ctx.font = '48px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('', canvas.width / 2, canvas.height / 2 - 30);
    //titulo
    ctx.fillStyle = '#495057';
    ctx.font = 'bold 16px Arial';
    ctx.fillText(title, canvas.width / 2, canvas.height / 2 + 20);
    //mensaje
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

window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

document.addEventListener('DOMContentLoaded', function() {
    initializeManagerDashboard();
});