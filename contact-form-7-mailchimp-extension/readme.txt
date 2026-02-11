=== Connect Contact Form 7 and Mailchimp ===
Contributors: rnzo, chimpmatic
Donate link: https://bit.ly/2HdTzmO
Tags: contact form 7 mailchimp, cf7 mailchimp, mailchimp, mailchimp integration, contact form 7
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 0.9.75
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Contact Form 7 to Mailchimp. Automatically sync form submissions to your Mailchimp audiences with merge field mapping, double opt-in, and opt-in checkbox support.

== Description ==

**Chimpmatic Lite** is the easiest way to connect **Contact Form 7 to Mailchimp**. When a visitor submits your CF7 form, their information is automatically added to your Mailchimp audience using the Mailchimp API v3.

Unlike heavyweight form builders, Chimpmatic is a specialist CF7-to-Mailchimp integration. It does one thing and does it well: syncing your Contact Form 7 submissions directly to Mailchimp.

= Why Chimpmatic? =

* **Built specifically for Contact Form 7** - not a generic form connector
* **Lightweight** - no bloat, no extra form builders, no overhead
* **Mailchimp API v3** - uses the latest official Mailchimp API
* **50,000+ active installs** - trusted by WordPress sites worldwide

= Free Features =

* Connect any Contact Form 7 form to a Mailchimp audience
* Map form fields to Mailchimp merge fields (FNAME, LNAME, etc.)
* Use a **different Mailchimp API key** per contact form
* Use a **different mailing list** per contact form
* **Single opt-in** or **double opt-in** support
* **Opt-in checkbox** - let visitors choose to subscribe
* Unlimited contact forms
* Works with any Contact Form 7 form layout

= Premium Features (Chimpmatic PRO) =

Upgrade to [Chimpmatic PRO](https://chimpmatic.com/pro) for advanced Mailchimp integration:

* **Mailchimp Tags** - automatically tag subscribers from form submissions
* **Mailchimp Groups** - assign subscribers to interest groups
* **Unlimited custom merge fields** - map any CF7 field to Mailchimp
* **GDPR consent checkbox** - built-in compliance for email marketing
* **Birthday field support** - sync date fields to Mailchimp
* **Email verification** - validate emails before subscribing
* **Unsubscribe, archive, and delete** subscriber management
* **Priority support** - direct help from the developer

[Learn more about Chimpmatic PRO](https://chimpmatic.com/pro)

= Requirements =

1. WordPress 6.4 or higher
2. Contact Form 7 (5.0 or higher)
3. PHP 7.4 or higher
4. A free or paid Mailchimp account

= Support =

* [Documentation & Help Center](https://chimpmatic.com/help)
* [Getting Started Guide](https://chimpmatic.com/connect-contact-form-7-with-mailchimp)
* [Bug Reports & Feature Requests](https://chimpmatic.com/contact)

== Installation ==

1. Install the plugin from the WordPress Plugin Directory, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Edit any Contact Form 7 form - you will see a new **Mailchimp** tab.
4. Enter your Mailchimp API key, select your audience, and map your fields.

= How to Find Your Mailchimp API Key =

1. Log in to your Mailchimp account.
2. Click your profile icon and select **Account & billing**.
3. Go to **Extras** > **API Keys**.
4. Click **Create A Key** and copy the key.
5. Paste it into the Mailchimp tab in your Contact Form 7 editor.

For a detailed walkthrough with screenshots, see our [Mailchimp API Key Guide](https://chimpmatic.com/how-to-get-your-mailchimp-api-key).

== Frequently Asked Questions ==

= How do I connect Contact Form 7 to Mailchimp? =

After installing Chimpmatic Lite, edit any Contact Form 7 form. Click the **Mailchimp** tab, enter your API key, select your Mailchimp audience, and map your form fields to Mailchimp merge tags. Submissions will sync automatically.

See the full [setup guide](https://chimpmatic.com/connect-contact-form-7-with-mailchimp).

= How do I find my Mailchimp API key? =

Log in to Mailchimp, click your profile icon, go to Account & billing > Extras > API Keys, and click Create A Key. Copy the key and paste it into the Chimpmatic settings in your CF7 form editor.

= Can I use different Mailchimp lists for different forms? =

Yes. Each Contact Form 7 form has its own Mailchimp tab where you can set a different API key, audience, and field mapping.

= Does this support double opt-in? =

Yes. You can choose between single opt-in (subscriber is added immediately) or double opt-in (Mailchimp sends a confirmation email first). This is configured per form.

= Can I add an opt-in checkbox? =

Yes. Chimpmatic Lite includes an opt-in checkbox feature so visitors can choose whether to subscribe to your Mailchimp list when submitting the form.

= What is the difference between Mailchimp tags and groups? =

Tags are labels you assign to subscribers for internal organization (e.g., "From Contact Page"). Groups are subscriber-facing categories that let people choose their interests. Both are available in [Chimpmatic PRO](https://chimpmatic.com/pricing).

= How do I add GDPR consent to Contact Form 7? =

GDPR consent checkbox support is available in [Chimpmatic PRO](https://chimpmatic.com/pro). It adds a required opt-in checkbox that maps to Mailchimp's GDPR marketing permission fields.

= Contact Form 7 not sending to Mailchimp? =

Common fixes: (1) Check your API key is correct and not expired. (2) Verify the email field is mapped properly. (3) Check for trailing spaces in the API key. (4) Make sure your Mailchimp audience is not archived. See our [troubleshooting guide](https://chimpmatic.com/connect-contact-form-7-with-mailchimp).

= Does this work with Mailchimp merge fields? =

Yes. You can map any Contact Form 7 field to Mailchimp merge fields like FNAME, LNAME, ADDRESS, and more. For unlimited custom merge fields, upgrade to [Chimpmatic PRO](https://chimpmatic.com/pro).

== Screenshots ==

1. Mailchimp tab in the Contact Form 7 editor - enter your API key and select your audience.
2. Field mapping interface - connect your CF7 form fields to Mailchimp merge fields.

== Changelog ==

= 0.9.75 =
* Fixed nullable type hint to prevent fatal TypeError.

= 0.9.73 =
* New: Test your form submissions with the preview tool.

= 0.9.69 =
* Improved: Activation hook now runs automatic migration from legacy options.

= 0.9.22 =
* Major performance improvements, security hardening.

= 0.5.64 =
* Fixed debug log cleaner and removed other bugs.

= 0.1.2 =
* Initial release.