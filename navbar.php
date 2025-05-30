<?php
// NO incluir auth.php aquí porque ya se incluye en la página principal
$usuario_actual = obtenerUsuarioActual();
$es_admin = esAdministrador();
$es_rh = esRH();
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #ffffff 0%, #dfdfdf 100%); box-shadow: 0 2px 20px rgba(0,0,0,0.1);">
  <div class="container-fluid px-4">
    
    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center" href="<?php echo $es_admin ? 'index.php' : 'postulaciones.php'; ?>">
      <div class="brand-icon me-2">🤖 RH</div>
      <span class="brand-text"></span>
    </a>
    
    <!-- Toggle button for mobile -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <!-- Main Navigation -->
      <ul class="navbar-nav me-auto">
        
        <?php if ($es_admin): ?>
          <!-- Menú completo para administradores -->
          <li class="nav-item">
            <a class="nav-link nav-link-custom" href="index.php">
              <i class="nav-icon">💼</i>
              <span>Vacantes</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link nav-link-custom" href="cuestionarios.php">
              <i class="nav-icon">📋</i>
              <span>Cuestionarios</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link nav-link-custom" href="sucursales.php">
              <i class="nav-icon">🏪</i>
              <span>Sucursales</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link nav-link-custom" href="areas.php">
              <i class="nav-icon">📁</i>
              <span>Áreas</span>
            </a>
          </li>
        <?php endif; ?>
        
        <!-- Postulaciones - accesible para ambos roles -->
        <li class="nav-item">
          <a class="nav-link nav-link-custom" href="postulaciones.php">
            <i class="nav-icon">📄</i>
            <span>Postulaciones</span>
          </a>
        </li>
        
      </ul>
      
      <!-- User Information -->
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle user-dropdown" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="user-info d-flex align-items-center">
              <!-- Avatar con iniciales -->
              <div class="user-avatar me-2">
                <?php 
                $initials = '';
                $words = explode(' ', $usuario_actual['nombre']);
                foreach($words as $word) {
                    if(!empty($word)) {
                        $initials .= strtoupper(substr($word, 0, 1));
                    }
                }
                echo substr($initials, 0, 2);
                ?>
              </div>
              
              <!-- Info del usuario -->
              <div class="user-details d-none d-md-block">
                <div class="user-name" title="<?php echo htmlspecialchars($usuario_actual['nombre']); ?>">
                  <?php 
                  $nombre = $usuario_actual['nombre'];
                  echo htmlspecialchars(strlen($nombre) > 10 ? substr($nombre, 0, 10) . '...' : $nombre); 
                  ?>
                </div>
                <div class="user-role">
                  <?php echo $es_admin ? 'Administrador' : 'Recursos Humanos'; ?>
                </div>
              </div>
              
              <!-- Chevron -->
              <i class="ms-2 chevron">▾</i>
            </div>
          </a>
          
          <!-- Dropdown Menu -->
          <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
            <!-- Header del usuario -->
            <li class="dropdown-header">
              <div class="dropdown-user-info">
                <div class="dropdown-avatar">
                  <?php echo substr($initials, 0, 2); ?>
                </div>
                <div class="dropdown-details">
                  <div class="dropdown-name"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></div>
                  <div class="dropdown-username">@<?php echo htmlspecialchars($usuario_actual['usuario']); ?></div>
                </div>
              </div>
            </li>
            
            <li><hr class="dropdown-divider"></li>
            
            <!-- Información del usuario -->
            <li class="dropdown-item-text">
              <div class="user-info-details">
                <div class="info-row">
                  <span class="info-label">Usuario:</span>
                  <span class="info-value"><?php echo htmlspecialchars($usuario_actual['usuario']); ?></span>
                </div>
                <div class="info-row">
                  <span class="info-label">Rol:</span>
                  <span class="info-value">
                    <?php if ($es_admin): ?>
                      <span class="role-badge-small admin">👑 Administrador</span>
                    <?php else: ?>
                      <span class="role-badge-small rh">👥 Recursos Humanos</span>
                    <?php endif; ?>
                  </span>
                </div>
              </div>
            </li>
            
            <?php if ($es_admin): ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item dropdown-item-custom" href="usuarios.php">
                  <i class="dropdown-icon">⚙️</i>
                  <span>Administrar Usuarios</span>
                </a>
              </li>
            <?php endif; ?>
            
            <li><hr class="dropdown-divider"></li>
            
            <!-- Cerrar sesión -->
            <li>
              <a class="dropdown-item dropdown-item-custom logout-item" href="logout.php">
                <i class="dropdown-icon">🚪</i>
                <span>Cerrar Sesión</span>
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<style>
:root {
    --navbar-height: 70px;
}

body {
    padding-top: var(--navbar-height);
}

/* Brand */
.navbar-brand {
    font-size: 1.2rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    color: #0e0e0e;
}

.navbar-brand:hover {
    transform: scale(1.05);
    color: white !important;
}

.brand-icon {
    font-size: 1.3rem;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.brand-text {
    letter-spacing: 0.3px;
    font-size: 1.1rem;
}

/* Navigation Links */
.nav-link-custom {
    display: flex;
    align-items: center;
    padding: 0.6rem 0.9rem !important;
    margin: 0 0.2rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: rgba(22, 22, 22, 0.9) !important;
    font-weight: 500;
    font-size: 0.85rem;
}

.nav-link-custom:hover {
    background-color: rgba(255, 255, 255, 0.15);
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.nav-icon {
    font-size: 1rem;
    margin-right: 0.4rem;
    width: 18px;
    text-align: center;
}

/* User Dropdown */
.user-dropdown {
    padding: 0.4rem 0.6rem !important;
    border-radius: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: white !important;
}

.user-dropdown:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white !important;
}

.user-info {
    gap: 0.4rem;
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    color: #ed9a0c;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    border: 2px solid rgba(255,255,255,0.2);
}

.user-details {
    text-align: left;
    line-height: 1.1;
    max-width: 140px;
}

.user-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e1e1e;
    margin-bottom: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: help;
}

.user-role {
    font-size: 0.65rem;
    color: rgb(52 52 52 / 80%);
    font-weight: 400;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chevron {
    font-size: 0.7rem;
    opacity: 0.7;
    transition: transform 0.3s ease;
}

.dropdown-toggle[aria-expanded="true"] .chevron {
    transform: rotate(180deg);
}

/* Dropdown Menu */
.user-dropdown-menu {
    border: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border-radius: 16px;
    min-width: 280px;
    padding: 0.5rem;
    margin-top: 0.5rem;
    background: white;
}

/* Dropdown Header */
.dropdown-header {
    padding: 0.9rem;
    background: linear-gradient(135deg, #ed9a0c 0%, #ff6b35 100%);
    border-radius: 12px;
    margin-bottom: 0.5rem;
}

.dropdown-user-info {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.dropdown-avatar {
    width: 40px;
    height: 40px;
    background: white;
    color: #ed9a0c;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.dropdown-details {
    flex: 1;
    color: white;
}

.dropdown-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 2px;
}

.dropdown-username {
    font-size: 0.75rem;
    opacity: 0.9;
}

/* User Info Details */
.user-info-details {
    padding: 0.4rem 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.4rem;
    font-size: 0.8rem;
}

.info-label {
    color: #6c757d;
    font-weight: 500;
}

.info-value {
    color: #333;
    font-weight: 600;
}

.role-badge-small {
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    font-weight: 600;
}

.role-badge-small.admin {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.role-badge-small.rh {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* Dropdown Items */
.dropdown-item-custom {
    display: flex;
    align-items: center;
    padding: 0.65rem 0.9rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #333;
    font-weight: 500;
    margin-bottom: 2px;
    font-size: 0.8rem;
}

.dropdown-item-custom:hover {
    background-color: #f8f9fa;
    color: #333;
    transform: translateX(3px);
}

.dropdown-icon {
    width: 18px;
    margin-right: 0.6rem;
    text-align: center;
    font-size: 0.9rem;
}

.logout-item {
    color: #dc3545 !important;
}

.logout-item:hover {
    background-color: #f8d7da !important;
    color: #dc3545 !important;
}

/* Divider */
.dropdown-divider {
    margin: 0.5rem 0;
    opacity: 0.1;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .user-details {
        display: none !important;
    }
    
    .user-dropdown-menu {
        min-width: 250px;
    }
    
    .nav-link-custom span {
        margin-left: 0.25rem;
    }
}

/* Active Page Indicator */
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$page_indicators = [
    'index.php' => '.nav-link-custom[href="index.php"]',
    'cuestionarios.php' => '.nav-link-custom[href="cuestionarios.php"]',
    'sucursales.php' => '.nav-link-custom[href="sucursales.php"]',
    'areas.php' => '.nav-link-custom[href="areas.php"]',
    'postulaciones.php' => '.nav-link-custom[href="postulaciones.php"]',
    'usuarios.php' => '.dropdown-item-custom[href="usuarios.php"]'
];

if (isset($page_indicators[$current_page])):
?>
<?php echo $page_indicators[$current_page]; ?> {
    background-color: rgba(255, 255, 255, 0.2) !important;
    color: black !important;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

<?php if ($current_page === 'usuarios.php'): ?>
.dropdown-item-custom[href="usuarios.php"] {
    background-color: #e3f2fd !important;
    color: #1976d2 !important;
}
<?php endif; ?>

<?php endif; ?>

/* Smooth animations */
.navbar-collapse {
    transition: all 0.3s ease;
}

.navbar-toggler {
    padding: 0.5rem;
    border-radius: 8px;
}

.navbar-toggler:focus {
    box-shadow: none;
    border-color: rgba(255,255,255,0.3);
}
</style>