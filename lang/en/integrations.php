<?php
/**
 * Nexus 2.0 — English Translations (Integrations)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'integrations' => [
        'page_title'    => 'Integrations',
        'page_subtitle' => 'Configure access keys for external services.',

        'ilp_description'         => 'Shared service to optimize PDFs and images via cloud API.',
        'status_configured'       => 'Configured',
        'status_not_configured'   => 'Not configured',
        'status_saved'            => 'Saved',

        'security_note' => 'Keys are stored encrypted with AES-256. They are never shown in plain text once saved.',

        'section_account' => 'Account data',
        'section_keys'    => 'API Keys',

        'field_email'             => 'Account email',
        'field_password'          => 'Account password',
        'field_project'           => 'Project name',
        'field_project_placeholder' => 'Ex: Nexus',
        'field_public_key'        => 'Public Key',
        'field_secret_key'        => 'Secret Key',

        'placeholder_keep_current' => 'Leave empty to keep current',
        'password_current_help'    => 'There is a saved password. Leave empty to keep it.',
        'public_key_help'          => 'Public key of your project at developer.ilovepdf.com.',
        'secret_key_help'          => 'Secret key of the project. Encrypted on save.',

        'show_password' => 'Show password',
        'show_value'    => 'Show value',
        'hide_value'    => 'Hide',

        'btn_save'    => 'Save changes',
        'btn_test'    => 'Test connection',
        'btn_clear'   => 'Delete my keys',
        'confirm_clear' => 'Delete your iLovePDF keys? This action cannot be undone.',
        'testing'   => 'Testing connection...',
        'plan_info' => 'Plan information',
        'test_ok'   => 'Connection verified successfully.',
        'test_fail' => 'Could not connect. Please verify the public key.',

        'err_email'   => 'Invalid email.',
        'err_generic' => 'Could not save configuration.',
        'saved'       => 'Configuration saved.',

        'guide_title'     => 'How to get iLovePDF / iLoveIMG keys',
        'guide_intro'     => 'iLovePDF and iLoveIMG share the same platform and API keys. Registration takes less than 5 minutes.',
        'guide_step1'     => 'Go to the developer console at',
        'guide_step2'     => 'Create a free account or sign in.',
        'guide_step3'     => 'In the main panel, click "+ New project" and give it a name.',
        'guide_step4'     => 'Open the created project. You will find the Public Key and Secret Key.',
        'guide_step5'     => 'Copy both keys and paste them in the form above. Click "Test connection" to confirm.',
        'guide_free_plan' => 'Free plan: 200 operations per month (PDFs and images) with files up to 100 MB.',

        // SMTP
        'smtp_title'          => 'Outgoing mail (SMTP)',
        'smtp_description'    => 'Configure the mail server for sending notifications and password recovery emails.',
        'smtp_security_note'  => 'The password is stored encrypted with AES-256. It is recommended to use an app password, not the main account password.',
        'smtp_section_server' => 'Server',
        'smtp_section_auth'   => 'Authentication',
        'smtp_section_sender' => 'Sender',
        'smtp_field_host'     => 'SMTP server',
        'smtp_field_port'     => 'Port',
        'smtp_field_secure'   => 'Encryption',
        'smtp_field_user'     => 'Username / email',
        'smtp_field_pass'     => 'Password',
        'smtp_field_from_email' => 'Sender address',
        'smtp_field_from_name'  => 'Sender name',
    ],
];
