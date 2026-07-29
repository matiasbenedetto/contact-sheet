=== Contact Sheet ===
Contributors: mmattiiaass
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.2.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, photography, one-column, custom-logo, block-patterns, block-styles, full-site-editing, translation-ready

A minimal photography blog theme that presents each post's images as a film-style contact sheet.

== Description ==

Contact Sheet is a minimal block theme for photographers and photo bloggers. On the home page, each post's images are rendered in a horizontal scrolling strip — like a photographer's proof sheet — using the bundled Photo Strip block. Single posts keep the focus on your photos with a clean, distraction-free layout, complete with comments and previous/next post navigation. A two-color palette with light and dark style variations keeps the frame neutral so the photographs do the talking.

Live demo: https://contactsheetdemo.mebenedetto.com/

= The Photo Strip block =

The theme ships with a custom Photo Strip block, which powers the home, archive, and search views:

* It automatically collects every image from a post's content — no galleries to configure and no featured image required. Just write a post and add photos; the strip builds itself.
* Images are laid out in a single horizontal, scrollable strip of frames, like a strip of film on a contact sheet, and every frame links to the post.
* Posts without images render a neutral placeholder frame, so mixed photo/text blogs keep a consistent layout.
* Block settings let you adjust the strip height, frame aspect ratio, and corner radius.
* It reads the post from the surrounding Query Loop, so you can drop it into your own templates and patterns anywhere a post context exists.

= Demo content =

The demo site's full content (photo posts, pages, and images) is available as a WordPress export file you can import into your own site: download it from https://raw.githubusercontent.com/matiasbenedetto/contact-sheet/trunk/demo-site/contact-sheet-demo.xml, then go to Tools > Import > WordPress in your admin panel, upload the file, and check "Download and import file attachments".

== Installation ==

1. In your admin panel, go to Appearance > Themes and click the Add New button.
2. Click Upload Theme and Choose File, then select the theme's .zip file. Click Install Now.
3. Click Activate to use your new theme right away.

== Changelog ==

= 1.2.5 =
* New theme screenshot and color-variation previews, captured from the demo site (contactsheetdemo.mebenedetto.com)
* Expanded theme description: live demo link and an explanation of the Photo Strip block

= 1.2.4 =
* Expose only the first photo-strip link to keyboard and AT users
* Add visible keyboard focus indicator to photo-strip links

= 1.2.3 =
* Mobile menu: the overlay now uses the theme's paper and ink colors, so it matches the active style variation (previously it fell back to a white panel on dark variations)

= 1.2.2 =
* Maintenance and behind-the-scenes updates.

= 1.2.1 =
* Rounded button corners (3px) and restyled search inputs to match the design tokens
* Limited archive and search results to 5 posts per page, matching the home page

= 1.2.0 =
* Added a comments section to single posts, with refined layout, full-bleed dividers, and a circular avatar
* Added previous/next post navigation links with labels on single posts
* Reduced the theme to a 2-color palette with style variations
* Added diffuse drop shadows so images read as printed photos
* Photo Strip: gray placeholder for image-less posts and hover polish
* Refined image block spacing and matched border radius to the Photo Strip
* Tightened spacing between posts on the home page; home now shows 5 posts
* Refined the search results and 404 templates (constrained widths, matching title sizes, spacing)

= 1.1.0 =
* Added 404, archive, page, and search templates
* Photo Strip block improvements
* Metadata and packaging fixes

= 1.0.0 =
* Initial release

== Copyright ==

Contact Sheet WordPress Theme, (C) 2025 Matias Benedetto
Contact Sheet is distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

== Resources ==

Cal Sans Font
Copyright (c) 2022 CalCom, Inc.
License: SIL Open Font License, Version 1.1, https://opensource.org/licenses/OFL-1.1
Source: https://github.com/calcom/font
Included in: assets/fonts/CalSans-SemiBold.ttf (license text in assets/fonts/OFL.txt)

Images
All images (screenshot.jpg and the images in screenshots/) are photographs taken by Matias Benedetto, https://mebenedetto.com
Copyright (c) 2025 Matias Benedetto
License: Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0), https://creativecommons.org/licenses/by-sa/4.0/
