<?php
/**
 * Controller para interface pública
 */

class PublicController {
    private $db;
    private $raffleModel;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->raffleModel = new Raffle($this->db);
    }
    
    /**
     * Página inicial - listar rifas ativas
     */
    public function home() {
        try {
            $raffles = $this->raffleModel->getActiveRaffles();
            $this->showHomePage($raffles);
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Detalhes da rifa
     */
    public function raffle($id) {
        try {
            $raffle = $this->raffleModel->getById($id);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            if (!$this->raffleModel->canBePurchased($id)) {
                throw new Exception("Rifa não disponível para compra");
            }
            
            $stats = $this->raffleModel->getStatistics($id);
            $availableNumbers = $this->raffleModel->getAvailableNumbers($id);
            
            $this->showRafflePage($raffle, $stats, $availableNumbers);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Reservar números
     */
    public function reserve($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /raffle/' . $id);
            exit;
        }
        
        try {
            $data = $this->sanitizeReservationData($_POST);
            $this->validateReservation($id, $data);
            
            // Criar participante
            $participantId = $this->createParticipant($data);
            
            // Obter IDs dos números
            $numberIds = $this->getNumberIds($id, $data['selected_numbers']);
            
            // Criar transação PIX
            $transactionModel = new Transaction($this->db);
            $transactionData = [
                'participant_id' => $participantId,
                'raffle_id' => $id,
                'numbers' => $numberIds,
                'amount' => $this->calculateAmount($id, count($data['selected_numbers']))
            ];
            
            $paymentData = $transactionModel->create($transactionData);
            
            $_SESSION['success'] = "Números reservados! Pague com PIX para confirmar.";
            $_SESSION['payment_data'] = $paymentData;
            
            header('Location: /raffle/' . $id . '/payment/' . $paymentData['payment_id']);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /raffle/' . $id);
            exit;
        }
    }
    
    /**
     * Criar participante
     */
    private function createParticipant($data) {
        // Verificar se participante já existe
        $sql = "SELECT id FROM participants WHERE cpf = ?";
        $stmt = $this->db->query($sql, [$data['participant_cpf']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Atualizar dados se necessário
            $sql = "UPDATE participants SET 
                    name = COALESCE(?, name),
                    email = COALESCE(?, email),
                    phone = COALESCE(?, phone),
                    address = COALESCE(?, address),
                    updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->query($sql, [
                $data['participant_name'],
                $data['participant_email'],
                $data['participant_phone'],
                $data['participant_address'],
                $existing['id']
            ]);
            
            return $existing['id'];
        }
        
        // Criar novo participante
        $sql = "INSERT INTO participants (name, cpf, email, phone, address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $this->db->query($sql, [
            $data['participant_name'],
            $data['participant_cpf'],
            $data['participant_email'],
            $data['participant_phone'],
            $data['participant_address']
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Obter IDs dos números
     */
    private function getNumberIds($raffleId, $numbers) {
        $placeholders = str_repeat('?,', count($numbers) - 1) . '?';
        $params = array_merge([$raffleId], $numbers);
        
        $sql = "SELECT id FROM raffle_numbers 
                WHERE raffle_id = ? AND number IN ($placeholders)";
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($results) !== count($numbers)) {
            throw new Exception("Alguns números não foram encontrados");
        }
        
        return $results;
    }
    
    /**
     * Calcular valor total
     */
    private function calculateAmount($raffleId, $quantity) {
        $sql = "SELECT number_price FROM raffles WHERE id = ?";
        $stmt = $this->db->query($sql, [$raffleId]);
        $raffle = $stmt->fetch();
        
        if (!$raffle) {
            throw new Exception("Rifa não encontrada");
        }
        
        return $raffle['number_price'] * $quantity;
    }
    
    /**
     * Página de pagamento
     */
    public function payment($id, $paymentId) {
        try {
            $transactionModel = new Transaction($this->db);
            $transaction = $transactionModel->getByPaymentId($paymentId);
            
            if (!$transaction) {
                throw new Exception("Transação não encontrada");
            }
            
            $raffle = $this->raffleModel->getById($id);
            if (!$raffle) {
                throw new Exception("Rifa não encontrada");
            }
            
            $this->showPaymentPage($raffle, $transaction);
            
        } catch (Exception $e) {
            $this->showError($e->getMessage());
        }
    }
    
    /**
     * Limpar dados de reserva
     */
    private function sanitizeReservationData($data) {
        return [
            'participant_name' => trim($data['participant_name'] ?? ''),
            'participant_cpf' => preg_replace('/[^0-9]/', '', $data['participant_cpf'] ?? ''),
            'participant_email' => trim($data['participant_email'] ?? ''),
            'participant_phone' => preg_replace('/[^0-9]/', '', $data['participant_phone'] ?? ''),
            'participant_address' => trim($data['participant_address'] ?? ''),
            'selected_numbers' => array_map('intval', $data['selected_numbers'] ?? [])
        ];
    }
    
    /**
     * Validar reserva
     */
    private function validateReservation($raffleId, $data) {
        // Validar campos obrigatórios
        $required = ['participant_name', 'participant_cpf', 'participant_email', 'selected_numbers'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo obrigatório: $field");
            }
        }
        
        // Validar CPF
        if (strlen($data['participant_cpf']) !== 11) {
            throw new Exception("CPF inválido");
        }
        
        // Validar email
        if (!filter_var($data['participant_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido");
        }
        
        // Validar números selecionados
        if (empty($data['selected_numbers'])) {
            throw new Exception("Selecione pelo menos um número");
        }
        
        // Verificar disponibilidade
        $availability = $this->raffleModel->checkNumberAvailability($raffleId, $data['selected_numbers']);
        foreach ($availability as $number => $status) {
            if ($status !== 'available') {
                throw new Exception("Número $number não está disponível");
            }
        }
        
        // Verificar limite por CPF
        $sql = "SELECT COUNT(*) as count FROM raffle_numbers 
                WHERE raffle_id = ? AND participant_cpf = ? AND status = 'paid'";
        $stmt = $this->db->query($sql, [$raffleId, $data['participant_cpf']]);
        $paidCount = $stmt->fetch()['count'];
        
        $raffle = $this->raffleModel->getById($raffleId);
        $maxPerCpf = $raffle['max_numbers_per_cpf'] ?? 10;
        
        if ($paidCount + count($data['selected_numbers']) > $maxPerCpf) {
            throw new Exception("Limite de $maxPerCpf números por CPF excedido");
        }
    }
    
    /**
     * Criar reserva
     */
    private function createReservation($raffleId, $data) {
        $this->db->beginTransaction();
        
        try {
            $reservationHash = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutos
            
            foreach ($data['selected_numbers'] as $number) {
                $sql = "UPDATE raffle_numbers 
                        SET status = 'reserved',
                            participant_name = ?,
                            participant_cpf = ?,
                            participant_email = ?,
                            participant_phone = ?,
                            participant_address = ?,
                            reservation_hash = ?,
                            reservation_expires_at = ?
                        WHERE raffle_id = ? AND number = ?";
                
                $this->db->query($sql, [
                    $data['participant_name'],
                    $data['participant_cpf'],
                    $data['participant_email'],
                    $data['participant_phone'],
                    $data['participant_address'],
                    $reservationHash,
                    $expiresAt,
                    $raffleId,
                    $number
                ]);
            }
            
            $this->db->commit();
            return $reservationHash;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Criar pagamento PIX (simulação)
     */
    private function createPixPayment($raffleId, $data, $reservationHash) {
        $raffle = $this->raffleModel->getById($raffleId);
        $totalAmount = $raffle['number_price'] * count($data['selected_numbers']);
        
        // Simular criação de pagamento no Asaas
        $paymentId = 'pix_' . time() . '_' . rand(1000, 9999);
        $qrCode = '00020126580014BR.GOV.BCB.BRCODE0214' . $paymentId . '52040000530398654041.005802BR5925' . SITE_NAME . '6008BRASILIA62070503***6304' . rand(1000, 9999);
        
        return [
            'hash' => $reservationHash,
            'payment_id' => $paymentId,
            'amount' => $totalAmount,
            'qr_code' => $qrCode,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hora
            'numbers' => $data['selected_numbers'],
            'participant' => [
                'name' => $data['participant_name'],
                'cpf' => $data['participant_cpf'],
                'email' => $data['participant_email']
            ]
        ];
    }
    
    /**
     * Exibir página inicial
     */
    private function showHomePage($raffles) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . SITE_NAME . ' - Rifas Online</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .header { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 20px 0; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .hero { text-align: center; color: white; padding: 60px 0; }
        .hero h1 { font-size: 4em; margin-bottom: 20px; }
        .hero p { font-size: 1.5em; margin-bottom: 30px; opacity: 0.9; }
        .raffles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-top: 40px; }
        .raffle-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); transition: transform 0.3s; }
        .raffle-card:hover { transform: translateY(-10px); }
        .raffle-image { height: 200px; background: linear-gradient(45deg, #3498db, #2980b9); display: flex; align-items: center; justify-content: center; color: white; font-size: 3em; }
        .raffle-content { padding: 30px; }
        .raffle-title { font-size: 1.5em; font-weight: bold; color: #2c3e50; margin-bottom: 15px; }
        .raffle-description { color: #666; margin-bottom: 20px; line-height: 1.6; }
        .raffle-stats { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #3498db; }
        .stat-label { font-size: 0.9em; color: #666; }
        .progress-bar { width: 100%; height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden; margin-bottom: 20px; }
        .progress-fill { height: 100%; background: linear-gradient(45deg, #27ae60, #2ecc71); transition: width 0.3s; }
        .btn { display: inline-block; padding: 15px 30px; border: none; border-radius: 30px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: rgba(39, 174, 96, 0.2); color: white; }
        .alert-error { background: rgba(231, 76, 60, 0.2); color: white; }
        .no-raffles { text-align: center; color: white; padding: 60px 0; }
        .no-raffles h2 { font-size: 2em; margin-bottom: 20px; }
        @media (max-width: 768px) { .hero h1 { font-size: 2.5em; } .raffles-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Rifas Online Seguras e Confiáveis</small>
            </div>
            <a href="/admin" class="btn btn-primary">🔐 Área Admin</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="hero">
            <h1>🎲 Rifas Online</h1>
            <p>Participe e concorra a prêmios incríveis!</p>
        </div>';
        
        if (empty($raffles)) {
            echo '<div class="no-raffles">
                <h2>😔 Nenhuma rifa ativa no momento</h2>
                <p>Volte em breve para novas oportunidades!</p>
            </div>';
        } else {
            echo '<div class="raffles-grid">';
            
            foreach ($raffles as $raffle) {
                $salesPercentage = $raffle['total_count'] > 0 
                    ? round(($raffle['paid_count'] / $raffle['total_count']) * 100, 1) 
                    : 0;
                
                echo '<div class="raffle-card">
                    <div class="raffle-image">🎁</div>
                    <div class="raffle-content">
                        <h3 class="raffle-title">' . htmlspecialchars($raffle['title']) . '</h3>
                        <p class="raffle-description">' . htmlspecialchars(substr($raffle['prize_description'], 0, 100)) . '...</p>
                        
                        <div class="raffle-stats">
                            <div class="stat-item">
                                <div class="stat-value">' . $raffle['paid_count'] . '</div>
                                <div class="stat-label">Vendidos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">' . ($raffle['total_count'] - $raffle['paid_count']) . '</div>
                                <div class="stat-label">Disponíveis</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">R$ ' . number_format($raffle['number_price'], 2, ',', '.') . '</div>
                                <div class="stat-label">Por número</div>
                            </div>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ' . $salesPercentage . '%;"></div>
                        </div>
                        
                        <div style="text-align: center;">
                            <a href="/raffle/' . $raffle['id'] . '" class="btn btn-primary">Ver Detalhes</a>
                        </div>
                    </div>
                </div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>
</body>
</html>';
    }
    
    /**
     * Exibir página da rifa
     */
    private function showRafflePage($raffle, $stats, $availableNumbers) {
        $error = $_SESSION['error'] ?? '';
        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['error'], $_SESSION['success']);
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($raffle['title']) . ' - ' . SITE_NAME . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .header { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 20px 0; }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .raffle-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px; }
        .raffle-info, .purchase-form { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .raffle-image { height: 200px; background: linear-gradient(45deg, #3498db, #2980b9); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 3em; margin-bottom: 20px; }
        .raffle-title { font-size: 2em; font-weight: bold; color: #2c3e50; margin-bottom: 15px; }
        .raffle-description { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #3498db; }
        .stat-label { font-size: 0.9em; color: #666; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
        .numbers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); gap: 5px; margin-bottom: 20px; max-height: 200px; overflow-y: auto; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .number-btn { padding: 8px; border: 2px solid #e1e5e9; background: white; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .number-btn:hover { border-color: #3498db; background: #e3f2fd; }
        .number-btn.selected { background: #3498db; color: white; border-color: #3498db; }
        .number-btn:disabled { background: #e9ecef; color: #6c757d; cursor: not-allowed; }
        .selected-numbers { background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .total-price { font-size: 1.5em; font-weight: bold; color: #27ae60; text-align: center; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 15px 30px; border: none; border-radius: 30px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #2ecc71); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .progress-bar { width: 100%; height: 15px; background: #e9ecef; border-radius: 8px; overflow: hidden; margin-bottom: 15px; }
        .progress-fill { height: 100%; background: linear-gradient(45deg, #27ae60, #2ecc71); transition: width 0.3s; }
        @media (max-width: 768px) { .raffle-container { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>' . htmlspecialchars($raffle['title']) . '</small>
            </div>
            <a href="/" class="btn btn-primary">← Voltar</a>
        </div>
    </div>
    
    <div class="container">
        ' . ($error ? '<div class="alert alert-error">❌ ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . ($success ? '<div class="alert alert-success">✅ ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="raffle-container">
            <div class="raffle-info">
                <div class="raffle-image">🎁</div>
                <h2 class="raffle-title">' . htmlspecialchars($raffle['title']) . '</h2>
                <p class="raffle-description">' . htmlspecialchars($raffle['prize_description']) . '</p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['paid_numbers'] . '</div>
                        <div class="stat-label">Vendidos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['available_numbers'] . '</div>
                        <div class="stat-label">Disponíveis</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">R$ ' . number_format($raffle['number_price'], 2, ',', '.') . '</div>
                        <div class="stat-label">Por número</div>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ' . $stats['sales_percentage'] . '%;"></div>
                </div>
                <p style="text-align: center; color: #666;">' . $stats['sales_percentage'] . '% vendido</p>
                
                <div style="margin-top: 30px;">
                    <h4 style="color: #2c3e50; margin-bottom: 10px;">📋 Regulamento</h4>
                    <p style="color: #666; line-height: 1.6;">' . htmlspecialchars($raffle['regulation'] ?: 'Regulamento não informado') . '</p>
                </div>
            </div>
            
            <div class="purchase-form">
                <h3 style="color: #2c3e50; margin-bottom: 20px;">🎲 Comprar Números</h3>
                
                <form method="POST" action="/raffle/' . $raffle['id'] . '/reserve">
                    <div class="form-group">
                        <label>Selecione os números:</label>
                        <div class="numbers-grid">';
                
                foreach ($availableNumbers as $number) {
                    echo '<button type="button" class="number-btn" onclick="toggleNumber(this, ' . $number . ')">' . $number . '</button>';
                }
                
                echo '</div>
                    </div>
                    
                    <div class="selected-numbers">
                        <strong>Números selecionados:</strong> <span id="selectedCount">0</span>
                    </div>
                    
                    <div class="total-price">
                        Total: R$ <span id="totalPrice">0.00</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="participant_name">Seu Nome *</label>
                        <input type="text" id="participant_name" name="participant_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="participant_cpf">CPF *</label>
                        <input type="text" id="participant_cpf" name="participant_cpf" placeholder="000.000.000-00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="participant_email">E-mail *</label>
                        <input type="email" id="participant_email" name="participant_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="participant_phone">Telefone</label>
                        <input type="tel" id="participant_phone" name="participant_phone" placeholder="(00) 00000-0000">
                    </div>
                    
                    <div class="form-group">
                        <label for="participant_address">Endereço</label>
                        <textarea id="participant_address" name="participant_address" rows="2"></textarea>
                    </div>
                    
                    <input type="hidden" id="selected_numbers" name="selected_numbers" value="">
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;" disabled id="submitBtn">
                        🎲 Reservar e Gerar PIX
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        const selectedNumbers = new Set();
        const pricePerNumber = ' . $raffle['number_price'] . ';
        
        function toggleNumber(btn, number) {
            if (btn.disabled) return;
            
            if (selectedNumbers.has(number)) {
                selectedNumbers.delete(number);
                btn.classList.remove("selected");
            } else {
                selectedNumbers.add(number);
                btn.classList.add("selected");
            }
            
            updateSelection();
        }
        
        function updateSelection() {
            const count = selectedNumbers.size;
            const total = (count * pricePerNumber).toFixed(2);
            
            document.getElementById("selectedCount").textContent = count;
            document.getElementById("totalPrice").textContent = total;
            document.getElementById("selected_numbers").value = Array.from(selectedNumbers).join(",");
            
            const submitBtn = document.getElementById("submitBtn");
            submitBtn.disabled = count === 0;
            
            if (count > 0) {
                submitBtn.textContent = `🎲 Reservar ${count} números - R$ ${total}`;
            } else {
                submitBtn.textContent = "🎲 Reservar e Gerar PIX";
            }
        }
        
        // Máscaras
        document.getElementById("participant_cpf").addEventListener("input", function(e) {
            let value = e.target.value.replace(/\D/g, "");
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 9) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})/, "$1.$2.$3");
            } else if (value.length > 3) {
                value = value.replace(/(\d{3})(\d{3})/, "$1.$2");
            }
            
            e.target.value = value;
        });
        
        document.getElementById("participant_phone").addEventListener("input", function(e) {
            let value = e.target.value.replace(/\D/g, "");
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 10) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (value.length > 6) {
                value = value.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            } else if (value.length > 2) {
                value = value.replace(/(\d{2})(\d{4})/, "($1) $2");
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>';
    }
    
    /**
     * Exibir página de pagamento
     */
    private function showPaymentPage($raffle, $transaction) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - ' . htmlspecialchars($raffle['title']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .header { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 20px 0; }
        .header-content { max-width: 800px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .payment-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .success-icon { font-size: 4em; margin-bottom: 20px; }
        .payment-title { font-size: 2em; color: #2c3e50; margin-bottom: 20px; }
        .qr-code { width: 256px; height: 256px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 10px; margin: 20px auto; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666; }
        .payment-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .payment-info h3 { color: #2c3e50; margin-bottom: 15px; }
        .payment-info p { color: #666; margin: 5px 0; }
        .btn { display: inline-block; padding: 15px 30px; border: none; border-radius: 30px; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .timer { font-size: 1.2em; color: #e74c3c; font-weight: bold; margin: 20px 0; }
        .status-pending { color: #f39c12; }
        .status-confirmed { color: #27ae60; }
        .status-cancelled { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🎯 ' . SITE_NAME . '</h1>
                <small>Pagamento PIX</small>
            </div>
            <a href="/raffle/' . $raffle['id'] . '" class="btn btn-primary">← Voltar</a>
        </div>
    </div>
    
    <div class="container">
        <div class="payment-card">
            <div class="success-icon">💳</div>
            <h2 class="payment-title">Pague com PIX</h2>';
            
            if ($transaction['payment_status'] === 'confirmed') {
                echo '<div class="status-confirmed" style="font-size: 1.5em; margin-bottom: 20px;">
                    ✅ Pagamento Confirmado!
                </div>';
            } elseif ($transaction['payment_status'] === 'cancelled') {
                echo '<div class="status-cancelled" style="font-size: 1.5em; margin-bottom: 20px;">
                    ❌ Pagamento Cancelado
                </div>';
            } else {
                echo '<div class="qr-code">
                    <img src="data:image/png;base64,' . $transaction['qr_code'] . '" alt="QR Code PIX" style="width: 100%; height: 100%; object-fit: contain;">
                </div>';
            }
            
            echo '<div class="payment-info">
                <h3>📋 Detalhes do Pagamento</h3>
                <p><strong>Rifa:</strong> ' . htmlspecialchars($raffle['title']) . '</p>
                <p><strong>Números:</strong> ' . htmlspecialchars($transaction['numbers']) . '</p>
                <p><strong>Comprador:</strong> ' . htmlspecialchars($transaction['participant_name']) . '</p>
                <p><strong>CPF:</strong> ' . htmlspecialchars($transaction['participant_cpf']) . '</p>
                <p><strong>Valor:</strong> R$ ' . number_format($transaction['amount'], 2, ',', '.') . '</p>
                <p><strong>ID Pagamento:</strong> ' . htmlspecialchars($transaction['payment_id']) . '</p>
                <p><strong>Status:</strong> <span class="status-' . $transaction['payment_status'] . '">' . $this->getStatusLabel($transaction['payment_status']) . '</span></p>';
                
                if ($transaction['payment_status'] === 'pending') {
                    echo '<div class="timer">
                        ⏰ Pague em até 1 hora
                    </div>';
                }
                
                echo '</div>';
            
            if ($transaction['payment_status'] === 'pending') {
                echo '<p style="color: #666; margin-bottom: 20px;">
                    Após o pagamento, os números serão confirmados automaticamente.
                </p>
                
                echo '<script>
                    // Verificar status a cada 10 segundos
                    let checkCount = 0;
                    const maxChecks = 60; // 10 minutos máximo
                
                    function checkPaymentStatus() {
                        if (checkCount >= maxChecks) {
                            clearInterval(interval);
                            return;
                        }
                
                        fetch("/api/payment-status/' . $transaction['payment_id'] . '")
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "confirmed") {
                                    location.reload();
                                }
                                checkCount++;
                            })
                            .catch(error => {
                                console.error("Erro ao verificar status:", error);
                                checkCount++;
                            });
                    }
                
                    const interval = setInterval(checkPaymentStatus, 10000);
                </script>';
            }
            
            echo '<a href="/" class="btn btn-primary">🏠 Página Inicial</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Obter label do status
     */
    private function getStatusLabel($status) {
        $labels = [
            'pending' => 'Aguardando Pagamento',
            'confirmed' => 'Pago',
            'cancelled' => 'Cancelado',
            'overdue' => 'Vencido',
            'refunded' => 'Reembolsado'
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Exibir página de erro
     */
    private function showError($message) {
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro - ' . SITE_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .error-icon { font-size: 4em; margin-bottom: 20px; }
        .error-title { color: #e74c3c; margin-bottom: 20px; }
        .error-message { color: #666; margin-bottom: 30px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #3498db; color: white; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h2 class="error-title">Erro</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>
        <a href="/" class="btn btn-primary">Voltar</a>
    </div>
</body>
</html>';
    }
}

?>
