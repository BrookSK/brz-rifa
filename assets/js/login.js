// JavaScript para página de login

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        
        // Validação básica
        if (!email || !password) {
            showError('Preencha todos os campos');
            return;
        }
        
        // Validação de e-mail
        if (!isValidEmail(email)) {
            showError('E-mail inválido');
            return;
        }
        
        // Desabilitar botão e mostrar loading
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
        
        try {
            const response = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    email: email,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                // Login successful
                showSuccess('Login realizado com sucesso!');
                
                // Redirecionar para o dashboard
                setTimeout(() => {
                    window.location.href = '/admin';
                }, 1000);
            } else {
                // Login failed
                showError(data.error || 'Erro ao fazer login');
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('Erro de conexão. Tente novamente.');
        } finally {
            // Reabilitar botão
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }
    });
    
    // Função para validar e-mail
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Função para mostrar erro
    function showError(message) {
        // Remover alertas anteriores
        removeAlerts();
        
        const alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.textContent = message;
        
        // Estilos inline para o alerta
        alert.style.cssText = `
            background: #e74c3c;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        `;
        
        // Inserir antes do formulário
        loginForm.parentNode.insertBefore(alert, loginForm);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    // Função para mostrar sucesso
    function showSuccess(message) {
        // Remover alertas anteriores
        removeAlerts();
        
        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.textContent = message;
        
        // Estilos inline para o alerta
        alert.style.cssText = `
            background: #27ae60;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        `;
        
        // Inserir antes do formulário
        loginForm.parentNode.insertBefore(alert, loginForm);
        
        // Auto-remover após 3 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 3000);
    }
    
    // Função para remover alertas
    function removeAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => alert.remove());
    }
    
    // Adicionar animação CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
    
    // Auto-focus no campo e-mail
    document.getElementById('email').focus();
    
    // Permitir Enter para submeter
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
            loginForm.dispatchEvent(new Event('submit'));
        }
    });
});

// Verificar se já está logado
fetch('/admin', {
    method: 'GET',
    redirect: 'manual'
}).then(response => {
    if (response.status === 200) {
        window.location.href = '/admin';
    }
}).catch(() => {
    // Ignorar erro, usuário não está logado
});
