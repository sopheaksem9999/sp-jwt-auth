<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WorkflowActionPinningTest extends TestCase
{
    public function test_all_workflow_actions_are_pinned_to_40_character_commit_shas(): void
    {
        $workflowFiles = glob(__DIR__ . '/../../.github/workflows/*.yml');
        self::assertNotEmpty($workflowFiles, 'Workflow files must exist');

        foreach ($workflowFiles as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('/uses:\s*([^\s#]+)/', $content, $matches);

            foreach ($matches[1] as $actionRef) {
                if (str_starts_with($actionRef, './') || str_starts_with($actionRef, 'docker://')) {
                    continue;
                }

                self::assertStringContainsString(
                    '@',
                    $actionRef,
                    "Action ref '{$actionRef}' in {$file} must contain '@'."
                );

                [$actionName, $ref] = explode('@', $actionRef, 2);
                self::assertMatchesRegularExpression(
                    '/^[0-9a-f]{40}$/',
                    $ref,
                    "Action '{$actionName}' in {$file} must be pinned to a 40-character commit SHA, found '{$ref}'."
                );
            }
        }
    }

    public function test_security_workflow_does_not_use_archived_semgrep_action(): void
    {
        $securityYaml = (string) file_get_contents(__DIR__ . '/../../.github/workflows/security.yml');
        self::assertStringNotContainsString(
            'semgrep/semgrep-action',
            $securityYaml,
            'security.yml must not use the archived semgrep/semgrep-action repository.'
        );
    }

    public function test_security_workflow_pins_semgrep_container_image_digest(): void
    {
        $securityYaml = (string) file_get_contents(__DIR__ . '/../../.github/workflows/security.yml');
        self::assertMatchesRegularExpression(
            '/image:\s*semgrep\/semgrep:[^@\s]+@sha256:[0-9a-f]{64}/',
            $securityYaml,
            'security.yml must pin the semgrep container image with an immutable @sha256: digest.'
        );
    }

    public function test_release_workflow_gates_beta_releases_behind_commit_flag(): void
    {
        $releaseYaml = (string) file_get_contents(__DIR__ . '/../../.github/workflows/release.yml');
        self::assertStringContainsString(
            "[release-beta]",
            $releaseYaml,
            "release.yml must gate release-beta behind the '[release-beta]' commit message check."
        );
    }
}
