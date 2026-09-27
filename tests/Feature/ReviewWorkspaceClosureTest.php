<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReviewWorkspaceClosureTest extends TestCase
{
    public function test_review_search_is_bounded_and_escaped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ProductReviewController.php'));

        $this->assertStringContainsString(
            'mb_substr(trim((string) $request->string(\'search\')), 0, 100)',
            $controller
        );
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_']", $controller);
        $this->assertStringNotContainsString('"%{$search}%"', $controller);
    }

    public function test_customer_review_live_actions_guard_duplicate_submits_and_sync_first_submit_state(): void
    {
        $view = file_get_contents(resource_path('views/frontend/products/_reviews.blade.php'));

        $this->assertStringContainsString(
            'id="reviewRating" name="rating" class="form-select lc-form-select" required aria-required="true"',
            $view
        );
        $this->assertStringContainsString("if (form.dataset.pending === '1') return;", $view);
        $this->assertStringContainsString("form.dataset.pending = '1';", $view);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true');", $view);
        $this->assertStringContainsString("delete form.dataset.confirmed;", $view);
        $this->assertStringContainsString('@if(! $currentUserReview) hidden @endif', $view);
        $this->assertStringContainsString("if (deleteForm) deleteForm.hidden = false;", $view);
        $this->assertStringContainsString("release(@json(__('Update review')));", $view);

        $arabic = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            'تعذر حفظ تقييمك. حاول مرة أخرى.',
            $arabic['Could not save your review. Please try again.'] ?? null
        );
    }


    public function test_review_filters_have_explicit_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/reviews/index.blade.php'));

        foreach ([
            'reviewSearch',
            'reviewStatusFilter',
            'reviewRatingFilter',
            'reviewPerPage',
        ] as $controlId) {
            $this->assertStringContainsString('for="' . $controlId . '"', $view);
            $this->assertStringContainsString('id="' . $controlId . '"', $view);
        }
    }
}
