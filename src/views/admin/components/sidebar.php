<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h2><?= Config::SITE_NAME ?></h2>
            <span class="subtitle">Painel Administrativo</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="/admin" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-group">
                <div class="nav-group-title">Rifas</div>
                <ul class="nav-sublist">
                    <li class="nav-item">
                        <a href="/admin/raffles" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/raffles') === 0 ? 'active' : '' ?>">
                            <span class="nav-icon">🎯</span>
                            <span class="nav-text">Minhas Rifas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/raffles/create" class="nav-link">
                            <span class="nav-icon">➕</span>
                            <span class="nav-text">Nova Rifa</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="nav-group">
                <div class="nav-group-title">Relatórios</div>
                <ul class="nav-sublist">
                    <li class="nav-item">
                        <a href="/admin/reports" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/reports') === 0 ? 'active' : '' ?>">
                            <span class="nav-icon">📈</span>
                            <span class="nav-text">Relatórios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/monitoring" class="nav-link">
                            <span class="nav-icon">👁️</span>
                            <span class="nav-text">Monitoramento</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <?php if ($_SESSION['user_profile'] === 'admin'): ?>
                <li class="nav-group">
                    <div class="nav-group-title">Configurações</div>
                    <ul class="nav-sublist">
                        <li class="nav-item">
                            <a href="/admin/setup" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/setup' ? 'active' : '' ?>">
                                <span class="nav-icon">⚙️</span>
                                <span class="nav-text">Configuração</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/policies" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/policies' ? 'active' : '' ?>">
                                <span class="nav-icon">📋</span>
                                <span class="nav-text">Políticas</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/integrations" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/integrations' ? 'active' : '' ?>">
                                <span class="nav-icon">🔗</span>
                                <span class="nav-text">Integrações</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/alerts" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/alerts' ? 'active' : '' ?>">
                                <span class="nav-icon">🚨</span>
                                <span class="nav-text">Alertas</span>
                                <span class="nav-badge" id="alerts-count">0</span>
                        </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-group">
                    <div class="nav-group-title">Usuários</div>
                    <ul class="nav-sublist">
                        <li class="nav-item">
                            <a href="/admin/users" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/users' ? 'active' : '' ?>">
                                <span class="nav-icon">👥</span>
                                <span class="nav-text">Usuários</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/profile" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/profile' ? 'active' : '' ?>">
                                <span class="nav-icon">👤</span>
                                <span class="nav-text">Meu Perfil</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php else: ?>
                <li class="nav-group">
                    <div class="nav-group-title">Conta</div>
                    <ul class="nav-sublist">
                        <li class="nav-item">
                            <a href="/admin/profile" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin/profile' ? 'active' : '' ?>">
                                <span class="nav-icon">👤</span>
                                <span class="nav-text">Meu Perfil</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- User Menu -->
    <div class="sidebar-footer">
        <div class="user-menu">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                    <div class="user-role"><?= ucfirst($_SESSION['user_profile']) ?></div>
                </div>
            </div>
            
            <div class="user-actions">
                <button class="btn btn-sm btn-outline" onclick="toggleUserMenu()">
                    <span>⚙️</span>
                </button>
            </div>
        </div>
        
        <div class="user-dropdown" id="user-dropdown">
            <ul>
                <li><a href="/admin/profile">Meu Perfil</a></li>
                <li><a href="#" onclick="changePassword()">Alterar Senha</a></li>
                <?php if ($_SESSION['user_profile'] === 'admin'): ?>
                    <li><a href="/admin/setup">Configurações</a></li>
                <?php endif; ?>
                <li class="divider"></li>
                <li><a href="/logout" class="text-danger">Sair</a></li>
            </ul>
        </div>
    </div>
</aside>

<!-- User Menu Script -->
<script>
function toggleUserMenu() {
    const dropdown = document.getElementById('user-dropdown');
    dropdown.classList.toggle('show');
}

function changePassword() {
    // Implementar modal de alteração de senha
    if (confirm('Deseja alterar sua senha?')) {
        window.location.href = '/admin/profile#change-password';
    }
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('user-dropdown');
    const userMenu = document.querySelector('.user-menu');
    
    if (!userMenu.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Carregar contador de alertas
function loadAlertsCount() {
    fetch('/admin/api/alerts/count')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('alerts-count');
            if (badge && data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            }
        })
        .catch(error => console.error('Error loading alerts count:', error));
}

// Carregar ao iniciar
document.addEventListener('DOMContentLoaded', loadAlertsCount);

// Atualizar periodicamente
setInterval(loadAlertsCount, 30000); // 30 segundos
</script>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: #2c3e50;
    color: white;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid #34495e;
}

.logo h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.subtitle {
    font-size: 0.875rem;
    color: #95a5a6;
}

.sidebar-nav {
    padding: 1rem 0;
}

.nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-group-title {
    padding: 0.75rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #95a5a6;
    letter-spacing: 0.05em;
}

.nav-sublist {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: #ecf0f1;
    text-decoration: none;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: #34495e;
    color: white;
}

.nav-link.active {
    background: #3498db;
    color: white;
}

.nav-icon {
    width: 20px;
    margin-right: 0.75rem;
    text-align: center;
}

.nav-text {
    flex: 1;
}

.nav-badge {
    background: #e74c3c;
    color: white;
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    min-width: 20px;
    text-align: center;
    margin-left: 0.5rem;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1rem 1.5rem;
    border-top: 1px solid #34495e;
    background: #34495e;
}

.user-menu {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
}

.user-info {
    display: flex;
    align-items: center;
    flex: 1;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 0.75rem;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.75rem;
    color: #95a5a6;
}

.user-dropdown {
    position: absolute;
    bottom: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    display: none;
    z-index: 1001;
}

.user-dropdown.show {
    display: block;
}

.user-dropdown ul {
    list-style: none;
    margin: 0;
    padding: 0.5rem 0;
}

.user-dropdown li {
    padding: 0;
}

.user-dropdown a {
    display: block;
    padding: 0.5rem 1rem;
    color: #2c3e50;
    text-decoration: none;
    transition: background 0.3s ease;
}

.user-dropdown a:hover {
    background: #f8f9fa;
}

.user-dropdown .text-danger {
    color: #e74c3c !important;
}

.user-dropdown .divider {
    height: 1px;
    background: #e1e5e9;
    margin: 0.5rem 0;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
}
</style>
