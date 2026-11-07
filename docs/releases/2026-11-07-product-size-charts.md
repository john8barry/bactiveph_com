# November 7 product size charts

Prepared locally against origin/main at 145e8100. Medium impact: the current Essential Workout Shorts guide gives the longer Go-Anywhere measurements. The published WooCommerce catalog and product gallery photos identify both shorts styles and both women's zip polos. The earlier October release predates these new catalog matches.

| Product (catalog ID) | Source JPEG time | Expected guide |
| --- | --- | --- |
| Essential Workout Shorts (1133) | 15.38.11 | Oak Brown drawcord style, S length 42.5 cm |
| Go-Anywhere Performance Shorts (1302) | 15.38.09 | Longer black style, S length 46 cm |
| Everyday Active Tee (1079) | 15.38.06 | Men's T-Shirt |
| Everyday Active Polo (1117) | 15.38.04 | Men's Polo / Tee, identical October asset |
| Banded Waist Zip Polo (1151) | 15.37.58 | Banded hem, length/neck width/bust |
| The Clubhouse Zip Polo (1248) | 15.38.01 | Side-slit hem, five measurements |

Source filenames begin with photo_2026-11-07. Go-Anywhere lists XXXL for sale. Its chart covers only S–XXL. Each product dialog and its standalone selection must display exactly its supplied original with a full-size link and text measurements. The existing mens-shorts bookmark continues to serve Essential Workout Shorts. The exact JPEG hashes are in the size-guide tests.

Release scope is the size-chart registry in the child theme and five new JPEGs. Install the images before the registry, preserving any unrelated production changes. Verify all six product dialogs, fallback links and served hashes after release. To roll back, restore the prior registry entries from the verified pre-release theme, purge the affected product and size-guide pages, and remove only the five new files after checking their hashes. No database migration.
