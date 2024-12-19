INSERT INTO billing.scheduled_payment_statuses (id, name, description)
VALUES (1, 'Pending', 'Payment is waiting to be processed by the trigger event'),
       (2, 'Cancelled', 'Processing of the scheduled payment was cancelled'),
       (3, 'Submitted', 'Payment was submitted to the payment processor');

INSERT INTO billing.scheduled_payment_triggers (id, name, description)
VALUES (1, 'Initial Service Completed', 'One-time payment for the completion of the initial service');