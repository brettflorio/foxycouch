
foxycouch
=========

A proof-of-concept datafeed debugger and simple order processing system for [foxycart](http://wiki.foxycart.com/docs:datafeed "Foxycart datafeeds").  Uses CouchDB to store orders and preferences; drop `foxy-orders.couch` into a CouchDB installation to get started.

Developing this, I used CouchDBX to get a Couch server up and running quickly on my Mac.  In order to install `foxy-orders.couch`, navigate to the CouchDBX application in Finder, right-click and choose "Show Package Contents...", then expand Contents > Resources > couchdbx-core > couchdb > var > lib > couchdb and drop `foxy-orders.couch` in there.  Fire up CouchDBX, and you should now have a `foxy-orders` database.


usage
=====

`index.php` is designed to receive datafeed POSTs from Foxycart.  Set up the secret key in document id `preferences` in Couch, or copy the one that's already in there.  See the preamble at the top of `index.php` and `test.xmldatafeed.php` to see how the preferences get loaded up.  Hit `test.xmldatafeed.php` to feed an order to `index.php`, then fire up Futon (or CouchDBX) and check out the document that foxycouch created.

`index.php` is also an order manager: it shows a list of all of the orders its received, notes any error states, and has controls to send an order to a datafeed processor.

what's really cool
==================

First of all, you can hit `test.xmldatafeed.php` multiple times, creating duplicate orders, and foxycouch will always respond back, `foxy`.  Duplicate orders are noted and flagged, but don't cause any deep errors in the code.  Like Couch itself, foxycouch is very relaxed.

Second of all, the entire XML document sent by Foxycart is stored in a field on the document.  In fact, if any errors occur while processing the document, foxycouch notes them and stores the incompletely-parsed document, the XML document, and the errors.  This means that foxycouch can trap its own errors!

Thirdly, receiving a datafeed is now de-coupled processing that datafeed.  This has multiple consequences:
- Complicated processing doesn't have to handle during the lifetime of the HTTP request from Foxycart.
- Foxycouch can send a single order to one or more other processors.
- Foxycouch notes processing errors and stores the processor HTTP response code and body for further debugging.

For example, if, for a single order, you need to decrement inventory and subscribe the customer to your MailChimp list, you don't have to write some franken-script that does both; configure foxycouch to run both processors.  See the `processors` field in the `preferences` document.

where's the evil?
=================

Is this fragile?  Hell yes!  It's a proof of concept.  Works in my development environment, but caveat emptor, here be dragons, etc.  The purpose of this project was to create a tool that I can use for debugging datafeeds, and there are no doubt bugs and incomplete features.


thanks
======

- 960.gs for the layout
- The CouchDB team for making such an awesome tool.