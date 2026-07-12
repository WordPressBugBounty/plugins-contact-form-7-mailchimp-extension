=== Connect Contact Form 7 to Mailchimp, Brevo, MailerLite & Klaviyo ===
Contributors: rnzo, chimpmatic
Donate link: https://chimpmatic.com/pricing
Tags: contact form 7, mailchimp, brevo, mailerlite, klaviyo
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 0.9.80.00
Requires PHP: 7.4
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect Contact Form 7 to Mailchimp, Brevo, MailerLite, or Klaviyo. Choose a provider per form, select a destination, and map subscriber fields.

== Description ==

**ChimpMatic Lite connects Contact Form 7 to Mailchimp, Brevo, MailerLite, and Klaviyo.** Send form submissions to the email marketing provider and destination you choose, without replacing Contact Form 7 or adding a heavyweight automation platform.

Select a provider for each form, connect your account, choose an audience, list, or group, and map Contact Form 7 fields to subscriber fields. Existing Mailchimp forms remain compatible and continue using their saved settings after upgrading.

= Four Email Marketing Integrations, One Familiar Workflow =

* **Mailchimp for Contact Form 7** - Connect with one-click OAuth or an API key, select an audience, map merge fields, and choose single or double opt-in. [Read the Mailchimp setup guide](https://chimpmatic.com/integrations/mailchimp/).
* **Brevo for Contact Form 7** - Connect with a Brevo API key, select a list, map contact attributes, and choose direct subscription or a Brevo confirmation email. [Read the Brevo setup guide](https://chimpmatic.com/integrations/brevo/).
* **MailerLite for Contact Form 7** - Connect with a MailerLite API token, select a group, and map subscriber fields. Double opt-in follows your MailerLite account setting. [Read the MailerLite setup guide](https://chimpmatic.com/integrations/mailerlite/).
* **Klaviyo for Contact Form 7** - Connect with a Klaviyo private API key, select a list, and map profile properties. Confirmation behavior follows the selected Klaviyo list. [Read the Klaviyo setup guide](https://chimpmatic.com/integrations/klaviyo/).

= Free Features =

* Choose Mailchimp, Brevo, MailerLite, or Klaviyo independently for each Contact Form 7 form.
* Use different provider accounts and destinations on different forms.
* Load audiences, lists, or groups inside the Contact Form 7 editor.
* Map the required email address and up to four additional Contact Form 7 fields.
* Require an opt-in checkbox before subscriber data is sent.
* Control or respect provider-specific confirmation behavior.
* Keep existing Mailchimp configurations working after upgrading.
* Use unlimited Contact Form 7 forms.

= Built for Contact Form 7 =

ChimpMatic adds a focused integration panel to each Contact Form 7 form. It does not replace your forms, introduce another form builder, or require a generic automation service between WordPress and your email provider.

Brevo API keys, MailerLite API tokens, and Klaviyo private API keys are encrypted before storage and are not displayed again after saving. Mailchimp users can avoid storing an API key by using the OAuth connection.

= ChimpMatic Pro =

ChimpMatic Pro is an optional add-on for teams that need advanced provider features, expanded field mapping, advanced consent controls, subscriber tools, and priority support. ChimpMatic Lite remains a complete Contact Form 7 integration on its own.

[Compare ChimpMatic Lite and Pro](https://chimpmatic.com/pro/).

= External Services and Data Disclosure =

ChimpMatic is independently developed and is not affiliated with or endorsed by Mailchimp, Brevo, MailerLite, or Klaviyo. When a configured form is submitted, its email address, mapped fields, destination, and consent choice are sent directly to the provider you selected; an account with that provider is required.

[Read how each external service is used, what data is sent, and review provider terms and privacy policies](https://chimpmatic.com/external-services/).

= Requirements =

1. WordPress 6.4 or higher.
2. Contact Form 7 5.0 or higher.
3. PHP 7.4 or higher.
4. An account with at least one supported email marketing provider.

= Support =

* [Provider integration guides](https://chimpmatic.com/integrations/)
* [Documentation and Help Center](https://chimpmatic.com/help/)
* [Contact the ChimpMatic team](https://chimpmatic.com/contact)

== Installation ==

1. Install and activate Contact Form 7.
2. Install ChimpMatic Lite from the WordPress Plugin Directory and activate it.
3. Edit a Contact Form 7 form and open its **Chimpmatic** tab.
4. Choose Mailchimp, Brevo, MailerLite, or Klaviyo.
5. Connect the provider, select an audience, list, or group, and map the email field plus any additional fields.
6. Configure consent behavior, save the form, and submit a test entry.

= Provider Credentials =

Mailchimp supports OAuth or an API key. Brevo uses an API key, MailerLite uses an API token, and Klaviyo uses a private API key. For current screenshots and exact provider navigation, use the [integration setup guides](https://chimpmatic.com/integrations/).

== Frequently Asked Questions ==

= Which email marketing providers does ChimpMatic Lite support? =

Version 0.9.80.00 supports Mailchimp, Brevo, MailerLite, and Klaviyo. You select one provider independently for each Contact Form 7 form.

[Read more about supported email marketing integrations](https://chimpmatic.com/integrations/).

= Will my existing Mailchimp forms keep working? =

Yes. Existing configurations default to Mailchimp and retain their saved audience, field mappings, API credentials, and opt-in behavior.

[Read more about the Contact Form 7 Mailchimp integration](https://chimpmatic.com/integrations/mailchimp/).

= Can I connect without an API key? =

Mailchimp supports one-click OAuth. Brevo requires an API key, MailerLite requires an API token, and Klaviyo requires a private API key.

[Read more about connecting each provider and finding its API credentials](https://chimpmatic.com/integrations/).

= Can different forms use different providers? =

Yes. A newsletter form can send to MailerLite while a lead form sends to Brevo, for example. Every form stores its own provider, credentials, destination, mappings, and opt-in configuration.

[Read more about configuring providers per Contact Form 7 form](https://chimpmatic.com/integrations/).

= Does ChimpMatic support double opt-in? =

Yes, but the control differs by provider. Mailchimp offers single or double opt-in per form. Brevo can send a confirmation email. MailerLite follows the account's API double-opt-in setting. Klaviyo follows the selected list's consent behavior.

[Read more about provider-specific double opt-in](https://chimpmatic.com/help/double-opt-in/).

= How many fields can I map in Lite? =

Lite maps the required email address plus up to four additional form fields. Compatible ChimpMatic Pro features can expand the mapping limit.

[Read more about Contact Form 7 field mapping](https://chimpmatic.com/help/field-mapping/).

= Where do I get help? =

Start with the [provider integration guides](https://chimpmatic.com/integrations/), visit the [Help Center](https://chimpmatic.com/help/), or [contact the ChimpMatic team](https://chimpmatic.com/contact).

== Screenshots ==

1. Choose Mailchimp, Brevo, MailerLite, or Klaviyo inside a Contact Form 7 form.
2. Select an audience, list, or group and map Contact Form 7 fields to subscriber fields.

== Upgrade Notice ==

= 0.9.80.00 =
Adds per-form integrations for Brevo, MailerLite, and Klaviyo alongside Mailchimp. Existing Mailchimp configurations remain compatible.

= 0.9.78.07 =
Security release: fixes a stored cross-site scripting vulnerability in the Contact Lookup tool (CVE-2026-15000). All users should update.

= 0.9.78.06 =
One-click Mailchimp connection, a smarter audience panel, and reliability fixes for checkbox fields and special mail-tags.

== Changelog ==

= 0.9.80.00 =

[Version 0.9.80.00 release notes](https://chimpmatic.com/changelog#0.9.80.00)

= 0.9.78.07 =

[Version 0.9.78.07 release notes](https://chimpmatic.com/changelog#0.9.78.07)

= 0.9.78.06 =

[Version 0.9.78.06 release notes](https://chimpmatic.com/changelog#0.9.78.06)

= 0.9.78.05 =

[Version 0.9.78.05 release notes](https://chimpmatic.com/changelog#0.9.78.05)

= 0.9.78.04 =

[Version 0.9.78.04 release notes](https://chimpmatic.com/changelog#0.9.78.04)

= 0.9.78.02 =

[Version 0.9.78.02 release notes](https://chimpmatic.com/changelog#0.9.78.02)

= 0.9.75 =

[Version 0.9.75 release notes](https://chimpmatic.com/changelog#0.9.75)

= 0.9.73 =

[Version 0.9.73 release notes](https://chimpmatic.com/changelog#0.9.73)

= 0.9.22 =

[Version 0.9.22 release notes](https://chimpmatic.com/changelog#0.9.22)

= 0.8.01 =

[Version 0.8.01 release notes](https://chimpmatic.com/changelog#0.8.01)

= 0.7.50 =

[Version 0.7.50 release notes](https://chimpmatic.com/changelog#0.7.50)

= 0.7.01 =

[Version 0.7.01 release notes](https://chimpmatic.com/changelog#0.7.01)

= 0.6.10 =

[Version 0.6.10 release notes](https://chimpmatic.com/changelog#0.6.10)

= 0.5.64 =

[Version 0.5.64 release notes](https://chimpmatic.com/changelog#0.5.64)

= 0.5.01 =

[Version 0.5.01 release notes](https://chimpmatic.com/changelog#0.5.01)

= 0.4.60 =

[Version 0.4.60 release notes](https://chimpmatic.com/changelog#0.4.60)

= 0.4.43 =

[Version 0.4.43 release notes](https://chimpmatic.com/changelog#0.4.43)

= 0.4.01 =

[Version 0.4.01 release notes](https://chimpmatic.com/changelog#0.4.01)

= 0.3.50 =

[Version 0.3.50 release notes](https://chimpmatic.com/changelog#0.3.50)

= 0.3.40 =

[Version 0.3.40 release notes](https://chimpmatic.com/changelog#0.3.40)

= 0.3.20 =

[Version 0.3.20 release notes](https://chimpmatic.com/changelog#0.3.20)

= 0.3.10 =

[Version 0.3.10 release notes](https://chimpmatic.com/changelog#0.3.10)

= 0.3.01 =

[Version 0.3.01 release notes](https://chimpmatic.com/changelog#0.3.01)

= 0.2.30 =

[Version 0.2.30 release notes](https://chimpmatic.com/changelog#0.2.30)

= 0.2.20 =

[Version 0.2.20 release notes](https://chimpmatic.com/changelog#0.2.20)

= 0.2.15 =

[Version 0.2.15 release notes](https://chimpmatic.com/changelog#0.2.15)

= 0.2.10 =

[Version 0.2.10 release notes](https://chimpmatic.com/changelog#0.2.10)

= 0.2.5 =

[Version 0.2.5 release notes](https://chimpmatic.com/changelog#0.2.5)

= 0.1.5 =

[Version 0.1.5 release notes](https://chimpmatic.com/changelog#0.1.5)

= 0.1.2 =

[Version 0.1.2 release notes](https://chimpmatic.com/changelog#0.1.2)
