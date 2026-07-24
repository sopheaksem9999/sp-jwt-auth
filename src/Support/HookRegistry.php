<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class HookRegistry
{
    /**
     * @var callable[]|string[]
     */
    private array $beforeTokenIssue = [];

    /**
     * @var callable[]|string[]
     */
    private array $validateTokenContext = [];

    /**
     * @var callable[]|string[]
     */
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

    /**
     * @return callable[]|string[]
     */
    public function beforeTokenIssueHooks(): array
    {
        return $this->beforeTokenIssue;
    }

    /**
     * @return callable[]|string[]
     */
    public function validateTokenContextHooks(): array
    {
        return $this->validateTokenContext;
    }

    /**
     * @return callable[]|string[]
     */
    public function afterTokenIssueHooks(): array
    {
        return $this->afterTokenIssue;
    }
}
