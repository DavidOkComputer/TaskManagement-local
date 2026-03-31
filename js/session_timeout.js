//session_timeout.js

const SessionTimeout = {
  // CONFIGURACIÓN
  TIMEOUT_MS: 5 * 60 * 1000, // 5 minutos en milisegundos
  WARNING_BEFORE_MS: 1 * 60 * 1000, // Mostrar advertencia 1 minuto antes
  HEARTBEAT_INTERVAL_MS: 60 * 1000, // Ping al servidor cada 60 segundos (solo si hay actividad)
  CHECK_INTERVAL_MS: 1000, // Revisar inactividad cada 1 segundo

  //ESTADO INTERNO
  _lastActivity: Date.now(),
  _warningShown: false,
  _expired: false,
  _checkTimer: null,
  _heartbeatTimer: null,
  _countdownTimer: null,
  _activitySinceLastHeartbeat: false,
  _modalElement: null,

  // EVENTOS DE ACTIVIDAD
  _activityEvents: [
    "mousedown",
    "mousemove",
    "keydown",
    "scroll",
    "touchstart",
    "click",
    "wheel",
  ],

  //Inicializar el sistema de timeout
  init: function () {
    // No inicializar en la página de login
    if (
      window.location.pathname.includes("index.html") ||
      window.location.pathname.includes("index.php") ||
      window.location.pathname.endsWith("/taskManagement/") ||
      window.location.pathname.endsWith("/taskmanagement/")
    ) {
      return;
    }

    this._createWarningModal();
    this._bindActivityEvents();
    this._startChecking();
    this._startHeartbeat();

    console.log("[SessionTimeout] Inicializado - timeout: 5 minutos");
  },

  //Registrar actividad del usuario
  _registerActivity: function () {
    if (this._expired) return;

    this._lastActivity = Date.now();
    this._activitySinceLastHeartbeat = true;

    // Si el modal de advertencia está visible, ocultarlo
    if (this._warningShown) {
      this._hideWarning();
    }
  },

  //Vincular eventos de actividad del usuario
  _bindActivityEvents: function () {
    const self = this;
    const handler = function () {
      self._registerActivity();
    };

    this._activityEvents.forEach(function (eventName) {
      document.addEventListener(eventName, handler, { passive: true });
    });

    // También detectar actividad en iframes si los hay
    try {
      const iframes = document.querySelectorAll("iframe");
      iframes.forEach(function (iframe) {
        try {
          iframe.contentDocument.addEventListener("mousedown", handler, {
            passive: true,
          });
          iframe.contentDocument.addEventListener("keydown", handler, {
            passive: true,
          });
        } catch (e) {
          // Cross-origin iframe, ignorar
        }
      });
    } catch (e) {}
  },

  //Loop principal - revisa cada segundo si el timeout se alcanzó
  _startChecking: function () {
    const self = this;
    this._checkTimer = setInterval(function () {
      if (self._expired) return;

      const elapsed = Date.now() - self._lastActivity;
      const remaining = self.TIMEOUT_MS - elapsed;

      // Sesión expirada
      if (remaining <= 0) {
        self._handleExpiry();
        return;
      }

      // Mostrar advertencia cuando queda 1 minuto
      if (remaining <= self.WARNING_BEFORE_MS && !self._warningShown) {
        self._showWarning(remaining);
      }

      // Actualizar countdown si el modal está visible
      if (self._warningShown) {
        self._updateCountdown(remaining);
      }
    }, this.CHECK_INTERVAL_MS);
  },

  // Heartbeat: ping al servidor periódicamente SOLO si hubo actividad
  _startHeartbeat: function () {
    const self = this;
    this._heartbeatTimer = setInterval(function () {
      if (self._expired) return;

      if (self._activitySinceLastHeartbeat) {
        self._activitySinceLastHeartbeat = false;
        self._pingServer();
      }
    }, this.HEARTBEAT_INTERVAL_MS);
  },

  //Enviar heartbeat al servidor para renovar la sesión PHP
  _pingServer: function () {
    const self = this;

    // Determinar la ruta base al directorio php/
    const phpPath = this._getPhpBasePath() + "check_session.php";

    fetch(phpPath, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.session_expired) {
          // El servidor ya destruyó la sesión
          self._handleExpiry();
        }
      })
      .catch(function (error) {
        console.warn("[SessionTimeout] Error en heartbeat:", error);
      });
  },

  //Determinar la ruta base al directorio php/ según la ubicación actual

  _getPhpBasePath: function () {
    const path = window.location.pathname;

    // Contar niveles desde taskManagement
    const tmIndex = path.toLowerCase().indexOf("/taskmanagement/");
    if (tmIndex !== -1) {
      const afterTm = path.substring(tmIndex + "/taskmanagement/".length);
      const depth = afterTm.split("/").filter(function (s) {
        return s.length > 0;
      }).length;

      if (depth === 0) return "php/";
      if (depth === 1) return "../php/";
      if (depth === 2) return "../../php/";
      return "../php/"; // fallback
    }

    return "../php/";
  },

  //MODAL DE ADVERTENCIA

  //Crear el modal de advertencia (inyectado dinámicamente)
  _createWarningModal: function () {
    // Evitar duplicados
    if (document.getElementById("sessionTimeoutModal")) {
      this._modalElement = document.getElementById("sessionTimeoutModal");
      return;
    }

    const modalHTML = `
        <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                    <div class="modal-body text-center p-4">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background: #fff3cd; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <i class="mdi mdi-clock-alert-outline" style="font-size: 36px; color: #856404;"></i>
                        </div>
                        <h4 class="mb-2" style="font-weight: 600; color: #333;">Sesión a punto de expirar</h4>
                        <p class="text-muted mb-3">Tu sesión se cerrará por inactividad en:</p>
                        <div id="sessionCountdown" style="font-size: 48px; font-weight: 700; color: #dc3545; font-variant-numeric: tabular-nums; line-height: 1;">
                            01:00
                        </div>
                        <p class="text-muted mt-3 mb-0" style="font-size: 13px;">Haz clic en "Continuar" o realiza cualquier acción para mantener tu sesión.</p>
                    </div>
                    <div class="modal-footer justify-content-center border-0 pt-0 pb-4" style="gap: 12px;">
                        <button type="button" class="btn btn-outline-secondary px-4" id="btnSessionLogout">
                            <i class="mdi mdi-logout me-1"></i>Cerrar sesión
                        </button>
                        <button type="button" class="btn btn-primary px-4" id="btnSessionContinue">
                            <i class="mdi mdi-check me-1"></i>Continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML("beforeend", modalHTML);
    this._modalElement = document.getElementById("sessionTimeoutModal");

    // Botón "Continuar" - registra actividad y cierra modal
    const self = this;
    document
      .getElementById("btnSessionContinue")
      .addEventListener("click", function () {
        self._registerActivity();
        self._pingServer(); // Renovar sesión inmediatamente
      });

    // Botón "Cerrar sesión" - logout inmediato
    document
      .getElementById("btnSessionLogout")
      .addEventListener("click", function () {
        self._doLogout();
      });
  },

  //Mostrar el modal de advertencia
  _showWarning: function (remaining) {
    if (this._warningShown || this._expired) return;
    this._warningShown = true;

    // Usar Bootstrap 5 Modal API
    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
      const modalInstance = bootstrap.Modal.getOrCreateInstance(
        this._modalElement,
      );
      modalInstance.show();
    } else if (typeof $ !== "undefined" && $.fn.modal) {
      // Fallback: jQuery/Bootstrap 4
      $(this._modalElement).modal({ backdrop: "static", keyboard: false });
      $(this._modalElement).modal("show");
    } else {
      // Fallback puro: mostrar directamente
      this._modalElement.style.display = "block";
      this._modalElement.classList.add("show");
    }

    this._updateCountdown(remaining);
  },

  //Ocultar el modal de advertencia
  _hideWarning: function () {
    this._warningShown = false;

    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
      const modalInstance = bootstrap.Modal.getInstance(this._modalElement);
      if (modalInstance) modalInstance.hide();
    } else if (typeof $ !== "undefined" && $.fn.modal) {
      $(this._modalElement).modal("hide");
    } else {
      this._modalElement.style.display = "none";
      this._modalElement.classList.remove("show");
    }
  },

  //Actualizar el contador regresivo en el modal
  _updateCountdown: function (remainingMs) {
    const el = document.getElementById("sessionCountdown");
    if (!el) return;

    const totalSeconds = Math.max(0, Math.ceil(remainingMs / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    const formatted =
      String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");

    el.textContent = formatted;

    // Cambiar color en los últimos 30 segundos
    if (totalSeconds <= 30) {
      el.style.color = "#dc3545";
      el.style.animation = "pulse 1s ease-in-out infinite";
    } else {
      el.style.color = "#e67e22";
      el.style.animation = "none";
    }
  },

  //EXPIRACIÓN Y LOGOUT

  // Manejar expiración de sesión
  _handleExpiry: function () {
    if (this._expired) return;
    this._expired = true;

    // Detener todos los timers
    clearInterval(this._checkTimer);
    clearInterval(this._heartbeatTimer);

    // Ocultar el modal de advertencia si estaba visible
    this._hideWarning();

    // Limpiar localStorage
    localStorage.removeItem("userSession");

    // Mostrar mensaje final y redirigir
    this._showExpiredMessage();
  },

  //Mostrar mensaje de sesión expirada y redirigir
  _showExpiredMessage: function () {
    // Remover modal de advertencia si existe
    if (this._modalElement) {
      this._modalElement.remove();
    }

    const overlayHTML = `
        <div id="sessionExpiredOverlay" style="
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); z-index: 99999;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        ">
            <div style="
                background: white; border-radius: 16px; padding: 40px;
                text-align: center; max-width: 400px; width: 90%;
                box-shadow: 0 25px 80px rgba(0,0,0,0.4);
                animation: fadeInUp 0.3s ease-out;
            ">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: #f8d7da; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="mdi mdi-lock-clock" style="font-size: 36px; color: #721c24;"></i>
                </div>
                <h4 style="font-weight: 600; color: #333; margin-bottom: 8px;">Sesión expirada</h4>
                <p style="color: #666; margin-bottom: 24px;">Tu sesión se cerró por inactividad.<br>Serás redirigido al inicio de sesión.</p>
                <button onclick="window.location.href='../index.html'" class="btn btn-primary px-4 py-2" style="border-radius: 8px;">
                    <i class="mdi mdi-login me-1"></i>Iniciar sesión
                </button>
                <p style="color: #999; font-size: 12px; margin-top: 16px; margin-bottom: 0;" id="redirectCountdownText">
                    Redirigiendo en 5 segundos...
                </p>
            </div>
        </div>
        <style>
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
        </style>`;

    document.body.insertAdjacentHTML("beforeend", overlayHTML);

    // Countdown de redirección
    let countdown = 5;
    const redirectTimer = setInterval(function () {
      countdown--;
      const el = document.getElementById("redirectCountdownText");
      if (el) {
        el.textContent =
          "Redirigiendo en " +
          countdown +
          " segundo" +
          (countdown !== 1 ? "s" : "") +
          "...";
      }
      if (countdown <= 0) {
        clearInterval(redirectTimer);
        window.location.href = "../index.html";
      }
    }, 1000);
  },

  //Logout inmediato (botón del modal)
  _doLogout: function () {
    this._expired = true;
    clearInterval(this._checkTimer);
    clearInterval(this._heartbeatTimer);
    localStorage.removeItem("userSession");
    window.location.href = this._getPhpBasePath() + "logout.php";
  },

  //Método público: obtener segundos restantes
  getRemainingSeconds: function () {
    const elapsed = Date.now() - this._lastActivity;
    return Math.max(0, Math.ceil((this.TIMEOUT_MS - elapsed) / 1000));
  },
};

//AUTO-INICIALIZACIÓN
document.addEventListener("DOMContentLoaded", function () {
  SessionTimeout.init();
});
