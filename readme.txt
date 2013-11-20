=== Posts to ADN ===
Contributors: maximevalette
Donate link: http://maxime.sh/paypal
Tags: adn, app.net, autopost, posting, post
Requires at least: 3.0
Tested up to: 3.7.1
Stable tag: 1.6.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically posts your new blog articles to your App.net account.

== Description ==

Every time you publish a new article, a new post is created on your App.net account.

Current features:

* Format your post with 6 variables
* Link shortening with bit.ly and YOURLS
* Specify an excerpt length
* Add a delay for your post to ADN
* Send the Featured Image with your post
* Per-post customization
* Publish old posts with the box in the compose view
* App.net Broadcast Channels support

I'm open to suggestions.

Ping me on App.net: http://alpha.app.net/maxime

== Installation ==

1. Copy the posts-to-adn folder into wp-content/plugins
2. Activate the plugin through the Plugins menu
3. Configure your account from the new Posts to ADN Settings submenu

== Changelog ==

= 1.6.4 =
* The traditional bugfix update.

= 1.6.3 =
* Better URL handling and WordPress CS compliance.
* New name for App.net Alerts.
* More secure App.net authentication mechanism.
* Fix file upload.

= 1.6.2 =
* Parameter update for App.net Alerts.

= 1.6.1 =
* New parameters for App.net Alerts.

= 1.6 =
* Now using WP_Http API.
* Now using WP_Filesystem API.
* WordPress Coding Standards compliance.

= 1.5 =
* New settings design.
* Support for ADN Alerts Channels.

= 1.4.1 =
* Fixed a bug where sometimes the post wasn't triggered.

= 1.4 =
* You can now post to ADN already published posts.
* Added YOURLS support for link shortening.
* Now using the new App.net Button to authenticate.
* Added a anti-flood detection to prevent ADN spam.

= 1.3.4 =
* Fixed a bug where the excerpt didn't show up on ADN.

= 1.3.3 =
* You can now chose multiple post types instead of only "post".
* Fixed a timezone bug with the scheduled posts.

= 1.3.2 =
* Added the box for Pending Review posts.
* Fixed a bug where some private posts were sent to ADN.

= 1.3.1 =
* Now using the App.net Files API for the image annotation.
* Using WordPress thumbnail fallback if the Featured Image is not set.

= 1.3 =
* Added the ability to post the Featured Image on ADN.

= 1.2.1 =
* You can now view and delete your delayed ADN posts.

= 1.2 =
* Added an option to delay your posts to ADN.

= 1.1.2 =
* Fixed a bug that prevented the post in some cases.

= 1.1.1 =
* The custom post now works for scheduled posts.
* Added a bit.ly authentication for shortened links.

= 1.1 =
* New meta box on the side of a New Post view.
* You can now customize the post format for every post.
* You can now disable post to ADN for a single post.
* The script now uses the Excerpt content if there's one.
* Fixed a bug that prevented the excerpt length to be saved.

= 1.0.9 =
* Added an {excerpt} tag to display the first words of your article.
* Added a {tags} tag (you see what I did there).

= 1.0.8 =
* You can now use {linkedTitle} tag to use the link entity feature of ADN.

= 1.0.7 =
* Now using the Display Name field for the {author} tag.

= 1.0.6 =
* Minor bugfix.

= 1.0.5 =
* Added the {author} tag in the post format.

= 1.0.4 =
* New WordPress actions to handle published posts.

= 1.0.3 =
* Removed the follow location parameter that messed things up with safe_mode.

= 1.0.2 =
* Prevents to send the post twice to ADN when it's published through XML-RPC.

= 1.0.1 =
* Added a third party server to handle the App.net OAuth.

= 1.0 =
* First version. Enjoy!
