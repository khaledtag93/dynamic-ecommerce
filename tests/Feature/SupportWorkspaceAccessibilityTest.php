<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupportWorkspaceAccessibilityTest extends TestCase
{
    public function test_admin_support_forms_explicitly_associate_labels_with_controls(): void
    {
        $create = file_get_contents(resource_path('views/admin/support/create.blade.php'));
        $show = file_get_contents(resource_path('views/admin/support/show.blade.php'));

        foreach ([
            'supportAdminCustomer',
            'supportAdminOrder',
            'supportAdminSubject',
            'supportAdminCategory',
            'supportAdminPriority',
            'supportAdminVisibility',
            'supportAdminMessage',
        ] as $id) {
            $this->assertStringContainsString('for="' . $id . '"', $create);
            $this->assertStringContainsString('id="' . $id . '"', $create);
        }

        foreach ([
            'supportReplyTemplate',
            'supportReplyVisibility',
            'supportAdminReplyMessage',
            'supportCaseOwner',
            'supportCasePriority',
            'supportCaseStatus',
        ] as $id) {
            $this->assertStringContainsString('for="' . $id . '"', $show);
            $this->assertStringContainsString('id="' . $id . '"', $show);
        }

        $this->assertStringContainsString('id="supportAdminSubject" name="subject" aria-required="true"', $create);
        $this->assertStringContainsString('id="supportAdminMessage" name="message" aria-required="true"', $create);
        $this->assertStringContainsString('id="supportAdminReplyMessage" name="message" aria-required="true"', $show);
    }
}
