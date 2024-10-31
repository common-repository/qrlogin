=== qrLogin ===
Contributors: qrLogin
Donate link: http://qrLogin.info/
Tags: login, qrcode
Requires PHP: 5.4
Requires at least: 4.6
Tested up to: 4.8.1
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

qrLogin is an authentication system based on the reading of the qr code by the mobile phone and the transfer of authentication data via the http/https protocol to the application or to a web resource.

== Description ==

qrLogin is an authentication system based on the reading of the qr code by the mobile phone and the transfer of authentication data via the http/https protocol to the application or to a web resource.

The mobile application qrLogin by reading of a specially generated qr-code allows:

To authenticate on a web resource or in an application;
To subtract and save account data;
To subtract the credentials of the new account, generate a password or key and send these data to the server to complete the registration of this account.
qrLogin is the unique thing you need to enter the web page.

To log in to the web resource, run qrLogin and scan the qr-code in the form of authentication on the web page or in the application.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/qrLogin` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->qrLogin screen to configure the plugin
4. All users must load apps for phone and save account from User Profile

== Frequently Asked Questions ==

= How it works? =

To log in to the WP Blog, run qrLogin app and scan the qr-code in the login form.

== Screenshots ==

1. Login screen.
2. Settings of qrLogin
3. User Profile: qrcode for save account to App

== Changelog ==

= 1.3.1 =
* changes in javascripts & screens

= 1.3.0 =
* changed session exchange mechanism to database

= 1.2.0 =
* revised login validation mechanism

= 1.1.0 =
* changed to long polling
* changed to js qrcode

= 1.0.1 =
* added suport "System V" (sysvmsg or sysvshm).
* added entering current password on user settings page
* added timer for waiting logging

= 1.0.0 =
* First commit.

== Upgrade Notice ==

= 1.3.1 =
* changes in javascripts & screens

= 1.3.0 =
* changed session exchange mechanism to database
!!! please Deactivate and Activate plugin if you have previous version !!!

= 1.2.0 =
* revised login validation mechanism

= 1.1.0 =
* changed to long polling
* changed to js qrcode

= 1.0.1 =
* added suport "System V" (sysvmsg or sysvshm).
* added entering current password on user settings page
* added timer for waiting logging

== qrLogin app ==

Secure storage of passwords;
Support for OTP passwords;
Ability to generate passwords and OTP keys in the application;
Secure export / import of database of accounts with encryption of secret data;
Ability to authenticate on the resource even if there is no Internet connection;
Adding a new account by scanning of qr-code;
Protection of access to the program and authentication data using a fingerprint or PIN options;
Support for Android and iOS devices;
Absolutely free full-featured version for Android and iOS;
Simple integration with web resources and applications.
To operate with this authentication system the web resource needs only to place the following qr-code that contains the URL for authentication and a unique session identifier in the authentication form. The mobile application will open the specified URL and will pass authentication data.
