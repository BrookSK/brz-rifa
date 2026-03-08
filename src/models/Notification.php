<?php
/**
 * Modelo para gerenciar notificações e alertas
 */

class Notification {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Criar notificação
     */
    public function create($type, $title, $message, $recipientId, $recipientType, $data = null, $priority = 'normal', $channels = ['email']) {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO notifications (
                type, title, message, recipient_id, recipient_type, 
                data, priority, channels, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $this->db->query($sql, [
                $type,
                $title,
                $message,
                $recipientId,
                $recipientType,
                $data ? json_encode($data) : null,
                $priority,
                json_encode($channels)
            ]);
            
            $notificationId = $this->db->getConnection()->lastInsertId();
            
            // Processar canais
            $this->processChannels($notificationId, $channels);
            
            $this->db->commit();
            
            return $notificationId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Processar canais de notificação
     */
    private function processChannels($notificationId, $channels) {
        foreach ($channels as $channel) {
            $sql = "INSERT INTO notification_channels (notification_id, channel, status, created_at) 
                    VALUES (?, ?, 'pending', NOW())";
            
            $this->db->query($sql, [$notificationId, $channel]);
        }
    }
    
    /**
     * Enviar notificação por email
     */
    public function sendEmail($notificationId) {
        $sql = "SELECT n.*, nc.channel, nc.id as channel_id
                FROM notifications n
                JOIN notification_channels nc ON n.id = nc.notification_id
                WHERE n.id = ? AND nc.channel = 'email' AND nc.status = 'pending'";
        
        $stmt = $this->db->query($sql, [$notificationId]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            throw new Exception("Notificação de email não encontrada ou já processada");
        }
        
        // Obter destinatário
        $recipient = $this->getRecipient($notification['recipient_id'], $notification['recipient_type']);
        
        if (!$recipient) {
            throw new Exception("Destinatário não encontrado");
        }
        
        // Enviar email
        $result = $this->sendEmailMessage($recipient['email'], $notification['title'], $notification['message'], $notification['data']);
        
        // Atualizar status
        $status = $result['success'] ? 'sent' : 'failed';
        $this->updateChannelStatus($notification['channel_id'], $status, $result['error'] ?? null);
        
        return $result;
    }
    
    /**
     * Enviar notificação por SMS
     */
    public function sendSMS($notificationId) {
        $sql = "SELECT n.*, nc.channel, nc.id as channel_id
                FROM notifications n
                JOIN notification_channels nc ON n.id = nc.notification_id
                WHERE n.id = ? AND nc.channel = 'sms' AND nc.status = 'pending'";
        
        $stmt = $this->db->query($sql, [$notificationId]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            throw new Exception("Notificação de SMS não encontrada ou já processada");
        }
        
        // Obter destinatário
        $recipient = $this->getRecipient($notification['recipient_id'], $notification['recipient_type']);
        
        if (!$recipient || !$recipient['phone']) {
            throw new Exception("Destinatário não encontrado ou sem telefone");
        }
        
        // Enviar SMS
        $result = $this->sendSMSMessage($recipient['phone'], $notification['message']);
        
        // Atualizar status
        $status = $result['success'] ? 'sent' : 'failed';
        $this->updateChannelStatus($notification['channel_id'], $status, $result['error'] ?? null);
        
        return $result;
    }
    
    /**
     * Enviar notificação push
     */
    public function sendPush($notificationId) {
        $sql = "SELECT n.*, nc.channel, nc.id as channel_id
                FROM notifications n
                JOIN notification_channels nc ON n.id = nc.notification_id
                WHERE n.id = ? AND nc.channel = 'push' AND nc.status = 'pending'";
        
        $stmt = $this->db->query($sql, [$notificationId]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            throw new Exception("Notificação push não encontrada ou já processada");
        }
        
        // Obter destinatário
        $recipient = $this->getRecipient($notification['recipient_id'], $notification['recipient_type']);
        
        if (!$recipient) {
            throw new Exception("Destinatário não encontrado");
        }
        
        // Enviar push notification
        $result = $this->sendPushMessage($recipient, $notification['title'], $notification['message'], $notification['data']);
        
        // Atualizar status
        $status = $result['success'] ? 'sent' : 'failed';
        $this->updateChannelStatus($notification['channel_id'], $status, $result['error'] ?? null);
        
        return $result;
    }
    
    /**
     * Obter destinatário
     */
    private function getRecipient($recipientId, $recipientType) {
        switch ($recipientType) {
            case 'participant':
                $sql = "SELECT id, name, email, phone FROM participants WHERE id = ?";
                break;
            case 'user':
                $sql = "SELECT id, name, email, phone FROM users WHERE id = ?";
                break;
            case 'admin':
                $sql = "SELECT id, name, email, phone FROM users WHERE profile = 'admin'";
                break;
            default:
                return null;
        }
        
        $stmt = $this->db->query($sql, [$recipientId]);
        return $stmt->fetch();
    }
    
    /**
     * Enviar mensagem de email
     */
    private function sendEmailMessage($to, $subject, $message, $data = null) {
        // Simulação de envio de email
        // Em produção, integrar com serviço real (SendGrid, Mailgun, etc.)
        
        try {
            // Headers
            $headers = [
                'From: ' . SITE_NAME . ' <noreply@' . parse_url(SITE_URL, PHP_URL_HOST) . '>',
                'Reply-To: contato@' . parse_url(SITE_URL, PHP_URL_HOST),
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];
            
            // Template HTML
            $htmlMessage = $this->getEmailTemplate($subject, $message, $data);
            
            // Enviar (simulado)
            error_log("Email enviado para: $to - Assunto: $subject");
            
            return [
                'success' => true,
                'message' => 'Email enviado com sucesso',
                'sent_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensagem SMS
     */
    private function sendSMSMessage($phone, $message) {
        // Simulação de envio de SMS
        // Em produção, integrar com serviço real (Twilio, Vonage, etc.)
        
        try {
            // Limpar número de telefone
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Simular envio
            error_log("SMS enviado para: $phone - Mensagem: $message");
            
            return [
                'success' => true,
                'message' => 'SMS enviado com sucesso',
                'sent_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar push notification
     */
    private function sendPushMessage($recipient, $title, $message, $data = null) {
        // Simulação de envio de push notification
        // Em produção, integrar com Firebase Cloud Messaging, OneSignal, etc.
        
        try {
            // Simular envio
            error_log("Push notification enviado para: {$recipient['name']} - Título: $title");
            
            return [
                'success' => true,
                'message' => 'Push notification enviado com sucesso',
                'sent_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter template de email
     */
    private function getEmailTemplate($subject, $message, $data = null) {
        $template = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .data-table th, .data-table td { padding: 10px; border: 1px solid #e9ecef; text-align: left; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 ' . SITE_NAME . '</h1>
        </div>
        <div class="content">
            <h2>' . htmlspecialchars($subject) . '</h2>
            <div style="line-height: 1.6; margin: 20px 0;">
                ' . nl2br(htmlspecialchars($message)) . '
            </div>';
            
        if ($data) {
            $template .= '<div class="data-table">
                <h3>📋 Detalhes:</h3>
                <table>';
            
            foreach ($data as $key => $value) {
                $template .= '<tr>
                    <th>' . htmlspecialchars(ucfirst($key)) . '</th>
                    <td>' . htmlspecialchars($value) . '</td>
                </tr>';
            }
            
            $template .= '</table>
            </div>';
        }
        
        $template .= '</div>
        <div class="footer">
            <p>Este email foi enviado automaticamente pelo sistema ' . SITE_NAME . '</p>
            <p>Se você não solicitou esta comunicação, por favor ignore este email.</p>
            <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>';
        
        return $template;
    }
    
    /**
     * Atualizar status do canal
     */
    private function updateChannelStatus($channelId, $status, $error = null) {
        $sql = "UPDATE notification_channels 
                SET status = ?, error_message = ?, sent_at = NOW() 
                WHERE id = ?";
        
        $this->db->query($sql, [$status, $error, $channelId]);
    }
    
    /**
     * Listar notificações
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT n.*, 
                       (SELECT COUNT(*) FROM notification_channels nc WHERE nc.notification_id = n.id AND nc.status = 'sent') as sent_count,
                       (SELECT COUNT(*) FROM notification_channels nc WHERE nc.notification_id = n.id AND nc.status = 'failed') as failed_count
                FROM notifications n";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['type'])) {
            $where[] = "n.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "n.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $where[] = "n.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "n.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "n.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obter notificação por ID
     */
    public function getById($id) {
        $sql = "SELECT n.*, 
                       (SELECT COUNT(*) FROM notification_channels nc WHERE nc.notification_id = n.id AND nc.status = 'sent') as sent_count,
                       (SELECT COUNT(*) FROM notification_channels nc WHERE nc.notification_id = n.id AND nc.status = 'failed') as failed_count
                FROM notifications n
                WHERE n.id = ?";
        
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Obter canais da notificação
     */
    public function getChannels($notificationId) {
        $sql = "SELECT * FROM notification_channels WHERE notification_id = ? ORDER BY created_at";
        $stmt = $this->db->query($sql, [$notificationId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Reenviar notificação
     */
    public function resend($notificationId, $channels = null) {
        $notification = $this->getById($notificationId);
        if (!$notification) {
            throw new Exception("Notificação não encontrada");
        }
        
        // Resetar status dos canais
        if ($channels) {
            foreach ($channels as $channel) {
                $sql = "UPDATE notification_channels 
                        SET status = 'pending', error_message = NULL, sent_at = NULL 
                        WHERE notification_id = ? AND channel = ?";
                
                $this->db->query($sql, [$notificationId, $channel]);
            }
        } else {
            $sql = "UPDATE notification_channels 
                    SET status = 'pending', error_message = NULL, sent_at = NULL 
                    WHERE notification_id = ?";
            
            $this->db->query($sql, [$notificationId]);
        }
        
        // Processar canais novamente
        $channels = $channels ?? json_decode($notification['channels'], true);
        foreach ($channels as $channel) {
            $this->processChannel($notificationId, $channel);
        }
        
        return true;
    }
    
    /**
     * Processar canal específico
     */
    private function processChannel($notificationId, $channel) {
        switch ($channel) {
            case 'email':
                $this->sendEmail($notificationId);
                break;
            case 'sms':
                $this->sendSMS($notificationId);
                break;
            case 'push':
                $this->sendPush($notificationId);
                break;
        }
    }
    
    /**
     * Criar notificação de pagamento confirmado
     */
    public function createPaymentConfirmed($transactionId, $participantId, $raffleTitle, $numbers) {
        $participant = $this->getRecipient($participantId, 'participant');
        
        $data = [
            'transaction_id' => $transactionId,
            'raffle_title' => $raffleTitle,
            'numbers' => implode(', ', $numbers),
            'participant_name' => $participant['name']
        ];
        
        return $this->create(
            'payment_confirmed',
            '🎉 Pagamento Confirmado!',
            "Parabéns! Seu pagamento foi confirmado e seus números da rifa são agora oficiais.",
            $participantId,
            'participant',
            $data,
            'high',
            ['email']
        );
    }
    
    /**
     * Criar notificação de rifa sorteada
     */
    public function createRaffleDrawn($raffleId, $raffleTitle, $winnerNumber, $winnerName) {
        // Notificar todos os participantes
        $sql = "SELECT DISTINCT participant_id FROM raffle_numbers WHERE raffle_id = ? AND status = 'paid'";
        $stmt = $this->db->query($sql, [$raffleId]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($participants as $participantId) {
            $participant = $this->getRecipient($participantId, 'participant');
            
            $data = [
                'raffle_title' => $raffleTitle,
                'winner_number' => $winnerNumber,
                'winner_name' => $winnerName,
                'participant_name' => $participant['name']
            ];
            
            $isWinner = $participant['name'] === $winnerName;
            
            $this->create(
                'raffle_drawn',
                $isWinner ? '🏆 Parabéns! Você ganhou!' : '🎯 Sorteio Realizado!',
                $isWinner 
                    ? "Parabéns! Você ganhou a rifa '{$raffleTitle}' com o número {$winnerNumber}!"
                    : "A rifa '{$raffleTitle}' foi sorteada. O número vencedor foi {$winnerNumber}.",
                $participantId,
                'participant',
                $data,
                $isWinner ? 'high' : 'normal',
                ['email']
            );
        }
        
        // Notificar administradores
        $this->create(
            'raffle_drawn_admin',
            '🎯 Sorteio Realizado',
            "A rifa '{$raffleTitle}' foi sorteada. Vencedor: {$winnerName} - Número: {$winnerNumber}",
            null,
            'admin',
            [
                'raffle_title' => $raffleTitle,
                'winner_number' => $winnerNumber,
                'winner_name' => $winnerName
            ],
            'normal',
            ['email']
        );
    }
    
    /**
     * Criar notificação de rifa encerrada
     */
    public function createRaffleClosed($raffleId, $raffleTitle) {
        // Notificar administradores
        $this->create(
            'raffle_closed',
            '⏰ Rifa Encerrada',
            "A rifa '{$raffleTitle}' foi encerrada e as vendas foram finalizadas.",
            null,
            'admin',
            [
                'raffle_title' => $raffleTitle,
                'raffle_id' => $raffleId
            ],
            'normal',
            ['email']
        );
    }
    
    /**
     * Criar alerta de segurança
     */
    public function createSecurityAlert($type, $message, $userId = null, $data = null) {
        return $this->create(
            'security_alert',
            '🔐 Alerta de Segurança',
            $message,
            $userId,
            'admin',
            $data,
            'high',
            ['email', 'push']
        );
    }
    
    /**
     * Criar alerta de sistema
     */
    public function createSystemAlert($type, $message, $data = null) {
        return $this->create(
            'system_alert',
            '⚠️ Alerta do Sistema',
            $message,
            null,
            'admin',
            $data,
            'normal',
            ['email']
        );
    }
    
    /**
     * Obter estatísticas de notificações
     */
    public function getStatistics($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    COUNT(*) as total_notifications,
                    COUNT(DISTINCT recipient_id) as unique_recipients,
                    COUNT(DISTINCT type) as unique_types,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
                    SUM(CASE WHEN priority = 'normal' THEN 1 ELSE 0 END) as normal_priority,
                    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority
                FROM notifications";
        
        $params = [];
        
        if ($dateFrom) {
            $sql .= " WHERE created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= ($dateFrom ? " AND" : " WHERE") . " created_at <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Limpar notificações antigas
     */
    public function cleanup($days = 90) {
        $sql = "DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status IN ('sent', 'failed')";
        
        $stmt = $this->db->query($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Obter notificações pendentes
     */
    public function getPendingNotifications() {
        $sql = "SELECT n.* FROM notifications n
                JOIN notification_channels nc ON n.id = nc.notification_id
                WHERE nc.status = 'pending'
                GROUP BY n.id
                ORDER BY n.priority DESC, n.created_at ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Processar fila de notificações
     */
    public function processQueue($limit = 10) {
        $notifications = $this->getPendingNotifications();
        $processed = 0;
        
        foreach ($notifications as $notification) {
            if ($processed >= $limit) break;
            
            try {
                $channels = json_decode($notification['channels'], true);
                foreach ($channels as $channel) {
                    $this->processChannel($notification['id'], $channel);
                }
                
                // Atualizar status da notificação
                $sql = "UPDATE notifications SET status = 'processed', processed_at = NOW() WHERE id = ?";
                $this->db->query($sql, [$notification['id']]);
                
                $processed++;
                
            } catch (Exception $e) {
                error_log("Erro ao processar notificação {$notification['id']}: " . $e->getMessage());
            }
        }
        
        return $processed;
    }
}

?>
