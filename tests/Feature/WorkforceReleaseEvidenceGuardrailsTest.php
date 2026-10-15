<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class WorkforceReleaseEvidenceGuardrailsTest extends TestCase
{
    public function test_release_gap_register_retains_non_delivery_flags(): void
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/docs/WGAP05_RELEASE_EVIDENCE_GAP_DISPOSITION_2026-10-15.md');
        self::assertIsString($text);
        foreach (['QAS Deferred', 'Missing Test', 'Policy Blocked', 'Indirect Evidence', 'W-ORG-01b'] as $label) {
            self::assertStringContainsString($label, $text);
        }
    }

    public function test_guardrails_preserve_approval_and_acceptance_boundaries(): void
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/docs/WGAP06_RELEASE_EVIDENCE_GUARDRAILS_2026-10-15.md');
        self::assertIsString($text);
        foreach (['QAS Acceptance', 'DEFERRED', 'specifications only', 'OPEN', 'Policy Blocked', 'not a security-policy implementation'] as $claim) {
            self::assertStringContainsString($claim, $text);
        }
    }
}
