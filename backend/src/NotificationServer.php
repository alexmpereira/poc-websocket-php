<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    private $validTokens = ['super-secret-token-123', 'admin-token-456'];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Extrai a query string da URL (ex: ws://localhost:8080?token=...)
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $query);

        $token = $query['token'] ?? null;

        // Validação de Autenticação (Mock)
        if (!in_array($token, $this->validTokens)) {
            echo "Conexão rejeitada: Token inválido.\n";
            $conn->send(json_encode(['type' => 'error', 'message' => 'Não autorizado.']));
            $conn->close();
            return;
        }

        $this->clients->attach($conn);
        echo "Nova conexão aceita! ({$conn->resourceId})\n";

        $conn->send(json_encode(['type' => 'success', 'message' => 'Conectado e autenticado com sucesso!']));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // Exemplo simples de broadcast para os outros clientes conectados
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode([
                    'type' => 'notification',
                    'message' => $data['message'] ?? 'Nova notificação do sistema!'
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Conexão {$conn->resourceId} encerrada.\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Ocorreu um erro: {$e->getMessage()}\n";
        $conn->close();
    }
}