# Phase 1 Cleanup Map

## Archived now

- `public/assets/New folder` -> `public/__legacy_assets/assets-new-folder-phase1`

## Still queued for verification before deletion

These paths look like template/demo leftovers and should be validated in the next cleanup batch before permanent removal:

- `public/assets/img/avaters/`
- `public/assets/img/company-logos/`
- `public/assets/img/latest-news/`
- `public/assets/img/team/`
- `public/assets/css/scss/`
- `public/assets/now-ui-dashboard/`
- `public/admin/images/carousel/`
- `public/admin/images/demo/`
- `public/admin/images/faces/`
- `public/admin/images/file-icons/`
- `public/admin/images/lightbox/`
- `public/admin/images/samples/`
- `public/admin/images/sprites/`

## Delivery cleanup to keep in exported zips

The final delivery zip should exclude runtime/local-only content whenever possible:

- `.env`
- `.git/`
- `node_modules/`
- `bootstrap/cache/*.php`
- `storage/logs/*`
