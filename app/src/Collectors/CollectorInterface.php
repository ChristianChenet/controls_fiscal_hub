<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

interface CollectorInterface
{
    public function collect(): array;
    public function setCompanyContext(?array $company): void;
}
