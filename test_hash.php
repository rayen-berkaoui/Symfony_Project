<?php
use Symfony\Component\Dotenv\Dotenv;
require 'vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$hasher = $container->get('security.user_password_hasher');
$repo = $container->get(\App\Repository\UtilisateurRepository::class);

$user = $repo->findOneBy(['email' => 'admin']);
if ($user) {
    $isValid = $hasher->isPasswordValid($user, 'root123');
    echo "Is valid: " . ($isValid ? 'Yes' : 'No') . "\n";
    echo "Current Hash in object: " . $user->getPassword() . "\n";
} else {
    echo "User admin not found in DB\n";
}
