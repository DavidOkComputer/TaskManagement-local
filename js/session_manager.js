
/** 

 * Session Manager - Cliente 

 * Maneja la información de sesión del usuario en el lado del cliente 

 */ 

 

const SessionManager = { 

    /** 

     * Obtiene la sesión del usuario desde localStorage 

     */ 

    getUserSession: function() { 

        try { 

            const session = localStorage.getItem('userSession'); 

            return session ? JSON.parse(session) : null; 

        } catch (error) { 

            console.error('Error obteniendo sesión:', error); 

            return null; 

        } 

    }, 

 

    /** 

     * Verifica si el usuario está logueado 

     */ 

    isLoggedIn: function() { 

        const session = this.getUserSession(); 

        return session !== null && session.id_usuario; 

    }, 

 

    /** 

     * Obtiene el tipo de usuario 

     */ 

    getUserType: function() { 

        const session = this.getUserSession(); 

        return session ? session.user_type : null; 

    }, 

 

    /** 

     * Obtiene el nivel de acceso 

     */ 

    getAccessLevel: function() { 

        const session = this.getUserSession(); 

        return session ? session.nivel_acceso : 0; 

    }, 

 

    /** 

     * Verifica si el usuario es admin 

     */ 

    isAdmin: function() { 

        return this.getUserType() === 'admin'; 

    }, 

 

    /** 

     * Verifica si el usuario es manager 

     */ 

    isManager: function() { 

        const userType = this.getUserType(); 

        return userType === 'manager' || userType === 'admin'; 

    }, 

 

    /** 

     * Verifica si el usuario es project leader 

     */ 

    isProjectLeader: function() { 

        const userType = this.getUserType(); 

        return userType === 'project_leader' || this.isManager(); 

    }, 

 

    /** 

     * Verifica si el usuario es team member 

     */ 

    isTeamMember: function() { 

        return this.getUserType() === 'team_member'; 

    }, 

 

    /** 

     * Obtiene el nombre completo del usuario 

     */ 

    getUserName: function() { 

        const session = this.getUserSession(); 

        return session ? session.nombre_completo : 'Usuario'; 

    }, 

 

    /** 

     * Obtiene el rol del usuario 

     */ 

    getUserRole: function() { 

        const session = this.getUserSession(); 

        return session ? session.nombre_rol : 'Sin rol'; 

    }, 

 

    /** 

     * Muestra/oculta elementos según el tipo de usuario 

     */ 

    applyUserTypeVisibility: function() { 

        const userType = this.getUserType(); 

         

        // Elementos solo para admin 

        document.querySelectorAll('[data-role="admin"]').forEach(el => { 

            el.style.display = this.isAdmin() ? '' : 'none'; 

        }); 

 

        // Elementos para manager y superiores 

        document.querySelectorAll('[data-role="manager"]').forEach(el => { 

            el.style.display = this.isManager() ? '' : 'none'; 

        }); 

 

        // Elementos para project leader y superiores 

        document.querySelectorAll('[data-role="project_leader"]').forEach(el => { 

            el.style.display = this.isProjectLeader() ? '' : 'none'; 

        }); 

 

        // Elementos solo para team members 

        document.querySelectorAll('[data-role="team_member"]').forEach(el => { 

            el.style.display = this.isTeamMember() ? '' : 'none'; 

        }); 

 

        console.log('Visibilidad aplicada para tipo de usuario:', userType); 

    }, 

 

    /** 

     * Actualiza el UI con información del usuario 

     */ 

    updateUserInfo: function() { 

        // Actualizar nombre de usuario en el UI 

        const userNameElements = document.querySelectorAll('[data-user-name]'); 

        userNameElements.forEach(el => { 

            el.textContent = this.getUserName(); 

        }); 

 

        // Actualizar rol en el UI 

        const userRoleElements = document.querySelectorAll('[data-user-role]'); 

        userRoleElements.forEach(el => { 

            el.textContent = this.getUserRole(); 

        }); 

 

        // Aplicar clases según tipo de usuario 

        document.body.classList.add(`user-type-${this.getUserType()}`); 

    }, 

 

    /** 

     * Redirige al usuario si no está autorizado 

     */ 

    requireLogin: function() { 

        if (!this.isLoggedIn()) { 

            window.location.href = 'index.php'; 

            return false; 

        } 

        return true; 

    }, 

 

    /** 

     * Redirige si el usuario no tiene el nivel de acceso requerido 

     */ 

    requireAccessLevel: function(minLevel) { 

        if (!this.requireLogin()) return false; 

         

        if (this.getAccessLevel() < minLevel) { 

            alert('No tienes permisos suficientes para acceder a esta página'); 

            window.location.href = 'dashboard.php'; 

            return false; 

        } 

        return true; 

    }, 

 

    /** 

     * Cierra sesión 

     */ 

    logout: function() { 

        localStorage.removeItem('userSession'); 

        window.location.href = 'php/logout.php'; 

    } 

}; 

 

// Inicializar cuando el DOM esté listo 

document.addEventListener('DOMContentLoaded', function() { 

    // Verificar si estamos en una página protegida (no index.php) 

    if (!window.location.pathname.includes('index.php') &&  

        !window.location.pathname.endsWith('/')) { 

        SessionManager.requireLogin(); 

        SessionManager.updateUserInfo(); 

        SessionManager.applyUserTypeVisibility(); 

    } 

});