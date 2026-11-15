# Local assets

The project has no runtime CDN requirement (fonts, icons and CSS are all served locally).

- `vendor/bootstrap.min.css`: Bootstrap 5.3.3, https://github.com/twbs/bootstrap (MIT; see `vendor/bootstrap-LICENSE.txt`).
- `vendor/bootstrap-icons.css` and `vendor/fonts/*`: Bootstrap Icons 1.11.3, https://github.com/twbs/icons (MIT; see `vendor/bootstrap-icons-LICENSE.txt`).
- `images/favicon.svg` and `images/placeholder.svg`: project-created vector artwork.
- `images/room-1.jpg`: illustrative photo downloaded from https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1400&q=85&fit=crop
- `images/room-2.jpg`: illustrative photo downloaded from https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=1000&q=85&fit=crop
- `images/room-3.jpg`: illustrative photo downloaded from https://images.unsplash.com/photo-1540518614846-7eded433c457?w=1000&q=85&fit=crop

Photos are used as fictional, illustrative demo content under the Unsplash license (https://unsplash.com/license). They are not photos of the named sample dormitories and do not establish property verification or availability. Replace them with owner-authorized property photos for any real deployment.

## Fonts

- `fonts/inter-*.woff2` (Inter) and `fonts/plus-jakarta-sans-*.woff2` (Plus Jakarta Sans): downloaded once from Google Fonts (latin and latin-ext subsets, variable weights) and served locally. Both are licensed under the SIL Open Font License 1.1 (https://openfontlicense.org). `fonts/fonts.css` declares them.
