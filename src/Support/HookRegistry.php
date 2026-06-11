<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class HookRegistry
{
    private array $beforeTokenIssue = [];

    private array $validateTokenContext = [];

    private array $afterTokenIssue = [];

    public function beforeTokenIssue(string|callable $hook): self
    {
        $this->beforeTokenIssue[] = $hook;

        return $this;
    }

    public function validateTokenContext(string|callable $hook): self
    {
        $this->validateTokenContext[] = $hook;

        return $this;
    }

    public function afterTokenIssue(string|callable $hook): self
    {
        $this->afterTokenIssue[] = $hook;

        return $this;
    }

    public function beforeTokenIssueHooks(): array
    {
        return $this->beforeTokenIssue;
    }

    public function validateTokenContextHooks(): array
    {
        return $this->validateTokenContext;
    }

    public function afterTokenIssueHooks(): array
    {
        return $this->afterTokenIssue;
    }
}
