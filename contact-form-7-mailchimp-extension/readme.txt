=== Connect Contact Form 7 to Mailchimp, Brevo, MailerLite & Klaviyo ===
Contributors: rnzo, chimpmatic
Donate link: https://chimpmatic.com/pricing
Tags: contact form 7, mailchimp, brevo, mailerlite, klaviyo
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 0.9.81.04
Requires PHP: 7.4
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Contact Form 7 integration for Mailchimp, Brevo, MailerLite, and Klaviyo. Send form leads to email marketing lists and audiences.

== Description ==

**ChimpMatic Lite is a Mailchimp integration for Contact Form 7 that also connects forms to Brevo, MailerLite, and Klaviyo.** Send form submissions to email marketing lists, audiences, or groups without replacing Contact Form 7 or adding a separate automation platform.

Choose a provider for each form, connect the account, select a destination, and map Contact Form 7 fields to subscriber fields. Existing Mailchimp forms keep their saved audience, field mappings, credentials, and opt-in settings after upgrading.

= Contact Form 7 Email Marketing Integrations =

Choose one provider independently for each Contact Form 7 form. Each integration sends submitted subscriber data directly to the audience, list, or group selected in that form's settings.

**Mailchimp integration for Contact Form 7** - Connect through one-click OAuth or an API key, choose an audience, map merge fields, and select single or double opt-in. [Read the Mailchimp setup guide](https://chimpmatic.com/integrations/mailchimp/).

**Brevo integration for Contact Form 7** - Connect with a Brevo API key, choose a list, map contact attributes, and select direct subscription or a Brevo confirmation email. [Read the Brevo setup guide](https://chimpmatic.com/integrations/brevo/).

**MailerLite integration for Contact Form 7** - Connect with a MailerLite API token, choose a group, and map subscriber fields. Double opt-in follows the MailerLite account setting. [Read the MailerLite setup guide](https://chimpmatic.com/integrations/mailerlite/).

**Klaviyo integration for Contact Form 7** - Connect with a Klaviyo private API key, choose a list, and map profile properties. Confirmation behavior follows the selected Klaviyo list. [Read the Klaviyo setup guide](https://chimpmatic.com/integrations/klaviyo/).

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

ChimpMatic Lite also offers an optional, unchecked “Help Us Improve” setting. Only after an administrator enables it, the plugin sends pseudonymous aggregate product signals to `https://signls.dev` so we can understand compatibility, provider adoption, feature use, setup friction, and reliability. The data includes a stable pseudonymous plugin install ID; a random device ID that rotates when the site origin changes; ChimpMatic Lite, WordPress, PHP, and Contact Form 7 versions; multisite state; aggregate configured and active form counts; provider authentication preference counts (OAuth, API key, or no credential); destination and mapping counts; fixed feature counts; aggregate operation success/failure classes; and whether ChimpMatic Pro is installed, active, its version, and a bounded license-state category.

Signls sharing never includes contact data, credentials, license keys, form IDs, field names or values, list/group/audience identifiers, account identifiers, raw errors, IP addresses, user agents, or your site URL. Active installations send at most once daily; installations without relevant activity send at most once weekly. Detailed delivery receipts are retained for 90 days, first-observation daily aggregates are retained permanently for trend analysis, and the stable pseudonymous install identity is retained to measure long-term product adoption. You can disable sharing at any time in Help Us Improve; disabling cancels future delivery and clears the local delivery credential without sending an opt-out event.

Signls service terms: [Terms and Conditions](https://chimpmatic.com/terms-and-conditions) · [Privacy Policy](https://chimpmatic.com/privacy).

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

Version 0.9.81.04 supports Mailchimp, Brevo, MailerLite, and Klaviyo. You select one provider independently for each Contact Form 7 form.

= How do I connect Mailchimp to Contact Form 7? =

Open the ChimpMatic tab in the Contact Form 7 editor, select Mailchimp, connect with OAuth or an API key, choose an audience, and map the form's email field. You can then configure consent and single or double opt-in. [Follow the Mailchimp setup guide](https://chimpmatic.com/integrations/mailchimp/).

= How do I connect Brevo to Contact Form 7? =

Open the ChimpMatic tab, select Brevo, enter a Brevo API key, choose a list, and map the form fields to Brevo contact attributes. You can subscribe the contact directly or use a Brevo confirmation email. [Follow the Brevo setup guide](https://chimpmatic.com/integrations/brevo/).

= Can Contact Form 7 send subscribers to MailerLite? =

Yes. Select MailerLite in the ChimpMatic tab, connect with an API token, choose a group, and map the Contact Form 7 email field and subscriber fields. MailerLite applies the account's API double-opt-in setting. [Follow the MailerLite setup guide](https://chimpmatic.com/integrations/mailerlite/).

= Can Contact Form 7 send leads to Klaviyo? =

Yes. Select Klaviyo in the ChimpMatic tab, connect with a private API key, choose a list, and map Contact Form 7 fields to Klaviyo profile properties. Consent behavior follows the selected Klaviyo list. [Follow the Klaviyo setup guide](https://chimpmatic.com/integrations/klaviyo/).

= Will my existing Mailchimp forms keep working? =

Yes. Existing configurations default to Mailchimp and retain their saved audience, field mappings, API credentials, and opt-in behavior.

[Read more about the Contact Form 7 Mailchimp integration](https://chimpmatic.com/integrations/mailchimp/).

= Can different forms use different providers? =

Yes. A newsletter form can send to MailerLite while a lead form sends to Brevo, for example. Every form stores its own provider, credentials, destination, mappings, and opt-in configuration.

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

= 0.9.81.04 =
Adds optional product-insights sharing that remains off until an administrator enables it. Existing provider integrations are unchanged.

= 0.9.81.03 =
Improved MailerLite support and an updated listing. Existing Mailchimp configurations remain compatible.

= 0.9.78.07 =
Security release: fixes a stored cross-site scripting vulnerability in the Contact Lookup tool (CVE-2026-15000). All users should update.

== Changelog ==

= 0.9.81.04 =

Added optional, explicit-consent product-insights sharing with privacy-safe aggregate signals and reliable daily or weekly delivery. It does not send contact data, credentials, site URLs, IP addresses, or user agents.

[Version 0.9.81.04 release notes](https://chimpmatic.com/changelog#0.9.81.04)

= 0.9.81.03 =

Improved MailerLite support and refreshed the plugin listing. Existing Mailchimp configurations remain compatible.

[Version 0.9.81.03 release notes](https://chimpmatic.com/changelog#0.9.81.03)

[View the complete ChimpMatic changelog](https://chimpmatic.com/changelog)
