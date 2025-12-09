/*session_manager.js maneja la info de sesion del usuario normal*/
const SessionManager = {
	getUserSession: function() {
		try {
			const session = localStorage.getItem('userSession');
			return session ? JSON.parse(session) : null;
		} catch (error) {
			console.error('Error obteniendo sesión:', error);
			return null;
		}
	},
	isLoggedIn: function() {
		const session = this.getUserSession();
		return session !== null && session.id_usuario;
	},
	getUserType: function() {
		const session = this.getUserSession();
		return session ? session.user_type : null;
	},
	getAccessLevel: function() {
		const session = this.getUserSession();
		return session ? session.nivel_acceso : 0;
	},
	isAdmin: function() {
		return this.getUserType() === 'admin';
	},
	isManager: function() {
		const userType = this.getUserType();
		return userType === 'manager' || userType === 'admin';
	},
	isProjectLeader: function() {
		const userType = this.getUserType();
		return userType === 'project_leader' || this.isManager();
	},
	isTeamMember: function() {
		return this.getUserType() === 'team_member';
	},
	getUserName: function() {
		const session = this.getUserSession();
		return session ? session.nombre_completo : 'Usuario';
	},
	getUserRole: function() {
		const session = this.getUserSession();
		return session ? session.nombre_rol : 'Sin rol';
	},
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
	},
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
	requireLogin: function() {
		if (!this.isLoggedIn()) {
			window.location.href = 'index.php';
			return false;
		}
		return true;
	},
	requireAccessLevel: function(minLevel) {
		if (!this.requireLogin()) return false;
		if (this.getAccessLevel() < minLevel) {
			alert('No tienes permisos suficientes para acceder a esta página');
			window.location.href = 'dashboard.php';
			return false;
		}
		return true;
	},

	logout: function() {
		localStorage.removeItem('userSession');
		window.location.href = 'php/logout.php';
	}
};
document.addEventListener('DOMContentLoaded', function() {
	// Verificar si estamos en una página protegida (no index.php) 
	if (!window.location.pathname.includes('index.php') &&
		!window.location.pathname.endsWith('/')) {
		SessionManager.requireLogin();
		SessionManager.updateUserInfo();
		SessionManager.applyUserTypeVisibility();
	}
});