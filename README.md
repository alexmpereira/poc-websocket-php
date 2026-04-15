# POC: WebSocket com PHP e JavaScript

Esta Prova de Conceito (POC) demonstra a implementação de um servidor WebSocket utilizando PHP (Ratchet) e um cliente HTML/JS nativo, rodando em um ambiente containerizado via Docker.

## Conceito Central
WebSocket é um protocolo de comunicação stateful que provê canais de comunicação *full-duplex* (bidirecional) sobre uma única conexão TCP de longa duração. Diferente do HTTP tradicional (onde o cliente deve sempre iniciar a requisição), o WebSocket permite que o servidor faça o *push* de dados ativamente para o cliente assim que um evento ocorre.

## Pontos Positivos
* **Baixa Latência:** Como a conexão TCP é mantida aberta, elimina-se o overhead de estabelecer novas conexões HTTP e enviar cabeçalhos repetitivos a cada requisição.
* **Comunicação Bidirecional:** Servidor e cliente podem emitir mensagens de forma independente e simultânea.
* **Eficiência de Recursos:** Excelente alternativa técnica para substituir rotinas custosas de *Long Polling* ou chamadas AJAX repetitivas em curto intervalo de tempo.

## Pontos Negativos
* **Escalabilidade Horizontal Complexa:** Por ser *stateful* (mantém estado na memória do processo), escalar a aplicação adicionando mais servidores exige a implementação de um mecanismo de *Pub/Sub* externo (como Redis) para sincronizar mensagens entre os diferentes nós do WebSocket.
* **Desconexões Silenciosas:** Redes instáveis, proxies ou firewalls agressivos podem derrubar conexões abertas (Idle timeout).
* **Gestão de Infraestrutura:** Requer processos rodando continuamente (Workers/CLI), o que demanda ferramentas como Supervisor, além de configurações específicas no proxy reverso (Nginx/Traefik) para permitir o upgrade do protocolo.

## Cuidados a se tomar (Segurança e Arquitetura)

1. **Autenticação no Handshake:** * A API nativa do JavaScript não permite enviar *Headers* HTTP customizados (como `Authorization: Bearer`) ao instanciar o WebSocket.
   * **Soluções:** Passar um token temporário (Short-lived) via *Query String* (como nesta POC) ou implementar uma arquitetura de "Ticket" via API HTTP prévia. Outra opção é exigir que a primeira mensagem enviada após o `onOpen` seja o payload de autenticação.
2. **Criptografia (WSS):**
   * Em produção, **nunca** trafegue WebSockets em texto plano (`ws://`). Use sempre `wss://` (WebSocket Secure) protegido por TLS/SSL. Além de proteger os dados e os tokens na Query String, isso previne que firewalls intermediários corrompam o tráfego do protocolo.
3. **Heartbeat (Ping/Pong):**
   * Implemente um sistema de Ping/Pong para detectar conexões mortas (zombies) e liberar recursos no servidor de forma proativa. O Ratchet/Nginx fecharão conexões nativamente após um tempo, mas um controle a nível de aplicação é altamente recomendado.
4. **Rate Limiting:**
   * Sem controle de requisições padrão do HTTP, o servidor WebSocket está vulnerável a exaustão de recursos. É crucial implementar limites de mensagens por segundo por cliente para mitigar ataques DDoS ou loops de bugs no frontend.

## 🧪 Roteiro de Testes da POC

Para validar o comportamento do WebSocket e entender suas limitações na prática, execute os cenários abaixo:

### 1. Testes Funcionais (Fluxo Feliz)
* **Conexão e Handshake:** Clique no botão "Conectar (Token Válido)". O status da interface deve mudar para verde e o terminal do backend (`docker logs -f poc_ws_backend`) deve registrar "Nova conexão aceita".
* **Broadcast Bidirecional:** Abra a aplicação em **três abas/janelas diferentes** do navegador. Conecte todas elas. Envie uma mensagem a partir da Aba 1 e verifique se apenas a Aba 2 e a Aba 3 recebem a notificação instantaneamente.
* **Desconexão Limpa:** Feche a Aba 2. O terminal do backend deve registrar imediatamente o evento `onClose` e liberar aquele recurso da memória.

### 2. Testes de Segurança (Autenticação)
* **Token Inválido:** Com o servidor rodando, clique em "Conectar (Token Inválido)". O servidor deve abortar o *handshake* no evento `onOpen`. A interface do cliente deve desconectar imediatamente e exibir a falha.
* **Ausência de Token:** Se possível, edite o `index.html` e remova a *query string* (`?token=`). O backend não deve quebrar (gerar *Fatal Error*); ele deve apenas rejeitar a conexão graciosamente.

### 3. Testes de Resiliência (Chaos e Falhas)
* **Queda do Servidor (Crash):** Com os clientes conectados, pare o container do backend de forma abrupta: `docker stop poc_ws_backend`. Observe a interface do usuário. O evento nativo `onclose` do JavaScript deve ser disparado, atualizando o status visual para desconectado.
* **Perda de Conexão Física:** Desconecte a internet do seu computador ou desligue o Wi-Fi enquanto a página estiver aberta e conectada. Note como o WebSocket nativo demora a perceber a queda (geralmente ocorre o *Idle Timeout*). Isso justifica a necessidade de implementar um "Ping/Pong" no futuro.

### 4. Monitoramento e Infraestrutura
* **Verificação de Memory Leak:** Diferente do PHP-FPM que "morre" a cada requ