# Qualified checkout reassurance

Issue #2: the plugin replaces the child theme's reassurance text and still named
BPI and UBP after the four-wallet qualification. Change only that literal to
`PayMongo: QRPh, Maya, ShopeePay & GrabPay`. Gateway availability and COD checks
remain unchanged. The copy intentionally matches this launch's selected methods;
revisit the literal when qualifying additional methods.

Six render scenarios cover gateway availability, conditional COD, missing commerce
and gateway lookup errors. No payment, order, refund or shipping behavior changes.

Deploy the eleven-file runtime package with only the main plugin PHP file changed.
Update the worker review and qualification gateway hash maps and the qualification's
review checksum together. Retain all worker executable/identity/cleanup settings.
Root must independently review, snapshot, serialize, verify the public line and
scheduled recovery before activation. Do not overwrite live theme functions.php.
Rollback uses exact preimages plus matching worker pins; keep callbacks/recovery
available and do not restore an old database over customer orders.
