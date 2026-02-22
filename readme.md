# Taxonomy Tags to Checkboxes

Convert taxonomy tags to checkboxes in the WordPress admin area.

## Description

This plugin lets you swap the auto-complete / search style interface for a checkbox list, without changing any underlying data.

It allows you to pick from a list of the existing tags, so that admin users do not need to remember each tag.

It doesn't allow new tags to be created on the fly, which keeps the tags list under control, although you can optionally enable an add / edit link.

# Features

- Replace the tags ui with a checkbox list
- No alteration to front end functionality, or the underlying data / terms
- Option to control the height of the taxonomy metabox, between auto, full height, and custom (px)
- Show a link to the taxonomy edit screen via an `+ Add / Edit {taxononomy}` link
- Customization via filters

## Installation

1. Upload the entire `runthings-taxonomy-tags-to-checkboxes` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Navigate to `Settings` > `Taxonomies` to configure your preferences.

## Frequently Asked Questions

### How do I enable checkboxes for specific taxonomies?

Go to the Taxonomies settings page, select the desired taxonomies, configure height options, and enable the edit link if needed.

### Does this permanently change the taxonomy data?

No change is made to the underlying taxonomy or its terms. Only the admin interface is swapped out.

### Can I display system taxonomies?

Yes, toggle the "Show system taxonomies" checkbox to view all available taxonomies.

### What user capabilities are required?

The configuration screen needs a user with `manage_options` cap to edit it, but the changes are applied to anyone with editor access to post types that display those taxonomies.

### I have a feature idea / I've found a bug

You can post ideas or contribute to the project over at the GitHub repository, which can be found at https://github.com/runthings-dev/runthings-taxonomy-tags-to-checkboxes

## Screenshots

1. Example taxonomy with the checklist ui enabled and an edit link
   ![Example taxonomy with the checklist ui enabled and an edit link](https://raw.githubusercontent.com/runthings-dev/runthings-taxonomy-tags-to-checkboxes/master/.wordpress-org/screenshot-1.png)

2. Taxonomy settings screen
   ![Taxonomy settings screen](https://raw.githubusercontent.com/runthings-dev/runthings-taxonomy-tags-to-checkboxes/master/.wordpress-org/screenshot-2.png)

## Changelog

### v1.3.0 - 18th February 2026

- Feature - Add Gutenberg (block editor) support for taxonomy meta boxes instead of using compatibility mode

### v1.2.0 - 14th February 2026

- Improvement - Rename "Auto" height option to "Default" for clarity
- Improvement - Consistent minimum height applied to all taxonomy panels
- Fix - Fatal error when a taxonomy has been deleted after being configured in the plugin (thanks @mikenucleodigital)
- Fix - Plugin cleanup on uninstall now works correctly

### v1.1.1 - 7th February 2026

- Fix - Prevent duplicate taxonomy panels showing in the block editor when using built-in taxonomies like Tags (thanks @swinggraphics)

### v1.1.0 - 17th December 2025

- Bug fix - Admin options table was not displaying correctly on mobile devices
- Add fade transition to row actions
- Bump tested up to 6.9

### v1.0.1 - 25th June 2025

- Bump WordPress tested up to field to support 6.8 branch.

### v1.0.0 - 1st April 2025

- Initial release
- Works with custom and built-in taxonomies
- Control the height of the outbox
- Optionally include an add/edit link
- Filter `runthings_ttc_selected_taxonomies` to short-circuit the override

## Filters

### runthings_ttc_selected_taxonomies

This filter allows developers to modify the array of taxonomies selected for the custom checkbox interface.

#### Usage Example

```php
add_filter( 'runthings_ttc_selected_taxonomies', function( $selected_taxonomies ) {
    // Disable the override for the 'category' taxonomy.
    unset( $selected_taxonomies['category'] );
    return $selected_taxonomies;
} );
```

#### Parameters:

- **`$selected_taxonomies`** (array): An array of taxonomy slugs. Unset an entry to disable the checkbox list override.

## Additional Notes

Built by Matthew Harris of runthings.dev, copyright 2025.

Visit [runthings.dev](https://runthings.dev/) for more WordPress plugins and resources.

Contribute or report issues at [GitHub repository](https://github.com/runthings-dev/runthings-taxonomy-tags-to-checkboxes).

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see [http://www.gnu.org/licenses/gpl-3.0.html](http://www.gnu.org/licenses/gpl-3.0.html).

Icon - Check Box List by unlimicon from Noun Project, https://thenounproject.com/browse/icons/term/check-box-list/ (CC BY 3.0)
