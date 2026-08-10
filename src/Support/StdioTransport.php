<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class StdioTransport
{
    /** @var resource */
    private $input;

    /** @var resource */
    private $output;

    /**
     * @param resource|null $input
     * @param resource|null $output
     */
    public function __construct(mixed $input = null, mixed $output = null)
    {
        $this->input = $input ?? (defined('STDIN') ? STDIN : fopen('php://stdin', 'rb'));
        $this->output = $output ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb'));
    }

    public function readLine(): ?string
    {
        $line = fgets($this->input);

        return $line === false ? null : $line;
    }

    public function writeLine(string $line): void
    {
        fwrite($this->output, $line . PHP_EOL);
    }
}
