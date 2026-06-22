<?php
declare(strict_types=1);

session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'ControlS\\Portal\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

function env_value(string $key, ?string $default = null): ?string
{
    static $loaded = false;
    static $values = [];

    if (!$loaded) {
        $loaded = true;
        $envPath = __DIR__ . '/.env';
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                    $v = substr($v, 1, -1);
                }
                $values[trim($k)] = $v;
            }
        }
    }

    return $_ENV[$key] ?? $_SERVER[$key] ?? $values[$key] ?? $default;
}

date_default_timezone_set(env_value('TIMEZONE', 'America/Sao_Paulo'));

$config = [
    'app_name' => env_value('APP_NAME', 'Control S Fiscal Hub'),
    'app_env' => env_value('APP_ENV', 'production'),
    'app_debug' => filter_var(env_value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'app_url' => env_value('APP_URL', 'http://localhost:8088'),
    'db_dsn' => env_value('DB_DSN', 'sqlite:' . __DIR__ . '/storage/test.sqlite'),
    'db_user' => env_value('DB_USER', ''),
    'db_pass' => env_value('DB_PASS', ''),
    'auth_enabled' => filter_var(env_value('AUTH_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
    'auth_user' => env_value('AUTH_USER', 'admin'),
    'auth_pass' => env_value('AUTH_PASS', 'admin'),
    'app_key' => env_value('APP_KEY', 'change-this-32-char-key'),
    'default_download_dir' => env_value('DEFAULT_DOWNLOAD_DIR', __DIR__ . '/storage/xmls'),
    'auto_migrate' => filter_var(env_value('AUTO_MIGRATE', 'true'), FILTER_VALIDATE_BOOLEAN),
    'base_path' => __DIR__,

    'sefaz_environment' => env_value('SEFAZ_ENVIRONMENT', '1'),
    'sefaz_uf_author' => env_value('SEFAZ_UF_AUTHOR', '41'),
    'sefaz_max_loops' => (int) env_value('SEFAZ_MAX_LOOPS', '8'),
    'sefaz_timeout' => (int) env_value('SEFAZ_TIMEOUT', '60'),
    'sefaz_user_agent' => env_value('SEFAZ_USER_AGENT', 'ControlSPortalFiscal/3.0'),

    'nfe_distribution_url' => env_value('NFE_DISTRIBUTION_URL', 'https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx'),
    'nfe_distribution_action' => env_value('NFE_DISTRIBUTION_ACTION', 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe/nfeDistDFeInteresse'),
    'nfe_recepcaoevento_url' => env_value('NFE_RECEPCAOEVENTO_URL', 'https://www1.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx'),
    'nfe_recepcaoevento_action' => env_value('NFE_RECEPCAOEVENTO_ACTION', 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4/nfeRecepcaoEventoNF'),

    'cte_distribution_url' => env_value('CTE_DISTRIBUTION_URL', 'https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx'),
    'cte_distribution_action' => env_value('CTE_DISTRIBUTION_ACTION', 'http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe/cteDistDFeInteresse'),

    'nfse_environment' => env_value('NFSE_ENVIRONMENT', 'production'),
    'nfse_base_url' => env_value('NFSE_BASE_URL', 'https://adn.nfse.gov.br'),
    'nfse_distribution_path' => env_value('NFSE_DISTRIBUTION_PATH', '/contribuintes/DFe/{nsu}'),
    'nfse_auth_type' => env_value('NFSE_AUTH_TYPE', 'certificate'),
    'nfse_token' => env_value('NFSE_TOKEN', ''),
    'nfse_page_size' => (int) env_value('NFSE_PAGE_SIZE', '10'),

    'auto_cte_enabled' => env_value('AUTO_CTE_ENABLED', '0'),
    'auto_cte_company_id' => env_value('AUTO_CTE_COMPANY_ID', '0'),
    'auto_cte_interval_minutes' => (int) env_value('AUTO_CTE_INTERVAL_MINUTES', '30'),
    'cte_robot_max_cycles' => (int) env_value('CTE_ROBOT_MAX_CYCLES', '10'),
    'cte_robot_time_limit_seconds' => (int) env_value('CTE_ROBOT_TIME_LIMIT_SECONDS', '240'),
    'auto_nfe_enabled' => env_value('AUTO_NFE_ENABLED', '0'),
    'auto_nfe_company_id' => env_value('AUTO_NFE_COMPANY_ID', '0'),
    'auto_nfe_interval_minutes' => (int) env_value('AUTO_NFE_INTERVAL_MINUTES', '60'),
    'auto_nfe_manifest_science' => env_value('AUTO_NFE_MANIFEST_SCIENCE', '0'),
    'nfe_robot_max_cycles' => (int) env_value('NFE_ROBOT_MAX_CYCLES', '4'),
    'nfe_robot_time_limit_seconds' => (int) env_value('NFE_ROBOT_TIME_LIMIT_SECONDS', '180'),
    'nfe_science_limit_per_run' => (int) env_value('NFE_SCIENCE_LIMIT_PER_RUN', '30'),
    'auto_nfse_enabled' => env_value('AUTO_NFSE_ENABLED', '0'),
    'auto_nfse_company_id' => env_value('AUTO_NFSE_COMPANY_ID', '0'),
    'auto_nfse_interval_minutes' => (int) env_value('AUTO_NFSE_INTERVAL_MINUTES', '60'),
    'auto_nfse_nsu_limit' => (int) env_value('AUTO_NFSE_NSU_LIMIT', '10'),
];

require_once __DIR__ . '/src/helpers.php';

$database = new ControlS\Portal\Database($config);
if ($config['auto_migrate']) {
    $database->ensureSchema();
}
$repo = new ControlS\Portal\Repository($database->pdo());

$runtimeSettingKeys = [
    'default_download_dir',
    'sefaz_environment',
    'sefaz_uf_author',
    'nfe_distribution_url',
    'nfe_distribution_action',
    'nfe_recepcaoevento_url',
    'nfe_recepcaoevento_action',
    'cte_distribution_url',
    'cte_distribution_action',
    'nfse_base_url',
    'nfse_distribution_path',
    'nfse_auth_type',
    'nfse_token',
    'nfse_page_size',
    'auto_cte_enabled',
    'auto_cte_company_id',
    'auto_cte_interval_minutes',
    'cte_robot_max_cycles',
    'cte_robot_time_limit_seconds',
    'auto_nfe_enabled',
    'auto_nfe_company_id',
    'auto_nfe_interval_minutes',
    'auto_nfe_manifest_science',
    'nfe_robot_max_cycles',
    'nfe_robot_time_limit_seconds',
    'nfe_science_limit_per_run',
    'auto_nfse_enabled',
    'auto_nfse_company_id',
    'auto_nfse_interval_minutes',
    'auto_nfse_nsu_limit',
];

foreach ($runtimeSettingKeys as $settingKey) {
    $runtimeValue = $repo->getSetting($settingKey);
    if ($runtimeValue !== null && $runtimeValue !== '') {
        $config[$settingKey] = $runtimeValue;
    }
}

if (($config['cte_distribution_url'] ?? '') === 'https://cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx') {
    $config['cte_distribution_url'] = 'https://www1.cte.fazenda.gov.br/CTeDistribuicaoDFe/CTeDistribuicaoDFe.asmx';
}

if (in_array(($config['nfe_recepcaoevento_url'] ?? ''), [
    'https://www.nfe.fazenda.gov.br/RecepcaoEvento4/RecepcaoEvento4.asmx',
    'https://www1.nfe.fazenda.gov.br/RecepcaoEvento4/RecepcaoEvento4.asmx',
], true)) {
    $config['nfe_recepcaoevento_url'] = 'https://www1.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx';
}

if (($config['nfe_recepcaoevento_action'] ?? '') === 'http://www.portalfiscal.inf.br/nfe/wsdl/RecepcaoEvento4/nfeRecepcaoEvento') {
    $config['nfe_recepcaoevento_action'] = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4/nfeRecepcaoEventoNF';
}

if (($config['nfse_distribution_path'] ?? '') === '/contribuintes/api/v1/distribuicao') {
    $config['nfse_distribution_path'] = '/contribuintes/DFe/{nsu}';
}

$storage = new ControlS\Portal\Storage($config, $repo);
$certificates = new ControlS\Portal\CertificateService($config, $repo, $storage);
$httpClient = new ControlS\Portal\Http\MutualTlsHttpClient($config, $storage, $certificates);
$parser = new ControlS\Portal\XmlParser();
$manifestation = new ControlS\Portal\ManifestationService($config, $repo, $storage, $certificates, $httpClient, $parser);
$collectors = [
    'nfe' => new ControlS\Portal\Collectors\NFeConnector($config, $repo, $storage, $certificates, $httpClient, $parser),
    'cte' => new ControlS\Portal\Collectors\CTeConnector($config, $repo, $storage, $certificates, $httpClient, $parser),
    'nfse' => new ControlS\Portal\Collectors\NFSeNationalConnector($config, $repo, $storage, $certificates, $httpClient, $parser),
];
$jobRunner = new ControlS\Portal\JobRunner($config, $repo, $collectors, $parser, $storage, $certificates, $manifestation);
$periodClosure = new ControlS\Portal\PeriodClosureService($config, $repo, $storage, $collectors, $manifestation);
$auth = new ControlS\Portal\Auth($config);

function app_container(): array
{
    global $config, $repo, $storage, $certificates, $parser, $manifestation, $collectors, $jobRunner, $periodClosure, $auth, $database, $httpClient;
    return compact('config', 'repo', 'storage', 'certificates', 'parser', 'manifestation', 'collectors', 'jobRunner', 'periodClosure', 'auth', 'database', 'httpClient');
}
