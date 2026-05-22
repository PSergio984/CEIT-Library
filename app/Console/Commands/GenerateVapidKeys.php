<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle(): int
    {
        $this->info('Generating VAPID keys...');

        $keys = null;

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Exception $e) {
            $this->warn('Native PHP OpenSSL key generation failed ('.$e->getMessage().'). Trying Node.js fallback...');

            $nodeCmd = 'node -e "const crypto = require(\'crypto\'); const ecdh = crypto.createECDH(\'prime256v1\'); ecdh.generateKeys(); console.log(JSON.stringify({publicKey: ecdh.getPublicKey(\'base64url\'), privateKey: ecdh.getPrivateKey(\'base64url\')}));"';

            $output = [];
            $resultCode = 0;
            exec($nodeCmd, $output, $resultCode);

            if ($resultCode === 0 && ! empty($output)) {
                $decoded = json_decode(implode('', $output), true);
                if (isset($decoded['publicKey']) && isset($decoded['privateKey'])) {
                    $keys = $decoded;
                } else {
                    $this->error('Failed to parse Node.js keygen output.');

                    return Command::FAILURE;
                }
            } else {
                $this->error('Node.js fallback failed. Please ensure Node.js is installed.');

                return Command::FAILURE;
            }
        }

        $publicKey = $keys['publicKey'];
        $privateKey = $keys['privateKey'];

        $this->line("Public Key:  <info>{$publicKey}</info>");
        $this->line("Private Key: <info>{$privateKey}</info>");

        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);

            // Update VAPID_PUBLIC_KEY
            if (str_contains($content, 'VAPID_PUBLIC_KEY=')) {
                $content = preg_replace('/VAPID_PUBLIC_KEY=.*/', 'VAPID_PUBLIC_KEY='.$publicKey, $content);
            } else {
                $content .= "\nVAPID_PUBLIC_KEY=".$publicKey;
            }

            // Update VAPID_PRIVATE_KEY
            if (str_contains($content, 'VAPID_PRIVATE_KEY=')) {
                $content = preg_replace('/VAPID_PRIVATE_KEY=.*/', 'VAPID_PRIVATE_KEY='.$privateKey, $content);
            } else {
                $content .= "\VAPID_PRIVATE_KEY=".$privateKey;
            }

            file_put_contents($envFile, $content);
            $this->info('✓ VAPID keys successfully saved to .env');
        } else {
            $this->warn('.env file not found. Please manually configure VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.');
        }

        return Command::SUCCESS;
    }
}
