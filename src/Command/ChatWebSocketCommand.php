<?php

namespace App\Command;

use App\WebSocket\ChatWebSocket;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chat:websocket',
    description: 'Démarre le serveur WebSocket (Ratchet) pour le salon de chat.',
)]
final class ChatWebSocketCommand extends Command
{
    public function __construct(
        private readonly string $chatWsHost,
        private readonly int $chatWsPort,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port TCP (remplace CHAT_WS_PORT pour ce lancement)');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Adresse d\'écoute (remplace CHAT_WS_HOST)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $host = $input->getOption('host') !== null ? (string) $input->getOption('host') : $this->chatWsHost;
        $port = $input->getOption('port') !== null ? (int) $input->getOption('port') : $this->chatWsPort;

        if ($port < 1 || $port > 65535) {
            $io->error('Port invalide (1–65535).');

            return Command::FAILURE;
        }

        $app = new ChatWebSocket();

        $server = IoServer::factory(
            new HttpServer(
                new WsServer($app)
            ),
            $port,
            $host
        );

        $displayHost = '0.0.0.0' === $host ? '127.0.0.1' : $host;
        $io->success(sprintf('WebSocket chat — écoute sur ws://%s:%d (bind %s:%d). Ctrl+C pour arrêter.', $displayHost, $port, $host, $port));

        $server->run();

        return Command::SUCCESS;
    }
}
