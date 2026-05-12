=== ExitSurvey - Smart Exit Survey for WooCommerce ===
Contributors: wdraihan
Tags: woocommerce, exit intent, survey, cart abandonment, feedback
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart exit-intent survey for WooCommerce. Tracks user behavior, shows cart items in the popup, and collects abandonment feedback.

== Description ==

**ExitSurvey** detects when a visitor is about to leave your WooCommerce store and presents a beautiful, non-intrusive survey popup tailored to their exact browsing behavior.

= How it works =

1. **Tracks browsing** — saves page visits (cart, checkout, product, shop) to localStorage
2. **Detects exit intent** — mouse movement toward browser bar triggers the popup
3. **Decides the right question** — based on the visitor's browsing history:
   - Visited checkout → Checkout abandonment question
   - Items in cart (no checkout) → Cart abandonment question
   - Visited product pages → Product interest question
   - Browsed shop/category → Shop browse question
   - Otherwise → General feedback
4. **Shows cart items** — displays the visitor's current cart with images, quantities and totals
5. **Records responses** — saves all answers to your database with cart value and page history

= Features =

* 🛒 **Cart-aware popup** — shows actual cart items with images and totals
* 🎯 **5 smart triggers** — cart, checkout, product, shop, general
* 📝 **Fully customisable questions** — edit text, options, and order from WP Admin
* 📊 **Built-in dashboard** — stats, top answers, trigger breakdown
* 📬 **Email notifications** — get alerted on every new response
* 🎨 **Brand colour** — matches your store's palette
* 🍪 **Cookie throttle** — won't bug the same visitor too often
* 📱 **Mobile toggle** — enable/disable on mobile separately
* 🔒 **Privacy-friendly** — no third-party trackers, all data stays on your server

== Installation ==

1. Upload the `exitsurvey` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins** menu
3. Go to **ExitSurvey → Settings** to configure
4. Go to **ExitSurvey → Questions** to customise survey questions
5. Responses appear under **ExitSurvey → Responses**

== Frequently Asked Questions ==

= Does it work without WooCommerce? =
No. ExitSurvey requires WooCommerce to read cart data and product information.

= Will it slow down my store? =
No. All tracking is done client-side in localStorage. The server is only called when the popup actually opens.

= Can I add my own questions? =
Yes — edit existing questions in **ExitSurvey → Questions**. Full custom question builder coming in v2.

= How do I stop it showing to the same visitor repeatedly? =
Use the **Cookie Duration** setting. Set it to the number of days before showing the survey again.

== Screenshots ==

1. Popup with cart items and multiple-choice question
2. Admin dashboard with response stats
3. Responses table
4. Questions management
5. Settings page

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
First release of ExitSurvey.
