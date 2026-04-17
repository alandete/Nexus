<?php
/**
 * Nexus 2.0 — English Translations (Application)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'application' => [
        'page_title'    => 'Application',
        'page_subtitle' => 'Identity, brand colors, timezone, maintenance mode and company data.',

        'sec_identity'       => 'Identity',
        'sec_identity_desc'  => 'Name, tagline, description, logo, favicon and brand colors.',
        'sec_company'        => 'Company',
        'sec_company_desc'   => 'Organization contact information.',
        'sec_operation'      => 'Operation',
        'sec_operation_desc' => 'Timezone, default language and maintenance mode.',
        'sec_privacy'        => 'Privacy',
        'sec_privacy_desc'   => 'Site visibility to search engines and translators.',

        'field_app_name'     => 'Application name',
        'field_tagline'      => 'Tagline',
        'field_tagline_help' => 'Short phrase shown on login and metadata.',
        'field_description'  => 'Description',
        'field_assets'       => 'Logo and favicon',
        'field_logo'         => 'Logo',
        'field_favicon'      => 'Favicon',
        'field_colors'       => 'Brand colors',
        'colors_help'        => 'The primary color is used for navigation, branding and key actions. The accent is reserved for specific elements.',
        'logo_help'          => 'JPG, PNG or WebP. Max 2 MB. Recommended 120x40 px.',
        'favicon_help'       => 'PNG, SVG or ICO. Max 512 KB. Square recommended.',
        'btn_upload'         => 'Upload',
        'btn_remove'         => 'Remove',

        'field_brand_color'  => 'Brand color',
        'brand_color_help'   => 'Used for active navigation, branding, focus and key accents.',
        'field_accent_color' => 'Accent color',
        'accent_color_help'  => 'Secondary color to highlight specific elements.',
        'color_preview'      => 'Preview',
        'preview_button'     => 'Primary action',
        'preview_lozenge'    => 'Active',

        'field_company_name'    => 'Company name',
        'field_company_address' => 'Address',
        'field_contact_email'   => 'Contact email',
        'field_contact_phone'   => 'Phone',
        'field_website'         => 'Website',

        'field_timezone'         => 'Timezone',
        'field_timezone_help'    => 'Affects dates and times shown throughout the application.',
        'field_default_lang'     => 'Default language',
        'field_default_lang_help'=> 'Initial language for visitors without session.',

        'field_maintenance'                 => 'Maintenance mode',
        'field_maintenance_help'            => 'Blocks access to non-admin users.',
        'field_maintenance_msg'             => 'Visible message',
        'field_maintenance_msg_placeholder' => 'We will be back soon...',
        'field_maintenance_ips'             => 'Allowed IPs',
        'field_maintenance_ips_help'        => 'One IP per line. These IPs will be able to access even when mode is active.',
        'maintenance_warning'               => 'When enabling maintenance mode, only administrators and allowed IPs will be able to access the system.',
        'maintenance_default_msg'           => 'We are making improvements to the system. We will be back soon.',

        'field_privacy'      => 'Private mode',
        'field_privacy_help' => 'Adds noindex/nofollow meta tags for search engines and disables translators.',

        'maintenance_title'        => 'Under maintenance',
        'maintenance_admin_access' => 'Admin access',

        'btn_save' => 'Save changes',

        'err_app_name'     => 'Application name is required.',
        'err_brand_color'  => 'Invalid brand color. Must be hexadecimal (#RRGGBB).',
        'err_accent_color' => 'Invalid accent color. Must be hexadecimal (#RRGGBB).',
        'err_email'        => 'Invalid email.',
        'err_website'      => 'Invalid URL. Must start with http:// or https://',
        'err_timezone'     => 'Invalid timezone.',
        'err_generic'      => 'Could not save configuration.',
        'err_save'         => 'Error saving. Please review the fields.',
        'success_save'     => 'Configuration saved.',
        'saved'            => 'Changes saved.',
    ],
];
