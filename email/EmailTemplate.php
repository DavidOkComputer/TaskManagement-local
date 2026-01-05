<?php
/*EmailTemplates.php plantilla html para notificaciones por correo*/

class EmailTemplates {
    private $baseTemplate;
    private $templates = [];
    private $colors = [
        'primary' => '#009B4A',
        'danger' => '#000000',
        'warning' => '#666666',
        'info' => '#666666',
        'success' => '#009B4A',
        'dark' => '#000000',
        'light' => '#E9E9E9',
        'white' => '#ffffff'
    ];
    
    public function __construct() {
        $this->loadBaseTemplate();
        $this->loadTemplates();
    }
    
    private function loadBaseTemplate() {
        $this->baseTemplate = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{SUBJECT}}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
        }
        .email-container {
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #009B4A 0%, #009B4A 100%);
            color: #ffffff;
            padding: 25px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        .header .subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .task-card {
            background: #f8f9fa;
            border-left: 4px solid #009B4A;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .task-card.urgent {
            border-left-color: #000000;
            background: #fff5f5;
        }
        .task-card.warning {
            border-left-color: #666666;
            background: #fff8e1;
        }
        .task-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 18px;
        }
        .task-card p {
            margin: 0 0 10px 0;
            color: #666;
        }
        .meta-info {
            font-size: 14px;
            color: #666;
        }
        .meta-info strong {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background-color: #009B4A;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #009B4A;
        }
        .btn-center {
            text-align: center;
            margin: 25px 0;
        }
        .stats-container {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        .stat-box {
            display: table-cell;
            width: 33.33%;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
        }
        .stat-box.success { background: #e8f5e9; }
        .stat-box.warning { background: #fff3e0; }
        .stat-box.danger { background: #ffebee; }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        .stat-box.success .stat-number { color: #009B4A; }
        .stat-box.warning .stat-number { color: #666666; }
        .stat-box.danger .stat-number { color: #000000; }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 30px;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .footer a {
            color: #009B4A;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background: #eee;
            margin: 20px 0;
        }
        .upcoming-list {
            margin: 15px 0;
            padding: 0;
            list-style: none;
        }
        .upcoming-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .upcoming-list li:last-child {
            border-bottom: none;
        }
        .date-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #009B4A;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }
        @media only screen and (max-width: 600px) {
            .content { padding: 20px; }
            .stat-box { display: block; width: 100%; margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="header">
                <h1>Sistema de Gestión de Tareas</h1>
                <div class="subtitle">{{HEADER_SUBTITLE}}</div>
            </div>
            <div class="content">
                {{CONTENT}}
            </div>
            <div class="footer">
                <p>Este es un mensaje automático del Sistema de Gestión de Tareas.</p>
                <p>Por favor no responda directamente a este correo.</p>
                <p style="margin-top: 10px;">
                    <a href="{{URL_SISTEMA}}">Acceder al Sistema</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    private function loadTemplates() {
        // Tarea Asignada
        $this->templates['tarea_asignada'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Se te ha asignado una nueva tarea en el sistema:</p>
            
            <div class="task-card">
                <h3>{{NOMBRE_TAREA}}</h3>
                <p>{{DESCRIPCION_TAREA}}</p>
                <div class="divider"></div>
                <div class="meta-info">
                    <p><strong>Proyecto:</strong> {{NOMBRE_PROYECTO}}</p>
                    <p><strong>Fecha límite:</strong> {{FECHA_VENCIMIENTO}}</p>
                    <p><strong>Asignado por:</strong> {{ASIGNADO_POR}}</p>
                </div>
            </div>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn">Ver Tarea</a>
            </div>';
        
        // Recordatorio de Vencimiento
        $this->templates['tarea_vencimiento'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Te recordamos que la siguiente tarea está próxima a vencer:</p>
            
            <div class="task-card warning">
                <h3>{{NOMBRE_TAREA}}</h3>
                <div class="meta-info">
                    <p><strong>Proyecto:</strong> {{NOMBRE_PROYECTO}}</p>
                    <p><strong>Vence en:</strong> <span style="color: #666666; font-weight: bold;">{{DIAS_RESTANTES}} día(s)</span></p>
                    <p><strong>Fecha límite:</strong> {{FECHA_VENCIMIENTO}}</p>
                </div>
            </div>
            
            <p>Por favor, asegúrate de completar esta tarea antes de la fecha límite.</p>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn">Ver Tarea</a>
            </div>';
        
        // Tarea Vencida
        $this->templates['tarea_vencida'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>La siguiente tarea ha superado su fecha límite:</p>
            
            <div class="task-card urgent">
                <h3>{{NOMBRE_TAREA}}</h3>
                <div class="meta-info">
                    <p><strong>Proyecto:</strong> {{NOMBRE_PROYECTO}}</p>
                    <p><strong>Venció hace:</strong> <span style="color: #666666; font-weight: bold;">{{DIAS_VENCIDOS}} día(s)</span></p>
                    <p><strong>Fecha límite:</strong> {{FECHA_VENCIMIENTO}}</p>
                </div>
            </div>
            
            <p>Por favor, actualiza el estado de esta tarea lo antes posible o contacta a tu supervisor si necesitas una extensión.</p>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn" style="background-color: #000000;">Atender Tarea</a>
            </div>';
        
        // Tarea Completada (notificación al creador/gerente)
        $this->templates['tarea_completada'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>La siguiente tarea ha sido marcada como completada:</p>
            
            <div class="task-card" style="border-left-color: #009B4A;">
                <h3>{{NOMBRE_TAREA}}</h3>
                <div class="meta-info">
                    <p><strong>Proyecto:</strong> {{NOMBRE_PROYECTO}}</p>
                    <p><strong>Completada por:</strong> {{COMPLETADA_POR}}</p>
                    <p><strong>Fecha de completado:</strong> {{FECHA_COMPLETADO}}</p>
                </div>
            </div>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn">Ver Detalles</a>
            </div>';
        
        // Proyecto Asignado
        $this->templates['proyecto_asignado'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Has sido asignado a un nuevo proyecto:</p>
            
            <div class="task-card" style="border-left-color: #009B4A;">
                <h3>{{NOMBRE_PROYECTO}}</h3>
                <p>{{DESCRIPCION_PROYECTO}}</p>
                <div class="divider"></div>
                <div class="meta-info">
                    <p><strong>Departamento:</strong> {{NOMBRE_DEPARTAMENTO}}</p>
                    <p><strong>Fecha límite:</strong> {{FECHA_VENCIMIENTO}}</p>
                    <p><strong>Creado por:</strong> {{CREADO_POR}}</p>
                </div>
            </div>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn" style="background-color: #009B4A;">Ver Proyecto</a>
            </div>';
        
        // Objetivo Asignado
        $this->templates['objetivo_asignado'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Se te ha asignado un nuevo objetivo:</p>
            
            <div class="task-card" style="border-left-color: #009B4A;">
                <h3>{{NOMBRE_OBJETIVO}}</h3>
                <p>{{DESCRIPCION_OBJETIVO}}</p>
                <div class="divider"></div>
                <div class="meta-info">
                    <p><strong>Departamento:</strong> {{NOMBRE_DEPARTAMENTO}}</p>
                    <p><strong>Fecha límite:</strong> {{FECHA_VENCIMIENTO}}</p>
                </div>
            </div>
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn" style="background-color: #009B4A;">Ver Objetivo</a>
            </div>';
        
        // Resumen Semanal
        $this->templates['resumen_semanal'] = '
            <p class="greeting">Hola <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Aquí está tu resumen de actividad de la semana:</p>
            
            <div class="stats-container">
                <div class="stat-box success">
                    <span class="stat-number">{{TAREAS_COMPLETADAS}}</span>
                    <span class="stat-label">Completadas</span>
                </div>
                <div class="stat-box warning">
                    <span class="stat-number">{{TAREAS_PENDIENTES}}</span>
                    <span class="stat-label">Pendientes</span>
                </div>
                <div class="stat-box danger">
                    <span class="stat-number">{{TAREAS_VENCIDAS}}</span>
                    <span class="stat-label">Vencidas</span>
                </div>
            </div>
            
            {{SECCION_PROXIMAS_TAREAS}}
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn">Ir al Sistema</a>
            </div>';
        
        // Recordatorio Diario
        $this->templates['recordatorio_diario'] = '
            <p class="greeting">Buenos días <strong>{{NOMBRE_USUARIO}}</strong>,</p>
            <p>Aquí está tu resumen de tareas para hoy:</p>
            
            {{TAREAS_HOY}}
            
            {{TAREAS_VENCIDAS_HOY}}
            
            <div class="btn-center">
                <a href="{{URL_SISTEMA}}" class="btn">Ver Todas las Tareas</a>
            </div>';
    }
    
    public function render($template_name, $variables = []) {
        if (!isset($this->templates[$template_name])) {
            throw new Exception("Plantilla '$template_name' no encontrada");
        }
        
        $content = $this->templates[$template_name];
        
        // Valores por defecto
        $defaults = [
            'URL_SISTEMA' => 'http://10.109.17.87/projectManagement',
            'HEADER_SUBTITLE' => $this->getSubtitleForType($template_name),
            'SUBJECT' => $variables['SUBJECT'] ?? 'Notificación'
        ];
        
        $variables = array_merge($defaults, $variables);
        
        // Reemplazar variables en el contenido
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Insertar contenido en la plantilla base
        $html = str_replace('{{CONTENT}}', $content, $this->baseTemplate);
        
        // Reemplazar variables en la plantilla base
        foreach ($variables as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        
        return $html;
    }
    
    private function getSubtitleForType($type) {
        $subtitles = [
            'tarea_asignada' => 'Nueva tarea asignada',
            'tarea_vencimiento' => 'Recordatorio de vencimiento',
            'tarea_vencida' => 'Tarea vencida',
            'tarea_completada' => 'Tarea completada',
            'proyecto_asignado' => 'Nuevo proyecto asignado',
            'proyecto_completado' => 'Proyecto completado',
            'objetivo_asignado' => 'Nuevo objetivo asignado',
            'recordatorio_diario' => 'Resumen diario',
            'resumen_semanal' => 'Resumen semanal',
            'prueba' => 'Email de prueba'
        ];
        
        return $subtitles[$type] ?? 'Notificación';
    }
    
    public function renderUpcomingTasks($tasks) {
        if (empty($tasks)) {
            return '<p style="color: #666; font-style: italic;">No hay tareas próximas a vencer.</p>';
        }
        
        $html = '<h3 style="margin-top: 20px;">Tareas próximas a vencer:</h3>';
        $html .= '<ul class="upcoming-list">';
        
        foreach ($tasks as $task) {
            $fecha = isset($task['fecha_cumplimiento']) 
                ? date('d/m/Y', strtotime($task['fecha_cumplimiento'])) 
                : 'Sin fecha';
            
            $html .= '<li>';
            $html .= '<strong>' . htmlspecialchars($task['nombre']) . '</strong>';
            $html .= '<span class="date-badge">' . $fecha . '</span>';
            if (!empty($task['proyecto_nombre'])) {
                $html .= '<br><small style="color: #666;">' . htmlspecialchars($task['proyecto_nombre']) . '</small>';
            }
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
    
    public function renderTodayTasks($tasks) {
        if (empty($tasks)) {
            return '<div class="task-card"><p>No tienes tareas pendientes para hoy. ¡Buen trabajo!</p></div>';
        }
        
        $html = '<h3>Tareas para hoy:</h3>';
        
        foreach ($tasks as $task) {
            $html .= '<div class="task-card">';
            $html .= '<h3>' . htmlspecialchars($task['nombre']) . '</h3>';
            $html .= '<p class="meta-info">';
            if (!empty($task['proyecto_nombre'])) {
                $html .= '<strong>Proyecto:</strong> ' . htmlspecialchars($task['proyecto_nombre']);
            }
            $html .= '</p>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    public function getAvailableTemplates() {
        return array_keys($this->templates);
    }
}