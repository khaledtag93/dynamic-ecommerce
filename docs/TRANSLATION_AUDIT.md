# Translation Audit

## Rule
Every new feature must support Arabic and English. Avoid hardcoded text in views and UI actions.

## Current audit priority

### High priority
- Admin growth pages
- Analytics dashboard labels
- Payment result / checkout support texts
- Customer policy/contact pages
- Admin settings and dynamic content labels

### Medium priority
- Flash messages
- Empty states
- Validation and helper texts
- Buttons inside rarely used admin modules

## Audit method

1. Review blade files for plain visible text
2. Move static UI text into translation files or JSON translation files
3. Verify Arabic and English rendering on both admin and storefront
4. Re-test pages after translation changes

## Target outcome

- No important admin/customer UI visible text remains hardcoded
- Both languages feel complete and intentional
