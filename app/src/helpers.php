<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = compact('type', 'message');
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function csrf_validate(?string $token): bool
{
    return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
}

function format_money(?float $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function format_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return $value;
    }
}

function format_date_short(?string $value): string
{
    if (!$value) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return $value;
    }
}

function base_url(string $path = ''): string
{
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $script = rtrim(str_replace('\\', '/', $script), '/');
    return ($script === '' ? '' : $script) . '/' . ltrim($path, '/');
}

function nl2br_safe(?string $value): string
{
    return nl2br(h($value));
}

function document_status_label(?string $status): string
{
    return [
        'xml_completo' => 'XML completo',
        'apenas_resumo' => 'Apenas resumo',
        'cancelado' => 'Cancelado',
        'pendente_manifestacao' => 'Pendente de manifestação',
        'aguardando_novo_download' => 'Aguardando novo download',
        'ja_existente' => 'Já existente',
        'fora_do_periodo_solicitado' => 'Fora do período solicitado',
        'indisponivel_por_limite_temporal' => 'Indisponível por limitação temporal',
        'nao_encontrado' => 'Não encontrado',
        'erro' => 'Erro',
        'downloaded' => 'XML completo',
        'summary_only' => 'Apenas resumo',
        'awaiting_redownload' => 'Aguardando novo download',
        'imported' => 'XML completo',
    ][(string)$status] ?? (string)$status;
}

function manifestation_status_label(?string $status): string
{
    return [
        'pending' => 'Pendente',
        'not_applicable' => 'Não aplicável',
        'manifested_science' => 'Ciência registrada',
        'manifested_confirm' => 'Confirmação registrada',
        'manifested_unknown' => 'Desconhecimento registrado',
        'manifested_not_realized' => 'Operação não realizada registrada',
        'error_science' => 'Erro na ciência',
        'error_confirm' => 'Erro na confirmação',
        'error_unknown' => 'Erro no desconhecimento',
        'error_not_realized' => 'Erro na operação não realizada',
        'downloaded_in_portal' => 'XML completo no portal',
    ][(string)$status] ?? (string)$status;
}
