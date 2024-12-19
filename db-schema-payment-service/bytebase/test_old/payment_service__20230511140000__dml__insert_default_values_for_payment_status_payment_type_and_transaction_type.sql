INSERT INTO `payment_statuses` (`id`, `name`, `description`)
VALUES (1, 'AuthCapturing', 'Authorize and Capture'),
       (2, 'Captured', 'Authorize and Capture'),
       (3, 'Authorizing', 'Authorize'),
       (4, 'Authorized', 'Authorize'),
       (5, 'Capturing', 'Capture previously authorized transaction'),
       (6, 'Cancelling', 'Cancel / Void'),
       (7, 'Cancelled', 'Cancel / Void'),
       (8, 'Crediting', 'Send funds back to payor'),
       (9, 'Credited', 'Send funds back to payor'),
       (10, 'Declined', 'Declined');

INSERT INTO `payment_types` (`id`, `name`, `description`, `is_hidden`, `is_enabled`, `sort_order`)
VALUES (1, 'ACH', 'ACH', FALSE, TRUE, 1),
       (2, 'Check', 'Check', TRUE, TRUE, 2),
       (3, 'Visa', 'Visa', FALSE, TRUE, 3),
       (4, 'Mastercard', 'Mastercard', FALSE, TRUE, 4),
       (5, 'Discover', 'Discover', FALSE, TRUE, 5),
       (6, 'Amex', 'Amex', FALSE, TRUE, 6);

INSERT INTO `transaction_types` (`id`, `name`, `description`)
VALUES (1, 'AuthCapture', 'Authorize and Capture'),
       (2, 'Authorize', 'Authorize'),
       (3, 'Capture', 'Capture previously authorized transaction'),
       (4, 'Cancel', 'Cancel / Void'),
       (5, 'Check Status', 'Get the current status of a payment'),
       (6, 'Credit', 'Send funds back to payor'),
       (7, 'Tokenize', 'Create a payment token');