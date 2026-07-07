<?php
declare(strict_types=1);

namespace ControlS\Portal;

use RuntimeException;

final class CertificateService
{
    public function __construct(private array $config, private Repository $repo, private Storage $storage)
    {
    }

    public function uploadAndActivate(array $file, string $password, int $companyId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do certificado.');
        }
        $company = $this->repo->findCompany($companyId);
        if (!$company) {
            throw new RuntimeException('Empresa não encontrada para vincular o certificado.');
        }

        $path = $this->storage->saveUploadedCertificate($file, (string)$company['cnpj']);
        $data = $this->parsePkcs12($path, $password);
        $this->repo->deactivateCertificates($companyId);

        $payload = [
            'company_id' => $companyId,
            'filename' => $file['name'],
            'storage_path' => $path,
            'password_enc' => $this->encrypt($password),
            'subject_name' => $data['subject_name'],
            'thumbprint' => $data['thumbprint'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'],
            'serial_number' => $data['serial_number'],
            'is_active' => 1,
            'created_at' => date('c'),
        ];

        $id = $this->repo->insertCertificate($payload);
        $this->repo->logAction('certificate_uploaded', 'Certificado '.$file['name'].' ativado para '.$company['company_name'].'. ID '.$id, $companyId);

        return ['id' => $id] + $payload;
    }

    public function active(?int $companyId = null): ?array
    {
        $cert = $this->repo->getActiveCertificate($companyId);
        if ($companyId !== null) {
            $cert = $this->resolveCertificateForCompanyRoot($companyId, $cert);
        }
        if (!$cert) {
            return null;
        }
        try {
            $cert['password'] = $this->decrypt((string)$cert['password_enc']);
            $cert['password_error'] = $cert['root_mismatch_error'] ?? null;
        } catch (RuntimeException $exception) {
            $cert['password'] = null;
            $cert['password_error'] = 'Senha do certificado herdado nao pode ser descriptografada. Reenvie o certificado para esta empresa.';
        }
        return $cert;
    }

    private function resolveCertificateForCompanyRoot(int $companyId, ?array $cert): ?array
    {
        $company = $this->repo->findCompany($companyId);
        if (!$company) {
            return $cert;
        }

        $companyCnpj = preg_replace('/\D+/', '', (string)$company['cnpj']);
        $companyRoot = substr($companyCnpj, 0, 8);
        if ($companyRoot === '') {
            return $cert;
        }

        if ($cert) {
            $certificateCnpj = $this->certificateCnpjFromSubject((string)($cert['subject_name'] ?? ''));
            $certificateRoot = $certificateCnpj !== '' ? substr($certificateCnpj, 0, 8) : substr(preg_replace('/\D+/', '', (string)($cert['company_cnpj'] ?? '')), 0, 8);
            if ($certificateRoot === $companyRoot) {
                return $cert;
            }
        }

        // Filiais podem usar o certificado A1 da matriz quando a raiz do CNPJ e igual.
        // Se houver certificado proprio com raiz divergente, preferimos outro certificado seguro da mesma raiz.
        $fallback = $this->repo->getActiveCertificateByCnpjRoot($companyRoot, $companyId);
        if (!$fallback) {
            if ($cert) {
                $cert['root_mismatch_error'] = 'Certificado ativo possui raiz de CNPJ diferente da empresa consultada. Vincule um certificado da matriz/filial com a mesma raiz do CNPJ.';
            }
            return $cert;
        }

        $fallback['inherited_for_company_id'] = $companyId;
        $fallback['inherited_for_company_cnpj'] = (string)$company['cnpj'];
        return $fallback;
    }

    private function certificateCnpjFromSubject(string $subject): string
    {
        if (preg_match_all('/\d{14}/', $subject, $matches) && !empty($matches[0])) {
            return (string)end($matches[0]);
        }
        if (preg_match_all('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $subject, $matches) && !empty($matches[0])) {
            return preg_replace('/\D+/', '', (string)end($matches[0]));
        }
        return '';
    }

    public function requireActive(?int $companyId = null): array
    {
        $cert = $this->active($companyId);
        if (!$cert) {
            throw new RuntimeException('Nenhum certificado ativo para esta empresa.');
        }
        if (!empty($cert['password_error'])) {
            throw new RuntimeException((string)$cert['password_error']);
        }
        return $cert;
    }

    public function assertMatchesCompany(int $companyId, string $companyCnpj): array
    {
        $cert = $this->requireActive($companyId);
        $companyRoot = substr(preg_replace('/\D+/', '', $companyCnpj), 0, 8);
        $certificateCnpj = $this->certificateCnpjFromSubject((string)($cert['subject_name'] ?? ''));
        $certificateRoot = $certificateCnpj !== ''
            ? substr($certificateCnpj, 0, 8)
            : substr(preg_replace('/\D+/', '', (string)($cert['company_cnpj'] ?? '')), 0, 8);

        if ($companyRoot !== '' && $certificateRoot !== '' && $companyRoot !== $certificateRoot) {
            throw new RuntimeException('Certificado ativo possui raiz de CNPJ diferente da empresa consultada. Vincule um certificado da matriz/filial com a mesma raiz do CNPJ.');
        }

        return $cert;
    }

    public function exportPemBundle(?int $companyId = null): array
    {
        $cert = $this->requireActive($companyId);
        $content = file_get_contents((string)$cert['storage_path']);
        $certs = [];
        if (!openssl_pkcs12_read($content ?: '', $certs, (string)$cert['password'])) {
            throw new RuntimeException('Não foi possível abrir o certificado ativo.');
        }

        $dir = $this->storage->ensureDirectory($this->config['base_path'] . '/storage/runtime/' . preg_replace('/\D+/', '', (string)($cert['company_cnpj'] ?? 'sem-cnpj')));
        $bundlePath = $dir . '/bundle.pem';
        $certPath = $dir . '/cert.pem';
        $keyPath = $dir . '/key.pem';
        file_put_contents($bundlePath, $certs['cert'] . PHP_EOL . $certs['pkey']);
        file_put_contents($certPath, $certs['cert']);
        file_put_contents($keyPath, $certs['pkey']);

        return [
            'bundle' => $bundlePath,
            'cert' => $certPath,
            'key' => $keyPath,
            'password' => (string)$cert['password'],
            'company_id' => (int)$cert['company_id'],
            'company_cnpj' => (string)$cert['company_cnpj'],
        ];
    }

    public function healthCheck(?int $companyId = null): array
    {
        $cert = $this->active($companyId);
        if (!$cert) {
            return [
                'ok' => false,
                'status' => 'missing',
                'message' => 'Sem certificado ativo',
                'valid_to' => null,
                'days_remaining' => null,
            ];
        }

        if (!empty($cert['password_error'])) {
            return [
                'ok' => false,
                'status' => 'invalid_password',
                'message' => (string)$cert['password_error'],
                'valid_to' => $cert['valid_to'] ?? null,
                'days_remaining' => null,
            ];
        }

        $password = (string)$cert['password'];
        $parsed = $this->parsePkcs12((string)$cert['storage_path'], $password);
        $daysRemaining = null;
        if (!empty($parsed['valid_to'])) {
            $daysRemaining = (int)floor((strtotime((string)$parsed['valid_to']) - time()) / 86400);
        }

        $status = 'ok';
        $message = 'Certificado válido.';
        if ($daysRemaining !== null && $daysRemaining < 0) {
            $status = 'expired';
            $message = 'Certificado expirado.';
        } elseif ($daysRemaining !== null && $daysRemaining <= 30) {
            $status = 'warning';
            $message = 'Certificado próximo do vencimento.';
        }

        return [
            'ok' => $status !== 'expired',
            'status' => $status,
            'message' => $message,
            'valid_to' => $parsed['valid_to'],
            'days_remaining' => $daysRemaining,
            'serial_number' => $parsed['serial_number'],
            'thumbprint' => $parsed['thumbprint'],
            'subject_name' => $parsed['subject_name'],
        ];
    }

    private function parsePkcs12(string $path, string $password): array
    {
        $content = file_get_contents($path);
        $certs = [];
        if (!openssl_pkcs12_read($content ?: '', $certs, $password)) {
            throw new RuntimeException('Não foi possível abrir o certificado PFX. Verifique a senha.');
        }

        $parsed = openssl_x509_parse($certs['cert']);
        if (!$parsed) {
            throw new RuntimeException('Não foi possível ler os dados do certificado.');
        }

        return [
            'subject_name' => $parsed['name'] ?? null,
            'thumbprint' => strtoupper(sha1($certs['cert'])),
            'valid_from' => isset($parsed['validFrom_time_t']) ? date('c', (int)$parsed['validFrom_time_t']) : null,
            'valid_to' => isset($parsed['validTo_time_t']) ? date('c', (int)$parsed['validTo_time_t']) : null,
            'serial_number' => $parsed['serialNumberHex'] ?? ($parsed['serialNumber'] ?? null),
        ];
    }

    private function encrypt(string $value): string
    {
        $cipher = 'aes-256-cbc';
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $key = hash('sha256', (string)$this->config['app_key'], true);
        $encrypted = openssl_encrypt($value, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('Falha ao criptografar a senha do certificado.');
        }
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $payload): string
    {
        $cipher = 'aes-256-cbc';
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            throw new RuntimeException('Falha ao descriptografar a senha do certificado.');
        }
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = substr($raw, 0, $ivLength);
        $encrypted = substr($raw, $ivLength);
        $key = hash('sha256', (string)$this->config['app_key'], true);
        $plain = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Falha ao descriptografar a senha do certificado.');
        }
        return $plain;
    }
}
