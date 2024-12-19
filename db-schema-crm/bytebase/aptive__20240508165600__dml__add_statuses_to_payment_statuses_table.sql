INSERT INTO "billing"."payment_statuses" ("id", "name", "description")
OVERRIDING SYSTEM VALUE 
VALUES
	( 11, 'Suspended', 'A payment is suspended and cannot be processed via gateway'),
	( 12, 'Terminated', 'If the payment is Suspended, it can be Terminated to fully skip the payment'),
	( 13, 'Processed', 'If the payment is Suspended, it can be changed to Processed to keep processing this payment');
