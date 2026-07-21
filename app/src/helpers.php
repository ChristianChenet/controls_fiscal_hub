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
        'denegado' => 'Denegado/Sem manifestação aplicável',
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

function compact_multi_picker(string $label, string $name, array $options, mixed $selected = [], string $hint = ''): string
{
    $selectedValues = is_array($selected) ? $selected : (($selected === '' || $selected === null) ? [] : [$selected]);
    $selectedValues = array_values(array_unique(array_map('strval', $selectedValues)));
    $selectedSet = array_fill_keys($selectedValues, true);
    $id = 'multi_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $name) . '_' . substr(hash('sha1', $label . $name), 0, 8);
    $count = count($selectedValues);
    $caption = $count === 0 ? 'Todas' : ($count === 1 ? '1 selecionada' : $count . ' selecionadas');
    $html = '<label class="compact-multi-field">' . h($label) . ($hint !== '' ? ' ' . $hint : '');
    $html .= '<button type="button" class="compact-multi-trigger" data-multi-open="' . h($id) . '"><span data-multi-caption>' . h($caption) . '</span></button>';
    $html .= '<span class="compact-multi-hidden" data-multi-hidden="' . h($id) . '" data-multi-name="' . h($name) . '">';
    foreach ($selectedValues as $value) {
        $html .= '<input type="hidden" name="' . h($name) . '[]" value="' . h($value) . '">';
    }
    $html .= '</span></label>';
    $html .= '<div class="modal-backdrop is-hidden compact-multi-modal" data-multi-modal="' . h($id) . '">';
    $html .= '<div class="modal-panel compact-multi-panel">';
    $html .= '<div class="modal-header"><div><h2>' . h($label) . '</h2><small>Selecione uma ou mais opções para filtrar.</small></div><button type="button" class="modal-close" data-multi-close="' . h($id) . '">&times;</button></div>';
    $html .= '<input type="text" class="compact-multi-search" placeholder="Pesquisar" data-multi-search="' . h($id) . '">';
    $html .= '<div class="compact-multi-list">';
    foreach ($options as $option) {
        $value = (string)($option['value'] ?? '');
        if ($value === '') {
            continue;
        }
        $optionLabel = (string)($option['label'] ?? $value);
        $html .= '<label class="compact-multi-option" data-multi-option="' . h(mb_strtolower($optionLabel . ' ' . $value)) . '">';
        $html .= '<input type="checkbox" value="' . h($value) . '" data-multi-checkbox="' . h($id) . '" data-label="' . h($optionLabel) . '"' . (isset($selectedSet[$value]) ? ' checked' : '') . '>';
        $html .= '<span>' . h($optionLabel) . '</span></label>';
    }
    $html .= '</div><div class="compact-multi-actions"><button type="button" class="button-link button-compact" data-multi-clear="' . h($id) . '">Limpar seleção</button><button type="button" class="primary" data-multi-close="' . h($id) . '">Aplicar</button></div>';
    $html .= '</div></div>';
    return $html;
}

function hidden_filter_inputs(array $keys, array $filters): string
{
    $html = '';
    foreach ($keys as $key) {
        $value = $filters[$key] ?? '';
        if (is_array($value)) {
            foreach ($value as $item) {
                $html .= '<input type="hidden" name="' . h((string)$key) . '[]" value="' . h((string)$item) . '">' . PHP_EOL;
            }
        } else {
            $html .= '<input type="hidden" name="' . h((string)$key) . '" value="' . h((string)$value) . '">' . PHP_EOL;
        }
    }
    return $html;
}
